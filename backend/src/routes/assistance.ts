import { FastifyPluginAsync, FastifyRequest, FastifyReply } from 'fastify';
import { query, transaction } from '../db';
import { redisClient, REDIS_KEYS, safeRedis } from '../redis';
import { getIO } from '../socketInstance';
import { createNotification } from '../services/notification';

async function requireAssistance(req: FastifyRequest, reply: FastifyReply) {
  try {
    await req.jwtVerify();
    const { role } = req.user as any;
    if (role !== 'assistance' && role !== 'super_admin') return reply.status(403).send({ error: 'Access denied' });
  } catch { return reply.status(401).send({ error: 'Unauthorized' }); }
}

export const assistanceRoutes: FastifyPluginAsync = async (fastify) => {
  // ── GET ALL OPEN DISPUTES ────────────────────────────────────
  fastify.get('/disputes', { preHandler: [requireAssistance] }, async (_req, reply) => {
    const result = await query(
      `SELECT d.id, d.status, d.buyer_ai_score, d.seller_ai_score,
              d.ai_recommendation, d.ai_confidence,
              d.buyer_proof_submitted_at, d.seller_proof_submitted_at,
              d.proof_deadline, d.created_at,
              t.amount, t.utr_number,
              buyer.full_name as buyer_name, buyer.total_trades as buyer_trades, buyer.strike_count as buyer_strikes,
              seller.full_name as seller_name, seller.total_trades as seller_trades, seller.strike_count as seller_strikes
       FROM disputes d
       JOIN trades t ON d.trade_id = t.id
       JOIN users buyer ON t.buyer_id = buyer.id
       JOIN users seller ON t.seller_id = seller.id
       WHERE d.status IN ('pending', 'under_review')
       ORDER BY d.created_at ASC`
    );
    return reply.send(result.rows);
  });

  // ── GET FULL DISPUTE DETAIL ──────────────────────────────────
  fastify.get('/disputes/:dispute_id', { preHandler: [requireAssistance] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { dispute_id } = req.params as any;
    const result = await query(
      `SELECT d.*,
              t.amount, t.utr_number, t.buyer_payment_screenshot_url, t.matched_at, t.paid_at,
              buyer.id as buyer_id, buyer.full_name as buyer_name, buyer.mobile_number as buyer_mobile,
              buyer.total_trades as buyer_total_trades, buyer.strike_count as buyer_strikes,
              buyer.reputation_score as buyer_reputation,
              seller.id as seller_id, seller.full_name as seller_name, seller.mobile_number as seller_mobile,
              seller.total_trades as seller_total_trades, seller.strike_count as seller_strikes,
              seller.reputation_score as seller_reputation,
              (SELECT COUNT(*) FROM disputes d2 JOIN trades t2 ON d2.trade_id = t2.id WHERE t2.buyer_id = buyer.id) as buyer_past_disputes,
              (SELECT COUNT(*) FROM disputes d2 JOIN trades t2 ON d2.trade_id = t2.id WHERE t2.seller_id = seller.id) as seller_past_disputes
       FROM disputes d
       JOIN trades t ON d.trade_id = t.id
       JOIN users buyer ON t.buyer_id = buyer.id
       JOIN users seller ON t.seller_id = seller.id
       WHERE d.id = $1`,
      [dispute_id]
    );
    if (result.rows.length === 0) return reply.status(404).send({ error: 'Dispute not found' });
    return reply.send(result.rows[0]);
  });

  // ── RESOLVE DISPUTE ──────────────────────────────────────────
  fastify.post('/disputes/:dispute_id/resolve', { preHandler: [requireAssistance] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: manager_id } = req.user as any;
    const { dispute_id } = req.params as any;
    const { decision, notes } = req.body as any;

    if (!notes || notes.trim().length < 10) return reply.status(400).send({ error: 'Resolution notes required (min 10 chars)' });
    if (!['buyer', 'seller', 'escalate'].includes(decision)) return reply.status(400).send({ error: 'Invalid decision' });

    const result = await transaction(async (client) => {
      const disputeResult = await client.query(
        `SELECT d.*, t.buyer_id, t.seller_id, t.amount, t.order_id
         FROM disputes d JOIN trades t ON d.trade_id = t.id
         WHERE d.id = $1 AND d.status IN ('pending', 'under_review')`,
        [dispute_id]
      );
      if (disputeResult.rows.length === 0) return { error: 'Dispute not found or already resolved', status: 404 };
      const dispute = disputeResult.rows[0];

      if (decision === 'escalate') {
        await client.query(
          `UPDATE disputes SET status = 'escalated', resolved_by = $1, resolution_notes = $2 WHERE id = $3`,
          [manager_id, notes, dispute_id]
        );
        try { getIO().emit('dispute:escalated', { dispute_id }); } catch {}
        return { success: true, message: 'Dispute escalated to Super Admin' };
      }

      const winnerIsBuyer = decision === 'buyer';
      const tradeAmt = parseFloat(dispute.amount);

      await client.query(
        `UPDATE disputes SET status = $1, resolved_by = $2, resolution_notes = $3, resolved_at = NOW() WHERE id = $4`,
        [winnerIsBuyer ? 'resolved_buyer' : 'resolved_seller', manager_id, notes, dispute_id]
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
          body_en: `₹${tradeAmt} credited.`, body_hi: `₹${tradeAmt} जमा किए गए।`,
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
          body_en: `₹${tradeAmt} returned.`, body_hi: `₹${tradeAmt} वापस किए गए।`,
          trade_id: dispute.trade_id, dispute_id,
        });
      }

      await client.query(
        `INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, notes) VALUES ($1, $2, 'dispute', $3, $4)`,
        [manager_id, `dispute_resolved_${decision}`, dispute_id, notes]
      );

      try { getIO().emit('dispute:resolved', { dispute_id, decision }); } catch {}
      return { success: true, message: `Dispute resolved in favour of ${decision}` };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.send({ message: result.message });
  });

  // ── POST SELL ORDER (assistance seeds coins) ─────────────────
  fastify.post('/sell', { preHandler: [requireAssistance] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: seller_id } = req.user as any;
    const { amount_id } = req.body as any;

    const result = await transaction(async (client) => {
      const amtResult = await client.query('SELECT amount FROM trade_amounts WHERE id = $1 AND is_active = true', [amount_id]);
      if (amtResult.rows.length === 0) return { error: 'Invalid trade amount', status: 400 };
      const amount = parseFloat(amtResult.rows[0].amount);

      const sellerResult = await client.query('SELECT wallet_balance, upi_id, upi_app FROM users WHERE id = $1 FOR UPDATE', [seller_id]);
      const seller = sellerResult.rows[0];
      if (!seller.upi_id) return { error: 'Set your UPI ID in profile first', status: 400 };
      if (parseFloat(seller.wallet_balance) < amount) return { error: 'Insufficient balance', status: 400 };

      const settingsResult = await client.query('SELECT commission_percent, trade_accept_minutes FROM platform_settings LIMIT 1');
      const commission_pct = parseFloat(settingsResult.rows[0].commission_percent);
      const commission_amt = parseFloat(((amount * commission_pct) / 100).toFixed(2));
      const acceptMinutes = settingsResult.rows[0].trade_accept_minutes || 2;
      const expiresAt = new Date(Date.now() + acceptMinutes * 60 * 1000);

      await client.query(`UPDATE users SET wallet_balance = wallet_balance - $1, escrow_balance = escrow_balance + $1 WHERE id = $2`, [amount, seller_id]);

      const orderResult = await client.query(
        `INSERT INTO orders (seller_id, amount, coin_amount, commission_pct, commission_amt, seller_upi_id, seller_upi_app, status, expires_at)
         VALUES ($1, $2, $2, $3, $4, $5, $6, 'open', $7) RETURNING *`,
        [seller_id, amount, commission_pct, commission_amt, seller.upi_id, seller.upi_app, expiresAt]
      );

      await safeRedis(async () => {
        const order = orderResult.rows[0];
        await redisClient.lPush(REDIS_KEYS.OPEN_ORDERS, order.id);
        await redisClient.hSet(REDIS_KEYS.ORDER(order.id), {
          id: order.id, seller_id, amount: amount.toString(),
          commission_pct: commission_pct.toString(), commission_amt: commission_amt.toString(),
          seller_upi_id: seller.upi_id, seller_upi_app: seller.upi_app,
          expires_at: expiresAt.toISOString(),
        });
      });

      try { getIO().emit('order:new', { id: orderResult.rows[0].id, amount }); } catch {}
      return { success: true, order: orderResult.rows[0] };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.status(201).send({ order: result.order, message: 'Sell order posted by assistance' });
  });

  // ── GET ALL ACTIVE TRADES ────────────────────────────────────
  fastify.get('/trades', { preHandler: [requireAssistance] }, async (_req, reply) => {
    const result = await query(
      `SELECT t.id, t.amount, t.status, t.matched_at, t.payment_deadline,
              buyer.full_name as buyer_name, seller.full_name as seller_name
       FROM trades t
       JOIN users buyer ON t.buyer_id = buyer.id
       JOIN users seller ON t.seller_id = seller.id
       WHERE t.status NOT IN ('completed', 'cancelled', 'refunded')
       ORDER BY t.matched_at DESC LIMIT 100`
    );
    return reply.send(result.rows);
  });
};
