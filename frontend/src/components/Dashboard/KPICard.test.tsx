/**
 * KPI Card Component Tests
 * Requirements: 14 - Modern Dashboard with KPIs
 */

import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { KPICard } from './KPICard';
import { KPIData } from '../../types/dashboard';

// Mock the i18n hook
vi.mock('../../i18n/hooks/useNumberFormatter', () => ({
  useNumberFormatter: () => ({
    formatNumber: (value: number) => value.toLocaleString('en-US'),
    formatCurrency: (value: number) => `$${value.toLocaleString('en-US')}`,
    formatPercent: (value: number) => `${value.toFixed(1)}%`,
  }),
}));

describe('KPICard', () => {
  it('renders number format correctly', () => {
    const data: KPIData = {
      label: 'Total Leads',
      value: 1234,
      change: 12.5,
      trend: 'up',
      format: 'number',
    };

    render(<KPICard data={data} />);
    
    expect(screen.getByText('Total Leads')).toBeInTheDocument();
    expect(screen.getByText('1,234')).toBeInTheDocument();
    expect(screen.getByText('12.5%')).toBeInTheDocument();
  });

  it('renders currency format correctly', () => {
    const data: KPIData = {
      label: 'Revenue Pipeline',
      value: 456789,
      change: 8.3,
      trend: 'up',
      format: 'currency',
    };

    render(<KPICard data={data} />);
    
    expect(screen.getByText('Revenue Pipeline')).toBeInTheDocument();
    expect(screen.getByText('$456,789')).toBeInTheDocument();
  });

  it('renders percentage format correctly', () => {
    const data: KPIData = {
      label: 'Conversion Rate',
      value: 23.4,
      change: -2.1,
      trend: 'down',
      format: 'percentage',
    };

    render(<KPICard data={data} />);
    
    expect(screen.getByText('Conversion Rate')).toBeInTheDocument();
    expect(screen.getByText('23.4%')).toBeInTheDocument();
  });

  it('displays up trend with correct icon', () => {
    const data: KPIData = {
      label: 'Test KPI',
      value: 100,
      change: 5.0,
      trend: 'up',
      format: 'number',
    };

    const { container } = render(<KPICard data={data} />);
    const trendIcon = container.querySelector('.kpi-trend-icon');
    
    expect(trendIcon).toHaveTextContent('↑');
    expect(container.querySelector('.kpi-trend-up')).toBeInTheDocument();
  });

  it('displays down trend with correct icon', () => {
    const data: KPIData = {
      label: 'Test KPI',
      value: 100,
      change: -3.0,
      trend: 'down',
      format: 'number',
    };

    const { container } = render(<KPICard data={data} />);
    const trendIcon = container.querySelector('.kpi-trend-icon');
    
    expect(trendIcon).toHaveTextContent('↓');
    expect(container.querySelector('.kpi-trend-down')).toBeInTheDocument();
  });

  it('displays neutral trend with correct icon', () => {
    const data: KPIData = {
      label: 'Test KPI',
      value: 100,
      change: 0,
      trend: 'neutral',
      format: 'number',
    };

    const { container } = render(<KPICard data={data} />);
    const trendIcon = container.querySelector('.kpi-trend-icon');
    
    expect(trendIcon).toHaveTextContent('→');
    expect(container.querySelector('.kpi-trend-neutral')).toBeInTheDocument();
  });
});
