import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';

export default function SettingsModule() {
    const [branding, setBranding] = useState({
        primary_color: '#3b82f6',
        secondary_color: '#1e293b',
        company_name: 'NexSaaS Tenant'
    });

    useEffect(() => {
        const fetchBranding = async () => {
            try {
                const res = await apiClient.get('/Settings/branding');
                setBranding(res.data.branding);
                // Apply branding globally via CSS variables
                document.documentElement.style.setProperty('--primary-color', res.data.branding.primary_color);
                document.documentElement.style.setProperty('--secondary-color', res.data.branding.secondary_color);
            } catch (err) {
                console.warn("Using default branding");
            }
        };
        fetchBranding();
    }, []);

    const handleSave = async () => {
        try {
            await apiClient.post('/Settings/branding', branding);
            document.documentElement.style.setProperty('--primary-color', branding.primary_color);
            document.documentElement.style.setProperty('--secondary-color', branding.secondary_color);
            alert("Settings saved. Branding applied globally.");
        } catch (err) {
            alert("Failed to save settings.");
        }
    };

    return (
        <div style={{ padding: '24px' }}>
            <h2>Organization Settings</h2>
            <p style={{ color: '#64748b' }}>Configure your white-labeled SaaS experience.</p>

            <div style={{ marginTop: '32px', background: 'white', padding: '32px', borderRadius: '12px', border: '1px solid #e2e8f0', maxWidth: '600px' }}>
                <h3 style={{ marginTop: 0 }}>Branding & Identity</h3>
                
                <div style={{ marginBottom: '20px' }}>
                    <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>Company Name</label>
                    <input 
                        type="text" 
                        value={branding.company_name} 
                        onChange={(e) => setBranding({...branding, company_name: e.target.value})}
                        style={{ width: '100%', padding: '10px', border: '1px solid #cbd5e1', borderRadius: '6px' }} 
                    />
                </div>

                <div style={{ display: 'flex', gap: '20px', marginBottom: '32px' }}>
                    <div style={{ flex: 1 }}>
                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>Primary Color</label>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <input 
                                type="color" 
                                value={branding.primary_color} 
                                onChange={(e) => setBranding({...branding, primary_color: e.target.value})}
                                style={{ height: '40px', width: '40px', border: 'none', padding: 0, background: 'none', cursor: 'pointer' }}
                            />
                            <input 
                                type="text" 
                                value={branding.primary_color} 
                                onChange={(e) => setBranding({...branding, primary_color: e.target.value})}
                                style={{ flex: 1, padding: '8px', border: '1px solid #cbd5e1', borderRadius: '6px' }} 
                            />
                        </div>
                    </div>
                    <div style={{ flex: 1 }}>
                        <label style={{ display: 'block', marginBottom: '8px', fontWeight: '500' }}>Secondary Color (Sidebar)</label>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <input 
                                type="color" 
                                value={branding.secondary_color} 
                                onChange={(e) => setBranding({...branding, secondary_color: e.target.value})}
                                style={{ height: '40px', width: '40px', border: 'none', padding: 0, background: 'none', cursor: 'pointer' }}
                            />
                            <input 
                                type="text" 
                                value={branding.secondary_color} 
                                onChange={(e) => setBranding({...branding, secondary_color: e.target.value})}
                                style={{ flex: 1, padding: '8px', border: '1px solid #cbd5e1', borderRadius: '6px' }} 
                            />
                        </div>
                    </div>
                </div>

                <button 
                    onClick={handleSave}
                    style={{ background: 'var(--primary-color, #3b82f6)', color: 'white', border: 'none', padding: '12px 24px', borderRadius: '8px', fontWeight: 'bold', cursor: 'pointer' }}>
                    Save Branding Changes
                </button>
            </div>
        </div>
    );
}
