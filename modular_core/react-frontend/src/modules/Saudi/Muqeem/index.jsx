import React, { useState } from 'react';

const EXPATS = [
  { id: '2010987654', name: 'Sara Smith', iqama: '2233445566', expiry: '2026-04-15', status: 'Expiring Soon', days: 26, wallet: 'SAR 4,500' },
  { id: '2010112233', name: 'John Doe', iqama: '2244556677', expiry: '2027-02-10', status: 'Valid', days: 327, wallet: 'SAR 0' },
  { id: '2010445566', name: 'Zaid Al-Hariri', iqama: '2255667788', expiry: '2026-03-22', status: 'CRITICAL', days: 2, wallet: 'SAR 12,000' },
  { id: '2010889900', name: 'Maria Garcia', iqama: '2266778899', expiry: '2026-03-10', status: 'Expired', days: -10, wallet: 'SAR 400' },
];

export default function MuqeemTracker() {
  const [filter, setFilter] = useState('All');

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '28px' }}>
        <div>
          <h1 style={{ margin: '0 0 6px', fontSize: '26px', fontWeight: '900' }}>🛂 Muqeem / Iqama Tracker</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Expatriate residency management, visa renewal alerts, and MOI wallet tracking</p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button style={{ padding: '10px 20px', background: '#0f2040', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#60a5fa', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>Muqeem Bulk Sync</button>
          <button style={{ padding: '10px 20px', background: '#1d4ed8', border: 'none', borderRadius: '10px', color: '#fff', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>+ Request New Visa</button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '24px' }}>
        {[
          { label: 'Expatriate Employees', value: '42', color: '#fff' },
          { label: 'Expired / Critical', value: '2', color: '#ef4444' },
          { label: 'Total Fine Exposure', value: 'SAR 5,000', color: '#f59e0b' },
          { label: 'MOI Wallet Balance', value: 'SAR 16,900', color: '#10b981' },
        ].map(stat => (
          <div key={stat.label} style={{ background: '#0d1a30', borderRadius: '12px', padding: '20px', border: '1px solid #0f2040' }}>
            <div style={{ fontSize: '11px', color: '#475569', fontWeight: '700', textTransform: 'uppercase', marginBottom: '8px' }}>{stat.label}</div>
            <div style={{ fontSize: '22px', fontWeight: '900', color: stat.color }}>{stat.value}</div>
          </div>
        ))}
      </div>

      <div style={{ background: '#0d1a30', borderRadius: '16px', border: '1px solid #0f2040', overflow: 'hidden' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ textAlign: 'left', background: '#0b1628' }}>
              {['Employee IQAMA', 'Name', 'Expiry Date', 'Validity', 'Wallet Status', 'Action'].map(h => (
                <th key={h} style={{ padding: '14px 20px', fontSize: '11px', color: '#475569', fontWeight: '600', textTransform: 'uppercase' }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {EXPATS.map((e, i) => (
              <tr key={i} style={{ borderTop: i > 0 ? '1px solid #0f2040' : 'none' }}>
                <td style={{ padding: '20px', fontWeight: '700', fontFamily: 'monospace' }}>{e.iqama}</td>
                <td style={{ padding: '20px' }}>
                   <div style={{ fontWeight: '700', fontSize: '14px', color: '#f1f5f9' }}>{e.name}</div>
                   <div style={{ fontSize: '11px', color: '#475569' }}>Nationality: Global</div>
                </td>
                <td style={{ padding: '20px', fontSize: '14px' }}>{e.expiry}</td>
                <td style={{ padding: '20px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span style={{ 
                      fontSize: '11px', fontWeight: '800', padding: '4px 10px', borderRadius: '6px',
                      background: e.days < 0 ? '#ef444411' : e.days < 30 ? '#ef444422' : e.days < 90 ? '#f59e0b11' : '#10b98111',
                      color: e.days < 0 ? '#ef4444' : e.days < 90 ? '#f59e0b' : '#10b981'
                    }}>{e.days < 0 ? 'EXPIRED' : `${e.days} days remaining`}</span>
                  </div>
                </td>
                <td style={{ padding: '20px', fontSize: '14px', color: '#94a3b8' }}>{e.wallet}</td>
                <td style={{ padding: '20px' }}>
                  <button style={{ 
                    padding: '8px 16px', background: e.days < 60 ? '#3b82f6' : 'transparent', border: '1px solid #1e3a5f', borderRadius: '10px', color: e.days < 60 ? '#fff' : '#60a5fa', fontWeight: '700', fontSize: '12px', cursor: 'pointer' 
                  }}>Renew Now</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
