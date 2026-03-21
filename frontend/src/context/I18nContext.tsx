import React, { createContext, useContext, useState, ReactNode } from 'react';

type Language = 'en' | 'ar';

interface I18nContextType {
  lang: Language;
  setLang: (lang: Language) => void;
  isRtl: boolean;
  t: (key: string) => string;
}

const translations: Record<Language, Record<string, string>> = {
  en: {
    'accounting': 'Accounting Module',
    'vouchers': 'Vouchers',
    'reports': 'Reports',
    'coa': 'Chart of Accounts',
    'periods': 'Financial Periods',
    'save_draft': 'Save Draft',
    'submit': 'Submit',
    'balanced': 'Perfectly Balanced',
    'unbalanced': 'Out of Balance',
    'debit': 'Debit',
    'credit': 'Credit',
    'account_code': 'Account Code',
    'description': 'Description',
    'add_line': 'Add Ledger Line',
    'total_debit': 'Total Debit',
    'total_credit': 'Total Credit',
    'balance': 'Balance'
  },
  ar: {
    'accounting': 'نظام المحاسبة',
    'vouchers': 'سندات القيد',
    'reports': 'التقارير المالية',
    'coa': 'دليل الحسابات',
    'periods': 'الفترات المالية',
    'save_draft': 'حفظ كمسودة',
    'submit': 'ترحيل القيد',
    'balanced': 'القيد متزن',
    'unbalanced': 'القيد غير متزن',
    'debit': 'مدين',
    'credit': 'دائن',
    'account_code': 'رقم الحساب',
    'description': 'البيان',
    'add_line': 'إضافة سطر جديد',
    'total_debit': 'إجمالي المدين',
    'total_credit': 'إجمالي الدائن',
    'balance': 'الفرق'
  }
};

const I18nContext = createContext<I18nContextType | undefined>(undefined);

export const I18nProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [lang, setLang] = useState<Language>('en');

  const t = (key: string) => translations[lang][key] || key;
  const isRtl = lang === 'ar';

  return (
    <I18nContext.Provider value={{ lang, setLang, isRtl, t }}>
      <div dir={isRtl ? 'rtl' : 'ltr'} className={isRtl ? 'font-arabic' : 'font-sans'}>
        {children}
      </div>
    </I18nContext.Provider>
  );
};

export const useI18n = () => {
  const context = useContext(I18nContext);
  if (!context) throw new Error('useI18n must be used within I18nProvider');
  return context;
};
