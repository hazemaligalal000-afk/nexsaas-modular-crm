import React, { useState } from 'react';

const CONTRACTS = [
  { id: 'QW-1447-03-01', name: 'Ahmed Ali', type: 'Full-time', status: 'Approved', joined: '2025-01-10', saudization: 'Yes', salary: 'SAR 12,000' },
  { id: 'QW-1447-03-02', name: 'Sara Smith', type: 'Contractor', status: 'Pending Approval', joined: '2025-03-01', saudization: 'No', salary: 'SAR 8,500' },
  { id: 'QW-1447-03-03', name: 'Khalid Mohammed', type: 'Full-time', status: 'Approved', joined: '2024-06-15', saudization: 'Yes', salary: 'SAR 10,200' },
];

export default function QiwaManager() {
  const [activeTab, setActiveTab] = useState('Contracts');

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '28px' }}>
        <div>
          <h1 style={{ margin: '0 0 6px', fontSize: '26px', fontWeight: '900' }}>📄 Qiwa / Labor Management</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Ministry of HR labor contracts, Nitaqat Saudization bands, and work permit services</p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button style={{ padding: '10px 20px', background: '#0f2040', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#60a5fa', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>Sync Nitaqat Band</button>
          <button style={{ padding: '10px 20px', background: '#1d4ed8', border: 'none', borderRadius: '10px', color: '#fff', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>+ Register New Contract</button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '24px' }}>
        {[
          { label: 'Current Nitaqat', value: 'YELLOW', color: '#f59e0b' },
          { label: 'Saudization %', value: '28%', color: '#ef4444' },
          { label: 'Registered Contracts', value: '42', color: '#3b82f6' },
          { label: 'Pending Compliance', value: '3', color: '#8b5cf6' },
        ].map(stat => (
          <div key={stat.label} style={{ background: '#0d1a30', borderRadius: '12px', padding: '20px', border: '1px solid #0f2040' }}>
            <div style={{ fontSize: '11px', color: '#475569', fontWeight: '700', textTransform: 'uppercase', marginBottom: '8px' }}>{stat.label}</div>
            <div style={{ fontSize: '22px', fontWeight: '900', color: stat.color }}>{stat.value}</div>
          </div>
        ))}
      </div>

      {/* Contract Table */}
      <div style={{ background: '#0d1a30', borderRadius: '16px', border: '1px solid #0f2040', overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid #0f2040' }}>
          <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '800' }}>Qiwa Unified Contracts</h3>
        </div>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ textAlign: 'left', background: '#0b1628' }}>
              {['Qiwa ID', 'Employee Name', 'Type', 'Status', 'Join Date', 'Saudization', 'Monthly Salary'].map(h => (
                <th key={h} style={{ padding: '14px 20px', fontSize: '11px', color: '#475569', fontWeight: '600', textTransform: 'uppercase' }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {CONTRACTS.map((c, i) => (
              <tr key={i} style={{ borderTop: i > 0 ? '1px solid #0f2040' : 'none' }}>
                <td style={{ padding: '20px', fontWeight: '700', fontFamily: 'monospace', fontSize: '12px' }}>{c.id}</td>
                <td style={{ padding: '20px', fontWeight: '700' }}>{c.name}</td>
                <td style={{ padding: '20px', fontSize: '14px' }}>{c.type}</td>
                <td style={{ padding: '20px' }}>
                  <span style={{ 
                    fontSize: '10px', padding: '4px 10px', borderRadius: '100px', textTransform: 'uppercase', fontWeight: '800',
                    background: c.status === 'Approved' ? '#10b98111' : '#f59e0b11',
                    color: c.status === 'Approved' ? '#10b981' : '#f59e0b',
                    border: `1px solid ${c.status === 'Approved' ? '#10b98122' : '#f59e0b33'}`
                  }}>{c.status}</span>
                </td>
                <td style={{ padding: '20px', fontSize: '13px' }}>{c.joined}</td>
                <td style={{ padding: '20px' }}>
                  <span style={{ fontSize: '12px', color: c.saudization === 'Yes' ? '#05ff91' : '#475569' }}>{c.saudization === 'Yes' ? '🇸🇦 Saudi National' : 'Expatriate'}</span>
                </td>
                <td style={{ padding: '20px', fontWeight: '700' }}>{c.salary}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
