# Task 7.1: DealsByStageChart Implementation Guide

## Quick Start

### View the Chart
The chart is integrated into the main Dashboard at `/dashboard`. It displays in the charts grid section below the KPI cards.

### Run Demo Page
To see the standalone demo:
1. Add route to your router configuration
2. Navigate to `/dashboard/deals-chart-demo`
3. Test interactions and RTL mode

### Run Tests
```bash
cd frontend
npm test -- DealsByStageChart.test.tsx
```

## Component API

### Props
```typescript
interface DealsByStageChartProps {
  data: DealsByStageData[];           // Required: Array of stage data
  onStageClick?: (stage: string) => void;  // Optional: Click handler
}
```

### Data Structure
```typescript
interface DealsByStageData {
  stage: string;   // Stage name (e.g., "Qualified")
  count: number;   // Number of deals
  value: number;   // Total value in USD
}
```

## Features

### Core Functionality
- ✅ Bar chart visualization with Recharts
- ✅ Custom tooltip with formatted numbers
- ✅ Click handler for navigation
- ✅ Smooth animations (800ms)
- ✅ Responsive design

### Internationalization
- ✅ All text uses i18n translation keys
- ✅ Number formatting with locale support
- ✅ Currency formatting
- ✅ English and Arabic translations

### RTL Support
- ✅ Automatic RTL detection via i18n
- ✅ Reversed X-axis in RTL mode
- ✅ Right-aligned Y-axis in RTL mode
- ✅ Reversed data order in RTL

### Accessibility
- ✅ Semantic HTML structure
- ✅ ARIA labels on chart elements
- ✅ Keyboard navigation (via Recharts)
- ✅ Focus states
- ✅ Screen reader support

### Theming
- ✅ Dark mode support
- ✅ CSS variables for colors
- ✅ Responsive breakpoints
- ✅ Print styles
- ✅ Reduced motion support
- ✅ High contrast mode

## Usage Examples

### Basic Usage
```typescript
import { DealsByStageChart } from './components/Dashboard/DealsByStageChart';

const data = [
  { stage: 'Qualified', count: 45, value: 562500 },
  { stage: 'Proposal', count: 32, value: 400000 },
  { stage: 'Negotiation', count: 18, value: 225000 },
  { stage: 'Closed Won', count: 12, value: 150000 }
];

<DealsByStageChart data={data} />
```

### With Click Handler
```typescript
const handleStageClick = (stage: string) => {
  navigate(`/deals?stage=${encodeURIComponent(stage)}`);
};

<DealsByStageChart 
  data={data} 
  onStageClick={handleStageClick}
/>
```

### With API Data
```typescript
const { data, isLoading } = useQuery({
  queryKey: ['dealsByStage'],
  queryFn: fetchDealsByStage
});

if (isLoading) return <Skeleton />;

<DealsByStageChart data={data} onStageClick={handleStageClick} />
```

## Customization

### Colors
The chart uses CSS variables for theming:
```css
--primary-color: #3b82f6;        /* Bar color */
--text-color: #111827;           /* Text color */
--text-secondary: #6b7280;       /* Secondary text */
--border-color: #e5e7eb;         /* Grid lines */
--surface-color: #ffffff;        /* Background */
```

### Dark Mode
Dark mode is automatically applied when `[data-theme="dark"]` is set:
```css
[data-theme="dark"] .deals-by-stage-chart {
  background-color: var(--surface-dark, #2d2d2d);
}
```

### Responsive Breakpoints
- Mobile: < 768px (1rem padding, smaller fonts)
- Tablet: 768px - 1023px (1.25rem padding)
- Desktop: >= 1024px (1.5rem padding)

## Translation Keys

### English
```json
{
  "dashboard.charts.dealsByStage": "Deals by Stage",
  "dashboard.charts.stage": "Stage",
  "dashboard.charts.deals": "Deals",
  "dashboard.charts.totalValue": "Total Value"
}
```

### Arabic
```json
{
  "dashboard.charts.dealsByStage": "الصفقات حسب المرحلة",
  "dashboard.charts.stage": "المرحلة",
  "dashboard.charts.deals": "الصفقات",
  "dashboard.charts.totalValue": "القيمة الإجمالية"
}
```

## Testing

### Unit Tests
```bash
npm test -- DealsByStageChart.test.tsx
```

Tests cover:
- Chart title rendering
- Empty data handling
- Chart components rendering
- Click handler integration

### Manual Testing Checklist
- [ ] Chart renders with sample data
- [ ] Tooltip appears on hover with correct values
- [ ] Click on bar triggers handler
- [ ] RTL mode reverses layout correctly
- [ ] Dark mode applies correct colors
- [ ] Responsive on mobile/tablet/desktop
- [ ] Animations are smooth
- [ ] Print layout works

### Browser Testing
Test in:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Troubleshooting

### Chart Not Rendering
- Check that Recharts is installed: `npm list recharts`
- Verify data prop is an array
- Check console for errors

### Tooltip Not Showing
- Ensure data has `stage`, `count`, and `value` properties
- Check that `useNumberFormatter` hook is available
- Verify i18n is initialized

### RTL Not Working
- Check that i18n is configured with RTL support
- Verify `i18n.dir()` returns 'rtl' for Arabic
- Check that CSS logical properties are supported

### Click Handler Not Firing
- Verify `onStageClick` prop is passed
- Check console for click logs
- Ensure bars are clickable (cursor: pointer)

## Performance

### Optimization Tips
- Use React.memo for the component if data changes frequently
- Debounce click handlers if needed
- Lazy load the chart if it's below the fold
- Use skeleton loading state

### Bundle Size
- Recharts: ~100KB gzipped
- Component: ~5KB gzipped
- Total impact: ~105KB

## Future Enhancements

Potential improvements:
- [ ] Add drill-down to deal details
- [ ] Implement actual navigation with filters
- [ ] Add export as image/PDF
- [ ] Add comparison with previous period
- [ ] Add animation on data updates
- [ ] Add loading skeleton
- [ ] Add error boundary
- [ ] Add data refresh button

## Support

For issues or questions:
1. Check this guide first
2. Review the component source code
3. Check Recharts documentation: https://recharts.org
4. Review test files for usage examples

## Related Files

### Component Files
- `frontend/src/components/Dashboard/DealsByStageChart.tsx`
- `frontend/src/components/Dashboard/DealsByStageChart.css`
- `frontend/src/components/Dashboard/DealsByStageChart.test.tsx`

### Type Definitions
- `frontend/src/types/dashboard.ts`

### Integration
- `frontend/src/pages/Dashboard/Dashboard.tsx`

### Translations
- `frontend/src/i18n/locales/en-US.json`
- `frontend/src/i18n/locales/ar-SA.json`

### Demo
- `frontend/src/pages/Dashboard/DealsByStageChartDemo.tsx`
- `frontend/src/pages/Dashboard/DealsByStageChartDemo.css`
