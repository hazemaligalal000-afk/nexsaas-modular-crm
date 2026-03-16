import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';

const STAGES = ['New Lead', 'Qualified', 'Demo', 'Proposal', 'Negotiation', 'Closed Won'];

const STAGE_COLORS = {
    'New Lead': '#818cf8',
    'Qualified': '#00d2ff',
    'Demo': '#f59e0b',
    'Proposal': '#ec4899',
    'Negotiation': '#f97316',
    'Closed Won': '#05ff91',
};

const MOCK_DEALS = [
    { potentialid: '101', potentialname: 'Netflix Content Delivery AI', amount: '95000', sales_stage: 'New Lead', company_name: 'Nexa Intelligence HQ' },
    { potentialid: '102', potentialname: 'Alphabet Global Expansion', amount: '1250000', sales_stage: 'Qualified', company_name: 'Nexa Intelligence HQ' },
    { potentialid: '103', potentialname: 'Meta Ad Platform Integration', amount: '850000', sales_stage: 'Demo', company_name: 'Nexa Intelligence HQ' },
    { potentialid: '104', potentialname: 'Amazon Web Services Migration', amount: '3500000', sales_stage: 'Proposal', company_name: 'Nexa Intelligence HQ' },
    { potentialid: '105', potentialname: 'Apple Supply Chain Opt.', amount: '450000', sales_stage: 'Negotiation', company_name: 'Nexa Intelligence HQ' },
    { potentialid: '106', potentialname: 'Microsoft Azure AI Deal', amount: '2100000', sales_stage: 'Closed Won', company_name: 'Nexa Intelligence HQ' },
];

