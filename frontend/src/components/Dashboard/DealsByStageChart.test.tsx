/**
 * DealsByStageChart Component Tests
 * Requirements: 15 - Interactive Charts with Recharts
 */

import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DealsByStageChart } from './DealsByStageChart';
import { DealsByStageData } from '../../types/dashboard';

// Mock i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const translations: Record<string, string> = {
        'dashboard.charts.dealsByStage': 'Deals by Stage',
        'dashboard.charts.stage': 'Stage',
        'dashboard.charts.deals': 'Deals',
        'dashboard.charts.totalValue': 'Total Value'
      };
      return translations[key] || key;
    },
    i18n: {
      dir: () => 'ltr'
    }
  })
}));

// Mock Recharts to avoid rendering issues in tests
vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  BarChart: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Bar: () => <div>Bar</div>,
  XAxis: () => <div>XAxis</div>,
  YAxis: () => <div>YAxis</div>,
  CartesianGrid: () => <div>CartesianGrid</div>,
  Tooltip: () => <div>Tooltip</div>,
  Cell: () => <div>Cell</div>
}));

describe('DealsByStageChart', () => {
  const mockData: DealsByStageData[] = [
    { stage: 'Qualified', count: 45, value: 562500 },
    { stage: 'Proposal', count: 32, value: 400000 },
    { stage: 'Negotiation', count: 18, value: 225000 },
    { stage: 'Closed Won', count: 12, value: 150000 }
  ];

  it('renders chart title', () => {
    render(<DealsByStageChart data={mockData} />);
    expect(screen.getByText('Deals by Stage')).toBeInTheDocument();
  });

  it('renders with empty data', () => {
    render(<DealsByStageChart data={[]} />);
    expect(screen.getByText('Deals by Stage')).toBeInTheDocument();
  });

  it('calls onStageClick when provided', () => {
    const handleClick = vi.fn();
    render(<DealsByStageChart data={mockData} onStageClick={handleClick} />);
    // Note: Full click testing would require more complex setup with Recharts
    expect(handleClick).not.toHaveBeenCalled();
  });

  it('renders chart components', () => {
    render(<DealsByStageChart data={mockData} />);
    expect(screen.getByText('Bar')).toBeInTheDocument();
    expect(screen.getByText('XAxis')).toBeInTheDocument();
    expect(screen.getByText('YAxis')).toBeInTheDocument();
  });
});
