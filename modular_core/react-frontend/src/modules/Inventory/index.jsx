import React, { useState, useEffect } from 'react';

const BRIDGE = 'http://localhost:9090';

const MOCK_ITEMS = [
    { name: 'ITM-001', item_name: 'Nexa CRM Enterprise License', item_group: 'Software', stock_uom: 'Nos', actual_qty: 500, valuation_rate: 12500, item_code: 'NexaCRM-ENT' },
    { name: 'ITM-002', item_name: 'AI Analytics Module', item_group: 'Software', stock_uom: 'Nos', actual_qty: 200, valuation_rate: 5000, item_code: 'NexaAI-MOD' },
    { name: 'ITM-003', item_name: 'Server (16-Core Enterprise)', item_group: 'Hardware', stock_uom: 'Unit', actual_qty: 12, valuation_rate: 42000, item_code: 'SRV-ENT-16' },
    { name: 'ITM-004', item_name: 'Professional Services Day', item_group: 'Services', stock_uom: 'Day', actual_qty: 180, valuation_rate: 2500, item_code: 'SVC-PRO-DAY' },
    { name: 'ITM-005', item_name: 'Cloud Storage 1TB / yr', item_group: 'Infrastructure', stock_uom: 'Unit', actual_qty: 350, valuation_rate: 800, item_code: 'CLO-STG-1TB' },
    { name: 'ITM-006', item_name: 'Premium Support Contract', item_group: 'Services', stock_uom: 'Year', actual_qty: 85, valuation_rate: 15000, item_code: 'SUP-PRM-YR' },
];

const GROUP_COLORS = { Software: '#818cf8', Hardware: '#00d2ff', Services: '#05ff91', Infrastructure: '#f59e0b' };

