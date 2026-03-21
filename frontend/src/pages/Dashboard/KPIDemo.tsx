/**
 * KPI Components Demo
 * Demonstrates KPICard and KPIGrid components with various configurations
 */

import React from 'react';
import { KPIGrid } from '../../components/Dashboard/KPIGrid';
import { KPIData } from '../../types/dashboard';
import '../../index.css';

export const KPIDemo: React.FC = () => {
  const mockKPIs: KPIData[] = [
    {
      label: 'Total Leads',
      value: 1234,
      change: 12.5,
      trend: 'up',
      format: 'number',
    },
    {
      label: 'Conversion Rate',
      value: 23.4,
      change: -2.1,
      trend: 'down',
      format: 'percentage',
    },
    {
      label: 'Revenue Pipeline',
      value: 456789,
      change: 8.3,
      trend: 'up',
      format: 'currency',
    },
    {
      label: 'Average Deal Size',
      value: 12500,
      change: 5.7,
      trend: 'up',
      format: 'currency',
    },
  ];

  return (
    <div style={{ padding: '2rem', maxWidth: '1400px', margin: '0 auto' }}>
      <h1 style={{ marginBottom: '2rem', fontSize: '2rem', fontWeight: 'bold' }}>
        KPI Components Demo
      </h1>
      
      <section style={{ marginBottom: '3rem' }}>
        <h2 style={{ marginBottom: '1rem', fontSize: '1.5rem', fontWeight: '600' }}>
          KPI Grid (Responsive)
        </h2>
        <p style={{ marginBottom: '1rem', color: '#666' }}>
          Resize the browser to see responsive behavior:
          <br />
          • Desktop (≥1024px): 4 columns
          <br />
          • Tablet (768-1023px): 2 columns
          <br />
          • Mobile (&lt;768px): 1 column
        </p>
        <KPIGrid kpis={mockKPIs} />
      </section>

      <section>
        <h2 style={{ marginBottom: '1rem', fontSize: '1.5rem', fontWeight: '600' }}>
          Features Demonstrated
        </h2>
        <ul style={{ listStyle: 'disc', paddingLeft: '2rem', color: '#666' }}>
          <li>Number formatting with locale support (1,234)</li>
          <li>Currency formatting ($456,789)</li>
          <li>Percentage formatting (23.4%)</li>
          <li>Trend indicators (↑ up, ↓ down, → neutral)</li>
          <li>Color-coded trends (green for up, red for down)</li>
          <li>Responsive grid layout</li>
          <li>RTL support using CSS logical properties</li>
          <li>Dark mode support</li>
          <li>Hover effects and animations</li>
          <li>Accessibility (ARIA labels, focus states)</li>
        </ul>
      </section>
    </div>
  );
};
