-- ============================================================
--  ROYAL SMM PLATFORM — Supabase / PostgreSQL schema
--  Paste this whole file into the Supabase SQL Editor and Run.
--  Safe to re-run (uses DROP ... IF EXISTS / CREATE).
--  Column names match exactly what the PHP app reads & writes.
-- ============================================================

-- Clean slate (order matters because of foreign keys)
DROP TABLE IF EXISTS webhook_events     CASCADE;
DROP TABLE IF EXISTS webhooks           CASCADE;
DROP TABLE IF EXISTS api_keys           CASCADE;
DROP TABLE IF EXISTS scheduled_orders   CASCADE;
DROP TABLE IF EXISTS bulk_orders        CASCADE;
DROP TABLE IF EXISTS affiliate_referrals CASCADE;
DROP TABLE IF EXISTS analytics          CASCADE;
DROP TABLE IF EXISTS services_cache     CASCADE;
DROP TABLE IF EXISTS promo_codes        CASCADE;
DROP TABLE IF EXISTS support_tickets    CASCADE;
DROP TABLE IF EXISTS activity_logs      CASCADE;
DROP TABLE IF EXISTS transactions       CASCADE;
DROP TABLE IF EXISTS orders             CASCADE;
DROP TABLE IF EXISTS users              CASCADE;

