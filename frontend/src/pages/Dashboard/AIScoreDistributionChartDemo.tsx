/**
 * AI Score Distribution Chart Demo Page
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Demonstrates the AIScoreDistributionChart component with:
 * - Mock data for all three categories
 * - Click handler logging
 * - RTL toggle
 * - Dark mode toggle
 */

import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AIScoreDistributionChart, AIScoreDistributionData } from '../../components/Dashboard/AIScoreDistributionChart';
import './AIScoreDistributionChartDemo.css';

export const AIScoreDistributionChartDemo: React.FC = () => {
  const { i18n } = useTranslation();
  const [isDark, setIsDark] = useState(false);

  // Mock data
  const mockData: AIScoreDistributionData[] = [
    {
      category: 'Hot',
      count: 185,
      percentage: 15,
      scoreRange: '80-100'
    },
    {
      category: 'Warm',
      count: 432,
      percentage: 35,
      scoreRange: '50-79'
    },
    {
      category: 'Cold',
      count: 617,
      percentage: 50,
      scoreRange: '0-49'
    }
  ];

  const handleCategoryClick = (category: 'Hot' | 'Warm' | 'Cold') => {
    console.log(`Category clicked: ${category}`);
    alert(`Navigate to leads filtered by category: ${category}`);
  };

  const toggleLanguage = () => {
    const newLang = i18n.language === 'en' ? 'ar' : 'en';
    i18n.changeLanguage(newLang);
    document.documentElement.dir = newLang === 'ar' ? 'rtl' : 'ltr';
  };

  const toggleDarkMode = () => {
    setIsDark(!isDark);
    document.documentElement.classList.toggle('dark');
  };

  return (
    <div className={`demo-container ${isDark ? 'dark' : ''}`}>
      <div className="demo-header">
        <h1>AI Score Distribution Chart Demo</h1>
        <div className="demo-controls">
          <button onClick={toggleLanguage} className="demo-button">
            Toggle Language (Current: {i18n.language})
          </button>
          <button onClick={toggleDarkMode} className="demo-button">
            Toggle Dark Mode
          </button>
        </div>
      </div>

      <div className="demo-content">
        <div className="demo-section">
          <h2>Interactive Pie Chart</h2>
          <p>Click on any pie slice to filter leads by category</p>
          <AIScoreDistributionChart 
            data={mockData}
            onCategoryClick={handleCategoryClick}
          />
        </div>

        <div className="demo-section">
          <h2>Data Summary</h2>
          <table className="data-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Score Range</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              {mockData.map((item) => (
                <tr key={item.category}>
                  <td>{item.category}</td>
                  <td>{item.scoreRange}</td>
                  <td>{item.count}</td>
                  <td>{item.percentage.toFixed(1)}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="demo-section">
          <h2>Features</h2>
          <ul>
            <li>✅ Three score categories: Hot (80-100), Warm (50-79), Cold (0-49)</li>
            <li>✅ Custom colors: Red for Hot, Amber for Warm, Blue for Cold</li>
            <li>✅ Interactive tooltips with category, count, and percentage</li>
            <li>✅ Click handler for drill-down navigation</li>
            <li>✅ Legend with color indicators</li>
            <li>✅ RTL support (legend position adjusts)</li>
            <li>✅ Dark mode support</li>
            <li>✅ Responsive design</li>
            <li>✅ Smooth animations (800ms)</li>
            <li>✅ Percentage labels on pie slices</li>
          </ul>
        </div>
      </div>
    </div>
  );
};
