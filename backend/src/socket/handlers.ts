import { Server, Socket } from 'socket.io';
import { redisClient, REDIS_KEYS, safeRedis } from '../redis';

export function setupSocketHandlers(io: Server) {
  io.on('connection', (socket: Socket) => {
    console.log(`🔌 Socket connected: ${socket.id}`);
    
    // Track user id for disconnect cleanup
    let currentUserId: string | null = null;

    // User authenticates their socket
    socket.on('auth', async (data: { user_id: string; token: string }) => {
      if (!data.user_id) return;
      currentUserId = data.user_id;
      
      // Map user_id → socket_id in Redis (expires in 8 hours)
      await safeRedis(async () => {
        await redisClient.set(REDIS_KEYS.USER_SOCKET(data.user_id), socket.id, { EX: 28800 });
        await redisClient.sAdd(REDIS_KEYS.ACTIVE_USERS, data.user_id);
      });
      socket.join(`user:${data.user_id}`);
      socket.emit('auth:ok', { message: 'Authenticated' });
      console.log(`✅ User ${data.user_id} mapped to socket ${socket.id}`);
    });

    // User joins trade room
    socket.on('join:trade', (data: { trade_id: string }) => {
      socket.join(`trade:${data.trade_id}`);
    });

    // User leaves trade room
    socket.on('leave:trade', (data: { trade_id: string }) => {
      socket.leave(`trade:${data.trade_id}`);
    });

    // Disconnect: clean up
    socket.on('disconnect', async () => {
      console.log(`🔌 Socket disconnected: ${socket.id}`);
      if (currentUserId) {
        await safeRedis(async () => {
          await redisClient.sRem(REDIS_KEYS.ACTIVE_USERS, currentUserId as string);
        });
      }
    });
  });
}
