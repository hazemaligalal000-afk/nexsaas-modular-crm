import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';

// Column schema per module
const MODULE_SCHEMA = {
    Accounts:   { pk: 'accountid',    columns: [{ key: 'accountname', label: 'Account Name' }, { key: 'industry', label: 'Industry' }, { key: 'website', label: 'Website' }] },
    Contacts:   { pk: 'contactid',    columns: [{ key: 'firstname', label: 'First Name' }, { key: 'lastname', label: 'Last Name' }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Phone' }] },
    Leads:      { pk: 'leadid',       columns: [{ key: 'firstname', label: 'Name' }, { key: 'company', label: 'Company' }, { key: 'email', label: 'Email' }, { key: 'leadstatus', label: 'Status' }] },
    Potentials: { pk: 'potentialid',  columns: [{ key: 'potentialname', label: 'Deal Name' }, { key: 'amount', label: 'Amount' }, { key: 'sales_stage', label: 'Stage' }, { key: 'closingdate', label: 'Close Date' }] },
    HelpDesk:   { pk: 'ticketid',     columns: [{ key: 'title', label: 'Title' }, { key: 'status', label: 'Status' }, { key: 'priority', label: 'Priority' }] },
};

const STATUS_COLORS = {
    Hot: '#ef4444', Qualified: '#f59e0b', New: '#818cf8', Nurture: '#6366f1', Open: '#ef4444',
    'In Progress': '#f59e0b', Done: '#05ff91', Paid: '#05ff91', Active: '#00d2ff',
    Prospecting: '#818cf8', Qualification: '#00d2ff', 'Needs Analysis': '#f59e0b',
    Proposal: '#f97316', Negotiation: '#ec4899', 'Closed Won': '#05ff91',
};

const ENDPOINT_MAP = {
    Accounts: '/accounts', Contacts: '/contacts', Leads: '/leads',
    Potentials: '/potentials', HelpDesk: '/tickets',
};

export default function DynamicModule({ moduleName, icon }) {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [editingRow, setEditingRow] = useState(null);
    const [formData, setFormData] = useState({});

    const schema = MODULE_SCHEMA[moduleName] || { pk: 'id', columns: [{ key: 'name', label: 'Name' }] };
    const endpoint = ENDPOINT_MAP[moduleName] || `/${moduleName.toLowerCase()}`;

    useEffect(() => {
        fetchData();
    }, [moduleName]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const body = await apiClient.get(endpoint);
            const arr = body?.data || body;
            setData(Array.isArray(arr) ? arr : []);
        } catch {
            setData([]);
        } finally {
            setLoading(false);
        }
    };

    const getPrimaryLabel = (item) => {
        return item.accountname || item.potentialname || item.title ||
            ((item.firstname || '') + ' ' + (item.lastname || '')).trim() || item.subject || 'Untitled';
    };

    const getStatusBadge = (value) => {
        if (!value) return null;
        const color = STATUS_COLORS[value] || '#64748b';
        return (
            <span style={{ padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800', background: `${color}20`, color }}>
                {value}
            </span>
        );
    };

    const filtered = data.filter(item =>
        schema.columns.some(col =>
            String(item[col.key] || '').toLowerCase().includes(search.toLowerCase())
        )
    );

    const handleEdit = (item) => {
        setEditingRow(item[schema.pk]);
        setFormData({ ...item });
        setShowForm(true);
    };

    const handleSave = async () => {
        if (editingRow) {
            setData(prev => prev.map(r => r[schema.pk] === editingRow ? { ...r, ...formData } : r));
            try { await apiClient.put(`${endpoint}/${editingRow}`, formData); } catch { }
        } else {
            const tempId = `new-${Date.now()}`;
            setData(prev => [...prev, { ...formData, [schema.pk]: tempId, crmid: tempId }]);
            try { await apiClient.post(endpoint, formData); } catch { }
        }
        setShowForm(false);
        setEditingRow(null);
        setFormData({});
    };

    const handleDelete = (id) => {
        if (!window.confirm('Delete this record?')) return;
        setData(prev => prev.filter(r => r[schema.pk] !== id));
        try { apiClient.delete(`${endpoint}/${id}`); } catch { }
    };

    return (
        <div style={{ padding: '0 4px', color: '#fff' }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', background: 'linear-gradient(90deg, rgba(5,255,145,0.05) 0%, transparent 60%)', padding: '24px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                <div>
                    <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <span style={{ fontSize: '32px' }}>{icon || '📁'}</span>
                        {moduleName}
                    </h2>
                    <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                        {filtered.length} records in Nexa Intelligence™ Core
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                    <input
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search..."
                        style={{ background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '12px', padding: '10px 16px', color: '#fff', fontSize: '13px', width: '200px', outline: 'none' }}
                    />
                    <button
                        onClick={() => { setShowForm(true); setEditingRow(null); setFormData({}); }}
                        style={{ background: 'linear-gradient(135deg, #05ff91, #00d2ff)', color: '#000', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer', fontSize: '14px', boxShadow: '0 8px 16px rgba(5,255,145,0.2)', whiteSpace: 'nowrap' }}
                    >+ Create New</button>
                </div>
            </div>

            {/* Create/Edit Form */}
            {showForm && (
                <div style={{ background: 'rgba(5,255,145,0.03)', border: '1px solid rgba(5,255,145,0.15)', borderRadius: '20px', padding: '28px', marginBottom: '24px' }}>
                    <h3 style={{ margin: '0 0 20px', color: '#05ff91', fontWeight: '800', fontSize: '16px' }}>
                        {editingRow ? `Edit ${moduleName} Record` : `Create New ${moduleName}`}
                    </h3>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '16px', marginBottom: '20px' }}>
                        {schema.columns.map(col => (
                            <div key={col.key}>
                                <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'block', marginBottom: '6px' }}>{col.label}</label>
                                <input
                                    value={formData[col.key] || ''}
                                    onChange={e => setFormData({ ...formData, [col.key]: e.target.value })}
                                    placeholder={col.label}
                                    style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '14px', boxSizing: 'border-box', outline: 'none' }}
                                />
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'flex', gap: '12px' }}>
                        <button onClick={handleSave} style={{ background: '#05ff91', color: '#000', border: 'none', padding: '10px 24px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer' }}>
                            {editingRow ? 'Save Changes' : 'Create Record'}
                        </button>
                        <button onClick={() => { setShowForm(false); setEditingRow(null); }} style={{ background: 'rgba(255,255,255,0.05)', color: '#94a3b8', border: 'none', padding: '10px 20px', borderRadius: '10px', cursor: 'pointer', fontWeight: '600' }}>Cancel</button>
                    </div>
                </div>
            )}

            {/* Table */}
            <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                {loading ? (
                    <div style={{ padding: '60px', textAlign: 'center', color: '#64748b' }}>
                        <div style={{ fontSize: '32px', marginBottom: '12px' }}>{icon || '📁'}</div>
                        Loading {moduleName}...
                    </div>
                ) : filtered.length === 0 ? (
                    <div style={{ padding: '60px', textAlign: 'center', color: '#64748b' }}>
                        <div style={{ fontSize: '48px', marginBottom: '12px' }}>📭</div>
                        <div style={{ fontWeight: '700', marginBottom: '8px' }}>No {moduleName} Found</div>
                        <div style={{ fontSize: '13px' }}>Create your first record using the button above.</div>
                    </div>
                ) : (
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead>
                            <tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                                {schema.columns.map(col => (
                                    <th key={col.key} style={{ padding: '18px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                                        {col.label}
                                    </th>
                                ))}
                                <th style={{ padding: '18px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.08em', textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((item, idx) => (
                                <tr key={item[schema.pk] || idx}
                                    style={{ borderBottom: '1px solid rgba(255,255,255,0.03)', transition: 'background 0.2s' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}
                                >
                                    {schema.columns.map((col, ci) => (
                                        <td key={col.key} style={{ padding: '18px 24px' }}>
                                            {ci === 0 ? (
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                    <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: 'rgba(5,255,145,0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '16px', flexShrink: 0 }}>
                                                        {icon || '📄'}
                                                    </div>
                                                    <div>
                                                        <div style={{ color: '#f8fafc', fontWeight: '700', fontSize: '14px' }}>{item[col.key] || '—'}</div>
                                                        <div style={{ color: '#475569', fontSize: '11px' }}>ID #{item[schema.pk]}</div>
                                                    </div>
                                                </div>
                                            ) : col.key === 'amount' ? (
                                                <span style={{ color: '#05ff91', fontWeight: '800', fontSize: '15px' }}>
                                                    ${parseFloat(item[col.key] || 0).toLocaleString()}
                                                </span>
                                            ) : col.key.includes('status') || col.key === 'sales_stage' || col.key === 'priority' || col.key === 'leadstatus' ? (
                                                getStatusBadge(item[col.key])
                                            ) : (
                                                <span style={{ color: '#94a3b8', fontSize: '14px' }}>{item[col.key] || '—'}</span>
                                            )}
                                        </td>
                                    ))}
                                    <td style={{ padding: '18px 24px', textAlign: 'right' }}>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                            <button
                                                onClick={() => handleEdit(item)}
                                                title="Edit"
                                                style={{ background: 'rgba(255,255,255,0.05)', border: 'none', padding: '8px 14px', borderRadius: '10px', color: '#94a3b8', cursor: 'pointer', fontSize: '14px', transition: 'all 0.2s' }}
                                                onMouseEnter={e => { e.currentTarget.style.background = 'rgba(5,255,145,0.1)'; e.currentTarget.style.color = '#05ff91'; }}
                                                onMouseLeave={e => { e.currentTarget.style.background = 'rgba(255,255,255,0.05)'; e.currentTarget.style.color = '#94a3b8'; }}
                                            >✏️ Edit</button>
                                            <button
                                                onClick={() => handleDelete(item[schema.pk])}
                                                title="Delete"
                                                style={{ background: 'rgba(255,255,255,0.05)', border: 'none', padding: '8px 14px', borderRadius: '10px', color: '#94a3b8', cursor: 'pointer', fontSize: '14px', transition: 'all 0.2s' }}
                                                onMouseEnter={e => { e.currentTarget.style.background = 'rgba(239,68,68,0.1)'; e.currentTarget.style.color = '#ef4444'; }}
                                                onMouseLeave={e => { e.currentTarget.style.background = 'rgba(255,255,255,0.05)'; e.currentTarget.style.color = '#94a3b8'; }}
                                            >🗑️</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
