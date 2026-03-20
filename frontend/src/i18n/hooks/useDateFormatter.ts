/**
 * Date Formatter Hook
 * Provides locale-aware date and time formatting
 * Requirements: 12
 */

import { useTranslation } from 'react-i18next';

export const useDateFormatter = () => {
  const { i18n, t } = useTranslation();
  const locale = i18n.language;

  const formatDate = (date: Date | string, options?: Intl.DateTimeFormatOptions) => {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    return new Intl.DateTimeFormat(locale, options).format(dateObj);
  };

  const formatShortDate = (date: Date | string) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const formatLongDate = (date: Date | string) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  const formatDateTime = (date: Date | string) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatRelativeTime = (date: Date | string) => {
    const dateObj = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - dateObj.getTime()) / 1000);

    if (diffInSeconds < 60) return t('time.just_now');
    if (diffInSeconds < 3600) {
      const minutes = Math.floor(diffInSeconds / 60);
      return t('time.minutes_ago', { count: minutes });
    }
    if (diffInSeconds < 86400) {
      const hours = Math.floor(diffInSeconds / 3600);
      return t('time.hours_ago', { count: hours });
    }
    if (diffInSeconds < 604800) {
      const days = Math.floor(diffInSeconds / 86400);
      return t('time.days_ago', { count: days });
    }
    
    return formatShortDate(dateObj);
  };

  const formatTime = (date: Date | string) => {
    return formatDate(date, {
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  return { 
    formatDate, 
    formatShortDate, 
    formatLongDate, 
    formatDateTime, 
    formatRelativeTime,
    formatTime
  };
};
