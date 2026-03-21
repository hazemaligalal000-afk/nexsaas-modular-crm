/**
 * Lead Capture Chart Demo Page
 * Demonstrates the LeadCaptureChart component with sample data
 */

import React, { useState } from 'react';
import { LeadCaptureChart, LeadCaptureData } from '../../components/Dashboard/LeadCaptureChart';
import './LeadCaptureChartDemo.css';

export const LeadCaptureChartDemo: React.FC = () => {
  // Generate sample data for the last 30 days
  const generateSampleData = (days: number): LeadCaptureData[] => {
    const data: LeadCaptureData[] = [];
    const today = new Date();
    
    for (let i = days - 1; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      data.push({
        date: date.toISOString(),
        count: Math.floor(Math.random() * 30) + 10 // Random count between 10-40
      });
    }
    
    return data;
  };

  const [dataRange, setDataRange] = useState<'7' | '30' | '90'>('30');
  const [chartData, setChartData] = useState<LeadCaptureData[]>(generateSampleData(30));

  const handleRangeChange = (range: '7' | '30' | '90') => {
    setDataRange(range);
    setChartData(generateSampleData(parseInt(range)));
  };

  return (
    <div className="lead-capture-demo">
      <div className="demo-header">
        <h1>Lead Capture Chart Demo</h1>
        <p>Interactive line chart showing lead capture trends over time</p>
      </div>

      <div className="demo-controls">
        <label>Date Range:</label>
        <div className="button-group">
          <button
            className={dataRange === '7' ? 'active' : ''}
            onClick={() => handleRangeChange('7')}
          >
            Last 7 Days
          </button>
          <button
            className={dataRange === '30' ? 'active' : ''}
            onClick={() => handleRangeChange('30')}
          >
            Last 30 Days
          </button>
          <button
            className={dataRange === '90' ? 'active' : ''}
            onClick={() => handleRangeChange('90')}
          >
            Last 90 Days
          </button>
        </div>
      </div>

      <div className="demo-chart-container">
        <LeadCaptureChart data={chartData} />
      </div>

      <div className="demo-features">
        <h2>Features</h2>
        <ul>
          <li>✅ Smooth monotone line curve</li>
          <li>✅ Interactive tooltip with formatted date and count</li>
          <li>✅ Locale-aware date formatting on X-axis</li>
          <li>✅ RTL support (reversed axis in Arabic)</li>
          <li>✅ Dark mode compatible</li>
          <li>✅ Responsive design</li>
          <li>✅ Smooth animations (500ms)</li>
          <li>✅ Accessible with ARIA labels</li>
        </ul>
      </div>

      <div className="demo-code">
        <h2>Usage Example</h2>
        <pre>
{`import { LeadCaptureChart } from './components/Dashboard/LeadCaptureChart';

const data = [
  { date: '2024-01-01', count: 15 },
  { date: '2024-01-02', count: 22 },
  { date: '2024-01-03', count: 18 }
];

<LeadCaptureChart data={data} />`}
        </pre>
      </div>
    </div>
  );
};
