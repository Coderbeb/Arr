const { Pool } = require('../backend/node_modules/pg');

const pool = new Pool({
  connectionString: 'postgresql://postgres:cMERUQHJLGYnaT5f@db.zkqtusrhebcuwiwvamxa.supabase.co:5432/postgres',
  ssl: { rejectUnauthorized: false },
});

async function run() {
  const client = await pool.connect();
  try {
    console.log('🚀 Running anti-fraud system migration...\n');

    // ── 1. Add buyer payment screenshot to trades table ──────────
    await safeAlter(client,
      `ALTER TABLE trades ADD COLUMN buyer_payment_screenshot_url TEXT`,
      'trades.buyer_payment_screenshot_url'
    );

    // ── 2. Add seller rejection proof columns to disputes ────────
    await safeAlter(client,
      `ALTER TABLE disputes ADD COLUMN seller_profile_recording_url TEXT`,
      'disputes.seller_profile_recording_url'
    );

    // ── 3. Add enhanced buyer proof column ──────────────────────
    await safeAlter(client,
      `ALTER TABLE disputes ADD COLUMN buyer_upi_screenshot_url TEXT`,
      'disputes.buyer_upi_screenshot_url'
    );

    // ── 4. Add AI analysis JSONB columns ────────────────────────
    await safeAlter(client,
      `ALTER TABLE disputes ADD COLUMN buyer_proof_analysis JSONB`,
      'disputes.buyer_proof_analysis'
    );
    await safeAlter(client,
      `ALTER TABLE disputes ADD COLUMN seller_proof_analysis JSONB`,
      'disputes.seller_proof_analysis'
    );

    // ── 5. Add AI recommendation columns ────────────────────────
    await safeAlter(client,
      `ALTER TABLE disputes ADD COLUMN ai_recommendation VARCHAR(20)`,
      'disputes.ai_recommendation'
    );
    await safeAlter(client,
      `ALTER TABLE disputes ADD COLUMN ai_confidence INTEGER`,
      'disputes.ai_confidence'
    );

    // ── 6. Create fraud_hashes table ────────────────────────────
    await client.query(`
      CREATE TABLE IF NOT EXISTS fraud_hashes (
        id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        file_hash   VARCHAR(64) UNIQUE NOT NULL,
        reason      TEXT,
        flagged_by  UUID REFERENCES users(id),
        dispute_id  UUID REFERENCES disputes(id),
        created_at  TIMESTAMP NOT NULL DEFAULT NOW()
      )
    `);
    console.log('✅ fraud_hashes table ready');

    // ── 7. Create proof_files table (tracks all uploaded files) ──
    await client.query(`
      CREATE TABLE IF NOT EXISTS proof_files (
        id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        dispute_id  UUID REFERENCES disputes(id),
        trade_id    UUID REFERENCES trades(id),
        uploaded_by UUID NOT NULL REFERENCES users(id),
        file_type   VARCHAR(20) NOT NULL,
        file_url    TEXT NOT NULL,
        file_hash   VARCHAR(64),
        file_size   INTEGER,
        mime_type   VARCHAR(100),
        analysis    JSONB,
        created_at  TIMESTAMP NOT NULL DEFAULT NOW()
      )
    `);
    console.log('✅ proof_files table ready');

    console.log('\n🎉 Anti-fraud migration complete!');
  } catch (err) {
    console.error('❌ Migration error:', err.message);
  } finally {
    client.release();
    await pool.end();
  }
}

async function safeAlter(client, sql, colName) {
  try {
    await client.query(sql);
    console.log(`✅ Added ${colName}`);
  } catch (err) {
    if (err.message.includes('already exists')) {
      console.log(`⚠️  ${colName} already exists — skipped`);
    } else {
      throw err;
    }
  }
}

run();
