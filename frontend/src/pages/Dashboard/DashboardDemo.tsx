/**
 * Dashboard Demo Component
 * 
 * This file demonstrates how to use the Dashboard component
 * and can be used for testing/development purposes
 */

import React from 'react';
import { Dashboard } from './Dashboard';
import '../../i18n'; // Initialize i18n

export const DashboardDemo: React.FC = () => {
  return (
    <div style={{ minHeight: '100vh', backgroundColor: 'var(--bg-color, #f9fafb)' }}>
      <Dashboard />
    </div>
  );
};

// Usage in App.tsx or routing:
// import { DashboardDemo } from './pages/Dashboard/DashboardDemo';
// 
// <Routes>
//   <Route path="/dashboard" element={<Dashboard />} />
//   <Route path="/dashboard-demo" element={<DashboardDemo />} />
// </Routes>
