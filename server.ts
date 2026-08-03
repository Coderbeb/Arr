/**
 * Unified Custom Server
 * Runs Fastify (API + Socket.IO) and Next.js on a single port.
 */
import http from 'http';
import path from 'path';
import dotenv from 'dotenv';
import os from 'os';

// Load env from root before anything else
dotenv.config({ path: path.resolve(__dirname, '.env') });

async function main() {
  // ── 1. Boot Fastify (API routes) ──────────────────────────────
  const { setupFastify, setupSocketAndJobs, app: fastifyApp } = await import(
    './backend/src/server'
  );
  await setupFastify();

  // ── 2. Boot Next.js ───────────────────────────────────────────
  const port = parseInt(process.env.PORT || '3000');
  const next = (await import('next')).default;
  const nextApp = next({
    dev: process.env.NODE_ENV !== 'production',
    dir: path.resolve(__dirname, 'frontend'),
    hostname: '0.0.0.0',
    port,
  });
  await nextApp.prepare();
  const nextHandler = nextApp.getRequestHandler();

  // ── 3. Create unified HTTP server ─────────────────────────────
  const httpServer = http.createServer((req, res) => {
    const url = req.url || '';

    // Route /api/* and /health to Fastify
    if (url.startsWith('/api/') || url === '/health') {
      fastifyApp.server.emit('request', req, res);
      return;
    }

    // Everything else goes to Next.js
    nextHandler(req, res);
  });

  // ── 4. Wire Fastify to our custom http server ─────────────────
  // Fastify needs a server to inject requests into.
  // We use fastify.server (which was created by app.ready()) to pipe
  // requests through. But since we're routing via the httpServer above,
  // we actually need Fastify to handle the raw req/res directly.
  //
  // The trick: Fastify's internal routing works via its `.routing` method.
  // We replace the emit('request') above with direct routing.

  // Override the request routing to use Fastify's internal router
  const fastifyRouting = (fastifyApp as any).routing.bind(fastifyApp);

  // Re-create the http server with proper routing
  httpServer.removeAllListeners('request');
  httpServer.on('request', (req, res) => {
    const url = req.url || '';

    if (url.startsWith('/api/') || url === '/health') {
      fastifyRouting(req, res);
      return;
    }

    nextHandler(req, res);
  });

  // ── 5. Attach Socket.IO + background jobs ─────────────────────
  await setupSocketAndJobs(httpServer);

  // ── 6. Start listening ────────────────────────────────────────
  httpServer.listen(port, '0.0.0.0', () => {
    // Get local network IP
    const interfaces = os.networkInterfaces();
    let networkIp = 'localhost';
    for (const name of Object.keys(interfaces)) {
      for (const iface of interfaces[name] || []) {
        if (iface.family === 'IPv4' && !iface.internal) {
          networkIp = iface.address;
          break;
        }
      }
      if (networkIp !== 'localhost') break;
    }

    const localUrl = `http://localhost:${port}`;
    const networkUrl = `http://${networkIp}:${port}`;

    console.log('');
    console.log('  ╔═══════════════════════════════════════════════════════╗');
    console.log('  ║             🪙 Arr Wallet — Unified Server            ║');
    console.log('  ╠═══════════════════════════════════════════════════════╣');
    console.log(`  ║  🌐 Local:       ${localUrl.padEnd(32)} ║`);
    console.log(`  ║  📱 Network:     ${networkUrl.padEnd(32)} ║`);
    console.log('  ╚═══════════════════════════════════════════════════════╝');
    console.log('');
  });
}

main().catch((err) => {
  console.error('❌ Failed to start unified server:', err);
  process.exit(1);
});
