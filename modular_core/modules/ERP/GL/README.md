# ERP General Ledger Module

## Overview

This module implements the foundational accounting functionality for the NexSaaS ERP system, including:

- **Double-entry bookkeeping** with automatic balance validation
- **Multi-company support** (6 companies per tenant)
- **Multi-currency capabilities** (6 currencies: EGP, USD, AED, SAR, EUR, GBP)
- **Financial period management** with open/close/lock controls
- **35-field journal entry structure** as per design specifications
- **Financial statements** (Trial Balance, P&L, Balance Sheet, Cash Flow)

## Requirements Implemented

- **18.1**: Chart of Accounts with 5-level hierarchy
- **18.2, 18.3**: Double-entry balance validation (Σ Dr = Σ Cr)
- **18.4**: Financial periods table
- **18.5, 18.6, 18.7**: Auto-assignment of voucher_code and section_code
- **18.8**: DECIMAL(15,2) precision for monetary amounts
- **18.9**: Explicit company_code filter requirement
- **18.10**: Trial Balance and Financial Statements
- **18.12**: Multi-currency conversion to EGP
- **18.13**: Closed period immutability

## Services

### JournalEntryService

**Location**: `modular_core/modules/ERP/GL/JournalEntryService.php`

**Key Methods**:
- `post(array $entry): array` - Post a journal entry with full validation

**Features**:
- Validates double-entry balance (Σ Dr = Σ Cr)
- Validates period is open for posting
- Auto-assigns voucher_code based on currency (EGP→1, USD→2, AED→3, SAR→4, EUR→5, GBP→6, Settlement→999)
- Auto-assigns section_code (Income→01, Expense→02, Settlement→991-996)
- Stores all 35 fields per journal entry line
- Converts amounts to EGP using exchange rate
- Requires explicit company_code filter
- Atomic transaction with rollback on failure

**Validation Rules**:
- Minimum 2 lines per entry
- Each line must be either Dr OR Cr, not both
- Total Dr must equal Total Cr (within 0.01 tolerance)
- Period must be open (not closed or locked)
- Settlement vouchers (999) cannot use section codes 01 or 02
- Non-settlement vouchers (1-6) must use section codes 01 or 02

### TrialBalanceService

**Location**: `modular_core/modules/ERP/GL/TrialBalanceService.php`

**Key Methods**:
- `generate(string $companyCode, string $finPeriod, ?string $currencyCode = null): array`

**Features**:
- Generates trial balance per company per period
- Shows debit/credit/net per account
- Supports currency filtering
- Returns amounts in both transaction currency and EGP

### FinancialStatementService

**Location**: `modular_core/modules/ERP/GL/FinancialStatementService.php`

**Key Methods**:
- `generateProfitAndLoss(string $companyCode, string $finPeriod): array`
- `generateBalanceSheet(string $companyCode, string $finPeriod): array`
- `generateCashFlow(string $companyCode, string $finPeriod): array`

**Features**:
- **P&L Statement**: Income - Cost - Expenses = Net Income
- **Balance Sheet**: Assets = Liabilities + Equity
- **Cash Flow Statement**: Direct method from bank movements

### FinPeriodService

**Location**: `modular_core/modules/ERP/GL/FinPeriodService.php`

**Key Methods**:
- `close(string $companyCode, string $finPeriod, string $closedBy): array`
- `lock(string $companyCode, string $finPeriod, string $lockedBy): array`
- `reopen(string $companyCode, string $finPeriod): array`
- `isOpen(string $companyCode, string $finPeriod): bool`

**Features**:
- Close periods to prevent new postings
- Lock periods for stricter immutability
- Reopen closed periods (locked periods cannot be reopened)
- Status transitions: open → closed → locked

## Property-Based Tests

All tests use the `eris/eris` PHP property-based testing library with minimum 100 iterations.

### Property 17: Double-Entry Balance Invariant
**File**: `modular_core/tests/Properties/DoubleEntryBalanceTest.php`
**Validates**: Requirements 18.2, 18.3, 46.6

Tests that for any journal entry, Σ Dr must equal Σ Cr in both transaction currency and EGP.

### Property 18: Voucher Code Assignment
**File**: `modular_core/tests/Properties/VoucherCodeAssignmentTest.php`
**Validates**: Requirements 18.5, 18.6, 18.7

Tests that voucher_code is correctly auto-assigned based on currency, and settlement vouchers follow special rules.

### Property 19: Company Code Query Isolation
**File**: `modular_core/tests/Properties/CompanyCodeQueryIsolationTest.php`
**Validates**: Requirements 18.9

Tests that all queries require explicit company_code filter and results never mix companies.

### Property 20: Closed Period Immutability
**File**: `modular_core/tests/Properties/ClosedPeriodImmutabilityTest.php`
**Validates**: Requirements 18.13, 46.19, 58.2

