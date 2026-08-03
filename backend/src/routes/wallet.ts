import { FastifyPluginAsync, FastifyRequest, FastifyReply } from 'fastify';
import { query } from '../db';

async function requireAuth(req: FastifyRequest, reply: FastifyReply) {
  try { await req.jwtVerify(); }
  catch { return reply.status(401).send({ error: 'Unauthorized' }); }
}

export const walletRoutes: FastifyPluginAsync = async (fastify) => {
  // ── GET WALLET BALANCE & EARNINGS ───────────────────────────
  fastify.get('/balance', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;

    const [userResult, earningsResult] = await Promise.all([
      query('SELECT wallet_balance, escrow_balance FROM users WHERE id = $1', [user_id]),
      query(
        `SELECT COALESCE(daily_earned, 0) as daily_earned, COALESCE(weekly_earned, 0) as weekly_earned
         FROM earnings_tracker WHERE user_id = $1 AND date = CURRENT_DATE`,
        [user_id]
      ),
    ]);

    const settings = await query('SELECT max_daily_earning, max_weekly_earning FROM platform_settings LIMIT 1');

    return reply.send({
      wallet_balance: userResult.rows[0]?.wallet_balance || 0,
      escrow_balance: userResult.rows[0]?.escrow_balance || 0,
      daily_earned: earningsResult.rows[0]?.daily_earned || 0,
      weekly_earned: earningsResult.rows[0]?.weekly_earned || 0,
      max_daily_earning: settings.rows[0]?.max_daily_earning || 500,
      max_weekly_earning: settings.rows[0]?.max_weekly_earning || 2000,
    });
  });

  // ── GET TRANSACTION HISTORY ──────────────────────────────────
  fastify.get('/transactions', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const { lang } = req.query as any;

    const result = await query(
      `SELECT id, type, amount, balance_before, balance_after,
              CASE WHEN $2 = 'hi' THEN description_hi ELSE description_en END as description,
              created_at
       FROM wallet_transactions WHERE user_id = $1
       ORDER BY created_at DESC LIMIT 100`,
      [user_id, lang || 'en']
    );

    return reply.send(result.rows);
  });

  // ── GET NOTIFICATIONS ───────────────────────────────────────
  fastify.get('/notifications', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const { lang } = req.query as any;

    const result = await query(
      `SELECT id, type,
              CASE WHEN $2 = 'hi' THEN title_hi ELSE title_en END as title,
              CASE WHEN $2 = 'hi' THEN body_hi ELSE body_en END as body,
              is_read, trade_id, dispute_id, created_at
       FROM notifications WHERE user_id = $1
       ORDER BY created_at DESC LIMIT 50`,
      [user_id, lang || 'en']
    );

    return reply.send(result.rows);
  });

  // ── MARK NOTIFICATIONS READ ──────────────────────────────────
  fastify.put('/notifications/read', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    await query('UPDATE notifications SET is_read = true WHERE user_id = $1', [user_id]);
    return reply.send({ message: 'All notifications marked as read' });
  });
};
