import React, { useState } from 'react';

const APP_STORE = [
  { id: 'slack',    name: 'Slack',    category: 'Communication', icon: '💬', desc: 'Sync leads, notifications, and deal updates to Slack channels.', connected: true,  color: '#4A154B' },
  { id: 'gmail',    name: 'Gmail',    category: 'Email',         icon: '📧', desc: 'Full bidirectional sync for emails and calendar events.', connected: true,  color: '#EA4335' },
  { id: 'whatsapp', name: 'WhatsApp', category: 'Support',       icon: '🟢', desc: 'Enable Omnichannel Inbox for WhatsApp Business API.', connected: false, color: '#25D366' },
  { id: 'stripe',   name: 'Stripe',   category: 'Billing',       icon: '💳', desc: 'Automated subscription billing and revenue tracking.', connected: true,  color: '#635BFF' },
  { id: 'hubspot',  name: 'HubSpot',  category: 'CRM Sync',      icon: '🧡', desc: 'Two-way sync for leads and deals between instances.', connected: false, color: '#FF7A59' },
  { id: 'jira',     name: 'Jira',     category: 'Engineering',   icon: '📘', desc: 'Link sales requests to engineering tickets.', connected: false, color: '#0052CC' },
  { id: 'zapier',   name: 'Zapier',   category: 'Automation',    icon: '⚡', desc: 'Construct complex workflows with 6,000+ apps.', connected: true,  color: '#FF4A00' },
  { id: 'metabase', name: 'Metabase', category: 'Analytics',     icon: '📊', desc: 'Embed rich business intelligence dashboards.', connected: true,  color: '#509EE3' },
];

export default function IntegrationsModule() {
  const [activeCat, setActiveCat] = useState('All');

  const filtered = APP_STORE.filter(a => activeCat === 'All' || a.category === activeCat);

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '28px' }}>
        <div>
          <h1 style={{ margin: '0 0 6px', fontSize: '26px', fontWeight: '800' }}>🔌 Integration Ecosystem</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Connect your entire tech stack to NexSaaS for unified data flow</p>
        </div>
        <div style={{ background: '#0d1a30', padding: '10px 20px', borderRadius: '12px', border: '1px solid #1e3a5f', display: 'flex', alignItems: 'center', gap: '12px' }}>
          <div style={{ fontSize: '12px', color: '#475569', fontWeight: '700' }}>API USAGE</div>
          <div style={{ fontSize: '18px', fontWeight: '800' }}>84.2k <span style={{ fontSize: '11px', color: '#334155' }}>/ 1M calls</span></div>
        </div>
      </div>

      {/* Categories */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '32px', overflowX: 'auto', paddingBottom: '10px' }}>
        {['All', 'Communication', 'Email', 'Billing', 'CRM Sync', 'Automation', 'Analytics', 'Support'].map(cat => (
          <button key={cat} onClick={() => setActiveCat(cat)}
            style={{ padding: '8px 16px', borderRadius: '100px', border: activeCat === cat ? '1px solid #3b82f6' : '1px solid #0f2040',
              background: activeCat === cat ? '#1e3a5f' : '#0d1a30',
              color: activeCat === cat ? '#60a5fa' : '#475569',
              cursor: 'pointer', fontWeight: '700', fontSize: '12px', whiteSpace: 'nowrap' }}>
            {cat}
          </button>
        ))}
      </div>

      {/* Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '20px' }}>
        {filtered.map(app => (
          <div key={app.id} style={{ background: '#0d1a30', borderRadius: '16px', border: '1px solid #0f2040', padding: '24px', display: 'flex', flexDirection: 'column', transition: 'transform 0.2s', cursor: 'default' }} className="app-card">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
              <div style={{ width: '48px', height: '48px', borderRadius: '14px', background: `${app.color}11`, color: app.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '24px', border: `1px solid ${app.color}22` }}>
                {app.icon}
              </div>
              <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                <span style={{ fontSize: '10px', background: '#0b1628', color: '#475569', padding: '3px 8px', borderRadius: '4px', fontWeight: '700', textTransform: 'uppercase' }}>{app.category}</span>
                {app.connected && <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#10b981', boxShadow: '0 0 10px #10b98188' }} title="Connected" />}
              </div>
            </div>
            
            <h3 style={{ margin: '0 0 8px', fontSize: '17px', fontWeight: '800' }}>{app.name}</h3>
            <p style={{ margin: '0 0 20px', fontSize: '13px', color: '#475569', lineHeight: '1.6', flex: 1 }}>{app.desc}</p>
            
            <div style={{ display: 'flex', gap: '10px' }}>
              <button style={{ flex: 1, padding: '10px', background: app.connected ? '#0b1628' : '#1d4ed8', border: 'none', borderRadius: '8px', color: app.connected ? '#475569' : '#fff', fontWeight: '700', fontSize: '13px', cursor: 'pointer' }}>
                {app.connected ? 'Configure' : 'Connect'}
              </button>
              {app.connected && (
                <button style={{ padding: '10px', background: 'transparent', border: '1px solid #ef444422', borderRadius: '8px', color: '#ef4444', fontSize: '13px', cursor: 'pointer' }}>🔌</button>
              )}
            </div>
          </div>
        ))}

        {/* Custom Integration Request */}
        <div style={{ background: 'rgba(5,255,145,0.02)', borderRadius: '16px', border: '1px dashed #05ff9144', padding: '24px', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', textAlign: 'center' }}>
           <div style={{ fontSize: '32px', marginBottom: '12px' }}>🛠️</div>
           <h3 style={{ margin: '0 0 6px', fontSize: '16px', fontWeight: '800' }}>Custom Hook?</h3>
           <p style={{ fontSize: '12px', color: '#475569', margin: '0 0 16px' }}>Need a custom API integration? Use our Webhook Builder or contact support.</p>
           <button style={{ padding: '8px 16px', background: 'transparent', border: '1px solid #05ff91', color: '#05ff91', borderRadius: '8px', fontSize: '12px', fontWeight: '700', cursor: 'pointer' }}>Build Webhook</button>
        </div>
      </div>
    </div>
  );
}
