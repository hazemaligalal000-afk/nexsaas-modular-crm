-- ============================================================
-- AI REVENUE OPERATING SYSTEM — Enterprise Schema v2.0
-- Multi-Tenant RBAC + Omnichannel + AI + Billing
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. TENANTS (Control Plane) ──────────────────────────────

CREATE TABLE IF NOT EXISTS tenants (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        CHAR(36) UNIQUE NOT NULL,
    name        VARCHAR(255) NOT NULL,
    subdomain   VARCHAR(100) UNIQUE NOT NULL,
    db_strategy ENUM('shared','dedicated') DEFAULT 'shared',
    db_config   JSON COMMENT 'Connection details if dedicated',
    status      ENUM('active','suspended','trial','cancelled') DEFAULT 'trial',
    settings    JSON COMMENT 'Branding, timezone, locale',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subdomain (subdomain),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ── 2. SUBSCRIPTION PLANS ───────────────────────────────────

CREATE TABLE IF NOT EXISTS plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(50) UNIQUE NOT NULL,
    name            VARCHAR(100) NOT NULL,
    price_monthly   DECIMAL(10,2) NOT NULL,
    price_yearly    DECIMAL(10,2) DEFAULT NULL,
    max_leads       INT DEFAULT 500,
    max_users       INT DEFAULT 3,
    max_contacts    INT DEFAULT 1000,
    features        JSON NOT NULL COMMENT '{"omnichannel":true,"ai":true,"dedicated_db":false}',
    stripe_price_id VARCHAR(255) DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO plans (slug, name, price_monthly, price_yearly, max_leads, max_users, max_contacts, features) VALUES
('starter',    'Starter',     29.00,   290.00,    500,   3,   1000, '{"omnichannel":false,"ai":false,"dedicated_db":false,"custom_roles":false}'),
('growth',     'Growth',      79.00,   790.00,   2500,  10,   5000, '{"omnichannel":true,"ai":false,"dedicated_db":false,"custom_roles":true}'),
('agency',     'Agency',     199.00,  1990.00,  10000,  25,  25000, '{"omnichannel":true,"ai":true,"dedicated_db":false,"custom_roles":true}'),
('enterprise', 'Enterprise', 499.00,  4990.00,     -1,  -1,     -1, '{"omnichannel":true,"ai":true,"dedicated_db":true,"custom_roles":true}');

-- ── 3. SUBSCRIPTIONS (Stripe Lifecycle) ─────────────────────

CREATE TABLE IF NOT EXISTS subscriptions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id               INT UNSIGNED NOT NULL,
    plan_id                 INT UNSIGNED NOT NULL,
    stripe_customer_id      VARCHAR(255),
    stripe_subscription_id  VARCHAR(255),
    status                  ENUM('active','past_due','cancelled','trialing') DEFAULT 'trialing',
    billing_cycle           ENUM('monthly','yearly') DEFAULT 'monthly',
    current_period_start    TIMESTAMP NULL,
    current_period_end      TIMESTAMP NULL,
    cancelled_at            TIMESTAMP NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id)   REFERENCES plans(id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_stripe_cust (stripe_customer_id)
) ENGINE=InnoDB;

-- ── 4. USERS ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    uuid            CHAR(36) UNIQUE NOT NULL,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    avatar_url      VARCHAR(500) DEFAULT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    last_login_at   TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tenant_email (tenant_id, email),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── 5. ROLES (Tenant-Extendable) ────────────────────────────

CREATE TABLE IF NOT EXISTS roles (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id        INT UNSIGNED NOT NULL,
    role_name        VARCHAR(100) NOT NULL,
    display_name     VARCHAR(255) DEFAULT NULL,
    is_system        TINYINT(1) DEFAULT 0 COMMENT '1=cannot be deleted by tenant',
    permissions_json JSON NOT NULL COMMENT 'Granular permission matrix',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tenant_role (tenant_id, role_name),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── 6. USER-ROLES (Many-to-Many) ────────────────────────────

CREATE TABLE IF NOT EXISTS user_roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    role_id     INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_user_role (user_id, role_id)
) ENGINE=InnoDB;

-- ── 7. REFRESH TOKENS ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    revoked     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ── 8. CONTACTS / LEADS ─────────────────────────────────────

