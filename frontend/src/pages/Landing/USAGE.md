# Landing Page Usage Guide

## Quick Start

### 1. Import the Landing Page

```tsx
import { LandingPage } from './pages/Landing';
```

### 2. Use in Your App

```tsx
import React from 'react';
import { LandingPage } from './pages/Landing';
import './i18n/config'; // Ensure i18n is initialized

function App() {
  return <LandingPage />;
}

export default App;
```

### 3. With React Router

```tsx
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { LandingPage } from './pages/Landing';
import { Dashboard } from './pages/Dashboard';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/dashboard" element={<Dashboard />} />
      </Routes>
    </BrowserRouter>
  );
}
```

## Components

### LandingPage
Main container component that includes all landing page sections.

```tsx
<LandingPage />
```

### HeroSection
Can be used standalone if needed:

```tsx
import { HeroSection } from './pages/Landing';

<HeroSection />
```

### VideoModal
Reusable modal for video embeds:

```tsx
import { VideoModal } from './components/VideoModal/VideoModal';

const [showVideo, setShowVideo] = useState(false);

<button onClick={() => setShowVideo(true)}>Watch Video</button>

{showVideo && (
  <VideoModal 
    videoId="your-vimeo-video-id"
    onClose={() => setShowVideo(false)}
  />
)}
```

## Configuration

### Update Video ID

Edit `HeroSection.tsx` to change the demo video:

```tsx
<VideoModal 
  videoId="your-vimeo-video-id"  // Change this
  onClose={() => setShowVideoModal(false)}
/>
```

### Update CTA Links

Edit `HeroSection.tsx` to change button destinations:

```tsx
<a 
  href="/signup"  // Change this URL
  className="btn btn-primary btn-large"
>
  {t('landing.hero.ctaTrial')}
</a>
```

### Customize Translations

Edit translation files:
- `frontend/src/i18n/locales/en-US.json`
- `frontend/src/i18n/locales/ar-SA.json`

```json
{
  "landing": {
    "hero": {
      "headline": "Your Custom Headline",
      "subheadline": "Your custom subheadline",
      "ctaTrial": "Your CTA Text",
      "ctaDemo": "Your Demo Text",
      "imageAlt": "Your image description"
    }
  }
}
```

## Styling

### Override Styles

Create a custom CSS file and import it after the component styles:

```tsx
import './pages/Landing/LandingPage.css';
import './custom-landing.css'; // Your overrides
```

### CSS Variables

The hero section uses these CSS variables (can be customized):

```css
:root {
  --color-background: #ffffff;
  --hero-gradient-start: #667eea;
  --hero-gradient-end: #764ba2;
}
```

### Responsive Breakpoints

- Mobile: < 768px
- Tablet: 768px - 1023px
- Desktop: ≥ 1024px

## Hero Image Setup

### 1. Create Optimized Images

```bash
# Convert to WebP
cwebp -q 85 hero-dashboard.png -o hero-dashboard.webp

# Or use ImageMagick
convert hero-dashboard.png -quality 85 hero-dashboard.webp
```

### 2. Place Images

Put images in `frontend/public/images/`:
- `hero-dashboard.webp` (primary)
- `hero-dashboard.png` (fallback)

### 3. Update Preload (Optional)

Edit `frontend/index.html`:

```html
<link rel="preload" as="image" href="/images/hero-dashboard.webp" type="image/webp">
```

## RTL Support

The landing page automatically supports RTL when the language is set to Arabic:

```tsx
import { useTranslation } from 'react-i18next';

const { i18n } = useTranslation();

// Switch to Arabic
i18n.changeLanguage('ar-SA');

// Switch to English
i18n.changeLanguage('en-US');
```

## Testing

### Manual Testing

1. Start dev server: `npm run dev`
2. Navigate to landing page
3. Test CTA buttons
4. Test video modal (Escape key, outside click)
5. Test responsive layout (resize browser)
6. Test RTL layout (switch to Arabic)

### Browser Testing

Test on:
- Chrome Desktop
- Firefox Desktop
- Safari Desktop
- Safari iOS
- Chrome Android

## Performance Tips

1. **Optimize Hero Image**: Keep under 200KB
2. **Use WebP Format**: 30-50% smaller than PNG
3. **Preload Critical Assets**: Hero image, fonts
4. **Lazy Load Below Fold**: Future sections
5. **Minimize CSS**: Remove unused styles

## Accessibility

The landing page includes:
- Semantic HTML
- ARIA labels
- Keyboard navigation
- Focus indicators
- Alt text for images
- High color contrast

## Common Issues

### Video Modal Not Opening
- Check that `videoId` prop is provided
- Verify Vimeo video is public or has correct privacy settings

### Hero Image Not Loading
- Check image path is correct
- Verify images exist in `public/images/`
- Check browser console for 404 errors

### Translations Not Working
- Ensure i18n is initialized before rendering
- Check translation keys match exactly
- Verify locale files are imported correctly

### RTL Layout Issues
- Ensure CSS uses logical properties
- Check `dir` attribute is set on `<html>`
- Verify i18n language change updates direction

## Next Steps

After implementing Task 3.1, continue with:
- Task 3.2: Features Showcase Section
- Task 3.3: Pricing Comparison Table
- Task 3.4: Social Proof Section
- Task 3.5: FAQ Section
- Task 3.6: Contact Form and Chat Widget

## Support

For issues or questions:
1. Check the implementation documentation
2. Review the design document
3. Test in different browsers
4. Verify translations are complete
