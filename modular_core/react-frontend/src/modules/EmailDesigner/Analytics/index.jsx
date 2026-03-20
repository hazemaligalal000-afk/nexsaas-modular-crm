import React from 'react';

const KPICARD = ({ label, value, trend, color }) => (
  <div style={{ background: '#0d1a30', padding: '24px', borderRadius: '24px', border: '1.5px solid #1e3a5f', boxShadow: '0 8px 30px rgba(0,0,0,0.3)' }}>
     <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px' }}>
        <span style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase' }}>{label}</span>
        <span style={{ fontSize: '12px', color: (trend > 0 ? '#10b981' : '#f43f5e'), fontWeight: '700' }}>{trend > 0 ? '↑' : '↓'} {Math.abs(trend)}%</span>
     </div>
     <div style={{ display: 'flex', alignItems: 'baseline', gap: '8px' }}>
        <div style={{ fontSize: '32px', fontWeight: '900', color: '#fff' }}>{value}</div>
        <div style={{ width: '40px', height: '4px', background: color, borderRadius: '100px', opacity: 0.5 }}></div>
     </div>
  </div>
);

export default function EmailAnalytics() {
  const stats = [
    { label: 'Total Sent', value: '42.8k', trend: 12.4, color: '#3b82f6' },
    { label: 'Open Rate', value: '38.2%', trend: 4.1, color: '#8b5cf6' },
    { label: 'Click Rate', value: '4.8%', trend: -0.5, color: '#10b981' },
    { label: 'Bounced', value: '1.2%', trend: -2.3, color: '#f43f5e' }
  ];

  const TOP_CAMPAIGNS = [
    { name: 'SaaS Launch Promo', sent: '12.4k', opened: '28.1%', clicked: '3.4%' },
    { name: 'Monthly Recap', sent: '5.2k', opened: '44.5%', clicked: '5.8%' },
    { name: 'Enterprise Pitch', sent: '1.2k', opened: '62.0%', clicked: '12.4%' }
  ];

  return (
    <div style={{ padding: '32px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '40px' }}>
        <div>
          <h1 style={{ margin: '0 0 8px', fontSize: '28px', fontWeight: '900' }}>📈 Delivery Performance</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Global send intelligence and behavioral tracking reports</p>
        </div>
        <div style={{ display: 'flex', gap: '8px' }}>
           <button style={{ padding: '10px 24px', background: '#1e293b', border: '1px solid #1e3a5f', borderRadius: '12px', color: '#fff', fontSize: '14px', fontWeight: '800', cursor: 'pointer' }}>Last 30 Days</button>
           <button style={{ padding: '10px 24px', background: '#3b82f6', border: 'none', borderRadius: '12px', color: '#fff', fontSize: '14px', fontWeight: '800', cursor: 'pointer' }}>Export CSV</button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '20px', marginBottom: '40px' }}>
         {stats.map(s => <KPICARD key={s.label} {...s} />)}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '7fr 5fr', gap: '40px' }}>
         {/* Performance Chart Placeholder */}
         <div style={{ background: '#0d1a30', padding: '32px', borderRadius: '32px', border: '1.5px solid #0f172a' }}>
            <h3 style={{ margin: '0 0 32px', fontSize: '18px', fontWeight: '800' }}>Volume & Engagement Trend</h3>
            <div style={{ height: '300px', display: 'flex', alignItems: 'flex-end', gap: '24px', padding: '0 20px' }}>
               {[40, 60, 45, 80, 55, 90, 70, 100, 85,  65].map((h, i) => (
                  <div key={i} style={{ flex: 1, background: `linear-gradient(to top, #3b82f644, #3b82f6)`, height: `${h}%`, borderRadius: '6px 6px 0 0', position: 'relative' }}>
                     {i === 7 && <div style={{ position: 'absolute', top: '-30px', left: '50%', transform: 'translateX(-50%)', fontSize: '11px', fontWeight: '800', color: '#fff' }}>12.4k</div>}
                  </div>
               ))}
            </div>
            <div style={{ borderTop: '1px solid #1e3a5f', marginTop: '16px', paddingTop: '16px', display: 'flex', justifyContent: 'space-between', color: '#475569', fontSize: '11px', fontWeight: '800' }}>
               <span>MAR 10</span>
               <span>MAR 15</span>
               <span>MAR 20</span>
               <span>MAR 25</span>
               <span>MAR 30</span>
            </div>
         </div>

         {/* Top Templates List */}
         <div style={{ background: '#0d1a30', padding: '32px', borderRadius: '32px', border: '1.5px solid #0f172a' }}>
            <h3 style={{ margin: '0 0 24px', fontSize: '18px', fontWeight: '800' }}>🏆 Top Performance</h3>
            {TOP_CAMPAIGNS.map(c => (
              <div key={c.name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '16px 0', borderBottom: '1px solid #1e3a5f' }}>
                 <div style={{ fontWeight: '800', fontSize: '14px' }}>{c.name}</div>
                 <div style={{ display: 'flex', gap: '24px', textAlign: 'right' }}>
                    <div>
                       <div style={{ fontSize: '11px', color: '#475569', fontWeight: '800' }}>OPEN</div>
                       <div style={{ fontSize: '14px', fontWeight: '800', color: '#10b981' }}>{c.opened}</div>
                    </div>
                    <div>
                       <div style={{ fontSize: '11px', color: '#475569', fontWeight: '800' }}>CLICK</div>
                       <div style={{ fontSize: '14px', fontWeight: '800', color: '#3b82f6' }}>{c.clicked}</div>
                    </div>
                 </div>
              </div>
            ))}
            <button style={{ width: '100%', marginTop: '24px', padding: '12px', background: 'transparent', border: '1px solid #1e3a5f', borderRadius: '12px', color: '#64748b', fontSize: '13px', fontWeight: '700', cursor: 'pointer' }}>View All Campaigns</button>
         </div>
      </div>
    </div>
  );
}
