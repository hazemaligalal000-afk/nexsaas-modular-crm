# Landing Page Images

This directory contains optimized images for the landing page.

## Hero Image Requirements

### hero-dashboard.webp
- **Format**: WebP (primary)
- **Dimensions**: 1200x800px
- **Max Size**: 200KB
- **Purpose**: Hero section dashboard preview
- **Optimization**: Use tools like Squoosh or ImageOptim

### hero-dashboard.png
- **Format**: PNG (fallback)
- **Dimensions**: 1200x800px
- **Max Size**: 300KB
- **Purpose**: Fallback for browsers without WebP support

## Image Optimization Guidelines

1. **WebP Format**: Primary format for modern browsers
2. **PNG Fallback**: Ensure compatibility with older browsers
3. **Responsive Images**: Consider creating multiple sizes for different viewports
4. **Lazy Loading**: Images below the fold should use lazy loading
5. **Preload**: Hero image should be preloaded for faster LCP

## Preload Configuration

Add to `index.html` in the `<head>` section:

```html
<link rel="preload" as="image" href="/images/hero-dashboard.webp" type="image/webp">
```

## Creating WebP Images

Using ImageMagick:
```bash
convert hero-dashboard.png -quality 85 hero-dashboard.webp
```

Using cwebp:
```bash
cwebp -q 85 hero-dashboard.png -o hero-dashboard.webp
```

## Image Checklist

- [ ] Hero dashboard image (WebP + PNG fallback)
- [ ] Optimized to < 200KB
- [ ] Dimensions: 1200x800px
- [ ] Added preload link in index.html
- [ ] Tested on mobile and desktop viewports
