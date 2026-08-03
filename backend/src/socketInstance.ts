/**
 * Shared Socket.IO instance — breaks circular import between server.ts and routes.
 * Routes import getIO() from here instead of importing from server.ts.
 */
import { Server } from 'socket.io';

let io: Server | null = null;

export function setIO(instance: Server) {
  io = instance;
}

export function getIO(): Server {
  if (!io) {
    throw new Error('Socket.IO not initialized yet. Call setIO() first.');
  }
  return io;
}
