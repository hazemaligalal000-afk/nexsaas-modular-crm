/**
 * DateRangeFilter Component Tests
 * Requirements: 14 - Modern Dashboard with KPIs
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { DateRangeFilter } from './DateRangeFilter';
import { DateRange } from '../../types/dashboard';

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

describe('DateRangeFilter Component', () => {
  const mockOnChange = vi.fn();
  const defaultValue: DateRange = {
    start: new Date('2024-01-01'),
    end: new Date('2024-01-31'),
    preset: 'last_30_days'
  };

  it('should render all preset buttons', () => {
    render(<DateRangeFilter value={defaultValue} onChange={mockOnChange} />);
    
    expect(screen.getByText('dashboard.dateRange.last_7_days')).toBeInTheDocument();
    expect(screen.getByText('dashboard.dateRange.last_30_days')).toBeInTheDocument();
    expect(screen.getByText('dashboard.dateRange.lastQuarter')).toBeInTheDocument();
    expect(screen.getByText('dashboard.dateRange.lastYear')).toBeInTheDocument();
    expect(screen.getByText('dashboard.dateRange.custom')).toBeInTheDocument();
  });

  it('should highlight active preset', () => {
    render(<DateRangeFilter value={defaultValue} onChange={mockOnChange} />);
    
    const activeButton = screen.getByText('dashboard.dateRange.last_30_days');
    expect(activeButton.className).toContain('active');
  });

  it('should call onChange when preset is clicked', () => {
    render(<DateRangeFilter value={defaultValue} onChange={mockOnChange} />);
    
    const last7DaysButton = screen.getByText('dashboard.dateRange.last_7_days');
    fireEvent.click(last7DaysButton);
    
    expect(mockOnChange).toHaveBeenCalled();
    const call = mockOnChange.mock.calls[0][0];
    expect(call.preset).toBe('last_7_days');
  });

  it('should show custom date picker when custom button is clicked', () => {
    render(<DateRangeFilter value={defaultValue} onChange={mockOnChange} />);
    
    const customButton = screen.getByText('dashboard.dateRange.custom');
    fireEvent.click(customButton);
    
    expect(screen.getByLabelText('date.from')).toBeInTheDocument();
    expect(screen.getByLabelText('date.to')).toBeInTheDocument();
  });

  it('should have apply and cancel buttons in custom picker', () => {
    render(<DateRangeFilter value={defaultValue} onChange={mockOnChange} />);
    
    const customButton = screen.getByText('dashboard.dateRange.custom');
    fireEvent.click(customButton);
    
    expect(screen.getByText('actions.apply')).toBeInTheDocument();
    expect(screen.getByText('actions.cancel')).toBeInTheDocument();
  });

  it('should close custom picker when cancel is clicked', () => {
    render(<DateRangeFilter value={defaultValue} onChange={mockOnChange} />);
    
    const customButton = screen.getByText('dashboard.dateRange.custom');
    fireEvent.click(customButton);
    
    const cancelButton = screen.getByText('actions.cancel');
    fireEvent.click(cancelButton);
    
    expect(screen.queryByLabelText('date.from')).not.toBeInTheDocument();
  });
});
