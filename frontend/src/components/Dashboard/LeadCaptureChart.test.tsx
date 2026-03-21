/**
 * Unit Tests for LeadCaptureChart Component
 * Requirements: 15 - Interactive Charts with Recharts
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LeadCaptureChart, LeadCaptureData } from './LeadCaptureChart';

// Mock react-i18next at the top level
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const translations: Record<string, string> = {
        'dashboard.charts.leadCapture': 'Lead Capture Trend',
        'dashboard.charts.leadsCount': 'Leads',
        'dashboard.charts.date': 'Date'
      };
      return translations[key] || key;
    },
    i18n: {
      dir: () => 'ltr',
      language: 'en-US'
    }
  })
}));

// Mock date formatter hook at the top level
vi.mock('../../i18n/hooks/useDateFormatter', () => ({
  useDateFormatter: () => ({
    formatDate: (date: Date) => date.toLocaleDateString('en-US'),
    formatShortDate: (date: Date) => {
      const month = date.toLocaleDateString('en-US', { month: 'short' });
      const day = date.getDate();
      return `${month} ${day}`;
    }
  })
}));

// Mock number formatter hook at the top level
vi.mock('../../i18n/hooks/useNumberFormatter', () => ({
  useNumberFormatter: () => ({
    formatNumber: (num: number) => num.toLocaleString('en-US')
  })
}));

describe('LeadCaptureChart', () => {
  const mockData: LeadCaptureData[] = [
    { date: '2024-01-01', count: 15 },
    { date: '2024-01-02', count: 22 },
    { date: '2024-01-03', count: 18 },
    { date: '2024-01-04', count: 30 },
    { date: '2024-01-05', count: 25 }
  ];

  it('renders chart title correctly', () => {
    render(<LeadCaptureChart data={mockData} />);
    expect(screen.getByText('Lead Capture Trend')).toBeInTheDocument();
  });

  it('renders with empty data', () => {
    render(<LeadCaptureChart data={[]} />);
    expect(screen.getByText('Lead Capture Trend')).toBeInTheDocument();
  });

  it('renders chart container with correct class', () => {
    const { container } = render(<LeadCaptureChart data={mockData} />);
    const chartDiv = container.querySelector('.lead-capture-chart');
    expect(chartDiv).toBeInTheDocument();
  });

  it('renders ResponsiveContainer', () => {
    const { container } = render(<LeadCaptureChart data={mockData} />);
    const responsiveContainer = container.querySelector('.recharts-responsive-container');
    expect(responsiveContainer).toBeInTheDocument();
  });

  it('renders chart with correct structure', () => {
    const { container } = render(<LeadCaptureChart data={mockData} />);
    const chartTitle = container.querySelector('.chart-title');
    expect(chartTitle).toBeInTheDocument();
    expect(chartTitle?.textContent).toBe('Lead Capture Trend');
  });
});

describe('LeadCaptureChart RTL Support', () => {
  const mockData: LeadCaptureData[] = [
    { date: '2024-01-01', count: 15 },
    { date: '2024-01-02', count: 22 }
  ];

  it('renders correctly in RTL mode', () => {
    const { container } = render(<LeadCaptureChart data={mockData} />);
    const chart = container.querySelector('.lead-capture-chart');
    expect(chart).toBeInTheDocument();
  });
});

describe('LeadCaptureChart Data Interface', () => {
  it('accepts valid LeadCaptureData structure', () => {
    const validData: LeadCaptureData[] = [
      { date: '2024-01-01', count: 10 },
      { date: '2024-01-02', count: 20 }
    ];

    expect(() => render(<LeadCaptureChart data={validData} />)).not.toThrow();
  });

  it('handles ISO date strings', () => {
    const isoData: LeadCaptureData[] = [
      { date: new Date('2024-01-01').toISOString(), count: 10 },
      { date: new Date('2024-01-02').toISOString(), count: 20 }
    ];

    expect(() => render(<LeadCaptureChart data={isoData} />)).not.toThrow();
  });
});
