<?php
namespace Modules\Accounting\FX;

use Core\BaseController;

/**
 * FXController: Exchange Rate Management API
 * Task 31.2 - Currency master UI and rate management
 * Requirements: 47.1, 47.2, 47.3, 47.4, 47.5
 */
class FXController extends BaseController {
    
    private FXService $fxService;
    
    public function __construct() {
        parent::__construct();
        $this->fxService = new FXService();
    }

    /**
     * GET /api/v1/accounting/fx/rates
     * Get exchange rates for a date range
     */
    public function getRates(): void {
        $currencyCode = $_GET['currency_code'] ?? null;
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        if (!$currencyCode) {
            $this->respond(['error' => 'currency_code is required'], 400);
            return;
        }
        
        $history = $this->fxService->getRateHistory($currencyCode, $startDate, $endDate);
        
        $this->respond([
            'currency_code' => $currencyCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'rates' => $history
        ]);
    }

    /**
     * GET /api/v1/accounting/fx/rates/{currency_code}/{date}
     * Get specific rate for a currency and date
     */
    public function getRate(string $currencyCode, string $date): void {
        try {
            $rate = $this->fxService->getRateForDate($currencyCode, $date);
            
            $this->respond([
                'currency_code' => $currencyCode,
                'date' => $date,
                'rate' => $rate
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/v1/accounting/fx/rates
     * Save or update exchange rate
     */
    public function saveRate(): void {
        $data = $this->getRequestBody();
        
        $currencyCode = $data['currency_code'] ?? null;
        $date = $data['date'] ?? null;
        $rate = $data['rate'] ?? null;
        $source = $data['source'] ?? 'manual';
        
        if (!$currencyCode || !$date || !$rate) {
            $this->respond(['error' => 'currency_code, date, and rate are required'], 400);
            return;
        }
        
        if (!is_numeric($rate) || $rate <= 0) {
            $this->respond(['error' => 'rate must be a positive number'], 400);
            return;
        }
        
        try {
            $this->fxService->saveRate($currencyCode, $date, (float)$rate, $source);
            
            $this->respond([
                'message' => 'Exchange rate saved successfully',
                'currency_code' => $currencyCode,
                'date' => $date,
                'rate' => (float)$rate
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fx/realized-gain-loss
     * Calculate realized FX gain/loss
     */
    public function calculateRealizedGainLoss(): void {
        $data = $this->getRequestBody();
        
        $invoiceId = $data['invoice_id'] ?? null;
        $paymentId = $data['payment_id'] ?? null;
        
        if (!$invoiceId || !$paymentId) {
            $this->respond(['error' => 'invoice_id and payment_id are required'], 400);
            return;
        }
        
        try {
            $result = $this->fxService->computeRealizedGainLoss((int)$invoiceId, (int)$paymentId);
            
            $this->respond([
                'message' => 'Realized FX gain/loss calculated',
                'gain_loss_amount' => $result['gain_loss_amount'],
                'journal_entry_id' => $result['journal_entry_id']
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fx/revaluation
     * Perform unrealized FX revaluation
     */
    public function performRevaluation(): void {
        $data = $this->getRequestBody();
        
        $companyCode = $data['company_code'] ?? null;
        $finPeriod = $data['fin_period'] ?? null;
        $closingDate = $data['closing_date'] ?? null;
        
        if (!$companyCode || !$finPeriod || !$closingDate) {
            $this->respond(['error' => 'company_code, fin_period, and closing_date are required'], 400);
            return;
        }
        
        try {
            $result = $this->fxService->performUnrealizedRevaluation($companyCode, $finPeriod, $closingDate);
            
            $this->respond([
                'message' => 'Unrealized FX revaluation completed',
                'revaluation_entries' => $result['revaluation_entries'],
                'total_adjustment' => $result['total_adjustment']
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/accounting/fx/auto-reverse
     * Auto-reverse revaluation entries
     */
    public function autoReverseRevaluation(): void {
        $data = $this->getRequestBody();
        
        $companyCode = $data['company_code'] ?? null;
        $priorPeriod = $data['prior_period'] ?? null;
        $newPeriod = $data['new_period'] ?? null;
        
        if (!$companyCode || !$priorPeriod || !$newPeriod) {
            $this->respond(['error' => 'company_code, prior_period, and new_period are required'], 400);
            return;
        }
        
        try {
            $count = $this->fxService->autoReverseRevaluation($companyCode, $priorPeriod, $newPeriod);
            
            $this->respond([
                'message' => 'Revaluation entries reversed',
                'entries_reversed' => $count
            ]);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/accounting/fx/translation-report
     * Generate currency translation report
     */
    public function getTranslationReport(): void {
        $companyCode = $_GET['company_code'] ?? null;
        $finPeriod = $_GET['fin_period'] ?? null;
        $fromCurrency = $_GET['from_currency'] ?? null;
        $toCurrency = $_GET['to_currency'] ?? '01';
        
        if (!$companyCode || !$finPeriod || !$fromCurrency) {
            $this->respond(['error' => 'company_code, fin_period, and from_currency are required'], 400);
            return;
        }
        
        try {
            $report = $this->fxService->generateCurrencyTranslationReport(
                $companyCode, 
                $finPeriod, 
                $fromCurrency, 
                $toCurrency
            );
            
            $this->respond($report);
        } catch (\Exception $e) {
            $this->respond(['error' => $e->getMessage()], 500);
        }
    }
}
