/**
 * Number Formatter Hook
 * Provides locale-aware number, currency, and percentage formatting
 * Requirements: 12
 */

import { useTranslation } from 'react-i18next';

export const useNumberFormatter = () => {
  const { i18n } = useTranslation();
  const locale = i18n.language;

  const formatNumber = (value: number, options?: Intl.NumberFormatOptions) => {
    return new Intl.NumberFormat(locale, options).format(value);
  };

  const formatCurrency = (value: number, currency: string = 'USD') => {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency
    }).format(value);
  };

  const formatPercent = (value: number) => {
    return new Intl.NumberFormat(locale, {
      style: 'percent',
      minimumFractionDigits: 1,
      maximumFractionDigits: 1
    }).format(value / 100);
  };

  const formatCompact = (value: number) => {
    return new Intl.NumberFormat(locale, {
      notation: 'compact',
      compactDisplay: 'short'
    }).format(value);
  };

  return { 
    formatNumber, 
    formatCurrency, 
    formatPercent,
    formatCompact
  };
};
