# Internationalization (i18n) System

This directory contains the internationalization infrastructure for the NexSaaS platform.

## Overview

The i18n system uses `react-i18next` to provide multi-language support with:
- Automatic language detection from browser/localStorage
- RTL (Right-to-Left) layout support for Arabic
- Locale-aware number and date formatting
- Translation coverage tracking

## Structure

```
i18n/
├── config.js              # react-i18next configuration
├── locales/
│   ├── en-US.json        # English (United States) translations
│   └── ar-SA.json        # Arabic (Saudi Arabia) translations
├── hooks/
│   ├── useNumberFormatter.ts  # Number/currency/percent formatting
│   ├── useDateFormatter.ts    # Date/time formatting
│   └── index.ts              # Hook exports
└── README.md             # This file
```

## Supported Locales

- **en-US**: English (United States) - Default/fallback language
- **ar-SA**: Arabic (Saudi Arabia) - Full RTL support

## Usage

### Basic Translation

```tsx
import { useTranslation } from 'react-i18next';

function MyComponent() {
  const { t } = useTranslation();
  
  return (
    <div>
      <h1>{t('app.name')}</h1>
      <p>{t('common.loading')}</p>
    </div>
  );
}
```

### Parameterized Translation

```tsx
const { t } = useTranslation();

// Translation key: "validation.minLength": "Minimum length is {{min}} characters"
<p>{t('validation.minLength', { min: 5 })}</p>
```

### Number Formatting

```tsx
import { useNumberFormatter } from '@/i18n/hooks';

function PriceDisplay() {
  const { formatNumber, formatCurrency, formatPercent } = useNumberFormatter();
  
  return (
    <div>
      <p>Count: {formatNumber(1234567)}</p>
      <p>Price: {formatCurrency(99.99, 'USD')}</p>
      <p>Rate: {formatPercent(75.5)}</p>
    </div>
  );
}
```

### Date Formatting

```tsx
import { useDateFormatter } from '@/i18n/hooks';

function DateDisplay() {
  const { formatShortDate, formatDateTime, formatRelativeTime } = useDateFormatter();
  const date = new Date();
  
  return (
    <div>
      <p>Date: {formatShortDate(date)}</p>
      <p>DateTime: {formatDateTime(date)}</p>
      <p>Relative: {formatRelativeTime(date)}</p>
    </div>
  );
}
```

### Language Switcher

```tsx
import { LanguageSwitcher } from '@/components/LanguageSwitcher';

function Header() {
  return (
    <header>
      <nav>...</nav>
      <LanguageSwitcher />
    </header>
  );
}
```

## Adding New Translations

1. Add the translation key to `locales/en-US.json`:
```json
{
  "myModule": {
    "myKey": "My English text"
  }
}
```

2. Add the corresponding Arabic translation to `locales/ar-SA.json`:
```json
{
  "myModule": {
    "myKey": "النص العربي"
  }
}
```

3. Run the coverage check:
```bash
npm run translation:check
```

## Translation Coverage

Check translation completeness:

```bash
npm run translation:check
```

This script:
- Compares en-US (base) with ar-SA (target)
- Reports missing translations
- Reports extra keys not in base
- Calculates coverage percentage
- Exits with code 0 if 100% coverage, 1 otherwise

## RTL Support

The system automatically applies RTL layout when Arabic is selected:

- Sets `dir="rtl"` on `<html>` element
- Sets `lang="ar-SA"` attribute
- CSS should use logical properties for proper RTL support:
  - `margin-inline-start` instead of `margin-left`
  - `padding-inline-end` instead of `padding-right`
  - `text-align: start` instead of `text-align: left`

## Language Persistence

Language preference is saved to:
1. **localStorage**: For immediate persistence across sessions
2. **User profile**: For authenticated users (synced to backend)

## Missing Translation Handling

When a translation key is missing:
1. A warning is logged to console (dev mode only)
2. The system falls back to the en-US value
3. The key is displayed as-is if not found in fallback

## Best Practices

1. **Use nested keys** for organization:
   ```json
   {
     "leads": {
       "title": "Leads",
       "filters": {
         "all": "All",
         "hot": "Hot"
       }
     }
   }
   ```

2. **Keep keys descriptive**:
   - ✅ `leads.filters.hot`
   - ❌ `l.f.h`

3. **Use parameters for dynamic content**:
   ```json
   {
     "welcome": "Welcome, {{name}}!"
   }
   ```

4. **Don't translate user-generated content**:
   - Lead names, contact names, notes, etc. should NOT be translated

5. **Test both languages**:
   - Always verify translations in both en-US and ar-SA
   - Check RTL layout for Arabic

## Requirements Mapping

This implementation satisfies the following requirements:
- **Requirement 9**: i18n library integration with react-i18next
- **Requirement 10**: Complete UI string translation
- **Requirement 12**: Arabic number and date formatting
- **Requirement 13**: Language switcher with persistence
- **Requirement 26**: Translation file parser and coverage tracking
