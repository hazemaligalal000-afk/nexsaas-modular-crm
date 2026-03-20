import React, { useState } from 'react';

export default function EmailBrandSettings({ companyCode = '01' }) {
  const [activeTab, setActiveTab] = useState('visual'); // visual, sender, notifications
  const [saving, setSaving] = useState(false);

  // Mock data for initial implementation
  const [settings, setSettings] = useState({
    companyNameEn: 'Globalize Group',
    color_primary: '#1d4ed8',
    color_secondary: '#1e3a5f',
    color_accent: '#f59e0b',
    color_bg: '#f8fafc',
    color_text: '#1e293b',
    color_footer_bg: '#0f172a',
    color_footer_text: '#f1f5f9',
    font_family: 'Inter, Arial, sans-serif',
    sender_name: 'Globalize Hub',
    sender_email: 'noreply@globalize.info',
    website_url: 'https://globalize.info',
    smtp_provider: 'sendgrid'
  });

  const handleSave = () => {
    setSaving(true);
    setTimeout(() => { setSaving(false); alert('Settings Saved! 🎉'); }, 800);
  };

  return (
    <div style={{ padding: '32px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '32px' }}>
        <div>
          <h1 style={{ margin: '0 0 8px', fontSize: '28px', fontWeight: '900' }}>🎨 Email Branding & Identity</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Configure visual themes, logos, and routing for Company: {companyCode}</p>
        </div>
        <button onClick={handleSave} style={{ padding: '12px 32px', background: settings.color_primary, border: 'none', borderRadius: '12px', color: '#fff', fontWeight: '800', cursor: 'pointer', transition: '0.2s', boxShadow: `0 4px 20px ${settings.color_primary}33` }}>
          {saving ? 'Applying...' : 'Save & Publish Brand'}
        </button>
      </div>

      <div style={{ display: 'flex', gap: '40px' }}>
        {/* Settings Column */}
        <div style={{ width: '500px' }}>
           <div style={{ display: 'flex', gap: '8px', marginBottom: '24px', background: '#0d1a30', padding: '6px', borderRadius: '12px', border: '1px solid #1e3a5f' }}>
              {['Visual Theme', 'Sender Info', 'API Routing'].map(tab => (
                 <button key={tab} onClick={() => setActiveTab(tab.toLowerCase().split(' ')[0])} style={{ flex: 1, padding: '10px', background: (activeTab === tab.toLowerCase().split(' ')[0]) ? '#1e293b' : 'transparent', border: 'none', borderRadius: '8px', color: (activeTab === tab.toLowerCase().split(' ')[0]) ? '#fff' : '#64748b', fontSize: '13px', fontWeight: '700', cursor: 'pointer' }}>{tab}</button>
              ))}
           </div>

           {activeTab === 'visual' && (
              <div style={{ background: '#0d1a30', padding: '24px', borderRadius: '20px', border: '1px solid #1e3a5f' }}>
                 <div style={{ marginBottom: '24px' }}>
                    <label style={{ fontSize: '11px', color: '#64748b', textTransform: 'uppercase', fontWeight: '800', display: 'block', marginBottom: '12px' }}>Logo & Icon</label>
                    <div style={{ display: 'flex', gap: '12px' }}>
                       <div style={{ width: '120px', height: '120px', border: '2px dashed #1e3a5f', borderRadius: '14px', display: 'flex', justifyContent: 'center', alignItems: 'center', cursor: 'pointer' }}>
                          <span style={{ fontSize: '24px' }}>📸</span>
                       </div>
                       <div style={{ flex: 1, padding: '10px 0' }}>
                          <div style={{ fontSize: '13px', fontWeight: '700', marginBottom: '4px' }}>Primary Logo</div>
                          <div style={{ fontSize: '11px', color: '#64748b', marginBottom: '12px' }}>SVG or PNG, Max 2MB (600x200px)</div>
                          <button style={{ background: '#1e293b', border: 'none', padding: '6px 12px', borderRadius: '6px', color: '#fff', fontSize: '12px', fontWeight: '700', cursor: 'pointer' }}>Upload New</button>
                       </div>
                    </div>
                 </div>

                 <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                    {[
                      { key: 'color_primary', label: 'Primary Brand' },
                      { key: 'color_secondary', label: 'Dark Secondary' },
                      { key: 'color_accent', label: 'Accent Highlight' },
                      { key: 'color_bg', label: 'Email Background' },
                      { key: 'color_text', label: 'Body Text' },
                      { key: 'color_footer_bg', label: 'Footer Base' }
                    ].map(c => (
                       <div key={c.key}>
                          <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '8px' }}>{c.label}</label>
                          <div style={{ display: 'flex', alignItems: 'center', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', overflow: 'hidden' }}>
                             <input type="color" value={settings[c.key]} onChange={e => setSettings({...settings, [c.key]: e.target.value})} style={{ width: '40px', height: '40px', border: 'none', background: 'none', cursor: 'pointer' }} />
                             <input type="text" value={settings[c.key]} readOnly style={{ border: 'none', background: 'none', color: '#fff', width: '80px', fontSize: '13px', textAlign: 'center' }} />
                          </div>
                       </div>
                    ))}
                 </div>

                 <div style={{ marginTop: '24px' }}>
                    <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '8px' }}>Typography</label>
                    <select value={settings.font_family} style={{ width: '100%', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', padding: '12px', color: '#fff', fontSize: '13px' }}>
                       <option>Inter, Arial, sans-serif</option>
                       <option>Roboto, sans-serif</option>
                       <option>Cairo, Tajawal, sans-serif (Arabic Optimized)</option>
                    </select>
                 </div>
              </div>
           )}
        </div>

        {/* Live Preview Column */}
        <div style={{ flex: 1 }}>
           <h4 style={{ margin: '0 0 16px', fontSize: '11px', color: '#475569', textTransform: 'uppercase', fontWeight: '800' }}>Live Brand Preview (Mobile)</h4>
           <div style={{ width: '375px', height: '700px', background: '#fff', borderRadius: '40px', border: '12px solid #000', margin: '0 auto', overflow: 'hidden', boxShadow: '0 30px 60px rgba(0,0,0,0.5)', position: 'relative' }}>
              <div style={{ position: 'absolute', top: 0, left: '50%', transform: 'translateX(-50%)', width: '150px', height: '30px', background: '#000', borderRadius: '0 0 20px 20px', zIndex: 10 }}></div>
              <div style={{ height: '100%', overflowY: 'auto', background: settings.color_bg }}>
                 <div style={{ background: settings.color_primary, padding: '40px 20px 20px', textAlign: 'center' }}>
                    <div style={{ fontSize: '24px', fontWeight: '900', color: '#fff' }}>NexSaaS Hub</div>
                 </div>
                 <div style={{ padding: '30px 20px', color: settings.color_text }}>
                    <h2 style={{ fontSize: '24px', fontWeight: '900', color: '#1a1a1a', margin: '0 0 16px', fontFamily: settings.font_family }}>Welcome Onboard!</h2>
                    <p style={{ lineHeight: 1.6, fontSize: '15px' }}>
                       This is a live preview of your brand theme. Your primary color is used for headers and buttons.
                    </p>
                    <div style={{ textAlign: 'center', margin: '30px 0' }}>
                       <button style={{ background: settings.color_primary, color: '#fff', padding: '14px 28px', border: 'none', borderRadius: '8px', fontWeight: '800' }}>Confirm Identity</button>
                    </div>
                 </div>
                 <div style={{ background: settings.color_footer_bg, padding: '30px 20px', textAlign: 'center', color: settings.color_footer_text, fontSize: '11px' }}>
                    <p>© 2026 {settings.companyNameEn}</p>
                    <p>{settings.sender_email}</p>
                 </div>
              </div>
           </div>
        </div>
      </div>
    </div>
  );
}
