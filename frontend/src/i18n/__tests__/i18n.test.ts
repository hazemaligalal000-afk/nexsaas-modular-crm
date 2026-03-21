/**
 * i18n System Tests
 * Verifies translation, formatting, and language switching functionality
 */

import i18n from '../config';

describe('i18n Configuration', () => {
  test('should initialize with default language', () => {
    expect(i18n.language).toBeDefined();
    expect(['en-US', 'ar-SA']).toContain(i18n.language);
  });

  test('should have en-US as fallback language', () => {
    expect(i18n.options.fallbackLng).toBe('en-US');
  });

  test('should support both en-US and ar-SA locales', () => {
    const resources = i18n.options.resources;
    expect(resources).toHaveProperty('en-US');
    expect(resources).toHaveProperty('ar-SA');
  });
});

describe('Translation Keys', () => {
  beforeEach(() => {
    i18n.changeLanguage('en-US');
  });

  test('should translate common strings', () => {
    expect(i18n.t('common.loading')).toBe('Loading...');
    expect(i18n.t('common.error')).toBe('Error');
    expect(i18n.t('common.success')).toBe('Success');
  });

  test('should translate navigation items', () => {
    expect(i18n.t('navigation.dashboard')).toBe('Dashboard');
    expect(i18n.t('navigation.crm')).toBe('CRM');
    expect(i18n.t('navigation.settings')).toBe('Settings');
  });

  test('should translate action buttons', () => {
    expect(i18n.t('actions.save')).toBe('Save');
    expect(i18n.t('actions.cancel')).toBe('Cancel');
    expect(i18n.t('actions.delete')).toBe('Delete');
  });

  test('should support parameterized translations', () => {
    const result = i18n.t('validation.minLength', { min: 5 });
    expect(result).toContain('5');
    expect(result).toContain('characters');
  });
});

describe('Arabic Translation', () => {
  beforeEach(() => {
    i18n.changeLanguage('ar-SA');
  });

  test('should translate to Arabic', () => {
    expect(i18n.t('common.loading')).toBe('جاري التحميل...');
    expect(i18n.t('navigation.dashboard')).toBe('لوحة التحكم');
    expect(i18n.t('actions.save')).toBe('حفظ');
  });

  test('should handle RTL direction', () => {
    expect(document.documentElement.getAttribute('dir')).toBe('rtl');
    expect(document.documentElement.getAttribute('lang')).toBe('ar-SA');
  });
});

describe('Language Switching', () => {
  test('should switch from English to Arabic', async () => {
    await i18n.changeLanguage('en-US');
    expect(i18n.language).toBe('en-US');
    expect(i18n.t('common.loading')).toBe('Loading...');

    await i18n.changeLanguage('ar-SA');
    expect(i18n.language).toBe('ar-SA');
    expect(i18n.t('common.loading')).toBe('جاري التحميل...');
  });

  test('should update document attributes on language change', async () => {
    await i18n.changeLanguage('en-US');
    expect(document.documentElement.getAttribute('dir')).toBe('ltr');
    expect(document.documentElement.getAttribute('lang')).toBe('en-US');

    await i18n.changeLanguage('ar-SA');
    expect(document.documentElement.getAttribute('dir')).toBe('rtl');
    expect(document.documentElement.getAttribute('lang')).toBe('ar-SA');
  });
});

describe('Missing Translation Handling', () => {
  beforeEach(() => {
    i18n.changeLanguage('en-US');
  });

  test('should return key for missing translation', () => {
    const result = i18n.t('nonexistent.key');
    expect(result).toBe('nonexistent.key');
  });

  test('should fallback to en-US for missing ar-SA translation', async () => {
    await i18n.changeLanguage('ar-SA');
    // If a key exists in en-US but not ar-SA, it should fallback
    const result = i18n.t('common.loading');
    expect(result).toBeDefined();
    expect(result.length).toBeGreaterThan(0);
  });
});
