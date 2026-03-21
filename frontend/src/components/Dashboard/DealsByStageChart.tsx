/**
 * Deals by Stage Bar Chart Component
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Bar chart displaying deals count by pipeline stage with:
 * - Custom tooltip with formatted values
 * - Click handler to navigate to filtered list
 * - RTL support (reversed axis, right-aligned Y-axis)
 * - Dark mode support
 * - Responsive design
 */

import React from 'react';
import { useTranslation } from 'react-i18next';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell
} from 'recharts';
import { DealsByStageData } from '../../types/dashboard';
import { useNumberFormatter } from '../../i18n/hooks/useNumberFormatter';
import './DealsByStageChart.css';

interface DealsByStageChartProps {
  data: DealsByStageData[];
  onStageClick?: (stage: string) => void;
}

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{
    value: number;
    payload: DealsByStageData;
  }>;
}

const CustomTooltip: React.FC<CustomTooltipProps> = ({ active, payload }) => {
  const { t } = useTranslation();
  const { formatNumber } = useNumberFormatter();

  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="deals-chart-tooltip">
        <p className="tooltip-stage">{data.stage}</p>
        <p className="tooltip-count">
          {t('dashboard.charts.deals')}: <strong>{formatNumber(data.count)}</strong>
        </p>
        <p className="tooltip-value">
          {t('dashboard.charts.totalValue')}: <strong>${formatNumber(data.value)}</strong>
        </p>
      </div>
    );
  }

  return null;
};

export const DealsByStageChart: React.FC<DealsByStageChartProps> = ({ 
  data, 
  onStageClick 
}) => {
  const { t, i18n } = useTranslation();
  const isRTL = i18n.dir() === 'rtl';

  // Reverse data order for RTL
  const chartData = isRTL ? [...data].reverse() : data;

  const handleBarClick = (_data: any, index: number) => {
    const entry = chartData[index];
    if (onStageClick && entry) {
      onStageClick(entry.stage);
    } else if (entry) {
      // Log navigation intent for now
      console.log(`Navigate to deals filtered by stage: ${entry.stage}`);
    }
  };

  return (
    <div className="deals-by-stage-chart">
      <h3 className="chart-title">{t('dashboard.charts.dealsByStage')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <BarChart
          data={chartData}
          margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
        >
          <CartesianGrid strokeDasharray="3 3" className="chart-grid" />
          
          <XAxis
            dataKey="stage"
            reversed={isRTL}
            tick={{ fill: 'var(--text-secondary, #6b7280)' }}
            axisLine={{ stroke: 'var(--border-color, #e5e7eb)' }}
          />
          
          <YAxis
            orientation={isRTL ? 'right' : 'left'}
            tick={{ fill: 'var(--text-secondary, #6b7280)' }}
            axisLine={{ stroke: 'var(--border-color, #e5e7eb)' }}
            label={{ 
              value: t('dashboard.charts.deals'), 
              angle: isRTL ? 90 : -90, 
              position: isRTL ? 'insideRight' : 'insideLeft',
              style: { fill: 'var(--text-secondary, #6b7280)' }
            }}
          />
          
          <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(59, 130, 246, 0.1)' }} />
          
          <Bar
            dataKey="count"
            fill="var(--primary-color, #3b82f6)"
            radius={[8, 8, 0, 0]}
            onClick={handleBarClick}
            style={{ cursor: 'pointer' }}
            animationDuration={800}
            animationEasing="ease-out"
          >
            {chartData.map((_entry, index) => (
              <Cell
                key={`cell-${index}`}
                className="chart-bar"
              />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
};