Tests that posting to closed/locked periods is rejected and period status transitions are valid.

### Property 21: Monetary Amount Precision
**File**: `modular_core/tests/Properties/MonetaryAmountPrecisionTest.php`
**Validates**: Requirements 18.8, 44.6

Tests that all monetary amounts use DECIMAL(15,2) precision and are serialized as strings in JSON.

## Database Schema

### Tables Created (Migrations 031-033)

1. **companies** - Multi-company master (01-06)
2. **currencies** - Currency master (EGP, USD, AED, SAR, EUR, GBP)
3. **exchange_rates** - Daily exchange rates
4. **voucher_sections** - Voucher and section code mappings
5. **partners** - Partner equity stakes
6. **financial_periods** - Period management (YYYYMM format)
7. **chart_of_accounts** - 5-level account hierarchy
8. **cost_centers** - Cost center master
9. **vendors** - Customer and supplier master
10. **fixed_assets** - Asset register
11. **employees** - Employee master
12. **journal_entry_headers** - Journal entry headers
13. **journal_entry_lines** - Journal entry lines (35 fields)
14. **journal_audit_log** - Immutable audit trail
15. **account_balances** - Materialized balances

### Key Indexes

- `idx_je_lines_tenant_company` - Tenant + company isolation
- `idx_je_lines_period` - Period-based queries
- `idx_je_lines_account` - Account-based queries
- `idx_je_lines_vendor` - Vendor-based queries
- `idx_je_lines_cost_center` - Cost center reporting
- `idx_je_lines_employee` - Employee expense tracking
- `idx_je_lines_partner` - Partner profit distribution

## Usage Example

```php
use Modules\ERP\GL\JournalEntryService;
use Core\BaseModel;

// Initialize service
$db = getAdodbConnection();
$model = new BaseModel($db, $tenantId, $companyCode);
$jeService = new JournalEntryService($model);

// Post a journal entry
$entry = [
    'company_code' => '01',
    'fin_period' => '202501',
    'voucher_date' => '2025-01-15',
    'currency_code' => '01', // EGP
    'exchange_rate' => 1.0,
    'description' => 'January rent payment',
    'lines' => [
        [
            'account_code' => '5.1.1.001', // Rent expense
            'dr_value' => 10000.00,
            'cr_value' => 0.00,
            'line_desc' => 'Office rent - January 2025',
        ],
        [
            'account_code' => '1.1.2.001', // Bank account
            'dr_value' => 0.00,
            'cr_value' => 10000.00,
            'line_desc' => 'Payment from bank',
            'check_transfer_no' => 'CHK-12345',
        ],
    ],
    'created_by' => 'user123',
];

$result = $jeService->post($entry);

if ($result['success']) {
    echo "Journal entry posted successfully!\n";
    echo "Voucher No: " . $result['data']['voucher_no'] . "\n";
    echo "Voucher Code: " . $result['data']['voucher_code'] . "\n";
} else {
    echo "Error: " . $result['error'] . "\n";
}
```

## Running Tests

```bash
# Install dependencies
cd modular_core
composer install

# Run property-based tests
vendor/bin/phpunit tests/Properties/DoubleEntryBalanceTest.php
vendor/bin/phpunit tests/Properties/VoucherCodeAssignmentTest.php
vendor/bin/phpunit tests/Properties/CompanyCodeQueryIsolationTest.php
vendor/bin/phpunit tests/Properties/ClosedPeriodImmutabilityTest.php
vendor/bin/phpunit tests/Properties/MonetaryAmountPrecisionTest.php

# Run all property tests
vendor/bin/phpunit tests/Properties/
```

## Next Steps

The following subtasks remain for complete ERP implementation:

- **Task 20**: Invoicing and Accounts Receivable
- **Task 21**: Expense Management and Accounts Payable
- **Task 22**: Inventory and Warehouse Management
- **Task 23**: Procurement Management
- **Task 24**: HR and Employee Management
- **Task 25**: Payroll Processing
- **Task 26**: Project Management
- **Task 27**: Manufacturing and Bill of Materials

## Design Constraints

- Every table includes: id, company_code, tenant_id, created_by, created_at, updated_at, deleted_at
- All monetary amounts: DECIMAL(15,2) - never float
- Financial period: VARCHAR(6) YYYYMM format
- 6 companies per tenant: company_code '01' through '06'
- 6 currencies: EGP (01), USD (02), AED (03), SAR (04), EUR (05), GBP (06)
- Exchange rates: DECIMAL(10,6)
- All journal entry lines have 35 fields as specified in design.md
- Voucher codes: 1-6 for currencies, 999 for settlements
- Section codes: 01 (Income), 02 (Expense), 991-996 (Settlement by currency)
- Settlement vouchers (999) cannot use section codes 01 or 02
