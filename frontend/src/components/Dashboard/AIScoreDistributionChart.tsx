/**
 * AI Score Distribution Pie Chart Component
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Pie chart displaying AI score distribution across 3 categories:
 * - Hot (80-100): Red/Orange
 * - Warm (50-79): Yellow/Amber
 * - Cold (0-49): Blue/Cyan
 * 
 * Features:
 * - Custom tooltip with category, count, and percentage
 * - Click handler to navigate to filtered leads list
 * - Legend with colors
 * - RTL support (legend position)
 * - Dark mode support
 * - Responsive design
 * - Smooth animations
 */

import React from 'react';
import { useTranslation } from 'react-i18next';
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';
import { useNumberFormatter } from '../../i18n/hooks/useNumberFormatter';
import './AIScoreDistributionChart.css';

export interface AIScoreDistributionData {
  category: 'Hot' | 'Warm' | 'Cold';
  count: number;
  percentage: number;
  scoreRange: string;
}

interface AIScoreDistributionChartProps {
  data: AIScoreDistributionData[];
  onCategoryClick?: (category: 'Hot' | 'Warm' | 'Cold') => void;
}

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{
    value: number;
    payload: AIScoreDistributionData;
  }>;
}

// Color mapping for categories
const COLORS = {
  Hot: '#ef4444',    // Red
  Warm: '#f59e0b',   // Amber
  Cold: '#3b82f6'    // Blue
};

const CustomTooltip: React.FC<CustomTooltipProps> = ({ active, payload }) => {
  const { t } = useTranslation();
  const { formatNumber } = useNumberFormatter();

  if (active && payload && payload.length) {
    const data = payload[0].payload;
    return (
      <div className="ai-score-tooltip">
        <p className="tooltip-category">
          {t(`dashboard.charts.${data.category.toLowerCase()}`)}
        </p>
        <p className="tooltip-range">
          {data.scoreRange}
        </p>
        <p className="tooltip-count">
          {t('dashboard.charts.count')}: <strong>{formatNumber(data.count)}</strong>
        </p>
        <p className="tooltip-percentage">
          {t('dashboard.charts.percentage')}: <strong>{data.percentage.toFixed(1)}%</strong>
        </p>
      </div>
    );
  }

  return null;
};

export const AIScoreDistributionChart: React.FC<AIScoreDistributionChartProps> = ({ 
  data, 
  onCategoryClick 
}) => {
  const { t, i18n } = useTranslation();
  const isRTL = i18n.dir() === 'rtl';

  const handlePieClick = (_data: any, index: number) => {
    const entry = data[index];
    if (onCategoryClick && entry) {
      onCategoryClick(entry.category);
    } else if (entry) {
      // Log navigation intent for now
      console.log(`Navigate to leads filtered by category: ${entry.category}`);
    }
  };

  // Custom legend formatter
  const renderLegend = (props: any) => {
    const { payload } = props;
    return (
      <ul className="chart-legend">
        {payload.map((entry: any, index: number) => (
          <li key={`legend-${index}`} className="legend-item">
            <span 
              className="legend-color" 
              style={{ backgroundColor: entry.color }}
            />
            <span className="legend-text">
              {t(`dashboard.charts.${entry.value.toLowerCase()}`)}
            </span>
          </li>
        ))}
      </ul>
    );
  };

  return (
    <div className="ai-score-distribution-chart">
      <h3 className="chart-title">{t('dashboard.charts.aiScoreDistribution')}</h3>
      
      <ResponsiveContainer width="100%" height={300}>
        <PieChart>
          <Pie
            data={data}
            dataKey="count"
            nameKey="category"
            cx="50%"
            cy="50%"
            outerRadius={80}
            onClick={handlePieClick}
            style={{ cursor: 'pointer' }}
            animationDuration={800}
            animationEasing="ease-out"
            label={({ percentage }) => `${percentage.toFixed(0)}%`}
            labelLine={{ stroke: 'var(--text-secondary, #6b7280)' }}
          >
            {data.map((entry, index) => (
              <Cell 
                key={`cell-${index}`} 
                fill={COLORS[entry.category]}
                className="chart-pie-cell"
              />
            ))}
          </Pie>
          
          <Tooltip content={<CustomTooltip />} />
          
          <Legend 
            content={renderLegend}
            align={isRTL ? 'left' : 'right'}
            verticalAlign="middle"
            layout="vertical"
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
};
