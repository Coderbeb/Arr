import Fastify from 'fastify';
import cors from '@fastify/cors';
import jwt from '@fastify/jwt';
import multipart from '@fastify/multipart';
import fastifyStatic from '@fastify/static';
import { Server } from 'socket.io';
import dotenv from 'dotenv';
import path from 'path';

import { pool } from './db';
import { connectRedis } from './redis';
import { authRoutes } from './routes/auth';
import { tradeRoutes } from './routes/trade';
import { walletRoutes } from './routes/wallet';
import { disputeRoutes } from './routes/dispute';
import { adminRoutes } from './routes/admin';
import { assistanceRoutes } from './routes/assistance';
import { setupSocketHandlers } from './socket/handlers';
import { startMatchingEngine } from './jobs/orderMatcher';
import { startTimerWatcher } from './jobs/timerWatcher';
import { setIO } from './socketInstance';

// Load .env from root if running via root server, else from local backend dir
dotenv.config({ path: path.resolve(__dirname, '../../.env') });
dotenv.config(); // fallback to backend/.env if it exists

const app = Fastify({ logger: false });

/**
 * Registers all Fastify plugins, routes, etc. but does NOT call app.listen().
 * This allows the root custom server to mount Fastify onto its own http.Server.
 */
export async function setupFastify() {
  await app.register(cors, {
    origin: true, // allow all origins (same-origin in production)
    credentials: true,
    methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH', 'HEAD'],
  });

  await app.register(jwt, {
    secret: process.env.JWT_SECRET || 'fallback_secret',
  });

  await app.register(multipart, {
    limits: {
      fileSize: 50 * 1024 * 1024, // 50MB max (for screen recordings)
      files: 5,
    },
  });

  // Serve uploaded proof files
  await app.register(fastifyStatic, {
    root: path.resolve(__dirname, '../uploads'),
    prefix: '/uploads/',
    decorateReply: false,
  });

  // Register routes
  await app.register(authRoutes, { prefix: '/api/auth' });
  await app.register(tradeRoutes, { prefix: '/api/trade' });
  await app.register(walletRoutes, { prefix: '/api/wallet' });
  await app.register(disputeRoutes, { prefix: '/api/dispute' });
  await app.register(adminRoutes, { prefix: '/api/admin' });
  await app.register(assistanceRoutes, { prefix: '/api/assistance' });

  // Health check
  app.get('/health', async () => ({ status: 'ok', timestamp: new Date().toISOString() }));

  // Prepare Fastify (compile schemas, etc.) without listening
  await app.ready();

  return app;
}

/**
 * Sets up Socket.IO on the given http server, starts background jobs.
 */
export async function setupSocketAndJobs(httpServer: any) {
  const io = new Server(httpServer, {
    cors: {
      origin: true,
      methods: ['GET', 'POST'],
      credentials: true,
    },
  });

  setIO(io);
  setupSocketHandlers(io);

  // Connect Redis then start background jobs
  await connectRedis();
  startMatchingEngine();
  startTimerWatcher();

  return io;
}

/**
 * Standalone mode: when running `tsx backend/src/server.ts` directly.
 * This is the original behavior for backward compatibility.
 */
async function standalone() {
  await setupFastify();

  const port = parseInt(process.env.PORT || '3001');
  await app.listen({ port, host: '0.0.0.0' });

  await setupSocketAndJobs(app.server);

  console.log(`🚀 Arr Wallet Backend running standalone on port ${port}`);
}

// Only run standalone if this file is the entry point (not imported)
if (require.main === module) {
  standalone().catch((err) => {
    console.error('Failed to start server:', err);
    process.exit(1);
  });
}

export { app };
