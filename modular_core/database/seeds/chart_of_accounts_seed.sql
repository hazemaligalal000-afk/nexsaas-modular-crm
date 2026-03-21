-- Chart of Accounts Seed Data (Standard 5-level hierarchy)
-- 1. ASSETS
-- 2. LIABILITIES
-- 3. EQUITY
-- 4. INCOME
-- 5. EXPENSES

INSERT INTO chart_of_accounts (tenant_id, company_code, account_code, account_name_en, account_name_ar, account_level, account_type, parent_code, allow_posting, balance_type, created_by)
VALUES
    -- Level 1: Categories
    (:tenant_id, '01', '1', 'Assets', 'الأصول', 1, 'Asset', NULL, FALSE, 'debit', 'system'),
    (:tenant_id, '01', '2', 'Liabilities', 'الخصوم', 1, 'Liability', NULL, FALSE, 'credit', 'system'),
    (:tenant_id, '01', '3', 'Equity', 'حقوق الملكية', 1, 'Equity', NULL, FALSE, 'credit', 'system'),
    (:tenant_id, '01', '4', 'Income', 'الإيرادات', 1, 'Income', NULL, FALSE, 'credit', 'system'),
    (:tenant_id, '01', '5', 'Expenses', 'المصروفات', 1, 'Expense', NULL, FALSE, 'debit', 'system'),

    -- Level 2: Groups (under Assets)
    (:tenant_id, '01', '1.1', 'Current Assets', 'الأصول المتداولة', 2, 'Asset', '1', FALSE, 'debit', 'system'),
    (:tenant_id, '01', '1.2', 'Fixed Assets', 'الأصول الثابتة', 2, 'Asset', '1', FALSE, 'debit', 'system'),

    -- Level 3: Sub-groups (under Current Assets)
    (:tenant_id, '01', '1.1.1', 'Cash and Bank', 'النقدية بالخزينة والبنوك', 3, 'Asset', '1.1', FALSE, 'debit', 'system'),
    (:tenant_id, '01', '1.1.2', 'Accounts Receivable', 'العملاء', 3, 'Asset', '1.1', FALSE, 'debit', 'system'),

    -- Level 4: Accounts (under Cash and Bank)
    (:tenant_id, '01', '1.1.1.1', 'Main Cash EGP', 'الخزينة الرئيسية - جنية', 4, 'Asset', '1.1.1', TRUE, 'debit', 'system'),
    (:tenant_id, '01', '1.1.1.2', 'CIB Bank EGP', 'بنك CIB - جنية', 4, 'Asset', '1.1.1', TRUE, 'debit', 'system'),
    (:tenant_id, '01', '1.1.1.3', 'Petty Cash', 'العهدة النقدية', 4, 'Asset', '1.1.1', TRUE, 'debit', 'system'),

    -- Level 4: Accounts (under Income)
    (:tenant_id, '01', '4.1', 'Sales Revenue', 'إيرادات المبيعات', 2, 'Income', '4', FALSE, 'credit', 'system'),
    (:tenant_id, '01', '4.1.1', 'Translation Services', 'خدمات الترجمة', 3, 'Income', '4.1', TRUE, 'credit', 'system'),

    -- Level 4: Accounts (under Expenses)
    (:tenant_id, '01', '5.1', 'Operating Expenses', 'المصاريف التشغيلية', 2, 'Expense', '5', FALSE, 'debit', 'system'),
    (:tenant_id, '01', '5.1.1', 'Salaries', 'الرواتب والأجور', 3, 'Expense', '5.1', TRUE, 'debit', 'system'),
    (:tenant_id, '01', '5.1.2', 'Rent', 'الإيجار', 3, 'Expense', '5.1', TRUE, 'debit', 'system'),
    (:tenant_id, '01', '5.1.3', 'Electricity', 'الكهرباء', 3, 'Expense', '5.1', TRUE, 'debit', 'system')
ON CONFLICT (tenant_id, company_code, account_code, deleted_at) DO NOTHING;
