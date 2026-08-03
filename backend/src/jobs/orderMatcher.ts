/**
 * Auto-Matching Engine — runs every 2 seconds
 * Matches waiting buyers to open sell orders using FIFO + amount matching
 */
import { redisClient, REDIS_KEYS, safeRedis } from '../redis';
import { query, transaction } from '../db';
import { getIO } from '../socketInstance';
import { generateUpiDeepLinks } from '../services/upiDeepLink';
import { createNotification } from '../services/notification';

let isRunning = false;

export function startMatchingEngine() {
  console.log('🔄 Order Matching Engine started');
  setInterval(runMatchingCycle, 2000);
}

async function runMatchingCycle() {
  if (isRunning) return;
  isRunning = true;

  try {
    const openOrderIds = await safeRedis(async () => await redisClient.lRange(REDIS_KEYS.OPEN_ORDERS, 0, -1));
    if (!openOrderIds || openOrderIds.length === 0) { isRunning = false; return; }

    const buyerEntries = await safeRedis(async () => await redisClient.zRangeWithScores(REDIS_KEYS.BUYER_QUEUE, 0, -1));
    if (!buyerEntries || buyerEntries.length === 0) { isRunning = false; return; }

    const matchedBuyers = new Set<string>();

    for (const orderId of openOrderIds) {
      const orderData = await safeRedis(async () => await redisClient.hGetAll(REDIS_KEYS.ORDER(orderId)));
      if (!orderData || !orderData.amount) continue;

      // Verify order still open in DB
      const orderDb = await query(
        `SELECT id, seller_id FROM orders WHERE id = $1 AND status = 'open' AND expires_at > NOW()`,
        [orderId]
      );
      if (orderDb.rows.length === 0) {
        await safeRedis(async () => {
          await redisClient.lRem(REDIS_KEYS.OPEN_ORDERS, 0, orderId);
          await redisClient.del(REDIS_KEYS.ORDER(orderId));
        });
        continue;
      }

      const orderAmount = parseFloat(orderData.amount);

      // Find oldest buyer matching this amount (member format: "buyerId:amount")
      for (const entry of buyerEntries) {
        const [buyerId, amountStr] = entry.value.split(':');
        if (matchedBuyers.has(buyerId)) continue;
        if (parseFloat(amountStr) !== orderAmount) continue;

        // Don't match buyer to their own sell order
        if (buyerId === orderData.seller_id) continue;

        try {
          await createTrade(orderId, orderData, buyerId);
          matchedBuyers.add(buyerId);

          await safeRedis(async () => {
            await redisClient.lRem(REDIS_KEYS.OPEN_ORDERS, 0, orderId);
            await redisClient.del(REDIS_KEYS.ORDER(orderId));
            await redisClient.zRem(REDIS_KEYS.BUYER_QUEUE, entry.value);
          });
          break;
        } catch (err) {
          console.error(`Match error for order ${orderId}:`, err);
        }
      }
    }
  } catch (err) {
    console.error('Matching engine error:', err);
  } finally {
    isRunning = false;
  }
}

async function createTrade(orderId: string, orderData: any, buyerId: string) {
  await transaction(async (client) => {
    const settings = await client.query('SELECT payment_timer_minutes FROM platform_settings LIMIT 1');
    const paymentMinutes = settings.rows[0].payment_timer_minutes || 30;
    const paymentDeadline = new Date(Date.now() + paymentMinutes * 60 * 1000);

    await client.query(`UPDATE orders SET status = 'locked', matched_at = NOW() WHERE id = $1`, [orderId]);

    const tradeResult = await client.query(
      `INSERT INTO trades (order_id, buyer_id, seller_id, amount, commission_amount, status, payment_deadline)
       VALUES ($1, $2, $3, $4, $5, 'pending_payment', $6) RETURNING *`,
      [orderId, buyerId, orderData.seller_id, parseFloat(orderData.amount), parseFloat(orderData.commission_amt), paymentDeadline]
    );
    const trade = tradeResult.rows[0];
    const deepLinks = generateUpiDeepLinks(orderData.seller_upi_id, parseFloat(orderData.amount), trade.id);

    // Notify buyer and seller directly via their user rooms
    try {
      getIO().to(`user:${buyerId}`).emit('trade:matched', {
        trade_id: trade.id, order_id: orderId, amount: orderData.amount,
        seller_upi_id: orderData.seller_upi_id, seller_upi_app: orderData.seller_upi_app,
        deep_links: deepLinks, payment_deadline: paymentDeadline,
      });

      getIO().to(`user:${orderData.seller_id}`).emit('trade:matched', {
        trade_id: trade.id, amount: orderData.amount, message: 'Buyer matched. Waiting for payment.',
      });

      // Emit order:matched for seller's TradeModule (clears openOrder state)
      getIO().to(`user:${orderData.seller_id}`).emit('order:matched', {
        trade_id: trade.id, order_id: orderId, amount: orderData.amount,
      });

      // Generic update for both
      getIO().to(`user:${buyerId}`).emit('trade:update', { trade_id: trade.id });
      getIO().to(`user:${orderData.seller_id}`).emit('trade:update', { trade_id: trade.id });
    } catch { /* IO not ready */ }

    await createNotification(client, {
      user_id: buyerId, type: 'trade_matched',
      title_en: 'Trade Matched!', title_hi: 'ट्रेड मिला!',
      body_en: `Pay ₹${orderData.amount} within ${paymentMinutes} minutes.`,
      body_hi: `${paymentMinutes} मिनट में ₹${orderData.amount} भुगतान करें।`,
      trade_id: trade.id,
    });

    console.log(`✅ Trade ${trade.id} | Buyer: ${buyerId} ↔ Seller: ${orderData.seller_id}`);
  });
}
