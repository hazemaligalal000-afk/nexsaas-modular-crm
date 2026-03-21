/**
 * KPI Grid Component Tests
 * Requirements: 14 - Modern Dashboard with KPIs
 */

import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { KPIGrid } from './KPIGrid';
import { KPIData } from '../../types/dashboard';

// Mock the i18n hook
vi.mock('../../i18n/hooks/useNumberFormatter', () => ({
  useNumberFormatter: () => ({
    formatNumber: (value: number) => value.toLocaleString('en-US'),
    formatCurrency: (value: number) => `$${value.toLocaleString('en-US')}`,
    formatPercent: (value: number) => `${value.toFixed(1)}%`,
  }),
}));

describe('KPIGrid', () => {
  const mockKPIs: KPIData[] = [
    {
      label: 'Total Leads',
      value: 1234,
      change: 12.5,
      trend: 'up',
      format: 'number',
    },
    {
      label: 'Conversion Rate',
      value: 23.4,
      change: -2.1,
      trend: 'down',
      format: 'percentage',
    },
    {
      label: 'Revenue Pipeline',
      value: 456789,
      change: 8.3,
      trend: 'up',
      format: 'currency',
    },
    {
      label: 'Average Deal Size',
      value: 12500,
      change: 5.7,
      trend: 'up',
      format: 'currency',
    },
  ];

  it('renders all KPI cards', () => {
    render(<KPIGrid kpis={mockKPIs} />);
    
    expect(screen.getByText('Total Leads')).toBeInTheDocument();
    expect(screen.getByText('Conversion Rate')).toBeInTheDocument();
    expect(screen.getByText('Revenue Pipeline')).toBeInTheDocument();
    expect(screen.getByText('Average Deal Size')).toBeInTheDocument();
  });

  it('renders correct number of cards', () => {
    const { container } = render(<KPIGrid kpis={mockKPIs} />);
    const cards = container.querySelectorAll('.kpi-card');
    
    expect(cards).toHaveLength(4);
  });

  it('has proper ARIA label', () => {
    render(<KPIGrid kpis={mockKPIs} />);
    
    const grid = screen.getByRole('region', { name: 'Key Performance Indicators' });
    expect(grid).toBeInTheDocument();
  });

  it('renders empty grid when no KPIs provided', () => {
    const { container } = render(<KPIGrid kpis={[]} />);
    const cards = container.querySelectorAll('.kpi-card');
    
    expect(cards).toHaveLength(0);
  });
});
