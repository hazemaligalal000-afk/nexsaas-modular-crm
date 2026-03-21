/**
 * i18n Configuration
 * React-i18next setup for multi-language support
 * Requirements: 32.1, 32.2, 32.3, 32.4, 32.5
 */

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

// Import translation files
import enCommon from './locales/en/common.json';
import arCommon from './locales/ar/common.json';
import enCRM from './locales/en/crm.json';
import arCRM from './locales/ar/crm.json';
import enERP from './locales/en/erp.json';
import arERP from './locales/ar/erp.json';
import enAccounting from './locales/en/accounting.json';
import arAccounting from './locales/ar/accounting.json';

const resources = {
  en: {
    common: enCommon,
    crm: enCRM,
    erp: enERP,
    accounting: enAccounting
  },
  ar: {
    common: arCommon,
    crm: arCRM,
    erp: arERP,
    accounting: arAccounting
  }
};

i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    fallbackLng: 'en', // Fall back to English on missing translation
    defaultNS: 'common',
    ns: ['common', 'crm', 'erp', 'accounting'],
    
    interpolation: {
      escapeValue: false // React already escapes
    },
    
    detection: {
      order: ['localStorage', 'navigator'],
      caches: ['localStorage']
    },
    
    react: {
      useSuspense: false
    }
  });

// Set HTML dir attribute based on language
i18n.on('languageChanged', (lng) => {
  const dir = lng === 'ar' ? 'rtl' : 'ltr';
  document.documentElement.setAttribute('dir', dir);
  document.documentElement.setAttribute('lang', lng);
});

// Set initial dir attribute on load
const initialLang = i18n.language || 'en';
const initialDir = initialLang === 'ar' ? 'rtl' : 'ltr';
document.documentElement.setAttribute('dir', initialDir);
document.documentElement.setAttribute('lang', initialLang);

export default i18n;
