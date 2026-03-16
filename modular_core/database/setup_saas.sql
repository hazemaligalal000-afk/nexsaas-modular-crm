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

-- Tenant Scoped Tables Example
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organization_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(255),
    lifecycle_stage VARCHAR(50) DEFAULT 'lead',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (organization_id),
    INDEX (phone),
    INDEX (email)
);

-- Seed some scoped data
INSERT INTO contacts (organization_id, first_name, last_name, email) VALUES (1, 'John', 'Doe', 'john@acme.com');
INSERT INTO contacts (organization_id, first_name, last_name, email) VALUES (2, 'Hank', 'Scorpio', 'hank@globex.com');
