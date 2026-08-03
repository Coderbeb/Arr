/**
 * Timer Watcher — auto-cancels expired trades, auto-resolves expired disputes
 * Runs every 30 seconds
 */
import { query, transaction } from '../db';
import { redisClient, REDIS_KEYS, safeRedis } from '../redis';
import { getIO } from '../socketInstance';

export function startTimerWatcher() {
  console.log('⏱️ Timer Watcher started');
  setInterval(checkExpiredTrades, 5000);
}

async function checkExpiredTrades() {
  try {
    // ── Expire unpaid trades ─────────────────────────────────────
    const expired = await query(
      `SELECT t.id, t.seller_id, t.buyer_id, t.amount, t.order_id
       FROM trades t
       WHERE t.status = 'pending_payment' AND t.payment_deadline < NOW()`
    );

    for (const trade of expired.rows) {
      await transaction(async (client) => {
        await client.query(
          `UPDATE trades SET status = 'cancelled', cancelled_reason = 'payment_timeout' WHERE id = $1`,
          [trade.id]
        );

        // Check if seller requested cancel
        const orderCheck = await client.query(`SELECT cancel_requested FROM orders WHERE id = $1 FOR UPDATE`, [trade.order_id]);
        const cancelRequested = orderCheck.rows[0]?.cancel_requested;

        if (cancelRequested) {
          // Permanently cancel the order
          await client.query(`UPDATE orders SET status = 'cancelled' WHERE id = $1`, [trade.order_id]);

          // Return coins from escrow to seller
          const sellerBal = await client.query('SELECT wallet_balance FROM users WHERE id = $1 FOR UPDATE', [trade.seller_id]);
          const balBefore = parseFloat(sellerBal.rows[0].wallet_balance);
          await client.query(
            `UPDATE users SET escrow_balance = escrow_balance - $1, wallet_balance = wallet_balance + $1 WHERE id = $2`,
            [trade.amount, trade.seller_id]
          );
          await client.query(
            `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
             VALUES ($1, 'escrow_release', $2, $3, $4, $5, $6)`,
            [trade.seller_id, trade.amount, balBefore, balBefore + parseFloat(trade.amount),
             `Sell order cancelled by request (buyer timeout). ₹${trade.amount} released from escrow.`,
             `क्रेता के समय समाप्त होने पर विक्रय ऑर्डर रद्द। ₹${trade.amount} एस्क्रो से वापस।`]
          );

          try {
            getIO().to(`user:${trade.buyer_id}`).emit('trade:cancelled', { trade_id: trade.id, reason: 'Payment timer expired.' });
            getIO().to(`user:${trade.seller_id}`).emit('trade:cancelled', { trade_id: trade.id, reason: 'Buyer did not pay. Your sell order has been cancelled as requested and coins returned.' });
            getIO().to(`user:${trade.buyer_id}`).emit('trade:update', { trade_id: trade.id });
            getIO().to(`user:${trade.seller_id}`).emit('trade:update', { trade_id: trade.id });
          } catch { /* IO not ready */ }

        } else {
          // Reopen the order so another buyer can be matched
          const orderResult = await client.query(
            `UPDATE orders SET status = 'open', matched_at = NULL WHERE id = $1 RETURNING *`,
            [trade.order_id]
          );

          // Re-add order to Redis
          if (orderResult.rows.length > 0) {
            const order = orderResult.rows[0];
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

          // Notify both parties
          try {
            getIO().to(`user:${trade.buyer_id}`).emit('trade:cancelled', { trade_id: trade.id, reason: 'Payment timer expired.' });
            getIO().to(`user:${trade.seller_id}`).emit('trade:cancelled', { trade_id: trade.id, reason: 'Buyer did not pay. Order relisted.' });
            getIO().to(`user:${trade.buyer_id}`).emit('trade:update', { trade_id: trade.id });
            getIO().to(`user:${trade.seller_id}`).emit('trade:update', { trade_id: trade.id });
          } catch { /* IO not ready */ }
        }

        console.log(`⏱️ Trade ${trade.id} expired and cancelled.`);
      });
    }

    // ── Auto-resolve expired dispute proofs ───────────────────────
    const expiredDisputes = await query(
      `SELECT d.id, d.trade_id,
              d.buyer_proof_submitted_at, d.seller_proof_submitted_at,
              t.buyer_id, t.seller_id, t.amount, t.order_id
       FROM disputes d JOIN trades t ON d.trade_id = t.id
       WHERE d.status = 'pending' AND d.proof_deadline < NOW()`
    );

    for (const dispute of expiredDisputes.rows) {
      await transaction(async (client) => {
        const hasBuyerProof = !!dispute.buyer_proof_submitted_at;
        const hasSellerProof = !!dispute.seller_proof_submitted_at;

        // If buyer submitted proof but seller didn't → buyer wins
        // If seller submitted but buyer didn't → seller wins
        // If neither or both → default to seller (escrow holder)
        let winner: 'buyer' | 'seller';
        if (hasBuyerProof && !hasSellerProof) winner = 'buyer';
        else winner = 'seller';

        await client.query(
          `UPDATE disputes SET status = $1, resolution_notes = 'Auto-resolved: proof deadline expired', resolved_at = NOW() WHERE id = $2`,
          [winner === 'buyer' ? 'resolved_buyer' : 'resolved_seller', dispute.id]
        );

        const tradeAmt = parseFloat(dispute.amount);
        if (winner === 'buyer') {
          await client.query('UPDATE users SET escrow_balance = escrow_balance - $1 WHERE id = $2', [tradeAmt, dispute.seller_id]);
          await client.query('UPDATE users SET wallet_balance = wallet_balance + $1 WHERE id = $2', [tradeAmt, dispute.buyer_id]);
          await client.query(`UPDATE trades SET status = 'completed' WHERE id = $1`, [dispute.trade_id]);
        } else {
          await client.query('UPDATE users SET escrow_balance = escrow_balance - $1, wallet_balance = wallet_balance + $1 WHERE id = $2', [tradeAmt, dispute.seller_id]);
          await client.query(`UPDATE trades SET status = 'refunded' WHERE id = $1`, [dispute.trade_id]);
        }

        console.log(`⏱️ Dispute ${dispute.id} auto-resolved → ${winner}`);
      });
    }

    // ── Cancel expired unmatched orders (release escrow) ──────────
    const expiredOrders = await query(
      `SELECT id, seller_id, amount FROM orders WHERE status = 'open' AND expires_at < NOW()`
    );

    for (const order of expiredOrders.rows) {
      await transaction(async (client) => {
        const orderAmt = parseFloat(order.amount);

        // Cancel the order
        await client.query(
          `UPDATE orders SET status = 'cancelled' WHERE id = $1`,
          [order.id]
        );

        // Release escrow back to seller wallet
        const sellerBal = await client.query(
          'SELECT wallet_balance, escrow_balance FROM users WHERE id = $1 FOR UPDATE',
          [order.seller_id]
        );
        const balBefore = parseFloat(sellerBal.rows[0].wallet_balance);

        await client.query(
          `UPDATE users SET escrow_balance = escrow_balance - $1, wallet_balance = wallet_balance + $1 WHERE id = $2`,
          [orderAmt, order.seller_id]
        );

        // Log wallet transaction
        await client.query(
          `INSERT INTO wallet_transactions (user_id, type, amount, balance_before, balance_after, description_en, description_hi)
           VALUES ($1, 'escrow_release', $2, $3, $4, $5, $6)`,
          [order.seller_id, orderAmt, balBefore, balBefore + orderAmt,
           `Sell order expired. ₹${orderAmt} released from escrow.`,
           `विक्रय ऑर्डर समाप्त। ₹${orderAmt} एस्क्रो से वापस।`]
        );

        // Remove from Redis if still there
        await safeRedis(async () => {
          await redisClient.lRem(REDIS_KEYS.OPEN_ORDERS, 0, order.id);
          await redisClient.del(REDIS_KEYS.ORDER(order.id));
        });

        // Notify seller
        try {
          getIO().to(`user:${order.seller_id}`).emit('order:expired', {
            order_id: order.id, amount: orderAmt,
            message: 'Your sell order expired. Coins returned to wallet.',
          });
        } catch { /* IO not ready */ }

        console.log(`⏱️ Order ${order.id} expired — escrow released to seller.`);
      });
    }
  } catch (err) {
    console.error('Timer watcher error:', err);
  }
}
