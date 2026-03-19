-- ══════════════════════════════════════════════════════════════════════════
-- NexSaaS Accounting Seed Data
-- Populates: Companies, Currencies, Voucher Sections, Partners
-- Based on: Company_Code.xlsx, Currency_Code.xlsx, Vocher___Section_Code.xlsx,
--           Partners_Code.xlsx
-- 
-- NOTE: Replace 'YOUR_TENANT_ID_HERE' with actual tenant UUID during provisioning
-- ══════════════════════════════════════════════════════════════════════════

-- Set tenant ID variable (replace in application)
-- \set tenant_id 'YOUR_TENANT_ID_HERE'

-- ══════════════════════════════════════════════════════════════════════════
-- 1. COMPANIES (from Company_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO companies (tenant_id, code, name_en, name_ar, activity, tax_card_no, tax_office, tax_card_expiry, commercial_reg_no, commercial_reg_expiry, vat_registered, e_invoice_active, created_by)
VALUES
    (:tenant_id, '01', 'Globalize Group', 'جلوباليز جروب', 'Translation', '723603790', 'مامورية الجيزة', '2026-11-03', '4840', '2029-04-07', FALSE, TRUE, 'system'),
    (:tenant_id, '02', 'Digitalize Business Solutions', 'ديجيتاليز', 'Call Center', NULL, NULL, NULL, NULL, NULL, FALSE, FALSE, 'system'),
    (:tenant_id, '03', 'Brandora', NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, FALSE, 'system'),
    (:tenant_id, '04', 'Project Metric', NULL, NULL, NULL, NULL, NULL, NULL, NULL, FALSE, FALSE, 'system'),
    (:tenant_id, '05', 'Jusor', 'جسور', 'Translation', NULL, NULL, NULL, NULL, NULL, FALSE, FALSE, 'system'),
    (:tenant_id, '06', 'شبكات', 'شبكات', NULL, NULL, NULL, NULL, NULL, NULL, FALSE, FALSE, 'system')
ON CONFLICT (tenant_id, code, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- 2. CURRENCIES (from Currency_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO currencies (tenant_id, code, iso_code, name_en, name_ar, country_en, country_ar, symbol, is_base_currency, created_by)
VALUES
    (:tenant_id, '01', 'EGP', 'Egyptian Pound', 'جنية مصري', 'Egypt', 'جمهورية مصر العربية', 'ج.م', TRUE, 'system'),
    (:tenant_id, '02', 'USD', 'US Dollar', 'دولار أمريكي', 'United States', 'الولايات المتحدة الامريكية', '$', FALSE, 'system'),
    (:tenant_id, '03', 'AED', 'Emirati Dirham', 'درهم إماراتي', 'United Arab Emirates', 'الامارات العربية المتحدة', 'د.إ', FALSE, 'system'),
    (:tenant_id, '04', 'SAR', 'Saudi Riyal', 'ريال سعودي', 'Saudi Arabia', 'السعودية', 'ر.س', FALSE, 'system'),
    (:tenant_id, '05', 'EUR', 'Euro', 'يورو', 'Europe', 'أوربا', '€', FALSE, 'system'),
    (:tenant_id, '06', 'GBP', 'Sterling', 'إسترليني', 'United Kingdom', 'المملكة المتحدة بريطانيا', '£', FALSE, 'system')
ON CONFLICT (tenant_id, code, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- 3. VOUCHER SECTIONS (from Vocher___Section_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO voucher_sections (tenant_id, voucher_code, section_code, description_en, description_ar, currency_code, section_type, created_by)
VALUES
    -- Voucher 1 (EGP)
    (:tenant_id, '1', '01', 'Income EGP', 'ايراد بالجنية المصري', '01', 'income', 'system'),
    (:tenant_id, '1', '02', 'Expenses EGP', 'مصروف بالجنية المصري', '01', 'expense', 'system'),
    
    -- Voucher 2 (USD)
    (:tenant_id, '2', '01', 'Income USD', 'ايراد بالدولار', '02', 'income', 'system'),
    (:tenant_id, '2', '02', 'Expenses USD', 'مصروف بالدولار', '02', 'expense', 'system'),
    
    -- Voucher 3 (AED)
    (:tenant_id, '3', '01', 'Income AED', 'ايراد بالدرهم', '03', 'income', 'system'),
    (:tenant_id, '3', '02', 'Expenses AED', 'مصروف بالدرهم', '03', 'expense', 'system'),
    
    -- Voucher 4 (SAR)
    (:tenant_id, '4', '01', 'Income SAR', 'ايراد بالريال', '04', 'income', 'system'),
    (:tenant_id, '4', '02', 'Expenses SAR', 'مصروف بالريال', '04', 'expense', 'system'),
    
    -- Voucher 5 (EUR)
    (:tenant_id, '5', '01', 'Income EUR', 'ايراد باليورو', '05', 'income', 'system'),
    (:tenant_id, '5', '02', 'Expenses EUR', 'مصروف باليورو', '05', 'expense', 'system'),
    
    -- Voucher 6 (GBP)
    (:tenant_id, '6', '01', 'Income GBP', 'ايراد بالجنية الإسترليني', '06', 'income', 'system'),
    (:tenant_id, '6', '02', 'Expenses GBP', 'مصروف بالجنية الإسترليني', '06', 'expense', 'system'),
    
    -- Voucher 999 (Settlements)
    (:tenant_id, '999', '991', 'Settlements EGP', 'تسويات بالجنية المصري', '01', 'settlement', 'system'),
    (:tenant_id, '999', '992', 'Settlements USD', 'تسويات بالدولار', '02', 'settlement', 'system'),
    (:tenant_id, '999', '993', 'Settlements AED', 'تسويات بالدرهم', '03', 'settlement', 'system'),
    (:tenant_id, '999', '994', 'Settlements SAR', 'تسويات بالريال', '04', 'settlement', 'system'),
    (:tenant_id, '999', '995', 'Settlements EUR', 'تسويات باليورو', '05', 'settlement', 'system'),
    (:tenant_id, '999', '996', 'Settlements GBP', 'تسويات بالجنية الإسترليني', '06', 'settlement', 'system')
ON CONFLICT (tenant_id, voucher_code, section_code, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- 4. PARTNERS (from Partners_Code.xlsx)
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO partners (tenant_id, company_code, partner_code, name_ar, name_en, ownership_pct, created_by)
VALUES
    -- Globalize Group
    (:tenant_id, '01', 'PG01', 'أحمد عبدالغفار محمد', 'Ahmed Abdelghaffar Mohamed', 50.00, 'system'),
    (:tenant_id, '01', 'PG02', 'أبو الفتوح', 'Abou El Fotouh', 50.00, 'system'),
    
    -- Digitalize
    (:tenant_id, '02', 'PD01', 'أحمد عبدالغفار محمد', 'Ahmed Abdelghaffar Mohamed', 50.00, 'system'),
    (:tenant_id, '02', 'PD02', 'أبو الفتوح', 'Abou El Fotouh', 50.00, 'system'),
    
    -- Brandora
    (:tenant_id, '03', 'PB01', 'أحمد عبدالغفار محمد', 'Ahmed Abdelghaffar Mohamed', 50.00, 'system'),
    (:tenant_id, '03', 'PB02', 'أبو الفتوح', 'Abou El Fotouh', 50.00, 'system'),
    
    -- Project Metric
    (:tenant_id, '04', 'PP01', 'أحمد عبدالغفار محمد', 'Ahmed Abdelghaffar Mohamed', 50.00, 'system'),
    (:tenant_id, '04', 'PP02', 'أبو الفتوح', 'Abou El Fotouh', 50.00, 'system'),
    
    -- Jusor
    (:tenant_id, '05', 'PJ01', 'أحمد عبدالغفار محمد', 'Ahmed Abdelghaffar Mohamed', 50.00, 'system'),
    (:tenant_id, '05', 'PJ02', 'أبو الفتوح', 'Abou El Fotouh', 50.00, 'system')
ON CONFLICT (tenant_id, company_code, partner_code, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- 5. DEFAULT EXCHANGE RATES (Initial rates - update daily)
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO exchange_rates (tenant_id, currency_code, rate_date, rate_to_base, source, created_by)
VALUES
    (:tenant_id, '01', CURRENT_DATE, 1.000000, 'system', 'system'),  -- EGP (base)
    (:tenant_id, '02', CURRENT_DATE, 30.900000, 'manual', 'system'),  -- USD
    (:tenant_id, '03', CURRENT_DATE, 8.410000, 'manual', 'system'),   -- AED
    (:tenant_id, '04', CURRENT_DATE, 8.240000, 'manual', 'system'),   -- SAR
    (:tenant_id, '05', CURRENT_DATE, 33.500000, 'manual', 'system'),  -- EUR
    (:tenant_id, '06', CURRENT_DATE, 39.200000, 'manual', 'system')   -- GBP
ON CONFLICT (tenant_id, currency_code, rate_date, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- 6. SAMPLE COST CENTERS
-- ══════════════════════════════════════════════════════════════════════════
INSERT INTO cost_centers (tenant_id, company_code, cost_center_code, cost_center_name_en, cost_center_name_ar, is_active, created_by)
VALUES
    (:tenant_id, '01', 'ADMIN', 'Administration', 'الإدارة', TRUE, 'system'),
    (:tenant_id, '01', 'SALES', 'Sales & Marketing', 'المبيعات والتسويق', TRUE, 'system'),
    (:tenant_id, '01', 'PROD', 'Production', 'الإنتاج', TRUE, 'system'),
    (:tenant_id, '01', 'IT', 'Information Technology', 'تكنولوجيا المعلومات', TRUE, 'system')
ON CONFLICT (tenant_id, company_code, cost_center_code, deleted_at) DO NOTHING;

-- ══════════════════════════════════════════════════════════════════════════
-- 7. FINANCIAL PERIODS (Current year + next year)
-- ══════════════════════════════════════════════════════════════════════════
DO $$
DECLARE
    v_tenant_id UUID := :tenant_id;
    v_company_code VARCHAR(2);
    v_year INT;
    v_month INT;
    v_period_code VARCHAR(6);
    v_start_date DATE;
    v_end_date DATE;
BEGIN
    -- Loop through all companies
    FOR v_company_code IN SELECT code FROM companies WHERE tenant_id = v_tenant_id AND deleted_at IS NULL
    LOOP
        -- Create periods for 2025 and 2026
        FOR v_year IN 2025..2026
        LOOP
            FOR v_month IN 1..12
            LOOP
                v_period_code := v_year::TEXT || LPAD(v_month::TEXT, 2, '0');
                v_start_date := (v_year::TEXT || '-' || v_month::TEXT || '-01')::DATE;
                v_end_date := (v_start_date + INTERVAL '1 month' - INTERVAL '1 day')::DATE;
                
                INSERT INTO financial_periods (tenant_id, company_code, period_code, period_name, start_date, end_date, status, created_by)
                VALUES (
                    v_tenant_id,
                    v_company_code,
                    v_period_code,
                    TO_CHAR(v_start_date, 'Month YYYY'),
                    v_start_date,
                    v_end_date,
                    'open',
                    'system'
                )
                ON CONFLICT (tenant_id, company_code, period_code, deleted_at) DO NOTHING;
            END LOOP;
        END LOOP;
    END LOOP;
END $$;
