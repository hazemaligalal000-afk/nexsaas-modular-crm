import React, { useState } from 'react';

// Mocked editor components for infrastructure setup (In production, replace with easy-email-editor)
const EmailBuilderLayout = ({ children, toolbar }) => (
  <div style={{ height: '100vh', display: 'flex', flexDirection: 'column', background: '#0b1628', color: '#e2e8f0' }}>
    <div style={{ padding: '16px 24px', background: '#0d1a30', borderBottom: '1px solid #1e3a5f', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
      {toolbar}
    </div>
    <div style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
      {children}
    </div>
  </div>
);

export default function EmailDesigner({ companyCode = '01', templateId = null }) {
  const [activeTab, setActiveTab] = useState('design');
  const [saving, setSaving] = useState(false);

  // Brand Context (from company settings)
  const brand = {
    primary: '#3b82f6',
    font: 'Inter, sans-serif',
    logo: 'https://placehold.co/200x60?text=Globalize'
  };

  const MERGE_TAGS = {
    'Contact': ['first_name', 'last_name', 'email', 'phone'],
    'Deal': ['title', 'value', 'stage'],
    'Invoice': ['number', 'date', 'amount'],
    'System': ['date', 'year']
  };

  const handleSave = () => {
    setSaving(true);
    setTimeout(() => setSaving(false), 800);
  };

  return (
    <EmailBuilderLayout
      toolbar={
        <>
          <div>
            <h2 style={{ margin: 0, fontSize: '18px', fontWeight: '800' }}>🏗️ Template Designer</h2>
            <p style={{ margin: 0, fontSize: '12px', color: '#64748b' }}>Company: {companyCode} | Campaign: Summer Promo</p>
          </div>
          <div style={{ display: 'flex', gap: '8px' }}>
            <button style={{ padding: '8px 16px', borderRadius: '8px', background: 'transparent', border: '1px solid #1e3a5f', color: '#fff', fontSize: '13px', cursor: 'pointer' }}>👁️ Preview</button>
            <button style={{ padding: '8px 16px', borderRadius: '8px', background: 'transparent', border: '1px solid #1e3a5f', color: '#fff', fontSize: '13px', cursor: 'pointer' }}>📤 Send Test</button>
            <button onClick={handleSave} style={{ padding: '8px 24px', borderRadius: '8px', background: '#1d4ed8', border: 'none', color: '#fff', fontWeight: '800', fontSize: '13px', cursor: 'pointer' }}>
               {saving ? 'Saving...' : '💾 Save Template'}
            </button>
          </div>
        </>
      }
    >
      {/* Sidebar - Blocks & Merge Tags */}
      <div style={{ width: '300px', background: '#0d1a30', borderRight: '1px solid #1e3a5f', padding: '24px', overflowY: 'auto' }}>
         <h4 style={{ margin: '0 0 16px', fontSize: '11px', color: '#475569', textTransform: 'uppercase', fontWeight: '800' }}>Blocks</h4>
         <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', marginBottom: '32px' }}>
            {['Text', 'Image', 'Button', 'Divider', 'Spacer', 'Social'].map(block => (
               <div key={block} style={{ padding: '12px', background: '#0b1628', borderRadius: '10px', border: '1px solid #1e3a5f', textAlign: 'center', cursor: 'pointer' }}>
                  <div style={{ fontSize: '18px' }}>📦</div>
                  <div style={{ fontSize: '11px', fontWeight: '700', marginTop: '4px' }}>{block}</div>
               </div>
            ))}
         </div>

         <h4 style={{ margin: '0 0 16px', fontSize: '11px', color: '#475569', textTransform: 'uppercase', fontWeight: '800' }}>Merge Fields</h4>
         {Object.entries(MERGE_TAGS).map(([cat, tags]) => (
            <div key={cat} style={{ marginBottom: '16px' }}>
               <div style={{ fontSize: '12px', fontWeight: '800', color: '#60a5fa', marginBottom: '8px' }}>{cat}</div>
               <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                  {tags.map(t => (
                    <span key={t} style={{ fontSize: '10px', background: '#1e293b', padding: '2px 8px', borderRadius: '100px', color: '#94a3b8', border: '1px solid #334155' }}>
                       {`{{${cat.toLowerCase()}.${t}}}`}
                    </span>
                  ))}
               </div>
            </div>
         ))}
      </div>

      {/* Main Canvas */}
      <div style={{ flex: 1, padding: '40px', overflowY: 'auto', display: 'flex', justifyContent: 'center' }}>
         <div style={{ width: '600px', background: '#ffffff', minHeight: '800px', borderRadius: '1px', boxShadow: '0 20px 50px rgba(0,0,0,0.5)', overflow: 'hidden' }}>
             {/* Header Snapshot from Brand */}
             <div style={{ background: brand.primary, padding: '20px 30px' }}>
                <img src={brand.logo} alt="Logo" style={{ height: '40px' }} />
             </div>
             
             {/* Editor Area */}
             <div style={{ padding: '40px 60px', color: '#1a1a1a' }}>
                <h1 style={{ margin: '0 0 20px', fontFamily: brand.font }}>Exciting news is here!</h1>
                <p style={{ lineHeight: 1.6, fontSize: '16px', marginBottom: '24px' }}>
                  Hello <strong>{`{{contact.first_name}}`}</strong>, we are thrilled to share that your deal for <strong>{`{{deal.title}}`}</strong> 
                  has reached a new stage. Check out the details below:
                </p>
                
                <div style={{ padding: '24px', background: '#f8fafc', borderRadius: '12px', border: '1px solid #e2e8f0', marginBottom: '24px' }}>
                   <div style={{ fontSize: '11px', color: '#64748b', textTransform: 'uppercase', fontWeight: '800', marginBottom: '8px' }}>Deal Value</div>
                   <div style={{ fontSize: '24px', fontWeight: '900', color: '#1e293b' }}>{`{{deal.value}} {{deal.currency}}`}</div>
                </div>

                <div style={{ textAlign: 'center' }}>
                   <button style={{ background: brand.primary, color: '#fff', border: 'none', padding: '14px 28px', borderRadius: '8px', fontSize: '16px', fontWeight: '800', cursor: 'pointer' }}>View Details Online</button>
                </div>
             </div>

             {/* Footer Snapshot from Brand */}
             <div style={{ background: '#0f172a', padding: '30px 40px', textAlign: 'center', color: '#94a3b8', fontSize: '12px' }}>
                <p>© 2026 Globalize Group Egypt. All rights reserved.</p>
                <p>123 Business Avenue, New Cairo, Egypt</p>
                <div style={{ marginTop: '16px' }}>
                   <a href="#" style={{ color: '#60a5fa', textDecoration: 'none' }}>Unsubscribe</a> | <a href="#" style={{ color: '#60a5fa', textDecoration: 'none' }}>Privacy Policy</a>
                </div>
             </div>
         </div>
      </div>

      {/* Settings Panel */}
      <div style={{ width: '300px', background: '#0d1a30', borderLeft: '1px solid #1e3a5f', padding: '24px' }}>
         <h4 style={{ margin: '0 0 20px', fontSize: '11px', color: '#475569', textTransform: 'uppercase', fontWeight: '800' }}>Subject & Preheader</h4>
         <div style={{ marginBottom: '16px' }}>
            <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '6px' }}>Subject Line</label>
            <input type="text" defaultValue="Update on your deal: {{deal.title}}" style={{ width: '100%', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '8px', padding: '10px', color: '#fff', fontSize: '13px' }} />
         </div>
         <div>
            <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '6px' }}>Preview Text</label>
            <textarea defaultValue="We have some exciting news regarding your latest transaction with us..." style={{ width: '100%', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '8px', padding: '10px', color: '#fff', fontSize: '13px', height: '80px', resize: 'none' }} />
         </div>

         <div style={{ marginTop: '32px', padding: '16px', background: '#3b82f611', border: '1px solid #3b82f644', borderRadius: '12px' }}>
            <div style={{ fontWeight: '800', fontSize: '12px', color: '#60a5fa', marginBottom: '8px' }}>🤖 AI Smart Suggest</div>
            <p style={{ fontSize: '11px', color: '#919ea0', lineHeight: 1.4, margin: 0 }}>
               "Based on your deal type, I recommend adding a clear Call-to-Action button to the client portal for faster closing."
            </p>
         </div>
      </div>
    </EmailBuilderLayout>
  );
}
