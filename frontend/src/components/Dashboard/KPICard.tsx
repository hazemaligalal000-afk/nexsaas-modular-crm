/**
 * KPI Card Component
 * Requirements: 14 - Modern Dashboard with KPIs
 * 
 * Displays a single KPI with value, trend indicator, and percentage change
 * Supports number, currency, and percentage formatting with locale awareness
 */

import React from 'react';
import { useNumberFormatter } from '../../i18n/hooks/useNumberFormatter';
import { KPIData } from '../../types/dashboard';
import './KPICard.css';

interface KPICardProps {
  data: KPIData;
}

export const KPICard: React.FC<KPICardProps> = ({ data }) => {
  const { formatNumber, formatCurrency, formatPercent } = useNumberFormatter();

  // Format the main value based on the format type
  const formatValue = (value: number | string, format: 'number' | 'currency' | 'percentage'): string => {
    if (typeof value === 'string') return value;
    
    switch (format) {
      case 'currency':
        return formatCurrency(value);
      case 'percentage':
        return formatPercent(value);
      case 'number':
      default:
        return formatNumber(value);
    }
  };

  // Determine trend icon
  const getTrendIcon = (trend: 'up' | 'down' | 'neutral'): string => {
    switch (trend) {
      case 'up':
        return '↑';
      case 'down':
        return '↓';
      case 'neutral':
      default:
        return '→';
    }
  };

  // Determine trend color class
  const getTrendClass = (trend: 'up' | 'down' | 'neutral'): string => {
    switch (trend) {
      case 'up':
        return 'kpi-trend-up';
      case 'down':
        return 'kpi-trend-down';
      case 'neutral':
      default:
        return 'kpi-trend-neutral';
    }
  };

  const formattedValue = formatValue(data.value, data.format);
  const formattedChange = formatPercent(Math.abs(data.change));
  const trendIcon = getTrendIcon(data.trend);
  const trendClass = getTrendClass(data.trend);

  return (
    <div className="kpi-card">
      <div className="kpi-label">{data.label}</div>
      <div className="kpi-value">{formattedValue}</div>
      <div className={`kpi-trend ${trendClass}`}>
        <span className="kpi-trend-icon" aria-hidden="true">{trendIcon}</span>
        <span className="kpi-trend-value">{formattedChange}</span>
      </div>
    </div>
  );
};
