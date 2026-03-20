import React from 'react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

/**
 * Requirement 15: Interactive Charts (Phase 3)
 */
const data = [
  { name: 'Jan', revenue: 4000 },
  { name: 'Feb', revenue: 3000 },
  { name: 'Mar', revenue: 2000 },
  { name: 'Apr', revenue: 2780 },
  { name: 'May', revenue: 1890 },
  { name: 'Jun', revenue: 2390 },
  { name: 'Jul', revenue: 3490 },
];

export default function RevenuePipelineChart() {
  const isRTL = document.documentElement.dir === 'rtl';

  return (
    <div style={{ height: '350px', background: '#0b1628', padding: '30px', borderRadius: '24px', border: '1px solid #1e3a5f' }}>
      <h3 style={{ fontSize: '18px', fontWeight: '900', color: '#fff', marginBottom: '8px' }}>Revenue Intelligence Pipeline</h3>
      <p style={{ fontSize: '12px', color: '#64748b', marginBottom: '32px' }}>AI-weighted revenue forecast based on deal progression and win probability.</p>
      
      <ResponsiveContainer width="100%" height="80%">
        <AreaChart data={data} margin={{ top: 10, right: 30, left: 0, bottom: 0 }}>
          <defs>
            <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.8}/>
              <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#1e3a5f" />
          
          {/* Requirement 15.6: RTL Axis Flip */}
          <XAxis 
            dataKey="name" 
            reversed={isRTL} 
            stroke="#475569" 
            fontSize={12} 
            tickLine={false} 
            axisLine={false} 
          />
          <YAxis 
            orientation={isRTL ? 'right' : 'left'} 
            stroke="#475569" 
            fontSize={12} 
            tickLine={false} 
            axisLine={false} 
          />
          
          <Tooltip 
            contentStyle={{ background: '#0d1a30', border: '1px solid #1e3a5f', borderRadius: '12px', color: '#fff' }} 
            itemStyle={{ color: '#3b82f6', fontWeight: '800' }}
          />
          
          <Area 
            type="monotone" 
            dataKey="revenue" 
            stroke="#3b82f6" 
            strokeWidth={3}
            fillOpacity={1} 
            fill="url(#colorRevenue)" 
            animationDuration={1500}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}
