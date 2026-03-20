import React, { useState } from 'react';

const CATEGORIES = [
  { id: 'all', label: 'All Templates', icon: '📋' },
  { id: 'transactional', label: 'Transactional', icon: '⚡' },
  { id: 'marketing', label: 'Marketing', icon: '📢' },
  { id: 'finance', label: 'Finance & Invoices', icon: '🧾' },
  { id: 'hr', label: 'HR & Internal', icon: '👔' }
];

const TEMPLATES = [
  { id: 1, name: 'Welcome New Contact', category: 'transactional', company: 'Global', status: 'System', sent: 1240, open: '64.2%', color: '#3b82f6' },
  { id: 2, name: 'Invoice Receipt #2026', category: 'finance', company: 'All', status: 'System', sent: 520, open: '89.1%', color: '#10b981' },
  { id: 3, name: 'Summer Promo Flash', category: 'marketing', company: 'Digitalize', status: 'Custom', sent: 8200, open: '24.5%', color: '#f59e0b' },
  { id: 4, name: 'Lead Assignment Alert', category: 'transactional', company: 'Global', status: 'System', sent: 310, open: '98.2%', color: '#6366f1' },
  { id: 5, name: 'Monthly Newsletter', category: 'marketing', company: 'All', status: 'Custom', sent: 0, open: '-', color: '#ec4899' },
];

export default function TemplateLibrary() {
  const [category, setCategory] = useState('all');
  const [search, setSearch] = useState('');

  const filtered = TEMPLATES.filter(t => 
    (category === 'all' || t.category === category) &&
    (t.name.toLowerCase().includes(search.toLowerCase()))
  );

  return (
    <div style={{ padding: '32px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '32px' }}>
        <div>
          <h1 style={{ margin: '0 0 8px', fontSize: '28px', fontWeight: '900' }}>📚 Template Library</h1>
          <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Manage 20+ pre-built system flows and custom branded templates</p>
        </div>
        <button style={{ padding: '12px 32px', background: '#1d4ed8', border: 'none', borderRadius: '12px', color: '#fff', fontWeight: '800', cursor: 'pointer' }}>+ Create New Template</button>
      </div>

      <div style={{ display: 'flex', gap: '8px', marginBottom: '32px', flexWrap: 'wrap' }}>
        {CATEGORIES.map(cat => (
          <button key={cat.id} onClick={() => setCategory(cat.id)} style={{ padding: '8px 20px', background: (category === cat.id) ? '#3b82f6' : '#0d1a30', border: '1px solid #1e3a5f', borderRadius: '100px', color: (category === cat.id) ? '#fff' : '#64748b', fontSize: '13px', fontWeight: '700', cursor: 'pointer', transition: '0.2s' }}>{cat.icon} {cat.label}</button>
        ))}
      </div>

      <div style={{ marginBottom: '24px' }}>
        <input type="text" placeholder="Search templates by name or keyword..." value={search} onChange={e => setSearch(e.target.value)} style={{ width: '100%', background: '#0d1a30', border: '1px solid #1e3a5f', borderRadius: '14px', padding: '14px 24px', color: '#fff', fontSize: '15px' }} />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '20px' }}>
        {filtered.map(t => (
          <div key={t.id} style={{ background: '#0d1a30', borderRadius: '20px', border: '1.5px solid #1e3a5f', overflow: 'hidden', cursor: 'pointer', transition: '0.2s', position: 'relative' }}>
             <div style={{ height: '140px', background: `${t.color}11`, borderBottom: '1.5px solid #1e3a5f', display: 'flex', justifyContent: 'center', alignItems: 'center', position: 'relative' }}>
                <span style={{ fontSize: '48px', opacity: 0.5 }}>📄</span>
                <div style={{ position: 'absolute', top: '16px', right: '16px', fontSize: '11px', background: t.status === 'System' ? '#6366f133' : '#f59e0b33', color: t.status === 'System' ? '#818cf8' : '#fbbf24', padding: '4px 10px', borderRadius: '8px', fontWeight: '800' }}>{t.status}</div>
             </div>
             <div style={{ padding: '24px' }}>
                <h3 style={{ margin: '0 0 16px', fontSize: '16px', fontWeight: '800' }}>{t.name}</h3>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', color: '#64748b', fontSize: '12px' }}>
                   <span>{t.company}</span>
                   <div style={{ display: 'flex', gap: '16px' }}>
                      <span>📤 {t.sent}</span>
                      <span style={{ color: '#10b981', fontWeight: '700' }}>👁️ {t.open}</span>
                   </div>
                </div>
                <div style={{ display: 'flex', gap: '8px', marginTop: '24px' }}>
                   <button style={{ flex: 1, padding: '10px', background: '#1e293b', border: 'none', borderRadius: '10px', color: '#fff', fontSize: '13px', fontWeight: '800', cursor: 'pointer' }}>Edit Builder</button>
                   <button style={{ padding: '10px', background: 'transparent', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#64748b', fontSize: '13px', cursor: 'pointer' }}>⚙️</button>
                </div>
             </div>
          </div>
        ))}
      </div>
    </div>
  );
}
