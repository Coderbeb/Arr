import { FastifyPluginAsync, FastifyRequest, FastifyReply } from 'fastify';
import { query, transaction } from '../db';
import { redisClient, REDIS_KEYS, safeRedis } from '../redis';
import { getIO } from '../socketInstance';
import { generateUpiDeepLinks } from '../services/upiDeepLink';
import { createNotification } from '../services/notification';
import { saveFile } from '../services/fileUpload';
import { analyzeFile } from '../services/proofAnalyzer';

// Middleware: verify JWT
async function requireAuth(req: FastifyRequest, reply: FastifyReply) {
  try { await req.jwtVerify(); }
  catch { return reply.status(401).send({ error: 'Unauthorized' }); }
}

export const tradeRoutes: FastifyPluginAsync = async (fastify) => {
  // ── GET AVAILABLE TRADE AMOUNTS ──────────────────────────────
  fastify.get('/amounts', async (_req, reply) => {
    const result = await query(
      'SELECT id, amount FROM trade_amounts WHERE is_active = true ORDER BY sort_order ASC'
    );
    return reply.send(result.rows);
  });

  // ── POST SELL ORDER ──────────────────────────────────────────
  fastify.post('/sell', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: seller_id } = req.user as any;
    const { amount_id, upi_id, upi_app } = req.body as any;

    const result = await transaction(async (client) => {
      // Get trade amount
      const amtResult = await client.query(
        'SELECT amount FROM trade_amounts WHERE id = $1 AND is_active = true',
        [amount_id]
      );
      if (amtResult.rows.length === 0) {
        return { error: 'Invalid or inactive trade amount', status: 400 };
      }
      const amount = parseFloat(amtResult.rows[0].amount);

      // Get seller info and check balance
      const sellerResult = await client.query(
        'SELECT wallet_balance, upi_id, upi_app, status FROM users WHERE id = $1 FOR UPDATE',
        [seller_id]
      );
      const seller = sellerResult.rows[0];

      if (!seller || seller.status !== 'active') return { error: 'Account is not active', status: 403 };

      const effectiveUpiId = upi_id || seller.upi_id;
      const effectiveUpiApp = upi_app || seller.upi_app || 'gpay';

      if (!effectiveUpiId || !effectiveUpiId.includes('@')) {
        return { error: 'Please enter a valid UPI ID (e.g. name@upi) to receive payment', status: 400 };
      }

      if (effectiveUpiId !== seller.upi_id || effectiveUpiApp !== seller.upi_app) {
        await client.query(
          'UPDATE users SET upi_id = $1, upi_app = $2 WHERE id = $3',
          [effectiveUpiId, effectiveUpiApp, seller_id]
        );
        seller.upi_id = effectiveUpiId;
        seller.upi_app = effectiveUpiApp;
      }

      if (parseFloat(seller.wallet_balance) < amount) return { error: 'Insufficient wallet balance', status: 400 };

      // Get current commission %
      const settingsResult = await client.query('SELECT commission_percent, trade_accept_minutes FROM platform_settings LIMIT 1');
      const commission_pct = parseFloat(settingsResult.rows[0].commission_percent);
      const commission_amt = parseFloat(((amount * commission_pct) / 100).toFixed(2));
      const acceptMinutes = settingsResult.rows[0].trade_accept_minutes || 2;

      // Lock coins in escrow (atomic balance update with row lock)
      const balBefore = parseFloat(seller.wallet_balance);
      await client.query(
        `UPDATE users SET wallet_balance = wallet_balance - $1, escrow_balance = escrow_balance + $1 WHERE id = $2`,
        [amount, seller_id]
      );

      // Log wallet transaction
      await client.query(
        `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
         VALUES ($1, 'escrow_lock', $2, $3, $4, $5, $6)`,
        [seller_id, amount, balBefore, balBefore - amount,
         `Coins locked in escrow for sell order of ₹${amount}`,
         `₹${amount} के विक्रय ऑर्डर के लिए कॉइन एस्क्रो में लॉक किए गए`]
      );

      // Calculate expiry
      const expiresAt = new Date(Date.now() + acceptMinutes * 60 * 1000);

      // Create order
      const orderResult = await client.query(
        `INSERT INTO orders (seller_id, amount, coin_amount, commission_pct, commission_amt,
                             seller_upi_id, seller_upi_app, status, expires_at)
         VALUES ($1, $2, $2, $3, $4, $5, $6, 'open', $7) RETURNING *`,
        [seller_id, amount, commission_pct, commission_amt, seller.upi_id, seller.upi_app, expiresAt]
      );
      const order = orderResult.rows[0];

      // Push to Redis order book (non-blocking — platform works without Redis too)
      await safeRedis(async () => {
        await redisClient.lPush(REDIS_KEYS.OPEN_ORDERS, order.id);
        await redisClient.hSet(REDIS_KEYS.ORDER(order.id), {
          id: order.id, seller_id, amount: amount.toString(),
          commission_pct: commission_pct.toString(), commission_amt: commission_amt.toString(),
          seller_upi_id: seller.upi_id, seller_upi_app: seller.upi_app,
          expires_at: expiresAt.toISOString(),
        });
        await redisClient.expire(REDIS_KEYS.ORDER(order.id), acceptMinutes * 60);
      });

      // Broadcast to all connected users
      try { getIO().emit('order:new', { id: order.id, amount, seller_upi_app: seller.upi_app }); }
      catch { /* IO not ready yet */ }

      return { order, success: true };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.status(201).send({ order: result.order, message: 'Sell order posted successfully' });
  });

  // ── JOIN BUYER QUEUE ─────────────────────────────────────────
  fastify.post('/buy/queue', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: buyer_id } = req.user as any;
    const { amount_id } = req.body as any;

    const userResult = await query('SELECT buy_ban_until FROM users WHERE id = $1', [buyer_id]);
    if (userResult.rows.length > 0) {
      const banUntil = userResult.rows[0].buy_ban_until;
      if (banUntil && new Date(banUntil) > new Date()) {
        const remainingMs = new Date(banUntil).getTime() - Date.now();
        const remainingMinutes = Math.ceil(remainingMs / 60000);
        return reply.status(403).send({ error: `You are temporarily blocked from buying due to repeated cancellations. Please try again in ${remainingMinutes} minute(s).` });
      }
    }

    const amtResult = await query(
      'SELECT amount FROM trade_amounts WHERE id = $1 AND is_active = true', [amount_id]
    );
    if (amtResult.rows.length === 0) return reply.status(400).send({ error: 'Invalid amount' });

    const amount = amtResult.rows[0].amount;
    const member = `${buyer_id}:${amount}`;
    const score = Date.now();

    await safeRedis(async () => {
      await redisClient.zAdd(REDIS_KEYS.BUYER_QUEUE, { score, value: member });
    });

    const position = await safeRedis(async () =>
      await redisClient.zRank(REDIS_KEYS.BUYER_QUEUE, member)
    );

    return reply.send({ message: 'You are in the queue', position: (position ?? 0) + 1 });
  });

  // ── SUBMIT PAYMENT (buyer marks as paid + uploads screenshot) ──
  fastify.post('/pay/:trade_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: buyer_id } = req.user as any;
    const { trade_id } = req.params as any;

    // Parse multipart form data (UTR + screenshot file)
    let utr_number = '';
    let buyer_upi_app = '';
    let screenshotUrl = '';

    const parts = req.parts();
    for await (const part of parts) {
      if (part.type === 'field') {
        if (part.fieldname === 'utr_number') utr_number = (part as any).value as string;
        if (part.fieldname === 'buyer_upi_app') buyer_upi_app = (part as any).value as string;
      } else if (part.type === 'file' && part.fieldname === 'screenshot') {
        const buffer = await part.toBuffer();
        const result = await saveFile(buffer, part.filename || 'screenshot.jpg', part.mimetype, 'trades', trade_id, 'buyer_payment');
        screenshotUrl = result.url;
      }
    }

    // Validate UTR format (12-22 chars alphanumeric)
    if (!utr_number || !/^[A-Za-z0-9]{12,22}$/.test(utr_number)) {
      return reply.status(400).send({ error: 'Invalid UTR number. Must be 12-22 alphanumeric characters.' });
    }

    if (!screenshotUrl) {
      return reply.status(400).send({ error: 'Payment screenshot is required. Please upload a screenshot of your UPI payment confirmation.' });
    }

    // Check UTR not already used
    const utrExists = await query('SELECT id FROM utr_registry WHERE utr_number = $1', [utr_number]);
    if (utrExists.rows.length > 0) {
      return reply.status(409).send({ error: 'This UTR number has already been used.' });
    }

    const result = await transaction(async (client) => {
      const tradeResult = await client.query(
        `SELECT * FROM trades WHERE id = $1 AND buyer_id = $2 AND status = 'pending_payment'`,
        [trade_id, buyer_id]
      );
      if (tradeResult.rows.length === 0) return { error: 'Trade not found or already processed', status: 404 };
      const trade = tradeResult.rows[0];

      if (new Date() > new Date(trade.payment_deadline)) return { error: 'Payment timer expired.', status: 400 };

      // Register UTR
      await client.query(
        'INSERT INTO utr_registry (utr_number, trade_id, user_id) VALUES ($1, $2, $3)',
        [utr_number, trade_id, buyer_id]
      );

      // Update trade with screenshot URL
      await client.query(
        `UPDATE trades SET status = 'payment_submitted', utr_number = $1, buyer_upi_app = $2,
         buyer_payment_screenshot_url = $3, paid_at = NOW() WHERE id = $4`,
        [utr_number, buyer_upi_app, screenshotUrl, trade_id]
      );

      // Notify seller with screenshot info
      try {
        getIO().to(`user:${trade.seller_id}`).emit('trade:payment_submitted', {
          trade_id, utr_number, screenshot_url: screenshotUrl,
          message: 'Buyer has submitted payment with screenshot. Please verify.',
        });
        // Also emit generic trade:update for any listeners
        getIO().to(`user:${trade.seller_id}`).emit('trade:update', { trade_id });
        getIO().to(`user:${trade.buyer_id}`).emit('trade:update', { trade_id });
      } catch { /* IO not ready */ }

      await createNotification(client, {
        user_id: trade.seller_id, type: 'payment_submitted',
        title_en: 'Payment Submitted with Proof', title_hi: 'प्रमाण के साथ भुगतान जमा',
        body_en: `Buyer submitted ₹${trade.amount} payment with UTR: ${utr_number} and screenshot.`,
        body_hi: `खरीदार ने ₹${trade.amount} भुगतान UTR: ${utr_number} और स्क्रीनशॉट के साथ जमा किया।`,
        trade_id,
      });

      return { success: true };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.send({ message: 'Payment submitted with screenshot. Waiting for seller confirmation.' });
  });

  // ── BUYER CANCELS TRADE ──────────────────────────────────────
  fastify.post('/cancel/:trade_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: buyer_id } = req.user as any;
    const { trade_id } = req.params as any;

    const result = await transaction(async (client) => {
      const tradeResult = await client.query(
        `SELECT * FROM trades WHERE id = $1 AND buyer_id = $2 AND status = 'pending_payment'`,
        [trade_id, buyer_id]
      );
      if (tradeResult.rows.length === 0) return { error: 'Trade not found or already processed', status: 404 };
      const trade = tradeResult.rows[0];

      // Mark trade as cancelled
      await client.query(
        `UPDATE trades SET status = 'cancelled', cancelled_reason = 'buyer_cancelled' WHERE id = $1`,
        [trade_id]
      );

      // Handle buyer penalty
      const userRes = await client.query('SELECT consecutive_cancels FROM users WHERE id = $1 FOR UPDATE', [buyer_id]);
      const cancels = (userRes.rows[0]?.consecutive_cancels || 0) + 1;
      let banQuery = '';
      let banParams: any[] = [];
      if (cancels >= 3) {
        banQuery = `, buy_ban_until = NOW() + INTERVAL '15 minutes'`;
      }
      await client.query(`UPDATE users SET consecutive_cancels = $1 ${banQuery} WHERE id = $2`, [cancels, buyer_id]);

      // Check if seller requested cancel
      const orderCheck = await client.query(`SELECT id, cancel_requested, amount FROM orders WHERE id = $1 FOR UPDATE`, [trade.order_id]);
      const orderData = orderCheck.rows[0];

      if (orderData.cancel_requested) {
        // Seller wanted to cancel. Refund escrow and close order.
        await client.query(`UPDATE orders SET status = 'cancelled' WHERE id = $1`, [trade.order_id]);
        
        // Release escrow
        const orderAmt = parseFloat(orderData.amount);
        const sellerBal = await client.query('SELECT wallet_balance FROM users WHERE id = $1 FOR UPDATE', [trade.seller_id]);
        const balBefore = parseFloat(sellerBal.rows[0].wallet_balance);
        await client.query(
          `UPDATE users SET escrow_balance = escrow_balance - $1, wallet_balance = wallet_balance + $1 WHERE id = $2`,
          [orderAmt, trade.seller_id]
        );
        await client.query(
          `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
           VALUES ($1, 'escrow_release', $2, $3, $4, $5, $6)`,
          [trade.seller_id, orderAmt, balBefore, balBefore + orderAmt,
           `Sell order cancelled by request. ₹${orderAmt} released from escrow.`,
           `विक्रय ऑर्डर अनुरोध पर रद्द। ₹${orderAmt} एस्क्रो से वापस।`]
        );

        try {
          getIO().to(`user:${trade.seller_id}`).emit('trade:cancelled', { trade_id, reason: 'Buyer cancelled the trade. Your sell order has been cancelled and coins returned to your wallet as requested.' });
          getIO().to(`user:${buyer_id}`).emit('trade:cancelled', { trade_id, reason: 'You cancelled the trade.' });
          getIO().to(`user:${trade.seller_id}`).emit('trade:update', { trade_id });
          getIO().to(`user:${buyer_id}`).emit('trade:update', { trade_id });
        } catch { /* IO not ready */ }
      } else {
        // Reopen the order so another buyer can be matched
        const orderResult = await client.query(
          `UPDATE orders SET status = 'open', matched_at = NULL WHERE id = $1 RETURNING *`,
          [trade.order_id]
        );

        // Re-add order to Redis for real-time rematching
        if (orderResult.rows.length > 0) {
          const order = orderResult.rows[0];
          // If order hasn't expired yet
          if (new Date(order.expires_at) > new Date()) {
            await safeRedis(async () => {
              await redisClient.lPush(REDIS_KEYS.OPEN_ORDERS, order.id);
              await redisClient.hSet(REDIS_KEYS.ORDER(order.id), {
                id: order.id, seller_id: order.seller_id,
                amount: order.amount.toString(),
                commission_pct: order.commission_pct.toString(),
                commission_amt: order.commission_amt.toString(),
                seller_upi_id: order.seller_upi_id,
                seller_upi_app: order.seller_upi_app,
                expires_at: order.expires_at.toISOString(),
              });
              const remainingSecs = Math.floor((new Date(order.expires_at).getTime() - Date.now()) / 1000);
              if (remainingSecs > 0) {
                await redisClient.expire(REDIS_KEYS.ORDER(order.id), remainingSecs);
              }
            });
          }
        }

        // Notify both parties
        try {
          getIO().to(`user:${trade.seller_id}`).emit('trade:cancelled', { trade_id, reason: 'Buyer cancelled the trade. Your order is back in the queue.' });
          getIO().to(`user:${buyer_id}`).emit('trade:cancelled', { trade_id, reason: 'You cancelled the trade.' });
          getIO().to(`user:${trade.seller_id}`).emit('trade:update', { trade_id });
          getIO().to(`user:${buyer_id}`).emit('trade:update', { trade_id });
        } catch { /* IO not ready */ }
      }

      return { success: true };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.send({ message: 'Trade cancelled successfully.' });
  });

  // ── SELLER CANCELS SELL ORDER ────────────────────────────────
  fastify.post('/sell/cancel/:order_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: seller_id } = req.user as any;
    const { order_id } = req.params as any;

    const result = await transaction(async (client) => {
      const orderResult = await client.query(
        `SELECT * FROM orders WHERE id = $1 AND seller_id = $2 FOR UPDATE`,
        [order_id, seller_id]
      );
      if (orderResult.rows.length === 0) return { error: 'Order not found', status: 404 };
      const order = orderResult.rows[0];

      if (order.status === 'open') {
        // Unmatched order: cancel immediately and refund
        await client.query(`UPDATE orders SET status = 'cancelled' WHERE id = $1`, [order_id]);
        
        const orderAmt = parseFloat(order.amount);
        const sellerBal = await client.query('SELECT wallet_balance FROM users WHERE id = $1 FOR UPDATE', [seller_id]);
        const balBefore = parseFloat(sellerBal.rows[0].wallet_balance);
        
        await client.query(
          `UPDATE users SET escrow_balance = escrow_balance - $1, wallet_balance = wallet_balance + $1 WHERE id = $2`,
          [orderAmt, seller_id]
        );
        
        await client.query(
          `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
           VALUES ($1, 'escrow_release', $2, $3, $4, $5, $6)`,
          [seller_id, orderAmt, balBefore, balBefore + orderAmt,
           `Sell order cancelled. ₹${orderAmt} released from escrow.`,
           `विक्रय ऑर्डर रद्द। ₹${orderAmt} एस्क्रो से वापस।`]
        );

        // Remove from Redis
        await safeRedis(async () => {
          await redisClient.lRem(REDIS_KEYS.OPEN_ORDERS, 0, order.id);
          await redisClient.del(REDIS_KEYS.ORDER(order.id));
        });

        // Notify
        try {
          getIO().to(`user:${seller_id}`).emit('order:cancelled', { order_id: order.id });
          getIO().to(`user:${seller_id}`).emit('trade:update', {}); // Trigger re-fetch
        } catch { /* IO not ready */ }
        
        return { success: true, message: 'Order cancelled successfully and coins returned to wallet.' };
        
      } else if (order.status === 'locked' || order.status === 'disputed') {
        // Matched order: set cancel_requested flag but do not interrupt trade
        await client.query(`UPDATE orders SET cancel_requested = true WHERE id = $1`, [order_id]);
        
        // Find if there's an active trade to notify the seller UI
        const tradeRes = await client.query(`SELECT id FROM trades WHERE order_id = $1 AND status IN ('pending_payment', 'payment_submitted', 'seller_rejected', 'disputed') LIMIT 1`, [order_id]);
        if (tradeRes.rows.length > 0) {
          try { getIO().to(`user:${seller_id}`).emit('trade:update', { trade_id: tradeRes.rows[0].id }); } catch {}
        }
        
        return { success: true, message: 'Cancellation requested. Coins will be returned if the current buyer fails to complete the trade.' };
      } else {
        return { error: 'Order cannot be cancelled in its current state', status: 400 };
      }
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.send({ message: (result as any).message });
  });


  // ── SELLER CONFIRMS RECEIPT ──────────────────────────────────
  fastify.post('/confirm/:trade_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: seller_id } = req.user as any;
    const { trade_id } = req.params as any;

    const result = await transaction(async (client) => {
      const tradeResult = await client.query(
        `SELECT * FROM trades WHERE id = $1 AND seller_id = $2 AND status = 'payment_submitted'`,
        [trade_id, seller_id]
      );
      if (tradeResult.rows.length === 0) return { error: 'Trade not found', status: 404 };
      const trade = tradeResult.rows[0];

      const commAmt = parseFloat(trade.commission_amount);
      const tradeAmt = parseFloat(trade.amount);

      // Update trade and order
      await client.query(`UPDATE trades SET status = 'completed', completed_at = NOW() WHERE id = $1`, [trade_id]);
      await client.query(`UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = $1`, [trade.order_id]);

      // Seller: release escrow (coins leave the system — sold to buyer via UPI)
      const sellerBal = await client.query('SELECT wallet_balance, escrow_balance FROM users WHERE id = $1 FOR UPDATE', [seller_id]);
      const sellerBalBefore = parseFloat(sellerBal.rows[0].wallet_balance);
      await client.query(
        `UPDATE users SET escrow_balance = escrow_balance - $1, total_trades = total_trades + 1 WHERE id = $2`,
        [tradeAmt, seller_id]
      );
      await client.query(
        `INSERT INTO wallet_transactions (user_id, trade_id, type, amount, balance_before, balance_after, description_en, description_hi)
         VALUES ($1, $2, 'escrow_release', $3, $4, $5, $6, $7)`,
        [seller_id, trade_id, tradeAmt, sellerBalBefore, sellerBalBefore,
         `Sold ₹${tradeAmt} coins via trade. Escrow released.`,
         `₹${tradeAmt} कॉइन ट्रेड के माध्यम से बेचे। एस्क्रो जारी।`]
      );

      // ── Enforce daily/weekly earnings cap ──────────────────────
      const settings = await client.query(
        'SELECT max_daily_earning, max_weekly_earning FROM platform_settings LIMIT 1'
      );
      const maxDaily = parseFloat(settings.rows[0].max_daily_earning);
      const maxWeekly = parseFloat(settings.rows[0].max_weekly_earning);

      // Get or create today's earnings record
      const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
      // Calculate week start (Monday)
      const d = new Date();
      const dayOfWeek = d.getDay(); // 0=Sun, 1=Mon, ...
      const mondayOffset = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
      d.setDate(d.getDate() - mondayOffset);
      const weekStart = d.toISOString().slice(0, 10);

      const earningsResult = await client.query(
        `SELECT daily_earned, weekly_earned FROM earnings_tracker
         WHERE user_id = $1 AND date = $2`,
        [trade.buyer_id, today]
      );

      let dailyEarned = 0;
      let weeklyEarned = 0;
      if (earningsResult.rows.length > 0) {
        dailyEarned = parseFloat(earningsResult.rows[0].daily_earned);
        weeklyEarned = parseFloat(earningsResult.rows[0].weekly_earned);
      }

      // Calculate how much commission the buyer can actually receive
      let allowedComm = commAmt;
      const dailyRemaining = Math.max(0, maxDaily - dailyEarned);
      const weeklyRemaining = Math.max(0, maxWeekly - weeklyEarned);
      allowedComm = Math.min(allowedComm, dailyRemaining, weeklyRemaining);
      allowedComm = parseFloat(allowedComm.toFixed(2));

      // Buyer: credit coins + (capped) commission
      const buyerBal = await client.query('SELECT wallet_balance FROM users WHERE id = $1 FOR UPDATE', [trade.buyer_id]);
      const buyerBalBefore = parseFloat(buyerBal.rows[0].wallet_balance);
      const buyerReceives = tradeAmt + allowedComm;
      // Increment total_trades FIRST so milestone check is accurate
      await client.query(
        `UPDATE users SET wallet_balance = wallet_balance + $1, total_trades = total_trades + 1, consecutive_cancels = 0 WHERE id = $2`,
        [buyerReceives, trade.buyer_id]
      );

      const commNote = allowedComm < commAmt
        ? ` (commission capped from ₹${commAmt} to ₹${allowedComm})`
        : '';
      await client.query(
        `INSERT INTO wallet_transactions (user_id, trade_id, type, amount, balance_before, balance_after, description_en, description_hi)
         VALUES ($1, $2, 'credit_commission', $3, $4, $5, $6, $7)`,
        [trade.buyer_id, trade_id, buyerReceives, buyerBalBefore, buyerBalBefore + buyerReceives,
         `Purchased ₹${tradeAmt} coins + ₹${allowedComm} commission.${commNote}`,
         `₹${tradeAmt} कॉइन खरीदे + ₹${allowedComm} कमीशन।`]
      );

      // Upsert earnings tracker
      if (allowedComm > 0) {
        await client.query(
          `INSERT INTO earnings_tracker (user_id, date, daily_earned, weekly_earned, week_start)
           VALUES ($1, $2, $3, $4, $5)
           ON CONFLICT (user_id, date)
           DO UPDATE SET daily_earned = earnings_tracker.daily_earned + $3,
                         weekly_earned = earnings_tracker.weekly_earned + $4`,
          [trade.buyer_id, today, allowedComm, allowedComm, weekStart]
        );
      }

      // Check bonus milestones AFTER total_trades is updated
      await checkBonusMilestones(client, trade.buyer_id);

      // Notify buyer of completion
      try {
        getIO().to(`user:${trade.buyer_id}`).emit('trade:completed', {
          trade_id, amount_received: buyerReceives,
        });
        // Also notify seller of completion
        getIO().to(`user:${seller_id}`).emit('trade:completed', {
          trade_id, amount_received: tradeAmt,
        });
        // Generic update for both
        getIO().to(`user:${trade.buyer_id}`).emit('trade:update', { trade_id });
        getIO().to(`user:${seller_id}`).emit('trade:update', { trade_id });
      } catch { /* IO not ready */ }

      await createNotification(client, {
        user_id: trade.buyer_id, type: 'trade_completed',
        title_en: 'Trade Completed!', title_hi: 'ट्रेड पूर्ण!',
        body_en: `₹${buyerReceives} credited to your wallet.`,
        body_hi: `₹${buyerReceives} आपके वॉलेट में जमा किए गए।`,
        trade_id,
      });

      return { success: true, buyerReceives };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });
    return reply.send({ message: 'Trade confirmed. Coins released to buyer.' });
  });

  // ── SELLER REJECTS → MUST UPLOAD SCREEN RECORDING + BANK STATEMENT → DISPUTE ──
  fastify.post('/reject/:trade_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: seller_id } = req.user as any;
    const { trade_id } = req.params as any;

    // Parse multipart: seller MUST upload screen recording, optionally bank statement
    let sellerRecordingUrl = '';
    let sellerScreenshotUrl = '';
    let sellerBankStatementUrl = '';
    let sellerRecordingHash = '';
    let sellerRecordingBuffer: Buffer | null = null;
    let sellerRecordingMime = '';
    let sellerRecordingSize = 0;
    // Keep all proof buffers for combined AI analysis
    const proofBuffers: { buffer: Buffer; hash: string; mime: string; size: number }[] = [];

    const parts = req.parts();
    for await (const part of parts) {
      if (part.type === 'file') {
        const buffer = await part.toBuffer();
        if (part.fieldname === 'screen_recording') {
          const result = await saveFile(buffer, part.filename || 'recording.mp4', part.mimetype, 'disputes', trade_id, 'seller_recording');
          sellerRecordingUrl = result.url;
          sellerRecordingHash = result.hash;
          sellerRecordingBuffer = buffer;
          sellerRecordingMime = result.mimeType;
          sellerRecordingSize = result.size;
          proofBuffers.push({ buffer, hash: result.hash, mime: result.mimeType, size: result.size });
        } else if (part.fieldname === 'txn_screenshot') {
          const result = await saveFile(buffer, part.filename || 'screenshot.jpg', part.mimetype, 'disputes', trade_id, 'seller_txn');
          sellerScreenshotUrl = result.url;
          proofBuffers.push({ buffer, hash: result.hash, mime: result.mimeType, size: result.size });
        } else if (part.fieldname === 'bank_statement') {
          const result = await saveFile(buffer, part.filename || 'statement.pdf', part.mimetype, 'disputes', trade_id, 'seller_bank_statement');
          sellerBankStatementUrl = result.url;
          proofBuffers.push({ buffer, hash: result.hash, mime: result.mimeType, size: result.size });
        }
      }
    }

    if (!sellerRecordingUrl) {
      return reply.status(400).send({
        error: 'Screen recording is required to reject. Record your UPI app showing your profile and latest transactions.',
      });
    }

    const result = await transaction(async (client) => {
      const tradeResult = await client.query(
        `SELECT * FROM trades WHERE id = $1 AND seller_id = $2 AND status = 'payment_submitted'`,
        [trade_id, seller_id]
      );
      if (tradeResult.rows.length === 0) return { error: 'Trade not found', status: 404 };
      const trade = tradeResult.rows[0];

      const settings = await client.query('SELECT dispute_proof_minutes FROM platform_settings LIMIT 1');
      const proofMinutes = settings.rows[0].dispute_proof_minutes || 30;
      const proofDeadline = new Date(Date.now() + proofMinutes * 60 * 1000);

      // Set trade to seller_rejected first — buyer gets a chance to appeal
      await client.query(`UPDATE trades SET status = 'seller_rejected' WHERE id = $1`, [trade_id]);
      await client.query(`UPDATE orders SET status = 'disputed' WHERE id = $1`, [trade.order_id]);

      // Create dispute with seller's proof already attached
      const disputeResult = await client.query(
        `INSERT INTO disputes (
           trade_id, raised_by, status,
           buyer_utr_number, buyer_upi_screenshot_url,
           seller_screen_recording_url, seller_txn_screenshot_url, seller_profile_recording_url,
           seller_proof_submitted_at, proof_deadline
         ) VALUES ($1, $2, 'pending', $3, $4, $5, $6, $5, NOW(), $7) RETURNING id`,
        [trade_id, trade.buyer_id, trade.utr_number,
         trade.buyer_payment_screenshot_url,
         sellerRecordingUrl, sellerScreenshotUrl, proofDeadline]
      );
      const dispute_id = disputeResult.rows[0].id;

      return { success: true, dispute_id, proofMinutes, trade };
    });

    if ('error' in result) return reply.status((result as any).status).send({ error: (result as any).error });

    const { dispute_id, proofMinutes, trade } = result as any;

    // Trigger async analysis on seller's primary proof (screen recording first, then others)
    const primaryProof = sellerRecordingBuffer
      ? { buffer: sellerRecordingBuffer, hash: sellerRecordingHash, mime: sellerRecordingMime, size: sellerRecordingSize }
      : proofBuffers[0];
    if (primaryProof) {
      analyzeFile(primaryProof.buffer, primaryProof.hash, primaryProof.mime, primaryProof.size, seller_id)
        .then(async (analysis) => {
          await query(
            `UPDATE disputes SET seller_ai_score = $1, seller_ai_breakdown = $2, seller_proof_analysis = $3 WHERE id = $4`,
            [analysis.score, JSON.stringify(analysis.breakdown), JSON.stringify(analysis), dispute_id]
          );
          try { getIO().emit('dispute:scored', { dispute_id, side: 'seller', score: analysis.score }); } catch {}
        })
        .catch(console.error);
    }

    // Notify buyer to appeal
    try {
      getIO().to(`user:${trade.buyer_id}`).emit('trade:seller_rejected', {
        trade_id, dispute_id, deadline_minutes: proofMinutes,
      });
      // Generic update for both
      getIO().to(`user:${trade.buyer_id}`).emit('trade:update', { trade_id });
      getIO().to(`user:${trade.seller_id}`).emit('trade:update', { trade_id });
    } catch { /* IO not ready */ }

    await createNotification(query as any, {
      user_id: trade.buyer_id, type: 'dispute_raised',
      title_en: 'Payment Rejected — Appeal Now', title_hi: 'भुगतान अस्वीकार — अभी अपील करें',
      body_en: `Seller rejected your payment. Upload bank statement & screen recording within ${proofMinutes} min to appeal.`,
      body_hi: `विक्रेता ने आपका भुगतान अस्वीकार किया। अपील के लिए ${proofMinutes} मिनट में बैंक स्टेटमेंट और रिकॉर्डिंग अपलोड करें।`,
      trade_id, dispute_id,
    });

    return reply.send({ dispute_id, message: 'Payment rejected. Buyer has been notified to appeal with proof.' });
  });

  // ── GET ACTIVE TRADE + UPI DEEP LINKS ────────────────────────
  fastify.get('/active/:trade_id', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const { trade_id } = req.params as any;

    const result = await query(
      `SELECT t.*, o.seller_upi_id, o.seller_upi_app, o.seller_qr_url
       FROM trades t JOIN orders o ON t.order_id = o.id
       WHERE t.id = $1 AND (t.buyer_id = $2 OR t.seller_id = $2)`,
      [trade_id, user_id]
    );
    if (result.rows.length === 0) return reply.status(404).send({ error: 'Trade not found' });

    const trade = result.rows[0];
    const deepLinks = generateUpiDeepLinks(trade.seller_upi_id, parseFloat(trade.amount), trade_id);
    return reply.send({ trade, deepLinks });
  });

  // ── GET MY ACTIVE TRADE (restore state on refresh) ───────────
  fastify.get('/my-active', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;

    // Find active trade (buyer or seller side) — include seller_rejected and disputed
    const tradeResult = await query(
      `SELECT t.*, o.seller_upi_id, o.seller_upi_app, o.seller_qr_url,
              CASE WHEN t.buyer_id = $1 THEN 'buyer' ELSE 'seller' END as my_role
       FROM trades t JOIN orders o ON t.order_id = o.id
       WHERE (t.buyer_id = $1 OR t.seller_id = $1)
         AND t.status IN ('pending_payment', 'payment_submitted', 'seller_rejected', 'disputed')
       ORDER BY t.matched_at DESC LIMIT 1`,
      [user_id]
    );

    // Find open sell order (seller waiting for buyer match)
    const openOrderResult = await query(
      `SELECT id, amount, status, created_at, expires_at
       FROM orders WHERE seller_id = $1 AND status = 'open' AND expires_at > NOW()
       ORDER BY created_at DESC LIMIT 1`,
      [user_id]
    );

    const trade = tradeResult.rows[0] || null;
    const openOrder = openOrderResult.rows[0] || null;

    let deepLinks = null;
    if (trade && trade.my_role === 'buyer' && trade.seller_upi_id) {
      deepLinks = generateUpiDeepLinks(trade.seller_upi_id, parseFloat(trade.amount), trade.id);
    }

    // If trade is in a disputed/rejected state, also fetch the dispute ID
    let dispute = null;
    if (trade && (trade.status === 'seller_rejected' || trade.status === 'disputed')) {
      const disputeResult = await query(
        `SELECT id, status, proof_deadline, buyer_proof_submitted_at, seller_proof_submitted_at,
                buyer_ai_score, seller_ai_score, ai_recommendation, ai_confidence
         FROM disputes WHERE trade_id = $1 ORDER BY created_at DESC LIMIT 1`,
        [trade.id]
      );
      dispute = disputeResult.rows[0] || null;
    }

    return reply.send({ trade, openOrder, deepLinks, dispute });
  });

  // ── GET MY TRADE HISTORY ─────────────────────────────────────
  fastify.get('/history', { preHandler: [requireAuth] }, async (req: FastifyRequest, reply: FastifyReply) => {
    const { id: user_id } = req.user as any;
    const result = await query(
      `SELECT t.id, t.amount, t.commission_amount, t.status, t.matched_at, t.completed_at, t.paid_at,
              t.utr_number, t.buyer_payment_screenshot_url, t.buyer_upi_app,
              CASE WHEN t.buyer_id = $1 THEN 'buyer' ELSE 'seller' END as my_role,
              buyer.full_name as buyer_name, buyer.mobile_number as buyer_mobile,
              seller.full_name as seller_name, seller.mobile_number as seller_mobile,
              o.seller_upi_id
       FROM trades t
       LEFT JOIN users buyer ON t.buyer_id = buyer.id
       LEFT JOIN users seller ON t.seller_id = seller.id
       LEFT JOIN orders o ON t.order_id = o.id
       WHERE t.buyer_id = $1 OR t.seller_id = $1
       ORDER BY t.matched_at DESC LIMIT 50`,
      [user_id]
    );
    return reply.send(result.rows);
  });
};

