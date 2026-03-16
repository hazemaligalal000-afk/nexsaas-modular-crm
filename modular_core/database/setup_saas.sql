-- Central Management Tables (The Control Plane)
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) UNIQUE NOT NULL,
    db_strategy ENUM('shared', 'dedicated') DEFAULT 'shared',
    db_config JSON,
    plan_id INT,
    status ENUM('active', 'suspended', 'trial') DEFAULT 'trial',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS saas_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Sample Data for Demonstration
INSERT INTO tenants (name, subdomain, status) VALUES ('Acme Corp', 'acme', 'active');
INSERT INTO tenants (name, subdomain, status) VALUES ('Globex', 'globex', 'active');

INSERT INTO saas_api_keys (organization_id, api_key) VALUES (1, 'demo_tenant_key_123'); -- Acme
INSERT INTO saas_api_keys (organization_id, api_key) VALUES (2, 'globex_key_456'); -- Globex

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('OWNER', 'ADMIN', 'SALES_AGENT', 'SUPPORT_AGENT') DEFAULT 'SALES_AGENT',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Seed an admin user for testing (password is 'secret')
INSERT INTO users (tenant_id, email, password_hash, role) 
VALUES (1, 'admin@acme.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN');

-- Tenant Scoped Tables Example
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(255),
    lifecycle_stage VARCHAR(50) DEFAULT 'lead',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id),
    INDEX (phone),
    INDEX (email),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Seed some scoped data
INSERT INTO contacts (tenant_id, first_name, last_name, email) VALUES (1, 'John', 'Doe', 'john@acme.com');
INSERT INTO contacts (tenant_id, first_name, last_name, email) VALUES (2, 'Hank', 'Scorpio', 'hank@globex.com');

-- ── Subscription Plans ──
CREATE TABLE IF NOT EXISTS plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    price_monthly DECIMAL(10,2) NOT NULL,
    max_leads INT DEFAULT 500,
    max_users INT DEFAULT 3,
    features JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO plans (name, price_monthly, max_leads, max_users, features) VALUES
('starter',    29.00,   500,  3,  '{"omnichannel": false, "ai": false}'),
('growth',     79.00,  2500, 10,  '{"omnichannel": true,  "ai": false}'),
('agency',    199.00, 10000, 25,  '{"omnichannel": true,  "ai": true}'),
('enterprise', 499.00, -1,   -1,  '{"omnichannel": true,  "ai": true, "dedicated_db": true}');

-- ── Subscriptions (Stripe Lifecycle) ──
CREATE TABLE IF NOT EXISTS subscriptions (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id INT NOT NULL,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    plan_tier VARCHAR(50) DEFAULT 'starter',
    current_period_end TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- ── Deals Pipeline ──
CREATE TABLE IF NOT EXISTS deals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    value DECIMAL(15,2) DEFAULT 0,
    pipeline_stage VARCHAR(50) DEFAULT 'qualification',
    contact_id INT,
    assigned_user_id INT,
    close_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ── Omnichannel Messages ──
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lead_id INT,
    channel ENUM('whatsapp', 'telegram', 'email', 'sms') NOT NULL,
    direction ENUM('inbound', 'outbound') NOT NULL,
    content TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id),
    INDEX (lead_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (lead_id) REFERENCES contacts(id) ON DELETE SET NULL
);

-- ── Activity Log (Audit Trail) ──
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT,
    entity_type VARCHAR(50),
    entity_id INT,
    action VARCHAR(50),
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
