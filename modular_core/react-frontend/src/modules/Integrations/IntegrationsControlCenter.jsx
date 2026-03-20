import React, { useState } from 'react';

/**
 * Integrations Control Center: Multi-tenant Connectivity Hub (Requirement Phase 3)
 * High-quality UI for Google, Zapier, Saudi platforms, and WhatsApp connections.
 */
export default function IntegrationsControlCenter() {
  const [activeTab, setActiveTab] = useState('productivity');

  const INTEGRATIONS = {
    productivity: [
      { id: 'google', name: 'Google Workspace', icon: '📩', desc: 'Sync Gmail, Calendar, and Drive contacts.', state: 'connected' },
      { id: 'slack', name: 'Slack for Teams', icon: '💬', desc: 'Push lead notifications to channels.', state: 'available' }
    ],
    automation: [
      { id: 'zapier', name: 'Zapier (No-code)', icon: '⚡', desc: 'Connect to 5,000+ apps via REST hooks.', state: 'available' },
      { id: 'make', name: 'Make.com', icon: '🛠️', desc: 'Build complex automation scenarios.', state: 'available' }
    ],
    saudi_hub: [
      { id: 'muqeem', name: 'Muqeem (ELM)', icon: '🇸🇦', desc: 'Government compliance for expatriates.', state: 'available' },
      { id: 'gosi', name: 'GOSI Portal', icon: '📑', desc: 'Social insurance and Saudization sync.', state: 'available' }
    ],
    omnichannel: [
       { id: 'whatsapp_meta', name: 'WhatsApp Business', icon: '🟢', desc: 'Official Meta API for high-volume sales.', state: 'connected' },
       { id: 'telegram', name: 'Telegram Bot', icon: '🔵', desc: 'Automated 24/7 client support.', state: 'available' }
    ]
  };

  const handleConnect = (id) => {
    if (id === 'google') window.location.href = '/api/v1/auth/google';
    else alert(`🚀 Redirecting to ${id} authorization flow...`);
  };

  return (
    <div style={{ minHeight: '100vh', background: '#0b1628', color: '#fff', padding: '60px' }}>
      <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
        
        {/* Header Section */}
        <div style={{ marginBottom: '60px' }}>
           <h1 style={{ fontSize: '42px', fontWeight: '900', letterSpacing: '-0.03em', marginBottom: '12px' }}>Integration Hub.</h1>
           <p style={{ fontSize: '18px', color: '#64748b', maxWidth: '600px' }}>Power your ecosystem with seamless data bridges to your favorite tools and regional platforms.</p>
        </div>

        {/* Categories Tab Bar */}
        <div style={{ display: 'flex', gap: '32px', borderBottom: '1px solid #1e3a5f', marginBottom: '40px' }}>
           {Object.keys(INTEGRATIONS).map(cat => (
             <div key={cat} onClick={() => setActiveTab(cat)} style={{ paddingBottom: '16px', cursor: 'pointer', borderBottom: activeTab === cat ? '3px solid #3b82f6' : 'none', color: activeTab === cat ? '#fff' : '#475569', fontWeight: '800', textTransform: 'capitalize', transition: '0.2s' }}>
                {cat.replace('_', ' ')}
             </div>
           ))}
        </div>

        {/* Integration Cards Grid */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(360px, 1fr))', gap: '24px' }}>
           {INTEGRATIONS[activeTab].map(item => (
             <div key={item.id} style={{ background: '#0d1a30', borderRadius: '24px', border: '1.5px solid #1e3a5f', padding: '32px', display: 'flex', flexDirection: 'column', justifyContent: 'space-between', transition: '0.3s cubic-bezier(0.19, 1, 0.22, 1)', cursor: 'default' }} onMouseEnter={(e) => e.currentTarget.style.borderColor = '#3b82f6'} onMouseLeave={(e) => e.currentTarget.style.borderColor = '#1e3a5f'}>
                <div>
                   <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '24px' }}>
                      <div style={{ fontSize: '48px' }}>{item.icon}</div>
                      <div style={{ background: item.state === 'connected' ? '#065f4633' : '#1e293b', border: '1px solid', borderColor: item.state === 'connected' ? '#059669' : '#334155', color: item.state === 'connected' ? '#10b981' : '#94a3b8', padding: '4px 12px', borderRadius: '20px', fontSize: '11px', fontWeight: '900', textTransform: 'uppercase' }}>
                         {item.state}
                      </div>
                   </div>
                   <h3 style={{ fontSize: '24px', fontWeight: '800', marginBottom: '12px' }}>{item.name}</h3>
                   <p style={{ color: '#475569', fontSize: '14px', lineHeight: '1.6', marginBottom: '32px' }}>{item.desc}</p>
                </div>

                <div style={{ display: 'flex', gap: '12px' }}>
                   {item.state === 'connected' ? (
                     <>
                        <button style={{ flex: 1, padding: '12px', border: '1.5px solid #1e3a5f', background: 'transparent', color: '#fff', borderRadius: '12px', fontWeight: '800', cursor: 'pointer' }}>Settings</button>
                        <button style={{ flex: 1, padding: '12px', border: 'none', background: '#991b1b22', color: '#f87171', borderRadius: '12px', fontWeight: '800', cursor: 'pointer' }}>Disconnect</button>
                     </>
                   ) : (
                     <button onClick={() => handleConnect(item.id)} style={{ width: '100%', padding: '14px', border: 'none', background: '#3b82f6', color: '#fff', borderRadius: '14px', fontWeight: '900', cursor: 'pointer', transition: '0.2s' }}>Install Expansion →</button>
                   )}
                </div>
             </div>
           ))}
        </div>

      </div>
    </div>
  );
}
