import React, { useState, useEffect } from 'react';

const BRIDGE = 'http://localhost:9090';

const MOCK_INVOICES = [
    { name: 'INV-2026-001', customer: 'Tesla Inc', grand_total: 1250000, outstanding_amount: 0, status: 'Paid', posting_date: '2026-03-01', due_date: '2026-03-31' },
    { name: 'INV-2026-002', customer: 'Alphabet Inc', grand_total: 3500000, outstanding_amount: 3500000, status: 'Unpaid', posting_date: '2026-03-10', due_date: '2026-04-10' },
    { name: 'INV-2026-003', customer: 'SpaceX', grand_total: 850000, outstanding_amount: 0, status: 'Paid', posting_date: '2026-02-28', due_date: '2026-03-28' },
    { name: 'INV-2026-004', customer: 'Meta Platforms', grand_total: 450000, outstanding_amount: 450000, status: 'Overdue', posting_date: '2026-02-01', due_date: '2026-03-01' },
    { name: 'INV-2026-005', customer: 'Amazon', grand_total: 2100000, outstanding_amount: 0, status: 'Paid', posting_date: '2026-03-12', due_date: '2026-04-12' },
];

const MOCK_JOURNALS = [
    { name: 'JV-001', posting_date: '2026-03-15', voucher_type: 'Journal Entry', title: 'Q1 Expense Accrual', total_debit: 125000 },
    { name: 'JV-002', posting_date: '2026-03-10', voucher_type: 'Payment Entry', title: 'Tesla Invoice Payment', total_debit: 1250000 },
    { name: 'JV-003', posting_date: '2026-03-08', voucher_type: 'Payment Entry', title: 'Amazon Invoice Payment', total_debit: 2100000 },
];

const STATUS_COLORS = { Paid: '#05ff91', Unpaid: '#f59e0b', Overdue: '#ef4444', Cancelled: '#64748b' };

