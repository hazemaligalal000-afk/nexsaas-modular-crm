import React, { useState } from 'react';

const MOCK_INVOICES = [
    { id: 'INV-2026-001', client: 'Tesla Inc', items: 3, total: 1250000, tax: 62500, status: 'Paid', due: '2026-03-31', currency: 'USD', recurring: false },
    { id: 'INV-2026-002', client: 'Alphabet Inc', items: 5, total: 3500000, tax: 175000, status: 'Sent', due: '2026-04-10', currency: 'USD', recurring: true },
    { id: 'INV-2026-003', client: 'SpaceX', items: 2, total: 850000, tax: 42500, status: 'Draft', due: '2026-04-15', currency: 'USD', recurring: false },
    { id: 'INV-2026-004', client: 'Meta Platforms', items: 1, total: 450000, tax: 22500, status: 'Overdue', due: '2026-03-01', currency: 'EUR', recurring: false },
    { id: 'INV-2026-005', client: 'Amazon', items: 4, total: 2100000, tax: 105000, status: 'Paid', due: '2026-04-12', currency: 'USD', recurring: true },
];

const MOCK_QUOTES = [
    { id: 'QT-001', client: 'NVIDIA', total: 680000, status: 'Pending', validUntil: '2026-04-30' },
    { id: 'QT-002', client: 'Samsung', total: 290000, status: 'Accepted', validUntil: '2026-04-15' },
    { id: 'QT-003', client: 'Oracle', total: 1200000, status: 'Draft', validUntil: '2026-05-01' },
];

const MOCK_PURCHASE_ORDERS = [
    { id: 'PO-001', vendor: 'AWS', items: 'Cloud Infrastructure', amount: 45000, status: 'Approved', date: '2026-03-10' },
    { id: 'PO-002', vendor: 'Cloudflare', items: 'CDN & Security', amount: 12000, status: 'Pending', date: '2026-03-12' },
    { id: 'PO-003', vendor: 'DataDog', items: 'Monitoring SaaS', amount: 8500, status: 'Approved', date: '2026-03-08' },
];

const MOCK_EXPENSES = [
    { id: 'EXP-001', category: 'Cloud Services', amount: 45000, date: '2026-03-15', status: 'Approved' },
    { id: 'EXP-002', category: 'Office Rent', amount: 12000, date: '2026-03-01', status: 'Paid' },
    { id: 'EXP-003', category: 'Software Licenses', amount: 8500, date: '2026-03-10', status: 'Approved' },
    { id: 'EXP-004', category: 'Travel', amount: 3200, date: '2026-03-14', status: 'Pending' },
];

const STATUS_COLORS = { Paid: '#05ff91', Sent: '#00d2ff', Draft: '#64748b', Overdue: '#ef4444', Pending: '#f59e0b', Approved: '#818cf8', Accepted: '#05ff91' };
const CURRENCY_SYMBOLS = { USD: '$', EUR: '€', GBP: '£', SAR: '﷼' };

