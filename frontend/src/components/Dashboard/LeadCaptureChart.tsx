/**
 * Lead Capture Trend Line Chart Component
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Line chart displaying lead capture count over time with:
 * - Locale-aware date formatting on X-axis
 * - Custom tooltip with formatted date and count
 * - Smooth monotone curve
 * - RTL support (reversed axis, right-aligned Y-axis)
 * - Dark mode support
 * - Responsive design
 * - Smooth animations (500ms)
 */

import React from 'react';
import { useTranslation } from 'react-i18next';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer
} from 'recharts';
import { useDateFormatter } from '../../i18n/hooks/useDateFormatter';
import { useNumberFormatter } from '../../i18n/hooks/useNumberFormatter';
import './LeadCaptureChart.css';

export interface LeadCaptureData {
  date: string; // ISO date string or timestamp
  count: number; // number of leads captured on this date
}

interface LeadCaptureChartProps {
  data: LeadCaptureData[];
}

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{
    value: number;
    payload: LeadCaptureData;
  }>;
  label?: string;
}

const CustomTooltip: React.FC<CustomTooltipProps> = ({ active, payload, label }) => {
  const { t } = useTranslation();
  const { formatDate } = useDateFormatter();
  const { formatNumber } = useNumberFormatter();

  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="lead-capture-tooltip">
        <p className="tooltip-date">{formatDate(new Date(data.date))}</p>
        <p className="tooltip-count">
          {t('dashboard.charts.leadsCount')}: <strong>{formatNumber(data.count)}</strong>
        </p>
      </div>
    );
  }

  return null;
};

export const LeadCaptureChart: React.FC<LeadCaptureChartProps> = ({ data }) => {
  const { t, i18n } = useTranslation();
  const { formatShortDate } = useDateFormatter();
  const isRTL = i18n.dir() === 'rtl';

  // Reverse data order for RTL
  const chartData = isRTL ? [...data].reverse() : data;

  return (
    <div className="lead-capture-chart">
      <h3 className="chart-title">{t('dashboard.charts.leadCapture')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <LineChart
          data={chartData}
          margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
        >
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
            tick={{ fill: 'var(--text-secondary, #6b7280)' }}
            axisLine={{ stroke: 'var(--border-color, #e5e7eb)' }}
            label={{ 
              value: t('dashboard.charts.leadsCount'), 
              angle: isRTL ? 90 : -90, 
              position: isRTL ? 'insideRight' : 'insideLeft',
              style: { fill: 'var(--text-secondary, #6b7280)' }
            }}
          />
          
          <Tooltip content={<CustomTooltip />} />
          
          <Line
            type="monotone"
            dataKey="count"
            stroke="var(--primary-color, #3b82f6)"
            strokeWidth={2}
            dot={{ r: 4, fill: 'var(--primary-color, #3b82f6)' }}
            activeDot={{ r: 6, fill: 'var(--primary-color, #3b82f6)' }}
            animationDuration={500}
            animationEasing="ease-out"
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
};
