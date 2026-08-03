const { Pool } = require('pg');
const fs = require('fs');
const path = require('path');
const dns = require('dns');

// Force IPv4 to avoid ENETUNREACH on IPv6
dns.setDefaultResultOrder('ipv4first');

const pool = new Pool({
  connectionString: process.env.DATABASE_URL || 'postgresql://postgres:cMERUQHJLGYnaT5f@db.zkqtusrhebcuwiwvamxa.supabase.co:5432/postgres',
  ssl: { rejectUnauthorized: false },
});

async function run() {
  const client = await pool.connect();
  try {
    const sql = fs.readFileSync(path.join(__dirname, 'schema.sql'), 'utf8');
    
    // Split by semicolons but keep compound statements together
    const statements = sql
      .split(/;\s*\n/)
      .map(s => s.trim())
      .filter(s => s.length > 0 && !s.startsWith('--'));
    
    console.log(`Found ${statements.length} statements to execute`);
    
    for (let i = 0; i < statements.length; i++) {
      const stmt = statements[i];
      if (!stmt || stmt === '') continue;
      try {
        await client.query(stmt);
        console.log(`✅ [${i + 1}/${statements.length}] OK`);
      } catch (err) {
        if (err.message.includes('already exists') || err.message.includes('duplicate')) {
          console.log(`⚠️ [${i + 1}/${statements.length}] Already exists (skipped)`);
        } else {
          console.error(`❌ [${i + 1}/${statements.length}] ERROR: ${err.message}`);
          console.error(`   Statement: ${stmt.substring(0, 100)}...`);
        }
      }
    }
    
    // Verify tables
    const tables = await client.query(`
      SELECT table_name FROM information_schema.tables 
      WHERE table_schema = 'public' AND table_type = 'BASE TABLE'
      ORDER BY table_name
    `);
    console.log('\n📋 Tables in database:');
    tables.rows.forEach(r => console.log(`  ✓ ${r.table_name}`));
    
    // Verify super admin
    const admin = await client.query(`SELECT id, full_name, role FROM users WHERE role = 'super_admin'`);
    if (admin.rows.length > 0) {
      console.log(`\n👤 Super Admin: ${admin.rows[0].full_name} (${admin.rows[0].id})`);
    }
    
    // Verify settings
    const settings = await client.query('SELECT * FROM platform_settings LIMIT 1');
    console.log(`⚙️ Commission: ${settings.rows[0].commission_percent}%`);
    
    // Verify trade amounts
    const amounts = await client.query('SELECT amount FROM trade_amounts WHERE is_active = true ORDER BY sort_order');
    console.log(`💰 Trade Amounts: ${amounts.rows.map(r => '₹' + r.amount).join(', ')}`);
    
    console.log('\n🎉 Database setup complete!');
  } finally {
    client.release();
    await pool.end();
  }
}

run().catch(e => { console.error('FATAL:', e); process.exit(1); });
