import React, { useState } from 'react';

/**
 * Saudi Dashboard: Unified Legal Compliance (Saudi Hub - Requirement Phase 2)
 * High-quality UI for Muqeem, GOSI, Qiwa, and ZATCA status monitoring.
 */
export default function SaudiDashboard() {
  const [activeTab, setActiveTab] = useState('muqeem');

  const STATS = {
    muqeem: { icon: '🇸🇦', alerts: 14, status: 'Expiring Soon', detail: 'Iqama Renewals Required' },
    gosi: { icon: '📑', ratio: '24.5%', status: 'Compliance Pass', detail: 'Saudization Platinum' },
    zatca: { icon: '🧾', sent: '1,240', status: 'Reported', detail: 'Fatoorah Phase 2 Compliant' },
    mudad: { icon: '💳', wps: '100%', status: 'Paid', detail: 'Wage Protection System Sync' }
  };

  const ITEMS = [
    { name: 'Ahmed Al-Fatiha', doc: 'Iqama #234...01', expiry: '7 Days Left', action: 'Renew (Muqeem)' },
    { name: 'Sami Mansour', doc: 'Iqama #442...12', expiry: '12 Days Left', action: 'Renew (Muqeem)' },
    { name: 'Riyadh Logistics', doc: 'Commercial Reg #002', expiry: '2027-04-01', action: 'Update (Qiwa)' }
  ];

  return (
    <div style={{ minHeight: '100vh', background: '#0b1628', color: '#fff', padding: '60px' }}>
      <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
        
        {/* Hub Header */}
        <div style={{ marginBottom: '60px' }}>
           <h1 style={{ fontSize: '42px', fontWeight: '900', letterSpacing: '-0.03em', marginBottom: '8px' }}>Saudi Compliance Hub.</h1>
           <p style={{ fontSize: '18px', color: '#64748b' }}>Precision monitoring for KSA government integrations and legal mandates.</p>
        </div>

        {/* Global KPI Cards */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '24px', marginBottom: '60px' }}>
           {Object.entries(STATS).map(([key, item]) => (
             <div key={key} onClick={() => setActiveTab(key)} style={{ background: '#0d1a30', borderRadius: '24px', border: activeTab === key ? '2px solid #3b82f6' : '1.5px solid #1e3a5f', padding: '32px', cursor: 'pointer', transition: '0.2s' }}>
                <div style={{ fontSize: '11px', fontWeight: '900', color: '#475569', textTransform: 'uppercase', marginBottom: '16px' }}>{key} Platform</div>
                <div style={{ fontSize: '32px', fontWeight: '900', marginBottom: '8px' }}>{item.alerts || item.ratio || item.sent || item.wps}</div>
                <div style={{ fontSize: '13px', fontWeight: '700', color: '#10b981' }}>{item.status}</div>
                <div style={{ fontSize: '11px', color: '#475569', marginTop: '12px' }}>{item.detail}</div>
             </div>
           ))}
        </div>

        {/* Platform-specific Actions Table */}
        <div style={{ background: '#0d1a30', borderRadius: '32px', border: '1px solid #1e3a5f', padding: '40px' }}>
           <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
              <h2 style={{ fontSize: '24px', fontWeight: '900' }}>Platform Priority Alerts (Muqeem)</h2>
              <button style={{ background: '#3b82f6', border: 'none', padding: '12px 24px', borderRadius: '14px', color: '#fff', fontWeight: '800', cursor: 'pointer' }}>Sync Now</button>
           </div>
           
           <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ borderBottom: '1.5px solid #1e3a5f', color: '#475569', textAlign: 'left', fontSize: '11px', fontWeight: '900', textTransform: 'uppercase' }}>
                   <th style={{ padding: '16px' }}>Expatriate/Document</th>
                   <th style={{ padding: '16px' }}>Platform Target</th>
                   <th style={{ padding: '16px' }}>Time to Overdue</th>
                   <th style={{ padding: '16px' }}>Action</th>
                </tr>
              </thead>
              <tbody>
                {ITEMS.map((item, idx) => (
                  <tr key={idx} style={{ borderBottom: '1px solid #1e3a5f', fontSize: '15px' }}>
                     <td style={{ padding: '24px 16px', fontWeight: '800' }}>{item.name}<div style={{ fontSize: '11px', color: '#445163', marginTop: '4px' }}>{item.doc}</div></td>
                     <td style={{ padding: '24px 16px' }}><div style={{ background: '#1e3a5f66', padding: '6px 16px', borderRadius: '20px', fontSize: '10px', fontWeight: '900' }}>GOV.SA / ELM</div></td>
                     <td style={{ padding: '24px 16px', color: '#f87171', fontWeight: '800' }}>{item.expiry}</td>
                     <td style={{ padding: '24px 16px' }}>
                        <button style={{ background: '#1e3a5f', border: 'none', padding: '10px 20px', borderRadius: '12px', color: '#fff', fontSize: '12px', fontWeight: '800', cursor: 'pointer' }}>{item.action} →</button>
                     </td>
                  </tr>
                ))}
              </tbody>
           </table>
        </div>

      </div>
    </div>
  );
}
