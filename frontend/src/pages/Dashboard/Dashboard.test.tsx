/**
 * Dashboard Component Tests
 * Requirements: 14 - Modern Dashboard with KPIs
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { Dashboard } from './Dashboard';

// Mock i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
    i18n: {
      language: 'en-US',
      changeLanguage: vi.fn()
    }
  })
}));

describe('Dashboard Component', () => {
  it('should render loading skeleton initially', () => {
    render(<Dashboard />);
    
    // Check for skeleton elements
    const skeletons = document.querySelectorAll('.skeleton-kpi-card');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('should render dashboard title after loading', async () => {
    render(<Dashboard />);
    
    await waitFor(() => {
      expect(screen.getByText('dashboard.title')).toBeInTheDocument();
    });
  });

  it('should render date range filter', async () => {
    render(<Dashboard />);
    
    await waitFor(() => {
      expect(screen.getByText('dashboard.dateRange.last_7_days')).toBeInTheDocument();
      expect(screen.getByText('dashboard.dateRange.last_30_days')).toBeInTheDocument();
      expect(screen.getByText('dashboard.dateRange.lastQuarter')).toBeInTheDocument();
      expect(screen.getByText('dashboard.dateRange.lastYear')).toBeInTheDocument();
      expect(screen.getByText('dashboard.dateRange.custom')).toBeInTheDocument();
    });
  });

  it('should have responsive grid layout', async () => {
    render(<Dashboard />);
    
    await waitFor(() => {
      const kpiGrid = document.querySelector('.kpi-grid');
      expect(kpiGrid).toBeInTheDocument();
      
      const chartsGrid = document.querySelector('.charts-grid');
      expect(chartsGrid).toBeInTheDocument();
    });
  });
});
