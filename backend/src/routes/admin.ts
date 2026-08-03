import { FastifyPluginAsync, FastifyRequest, FastifyReply } from 'fastify';
import { query, transaction } from '../db';
import bcrypt from 'bcryptjs';
import { redisClient, REDIS_KEYS, safeRedis } from '../redis';
import { getIO } from '../socketInstance';
import { createNotification } from '../services/notification';

async function requireAdmin(req: FastifyRequest, reply: FastifyReply) {
  try {
    await req.jwtVerify();
    const { role } = req.user as any;
    if (role !== 'super_admin') return reply.status(403).send({ error: 'Super Admin only' });
  } catch { return reply.status(401).send({ error: 'Unauthorized' }); }
}

export const adminRoutes: FastifyPluginAsync = async (fastify) => {
  // ── GET DASHBOARD STATS ──────────────────────────────────────
  fastify.get('/stats', { preHandler: [requireAdmin] }, async (req, reply) => {
    let activeOnlineUsers = 0;
    await safeRedis(async () => {
      activeOnlineUsers = await redisClient.sCard(REDIS_KEYS.ACTIVE_USERS) || 0;
    });

    const [users, trades, tradesToday, disputes, disputesToday, revenue, regions] = await Promise.all([
      query('SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE status = \'active\') as active FROM users WHERE role = \'user\''),
      query('SELECT COUNT(*) as total, COUNT(*) FILTER (WHERE status = \'completed\') as completed, COUNT(*) FILTER (WHERE status = \'disputed\') as disputed FROM trades'),
      query('SELECT COUNT(*) as today FROM trades WHERE DATE(matched_at) = CURRENT_DATE'),
      query('SELECT COUNT(*) as open FROM disputes WHERE status IN (\'pending\', \'under_review\')'),
      query('SELECT COUNT(*) as today FROM disputes WHERE DATE(created_at) = CURRENT_DATE'),
      query('SELECT COALESCE(SUM(amount), 0) as total FROM wallet_transactions WHERE type = \'credit_commission\' AND DATE(created_at) = CURRENT_DATE'),
      query('SELECT city, COUNT(*) as count FROM users WHERE role = \'user\' AND city IS NOT NULL GROUP BY city ORDER BY count DESC LIMIT 5')
    ]);

    return reply.send({
      users: users.rows[0],
      trades: trades.rows[0],
      trades_today: tradesToday.rows[0].today,
      open_disputes: disputes.rows[0].open,
      fraud_cases_today: disputesToday.rows[0].today,
      revenue_today: revenue.rows[0].total,
      active_online_users: activeOnlineUsers,
      regional_stats: regions.rows
    });
  });

  // ── GET/UPDATE PLATFORM SETTINGS ────────────────────────────
  fastify.get('/settings', { preHandler: [requireAdmin] }, async (req, reply) => {
    const result = await query('SELECT * FROM platform_settings LIMIT 1');
    return reply.send(result.rows[0]);
  });

  fastify.put('/settings', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: admin_id } = req.user as any;
    const {
      registration_open, commission_percent, max_daily_earning,
      max_weekly_earning, trade_accept_minutes, payment_timer_minutes, dispute_proof_minutes
    } = req.body as any;

    await query(
      `UPDATE platform_settings SET
        registration_open = COALESCE($1, registration_open),
        commission_percent = COALESCE($2, commission_percent),
        max_daily_earning = COALESCE($3, max_daily_earning),
        max_weekly_earning = COALESCE($4, max_weekly_earning),
        trade_accept_minutes = COALESCE($5, trade_accept_minutes),
        payment_timer_minutes = COALESCE($6, payment_timer_minutes),
        dispute_proof_minutes = COALESCE($7, dispute_proof_minutes),
        updated_by = $8, updated_at = NOW()`,
      [registration_open, commission_percent, max_daily_earning, max_weekly_earning,
       trade_accept_minutes, payment_timer_minutes, dispute_proof_minutes, admin_id]
    );

    await query(
      `INSERT INTO admin_audit_log (admin_id, action, notes) VALUES ($1, 'updated_platform_settings', $2)`,
      [admin_id, JSON.stringify(req.body)]
    );

    return reply.send({ message: 'Settings updated' });
  });

  // ── TRADE AMOUNTS MANAGEMENT ─────────────────────────────────
  fastify.get('/amounts', { preHandler: [requireAdmin] }, async (req, reply) => {
    const result = await query('SELECT * FROM trade_amounts ORDER BY sort_order ASC');
    return reply.send(result.rows);
  });

  fastify.post('/amounts', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: admin_id } = req.user as any;
    const { amount, sort_order } = req.body as any;

    if (amount < 1000 || amount > 2000) {
      return reply.status(400).send({ error: 'Amount must be between 1000 and 2000' });
    }

    const result = await query(
      'INSERT INTO trade_amounts (amount, sort_order, created_by) VALUES ($1, $2, $3) RETURNING *',
      [amount, sort_order || 0, admin_id]
    );
    return reply.status(201).send(result.rows[0]);
  });

  fastify.put('/amounts/:id', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id } = req.params as any;
    const { is_active } = req.body as any;
    await query('UPDATE trade_amounts SET is_active = $1 WHERE id = $2', [is_active, id]);
    return reply.send({ message: 'Amount updated' });
  });

  // ── BONUS MILESTONES ─────────────────────────────────────────
  fastify.get('/bonuses', { preHandler: [requireAdmin] }, async (req, reply) => {
    const result = await query('SELECT * FROM bonus_milestones ORDER BY trade_count ASC');
    return reply.send(result.rows);
  });

  fastify.post('/bonuses', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: admin_id } = req.user as any;
    const { trade_count, bonus_amount } = req.body as any;
    const result = await query(
      'INSERT INTO bonus_milestones (trade_count, bonus_amount, created_by) VALUES ($1, $2, $3) RETURNING *',
      [trade_count, bonus_amount, admin_id]
    );
    return reply.status(201).send(result.rows[0]);
  });

  // ── USER MANAGEMENT ──────────────────────────────────────────
  fastify.get('/users', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { search, role, status, page = '1' } = req.query as any;
    const offset = (parseInt(page) - 1) * 20;

    let whereClause = 'WHERE 1=1';
    const params: any[] = [];

    if (search) { params.push(`%${search}%`); whereClause += ` AND (full_name ILIKE $${params.length} OR mobile_number ILIKE $${params.length})`; }
    if (role) { params.push(role); whereClause += ` AND role = $${params.length}`; }
    if (status) { params.push(status); whereClause += ` AND status = $${params.length}`; }

    params.push(20, offset);
    const result = await query(
      `SELECT id, full_name, mobile_number, role, status, wallet_balance, total_trades,
              reputation_score, strike_count, created_at, last_login, city
       FROM users ${whereClause}
       ORDER BY created_at DESC LIMIT $${params.length - 1} OFFSET $${params.length}`,
      params
    );
    return reply.send(result.rows);
  });

  fastify.put('/users/:id/status', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: admin_id } = req.user as any;
    const { id } = req.params as any;
    const { status, reason } = req.body as any;

    await query('UPDATE users SET status = $1 WHERE id = $2', [status, id]);
    await query(
      `INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, notes)
       VALUES ($1, $2, 'user', $3, $4)`,
      [admin_id, `user_${status}`, id, reason]
    );

    return reply.send({ message: `User ${status}` });
  });




  // ── ADD COINS TO USER (admin credit) ────────────────────────
  fastify.post('/users/:id/credit', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: admin_id } = req.user as any;
    const { id: user_id } = req.params as any;
    const { amount, reason } = req.body as any;

    await transaction(async (client) => {
      const balBefore = await client.query('SELECT wallet_balance FROM users WHERE id = $1', [user_id]);
      await client.query('UPDATE users SET wallet_balance = wallet_balance + $1 WHERE id = $2', [amount, user_id]);
      await client.query(
        `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
         VALUES ($1, 'admin_credit', $2, $3, $4, $5, $5)`,
        [user_id, amount, balBefore.rows[0].wallet_balance, parseFloat(balBefore.rows[0].wallet_balance) + amount, reason]
      );
      await client.query(
        `INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, notes)
         VALUES ($1, 'admin_credit', 'user', $2, $3)`,
        [admin_id, user_id, `Credited ₹${amount}: ${reason}`]
      );
    });

    return reply.send({ message: `₹${amount} credited to user wallet` });
  });

  // ── CREATE ASSISTANCE MANAGER ACCOUNT ───────────────────────
  fastify.post('/create-manager', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { mobile_number, full_name, date_of_birth, password } = req.body as any;
    const password_hash = await bcrypt.hash(password, 12);

    const result = await query(
      `INSERT INTO users (mobile_number, full_name, date_of_birth, password_hash, role, status, is_verified)
       VALUES ($1, $2, $3, $4, 'assistance', 'active', true) RETURNING id, full_name, mobile_number, role`,
      [mobile_number, full_name, date_of_birth, password_hash]
    );

    return reply.status(201).send(result.rows[0]);
  });

  // ── AUDIT LOG ────────────────────────────────────────────────
  fastify.get('/audit', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const result = await query(
      `SELECT a.*, u.full_name as admin_name
       FROM admin_audit_log a JOIN users u ON a.admin_id = u.id
       ORDER BY a.created_at DESC LIMIT 200`
    );
    return reply.send(result.rows);
  });

  // ── ESCALATED DISPUTES ───────────────────────────────────────
  fastify.get('/escalated-disputes', { preHandler: [requireAdmin] }, async (req, reply) => {
    const result = await query(
      `SELECT d.*, t.amount, t.utr_number,
              buyer.full_name as buyer_name, seller.full_name as seller_name,
              mgr.full_name as escalated_by_name
       FROM disputes d
       JOIN trades t ON d.trade_id = t.id
       JOIN users buyer ON t.buyer_id = buyer.id
       JOIN users seller ON t.seller_id = seller.id
       LEFT JOIN users mgr ON d.resolved_by = mgr.id
       WHERE d.status = 'escalated'
       ORDER BY d.created_at ASC`
    );
    return reply.send(result.rows);
  });

  // ── RESOLVE ESCALATED DISPUTE ────────────────────────────────
  fastify.post('/escalated-disputes/:dispute_id/resolve', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: admin_id } = req.user as any;
    const { dispute_id } = req.params as any;
    const { decision, notes } = req.body as any;

    if (!notes || notes.trim().length < 10) return reply.status(400).send({ error: 'Resolution notes required (min 10 chars)' });
    if (!['buyer', 'seller'].includes(decision)) return reply.status(400).send({ error: 'Invalid decision' });

    const result = await transaction(async (client) => {
      const disputeResult = await client.query(
        `SELECT d.*, t.buyer_id, t.seller_id, t.amount, t.order_id
         FROM disputes d JOIN trades t ON d.trade_id = t.id
         WHERE d.id = $1 AND d.status = 'escalated'`,
        [dispute_id]
      );
      if (disputeResult.rows.length === 0) return { error: 'Dispute not found or already resolved', status: 404 };
      const dispute = disputeResult.rows[0];

      const winnerIsBuyer = decision === 'buyer';
      const tradeAmt = parseFloat(dispute.amount);

      await client.query(
        `UPDATE disputes SET status = $1, resolved_by = $2, resolution_notes = $3, resolved_at = NOW() WHERE id = $4`,
        [winnerIsBuyer ? 'resolved_buyer' : 'resolved_seller', admin_id, notes, dispute_id]
      );

      if (winnerIsBuyer) {
        await client.query('UPDATE users SET escrow_balance = escrow_balance - $1 WHERE id = $2', [tradeAmt, dispute.seller_id]);
        await client.query('UPDATE users SET wallet_balance = wallet_balance + $1, total_trades = total_trades + 1 WHERE id = $2', [tradeAmt, dispute.buyer_id]);
        await client.query(`UPDATE trades SET status = 'completed', completed_at = NOW() WHERE id = $1`, [dispute.trade_id]);
        await client.query('UPDATE users SET strike_count = strike_count + 1, reputation_score = GREATEST(0, reputation_score - 10) WHERE id = $1', [dispute.seller_id]);
        await client.query(`UPDATE users SET status = 'suspended' WHERE id = $1 AND strike_count >= 3`, [dispute.seller_id]);

        await createNotification(client, {
          user_id: dispute.buyer_id, type: 'dispute_resolved',
          title_en: 'Dispute Resolved in Your Favour', title_hi: 'विवाद आपके पक्ष में सुलझा',
          body_en: `Super Admin resolved it. ₹${tradeAmt} credited.`, body_hi: `सुपर एडमिन ने इसे सुलझाया। ₹${tradeAmt} जमा किए गए।`,
          trade_id: dispute.trade_id, dispute_id,
        });
      } else {
        await client.query('UPDATE users SET escrow_balance = escrow_balance - $1, wallet_balance = wallet_balance + $1 WHERE id = $2', [tradeAmt, dispute.seller_id]);
        await client.query(`UPDATE trades SET status = 'refunded', completed_at = NOW() WHERE id = $1`, [dispute.trade_id]);
        await client.query('UPDATE users SET strike_count = strike_count + 1, reputation_score = GREATEST(0, reputation_score - 10) WHERE id = $1', [dispute.buyer_id]);
        await client.query(`UPDATE users SET status = 'suspended' WHERE id = $1 AND strike_count >= 3`, [dispute.buyer_id]);

        await createNotification(client, {
          user_id: dispute.seller_id, type: 'dispute_resolved',
          title_en: 'Dispute Resolved in Your Favour', title_hi: 'विवाद आपके पक्ष में सुलझा',
          body_en: `Super Admin resolved it. ₹${tradeAmt} returned.`, body_hi: `सुपर एडमिन ने इसे सुलझाया। ₹${tradeAmt} वापस किए गए।`,
          trade_id: dispute.trade_id, dispute_id,
        });
      }

      await client.query(
        `INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, notes) VALUES ($1, $2, 'dispute', $3, $4)`,
        [admin_id, `escalated_dispute_resolved_${decision}`, dispute_id, notes]
      );

      try { getIO().emit('dispute:resolved', { dispute_id, decision }); } catch {}
      return { success: true, message: `Escalated dispute resolved in favour of ${decision}` };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.send({ message: result.message });
  });

  // ── USER DETAILS & HISTORY ───────────────────────────────────
  fastify.get('/users/:id/details', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id } = req.params as any;
    const userResult = await query('SELECT * FROM users WHERE id = $1', [id]);
    if (userResult.rows.length === 0) return reply.status(404).send({ error: 'User not found' });
    
    const [walletTx, trades, earnings] = await Promise.all([
      query('SELECT * FROM wallet_transactions WHERE user_id = $1 ORDER BY created_at DESC LIMIT 50', [id]),
      query('SELECT * FROM trades WHERE buyer_id = $1 OR seller_id = $1 ORDER BY matched_at DESC LIMIT 50', [id]),
      query('SELECT * FROM earnings_tracker WHERE user_id = $1 ORDER BY date DESC LIMIT 30', [id])
    ]);
    
    return reply.send({
      user: userResult.rows[0],
      wallet_transactions: walletTx.rows,
      trades: trades.rows,
      earnings: earnings.rows
    });
  });

  // ── DELETE TRADE AMOUNT ──────────────────────────────────────
  fastify.delete('/amounts/:id', { preHandler: [requireAdmin] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id } = req.params as any;
    await query('DELETE FROM trade_amounts WHERE id = $1', [id]);
    return reply.send({ message: 'Trade amount deleted' });
  });

};
