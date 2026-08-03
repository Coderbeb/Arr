import { Pool } from 'pg';
import dotenv from 'dotenv';
import path from 'path';
import dns from 'dns';

// Force IPv4 DNS resolution — belt-and-suspenders (also done in dns-fix.cjs preload)
if (dns.setDefaultResultOrder) {
  dns.setDefaultResultOrder('ipv4first');
}
// Override dns.lookup to force family=4 if not already patched by preload
if (!(dns.lookup as any).__ipv4Patched) {
  const origLookup = dns.lookup;
  const patchedLookup: any = function (
    hostname: string,
    options: any,
    callback: any
  ) {
    if (typeof options === 'function') {
      callback = options;
      options = { family: 4 };
    } else if (typeof options === 'number') {
      options = { family: 4 };
    } else {
      options = Object.assign({}, options, { family: 4 });
    }
    return origLookup.call(dns, hostname, options, callback);
  };
  patchedLookup.__ipv4Patched = true;
  (dns as any).lookup = patchedLookup;
}

dotenv.config({ path: path.resolve(__dirname, '../../.env') });
dotenv.config();

export const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  max: 20,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 10000,
  ssl: process.env.DATABASE_URL?.includes('supabase') ? { rejectUnauthorized: false } : false,
});

pool.on('error', (err) => {
  console.error('Unexpected DB error:', err);
});

export async function query(text: string, params?: any[]) {
  const client = await pool.connect();
  try {
    const result = await client.query(text, params);
    return result;
  } finally {
    client.release();
  }
}

export async function transaction<T>(
  fn: (client: any) => Promise<T>
): Promise<T> {
  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    const result = await fn(client);
    await client.query('COMMIT');
    return result;
  } catch (e) {
    await client.query('ROLLBACK');
    throw e;
  } finally {
    client.release();
  }
}
