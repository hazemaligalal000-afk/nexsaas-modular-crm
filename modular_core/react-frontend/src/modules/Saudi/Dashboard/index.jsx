import React, { useState } from 'react';

const COMPLIANCE_STATUS = [
  { id: 'zatca',   name: 'ZATCA',    title: 'E-Invoicing', status: 'Compliant', lastRun: 'Today', icon: '📝', color: '#05ff91', msg: 'Wave 23 ready. Next reporting due in 24h.' },
  { id: 'gosi',    name: 'GOSI',     title: 'Social Insurance', status: 'Paid', lastRun: 'Mar 15', icon: '🏦', color: '#3b82f6', msg: 'All contributions settled. Next due Apr 15.' },
  { id: 'qiwa',    name: 'Qiwa',     title: 'Labor Hub', status: 'Warning', lastRun: 'Today', icon: '👥', color: '#f59e0b', msg: 'Nitaqat: Yellow Band. Saudization at 28% (30% target).' },
  { id: 'mudad',   name: 'Mudad/WPS',title: 'Wage Protection', status: 'Submitted', lastRun: 'Mar 10', icon: '💳', color: '#10b981', msg: 'WPS March file accepted. Compliance: 94%.' },
  { id: 'muqeem',  name: 'Muqeem',   title: 'Visa & Residency', status: 'Alert', lastRun: '6h ago', icon: '🛂', color: '#ef4444', msg: '3 Iqamas expire in < 60 days. Wallet balance low.' },
  { id: 'sbc',     name: 'SBC/CR',   title: 'Commerce Registry', status: 'Valid', lastRun: 'Annual', icon: '🏢', color: '#8b5cf6', msg: 'CR #1010123... Expires Dec 2026.' },
];

const UPCOMING_DEADLINES = [
  { date: 'Apr 10', task: 'Monthly WPS Submission', portal: 'Mudad', urgency: 'Medium' },
  { date: 'Apr 15', task: 'GOSI Monthly Contribution', portal: 'GOSI', urgency: 'High' },
  { date: 'Apr 25', task: 'VAT Return (Q1 2026)', portal: 'ZATCA', urgency: 'Critical' },
  { date: 'May 05', task: 'Work Permit Renewals (5)', portal: 'Qiwa', urgency: 'Low' },
];

export default function SaudiComplianceDashboard() {
  const [activePortal, setActivePortal] = useState(null);

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '32px' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '6px' }}>
            <span style={{ fontSize: '24px' }}>🇸🇦</span>
            <h1 style={{ margin: 0, fontSize: '26px', fontWeight: '900' }}>Saudi Compliance Command Center</h1>
          </div>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Multi-platform integration hub: ZATCA, MHRSD, MOI, GOSI & SBC</p>
        </div>
        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ background: '#05ff9111', border: '1px solid #05ff9122', padding: '10px 20px', borderRadius: '12px', textAlign: 'right' }}>
            <div style={{ fontSize: '11px', color: '#05ff91', fontWeight: '700', textTransform: 'uppercase' }}>Fine Exposure</div>
            <div style={{ fontSize: '18px', fontWeight: '900', color: '#05ff91' }}>SAR 0.00</div>
          </div>
          <button style={{ padding: '10px 24px', background: '#1d4ed8', border: 'none', borderRadius: '12px', color: '#fff', fontWeight: '700', cursor: 'pointer' }}>+ New Onboarding</button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '20px', marginBottom: '28px' }}>
        {COMPLIANCE_STATUS.map(app => (
          <div key={app.id} style={{ background: '#0d1a30', borderRadius: '16px', border: `1.5px solid ${activePortal === app.id ? app.color : '#0f2040'}`, padding: '24px', position: 'relative', cursor: 'pointer', transition: 'transform 0.2s' }}
            onClick={() => setActivePortal(app.id)}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
              <div style={{ width: '44px', height: '44px', background: `${app.color}11`, color: app.color, borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px' }}>{app.icon}</div>
              <span style={{ fontSize: '11px', fontWeight: '800', padding: '4px 10px', borderRadius: '100px', background: `${app.color}11`, color: app.color, border: `1px solid ${app.color}22` }}>{app.status}</span>
            </div>
            <h3 style={{ margin: '0 0 4px', fontSize: '17px', fontWeight: '800' }}>{app.name}</h3>
            <div style={{ fontSize: '12px', color: '#475569', marginBottom: '12px' }}>{app.title} — Last checked: {app.lastRun}</div>
            <p style={{ margin: 0, fontSize: '13px', color: '#94a3b8', lineHeight: '1.5' }}>{app.msg}</p>
          </div>
        ))}
      </div>
    </div>
  );
}
