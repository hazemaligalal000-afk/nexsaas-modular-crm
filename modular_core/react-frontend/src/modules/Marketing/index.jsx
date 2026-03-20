import React, { useState } from 'react';

const CAMPAIGNS = [
  { id: 1, name: 'SaaS Launch Promo', type: 'Email', status: 'Running', sent: 12400, color: '#3b82f6', openRate: '24.5%', clicks: '3.1%' },
  { id: 2, name: 'Enterprise Retargeting', type: 'Email', status: 'Paused', sent: 5200, color: '#8b5cf6', openRate: '18.2%', clicks: '1.9%' },
  { id: 3, name: 'Summer Special 2026', type: 'WhatsApp', status: 'Scheduled', sent: 0, color: '#10b981', openRate: '-', clicks: '-' },
];

const AUTOMATIONS = [
  { id: 1, name: 'New Lead Nurture', trigger: 'Lead Created', steps: 4, active: true },
  { id: 2, name: 'Re-engagement Flow', trigger: 'Inactive 14d', steps: 3, active: false },
  { id: 3, name: 'High Intent Alert', trigger: 'Score > 80', steps: 2, active: true },
];

export default function MarketingModule() {
  const [view, setView] = useState('campaigns'); // campaigns, automations, templates
  const [isCreating, setIsCreating] = useState(false);

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '32px' }}>
        <div>
          <h1 style={{ margin: '0 0 6px', fontSize: '26px', fontWeight: '900' }}>📣 Marketing Hub</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Behavior-triggered campaigns and visual automation flows</p>
        </div>
        <button onClick={() => setIsCreating(true)} style={{ padding: '12px 24px', background: '#1d4ed8', border: 'none', borderRadius: '12px', color: '#fff', fontWeight: '700', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}>
          <span>+</span> Create {view === 'campaigns' ? 'Campaign' : 'Sequence'}
        </button>
      </div>

      <div style={{ display: 'flex', gap: '8px', marginBottom: '24px', background: '#0d1a30', padding: '6px', borderRadius: '14px', width: 'fit-content', border: '1.5px solid #0f2040' }}>
        {['campaigns', 'automations', 'templates'].map(tab => (
          <button key={tab} onClick={() => setView(tab)} style={{ padding: '8px 20px', background: view === tab ? '#1e293b' : 'transparent', border: 'none', borderRadius: '10px', color: view === tab ? '#fff' : '#64748b', fontSize: '13px', fontWeight: '700', cursor: 'pointer', textTransform: 'capitalize' }}>{tab}</button>
        ))}
      </div>

      {view === 'campaigns' && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '20px' }}>
          {CAMPAIGNS.map(c => (
            <div key={c.id} style={{ background: '#0d1a30', borderRadius: '20px', border: '1px solid #1e3a5f', padding: '24px', position: 'relative', overflow: 'hidden' }}>
              <div style={{ position: 'absolute', top: 0, left: 0, bottom: 0, width: '4px', background: c.color }} />
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px' }}>
                <span style={{ fontSize: '11px', fontWeight: '800', padding: '4px 10px', borderRadius: '100px', background: `${c.color}11`, color: c.color }}>{c.type}</span>
                <span style={{ fontSize: '12px', color: '#64748b' }}>{c.status}</span>
              </div>
              <h3 style={{ margin: '0 0 20px', fontSize: '18px', fontWeight: '800' }}>{c.name}</h3>
              
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '24px' }}>
                <div>
                   <div style={{ fontSize: '11px', color: '#475569', textTransform: 'uppercase', fontWeight: '700', marginBottom: '4px' }}>Open Rate</div>
                   <div style={{ fontSize: '18px', fontWeight: '900', color: '#fff' }}>{c.openRate}</div>
                </div>
                <div>
                   <div style={{ fontSize: '11px', color: '#475569', textTransform: 'uppercase', fontWeight: '700', marginBottom: '4px' }}>CTR</div>
                   <div style={{ fontSize: '18px', fontWeight: '900', color: '#fff' }}>{c.clicks}</div>
                </div>
              </div>

              <div style={{ display: 'flex', gap: '10px' }}>
                <button style={{ flex: 1, padding: '10px', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#60a5fa', fontSize: '12px', fontWeight: '700', cursor: 'pointer' }}>View Stats</button>
                <button style={{ padding: '10px', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#fff', fontSize: '12px', fontWeight: '700', cursor: 'pointer' }}>Edit</button>
              </div>
            </div>
          ))}
        </div>
      )}

      {view === 'automations' && (
        <div style={{ background: '#0d1a30', borderRadius: '20px', border: '1.5px solid #0f2040', overflow: 'hidden' }}>
           <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#0b1628', textAlign: 'left' }}>
                  {['Flow Name', 'Trigger', 'Steps', 'Status', 'Actions'].map(h => (
                    <th key={h} style={{ padding: '16px 24px', fontSize: '11px', color: '#475569', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {AUTOMATIONS.map((a, i) => (
                  <tr key={a.id} style={{ borderTop: '1px solid #0f2040' }}>
                    <td style={{ padding: '20px 24px', fontWeight: '700' }}>{a.name}</td>
                    <td style={{ padding: '20px 24px' }}>
                       <span style={{ fontSize: '12px', background: 'rgba(59,130,246,0.1)', color: '#3b82f6', padding: '4px 10px', borderRadius: '8px' }}>⚡ {a.trigger}</span>
                    </td>
                    <td style={{ padding: '20px 24px', fontSize: '14px' }}>{a.steps} Blocks</td>
                    <td style={{ padding: '20px 24px' }}>
                       <div style={{ width: '40px', height: '20px', background: a.active ? '#10b981' : '#1e293b', borderRadius: '20px', position: 'relative', cursor: 'pointer' }}>
                          <div style={{ width: '14px', height: '14px', background: '#fff', borderRadius: '50%', position: 'absolute', top: '3px', left: a.active ? '23px' : '3px', transition: '0.2s' }} />
                       </div>
                    </td>
                    <td style={{ padding: '20px 24px' }}>
                       <button style={{ color: '#60a5fa', background: 'none', border: 'none', fontWeight: '700', cursor: 'pointer', fontSize: '13px' }}>Edit Flow</button>
                    </td>
                  </tr>
                ))}
              </tbody>
           </table>
        </div>
      )}
    </div>
  );
}
