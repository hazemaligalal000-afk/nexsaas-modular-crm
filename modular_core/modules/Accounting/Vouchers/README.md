# Journal Entry and Voucher Engine (Batch B)

**Task 30 Implementation Complete**

This module implements the full-featured journal entry and voucher engine for the NexSaaS Accounting system.

## Requirements Addressed

- **Requirements 46.1-46.20**: Journal Entry and Voucher Engine
- **Requirements 47.10, 47.11**: Multi-Currency Settlement and Trial Balance

## Components

### 1. VoucherService.php
**Task 30.1, 30.2, 30.3, 30.4, 30.5, 30.6**

Core voucher management service implementing:

- ✅ **All 35 fields** per journal entry line (Req 46.1, 18.4)
- ✅ **Validation**: Double-entry balance check (Σ Dr = Σ Cr) (Req 46.6, 18.2, 18.3)
- ✅ **Auto-assignment**: Voucher_Code and Section_Code based on currency (Req 46.2, 18.5-18.7)
- ✅ **Period validation**: Ensures period is open before posting (Req 46.3, 46.19)
- ✅ **Service date support**: Allows service_date to differ from voucher_date (Req 46.4)
- ✅ **Multi-line entries**: Unlimited debit and credit lines per voucher (Req 46.5)
- ✅ **FX rate auto-fill**: From Redis cache with user override (Req 46.7, 47.5)
- ✅ **Dual currency display**: Transaction currency + EGP equivalent (Req 46.8, 18.12)
- ✅ **Word count calculation**: Auto-calculate amounts from word counts (Req 46.12)
- ✅ **Opening balance support**: Bypasses balance check for Owner role (Req 46.20)

#### Approval State Machine (Task 30.2)
**Requirement 46.13**

State transitions:
```
Draft → Submitted → Approved → Posted → Reversed
```

Methods:
- `submit()`: Draft → Submitted
- `approve()`: Submitted → Approved
- `post()`: Approved → Posted
- `reverse()`: Posted → Reversed (creates equal-and-opposite voucher)
- `copy()`: Creates new draft voucher with same lines (Req 46.15)

#### Bulk Import (Task 30.4)
**Requirement 46.16**

- Imports vouchers from Excel files
- Validates all 35 fields per row
- Reports row-level errors before committing
- Groups lines by voucher_no

#### Search Interface (Task 30.5)
**Requirement 46.17**

Filterable by:
- Company_Code
- Fin_Period
- Voucher_Code
- Section_Code
- Status
- Date range
- Account_Code (in lines)
- Vendor_Code (in lines)

#### PDF Generation (Task 30.6)
**Requirement 46.18**

- Bilingual PDF (Arabic RTL + English LTR)
- Company letterhead
- All journal lines
- Posted vouchers only
- Uses mPDF library

### 2. SettlementVoucherService.php
**Task 30.7**

**Requirements: 47.10, 18.7**

Settlement voucher engine for inter-currency settlements:

- ✅ **Voucher_Code 999**: Dedicated settlement voucher code
- ✅ **Section_Codes 991-996**: One per currency (EGP, USD, AED, SAR, EUR, GBP)
- ✅ **Net settlement calculation**: Computes net position per currency per period
- ✅ **Validation**: Enforces settlement-specific rules

Methods:
- `createSettlement()`: Create settlement voucher with code 999
- `calculateNetSettlement()`: Calculate net settlement amount for a currency
- `getSettlementVouchers()`: Retrieve all settlement vouchers for a period

### 3. TrialBalanceService.php
**Task 30.8**

**Requirement 47.11**

Multi-currency trial balance reporting:

- ✅ **Dual currency columns**: Transaction currency + EGP equivalent
- ✅ **Debit/Credit/Net**: Shows all three columns for each currency
- ✅ **Account-level detail**: Balances per account per currency
- ✅ **Account type grouping**: Summary by account type
- ✅ **CSV export**: Export to CSV with bilingual headers

Methods:
- `generateMultiCurrencyTrialBalance()`: Main trial balance report
- `generateTrialBalanceByAccountType()`: Summary by account type
- `exportToCSV()`: Export to CSV file

### 4. VendorLookupService.php
**Task 30.3**

**Requirement 46.9**

Vendor typeahead lookup:

- ✅ **Search by code or name**: ILIKE pattern matching
- ✅ **Company-scoped**: Searches within selected Company_Code
- ✅ **Usage-based ranking**: Orders by frequency of use
- ✅ **Recent vendors**: Returns recently used vendors

Methods:
- `searchVendors()`: Typeahead search
- `getVendorByCode()`: Get vendor details
- `getRecentVendors()`: Recently used vendors

### 5. VoucherController.php

REST API endpoints:

#### Voucher Management
- `POST /api/accounting/vouchers` - Create voucher
- `GET /api/accounting/vouchers` - Search vouchers
- `GET /api/accounting/vouchers/:id` - Get voucher details
- `POST /api/accounting/vouchers/:id/submit` - Submit for approval
- `POST /api/accounting/vouchers/:id/approve` - Approve voucher
- `POST /api/accounting/vouchers/:id/post` - Post to ledger
- `POST /api/accounting/vouchers/:id/reverse` - Reverse voucher
- `POST /api/accounting/vouchers/:id/copy` - Copy voucher
- `POST /api/accounting/vouchers/import` - Bulk import from Excel
- `GET /api/accounting/vouchers/:id/pdf` - Generate PDF

#### Settlement Vouchers
- `POST /api/accounting/vouchers/settlement` - Create settlement voucher
- `GET /api/accounting/vouchers/settlement/calculate` - Calculate net settlement

