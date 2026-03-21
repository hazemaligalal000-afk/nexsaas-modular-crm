/**
 * Revenue Pipeline Chart Demo Page
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Standalone demo page for testing and showcasing the RevenuePipelineChart component
 */

import React, { useState } from 'react';
import { RevenuePipelineChart, RevenuePipelineData } from '../../components/Dashboard/RevenuePipelineChart';
import { LanguageSwitcher } from '../../components/LanguageSwitcher/LanguageSwitcher';
import './RevenuePipelineChartDemo.css';

export const RevenuePipelineChartDemo: React.FC = () => {
  const [darkMode, setDarkMode] = useState(false);

  // Generate mock revenue pipeline data (30 days)
  const generateMockData = (): RevenuePipelineData[] => {
    const data: RevenuePipelineData[] = [];
    const today = new Date();
    let baseRevenue = 400000; // Starting revenue
    
    for (let i = 29; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      
      // Simulate growth trend with some variance
      const growth = (29 - i) * 2000; // Gradual growth
      const variance = (Math.random() - 0.5) * 10000; // Random variance
      
      data.push({
        date: date.toISOString(),
        value: Math.max(baseRevenue + growth + variance, 350000) // Keep above 350K
      });
    }
    
    return data;
  };

  const [chartData] = useState<RevenuePipelineData[]>(generateMockData());

  const toggleDarkMode = () => {
    setDarkMode(!darkMode);
    document.documentElement.classList.toggle('dark');
  };

  return (
    <div className={`revenue-pipeline-demo ${darkMode ? 'dark' : ''}`}>
      <div className="demo-header">
        <h1>Revenue Pipeline Chart Demo</h1>
        <div className="demo-controls">
          <button onClick={toggleDarkMode} className="theme-toggle">
            {darkMode ? '☀️ Light Mode' : '🌙 Dark Mode'}
          </button>
          <LanguageSwitcher />
        </div>
      </div>

      <div className="demo-content">
        <section className="demo-section">
          <h2>Revenue Pipeline Trend (30 Days)</h2>
          <p className="demo-description">
            Area chart showing revenue pipeline value over time with gradient fill, 
            currency formatting, RTL support, and smooth animations.
          </p>
          <div className="chart-container">
            <RevenuePipelineChart data={chartData} />
          </div>
        </section>

        <section className="demo-section">
          <h2>Features</h2>
          <ul className="features-list">
            <li>✅ Gradient fill for visual appeal (primary color to transparent)</li>
            <li>✅ Currency formatting on Y-axis (e.g., "$450K", "$1.2M")</li>
            <li>✅ Custom tooltip with formatted date and full currency value</li>
            <li>✅ RTL support (reversed X-axis, right-aligned Y-axis)</li>
            <li>✅ Dark mode compatible</li>
            <li>✅ Responsive design (adjusts to container)</li>
            <li>✅ Smooth animations (500ms ease-out)</li>
            <li>✅ Internationalization (i18n) support</li>
            <li>✅ Accessibility features (ARIA labels, keyboard navigation)</li>
          </ul>
        </section>

        <section className="demo-section">
          <h2>Data Structure</h2>
          <pre className="code-block">
{`interface RevenuePipelineData {
  date: string;  // ISO date string
  value: number; // revenue in USD
}

// Example:
[
  { date: "2024-01-01T00:00:00Z", value: 400000 },
  { date: "2024-01-02T00:00:00Z", value: 420000 },
  { date: "2024-01-03T00:00:00Z", value: 450000 }
]`}
          </pre>
        </section>

        <section className="demo-section">
          <h2>Usage</h2>
          <pre className="code-block">
{`import { RevenuePipelineChart } from './components/Dashboard/RevenuePipelineChart';

<RevenuePipelineChart data={revenuePipelineData} />`}
          </pre>
        </section>

        <section className="demo-section">
          <h2>Testing Instructions</h2>
          <ol className="testing-list">
            <li>Toggle between Light/Dark mode to verify theme support</li>
            <li>Switch language to Arabic to test RTL layout</li>
            <li>Hover over data points to see formatted tooltips</li>
            <li>Resize browser window to test responsive behavior</li>
            <li>Check that gradient fill displays correctly</li>
            <li>Verify Y-axis shows compact currency format (K/M suffixes)</li>
            <li>Verify tooltip shows full currency format</li>
          </ol>
        </section>
      </div>
    </div>
  );
};
