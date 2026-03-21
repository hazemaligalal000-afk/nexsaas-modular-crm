/**
 * useDirection Hook
 * Provides the current text direction (ltr/rtl) based on active language
 * Requirements: 11
 */

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

export type Direction = 'ltr' | 'rtl';

/**
 * Hook to get and manage text direction based on current language
 * @returns Current direction ('ltr' or 'rtl')
 */
export function useDirection(): Direction {
  const { i18n } = useTranslation();
  const [direction, setDirection] = useState<Direction>(() => {
    return i18n.language === 'ar' ? 'rtl' : 'ltr';
  });

  useEffect(() => {
    const handleLanguageChange = (lng: string) => {
      const newDirection: Direction = lng === 'ar' ? 'rtl' : 'ltr';
      setDirection(newDirection);
    };

    // Set initial direction
    handleLanguageChange(i18n.language);

    // Listen for language changes
    i18n.on('languageChanged', handleLanguageChange);

    return () => {
      i18n.off('languageChanged', handleLanguageChange);
    };
  }, [i18n]);

  return direction;
}

/**
 * Hook to check if current direction is RTL
 * @returns true if direction is RTL, false otherwise
 */
export function useIsRTL(): boolean {
  const direction = useDirection();
  return direction === 'rtl';
}