#### Reports
- `GET /api/accounting/reports/trial-balance` - Multi-currency trial balance
- `GET /api/accounting/reports/trial-balance/export` - Export to CSV

## Database Schema

### journal_entry_headers
- Voucher header with totals
- Status workflow tracking
- Reversal linkage

### journal_entry_lines
- All 35 fields per line
- Transaction currency + EGP amounts
- Full audit trail

See `modular_core/database/migrations/033_journal_entries.sql` for complete schema.

## Usage Examples

### Create Voucher

```php
$voucherService = new VoucherService($model, $tenantId, $companyCode, $redis);

$result = $voucherService->save([
    'header' => [
        'company_code' => '01',
        'fin_period' => '202401',
        'voucher_date' => '2024-01-15',
        'currency_code' => '02', // USD
        'description' => 'Office supplies purchase',
    ],
    'lines' => [
        [
            'account_code' => '5100',
            'dr_value' => 1000.00,
            'cr_value' => 0.00,
            'line_desc' => 'Office supplies',
        ],
        [
            'account_code' => '2100',
            'dr_value' => 0.00,
            'cr_value' => 1000.00,
            'line_desc' => 'Accounts payable',
            'vendor_code' => 'V001',
            'vendor_name' => 'Office Depot',
        ],
    ]
], $userId);
```

### Approval Workflow

```php
// Submit for approval
$voucherService->submit($voucherId, $userId);

// Approve
$voucherService->approve($voucherId, $approverId);

// Post to ledger
$voucherService->post($voucherId, $posterId);
```

### Create Settlement Voucher

```php
$settlementService = new SettlementVoucherService($model, $voucherService, $tenantId, $companyCode);

$result = $settlementService->createSettlement([
    'currency_code' => '02', // USD
    'fin_period' => '202401',
    'voucher_date' => '2024-01-31',
    'lines' => [
        // Settlement lines
    ]
], $userId);
```

### Generate Trial Balance

```php
$trialBalanceService = new TrialBalanceService($model, $tenantId);

$result = $trialBalanceService->generateMultiCurrencyTrialBalance(
    '01',      // company_code
    '202401',  // fin_period
    null       // all currencies
);
```

## Key Features

### 1. Auto-Assignment Logic
- **Voucher_Code**: Auto-assigned based on currency (EGP→1, USD→2, etc.)
- **Section_Code**: Auto-assigned based on transaction type (Income→01, Expense→02)
- **Settlement**: Special handling for Voucher_Code 999 with Section_Codes 991-996

### 2. FX Rate Integration
- Redis cache: `fx:rate:{currency_code}:{date}`
- 24-hour TTL
- User override supported
- Fallback to database

### 3. Validation Rules
- Double-entry balance: Σ Dr = Σ Cr (tolerance 0.01)
- Period must be open
- Each line must be Dr OR Cr, not both
- Settlement vouchers cannot use section codes 01 or 02

### 4. Word Count Calculation
- `vendor_word_count × vendor_per_word_rate = amount`
- `translator_word_count × translator_per_word_rate = amount`
- Auto-assigns to Dr or Cr based on context

### 5. Bilingual Support
- Arabic RTL + English LTR
- PDF generation with bilingual labels
- Account descriptions in both languages

## Testing

Property-based tests should verify:
- Double-entry balance invariant
- Voucher code assignment correctness
- Period immutability
- Settlement voucher section code restrictions
- Multi-currency trial balance accuracy

## Dependencies

- **mPDF**: PDF generation with RTL support
- **PhpSpreadsheet**: Excel import
- **Redis**: FX rate caching (optional)
- **PostgreSQL**: Database backend

## Future Enhancements

- [ ] Recurring vouchers
- [ ] Voucher templates
- [ ] Batch approval workflow
- [ ] Advanced search with full-text
- [ ] Voucher attachments (receipts, invoices)
- [ ] Email notifications on status changes
- [ ] Audit trail viewer
- [ ] Budget vs. actual comparison

## Related Modules

- **COA Management** (`modular_core/modules/Accounting/COA/`)
- **Financial Periods** (`modular_core/modules/ERP/GL/FinPeriodService.php`)
- **Exchange Rates** (FX Engine - Batch C)
- **AR/AP** (Batch D)

## Compliance

- ✅ Requirement 18.2: Double-entry balance
- ✅ Requirement 18.3: Balance validation
- ✅ Requirement 18.4: All 35 fields
- ✅ Requirement 18.5-18.7: Voucher/section code assignment
- ✅ Requirement 18.9: Company code isolation
- ✅ Requirement 18.12: Multi-currency support
- ✅ Requirement 46.1-46.20: Full voucher engine
- ✅ Requirement 47.10: Settlement vouchers
- ✅ Requirement 47.11: Multi-currency trial balance

---

**Implementation Status**: ✅ Complete

**Task 30 Sub-tasks**:
- ✅ 30.1: VoucherService::save() with validation, auto-assignment, all 35 fields, word count
- ✅ 30.2: Approval state machine (Draft → Submitted → Approved → Posted → Reversed)
- ✅ 30.3: FX rate auto-fill from Redis with user override, vendor lookup typeahead
- ✅ 30.4: Bulk voucher import from Excel with validation
- ✅ 30.5: Voucher search interface with multiple filters
- ✅ 30.6: Bilingual PDF generation (mPDF, Arabic RTL + English LTR)
- ✅ 30.7: Settlement_Voucher engine (Voucher_Code 999, Section_Codes 991-996)
- ✅ 30.8: Multi-currency trial balance (transaction currency + EGP)
