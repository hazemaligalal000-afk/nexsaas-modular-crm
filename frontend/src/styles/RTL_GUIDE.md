# RTL Layout Support Guide

## Overview

This guide explains how RTL (Right-to-Left) layout support is implemented in the NexSaaS platform for Arabic language support.

## Requirements

- **Requirement 11**: RTL Layout Support
  - Apply `dir="rtl"` to `<html>` element when Arabic is active
  - Use CSS logical properties throughout the codebase
  - Mirror layout for RTL (navigation, sidebars, forms, buttons)
  - Don't mirror icons, logos, or images (unless directional)
  - Flip chart axes and legends for RTL

## Architecture

### 1. CSS Logical Properties

Instead of directional properties (left/right), we use logical properties that adapt to text direction:

```css
/* ❌ Don't use directional properties */
margin-left: 16px;
padding-right: 24px;
text-align: left;
border-left: 1px solid;

/* ✅ Use logical properties */
margin-inline-start: 16px;
padding-inline-end: 24px;
text-align: start;
border-inline-start: 1px solid;
```

### 2. Logical Property Mapping

| Directional Property | Logical Property |
|---------------------|------------------|
| `margin-left` | `margin-inline-start` |
| `margin-right` | `margin-inline-end` |
| `padding-left` | `padding-inline-start` |
| `padding-right` | `padding-inline-end` |
| `border-left` | `border-inline-start` |
| `border-right` | `border-inline-end` |
| `text-align: left` | `text-align: start` |
| `text-align: right` | `text-align: end` |
| `left` | `inset-inline-start` |
| `right` | `inset-inline-end` |

### 3. Direction Detection

The `dir` attribute is automatically set on the `<html>` element based on the active language:

```typescript
// In frontend/src/i18n/config.ts
i18n.on('languageChanged', (lng) => {
  const dir = lng === 'ar' ? 'rtl' : 'ltr';
  document.documentElement.setAttribute('dir', dir);
  document.documentElement.setAttribute('lang', lng);
});
```

### 4. RTL-Specific Overrides

Some components need special handling in RTL mode. These are defined in `frontend/src/styles/rtl.css`:

```css
/* Reverse gradient directions */
[dir="rtl"] .nav-item.active {
  background: linear-gradient(to left, rgba(99, 102, 241, 0.1), transparent);
}

/* Flip chevron icons */
[dir="rtl"] .chevron {
  transform: scaleX(-1);
}

/* Don't flip logos and avatars */
[dir="rtl"] .logo,
[dir="rtl"] .avatar {
  transform: none;
}
```

## Usage

### Using Logical Properties in CSS

When writing new CSS, always use logical properties:

```css
.my-component {
  /* Spacing */
  margin-inline-start: 1rem;
  padding-inline-end: 0.5rem;
  
  /* Borders */
  border-inline-start: 1px solid var(--border);
  
  /* Text alignment */
  text-align: start;
  
  /* Positioning */
  inset-inline-start: 0;
}
```

### Using Direction Hooks in React

Use the `useDirection` or `useIsRTL` hooks to conditionally render based on direction:

```typescript
import { useDirection, useIsRTL } from '@/i18n/hooks';

function MyComponent() {
  const direction = useDirection(); // 'ltr' or 'rtl'
  const isRTL = useIsRTL(); // boolean
  
  return (
    <div>
      <p>Current direction: {direction}</p>
      {isRTL && <p>RTL mode is active</p>}
    </div>
  );
}
```

### Utility Classes

Use the provided utility classes for common patterns:

```tsx
<div className="m-inline-start-4 p-inline-end-2">
  <p className="text-start">This text aligns to the start</p>
  <button className="btn-with-icon">
    <Icon className="btn-icon-start" />
    Button Text
  </button>
</div>
```

## Components That Need Special Handling

### 1. Icons

- **Directional icons** (arrows, chevrons): Should flip in RTL
- **Non-directional icons** (logos, avatars, social media): Should NOT flip

```css
/* Flip directional icons */
[dir="rtl"] .chevron,
[dir="rtl"] .arrow-icon {
  transform: scaleX(-1);
}

/* Don't flip non-directional icons */
[dir="rtl"] .logo,
[dir="rtl"] .avatar {
  transform: none;
}
```

### 2. Charts

Charts need special handling for RTL:
- Y-axis should be on the right
- X-axis should progress right-to-left
- Legends should be RTL

```css
[dir="rtl"] .recharts-cartesian-axis-tick-value {
  text-anchor: start;
}

[dir="rtl"] .recharts-legend-wrapper {
  direction: rtl;
}
```

### 3. Forms

Form inputs that should remain LTR:
- Email addresses
- URLs
- Phone numbers
- Numbers

```css
[dir="rtl"] input[type="email"],
[dir="rtl"] input[type="url"],
[dir="rtl"] input[type="tel"],
[dir="rtl"] input[type="number"] {
  direction: ltr;
  text-align: end;
}
```

### 4. Code Blocks

Code should always be LTR:

```css
[dir="rtl"] code,
[dir="rtl"] pre {
  direction: ltr;
  text-align: start;
}
```

## Testing RTL Layout

### Manual Testing

1. Switch language to Arabic using the LanguageSwitcher
2. Verify the `dir="rtl"` attribute is set on `<html>`
3. Check that:
   - Navigation menus are right-aligned
   - Sidebars are on the right
   - Form labels are on the right
   - Button groups are reversed
   - Text aligns to the right
   - Icons flip appropriately

### Browser Testing

Test RTL layout on:
- ✅ Chrome Desktop
- ✅ Firefox Desktop
- ✅ Safari Desktop
- ✅ Safari iOS
- ✅ Chrome Android

### Common Issues

**Issue**: Component doesn't flip in RTL
**Solution**: Check if you're using directional properties instead of logical properties

**Issue**: Icon flips when it shouldn't
**Solution**: Add the component to the "don't flip" list in `rtl.css`

**Issue**: Text alignment is wrong
**Solution**: Use `text-align: start` instead of `text-align: left`

## Migration Guide

### Converting Existing CSS

1. Find all directional properties:
   ```bash
   grep -r "margin-left\|margin-right\|padding-left\|padding-right" frontend/src
   ```

2. Replace with logical properties:
   ```css
   /* Before */
   .component {
     margin-left: 1rem;
     padding-right: 0.5rem;
     text-align: left;
   }
   
   /* After */
   .component {
     margin-inline-start: 1rem;
     padding-inline-end: 0.5rem;
     text-align: start;
   }
   ```

3. Test in both LTR and RTL modes

### Converting Tailwind Classes

Tailwind CSS v3.3+ supports logical properties:

```tsx
{/* Before */}
<div className="ml-4 pr-2 text-left">

{/* After */}
<div className="ms-4 pe-2 text-start">
```

## Resources

- [MDN: CSS Logical Properties](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Logical_Properties)
- [W3C: CSS Logical Properties Spec](https://www.w3.org/TR/css-logical-1/)
- [RTL Styling 101](https://rtlstyling.com/)
- [Material Design: Bidirectionality](https://material.io/design/usability/bidirectionality.html)

## Files

- `frontend/src/styles/logical-properties.css` - Base logical property utilities
- `frontend/src/styles/rtl.css` - RTL-specific overrides
- `frontend/src/i18n/config.ts` - Direction detection and setting
- `frontend/src/i18n/hooks/useDirection.ts` - Direction hooks for React components
