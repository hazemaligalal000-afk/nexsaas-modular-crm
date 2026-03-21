/**
 * Dashboard Page Component
 * Requirements: 14 - Modern Dashboard with KPIs
 * 
 * Main dashboard page with KPIs, date range filtering, and responsive grid layout
 */

import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { DateRangeFilter } from '../../components/Dashboard/DateRangeFilter';
import { DashboardSkeleton } from '../../components/Dashboard/DashboardSkeleton';
import { KPIGrid } from '../../components/Dashboard/KPIGrid';
import { DealsByStageChart } from '../../components/Dashboard/DealsByStageChart';
import { LeadCaptureChart } from '../../components/Dashboard/LeadCaptureChart';
import { AIScoreDistributionChart } from '../../components/Dashboard/AIScoreDistributionChart';
import { RevenuePipelineChart } from '../../components/Dashboard/RevenuePipelineChart';
import { DateRange, DashboardData, DealsByStageData, AIScoreDistributionData, RevenuePipelineData } from '../../types/dashboard';
import { LeadCaptureData } from '../../components/Dashboard/LeadCaptureChart';
import './Dashboard.css';

export const Dashboard: React.FC = () => {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(true);
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [dealsByStageData, setDealsByStageData] = useState<DealsByStageData[]>([]);
  const [leadCaptureData, setLeadCaptureData] = useState<LeadCaptureData[]>([]);
  const [aiScoreData, setAiScoreData] = useState<AIScoreDistributionData[]>([]);
  const [revenuePipelineData, setRevenuePipelineData] = useState<RevenuePipelineData[]>([]);
  
  // Initialize with last 30 days
  const [dateRange, setDateRange] = useState<DateRange>({
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000),
    end: new Date(),
    preset: 'last_30_days'
  });

  // Fetch dashboard data when date range changes
  useEffect(() => {
    const fetchDashboardData = async () => {
      setIsLoading(true);
      
      try {
        // TODO: Replace with actual API call
        // const response = await fetch(`/api/v1/dashboard/kpis?start=${dateRange.start.toISOString()}&end=${dateRange.end.toISOString()}`);
        // const data = await response.json();
        
        // Mock data for now
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        const mockData: DashboardData = {
          totalLeads: {
            label: t('dashboard.totalLeads'),
            value: 1234,
            change: 12.5,
            trend: 'up',
            format: 'number'
          },
          conversionRate: {
            label: t('dashboard.conversionRate'),
            value: 23.4,
            change: -2.1,
            trend: 'down',
            format: 'percentage'
          },
          revenuePipeline: {
            label: t('dashboard.revenuePipeline'),
            value: 456789,
            change: 8.3,
            trend: 'up',
            format: 'currency'
          },
          averageDealSize: {
            label: t('dashboard.averageDealSize'),
            value: 12500,
            change: 5.7,
            trend: 'up',
            format: 'currency'
          }
        };
        
        // Mock deals by stage data
        const mockDealsByStage: DealsByStageData[] = [
          { stage: 'Qualified', count: 45, value: 562500 },
          { stage: 'Proposal', count: 32, value: 400000 },
          { stage: 'Negotiation', count: 18, value: 225000 },
          { stage: 'Closed Won', count: 12, value: 150000 }
        ];
        
        // Mock lead capture trend data (30 days)
        const mockLeadCapture: LeadCaptureData[] = [];
        const today = new Date();
        for (let i = 29; i >= 0; i--) {
          const date = new Date(today);
          date.setDate(date.getDate() - i);
          mockLeadCapture.push({
            date: date.toISOString(),
            count: Math.floor(Math.random() * 30) + 10 // Random count between 10-40
          });
        }
        
        // Mock AI score distribution data
        const totalLeads = 1234; // From mockData.totalLeads.value
        const hotCount = Math.floor(totalLeads * 0.15); // 15% hot
        const warmCount = Math.floor(totalLeads * 0.35); // 35% warm
        const coldCount = totalLeads - hotCount - warmCount; // Remaining cold
        
        const mockAiScore: AIScoreDistributionData[] = [
          {
            category: 'Hot',
            count: hotCount,
            percentage: (hotCount / totalLeads) * 100,
            scoreRange: '80-100'
          },
          {
            category: 'Warm',
            count: warmCount,
            percentage: (warmCount / totalLeads) * 100,
            scoreRange: '50-79'
          },
          {
            category: 'Cold',
            count: coldCount,
            percentage: (coldCount / totalLeads) * 100,
            scoreRange: '0-49'
          }
        ];
        
        // Mock revenue pipeline trend data (30 days)
        const mockRevenuePipeline: RevenuePipelineData[] = [];
        let baseRevenue = 400000; // Starting revenue
        for (let i = 29; i >= 0; i--) {
          const date = new Date(today);
          date.setDate(date.getDate() - i);
          // Simulate growth trend with some variance
          const growth = (29 - i) * 2000; // Gradual growth
          const variance = (Math.random() - 0.5) * 10000; // Random variance
          mockRevenuePipeline.push({
            date: date.toISOString(),
            value: Math.max(baseRevenue + growth + variance, 350000) // Keep above 350K
          });
        }
        
        setDashboardData(mockData);
        setDealsByStageData(mockDealsByStage);
        setLeadCaptureData(mockLeadCapture);
        setAiScoreData(mockAiScore);
        setRevenuePipelineData(mockRevenuePipeline);
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchDashboardData();
  }, [dateRange, t]);

  const handleDateRangeChange = (newRange: DateRange) => {
    setDateRange(newRange);
  };

  const handleStageClick = (stage: string) => {
    // Log navigation intent for now - actual navigation can be implemented later
    console.log(`Navigate to deals filtered by stage: ${stage}`);
    // TODO: Implement navigation to deals list with stage filter
    // Example: navigate(`/deals?stage=${encodeURIComponent(stage)}`);
  };

  const handleCategoryClick = (category: 'Hot' | 'Warm' | 'Cold') => {
    // Log navigation intent for now - actual navigation can be implemented later
    console.log(`Navigate to leads filtered by category: ${category}`);
    // TODO: Implement navigation to leads list with category filter
    // Example: navigate(`/leads?category=${category}`);
  };

  if (isLoading) {
    return <DashboardSkeleton />;
  }

  return (
    <div className="dashboard">
      <div className="dashboard-header">
        <h1 className="dashboard-title">{t('dashboard.title')}</h1>
        <DateRangeFilter value={dateRange} onChange={handleDateRangeChange} />
      </div>

      {dashboardData && (
        <div className="dashboard-content">
          {/* KPI Grid */}
          <KPIGrid 
            kpis={[
              dashboardData.totalLeads,
              dashboardData.conversionRate,
              dashboardData.revenuePipeline,
              dashboardData.averageDealSize
            ]} 
          />

          {/* Charts Grid */}
          <div className="charts-grid">
            <DealsByStageChart 
              data={dealsByStageData} 
              onStageClick={handleStageClick}
            />
            
            <LeadCaptureChart 
              data={leadCaptureData}
            />
            
            <AIScoreDistributionChart 
              data={aiScoreData}
              onCategoryClick={handleCategoryClick}
            />
            
            <RevenuePipelineChart 
              data={revenuePipelineData}
            />
          </div>
        </div>
      )}
    </div>
  );
};
