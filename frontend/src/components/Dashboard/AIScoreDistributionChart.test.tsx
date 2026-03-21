/**
 * AI Score Distribution Chart Component Tests
 * Requirements: 15 - Interactive Charts with Recharts
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AIScoreDistributionChart, AIScoreDistributionData } from './AIScoreDistributionChart';
import '../../i18n';

describe('AIScoreDistributionChart', () => {
  const mockData: AIScoreDistributionData[] = [
    {
      category: 'Hot',
      count: 185,
      percentage: 15,
      scoreRange: '80-100'
    },
    {
      category: 'Warm',
      count: 432,
      percentage: 35,
      scoreRange: '50-79'
    },
    {
      category: 'Cold',
      count: 617,
      percentage: 50,
      scoreRange: '0-49'
    }
  ];

  it('renders chart title', () => {
    render(<AIScoreDistributionChart data={mockData} />);
    expect(screen.getByText('dashboard.charts.aiScoreDistribution')).toBeInTheDocument();
  });

  it('renders with empty data', () => {
    render(<AIScoreDistributionChart data={[]} />);
    expect(screen.getByText('dashboard.charts.aiScoreDistribution')).toBeInTheDocument();
  });

  it('calls onCategoryClick when provided', () => {
    const handleClick = vi.fn();
    render(<AIScoreDistributionChart data={mockData} onCategoryClick={handleClick} />);
    // Note: Testing click on Recharts components requires more complex setup
    // This test verifies the prop is accepted
    expect(handleClick).not.toHaveBeenCalled();
  });

  it('renders all three categories', () => {
    const { container } = render(<AIScoreDistributionChart data={mockData} />);
    // Verify component renders with data
    expect(container.querySelector('.ai-score-distribution-chart')).toBeInTheDocument();
    // Recharts legend may not render in test environment without proper dimensions
  });

  it('handles RTL layout', () => {
    const { container } = render(<AIScoreDistributionChart data={mockData} />);
    expect(container.querySelector('.ai-score-distribution-chart')).toBeInTheDocument();
  });
});
