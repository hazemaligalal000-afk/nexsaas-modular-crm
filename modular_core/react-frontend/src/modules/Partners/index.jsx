import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';

export default function PartnersModule() {
    const [data, setData] = useState(null);

    useEffect(() => {
        const fetchPartnership = async () => {
            try {
                const res = await apiClient.get('/Partners/subtenants');
                setData(res.data);
            } catch (err) {
                setData({
                    partner: { agency_name: 'Growth Engines Inc.', tier: 'Platinum', monthly_commission: '$4,520.00' },
                    clients: [
                        { id: 101, name: 'Pizza Palace', status: 'Active', mrr: '$499' },
                        { id: 102, name: 'Law Office of Smith', status: 'Active', mrr: '$899' },
                        { id: 105, name: 'City Real Estate', status: 'Pending Setup', mrr: '$0' }
                    ]
                });
            }
        };
        fetchPartnership();
    }, []);

    if (!data) return <div style={{ padding: '24px' }}>Loading Agency Dashboard...</div>;

    return (
        <div style={{ padding: '24px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                    <h2 style={{ margin: 0 }}>Partner Master Portal</h2>
                    <p style={{ color: '#64748b' }}>Agency: <b>{data.partner.agency_name}</b> | Tier: <span style={{ color: '#8b5cf6', fontWeight: 'bold' }}>{data.partner.tier}</span></p>
                </div>
                <div style={{ background: '#ecfdf5', padding: '12px 24px', borderRadius: '12px', border: '1px solid #10b981', textAlign: 'right' }}>
                    <div style={{ fontSize: '12px', color: '#065f46', textTransform: 'uppercase' }}>Current Month Commission</div>
                    <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#047857' }}>{data.partner.monthly_commission}</div>
                </div>
            </div>

            <div style={{ marginTop: '32px', background: 'white', borderRadius: '12px', border: '1px solid #e2e8f0', overflow: 'hidden' }}>
                <div style={{ padding: '16px 24px', background: '#f8fafc', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3 style={{ margin: 0, fontSize: '16px' }}>Managed Client Accounts</h3>
                    <button style={{ background: '#3b82f6', color: 'white', border: 'none', padding: '8px 16px', borderRadius: '6px', fontWeight: '500', cursor: 'pointer' }}>
                        + Provision New Sub-Tenant
                    </button>
                </div>
                <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ borderBottom: '1px solid #e2e8f0' }}>
                            <th style={{ padding: '16px 24px' }}>Tenant ID</th>
                            <th>Organization Name</th>
                            <th>Status</th>
                            <th>MRR Contribution</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.clients.map(client => (
                            <tr key={client.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                <td style={{ padding: '16px 24px' }}>#{client.id}</td>
                                <td><b>{client.name}</b></td>
                                <td>
                                    <span style={{ 
                                        padding: '4px 10px', borderRadius: '12px', fontSize: '12px', 
                                        background: client.status === 'Active' ? '#ecfdf5' : '#fef3c7', 
                                        color: client.status === 'Active' ? '#065f46' : '#92400e' 
                                    }}>
                                        {client.status}
                                    </span>
                                </td>
                                <td>{client.mrr}</td>
                                <td>
                                    <button style={{ background: 'transparent', color: '#3b82f6', border: '1px solid #3b82f6', padding: '6px 12px', borderRadius: '4px', cursor: 'pointer', fontSize: '13px' }}>
                                        Login as Admin
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            
            <div style={{ marginTop: '24px', padding: '20px', background: '#eff6ff', borderRadius: '12px', border: '1px solid #bfdbfe', display: 'flex', gap: '16px', alignItems: 'center' }}>
                <span style={{ fontSize: '24px' }}>💡</span>
                <p style={{ margin: 0, fontSize: '14px', color: '#1e40af' }}>
                    <b>Partner Tip:</b> Upgrading to 50 active clients unlocks the "White-Label Domain" feature for your sub-tenants.
                </p>
            </div>
        </div>
    );
}