-- ============================================================
--  Auto-update updated_at helper
-- ============================================================
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
--  USERS
-- ============================================================
CREATE TABLE users (
    id                  BIGSERIAL PRIMARY KEY,
    username            TEXT NOT NULL UNIQUE,
    email               TEXT NOT NULL UNIQUE,
    phone               TEXT,
    password            TEXT NOT NULL,
    balance             NUMERIC(15,2) DEFAULT 0.00,
    role                TEXT DEFAULT 'user'   CHECK (role   IN ('user','admin','moderator')),
    status              TEXT DEFAULT 'active' CHECK (status IN ('active','suspended','banned')),
    two_factor_enabled  SMALLINT DEFAULT 0,
    two_factor_secret   TEXT,
    referral_code       TEXT UNIQUE,
    referred_by         BIGINT REFERENCES users(id) ON DELETE SET NULL,
    api_key             TEXT UNIQUE,
    last_login          TIMESTAMPTZ,
    created_at          TIMESTAMPTZ DEFAULT now(),
    updated_at          TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_users_email         ON users(email);
CREATE INDEX idx_users_referral_code ON users(referral_code);
CREATE INDEX idx_users_api_key       ON users(api_key);
CREATE TRIGGER trg_users_updated BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
--  ORDERS
-- ============================================================
CREATE TABLE orders (
    id                BIGSERIAL PRIMARY KEY,
    user_id           BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_id        BIGINT NOT NULL,
    service_name      TEXT NOT NULL,
    service_category  TEXT,
    platform          TEXT,
    quantity          INTEGER NOT NULL,
    price             NUMERIC(15,2) NOT NULL,
    status            TEXT DEFAULT 'Pending',
    progress          INTEGER DEFAULT 0,
    external_order_id TEXT,
    provider          TEXT DEFAULT 'boost',
    -- Provider lane shown on the orders page (primary = Kawaida, partner = Pro).
    -- place-order.php and orders.php both write/read this; without it every
    -- order INSERT aborts its transaction on Postgres and rolls back silently.
    gateway           TEXT DEFAULT 'primary',
    link              TEXT NOT NULL,
    notes             TEXT,
    refund_requested  SMALLINT DEFAULT 0,
    refund_reason     TEXT,
    refund_amount     NUMERIC(15,2),
    refill_available  SMALLINT DEFAULT 0,
    refill_requested  SMALLINT DEFAULT 0,
    refill_status     TEXT,
    refill_requested_at TIMESTAMPTZ,
    created_at        TIMESTAMPTZ DEFAULT now(),
    completed_at      TIMESTAMPTZ,
    updated_at        TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_orders_user_id   ON orders(user_id);
CREATE INDEX idx_orders_status    ON orders(status);
CREATE INDEX idx_orders_external  ON orders(external_order_id);
CREATE INDEX idx_orders_created   ON orders(created_at);
CREATE TRIGGER trg_orders_updated BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
--  TRANSACTIONS
-- ============================================================
CREATE TABLE transactions (
    id             BIGSERIAL PRIMARY KEY,
    user_id        BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    order_id       BIGINT REFERENCES orders(id) ON DELETE SET NULL,
    amount         NUMERIC(15,2) NOT NULL,
    type           TEXT NOT NULL CHECK (type IN ('credit','debit','refund')),
    payment_method TEXT,
    gateway        TEXT,
    description    TEXT,
    external_ref   TEXT,
    status         TEXT DEFAULT 'pending',
    metadata       JSONB,
    created_at     TIMESTAMPTZ DEFAULT now(),
    completed_at   TIMESTAMPTZ
);
CREATE INDEX idx_tx_user_id  ON transactions(user_id);
CREATE INDEX idx_tx_external ON transactions(external_ref);
CREATE INDEX idx_tx_status   ON transactions(status);
CREATE INDEX idx_tx_created  ON transactions(created_at);

-- ============================================================
--  ACTIVITY LOGS
-- ============================================================
CREATE TABLE activity_logs (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action     TEXT NOT NULL,
    details    TEXT,
    status     TEXT DEFAULT 'success',
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_logs_user   ON activity_logs(user_id);
CREATE INDEX idx_logs_action ON activity_logs(action);

-- ============================================================
--  SUPPORT TICKETS
-- ============================================================
CREATE TABLE support_tickets (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subject    TEXT NOT NULL,
    message    TEXT NOT NULL,
    status     TEXT DEFAULT 'open',
    priority   TEXT DEFAULT 'medium',
    response   TEXT,
    created_at TIMESTAMPTZ DEFAULT now(),
    updated_at TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_tickets_user   ON support_tickets(user_id);
CREATE INDEX idx_tickets_status ON support_tickets(status);
CREATE TRIGGER trg_tickets_updated BEFORE UPDATE ON support_tickets
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- ============================================================
--  PROMO CODES
-- ============================================================
CREATE TABLE promo_codes (
    id             BIGSERIAL PRIMARY KEY,
    code           TEXT NOT NULL UNIQUE,
    discount_type  TEXT,
    discount_value NUMERIC(15,2),
    usage_limit    INTEGER,
    used_count     INTEGER DEFAULT 0,
    expiry_date    TIMESTAMPTZ,
    is_active      SMALLINT DEFAULT 1,
    created_at     TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
--  SERVICES CACHE (optional — mirror of provider catalogue)
-- ============================================================
CREATE TABLE services_cache (
    id                   BIGSERIAL PRIMARY KEY,
    service_id           BIGINT UNIQUE,
    name                 TEXT NOT NULL,
    category             TEXT,
    platform             TEXT,
    description          TEXT,
    min_quantity         INTEGER,
    max_quantity         INTEGER,
    price_base           NUMERIC(15,2),
    price_markup_percent NUMERIC(5,2),
    status               TEXT DEFAULT 'active',
    raw_data             JSONB,
    cached_at            TIMESTAMPTZ DEFAULT now(),
    expires_at           TIMESTAMPTZ
);
CREATE INDEX idx_svc_platform ON services_cache(platform);
CREATE INDEX idx_svc_category ON services_cache(category);

-- ============================================================
--  API KEYS (optional — for users' own API access)
-- ============================================================
CREATE TABLE api_keys (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    api_key    TEXT UNIQUE NOT NULL,
    api_secret TEXT NOT NULL,
    name       TEXT,
    status     TEXT DEFAULT 'active' CHECK (status IN ('active','inactive')),
    rate_limit INTEGER DEFAULT 100,
    last_used  TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_apikeys_user ON api_keys(user_id);

-- ============================================================
--  WEBHOOKS (optional)
-- ============================================================
CREATE TABLE webhooks (
    id             BIGSERIAL PRIMARY KEY,
    user_id        BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    url            TEXT NOT NULL,
    events         JSONB NOT NULL,
    status         TEXT DEFAULT 'active' CHECK (status IN ('active','inactive')),
    retry_count    INTEGER DEFAULT 0,
    last_triggered TIMESTAMPTZ,
    secret_key     TEXT,
    created_at     TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE webhook_events (
    id           BIGSERIAL PRIMARY KEY,
    webhook_id   BIGINT NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event_type   TEXT NOT NULL,
    payload      JSONB NOT NULL,
    status       TEXT DEFAULT 'pending',
    attempts     INTEGER DEFAULT 0,
    last_attempt TIMESTAMPTZ,
    next_retry   TIMESTAMPTZ,
    created_at   TIMESTAMPTZ DEFAULT now()
);

-- ============================================================
--  ANALYTICS (optional — daily rollups)
-- ============================================================
CREATE TABLE analytics (
    id                 BIGSERIAL PRIMARY KEY,
    user_id            BIGINT REFERENCES users(id) ON DELETE CASCADE,
    date               DATE NOT NULL,
    total_orders       INTEGER DEFAULT 0,
    total_spent        NUMERIC(15,2) DEFAULT 0,
    total_refunded     NUMERIC(15,2) DEFAULT 0,
    completed_orders   INTEGER DEFAULT 0,
    pending_orders     INTEGER DEFAULT 0,
    failed_orders      INTEGER DEFAULT 0,
    platform_breakdown JSONB,
    created_at         TIMESTAMPTZ DEFAULT now(),
    UNIQUE (user_id, date)
);

-- ============================================================
--  SCHEDULED ORDERS (optional)
-- ============================================================
CREATE TABLE scheduled_orders (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_id      BIGINT NOT NULL,
    link            TEXT NOT NULL,
    quantity        INTEGER NOT NULL,
    scheduled_for   TIMESTAMPTZ NOT NULL,
    repeat_interval TEXT,
    repeat_until    TIMESTAMPTZ,
    status          TEXT DEFAULT 'scheduled',
    created_at      TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_sched_user ON scheduled_orders(user_id);
CREATE INDEX idx_sched_when ON scheduled_orders(scheduled_for);

-- ============================================================
--  AFFILIATE REFERRALS (optional)
-- ============================================================
CREATE TABLE affiliate_referrals (
    id                BIGSERIAL PRIMARY KEY,
    referrer_id       BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    referred_user_id  BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    commission_rate   NUMERIC(5,2) DEFAULT 10.00,
    commission_amount NUMERIC(15,2) DEFAULT 0,
    status            TEXT DEFAULT 'pending',
    created_at        TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX idx_aff_referrer ON affiliate_referrals(referrer_id);

-- ============================================================
--  SEED: default admin account
--  Login -> username: admin   password: Admin@123
--  (change the password after first login!)
-- ============================================================
INSERT INTO users (username, email, phone, password, balance, role, status, referral_code)
VALUES (
    'admin',
    'admin@royal.local',
    '255700000000',
    '$2y$10$Xbukl4XtfIDD2FnTjhYxqOVgcv8yphrBIondsVA5QcQFsXalhPTvK',
    0,
    'admin',
    'active',
    'ADMIN1'
)
ON CONFLICT (username) DO NOTHING;

-- ============================================================
--  ROW LEVEL SECURITY
--  The PHP app connects with a direct Postgres role (service role
--  via the connection string), which BYPASSES RLS — so you can
--  leave RLS disabled and the app will work as-is.
--
--  If you also expose these tables through the Supabase REST/JS
--  client from a browser, enable RLS and add policies, e.g.:
--
--  ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
--  CREATE POLICY "own orders" ON orders
--      FOR SELECT USING (auth.uid()::text = user_id::text);
-- ============================================================

-- Done.
