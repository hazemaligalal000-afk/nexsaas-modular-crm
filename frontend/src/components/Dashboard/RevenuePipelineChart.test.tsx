/**
 * Revenue Pipeline Chart Component Tests
 * Requirements: 15 - Interactive Charts with Recharts
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { RevenuePipelineChart, RevenuePipelineData } from './RevenuePipelineChart';

// Mock i18next
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const translations: Record<string, string> = {
        'dashboard.charts.revenuePipeline': 'Revenue Pipeline Trend',
        'dashboard.charts.revenue': 'Revenue'
      };
      return translations[key] || key;
    },
    i18n: {
      dir: () => 'ltr',
      language: 'en-US'
    }
  })
}));

// Mock date formatter hook
vi.mock('../../i18n/hooks/useDateFormatter', () => ({
  useDateFormatter: () => ({
    formatDate: (date: Date) => date.toLocaleDateString('en-US'),
    formatShortDate: (date: Date) => date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  })
}));

// Mock number formatter hook
vi.mock('../../i18n/hooks/useNumberFormatter', () => ({
  useNumberFormatter: () => ({
    formatCurrency: (value: number, options?: { maximumFractionDigits?: number }) => {
      const formatted = value.toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: options?.maximumFractionDigits ?? 2
      });
      return formatted;
    }
  })
}));

// Mock Recharts to avoid rendering issues in tests
vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div data-testid="responsive-container">{children}</div>,
  AreaChart: ({ children }: { children: React.ReactNode }) => <div data-testid="area-chart">{children}</div>,
  Area: () => <div data-testid="area" />,
  XAxis: () => <div data-testid="x-axis" />,
  YAxis: () => <div data-testid="y-axis" />,
  CartesianGrid: () => <div data-testid="cartesian-grid" />,
  Tooltip: () => <div data-testid="tooltip" />
}));

describe('RevenuePipelineChart', () => {
  const mockData: RevenuePipelineData[] = [
    { date: '2024-01-01T00:00:00Z', value: 400000 },
    { date: '2024-01-02T00:00:00Z', value: 420000 },
    { date: '2024-01-03T00:00:00Z', value: 450000 },
    { date: '2024-01-04T00:00:00Z', value: 430000 },
    { date: '2024-01-05T00:00:00Z', value: 460000 }
  ];

  it('renders chart title', () => {
    render(<RevenuePipelineChart data={mockData} />);
    expect(screen.getByText('Revenue Pipeline Trend')).toBeInTheDocument();
  });

  it('renders chart components', () => {
    render(<RevenuePipelineChart data={mockData} />);
    
    expect(screen.getByTestId('responsive-container')).toBeInTheDocument();
    expect(screen.getByTestId('area-chart')).toBeInTheDocument();
    expect(screen.getByTestId('area')).toBeInTheDocument();
    expect(screen.getByTestId('x-axis')).toBeInTheDocument();
    expect(screen.getByTestId('y-axis')).toBeInTheDocument();
    expect(screen.getByTestId('cartesian-grid')).toBeInTheDocument();
    expect(screen.getByTestId('tooltip')).toBeInTheDocument();
  });

  it('renders with empty data', () => {
    render(<RevenuePipelineChart data={[]} />);
    expect(screen.getByText('Revenue Pipeline Trend')).toBeInTheDocument();
    expect(screen.getByTestId('area-chart')).toBeInTheDocument();
  });

  it('applies correct CSS class', () => {
    const { container } = render(<RevenuePipelineChart data={mockData} />);
    const chartContainer = container.querySelector('.revenue-pipeline-chart');
    expect(chartContainer).toBeInTheDocument();
  });

  it('handles large revenue values', () => {
    const largeData: RevenuePipelineData[] = [
      { date: '2024-01-01T00:00:00Z', value: 5000000 },
      { date: '2024-01-02T00:00:00Z', value: 10000000 }
    ];
    
    render(<RevenuePipelineChart data={largeData} />);
    expect(screen.getByTestId('area-chart')).toBeInTheDocument();
  });

  it('handles small revenue values', () => {
    const smallData: RevenuePipelineData[] = [
      { date: '2024-01-01T00:00:00Z', value: 500 },
      { date: '2024-01-02T00:00:00Z', value: 1000 }
    ];
    
    render(<RevenuePipelineChart data={smallData} />);
    expect(screen.getByTestId('area-chart')).toBeInTheDocument();
  });
});
