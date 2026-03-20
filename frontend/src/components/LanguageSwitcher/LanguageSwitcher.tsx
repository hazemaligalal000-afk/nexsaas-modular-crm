/**
 * Language Switcher Component
 * Dropdown for changing interface language with persistence
 * Requirements: 13
 */

import React, { useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import './LanguageSwitcher.css';

interface Language {
  code: string;
  name: string;
  flag: string;
}

const LANGUAGES: Language[] = [
  { code: 'en-US', name: 'English', flag: '🇺🇸' },
  { code: 'ar-SA', name: 'العربية', flag: '🇸🇦' }
];

export const LanguageSwitcher: React.FC = () => {
  const { i18n, t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const currentLanguage = LANGUAGES.find(lang => lang.code === i18n.language) || LANGUAGES[0];

  const changeLanguage = async (languageCode: string) => {
    await i18n.changeLanguage(languageCode);
    
    // Update document direction
    const direction = languageCode === 'ar-SA' ? 'rtl' : 'ltr';
    document.documentElement.setAttribute('dir', direction);
    document.documentElement.setAttribute('lang', languageCode);
    
    // Save to user profile if authenticated
    const user = (window as any).user;
    if (user) {
      try {
        await fetch('/api/v1/user/preferences', {
          method: 'PATCH',
          headers: { 
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${user.token}`
          },
          body: JSON.stringify({ language: languageCode })
        });
      } catch (error) {
        console.error('Failed to save language preference:', error);
      }
    }
    
    setIsOpen(false);
  };

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
    
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleEscape);
    };
  }, []);

  return (
    <div className="language-switcher" ref={dropdownRef}>
      <button
        className="language-button"
        onClick={() => setIsOpen(!isOpen)}
        aria-label={t('language.changeLanguage')}
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        <span className="flag" aria-hidden="true">{currentLanguage.flag}</span>
        <span className="language-code">{currentLanguage.code.split('-')[0].toUpperCase()}</span>
        <svg 
          className={`chevron ${isOpen ? 'open' : ''}`}
          width="12" 
          height="12" 
          viewBox="0 0 12 12" 
          fill="none"
          aria-hidden="true"
        >
          <path 
            d="M2 4L6 8L10 4" 
            stroke="currentColor" 
            strokeWidth="2" 
            strokeLinecap="round" 
            strokeLinejoin="round"
          />
        </svg>
      </button>
      
      {isOpen && (
        <div className="language-dropdown" role="menu">
          {LANGUAGES.map((language) => (
            <button
              key={language.code}
              className={`language-option ${language.code === i18n.language ? 'active' : ''}`}
              onClick={() => changeLanguage(language.code)}
              role="menuitem"
              aria-current={language.code === i18n.language ? 'true' : undefined}
            >
              <span className="flag" aria-hidden="true">{language.flag}</span>
              <span className="language-name">{language.name}</span>
              {language.code === i18n.language && (
                <svg 
                  className="check-icon" 
                  width="16" 
                  height="16" 
                  viewBox="0 0 16 16" 
                  fill="none"
                  aria-hidden="true"
                >
                  <path 
                    d="M13 4L6 11L3 8" 
                    stroke="currentColor" 
                    strokeWidth="2" 
                    strokeLinecap="round" 
                    strokeLinejoin="round"
                  />
                </svg>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};
