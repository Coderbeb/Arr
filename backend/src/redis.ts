import { createClient } from 'redis';
import dotenv from 'dotenv';
import path from 'path';

dotenv.config({ path: path.resolve(__dirname, '../../.env') });
dotenv.config();

let redisClient: any;
let redisSub: any;
let connected = false;

class InMemoryRedis {
  private kv = new Map<string, string>();
  private lists = new Map<string, string[]>();
  private hashes = new Map<string, Record<string, string>>();
  private sortedSets = new Map<string, {score: number, value: string}[]>();

  async connect() {}
  async get(key: string) { return this.kv.get(key) || null; }
  async set(key: string, val: string) { this.kv.set(key, val); }
  async del(key: string) { this.kv.delete(key); this.lists.delete(key); this.hashes.delete(key); this.sortedSets.delete(key); }
  async lPush(key: string, val: string) { if(!this.lists.has(key)) this.lists.set(key, []); this.lists.get(key)!.unshift(val); }
  async lRange(key: string, start: number, stop: number) { return this.lists.get(key) || []; }
  async lRem(key: string, count: number, val: string) { 
    if(!this.lists.has(key)) return; 
    this.lists.set(key, this.lists.get(key)!.filter(x => x !== val)); 
  }
  async hSet(key: string, obj: any) { 
     const existing = this.hashes.get(key) || {};
     this.hashes.set(key, {...existing, ...obj});
  }
  async hGetAll(key: string) { return this.hashes.get(key) || null; }
  async zAdd(key: string, {score, value}: any) {
    if(!this.sortedSets.has(key)) this.sortedSets.set(key, []);
    const arr = this.sortedSets.get(key)!;
    arr.push({score, value});
    arr.sort((a,b) => a.score - b.score);
  }
  async zRank(key: string, value: string) {
    const arr = this.sortedSets.get(key);
    if(!arr) return null;
    const idx = arr.findIndex(x => x.value === value);
    return idx >= 0 ? idx : null;
  }
  async zRangeWithScores(key: string, start: number, stop: number) {
    return this.sortedSets.get(key) || [];
  }
  async zRem(key: string, value: string) {
    if(!this.sortedSets.has(key)) return;
    this.sortedSets.set(key, this.sortedSets.get(key)!.filter(x => x.value !== value));
  }
  on(event: string, cb: any) {}
}

function createClients() {
  redisClient = createClient({
    url: process.env.REDIS_URL || 'redis://localhost:6379',
    socket: {
      reconnectStrategy: (retries) => {
        if (retries > 0) return false;
        return 1000;
      },
    },
  });

  redisSub = createClient({
    url: process.env.REDIS_URL || 'redis://localhost:6379',
    socket: { reconnectStrategy: () => false },
  });

  redisClient.on('error', () => {});
  redisSub.on('error', () => {});
}

createClients();

async function connectRedis() {
  try {
    await redisClient.connect();
    await redisSub.connect();
    connected = true;
    console.log('✅ Redis connected');
  } catch (err) {
    console.warn('⚠️ Redis not available — falling back to InMemoryRedis mode for local development!');
    redisClient = new InMemoryRedis();
    redisSub = new InMemoryRedis();
    connected = true; // We set to true so safeRedis allows operations!
  }
}

// Helper: safe Redis operation (returns null if Redis is down)
async function safeRedis<T>(fn: () => Promise<T>): Promise<T | null> {
  if (!connected) return null;
  try { return await fn(); }
  catch (err) { console.error('Redis error:', err); return null; }
}

// ── Order Book Keys ──────────────────────────
const REDIS_KEYS = {
  OPEN_ORDERS: 'open_orders',
  ORDER: (id: string) => `order:${id}`,
  BUYER_QUEUE: 'buyer_queue',
  TRADE: (id: string) => `trade:${id}`,
  USER_SOCKET: (userId: string) => `user_socket:${userId}`,
  DISPUTE: (id: string) => `dispute:${id}`,
  ACTIVE_USERS: 'active_users',
};

export { redisClient, redisSub, connectRedis, safeRedis, REDIS_KEYS, connected };