CREATE TABLE IF NOT EXISTS contacts (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    first_name          VARCHAR(100),
    last_name           VARCHAR(100),
    email               VARCHAR(255),
    phone               VARCHAR(50),
    company             VARCHAR(255),
    lifecycle_stage     ENUM('subscriber','lead','mql','sql','opportunity','customer','evangelist') DEFAULT 'lead',
    ai_score            TINYINT UNSIGNED DEFAULT 0,
    truecaller_verified TINYINT(1) DEFAULT 0,
    truecaller_data     JSON,
    source              VARCHAR(100) DEFAULT NULL,
    assigned_user_id    INT UNSIGNED DEFAULT NULL,
    last_activity_at    TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)       REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_stage (lifecycle_stage),
    INDEX idx_score (ai_score),
    INDEX idx_phone (phone),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ── 9. DEALS PIPELINE ───────────────────────────────────────

CREATE TABLE IF NOT EXISTS deals (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id         INT UNSIGNED NOT NULL,
    title             VARCHAR(255) NOT NULL,
    value             DECIMAL(15,2) DEFAULT 0,
    currency          CHAR(3) DEFAULT 'USD',
    pipeline_stage    ENUM('qualification','meeting','proposal','negotiation','closed_won','closed_lost') DEFAULT 'qualification',
    contact_id        INT UNSIGNED,
    assigned_user_id  INT UNSIGNED,
    expected_close    DATE,
    closed_at         TIMESTAMP NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)        REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id)       REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_stage (pipeline_stage)
) ENGINE=InnoDB;

-- ── 10. OMNICHANNEL MESSAGES ────────────────────────────────

