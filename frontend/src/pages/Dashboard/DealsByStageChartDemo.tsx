/**
 * DealsByStageChart Demo Page
 * Requirements: 15 - Interactive Charts with Recharts
 * 
 * Standalone demo page to showcase the DealsByStageChart component
 */

import React, { useState } from 'react';
import { DealsByStageChart } from '../../components/Dashboard/DealsByStageChart';
import { DealsByStageData } from '../../types/dashboard';
import './DealsByStageChartDemo.css';

export const DealsByStageChartDemo: React.FC = () => {
  const [clickedStage, setClickedStage] = useState<string | null>(null);

  // Sample data
  const sampleData: DealsByStageData[] = [
    { stage: 'Qualified', count: 45, value: 562500 },
    { stage: 'Proposal', count: 32, value: 400000 },
    { stage: 'Negotiation', count: 18, value: 225000 },
    { stage: 'Closed Won', count: 12, value: 150000 }
  ];

  const handleStageClick = (stage: string) => {
    setClickedStage(stage);
    console.log(`Clicked on stage: ${stage}`);
  };

  return (
    <div className="deals-chart-demo">
      <div className="demo-header">
        <h1>Deals by Stage Chart Demo</h1>
        <p>Interactive bar chart showing deals distribution across pipeline stages</p>
      </div>

      <div className="demo-content">
        <div className="demo-section">
          <h2>Features</h2>
          <ul>
            <li>✅ Bar chart with Recharts library</li>
            <li>✅ Custom tooltip with formatted values</li>
            <li>✅ Click handler for navigation</li>
            <li>✅ RTL support (reversed axis, right-aligned Y-axis)</li>
            <li>✅ Dark mode support</li>
            <li>✅ Responsive design</li>
            <li>✅ Accessibility (ARIA labels, keyboard navigation)</li>
            <li>✅ Smooth animations</li>
          </ul>
        </div>

        <div className="demo-section">
          <h2>Chart</h2>
          <DealsByStageChart data={sampleData} onStageClick={handleStageClick} />
        </div>

        {clickedStage && (
          <div className="demo-section">
            <h2>Last Clicked Stage</h2>
            <div className="clicked-stage-info">
              <p>
                <strong>Stage:</strong> {clickedStage}
              </p>
              <p className="info-text">
                In a real application, this would navigate to the deals list filtered by this stage.
              </p>
            </div>
          </div>
        )}

        <div className="demo-section">
          <h2>Sample Data</h2>
          <pre className="code-block">
            {JSON.stringify(sampleData, null, 2)}
          </pre>
        </div>

        <div className="demo-section">
          <h2>Usage</h2>
          <pre className="code-block">
{`import { DealsByStageChart } from './components/Dashboard/DealsByStageChart';
import { DealsByStageData } from './types/dashboard';

const data: DealsByStageData[] = [
  { stage: 'Qualified', count: 45, value: 562500 },
  { stage: 'Proposal', count: 32, value: 400000 },
  { stage: 'Negotiation', count: 18, value: 225000 },
  { stage: 'Closed Won', count: 12, value: 150000 }
];

const handleStageClick = (stage: string) => {
  // Navigate to filtered deals list
  navigate(\`/deals?stage=\${encodeURIComponent(stage)}\`);
};

<DealsByStageChart 
  data={data} 
  onStageClick={handleStageClick}
/>`}
          </pre>
        </div>

        <div className="demo-section">
          <h2>RTL Testing</h2>
          <p>
            Switch to Arabic language using the language switcher to see the chart in RTL mode.
            The X-axis will be reversed and the Y-axis will be positioned on the right.
          </p>
        </div>
      </div>
    </div>
  );
};
