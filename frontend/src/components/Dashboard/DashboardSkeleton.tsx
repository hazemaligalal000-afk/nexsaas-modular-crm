/**
 * Dashboard Loading Skeleton Component
 * Requirements: 14, 19 - Loading Skeletons Instead of Spinners
 * 
 * Displays animated placeholder UI while dashboard data loads
 */

import React from 'react';
import './DashboardSkeleton.css';

export const DashboardSkeleton: React.FC = () => {
  return (
    <div className="dashboard-skeleton">
      {/* Date Range Filter Skeleton */}
      <div className="skeleton-header">
        <div className="skeleton-title" />
        <div className="skeleton-filters">
          <div className="skeleton-button" />
          <div className="skeleton-button" />
          <div className="skeleton-button" />
          <div className="skeleton-button" />
          <div className="skeleton-button" />
        </div>
      </div>

      {/* KPI Cards Skeleton */}
      <div className="skeleton-kpi-grid">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="skeleton-kpi-card">
            <div className="skeleton-kpi-label" />
            <div className="skeleton-kpi-value" />
            <div className="skeleton-kpi-trend" />
          </div>
        ))}
      </div>

      {/* Charts Skeleton */}
      <div className="skeleton-charts-grid">
        {[1, 2].map((i) => (
          <div key={i} className="skeleton-chart">
            <div className="skeleton-chart-title" />
            <div className="skeleton-chart-content" />
          </div>
        ))}
      </div>
    </div>
  );
};
