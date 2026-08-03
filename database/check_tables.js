const { Pool } = require('../backend/node_modules/pg');
const dns = require('dns');
dns.setDefaultResultOrder('ipv4first');

const p = new Pool({
  connectionString: 'postgresql://postgres:cMERUQHJLGYnaT5f@db.zkqtusrhebcuwiwvamxa.supabase.co:5432/postgres',
  ssl: { rejectUnauthorized: false }
});

p.query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name")
  .then(r => {
    console.log('Tables found:', r.rows.length);
    r.rows.forEach(x => console.log('  ✓', x.table_name));
    return p.end();
  })
  .catch(e => {
    console.error('DB Error:', e.message);
    return p.end();
  });
