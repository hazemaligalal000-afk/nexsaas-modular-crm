import React, { useState } from 'react';

const EMPLOYEES = [
  { id: '1010123456', name: 'أحمد علي', IBAN: 'SA039000012345678901', amount: '12400.00', month: '2026-03', basic: '9000.00', housing: '3000.00', other: '400.00', deductions: '0.00', status: 'Ready' },
  { id: '2010987654', name: 'Sara Smith', IBAN: 'SA241000087654321098', amount: '8500.00', month: '2026-03', basic: '6000.00', housing: '2000.00', other: '500.00', deductions: '0.00', status: 'Missing IBAN' },
  { id: '1044556677', name: 'خالد محمد', IBAN: 'SA984000044556677889', amount: '10200.00', month: '2026-03', basic: '7500.00', housing: '2500.00', other: '200.00', deductions: '0.00', status: 'Ready' },
];

export default function MudadWPS() {
  const [activeMonth, setActiveMonth] = useState('2026-03');
  const [validationResult, setValidationResult] = useState(null);

  const validate = () => {
    setValidationResult({ errors: 1, warnings: 0, compliance: '94%' });
  };

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '28px' }}>
        <div>
          <h1 style={{ margin: '0 0 6px', fontSize: '26px', fontWeight: '900' }}>💳 Mudad / WPS Management</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Monthly payroll submission (Wage Protection System) per MHRSD standards</p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button onClick={validate} style={{ padding: '10px 20px', background: '#0f2040', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#60a5fa', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>Run WPS Validator</button>
          <button style={{ padding: '10px 20px', background: '#1d4ed8', border: 'none', borderRadius: '10px', color: '#fff', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>💾 Generate WPS File</button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '24px' }}>
        {[
          { label: 'Employees in File', value: '42', color: '#fff' },
          { label: 'Total Net Salary', value: 'SAR 428.4k', color: '#10b981' },
          { label: 'WPS Deadline', value: 'Apr 10 (18d)', color: '#f59e0b' },
          { label: 'IBAN Coverage', value: '98%', color: '#3b82f6' },
        ].map(stat => (
          <div key={stat.label} style={{ background: '#0d1a30', borderRadius: '12px', padding: '20px', border: '1px solid #0f2040' }}>
            <div style={{ fontSize: '11px', color: '#475569', fontWeight: '700', textTransform: 'uppercase', marginBottom: '8px' }}>{stat.label}</div>
            <div style={{ fontSize: '22px', fontWeight: '900', color: stat.color }}>{stat.value}</div>
          </div>
        ))}
      </div>

      {validationResult && (
        <div style={{ background: validationResult.errors > 0 ? '#ef444411' : '#10b98111', border: `1px solid ${validationResult.errors > 0 ? '#ef444422' : '#10b98122'}`, borderRadius: '16px', padding: '24px', marginBottom: '28px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <div style={{ fontWeight: '800', fontSize: '18px', color: validationResult.errors > 0 ? '#ef4444' : '#10b981' }}>
              {validationResult.errors > 0 ? '❌ Validation Errors Found' : '✅ WPS File Validated'}
            </div>
            <div style={{ fontSize: '14px', color: '#94a3b8', marginTop: '4px' }}>1 employee requires IBAN update before submission. Compliance Rate if submitted now: <strong>{validationResult.compliance}</strong></div>
          </div>
          <button style={{ padding: '8px 16px', background: '#0b1628', color: '#fff', border: '1px solid #1e3a5f', borderRadius: '8px', fontSize: '12px' }}>Fix Errors</button>
        </div>
      )}

      <div style={{ background: '#0d1a30', borderRadius: '16px', border: '1px solid #0f2040', overflow: 'hidden' }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid #0f2040', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '800' }}>WPS Line Items for {activeMonth}</h3>
          <div style={{ display: 'flex', gap: '8px' }}>
            <span style={{ fontSize: '11px', background: '#0b1628', border: '1px solid #1e3a5f', padding: '4px 8px', borderRadius: '6px' }}>Showing: Bank Transfer</span>
          </div>
        </div>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ textAlign: 'left', background: '#0b1628' }}>
              {['Employee ID', 'Name / IBAN', 'Basic', 'Housing', 'Total Net', 'Status'].map(h => (
                <th key={h} style={{ padding: '14px 20px', fontSize: '11px', color: '#475569', fontWeight: '600', textTransform: 'uppercase' }}>{h}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {EMPLOYEES.map((e, i) => (
              <tr key={i} style={{ borderTop: i > 0 ? '1px solid #0f2040' : 'none' }}>
                <td style={{ padding: '16px 20px', fontWeight: '700', fontFamily: 'monospace' }}>{e.id}</td>
                <td style={{ padding: '16px 20px' }}>
                  <div style={{ fontSize: '14px', fontWeight: '700' }}>{e.name}</div>
                  <div style={{ fontSize: '11px', color: '#475569', fontFamily: 'monospace' }}>{e.IBAN}</div>
                </td>
                <td style={{ padding: '16px 20px', fontSize: '14px' }}>{e.basic}</td>
                <td style={{ padding: '16px 20px', fontSize: '14px' }}>{e.housing}</td>
                <td style={{ padding: '16px 20px', fontSize: '14px', fontWeight: '800', color: '#05ff91' }}>{e.amount}</td>
                <td style={{ padding: '16px 20px' }}>
                  <span style={{ 
                    fontSize: '10px', height: 'max-content', padding: '3px 8px', borderRadius: '4px', textTransform: 'uppercase', fontWeight: '800',
                    background: e.status === 'Ready' ? '#10b98111' : '#ef444411',
                    color: e.status === 'Ready' ? '#10b981' : '#ef4444',
                    border: `1px solid ${e.status === 'Ready' ? '#10b98122' : '#ef444422'}`
                  }}>{e.status}</span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
