-- ============================================
-- ARR WALLET - Complete Database Schema
-- ============================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- ENUMS
-- ============================================

CREATE TYPE user_role AS ENUM ('user', 'assistance', 'super_admin');
CREATE TYPE user_status AS ENUM ('active', 'suspended', 'banned');
CREATE TYPE upi_app AS ENUM ('gpay', 'phonepe', 'paytm', 'bhim');
CREATE TYPE language_pref AS ENUM ('en', 'hi');
CREATE TYPE order_status AS ENUM ('open', 'matched', 'locked', 'completed', 'cancelled', 'disputed');
CREATE TYPE trade_status AS ENUM (
  'pending_payment',
  'payment_submitted',
  'seller_confirmed',
  'seller_rejected',
  'disputed',
  'completed',
  'cancelled',
  'refunded'
);
CREATE TYPE dispute_status AS ENUM ('pending', 'under_review', 'resolved_buyer', 'resolved_seller', 'escalated');
CREATE TYPE wallet_tx_type AS ENUM ('credit_commission', 'debit_trade', 'bonus', 'admin_credit', 'admin_debit', 'escrow_lock', 'escrow_release');

-- ============================================
-- TABLE: users
-- ============================================

CREATE TABLE users (
  id                UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  mobile_number     VARCHAR(15) UNIQUE NOT NULL,
  email             VARCHAR(255) UNIQUE,
  full_name         VARCHAR(100) NOT NULL,
  date_of_birth     DATE NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  upi_id            VARCHAR(100),
  upi_app           upi_app,
  upi_qr_image_url  TEXT,
  city              VARCHAR(100),
  language          language_pref NOT NULL DEFAULT 'en',
  role              user_role NOT NULL DEFAULT 'user',
  status            user_status NOT NULL DEFAULT 'active',
  wallet_balance    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  escrow_balance    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_trades      INTEGER NOT NULL DEFAULT 0,
  reputation_score  INTEGER NOT NULL DEFAULT 100,
  strike_count      INTEGER NOT NULL DEFAULT 0,
  is_verified       BOOLEAN NOT NULL DEFAULT false,
  failed_dob_attempts INTEGER NOT NULL DEFAULT 0,
  dob_lockout_until TIMESTAMP,
  created_at        TIMESTAMP NOT NULL DEFAULT NOW(),
  last_login        TIMESTAMP
);

CREATE INDEX idx_users_mobile ON users(mobile_number);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- ============================================
-- TABLE: platform_settings
-- ============================================

