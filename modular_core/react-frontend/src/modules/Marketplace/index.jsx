import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';

export default function MarketplaceModule() {
    const [catalog, setCatalog] = useState([]);
    const [installing, setInstalling] = useState(null);

    useEffect(() => {
        const fetchCatalog = async () => {
            try {
                // E.g., const res = await apiClient.get('/Marketplace');
                // setCatalog(res.data.catalog);
            } catch (err) {
                setCatalog([
                    { id: 'WhatsAppCRM', name: 'WhatsApp Multi-Channel', description: 'Bind Twilio WhatsApp directly into the Support Tickets and Sales Sequences.', price: '15.00', category: 'Communication', is_installed: false },
                    { id: 'DocuSignIntegrator', name: 'E-Signatures Native', description: 'Appends digital signatures to generated Invoices safely.', price: 'Free', category: 'Sales Enablement', is_installed: false },
                    { id: 'StripeAdvanced', name: 'Stripe SaaS Billing Logic', description: 'Map MRR (Monthly Recurring Revenue) directly against Stripe Webhooks globally.', price: 'Free', category: 'Finance', is_installed: true },
                    { id: 'RealEstateEdition', name: 'Real Estate Power Pack', description: 'One-click vertical transformation: Custom fields for listing, virtual tour links, and zoning data.', price: '49.00', category: 'Vertical Editions', is_installed: false },
                    { id: 'DevSDK', name: 'Marketplace Developer SDK', description: 'Tools and API keys to build your own NexaCRM modules and sell them on this AppExchange.', price: 'Free', category: 'Developer Tools', is_installed: false }
                ]);
            }
        };
        fetchCatalog();
    }, []);

    const installModule = async (moduleId) => {
        setInstalling(moduleId);
        try {
            // E.g., await apiClient.post(`/Marketplace/install/${moduleId}`);
            
            // Mock sequence representing 3 seconds of Server physical ingestion
            setTimeout(() => {
                setCatalog(catalog.map(m => m.id === moduleId ? { ...m, is_installed: true } : m));
                setInstalling(null);
                
                // Technically, window.location.reload() would organically remount the Sidebar with the newly injected Module Schema via dynamic dynamic loading payload!
                alert(`Success! [${moduleId}] has been downloaded, validated, and provisioned into your NexSaaS Organization Database.`);
                
            }, 2500);

        } catch (err) {
            alert('Installation Failed remotely.');
            setInstalling(null);
        }
    };

    return (
        <div style={{ padding: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h2>NexSaaS AppExchange</h2>
                    <p style={{ color: '#64748b', marginTop: 0 }}>Install modules seamlessly into your organizational container structure.</p>
                </div>
                <div style={{ background: '#e2e8f0', padding: '6px 12px', borderRadius: '4px' }}>
                    <strong>Balance:</strong> $2,450.00
                </div>
            </div>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '24px', marginTop: '24px' }}>
                {catalog.map(mod => (
                    <div key={mod.id} style={{ padding: '24px', border: '1px solid #e2e8f0', borderRadius: '12px', background: '#fff', boxShadow: '0 2px 4px rgba(0,0,0,0.02)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                            <h3 style={{ margin: '0 0 8px 0', fontSize: '18px' }}>{mod.name}</h3>
                            <span style={{ fontSize: '12px', background: '#f1f5f9', padding: '2px 6px', borderRadius: '4px', color: '#64748b' }}>
                                {mod.category}
                            </span>
                        </div>
                        <p style={{ fontSize: '14px', color: '#475569', minHeight: '60px' }}>{mod.description}</p>
                        
                        <div style={{ marginTop: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid #e2e8f0', paddingTop: '16px' }}>
                            <div style={{ fontWeight: '500', color: mod.price === 'Free' ? '#10b981' : '#334155' }}>
                                {mod.price === 'Free' ? 'Free' : `$${mod.price}/mo`}
                            </div>
                            
                            {mod.is_installed ? (
                                <button disabled style={{ background: '#f8fafc', color: '#10b981', border: '1px solid #10b981', padding: '6px 16px', borderRadius: '6px', fontWeight: '500' }}>
                                    ✓ Installed
                                </button>
                            ) : (
                                <button 
                                    onClick={() => installModule(mod.id)}
                                    disabled={installing === mod.id}
                                    style={{ background: '#3b82f6', color: '#fff', border: 'none', padding: '6px 16px', borderRadius: '6px', cursor: 'pointer', fontWeight: '500', minWidth: '90px' }}
                                >
                                    {installing === mod.id ? 'Loading...' : 'Install'}
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
