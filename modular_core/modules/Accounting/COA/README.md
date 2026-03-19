# Chart of Accounts (COA) Management Module

## Overview

The COA Management module provides comprehensive functionality for managing the 5-level hierarchical chart of accounts, allocation accounts, and WIP account monitoring.

## Features

### 1. Account Balance Viewer
- View current debit total, credit total, and net balance per account
- Scoped by Company Code and Financial Period
- Real-time balance calculation from journal entry lines

### 2. COA Import/Export
- Export entire COA to Excel with all attributes
- Import COA from Excel preserving 5-level hierarchy
- Validation and error reporting during import
- Support for bilingual account names (English and Arabic)

### 3. Account Merge Tool
- Transfer all historical journal lines from source to target account
- Automatically block source account after merge
- Transaction-safe operation with rollback on failure
- Audit trail of merge operations

### 4. Account Usage Report
- List all journal entry lines posted to an account
- Filter by date range
- Include voucher details and status
- Export capability

### 5. Opening Balance Entries
- Create opening balances per account per Company Code
- Support for multi-currency opening balances
- Automatic base currency (EGP) conversion
- Upsert functionality to update existing opening balances

### 6. Allocation Accounts Engine
- Configure allocation rules with percentage-based distribution
- Automatic distribution when posting to allocation accounts
- Validation that allocation percentages sum to 100%
- Support for multiple target accounts per source allocation account
- Transaction-safe distribution with rollback on failure

### 7. WIP Stale Check
- Celery task to identify WIP accounts with no movement > 90 days
- Automatic notification to Accountant role users
- Configurable threshold (default: 90 days)
- Monthly scheduled execution via Celery Beat
- Per-tenant and per-company execution

## Database Schema

### chart_of_accounts
- 5-level hierarchy: Category → Group → Sub-group → Account → Sub-account
- Account types: Asset, Liability, Equity, Income, Expense, Cost, Allocation
- Currency and company restrictions via arrays
- Blocked flag to prevent new postings
- Bilingual support (English and Arabic)

### account_opening_balances
- Opening balances per account, currency, and period
- Stores both transaction currency and base currency amounts
- Exchange rate tracking

### allocation_account_rules
- Source account (must be type='Allocation')
- Target account
- Allocation percentage (0-100)
- Active flag for enabling/disabling rules

## API Endpoints

### COA Management
- `GET /api/v1/accounting/coa` - List all accounts
- `GET /api/v1/accounting/coa/{code}` - Get account details
- `GET /api/v1/accounting/coa/{code}/balance` - Get account balance
- `GET /api/v1/accounting/coa/{code}/usage` - Get account usage report
- `POST /api/v1/accounting/coa/merge` - Merge accounts
- `POST /api/v1/accounting/coa/opening-balance` - Create opening balance
- `GET /api/v1/accounting/coa/export` - Export to Excel
- `POST /api/v1/accounting/coa/import` - Import from Excel
- `GET /api/v1/accounting/coa/stale-wip` - Get stale WIP accounts

### Allocation Rules
- `GET /api/v1/accounting/allocation-rules` - Get allocation rules
- `POST /api/v1/accounting/allocation-rules` - Create/update allocation rule
- `DELETE /api/v1/accounting/allocation-rules/{id}` - Delete allocation rule
- `POST /api/v1/accounting/allocation-rules/distribute` - Manually trigger distribution

## Permissions

- `accounting.coa.view` - View COA and balances
- `accounting.coa.create` - Create new accounts
- `accounting.coa.update` - Update existing accounts
- `accounting.coa.delete` - Delete accounts (soft delete)
- `accounting.coa.merge` - Merge accounts
- `accounting.coa.import` - Import COA from Excel
- `accounting.coa.export` - Export COA to Excel
- `accounting.coa.opening_balance` - Create opening balances
- `accounting.allocation.view` - View allocation rules
- `accounting.allocation.manage` - Create/update/delete allocation rules
- `accounting.allocation.distribute` - Trigger allocation distribution

## Services

### COAService
Main service for COA operations:
- `getAccountBalanceViewer()` - Get account balance data
- `exportToExcel()` - Export COA to Excel
- `importFromExcel()` - Import COA from Excel
- `mergeAccounts()` - Merge two accounts
- `getAccountUsageReport()` - Get account usage report
- `createOpeningBalance()` - Create opening balance entry
- `getStaleWIPAccounts()` - Get WIP accounts with no movement

### AllocationService
Service for allocation account operations:
- `getAllocationRules()` - Get allocation rules for an account
- `distribute()` - Distribute amount from allocation account to targets
- `saveAllocationRule()` - Create or update allocation rule
- `deleteAllocationRule()` - Delete allocation rule

## Celery Tasks

### check_stale_wip_accounts
- Task name: `check_stale_wip_accounts`
- Parameters:
  - `tenant_id` (optional) - Specific tenant to check
  - `company_code` (optional) - Specific company to check
  - `days` (default: 90) - Threshold for stale accounts
- Returns: Summary of stale accounts found and notifications sent

### schedule_wip_stale_check_all_tenants
- Task name: `schedule_wip_stale_check_all_tenants`
- Scheduled: Monthly via Celery Beat
- Checks all tenants and companies for stale WIP accounts
- Sends notifications to Accountant role users

## Usage Examples

### Get Account Balance
```php
$coaService = new COAService();
$balance = $coaService->getAccountBalanceViewer(
    $tenantId, 
    $companyCode, 
    '1.1.1.001', 
    '202403'
);
```

### Merge Accounts
```php
$coaService = new COAService();
$result = $coaService->mergeAccounts(
    $tenantId,
    $companyCode,
    'OLD_ACCOUNT_CODE',
    'NEW_ACCOUNT_CODE',
    $userId
);
```

### Create Allocation Rule
```php
$allocationService = new AllocationService();
$rule = $allocationService->saveAllocationRule(
    $tenantId,
    $companyCode,
    'ALLOC_001',  // Source allocation account
    'EXPENSE_001', // Target account
    50.0,          // 50% allocation
    $userId
);
```

### Distribute Allocation
```php
$allocationService = new AllocationService();
$result = $allocationService->distribute(
    $journalLineId,
    $tenantId,
    $companyCode,
    $userId
);
```

## Requirements Implemented

This module implements the following requirements from the specification:

- **Requirement 45.1**: 5-level hierarchy per Company Code
- **Requirement 45.2**: Account type classification
- **Requirement 45.3**: Currency restrictions (allowed_currencies array)
- **Requirement 45.4**: Company restrictions (allowed_companies array)
- **Requirement 45.5**: Blocked flag to prevent new postings
- **Requirement 45.6**: Account balance viewer
- **Requirement 45.7**: COA import/export from Excel
- **Requirement 45.8**: Account merge tool
- **Requirement 45.9**: Account usage report
- **Requirement 45.10**: Opening balance journal entries
- **Requirement 45.11**: Allocation accounts engine
- **Requirement 45.12**: WIP stale check with notification

## Testing

Unit tests should cover:
- Account balance calculation accuracy
- Merge operation completeness
- Allocation rule validation (sum to 100%)
- Allocation distribution accuracy
- WIP stale account detection
- Import/export data integrity

## Future Enhancements

- Bulk account creation via API
- Account hierarchy visualization
- Allocation rule templates
- Advanced WIP analytics
- Account activity dashboard
- Automated account reconciliation
