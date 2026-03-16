-- Nexa CRM Core Schema Optimization
-- This file ensures all required tables exist for the SaaS multi-tenant environment.

-- 1. Organizations (Tenants)
CREATE TABLE IF NOT EXISTS `vtiger_organizations` (
    `organization_id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `plan_type` VARCHAR(50) DEFAULT 'Trial',
    `owner_user_id` INT,
    `created_date` DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Core Users Table
CREATE TABLE IF NOT EXISTS `vtiger_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_name` VARCHAR(255) UNIQUE NOT NULL,
    `user_hash` VARCHAR(255),
    `organization_id` INT,
    `status` VARCHAR(50) DEFAULT 'Active',
    `deleted` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. CRM Entity (Base for all records)
CREATE TABLE IF NOT EXISTS `vtiger_crmentity` (
    `crmid` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT,
    `smcreatorid` INT,
    `smownerid` INT,
    `modifiedby` INT,
    `setype` VARCHAR(100),
    `createdtime` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `modifiedtime` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `deleted` TINYINT(1) DEFAULT 0
);

-- 4. Leads, Contacts, Accounts stubs for isolation
CREATE TABLE IF NOT EXISTS `vtiger_leaddetails` (
    `leadid` INT PRIMARY KEY,
    `organization_id` INT,
    `firstname` VARCHAR(255),
    `lastname` VARCHAR(255),
    `company` VARCHAR(255),
    `email` VARCHAR(255),
    `phone` VARCHAR(50),
    `leadstatus` VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS `vtiger_contactdetails` (
    `contactid` INT PRIMARY KEY,
    `organization_id` INT,
    `firstname` VARCHAR(255),
    `lastname` VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS `vtiger_account` (
    `accountid` INT PRIMARY KEY,
    `organization_id` INT,
    `accountname` VARCHAR(255)
);

-- 4.5. Potentials (Deals)
CREATE TABLE IF NOT EXISTS `vtiger_potential` (
    `potentialid` INT PRIMARY KEY,
    `potentialname` VARCHAR(255) NOT NULL,
    `organization_id` INT,
    `amount` DECIMAL(25,8),
    `closingdate` DATE,
    `sales_stage` VARCHAR(100),
    `probability` DECIMAL(7,3),
    `nextstep` VARCHAR(100)
);


-- 5. SaaS Specific Tables (API Keys & Webhooks)
CREATE TABLE IF NOT EXISTS `saas_api_keys` (
    `key_id` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT NOT NULL,
    `api_key` VARCHAR(64) UNIQUE NOT NULL,
    `description` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `saas_deal_stages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT NOT NULL,
    `stage_name` VARCHAR(100) NOT NULL,
    `probability` INT DEFAULT 0,
    `sort_order` INT DEFAULT 0
);

-- 6. Indices for high performance
-- CREATE INDEX `idx_tenant_crmentity` ON `vtiger_crmentity` (`organization_id`);
-- CREATE INDEX `idx_tenant_users` ON `vtiger_users` (`organization_id`);

-- 7. Seed Data for Nexa Intelligence HQ
INSERT IGNORE INTO `vtiger_organizations` (`organization_id`, `company_name`, `plan_type`, `owner_user_id`) 
VALUES (1, 'Nexa Intelligence HQ', 'Enterprise', 1);

INSERT IGNORE INTO `vtiger_users` (`id`, `user_name`, `organization_id`, `status`, `user_hash`) 
VALUES (1, 'admin', 1, 'Active', 'password123');

INSERT IGNORE INTO `saas_api_keys` (`organization_id`, `api_key`, `description`) 
VALUES (1, '76b2c8a1d5e3f4g5h6i7j8k9l0m1n2o3p4q5r6s7t8u9v0w1x2y3z4a5b6c7d8e9', 'Production Master Key');

INSERT IGNORE INTO `saas_deal_stages` (`organization_id`, `stage_name`, `probability`, `sort_order`) VALUES 
(1, 'New Lead', 10, 1),
(1, 'Qualified', 20, 2),
(1, 'Demo', 40, 3),
(1, 'Proposal', 60, 4),
(1, 'Negotiation', 80, 5),
(1, 'Closed Won', 100, 6),
(1, 'Closed Lost', 0, 7);