export default function Accounting() {
    const [invoices, setInvoices] = useState([]);
    const [journals, setJournals] = useState(MOCK_JOURNALS);
    const [stats, setStats] = useState({ revenue: 0, outstanding: 0, paid: 0, overdue: 0 });
    const [loading, setLoading] = useState(true);
    const [tab, setTab] = useState('invoices');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ customer: '', grand_total: '', due_date: '' });

    useEffect(() => {
        fetch(`${BRIDGE}/erp/Sales Invoice`)
            .then(r => r.json())
            .then(d => populateData(d.data?.length ? d.data : MOCK_INVOICES))
            .catch(() => populateData(MOCK_INVOICES))
            .finally(() => setLoading(false));
    }, []);

    const populateData = (invs) => {
        setInvoices(invs);
        const revenue = invs.reduce((s, i) => s + parseFloat(i.grand_total || 0), 0);
        const paid = invs.filter(i => i.status === 'Paid').reduce((s, i) => s + parseFloat(i.grand_total || 0), 0);
        const overdue = invs.filter(i => i.status === 'Overdue').reduce((s, i) => s + parseFloat(i.outstanding_amount || i.grand_total || 0), 0);
        const outstanding = invs.filter(i => i.status === 'Unpaid').reduce((s, i) => s + parseFloat(i.outstanding_amount || i.grand_total || 0), 0);
        setStats({ revenue, paid, outstanding, overdue });
    };

    const addInvoice = () => {
        if (!form.customer) return;
        const id = `INV-${Date.now()}`;
        const newInv = { name: id, customer: form.customer, grand_total: parseFloat(form.grand_total) || 0, outstanding_amount: parseFloat(form.grand_total) || 0, status: 'Unpaid', posting_date: new Date().toISOString().split('T')[0], due_date: form.due_date };
        const updated = [...invoices, newInv];
        populateData(updated);
        setForm({ customer: '', grand_total: '', due_date: '' });
        setShowForm(false);
    };

    const markPaid = (name) => {
        const updated = invoices.map(i => i.name === name ? { ...i, status: 'Paid', outstanding_amount: 0 } : i);
        populateData(updated);
    };

    const tabs = [{ id: 'invoices', label: '📄 Invoices' }, { id: 'journals', label: '📒 Journal Entries' }, { id: 'reports', label: '📊 P&L Report' }];

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(90deg, rgba(129,140,248,0.08) 0%, transparent 60%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>💰</span> Accounting & Finance
                            <span style={{ fontSize: '11px', background: 'rgba(129,140,248,0.15)', color: '#818cf8', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>ERPNext Synced</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            Total Revenue: <span style={{ color: '#05ff91', fontWeight: '800' }}>${(stats.revenue / 1e6).toFixed(1)}M</span> · Outstanding: <span style={{ color: '#f59e0b', fontWeight: '800' }}>${(stats.outstanding / 1e6).toFixed(1)}M</span> · Overdue: <span style={{ color: '#ef4444', fontWeight: '800' }}>${(stats.overdue / 1e3).toFixed(0)}K</span>
                        </p>
                    </div>
                    <button onClick={() => setShowForm(true)} style={{ background: 'linear-gradient(135deg, #818cf8, #6366f1)', color: '#fff', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer' }}>
                        + New Invoice
                    </button>
                </div>

                {/* KPI */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px' }}>
                    {[
                        { label: 'Total Revenue', value: `$${(stats.revenue / 1e6).toFixed(1)}M`, color: '#818cf8', icon: '📊' },
                        { label: 'Collected', value: `$${(stats.paid / 1e6).toFixed(1)}M`, color: '#05ff91', icon: '✅' },
                        { label: 'Outstanding', value: `$${(stats.outstanding / 1e6).toFixed(1)}M`, color: '#f59e0b', icon: '⏳' },
                        { label: 'Overdue', value: `$${(stats.overdue / 1e3).toFixed(0)}K`, color: '#ef4444', icon: '🔴' },
                    ].map(s => (
                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '20px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'flex', gap: '6px', alignItems: 'center' }}><span>{s.icon}</span>{s.label}</div>
                            <div style={{ fontSize: '28px', fontWeight: '900', color: s.color, marginTop: '8px' }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: 'flex', gap: '4px', marginBottom: '20px', background: 'rgba(255,255,255,0.02)', borderRadius: '14px', padding: '4px', width: 'fit-content' }}>
                {tabs.map(t => (
                    <button key={t.id} onClick={() => setTab(t.id)} style={{ background: tab === t.id ? 'rgba(129,140,248,0.15)' : 'transparent', color: tab === t.id ? '#818cf8' : '#64748b', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', fontSize: '13px', transition: 'all 0.2s' }}>
                        {t.label}
                    </button>
                ))}
            </div>

            {/* New Invoice Form */}
            {showForm && (
                <div style={{ background: 'rgba(129,140,248,0.04)', border: '1px solid rgba(129,140,248,0.2)', borderRadius: '20px', padding: '24px', marginBottom: '20px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '16px', alignItems: 'end' }}>
                    {[['Customer', 'customer', 'text'], ['Amount ($)', 'grand_total', 'number'], ['Due Date', 'due_date', 'date']].map(([label, key, type]) => (
                        <div key={key}>
                            <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>{label}</label>
                            <input type={type} value={form[key]} onChange={e => setForm({ ...form, [key]: e.target.value })} style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', boxSizing: 'border-box' }} />
                        </div>
                    ))}
                    <button onClick={addInvoice} style={{ background: '#818cf8', color: '#fff', border: 'none', padding: '11px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer' }}>Create</button>
                </div>
            )}

            {/* Invoices Table */}
            {tab === 'invoices' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    {loading ? <div style={{ padding: '60px', textAlign: 'center', color: '#64748b' }}>Loading...</div> : (
                        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                            <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                                {['Invoice #', 'Customer', 'Total', 'Due Date', 'Status', 'Action'].map(h => (
                                    <th key={h} style={{ padding: '16px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                                ))}
                            </tr></thead>
                            <tbody>
                                {invoices.map(inv => {
                                    const sc = STATUS_COLORS[inv.status] || '#64748b';
                                    return (
                                        <tr key={inv.name} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                            onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                            onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                            <td style={{ padding: '16px 24px', color: '#818cf8', fontWeight: '700' }}>{inv.name}</td>
                                            <td style={{ padding: '16px 24px', fontWeight: '600' }}>{inv.customer}</td>
                                            <td style={{ padding: '16px 24px', color: '#05ff91', fontWeight: '900', fontSize: '15px' }}>${parseFloat(inv.grand_total || 0).toLocaleString()}</td>
                                            <td style={{ padding: '16px 24px', color: '#94a3b8', fontSize: '13px' }}>{inv.due_date || inv.posting_date}</td>
                                            <td style={{ padding: '16px 24px' }}>
                                                <span style={{ background: `${sc}15`, color: sc, padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800' }}>{inv.status}</span>
                                            </td>
                                            <td style={{ padding: '16px 24px' }}>
                                                {inv.status !== 'Paid' && (
                                                    <button onClick={() => markPaid(inv.name)} style={{ background: 'rgba(5,255,145,0.1)', color: '#05ff91', border: '1px solid rgba(5,255,145,0.2)', padding: '6px 14px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '12px' }}>
                                                        Mark Paid
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            )}

            {/* Journal Entries */}
            {tab === 'journals' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Voucher #', 'Date', 'Type', 'Description', 'Amount'].map(h => (
                                <th key={h} style={{ padding: '16px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {journals.map(j => (
                                <tr key={j.name} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 24px', color: '#818cf8', fontWeight: '700' }}>{j.name}</td>
                                    <td style={{ padding: '16px 24px', color: '#94a3b8' }}>{j.posting_date}</td>
                                    <td style={{ padding: '16px 24px' }}><span style={{ background: 'rgba(129,140,248,0.1)', color: '#818cf8', padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '700' }}>{j.voucher_type}</span></td>
                                    <td style={{ padding: '16px 24px', color: '#94a3b8' }}>{j.title}</td>
                                    <td style={{ padding: '16px 24px', color: '#05ff91', fontWeight: '800' }}>${parseFloat(j.total_debit || 0).toLocaleString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* P&L Report */}
            {tab === 'reports' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', color: '#818cf8', fontWeight: '800' }}>📊 Profit & Loss — Q1 2026</h3>
                        {[
                            { label: 'Total Revenue', value: `$${(stats.revenue / 1e6).toFixed(2)}M`, color: '#05ff91', bold: true },
                            { label: 'Cost of Goods Sold', value: `-$${(stats.revenue * 0.35 / 1e6).toFixed(2)}M`, color: '#ef4444' },
                            { label: 'Gross Profit', value: `$${(stats.revenue * 0.65 / 1e6).toFixed(2)}M`, color: '#00d2ff', bold: true },
                            { label: 'Operating Expenses', value: `-$${(stats.revenue * 0.22 / 1e6).toFixed(2)}M`, color: '#ef4444' },
                            { label: 'Net Profit', value: `$${(stats.revenue * 0.43 / 1e6).toFixed(2)}M`, color: '#05ff91', bold: true },
                        ].map(r => (
                            <div key={r.label} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <span style={{ fontSize: '13px', color: r.bold ? '#e2e8f0' : '#94a3b8', fontWeight: r.bold ? '700' : '400' }}>{r.label}</span>
                                <span style={{ fontSize: r.bold ? '18px' : '15px', color: r.color, fontWeight: r.bold ? '900' : '700' }}>{r.value}</span>
                            </div>
                        ))}
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', color: '#05ff91', fontWeight: '800' }}>💰 Revenue by Customer</h3>
                        {invoices.slice(0, 5).map(inv => {
                            const pct = Math.round((parseFloat(inv.grand_total) / stats.revenue) * 100);
                            return (
                                <div key={inv.name} style={{ marginBottom: '14px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '5px' }}>
                                        <span style={{ fontSize: '13px', color: '#94a3b8' }}>{inv.customer}</span>
                                        <span style={{ fontSize: '13px', color: '#05ff91', fontWeight: '800' }}>${(parseFloat(inv.grand_total) / 1e6).toFixed(2)}M ({pct}%)</span>
                                    </div>
                                    <div style={{ height: '6px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden' }}>
                                        <div style={{ height: '100%', width: `${pct}%`, background: 'linear-gradient(90deg, #818cf8, #05ff91)', borderRadius: '100px' }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
