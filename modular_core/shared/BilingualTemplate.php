<?php

namespace ModularCore\Shared;

/**
 * Bilingual Template Engine
 * 
 * Smarty template support for Arabic RTL + English LTR
 * Requirements: 32.4, 58.6
 */
class BilingualTemplate
{
    protected $smarty;
    protected $locale;
    protected $translations;
    
    public function __construct(string $locale = 'en')
    {
        $this->locale = $locale;
        $this->smarty = new \Smarty();
        
        // Configure Smarty
        $this->smarty->setTemplateDir(__DIR__ . '/../templates/');
        $this->smarty->setCompileDir(__DIR__ . '/../templates_c/');
        $this->smarty->setCacheDir(__DIR__ . '/../cache/');
        
        // Load translations
        $this->loadTranslations($locale);
        
        // Register custom functions
        $this->registerCustomFunctions();
    }
    
    /**
     * Load translation file for locale
     */
    protected function loadTranslations(string $locale): void
    {
        $translationFile = __DIR__ . "/../i18n/{$locale}.php";
        
        if (file_exists($translationFile)) {
            $this->translations = include $translationFile;
        } else {
            // Fall back to English
            $this->translations = include __DIR__ . "/../i18n/en.php";
        }
        
        $this->smarty->assign('translations', $this->translations);
        $this->smarty->assign('locale', $locale);
        $this->smarty->assign('isRTL', $locale === 'ar');
    }
    
    /**
     * Register custom Smarty functions
     */
    protected function registerCustomFunctions(): void
    {
        // Translation function
        $this->smarty->registerPlugin('function', 't', function($params, $smarty) {
            $key = $params['key'] ?? '';
            $default = $params['default'] ?? $key;
            
            return $this->translate($key, $default);
        });
        
        // Format number function
        $this->smarty->registerPlugin('function', 'formatNumber', function($params, $smarty) {
            $number = $params['value'] ?? 0;
            $decimals = $params['decimals'] ?? 2;
            
            if ($this->locale === 'ar') {
                // Arabic-Indic numerals
                $formatted = number_format($number, $decimals, '.', ',');
                return $this->convertToArabicNumerals($formatted);
            }
            
            return number_format($number, $decimals, '.', ',');
        });
        
        // Format currency function
        $this->smarty->registerPlugin('function', 'formatCurrency', function($params, $smarty) {
            $amount = $params['amount'] ?? 0;
            $currency = $params['currency'] ?? 'EGP';
            $decimals = $params['decimals'] ?? 2;
            
            $formatted = number_format($amount, $decimals, '.', ',');
            
            if ($this->locale === 'ar') {
                $formatted = $this->convertToArabicNumerals($formatted);
                return "{$formatted} {$currency}";
            }
            
            return "{$currency} {$formatted}";
        });
        
        // Format date function
        $this->smarty->registerPlugin('function', 'formatDate', function($params, $smarty) {
            $date = $params['date'] ?? null;
            $format = $params['format'] ?? 'Y-m-d';
            
            if (!$date) {
                return '';
            }
            
            $timestamp = is_numeric($date) ? $date : strtotime($date);
            
            if ($this->locale === 'ar') {
                // Use Arabic date format
                $formatted = date($format, $timestamp);
                return $this->convertToArabicNumerals($formatted);
            }
            
            return date($format, $timestamp);
        });
    }
    
    /**
     * Translate a key
     */
    public function translate(string $key, string $default = ''): string
    {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default ?: $key;
            }
        }
        
        return is_string($value) ? $value : $default;
    }
    
    /**
     * Convert Western numerals to Arabic-Indic numerals
     */
    protected function convertToArabicNumerals(string $text): string
    {
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        
        return str_replace($western, $arabic, $text);
    }
    
    /**
     * Assign variable to template
     */
    public function assign(string $key, $value): void
    {
        $this->smarty->assign($key, $value);
    }
    
    /**
     * Render template
     */
    public function render(string $template): string
    {
        // Set direction based on locale
        $this->smarty->assign('dir', $this->locale === 'ar' ? 'rtl' : 'ltr');
        
        return $this->smarty->fetch($template);
    }
    
    /**
     * Display template
     */
    public function display(string $template): void
    {
        echo $this->render($template);
    }
    
    /**
     * Set locale
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->loadTranslations($locale);
    }
    
    /**
     * Get current locale
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
    
    /**
     * Check if current locale is RTL
     */
    public function isRTL(): bool
    {
        return $this->locale === 'ar';
    }
}
