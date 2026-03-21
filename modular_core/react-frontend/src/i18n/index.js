import React, { createContext, useContext, useState, useEffect } from 'react';
import enLocale from './locales/en-US.json';
import arLocale from './locales/ar-SA.json';

const I18nContext = createContext();

const TRANSLATIONS = {
  en: enLocale,
  ar: arLocale
};

/**
 * i18n Provider: Localization & RTL Engine (Requirement 18 / Phase 3)
 */
export function I18nProvider({ children }) {
  const [lang, setLang] = useState(localStorage.getItem('lang') || 'en');

  useEffect(() => {
    localStorage.setItem('lang', lang);
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    // Update body font for better Arabic typography
    if (lang === 'ar') {
        document.body.style.fontFamily = "'Cairo', 'Inter', sans-serif";
    } else {
        document.body.style.fontFamily = "'Inter', sans-serif";
    }
  }, [lang]);

  const t = (key, params = {}) => {
    // Nested key support (e.g. 'auth.login')
    const keys = key.split('.');
    let str = TRANSLATIONS[lang];
    
    for (const k of keys) {
        if (str && str[k]) {
            str = str[k];
        } else {
            str = key;
            break;
        }
    }

    if (typeof str === 'string') {
        Object.keys(params).forEach(p => {
            str = str.replace(`{${p}}`, params[p]);
        });
    }

    return str;
  };

  return (
    <I18nContext.Provider value={{ lang, setLang, t, isRtl: lang === 'ar' }}>
      <div dir={lang === 'ar' ? 'rtl' : 'ltr'}>
        {children}
      </div>
    </I18nContext.Provider>
  );
}

export const useTranslation = () => useContext(I18nContext);