export default function InventoryModule() {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [groupFilter, setGroupFilter] = useState('all');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ item_name: '', item_code: '', item_group: 'Software', actual_qty: '', valuation_rate: '' });

    useEffect(() => {
        fetch(`${BRIDGE}/erp/Item`)
            .then(r => r.json())
            .then(d => setItems(d.data?.length ? d.data : MOCK_ITEMS))
            .catch(() => setItems(MOCK_ITEMS))
            .finally(() => setLoading(false));
    }, []);

    const groups = [...new Set(items.map(i => i.item_group))];
    const filtered = items.filter(i =>
        (groupFilter === 'all' || i.item_group === groupFilter) &&
        (i.item_name?.toLowerCase().includes(search.toLowerCase()) || i.item_code?.toLowerCase().includes(search.toLowerCase()))
    );

    const totalValue = filtered.reduce((s, i) => s + (parseFloat(i.actual_qty) * parseFloat(i.valuation_rate || 0)), 0);

    const addItem = () => {
        if (!form.item_name) return;
        setItems(prev => [...prev, { ...form, name: `ITM-${Date.now()}`, stock_uom: 'Nos' }]);
        setForm({ item_name: '', item_code: '', item_group: 'Software', actual_qty: '', valuation_rate: '' });
        setShowForm(false);
    };

    const getLevelColor = (qty) => {
        const n = parseInt(qty) || 0;
        if (n > 100) return '#05ff91';
        if (n > 20) return '#f59e0b';
        return '#ef4444';
    };

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(90deg, rgba(0,210,255,0.08) 0%, transparent 60%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>📦</span> Inventory & Stock
                            <span style={{ fontSize: '11px', background: 'rgba(0,210,255,0.15)', color: '#00d2ff', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>ERPNext Synced</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            {filtered.length} items · Total value: <span style={{ color: '#00d2ff', fontWeight: '800' }}>${(totalValue / 1e6).toFixed(1)}M</span>
                        </p>
                    </div>
                    <button onClick={() => setShowForm(true)} style={{ background: 'linear-gradient(135deg, #00d2ff, #818cf8)', color: '#000', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer' }}>
                        + Add Item
                    </button>
                </div>

                {/* KPI Cards */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px' }}>
                    {[
                        { label: 'Total SKUs', value: items.length, color: '#00d2ff', icon: '📦' },
                        { label: 'Categories', value: groups.length, color: '#818cf8', icon: '🏷️' },
                        { label: 'Total Value', value: `$${(items.reduce((s, i) => s + parseFloat(i.actual_qty || 0) * parseFloat(i.valuation_rate || 0), 0) / 1e6).toFixed(1)}M`, color: '#05ff91', icon: '💰' },
                        { label: 'Low Stock', value: items.filter(i => parseInt(i.actual_qty) < 20).length, color: '#ef4444', icon: '⚠️' },
                    ].map(s => (
                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '20px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'flex', gap: '6px', alignItems: 'center' }}><span>{s.icon}</span>{s.label}</div>
                            <div style={{ fontSize: '28px', fontWeight: '900', color: s.color, marginTop: '8px' }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Filters */}
            <div style={{ display: 'flex', gap: '12px', marginBottom: '20px', flexWrap: 'wrap' }}>
                <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Search items..." style={{ background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '12px', padding: '10px 16px', color: '#fff', fontSize: '13px', flex: 1, minWidth: '200px', outline: 'none' }} />
                <div style={{ display: 'flex', gap: '6px', background: 'rgba(255,255,255,0.02)', borderRadius: '12px', padding: '4px' }}>
                    {['all', ...groups].map(g => (
                        <button key={g} onClick={() => setGroupFilter(g)} style={{ background: groupFilter === g ? `${GROUP_COLORS[g] || '#64748b'}20` : 'transparent', color: groupFilter === g ? (GROUP_COLORS[g] || '#00d2ff') : '#64748b', border: 'none', padding: '8px 16px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '12px', textTransform: 'capitalize', transition: 'all 0.2s' }}>
                            {g === 'all' ? 'All' : g}
                        </button>
                    ))}
                </div>
            </div>

            {/* Add Form */}
            {showForm && (
                <div style={{ background: 'rgba(0,210,255,0.04)', border: '1px solid rgba(0,210,255,0.15)', borderRadius: '20px', padding: '24px', marginBottom: '20px', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr)) auto', gap: '16px', alignItems: 'end' }}>
                    {[['Item Name', 'item_name', 'text'], ['Item Code', 'item_code', 'text'], ['Qty', 'actual_qty', 'number'], ['Unit Price', 'valuation_rate', 'number']].map(([label, key, type]) => (
                        <div key={key}>
                            <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>{label}</label>
                            <input type={type} value={form[key]} onChange={e => setForm({ ...form, [key]: e.target.value })} style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '13px', boxSizing: 'border-box' }} />
                        </div>
                    ))}
                    <div>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>Category</label>
                        <select value={form.item_group} onChange={e => setForm({ ...form, item_group: e.target.value })} style={{ width: '100%', background: '#1e293b', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '13px', boxSizing: 'border-box' }}>
                            {['Software', 'Hardware', 'Services', 'Infrastructure'].map(g => <option key={g} value={g}>{g}</option>)}
                        </select>
                    </div>
                    <button onClick={addItem} style={{ background: '#00d2ff', color: '#000', border: 'none', padding: '11px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', whiteSpace: 'nowrap' }}>Add</button>
                </div>
            )}

            {/* Table */}
            <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                {loading ? (
                    <div style={{ padding: '60px', textAlign: 'center', color: '#64748b' }}>Loading inventory...</div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Item', 'Code', 'Category', 'Stock Level', 'Unit Price', 'Total Value'].map(h => (
                                <th key={h} style={{ padding: '16px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {filtered.map((item, i) => {
                                const qty = parseInt(item.actual_qty) || 0;
                                const price = parseFloat(item.valuation_rate) || 0;
                                const total = qty * price;
                                const groupColor = GROUP_COLORS[item.item_group] || '#64748b';
                                return (
                                    <tr key={item.name || i} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                        onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                        <td style={{ padding: '16px 24px' }}>
                                            <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                                                <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: `${groupColor}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '18px' }}>📦</div>
                                                <div>
                                                    <div style={{ fontWeight: '700', color: '#f8fafc', fontSize: '14px' }}>{item.item_name}</div>
                                                    <div style={{ color: '#475569', fontSize: '11px' }}>{item.stock_uom}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style={{ padding: '16px 24px', color: '#64748b', fontFamily: 'monospace', fontSize: '12px' }}>{item.item_code}</td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <span style={{ background: `${groupColor}15`, color: groupColor, padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800' }}>{item.item_group}</span>
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                <span style={{ fontSize: '16px', fontWeight: '900', color: getLevelColor(qty) }}>{qty.toLocaleString()}</span>
                                                <div style={{ height: '6px', width: '80px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden' }}>
                                                    <div style={{ height: '100%', width: `${Math.min(100, (qty / 500) * 100)}%`, background: getLevelColor(qty), borderRadius: '100px' }} />
                                                </div>
                                            </div>
                                        </td>
                                        <td style={{ padding: '16px 24px', color: '#94a3b8' }}>${price.toLocaleString()}</td>
                                        <td style={{ padding: '16px 24px', color: '#05ff91', fontWeight: '900', fontSize: '15px' }}>${total.toLocaleString()}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
