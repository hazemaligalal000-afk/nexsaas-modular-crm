/**
 * useTranslation Hook
 * Wrapper around react-i18next for easy translation access
 * Requirements: 32.1, 32.2, 32.3
 */

import { useTranslation as useI18nTranslation } from 'react-i18next';

export const useTranslation = (namespace = 'common') => {
  const { t, i18n } = useI18nTranslation(namespace);
  
  const changeLanguage = (lng) => {
    i18n.changeLanguage(lng);
  };
  
  const currentLanguage = i18n.language;
  const isRTL = currentLanguage === 'ar';
  
  return {
    t,
    i18n,
    changeLanguage,
    currentLanguage,
    isRTL
  };
};

export default useTranslation;