CREATE TABLE platform_settings (
  id                      UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  registration_open       BOOLEAN NOT NULL DEFAULT true,
  commission_percent      DECIMAL(5,2) NOT NULL DEFAULT 8.00,
  max_daily_earning       DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  max_weekly_earning      DECIMAL(10,2) NOT NULL DEFAULT 2000.00,
  trade_accept_minutes    INTEGER NOT NULL DEFAULT 2,
  payment_timer_minutes   INTEGER NOT NULL DEFAULT 30,
  dispute_proof_minutes   INTEGER NOT NULL DEFAULT 30,
  updated_by              UUID REFERENCES users(id),
  updated_at              TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Insert default settings
INSERT INTO platform_settings (registration_open, commission_percent, max_daily_earning, max_weekly_earning)
VALUES (true, 8.00, 500.00, 2000.00);

-- ============================================
-- TABLE: trade_amounts
-- ============================================

CREATE TABLE trade_amounts (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  amount      DECIMAL(10,2) NOT NULL,
  is_active   BOOLEAN NOT NULL DEFAULT true,
  sort_order  INTEGER NOT NULL DEFAULT 0,
  created_by  UUID REFERENCES users(id),
  created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Default trade amounts
INSERT INTO trade_amounts (amount, sort_order) VALUES
  (1000.00, 1),
  (1200.00, 2),
  (1500.00, 3),
  (1800.00, 4),
  (2000.00, 5);

-- ============================================
-- TABLE: orders
-- ============================================

CREATE TABLE orders (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  seller_id       UUID NOT NULL REFERENCES users(id),
  amount          DECIMAL(10,2) NOT NULL,
  coin_amount     DECIMAL(10,2) NOT NULL,
  commission_pct  DECIMAL(5,2) NOT NULL,
  commission_amt  DECIMAL(10,2) NOT NULL,
  seller_upi_id   VARCHAR(100) NOT NULL,
  seller_upi_app  upi_app NOT NULL,
  seller_qr_url   TEXT,
  status          order_status NOT NULL DEFAULT 'open',
  created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
  expires_at      TIMESTAMP NOT NULL,
  matched_at      TIMESTAMP,
  completed_at    TIMESTAMP
);

CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_seller ON orders(seller_id);
CREATE INDEX idx_orders_created ON orders(created_at);

-- ============================================
-- TABLE: trades
-- ============================================

CREATE TABLE trades (
  id                    UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  order_id              UUID NOT NULL REFERENCES orders(id),
  buyer_id              UUID NOT NULL REFERENCES users(id),
  seller_id             UUID NOT NULL REFERENCES users(id),
  amount                DECIMAL(10,2) NOT NULL,
  commission_amount     DECIMAL(10,2) NOT NULL,
  buyer_upi_app         upi_app,
  utr_number            VARCHAR(50),
  payment_screenshot_url TEXT,
  buyer_payment_screenshot_url TEXT,
  status                trade_status NOT NULL DEFAULT 'pending_payment',
  matched_at            TIMESTAMP NOT NULL DEFAULT NOW(),
  payment_deadline      TIMESTAMP NOT NULL,
  paid_at               TIMESTAMP,
  completed_at          TIMESTAMP,
  cancelled_reason      TEXT
);

CREATE INDEX idx_trades_buyer ON trades(buyer_id);
CREATE INDEX idx_trades_seller ON trades(seller_id);
CREATE INDEX idx_trades_status ON trades(status);
CREATE INDEX idx_trades_order ON trades(order_id);

-- ============================================
-- TABLE: disputes
-- ============================================

CREATE TABLE disputes (
  id                          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  trade_id                    UUID NOT NULL REFERENCES trades(id),
  raised_by                   UUID NOT NULL REFERENCES users(id),
  status                      dispute_status NOT NULL DEFAULT 'pending',

  -- BUYER PROOF
  buyer_screenshot_url         TEXT,
  buyer_screen_recording_url   TEXT,
  buyer_bank_statement_url     TEXT,
  buyer_utr_number             VARCHAR(50),
  buyer_upi_screenshot_url     TEXT,
  buyer_ai_score               INTEGER CHECK (buyer_ai_score BETWEEN 0 AND 100),
  buyer_ai_breakdown           JSONB,
  buyer_proof_analysis         JSONB,
  buyer_proof_submitted_at     TIMESTAMP,

  -- SELLER PROOF
  seller_screen_recording_url  TEXT,
  seller_txn_screenshot_url    TEXT,
  seller_profile_recording_url TEXT,
  seller_ai_score              INTEGER CHECK (seller_ai_score BETWEEN 0 AND 100),
  seller_ai_breakdown          JSONB,
  seller_proof_analysis        JSONB,
  seller_proof_submitted_at    TIMESTAMP,

  -- AI RECOMMENDATION
  ai_recommendation            VARCHAR(20),
  ai_confidence                INTEGER CHECK (ai_confidence BETWEEN 0 AND 100),

  -- RESOLUTION
  resolved_by                  UUID REFERENCES users(id),
  resolution_notes             TEXT,
  resolved_at                  TIMESTAMP,
  proof_deadline               TIMESTAMP NOT NULL,

  created_at                   TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_disputes_status ON disputes(status);
CREATE INDEX idx_disputes_trade ON disputes(trade_id);

-- ============================================
-- TABLE: wallet_transactions
-- ============================================

CREATE TABLE wallet_transactions (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id),
  trade_id        UUID REFERENCES trades(id),
  type            wallet_tx_type NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  balance_before  DECIMAL(14,2) NOT NULL,
  balance_after   DECIMAL(14,2) NOT NULL,
  description_en  TEXT,
  description_hi  TEXT,
  created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_wallet_tx_user ON wallet_transactions(user_id);
CREATE INDEX idx_wallet_tx_created ON wallet_transactions(created_at);

-- ============================================
-- TABLE: bonus_milestones
-- ============================================

CREATE TABLE bonus_milestones (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  trade_count     INTEGER NOT NULL UNIQUE,
  bonus_amount    DECIMAL(10,2) NOT NULL,
  is_active       BOOLEAN NOT NULL DEFAULT true,
  created_by      UUID REFERENCES users(id),
  created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Default bonuses
INSERT INTO bonus_milestones (trade_count, bonus_amount) VALUES
  (10, 50.00),
  (50, 200.00),
  (100, 500.00),
  (250, 1000.00);

-- ============================================
-- TABLE: user_bonuses_claimed
-- ============================================

CREATE TABLE user_bonuses_claimed (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id),
  milestone_id    UUID NOT NULL REFERENCES bonus_milestones(id),
  claimed_at      TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE(user_id, milestone_id)
);

-- ============================================
-- TABLE: utr_registry (fraud prevention)
-- ============================================

CREATE TABLE utr_registry (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  utr_number  VARCHAR(50) UNIQUE NOT NULL,
  trade_id    UUID NOT NULL REFERENCES trades(id),
  user_id     UUID NOT NULL REFERENCES users(id),
  used_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_utr_number ON utr_registry(utr_number);

-- ============================================
-- TABLE: earnings_tracker (daily/weekly cap)
-- ============================================

CREATE TABLE earnings_tracker (
  id              UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id         UUID NOT NULL REFERENCES users(id),
  date            DATE NOT NULL,
  daily_earned    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  weekly_earned   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  week_start      DATE NOT NULL,
  UNIQUE(user_id, date)
);

CREATE INDEX idx_earnings_user_date ON earnings_tracker(user_id, date);

-- ============================================
-- TABLE: notifications
-- ============================================

CREATE TABLE notifications (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id     UUID NOT NULL REFERENCES users(id),
  type        VARCHAR(50) NOT NULL,
  title_en    TEXT NOT NULL,
  title_hi    TEXT NOT NULL,
  body_en     TEXT NOT NULL,
  body_hi     TEXT NOT NULL,
  is_read     BOOLEAN NOT NULL DEFAULT false,
  trade_id    UUID REFERENCES trades(id),
  dispute_id  UUID REFERENCES disputes(id),
  created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);

-- ============================================
-- TABLE: admin_audit_log
-- ============================================

CREATE TABLE admin_audit_log (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  admin_id    UUID NOT NULL REFERENCES users(id),
  action      VARCHAR(100) NOT NULL,
  target_type VARCHAR(50),
  target_id   UUID,
  notes       TEXT,
  metadata    JSONB,
  created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_admin ON admin_audit_log(admin_id);
CREATE INDEX idx_audit_created ON admin_audit_log(created_at);

-- ============================================
-- SEED: Super Admin account
-- password: Admin@123 (bcrypt hashed)
-- ============================================

INSERT INTO users (
  mobile_number, full_name, date_of_birth, password_hash, role, status, wallet_balance, is_verified
) VALUES (
  '9999999999',
  'Super Admin',
  '1990-01-01',
  '$2b$12$LQv3c1yqBwEHXp.FT9O5W.Jn9jRXAJDvDDy5xHC5fQxhGEb8wN6Gy',
  'super_admin',
  'active',
  0.00,
  true
);

-- ============================================
-- TABLE: fraud_hashes (proof fraud detection)
-- ============================================

CREATE TABLE fraud_hashes (
  id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  file_hash   VARCHAR(64) UNIQUE NOT NULL,
  reason      TEXT,
  flagged_by  UUID REFERENCES users(id),
  dispute_id  UUID REFERENCES disputes(id),
  created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ============================================
-- TABLE: proof_files (tracks all uploaded files)
-- ============================================

CREATE TABLE proof_files (
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
);
