/**
 * Dashboard Type Definitions
 * Requirements: 14 - Modern Dashboard with KPIs
 */

export interface DateRange {
  start: Date;
  end: Date;
  preset: 'last_7_days' | 'last_30_days' | 'last_quarter' | 'last_year' | 'custom';
}

export interface KPIData {
  label: string;
  value: number | string;
  change: number;
  trend: 'up' | 'down' | 'neutral';
  format: 'number' | 'currency' | 'percentage';
}

export interface DashboardData {
  totalLeads: KPIData;
  conversionRate: KPIData;
  revenuePipeline: KPIData;
  averageDealSize: KPIData;
}

export interface DealsByStageData {
  stage: string;
  count: number;
  value: number; // total value in this stage
}

export interface AIScoreDistributionData {
  category: 'Hot' | 'Warm' | 'Cold';
  count: number;
  percentage: number;
  scoreRange: string;
}

export interface RevenuePipelineData {
  date: string; // ISO date string or timestamp
  value: number; // revenue pipeline value in USD
}
