import React, { createContext, useContext, useState, useEffect } from 'react';

const I18nContext = createContext();

const TRANSLATIONS = {
  en: {
    dashboard: "Dashboard",
    leads: "Leads",
    deals: "Deals",
    settings: "Settings",
    save: "Save Changes",
    muqeem: "Muqeem Sync",
    logout: "Sign Out",
    welcome: "Welcome back, {name}"
  },
  ar: {
    dashboard: "لوحة التحكم",
    leads: "العملاء المحتملون",
    deals: "الصفقات",
    settings: "الإعدادات",
    save: "حفظ التغييرات",
    muqeem: "مزامنة مقيم",
    logout: "تسجيل الخروج",
    welcome: "أهلاً بك مجدداً، {name}"
  }
};

/**
 * i18n Provider: Localization & RTL Engine (Requirement 18)
 */
export function I18nProvider({ children }) {
  const [lang, setLang] = useState(localStorage.getItem('lang') || 'en');

  useEffect(() => {
    localStorage.setItem('lang', lang);
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  }, [lang]);

  const t = (key, params = {}) => {
    let str = TRANSLATIONS[lang][key] || key;
    Object.keys(params).forEach(p => {
      str = str.replace(`{${p}}`, params[p]);
    });
    return str;
  };

  return (
    <I18nContext.Provider value={{ lang, setLang, t, isRtl: lang === 'ar' }}>
      {children}
    </I18nContext.Provider>
  );
}

export const useTranslation = () => useContext(I18nContext);
