import React, { useState } from 'react';

/**
 * Partners Portal: Agency Performance & Reseller Hub (Requirement S3 / Phase 4)
 * High-quality UI for managing multi-client expansion and commissions.
 */
export default function PartnersPortal() {
  const [activeView, setActiveView] = useState('clients');
  
  const [metrics] = useState({
    active_clients: 12,
    total_managed_revenue: '$14,500',
    pending_commissions: '$2,175',
    available_ai_tokens: '250,000'
  });

  const [clients] = useState([
    { id: 'C-901', name: 'Al-Majid Logistics', mrr: 1490, commission: 15, status: 'Active' },
    { id: 'C-902', name: 'Nile Retail', mrr: 490, commission: 10, status: 'Onboarding' }
  ]);

  return (
    <div style={{ minHeight: '100vh', background: '#0b1628', color: '#fff', padding: '60px' }}>
      <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
        
        {/* Header with Stats Row */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '60px' }}>
           <div>
              <h1 style={{ fontSize: '42px', fontWeight: '900', letterSpacing: '-0.03em', marginBottom: '8px' }}>Partner HQ.</h1>
              <p style={{ fontSize: '18px', color: '#64748b' }}>Provision clients, manage license tiers, and monitor your ecosystem growth.</p>
           </div>
           <div style={{ display: 'flex', gap: '24px' }}>
              <div style={{ textAlign: 'right' }}>
                 <div style={{ fontSize: '12px', fontWeight: '900', color: '#475569', textTransform: 'uppercase' }}>Commission Due</div>
                 <div style={{ fontSize: '28px', fontWeight: '900', color: '#10b981' }}>{metrics.pending_commissions}</div>
              </div>
              <button style={{ background: '#3b82f6', border: 'none', padding: '16px 24px', borderRadius: '16px', color: '#fff', fontWeight: '800', cursor: 'pointer' }}>+ New Client Hub</button>
           </div>
        </div>

        {/* Categories Tab Bar */}
        <div style={{ display: 'flex', gap: '40px', borderBottom: '1px solid #1e3a5f', marginBottom: '40px' }}>
           {['clients', 'payouts', 'marketplace'].map(cat => (
             <div key={cat} onClick={() => setActiveView(cat)} style={{ paddingBottom: '16px', cursor: 'pointer', borderBottom: activeView === cat ? '3px solid #3b82f6' : 'none', color: activeView === cat ? '#fff' : '#475569', fontWeight: '800', textTransform: 'capitalize' }}>
                {cat}
             </div>
           ))}
        </div>

        {/* View Content */}
        {activeView === 'clients' && (
           <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(400px, 1fr))', gap: '24px' }}>
              {clients.map(c => (
                <div key={c.id} style={{ background: '#0d1a30', borderRadius: '24px', border: '1.5px solid #1e3a5f', padding: '32px' }}>
                   <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '24px' }}>
                      <div style={{ fontWeight: '900', fontSize: '20px' }}>{c.name}</div>
                      <div style={{ fontSize: '11px', fontWeight: '900', color: c.status === 'Active' ? '#10b981' : '#f59e0b', textTransform: 'uppercase', background: c.status === 'Active' ? '#065f4633' : '#78350f33', padding: '4px 12px', borderRadius: '20px' }}>{c.status}</div>
                   </div>
                   <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '32px' }}>
                      <div>
                         <div style={{ fontSize: '11px', color: '#475569', fontWeight: '800', marginBottom: '4px' }}>MONTHLY YIELD</div>
                         <div style={{ fontSize: '22px', fontWeight: '900' }}>${c.mrr}</div>
                      </div>
                      <div>
                         <div style={{ fontSize: '11px', color: '#475569', fontWeight: '800', marginBottom: '4px' }}>PARTNER SHARE</div>
                         <div style={{ fontSize: '22px', fontWeight: '900' }}>{c.commission}%</div>
                      </div>
                   </div>
                   <div style={{ display: 'flex', gap: '12px' }}>
                      <button style={{ flex: 1, padding: '12px', border: 'none', background: '#3b82f6', color: '#fff', borderRadius: '12px', fontWeight: '800', cursor: 'pointer' }}>Manage Licenses</button>
                      <button style={{ flex: 1, padding: '12px', border: '1.5px solid #1e3a5f', background: 'transparent', color: '#fff', borderRadius: '12px', fontWeight: '800', cursor: 'pointer' }}>Enter Portal</button>
                   </div>
                </div>
              ))}
           </div>
        )}

        {/* Marketplace (Requirement 362) */}
        {activeView === 'marketplace' && (
           <div style={{ background: '#0d1a30', borderRadius: '32px', border: '1px solid #1e3a5f', padding: '40px', textAlign: 'center' }}>
              <div style={{ fontSize: '48px', marginBottom: '24px' }}>📦</div>
              <h3 style={{ fontSize: '24px', fontWeight: '900', marginBottom: '16px' }}>Bulk License Inventory.</h3>
              <p style={{ color: '#475569', maxWidth: '500px', margin: '0 auto 40px' }}>Pre-purchase capacity for your clients to secure early-bird discount rates for AI tokens and additional seat license packs.</p>
              <button style={{ background: '#3b82f6', padding: '16px 40px', borderRadius: '16px', border: 'none', color: '#fff', fontWeight: '900', cursor: 'pointer' }}>View Bulk Tiers →</button>
           </div>
        )}

      </div>
    </div>
  );
}