// ── HELPERS ─────────────────────────────────────────────────────
async function checkBonusMilestones(client: any, user_id: string) {
  const userResult = await client.query('SELECT total_trades FROM users WHERE id = $1', [user_id]);
  const totalTrades = userResult.rows[0].total_trades;

  const milestones = await client.query(
    `SELECT bm.id, bm.trade_count, bm.bonus_amount
     FROM bonus_milestones bm
     WHERE bm.is_active = true AND bm.trade_count <= $1
       AND bm.id NOT IN (SELECT milestone_id FROM user_bonuses_claimed WHERE user_id = $2)`,
    [totalTrades, user_id]
  );

  for (const milestone of milestones.rows) {
    const balBefore = await client.query('SELECT wallet_balance FROM users WHERE id = $1', [user_id]);
    const balVal = parseFloat(balBefore.rows[0].wallet_balance);
    await client.query('UPDATE users SET wallet_balance = wallet_balance + $1 WHERE id = $2', [milestone.bonus_amount, user_id]);
    await client.query('INSERT INTO user_bonuses_claimed (user_id, milestone_id) VALUES ($1, $2)', [user_id, milestone.id]);
    await client.query(
      `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
       VALUES ($1, 'bonus', $2, $3, $4, $5, $6)`,
      [user_id, milestone.bonus_amount, balVal, balVal + parseFloat(milestone.bonus_amount),
       `🎉 Bonus for completing ${milestone.trade_count} trades!`,
       `🎉 ${milestone.trade_count} ट्रेड पूर्ण करने पर बोनस!`]
    );
  }
}
