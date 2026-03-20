import React, { useState } from 'react';

/**
 * Settings Page: Unified Tenant Control Panel (Requirement Phase 0-4)
 * High-quality UI for Profiles, Billing, Localization, and Security.
 */
export default function SettingsPage() {
  const [activeTab, setActiveTab] = useState('profile');
  const [isRtl, setIsRtl] = useState(false);

  const TABS = [
    { id: 'profile', label: 'Tenant Profile', icon: '🏢' },
    { id: 'billing', label: 'Global Billing', icon: '💳' },
    { id: 'localization', label: 'Localization', icon: '🌍' },
    { id: 'developers', label: 'Ecosystem & API', icon: '🔗' }
  ];

  const handleLanguageToggle = () => {
    setIsRtl(!isRtl);
    document.dir = !isRtl ? 'rtl' : 'ltr';
    alert(`🌐 Language switched to ${!isRtl ? 'Arabic (RTL)' : 'English (LTR)'}`);
  };

  return (
    <div style={{ minHeight: '100vh', background: '#0b1628', color: '#fff', padding: '60px' }}>
      <div style={{ maxWidth: '1200px', margin: '0 auto', display: 'flex', gap: '60px' }}>
        
        {/* Navigation Sidebar */}
        <div style={{ width: '280px' }}>
           <h1 style={{ fontSize: '32px', fontWeight: '900', marginBottom: '40px' }}>Settings Hub.</h1>
           <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
              {TABS.map(tab => (
                <div key={tab.id} onClick={() => setActiveTab(tab.id)} style={{ padding: '16px 24px', background: activeTab === tab.id ? '#1e3a5f' : 'transparent', borderRadius: '16px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '16px', fontWeight: '800', color: activeTab === tab.id ? '#fff' : '#64748b', transition: '0.2s' }}>
                   <span style={{ fontSize: '20px' }}>{tab.icon}</span>
                   {tab.label}
                </div>
              ))}
           </div>
        </div>

        {/* Content Section */}
        <div style={{ flex: 1, background: '#0d1a30', borderRadius: '32px', border: '1px solid #1e3a5f', padding: '60px' }}>
           
           {activeTab === 'profile' && (
             <div>
                <h2 style={{ fontSize: '28px', fontWeight: '900', marginBottom: '8px' }}>Business Profile.</h2>
                <p style={{ color: '#64748b', marginBottom: '40px' }}>Manage your organization's identity and global platform branding.</p>
                <div style={{ display: 'grid', gap: '24px', maxWidth: '500px' }}>
                   <div>
                      <label style={{ fontSize: '12px', fontWeight: '900', color: '#475569', textTransform: 'uppercase', marginBottom: '8px', display: 'block' }}>Organization Legal Name</label>
                      <input type="text" defaultValue="Al-Fatiha Holding Ltd." style={{ width: '100%', padding: '16px', background: '#0b1628', border: '1.5px solid #1e3a5f', borderRadius: '12px', color: '#fff', outline: 'none' }} />
                   </div>
                   <div>
                      <label style={{ fontSize: '12px', fontWeight: '900', color: '#475569', textTransform: 'uppercase', marginBottom: '8px', display: 'block' }}>Primary Brand Color</label>
                      <input type="color" defaultValue="#1d4ed8" style={{ width: '100px', height: '50px', border: 'none', background: 'none' }} />
                   </div>
                </div>
             </div>
           )}

           {activeTab === 'localization' && (
             <div>
                <h2 style={{ fontSize: '28px', fontWeight: '900', marginBottom: '8px' }}>Global Localization.</h2>
                <p style={{ color: '#64748b', marginBottom: '40px' }}>Set your preferred interface language and regional time zones.</p>
                <div onClick={handleLanguageToggle} style={{ padding: '32px', background: '#1e3a5f33', border: '2px solid #3b82f6', borderRadius: '24px', cursor: 'pointer', textAlign: 'center' }}>
                   <div style={{ fontSize: '32px', marginBottom: '16px' }}>{isRtl ? '🇸🇦' : '🌐'}</div>
                   <div style={{ fontWeight: '900', fontSize: '20px' }}>Switch to {isRtl ? 'English Interface' : 'Arabic Interface (RTL)'}</div>
                   <p style={{ color: '#475569', fontSize: '14px', marginTop: '8px' }}>Instant UI flipping and translation reload.</p>
                </div>
           </div>
           )}

           {activeTab === 'developers' && (
             <div>
                <h2 style={{ fontSize: '28px', fontWeight: '900', marginBottom: '8px' }}>Ecosystem & API.</h2>
                <p style={{ color: '#64748b', marginBottom: '40px' }}>Manage secret keys for Zapier, Make, and custom external integrations.</p>
                <button style={{ background: '#3b82f6', padding: '16px 32px', borderRadius: '16px', border: 'none', color: '#fff', fontWeight: '800', cursor: 'pointer' }}>+ Generate Live Production Key</button>
             </div>
           )}

        </div>

      </div>
    </div>
  );
}