export default function InvoicingModule() {
    const [tab, setTab] = useState('invoices');
    const [invoices, setInvoices] = useState(MOCK_INVOICES);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ client: '', total: '', due: '', currency: 'USD' });

    const totalRevenue = invoices.reduce((s, i) => s + i.total, 0);
    const totalTax = invoices.reduce((s, i) => s + i.tax, 0);
    const totalExpenses = MOCK_EXPENSES.reduce((s, e) => s + e.amount, 0);

    const sendInvoice = (id) => setInvoices(prev => prev.map(i => i.id === id ? { ...i, status: 'Sent' } : i));
    const markPaid = (id) => setInvoices(prev => prev.map(i => i.id === id ? { ...i, status: 'Paid' } : i));

    const addInvoice = () => {
        if (!form.client) return;
        const id = `INV-${Date.now()}`;
        setInvoices(prev => [...prev, { id, client: form.client, items: 1, total: parseFloat(form.total) || 0, tax: (parseFloat(form.total) || 0) * 0.05, status: 'Draft', due: form.due, currency: form.currency, recurring: false }]);
        setForm({ client: '', total: '', due: '', currency: 'USD' });
        setShowForm(false);
    };

    const tabs = [
        { id: 'invoices', label: '📄 Invoices' },
        { id: 'quotes', label: '📋 Quotes' },
        { id: 'purchase', label: '🛒 Purchase Orders' },
        { id: 'expenses', label: '💳 Expenses' },
        { id: 'subscriptions', label: '🔄 Subscriptions' },
    ];

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(5,255,145,0.05) 100%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>📄</span> Invoicing & Operations
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            {invoices.length} invoices · Revenue: <span style={{ color: '#05ff91', fontWeight: '800' }}>${(totalRevenue / 1e6).toFixed(1)}M</span> · Tax: <span style={{ color: '#f59e0b', fontWeight: '800' }}>${(totalTax / 1e3).toFixed(0)}K</span>
                        </p>
                    </div>
                    <button onClick={() => setShowForm(true)} style={{ background: 'linear-gradient(135deg, #818cf8, #05ff91)', color: '#000', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer' }}>+ New Invoice</button>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: '14px' }}>
                    {[
                        { label: 'Invoiced', value: `$${(totalRevenue / 1e6).toFixed(1)}M`, color: '#818cf8', icon: '📄' },
                        { label: 'Collected', value: `$${(invoices.filter(i => i.status === 'Paid').reduce((s, i) => s + i.total, 0) / 1e6).toFixed(1)}M`, color: '#05ff91', icon: '✅' },
                        { label: 'Overdue', value: `$${(invoices.filter(i => i.status === 'Overdue').reduce((s, i) => s + i.total, 0) / 1e3).toFixed(0)}K`, color: '#ef4444', icon: '🔴' },
                        { label: 'Tax Collected', value: `$${(totalTax / 1e3).toFixed(0)}K`, color: '#f59e0b', icon: '🏦' },
                        { label: 'Expenses', value: `$${(totalExpenses / 1e3).toFixed(0)}K`, color: '#ec4899', icon: '💳' },
                    ].map(s => (
                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '18px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ fontSize: '10px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'flex', gap: '6px', alignItems: 'center' }}><span>{s.icon}</span>{s.label}</div>
                            <div style={{ fontSize: '24px', fontWeight: '900', color: s.color, marginTop: '6px' }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: 'flex', gap: '4px', marginBottom: '20px', background: 'rgba(255,255,255,0.02)', borderRadius: '14px', padding: '4px', overflowX: 'auto' }}>
                {tabs.map(t => (
                    <button key={t.id} onClick={() => setTab(t.id)} style={{ background: tab === t.id ? 'rgba(129,140,248,0.12)' : 'transparent', color: tab === t.id ? '#818cf8' : '#64748b', border: 'none', padding: '10px 18px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', fontSize: '12px', whiteSpace: 'nowrap' }}>{t.label}</button>
                ))}
            </div>

            {/* New Invoice Form */}
            {showForm && (
                <div style={{ background: 'rgba(129,140,248,0.04)', border: '1px solid rgba(129,140,248,0.2)', borderRadius: '20px', padding: '24px', marginBottom: '20px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '14px', alignItems: 'end' }}>
                    {[['Client', 'client', 'text'], ['Amount', 'total', 'number'], ['Due Date', 'due', 'date']].map(([l, k, t]) => (
                        <div key={k}>
                            <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>{l}</label>
                            <input type={t} value={form[k]} onChange={e => setForm({ ...form, [k]: e.target.value })} style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', boxSizing: 'border-box' }} />
                        </div>
                    ))}
                    <div>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>Currency</label>
                        <select value={form.currency} onChange={e => setForm({ ...form, currency: e.target.value })} style={{ width: '100%', background: '#1e293b', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', boxSizing: 'border-box' }}>
                            {Object.keys(CURRENCY_SYMBOLS).map(c => <option key={c} value={c}>{c}</option>)}
                        </select>
                    </div>
                    <button onClick={addInvoice} style={{ background: '#818cf8', color: '#fff', border: 'none', padding: '11px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer' }}>Create</button>
                </div>
            )}

            {/* Invoices Table */}
            {tab === 'invoices' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Invoice #', 'Client', 'Amount', 'Tax', 'Currency', 'Due', 'Status', 'Actions'].map(h => (
                                <th key={h} style={{ padding: '14px 18px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {invoices.map(inv => {
                                const sc = STATUS_COLORS[inv.status] || '#64748b';
                                const sym = CURRENCY_SYMBOLS[inv.currency] || '$';
                                return (
                                    <tr key={inv.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                        onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                        <td style={{ padding: '14px 18px', color: '#818cf8', fontWeight: '700' }}>{inv.id}</td>
                                        <td style={{ padding: '14px 18px', fontWeight: '600' }}>{inv.client}</td>
                                        <td style={{ padding: '14px 18px', color: '#05ff91', fontWeight: '900' }}>{sym}{inv.total.toLocaleString()}</td>
                                        <td style={{ padding: '14px 18px', color: '#f59e0b', fontSize: '13px' }}>{sym}{inv.tax.toLocaleString()}</td>
                                        <td style={{ padding: '14px 18px', color: '#64748b' }}>{inv.currency} {inv.recurring && <span style={{ color: '#818cf8', fontSize: '10px' }}>🔄</span>}</td>
                                        <td style={{ padding: '14px 18px', color: '#94a3b8', fontSize: '13px' }}>{inv.due}</td>
                                        <td style={{ padding: '14px 18px' }}><span style={{ background: `${sc}15`, color: sc, padding: '4px 12px', borderRadius: '100px', fontSize: '10px', fontWeight: '800' }}>{inv.status}</span></td>
                                        <td style={{ padding: '14px 18px' }}>
                                            <div style={{ display: 'flex', gap: '6px' }}>
                                                {inv.status === 'Draft' && <button onClick={() => sendInvoice(inv.id)} style={{ background: 'rgba(0,210,255,0.1)', color: '#00d2ff', border: 'none', padding: '5px 12px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '11px' }}>Send</button>}
                                                {(inv.status === 'Sent' || inv.status === 'Overdue') && <button onClick={() => markPaid(inv.id)} style={{ background: 'rgba(5,255,145,0.1)', color: '#05ff91', border: 'none', padding: '5px 12px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '11px' }}>Mark Paid</button>}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Quotes */}
            {tab === 'quotes' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Quote #', 'Client', 'Total', 'Valid Until', 'Status', 'Action'].map(h => (
                                <th key={h} style={{ padding: '16px 20px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {MOCK_QUOTES.map(q => (
                                <tr key={q.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 20px', color: '#818cf8', fontWeight: '700' }}>{q.id}</td>
                                    <td style={{ padding: '16px 20px', fontWeight: '600' }}>{q.client}</td>
                                    <td style={{ padding: '16px 20px', color: '#05ff91', fontWeight: '800' }}>${q.total.toLocaleString()}</td>
                                    <td style={{ padding: '16px 20px', color: '#94a3b8' }}>{q.validUntil}</td>
                                    <td style={{ padding: '16px 20px' }}><span style={{ background: `${STATUS_COLORS[q.status]}15`, color: STATUS_COLORS[q.status], padding: '4px 12px', borderRadius: '100px', fontSize: '10px', fontWeight: '800' }}>{q.status}</span></td>
                                    <td style={{ padding: '16px 20px' }}><button style={{ background: 'rgba(129,140,248,0.1)', color: '#818cf8', border: 'none', padding: '6px 14px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '12px' }}>Convert to Invoice</button></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Purchase Orders */}
            {tab === 'purchase' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['PO #', 'Vendor', 'Items', 'Amount', 'Date', 'Status'].map(h => (
                                <th key={h} style={{ padding: '16px 20px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {MOCK_PURCHASE_ORDERS.map(po => (
                                <tr key={po.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 20px', color: '#818cf8', fontWeight: '700' }}>{po.id}</td>
                                    <td style={{ padding: '16px 20px', fontWeight: '600' }}>{po.vendor}</td>
                                    <td style={{ padding: '16px 20px', color: '#94a3b8' }}>{po.items}</td>
                                    <td style={{ padding: '16px 20px', color: '#ef4444', fontWeight: '800' }}>-${po.amount.toLocaleString()}</td>
                                    <td style={{ padding: '16px 20px', color: '#94a3b8', fontSize: '13px' }}>{po.date}</td>
                                    <td style={{ padding: '16px 20px' }}><span style={{ background: `${STATUS_COLORS[po.status]}15`, color: STATUS_COLORS[po.status], padding: '4px 12px', borderRadius: '100px', fontSize: '10px', fontWeight: '800' }}>{po.status}</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Expenses */}
            {tab === 'expenses' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['ID', 'Category', 'Amount', 'Date', 'Status'].map(h => (
                                <th key={h} style={{ padding: '16px 20px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {MOCK_EXPENSES.map(e => (
                                <tr key={e.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={ev => ev.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={ev => ev.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 20px', color: '#818cf8', fontWeight: '700' }}>{e.id}</td>
                                    <td style={{ padding: '16px 20px', fontWeight: '600' }}>{e.category}</td>
                                    <td style={{ padding: '16px 20px', color: '#ef4444', fontWeight: '800' }}>-${e.amount.toLocaleString()}</td>
                                    <td style={{ padding: '16px 20px', color: '#94a3b8', fontSize: '13px' }}>{e.date}</td>
                                    <td style={{ padding: '16px 20px' }}><span style={{ background: `${STATUS_COLORS[e.status]}15`, color: STATUS_COLORS[e.status], padding: '4px 12px', borderRadius: '100px', fontSize: '10px', fontWeight: '800' }}>{e.status}</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Subscriptions */}
            {tab === 'subscriptions' && (
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '16px' }}>
                    {[
                        { client: 'Tesla Inc', plan: 'Enterprise', mrr: 42000, next: 'Apr 01', status: 'Active' },
                        { client: 'Alphabet Inc', plan: 'Enterprise Plus', mrr: 85000, next: 'Apr 10', status: 'Active' },
                        { client: 'Amazon', plan: 'Enterprise', mrr: 62000, next: 'Apr 12', status: 'Active' },
                        { client: 'SpaceX', plan: 'Pro', mrr: 15000, next: 'Apr 15', status: 'Paused' },
                    ].map(sub => (
                        <div key={sub.client} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)', transition: 'all 0.2s' }}
                            onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(129,140,248,0.3)'}
                            onMouseLeave={e => e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)'}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '12px' }}>
                                <span style={{ fontWeight: '800', fontSize: '15px' }}>{sub.client}</span>
                                <span style={{ background: sub.status === 'Active' ? 'rgba(5,255,145,0.1)' : 'rgba(245,158,11,0.1)', color: sub.status === 'Active' ? '#05ff91' : '#f59e0b', padding: '3px 10px', borderRadius: '100px', fontSize: '10px', fontWeight: '800' }}>{sub.status}</span>
                            </div>
                            <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '12px' }}>Plan: {sub.plan} · Next billing: {sub.next}</div>
                            <div style={{ fontSize: '24px', fontWeight: '900', color: '#05ff91' }}>${sub.mrr.toLocaleString()}<span style={{ fontSize: '12px', color: '#64748b', fontWeight: '600' }}>/mo</span></div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