export default function SalesPipeline() {
    const [deals, setDeals] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dragging, setDragging] = useState(null);
    const [showForm, setShowForm] = useState(false);
    const [newDeal, setNewDeal] = useState({ potentialname: '', amount: '', company_name: 'Nexa Intelligence HQ', sales_stage: 'New Lead' });

    useEffect(() => { fetchDeals(); }, []);

    const fetchDeals = async () => {
        try {
            const body = await apiClient.get('/deals');
            const arr = body?.data || body;
            setDeals(Array.isArray(arr) ? arr : MOCK_DEALS);
        } catch {
            setDeals(MOCK_DEALS);
        } finally {
            setLoading(false);
        }
    };

    const onDragStart = (e, id) => {
        e.dataTransfer.setData('id', id);
        setDragging(id);
    };
    const onDragEnd = () => setDragging(null);
    const onDragOver = (e) => e.preventDefault();
    const onDrop = async (e, stage) => {
        const id = e.dataTransfer.getData('id');
        setDeals(prev => prev.map(d => d.potentialid === id ? { ...d, sales_stage: stage } : d));
        try { await apiClient.put(`/deals/${id}`, { sales_stage: stage }); } catch { }
    };

    const addDeal = () => {
        if (!newDeal.potentialname) return;
        const id = `local-${Date.now()}`;
        setDeals(prev => [...prev, { ...newDeal, potentialid: id }]);
        setNewDeal({ potentialname: '', amount: '', company_name: 'Nexa Intelligence HQ', sales_stage: 'New Lead' });
        setShowForm(false);
    };

    const totalPipeline = deals.reduce((s, d) => s + parseFloat(d.amount || 0), 0);
    const won = deals.filter(d => d.sales_stage === 'Closed Won').reduce((s, d) => s + parseFloat(d.amount || 0), 0);

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', background: 'linear-gradient(90deg, rgba(5,255,145,0.06) 0%, transparent 60%)', padding: '24px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                <div>
                    <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900' }}>Sales Pipeline <span style={{ color: '#05ff91' }}>Kanban</span></h2>
                    <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                        {deals.length} deals · Pipeline: <span style={{ color: '#05ff91', fontWeight: '800' }}>${(totalPipeline / 1e6).toFixed(1)}M</span> · Won: <span style={{ color: '#00d2ff', fontWeight: '800' }}>${(won / 1e6).toFixed(1)}M</span>
                    </p>
                </div>
                <button
                    onClick={() => setShowForm(true)}
                    style={{ background: 'linear-gradient(135deg, #05ff91, #00d2ff)', color: '#000', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer', fontSize: '14px', boxShadow: '0 8px 16px rgba(5,255,145,0.2)' }}
                >+ New Deal</button>
            </div>

            {/* New Deal Form */}
            {showForm && (
                <div style={{ background: 'rgba(5,255,145,0.03)', border: '1px solid rgba(5,255,145,0.15)', borderRadius: '20px', padding: '28px', marginBottom: '24px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto auto', gap: '16px', alignItems: 'end' }}>
                    <div>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'block', marginBottom: '8px' }}>Deal Name</label>
                        <input value={newDeal.potentialname} onChange={e => setNewDeal({ ...newDeal, potentialname: e.target.value })} placeholder="Deal name..." style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '14px', boxSizing: 'border-box' }} />
                    </div>
                    <div>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'block', marginBottom: '8px' }}>Amount ($)</label>
                        <input type="number" value={newDeal.amount} onChange={e => setNewDeal({ ...newDeal, amount: e.target.value })} placeholder="0" style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '14px', boxSizing: 'border-box' }} />
                    </div>
                    <div>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'block', marginBottom: '8px' }}>Stage</label>
                        <select value={newDeal.sales_stage} onChange={e => setNewDeal({ ...newDeal, sales_stage: e.target.value })} style={{ width: '100%', background: '#1e293b', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '14px', boxSizing: 'border-box' }}>
                            {STAGES.map(s => <option key={s} value={s}>{s}</option>)}
                        </select>
                    </div>
                    <button onClick={addDeal} style={{ background: '#05ff91', color: '#000', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', whiteSpace: 'nowrap' }}>Add Deal</button>
                    <button onClick={() => setShowForm(false)} style={{ background: 'rgba(255,255,255,0.05)', color: '#94a3b8', border: 'none', padding: '10px 16px', borderRadius: '10px', cursor: 'pointer' }}>✕</button>
                </div>
            )}

            {/* Kanban Board */}
            <div style={{ display: 'flex', gap: '16px', overflowX: 'auto', paddingBottom: '12px' }}>
                {STAGES.map(stage => {
                    const stageDeals = deals.filter(d => d.sales_stage === stage);
                    const color = STAGE_COLORS[stage];
                    return (
                        <div key={stage} onDragOver={onDragOver} onDrop={e => onDrop(e, stage)}
                            style={{ minWidth: '280px', background: 'rgba(255,255,255,0.01)', borderRadius: '24px', padding: '20px', border: `1px solid rgba(255,255,255,0.05)`, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span style={{ fontSize: '11px', fontWeight: '800', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.08em' }}>{stage}</span>
                                <span style={{ fontSize: '11px', background: `${color}20`, color, padding: '2px 8px', borderRadius: '100px', fontWeight: '800' }}>{stageDeals.length}</span>
                            </div>
                            {loading ? <div style={{ color: '#475569', fontSize: '12px', padding: '20px', textAlign: 'center' }}>Loading...</div> :
                                stageDeals.map(deal => (
                                    <div key={deal.potentialid} draggable
                                        onDragStart={e => onDragStart(e, deal.potentialid)}
                                        onDragEnd={onDragEnd}
                                        style={{ background: 'rgba(255,255,255,0.03)', border: `1px solid ${dragging === deal.potentialid ? color : 'rgba(255,255,255,0.06)'}`, borderRadius: '16px', padding: '18px', cursor: 'grab', transition: 'all 0.2s ease' }}
                                        onMouseEnter={e => e.currentTarget.style.borderColor = `${color}60`}
                                        onMouseLeave={e => e.currentTarget.style.borderColor = dragging === deal.potentialid ? color : 'rgba(255,255,255,0.06)'}
                                    >
                                        <div style={{ fontSize: '14px', fontWeight: '800', marginBottom: '6px', color: '#f8fafc', lineHeight: '1.3' }}>{deal.potentialname}</div>
                                        <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '14px', display: 'flex', alignItems: 'center', gap: '4px' }}>
                                            <span>🏢</span> {deal.company_name}
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid rgba(255,255,255,0.04)', paddingTop: '12px' }}>
                                            <span style={{ fontSize: '16px', color, fontWeight: '900' }}>${parseFloat(deal.amount || 0).toLocaleString()}</span>
                                            <span style={{ fontSize: '10px', color: '#475569', background: 'rgba(255,255,255,0.03)', padding: '2px 8px', borderRadius: '8px' }}>
                                                {new Date(deal.closingdate || Date.now()).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            }
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
