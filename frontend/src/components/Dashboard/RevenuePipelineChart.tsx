/**
 * Revenue Pipeline Trend Area Chart Component
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Area chart displaying revenue pipeline value over time with:
 * - Gradient fill for visual appeal
 * - Currency formatting on Y-axis using useNumberFormatter hook
 * - Custom tooltip with formatted date and currency value
 * - RTL support (reversed axis, right-aligned Y-axis)
 * - Dark mode support
 * - Responsive design
 * - Smooth animations (500ms)
 */

import React from 'react';
import { useTranslation } from 'react-i18next';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer
} from 'recharts';
import { useDateFormatter } from '../../i18n/hooks/useDateFormatter';
import { useNumberFormatter } from '../../i18n/hooks/useNumberFormatter';
import './RevenuePipelineChart.css';

export interface RevenuePipelineData {
  date: string; // ISO date string or timestamp
  value: number; // revenue pipeline value in USD
}

interface RevenuePipelineChartProps {
  data: RevenuePipelineData[];
}

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{
    value: number;
    payload: RevenuePipelineData;
  }>;
  label?: string;
}

const CustomTooltip: React.FC<CustomTooltipProps> = ({ active, payload }) => {
  const { t } = useTranslation();
  const { formatDate } = useDateFormatter();
  const { formatCurrency } = useNumberFormatter();

  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="revenue-pipeline-tooltip">
        <p className="tooltip-date">{formatDate(new Date(data.date))}</p>
        <p className="tooltip-value">
          {t('dashboard.charts.revenue')}: <strong>{formatCurrency(data.value)}</strong>
        </p>
      </div>
    );
  }

  return null;
};

export const RevenuePipelineChart: React.FC<RevenuePipelineChartProps> = ({ data }) => {
  const { t, i18n } = useTranslation();
  const { formatShortDate } = useDateFormatter();
  const { formatCurrency, formatNumber } = useNumberFormatter();
  const isRTL = i18n.dir() === 'rtl';

  // Reverse data order for RTL
  const chartData = isRTL ? [...data].reverse() : data;

  // Format Y-axis values as compact currency (e.g., "$450K")
  const formatYAxis = (value: number): string => {
    if (value >= 1000000) {
      const millions = value / 1000000;
      return '$' + formatNumber(millions, { maximumFractionDigits: 1 }) + 'M';
    } else if (value >= 1000) {
      const thousands = value / 1000;
      return '$' + formatNumber(thousands, { maximumFractionDigits: 0 }) + 'K';
    }
    return formatCurrency(value);
  };

  return (
    <div className="revenue-pipeline-chart">
      <h3 className="chart-title">{t('dashboard.charts.revenuePipeline')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <AreaChart
          data={chartData}
          margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
        >
          <defs>
            <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="var(--primary-color, #3b82f6)" stopOpacity={0.8}/>
              <stop offset="95%" stopColor="var(--primary-color, #3b82f6)" stopOpacity={0}/>
            </linearGradient>
          </defs>
          
          <CartesianGrid strokeDasharray="3 3" className="chart-grid" />
          
          <XAxis
            dataKey="date"
            tickFormatter={(value) => formatShortDate(new Date(value))}
            reversed={isRTL}
            tick={{ fill: 'var(--text-secondary, #6b7280)' }}
            axisLine={{ stroke: 'var(--border-color, #e5e7eb)' }}
          />
          
          <YAxis
            orientation={isRTL ? 'right' : 'left'}
            tickFormatter={formatYAxis}
            tick={{ fill: 'var(--text-secondary, #6b7280)' }}
            axisLine={{ stroke: 'var(--border-color, #e5e7eb)' }}
            label={{ 
              value: t('dashboard.charts.revenue'), 
              angle: isRTL ? 90 : -90, 
              position: isRTL ? 'insideRight' : 'insideLeft',
              style: { fill: 'var(--text-secondary, #6b7280)' }
            }}
          />
          
          <Tooltip content={<CustomTooltip />} />
          
          <Area
            type="monotone"
            dataKey="value"
            stroke="var(--primary-color, #3b82f6)"
            strokeWidth={2}
            fill="url(#colorRevenue)"
            animationDuration={500}
            animationEasing="ease-out"
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
};
