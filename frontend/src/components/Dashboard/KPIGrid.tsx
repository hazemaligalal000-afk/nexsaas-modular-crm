/**
 * KPI Grid Component
 * Requirements: 14 - Modern Dashboard with KPIs
 * 
 * Responsive grid layout for KPI cards
 * 4 columns (desktop ≥1024px), 2 columns (tablet 768-1023px), 1 column (mobile <768px)
 */

import React from 'react';
import { KPICard } from './KPICard';
import { KPIData } from '../../types/dashboard';
import './KPIGrid.css';

interface KPIGridProps {
  kpis: KPIData[];
}

export const KPIGrid: React.FC<KPIGridProps> = ({ kpis }) => {
  return (
    <div className="kpi-grid" role="region" aria-label="Key Performance Indicators">
      {kpis.map((kpi, index) => (
        <KPICard key={`kpi-${index}-${kpi.label}`} data={kpi} />
      ))}
    </div>
  );
};