CREATE TABLE IF NOT EXISTS messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    contact_id  INT UNSIGNED,
    channel     ENUM('whatsapp','telegram','email','sms','webchat') NOT NULL,
    direction   ENUM('inbound','outbound') NOT NULL,
    content     TEXT,
    metadata    JSON,
    agent_id    INT UNSIGNED DEFAULT NULL,
    thread_id   VARCHAR(255) DEFAULT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (agent_id)   REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id),
    INDEX idx_thread (thread_id),
    INDEX idx_contact (contact_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── 11. CAMPAIGNS ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS campaigns (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    type            ENUM('email','sms','whatsapp','multi') DEFAULT 'email',
    status          ENUM('draft','scheduled','running','paused','completed') DEFAULT 'draft',
    audience_filter JSON,
    content         JSON COMMENT '{"subject":"...","body":"...","template_id":null}',
    scheduled_at    TIMESTAMP NULL,
    sent_count      INT DEFAULT 0,
    open_count      INT DEFAULT 0,
    click_count     INT DEFAULT 0,
    created_by      INT UNSIGNED,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── 12. AUDIT LOG ───────────────────────────────────────────

CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id   INT UNSIGNED,
    old_values  JSON,
    new_values  JSON,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(500),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_date (tenant_id, created_at),
    INDEX idx_entity (entity_type, entity_id)
) ENGINE=InnoDB;

-- ── 13. SEED DEFAULT TENANTS ────────────────────────────────

INSERT INTO tenants (uuid, name, subdomain, status) VALUES
(UUID(), 'Acme Corporation', 'acme', 'active'),
(UUID(), 'Globex Industries', 'globex', 'active');

-- ── 14. SEED DEFAULT ROLES FOR EACH TENANT ──────────────────

INSERT INTO roles (tenant_id, role_name, display_name, is_system, permissions_json) VALUES
-- Acme Corp (tenant 1)
(1, 'owner', 'Owner', 1, '{
    "leads":      {"create":true,"read":true,"update":true,"delete":true,"export":true,"import":true,"assign":true},
    "deals":      {"create":true,"read":true,"update":true,"delete":true,"export":true},
    "contacts":   {"create":true,"read":true,"update":true,"delete":true,"export":true,"import":true},
    "campaigns":  {"create":true,"read":true,"update":true,"delete":true,"send":true},
    "messaging":  {"read":true,"send":true,"assign":true,"delete":true},
    "analytics":  {"view":true,"export":true,"revenue":true,"agents":true},
    "ai":         {"scoring":true,"intent":true,"generate":true,"train":true},
    "billing":    {"view":true,"manage":true,"cancel":true},
    "settings":   {"branding":true,"integrations":true,"api_keys":true},
    "users":      {"create":true,"read":true,"update":true,"delete":true,"manage_roles":true},
    "audit":      {"view":true}
}'),
(1, 'admin', 'Admin', 1, '{
    "leads":      {"create":true,"read":true,"update":true,"delete":true,"export":true,"import":true,"assign":true},
    "deals":      {"create":true,"read":true,"update":true,"delete":true,"export":true},
    "contacts":   {"create":true,"read":true,"update":true,"delete":true,"export":true,"import":true},
    "campaigns":  {"create":true,"read":true,"update":true,"delete":true,"send":true},
    "messaging":  {"read":true,"send":true,"assign":true,"delete":false},
    "analytics":  {"view":true,"export":true,"revenue":true,"agents":true},
    "ai":         {"scoring":true,"intent":true,"generate":true,"train":false},
    "billing":    {"view":true,"manage":false,"cancel":false},
    "settings":   {"branding":true,"integrations":true,"api_keys":false},
    "users":      {"create":true,"read":true,"update":true,"delete":false,"manage_roles":true},
    "audit":      {"view":true}
}'),
(1, 'sales_manager', 'Sales Manager', 1, '{
    "leads":      {"create":true,"read":true,"update":true,"delete":false,"export":true,"import":true,"assign":true},
    "deals":      {"create":true,"read":true,"update":true,"delete":false,"export":true},
    "contacts":   {"create":true,"read":true,"update":true,"delete":false,"export":true,"import":false},
    "campaigns":  {"create":true,"read":true,"update":true,"delete":false,"send":true},
    "messaging":  {"read":true,"send":true,"assign":true,"delete":false},
    "analytics":  {"view":true,"export":false,"revenue":true,"agents":true},
    "ai":         {"scoring":true,"intent":true,"generate":true,"train":false},
    "billing":    {"view":false,"manage":false,"cancel":false},
    "settings":   {"branding":false,"integrations":false,"api_keys":false},
    "users":      {"create":false,"read":true,"update":false,"delete":false,"manage_roles":false},
    "audit":      {"view":false}
}'),
(1, 'sales_agent', 'Sales Agent', 1, '{
    "leads":      {"create":true,"read":true,"update":true,"delete":false,"export":false,"import":false,"assign":false},
    "deals":      {"create":true,"read":true,"update":true,"delete":false,"export":false},
    "contacts":   {"create":true,"read":true,"update":true,"delete":false,"export":false,"import":false},
    "campaigns":  {"create":false,"read":true,"update":false,"delete":false,"send":false},
    "messaging":  {"read":true,"send":true,"assign":false,"delete":false},
    "analytics":  {"view":true,"export":false,"revenue":false,"agents":false},
    "ai":         {"scoring":false,"intent":false,"generate":true,"train":false},
    "billing":    {"view":false,"manage":false,"cancel":false},
    "settings":   {"branding":false,"integrations":false,"api_keys":false},
    "users":      {"create":false,"read":false,"update":false,"delete":false,"manage_roles":false},
    "audit":      {"view":false}
}'),
(1, 'support_agent', 'Support Agent', 1, '{
    "leads":      {"create":false,"read":true,"update":false,"delete":false,"export":false,"import":false,"assign":false},
    "deals":      {"create":false,"read":true,"update":false,"delete":false,"export":false},
    "contacts":   {"create":false,"read":true,"update":true,"delete":false,"export":false,"import":false},
    "campaigns":  {"create":false,"read":false,"update":false,"delete":false,"send":false},
    "messaging":  {"read":true,"send":true,"assign":false,"delete":false},
    "analytics":  {"view":false,"export":false,"revenue":false,"agents":false},
    "ai":         {"scoring":false,"intent":false,"generate":true,"train":false},
    "billing":    {"view":false,"manage":false,"cancel":false},
    "settings":   {"branding":false,"integrations":false,"api_keys":false},
    "users":      {"create":false,"read":false,"update":false,"delete":false,"manage_roles":false},
    "audit":      {"view":false}
}');

-- ── 15. SEED USERS ──────────────────────────────────────────
-- Password: 'SecureP@ss2026' -> bcrypt hash
INSERT INTO users (tenant_id, uuid, name, email, password_hash) VALUES
(1, UUID(), 'Hazem Ali (Owner)', 'hazem@acme.com', '$2y$12$LJ3m4ys1QfSbFnBkG7R/5OQq8TG/Y2TNo7FLZ1Dt32Hci3QBV7Uae'),
(1, UUID(), 'Sarah Admin', 'sarah@acme.com', '$2y$12$LJ3m4ys1QfSbFnBkG7R/5OQq8TG/Y2TNo7FLZ1Dt32Hci3QBV7Uae'),
(1, UUID(), 'Mike Sales', 'mike@acme.com', '$2y$12$LJ3m4ys1QfSbFnBkG7R/5OQq8TG/Y2TNo7FLZ1Dt32Hci3QBV7Uae');

-- ── 16. ASSIGN ROLES ────────────────────────────────────────
INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1),  -- Hazem => Owner
(2, 2),  -- Sarah => Admin
(3, 4);  -- Mike  => Sales Agent

SET FOREIGN_KEY_CHECKS = 1;
