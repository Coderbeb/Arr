'use client';
import { useEffect, useRef, useState } from 'react';
import { io, Socket } from 'socket.io-client';

const SOCKET_URL = process.env.NEXT_PUBLIC_SOCKET_URL || '';

/**
 * Reusable Socket.IO hook.
 * Connects once per mount, authenticates with the user's JWT,
 * and exposes the socket instance + connection state.
 */
export function useSocket() {
  const socketRef = useRef<Socket | null>(null);
  const [connected, setConnected] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('token');
    // Try direct key first, then extract from JSON user object
    let userId = localStorage.getItem('user_id');
    if (!userId) {
      try {
        const userJson = localStorage.getItem('user');
        if (userJson) {
          const parsed = JSON.parse(userJson);
          userId = parsed.id;
          if (userId) localStorage.setItem('user_id', userId); // backfill for future use
        }
      } catch {}
    }
    if (!token || !userId) return;

    // Create socket connection
    const socket = io(SOCKET_URL, {
      transports: ['websocket', 'polling'],
      reconnectionAttempts: 10,
      reconnectionDelay: 2000,
    });

    socketRef.current = socket;

    socket.on('connect', () => {
      setConnected(true);
      // Authenticate the socket with user identity
      socket.emit('auth', { user_id: userId, token });
    });

    socket.on('auth:ok', () => {
      console.log('🔌 Socket authenticated');
    });

    socket.on('disconnect', () => {
      setConnected(false);
    });

    return () => {
      socket.disconnect();
      socketRef.current = null;
      setConnected(false);
    };
  }, []);

  return { socket: socketRef.current, connected };
}
