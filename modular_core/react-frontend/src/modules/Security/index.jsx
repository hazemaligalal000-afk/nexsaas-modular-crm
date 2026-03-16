import React, { useState } from 'react';

const SECURITY_FEATURES = [
    { id: 'aes256', name: 'AES-256 Encryption', status: 'active', icon: '🔐', category: 'Encryption', detail: 'All data encrypted at rest and in transit' },
    { id: 'soc2', name: 'SOC2 Type II', status: 'certified', icon: '✅', category: 'Compliance', detail: 'Annual audit — Last: Jan 2026' },
    { id: 'gdpr', name: 'GDPR Ready', status: 'active', icon: '🇪🇺', category: 'Compliance', detail: 'EU data protection compliance' },
    { id: 'hipaa', name: 'HIPAA Compliant', status: 'active', icon: '🏥', category: 'Compliance', detail: 'Healthcare data handling certified' },
    { id: '2fa', name: 'Two-Factor Auth', status: 'enforced', icon: '📱', category: 'Auth', detail: 'TOTP + SMS fallback for all users' },
    { id: 'saml', name: 'SAML SSO', status: 'active', icon: '🔑', category: 'Auth', detail: 'Okta, Azure AD, Google Workspace' },
    { id: 'audit', name: 'Audit Logs', status: 'active', icon: '📋', category: 'Monitoring', detail: 'Full audit trail — 90 day retention' },
    { id: 'ipwhite', name: 'IP Whitelisting', status: 'active', icon: '🌐', category: 'Network', detail: '3 IP ranges configured' },
    { id: 'sovereignty', name: 'Data Sovereignty', status: 'active', icon: '🏛️', category: 'Compliance', detail: 'Regional data residency controls' },
    { id: 'regional', name: 'Regional Storage', status: 'active', icon: '🗺️', category: 'Infrastructure', detail: 'US-East, EU-West, ME-Central' },
    { id: 'e2e', name: 'E2E Encryption', status: 'active', icon: '🔒', category: 'Encryption', detail: 'End-to-end for messages & files' },
    { id: 'password', name: 'Password Policies', status: 'enforced', icon: '🛡️', category: 'Auth', detail: 'Min 12 chars, complexity rules' },
    { id: 'roles', name: 'Role Permissions', status: 'active', icon: '👥', category: 'Access', detail: '5 roles: Admin, Sales, Ops, Viewer, Custom' },
    { id: 'fieldlevel', name: 'Field-Level Security', status: 'active', icon: '🔏', category: 'Access', detail: 'Per-field read/write controls' },
    { id: 'apikeys', name: 'API Key Management', status: 'active', icon: '🗝️', category: 'Auth', detail: '4 active keys, auto-rotation enabled' },
    { id: 'dr', name: 'Disaster Recovery', status: 'active', icon: '♻️', category: 'Infrastructure', detail: 'RPO: 1hr, RTO: 4hr — 3-region backup' },
    { id: 'zerotrust', name: 'Zero-Trust Architecture', status: 'active', icon: '🏰', category: 'Network', detail: 'Verify every request, never trust' },
];

const AUDIT_LOG = [
    { time: '2 min ago', user: 'admin', action: 'Login', detail: 'Dashboard login from 192.168.1.105', severity: 'info' },
    { time: '15 min ago', user: 'sara.ali', action: 'API Key Created', detail: 'New key: ****-d8e9', severity: 'warning' },
    { time: '1 hr ago', user: 'system', action: 'Backup Complete', detail: 'Full DB backup — 2.4GB encrypted', severity: 'info' },
    { time: '3 hr ago', user: 'admin', action: 'Role Modified', detail: 'Added "Export" permission to Sales role', severity: 'warning' },
    { time: '6 hr ago', user: 'system', action: 'Failed Login', detail: '3 failed attempts from 45.12.33.xx — IP blocked', severity: 'critical' },
    { time: '12 hr ago', user: 'system', action: 'Certificate Renewed', detail: 'SSL cert auto-renewed — expires Mar 2027', severity: 'info' },
];

const SEV_COLORS = { info: '#00d2ff', warning: '#f59e0b', critical: '#ef4444' };
const STAT_COLORS = { active: '#05ff91', enforced: '#818cf8', certified: '#f59e0b' };

export default function SecurityModule() {
    const [tab, setTab] = useState('overview');
    const [catFilter, setCatFilter] = useState('all');

    const categories = [...new Set(SECURITY_FEATURES.map(f => f.category))];
    const filtered = catFilter === 'all' ? SECURITY_FEATURES : SECURITY_FEATURES.filter(f => f.category === catFilter);

    const tabs = [
        { id: 'overview', label: '🏠 Overview' },
        { id: 'features', label: '🛡️ Features' },
        { id: 'audit', label: '📋 Audit Log' },
        { id: 'access', label: '👥 Access Control' },
    ];

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(135deg, rgba(5,255,145,0.08) 0%, rgba(0,210,255,0.05) 100%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(5,255,145,0.15)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>🛡️</span> Security & Compliance
                            <span style={{ fontSize: '11px', background: 'rgba(5,255,145,0.15)', color: '#05ff91', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>ZERO TRUST</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            {SECURITY_FEATURES.length} controls active · Security score: <span style={{ color: '#05ff91', fontWeight: '800' }}>98/100</span>
                        </p>
                    </div>
                    <div style={{ background: 'rgba(5,255,145,0.1)', border: '1px solid rgba(5,255,145,0.2)', padding: '10px 20px', borderRadius: '14px' }}>
                        <span style={{ color: '#05ff91', fontWeight: '900', fontSize: '16px' }}>🔒 ALL SECURE</span>
                    </div>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '14px' }}>
                    {[
                        { label: 'Security Score', value: '98/100', color: '#05ff91', icon: '🔒' },
                        { label: 'Active Controls', value: SECURITY_FEATURES.length, color: '#00d2ff', icon: '🛡️' },
                        { label: 'Threats Blocked', value: '4,281', color: '#ef4444', icon: '🚫' },
                        { label: 'Uptime', value: '99.97%', color: '#818cf8', icon: '⏱️' },
                    ].map(s => (
                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '18px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ fontSize: '10px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'flex', gap: '6px', alignItems: 'center' }}><span>{s.icon}</span>{s.label}</div>
                            <div style={{ fontSize: '24px', fontWeight: '900', color: s.color, marginTop: '6px' }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: 'flex', gap: '4px', marginBottom: '20px', background: 'rgba(255,255,255,0.02)', borderRadius: '14px', padding: '4px', width: 'fit-content' }}>
                {tabs.map(t => (
                    <button key={t.id} onClick={() => setTab(t.id)} style={{ background: tab === t.id ? 'rgba(5,255,145,0.12)' : 'transparent', color: tab === t.id ? '#05ff91' : '#64748b', border: 'none', padding: '10px 18px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', fontSize: '12px', transition: 'all 0.2s' }}>{t.label}</button>
                ))}
            </div>

            {/* Overview */}
            {tab === 'overview' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#05ff91', fontSize: '15px' }}>Compliance Status</h3>
                        {SECURITY_FEATURES.filter(f => f.category === 'Compliance').map(f => (
                            <div key={f.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                                    <span style={{ fontSize: '18px' }}>{f.icon}</span>
                                    <div>
                                        <div style={{ fontWeight: '700', fontSize: '14px' }}>{f.name}</div>
                                        <div style={{ fontSize: '11px', color: '#64748b' }}>{f.detail}</div>
                                    </div>
                                </div>
                                <span style={{ background: `${STAT_COLORS[f.status]}15`, color: STAT_COLORS[f.status], padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{f.status}</span>
                            </div>
                        ))}
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#f59e0b', fontSize: '15px' }}>Recent Security Events</h3>
                        {AUDIT_LOG.slice(0, 5).map((log, i) => (
                            <div key={i} style={{ padding: '12px 14px', borderLeft: `3px solid ${SEV_COLORS[log.severity]}`, background: `${SEV_COLORS[log.severity]}06`, borderRadius: '0 10px 10px 0', marginBottom: '10px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <span style={{ fontWeight: '700', fontSize: '13px' }}>{log.action}</span>
                                    <span style={{ fontSize: '10px', color: '#475569' }}>{log.time}</span>
                                </div>
                                <div style={{ fontSize: '11px', color: '#94a3b8', marginTop: '4px' }}>{log.detail}</div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Features Grid */}
            {tab === 'features' && (
                <div>
                    <div style={{ display: 'flex', gap: '6px', marginBottom: '20px', flexWrap: 'wrap' }}>
                        {['all', ...categories].map(c => (
                            <button key={c} onClick={() => setCatFilter(c)} style={{ background: catFilter === c ? 'rgba(5,255,145,0.12)' : 'rgba(255,255,255,0.03)', color: catFilter === c ? '#05ff91' : '#64748b', border: 'none', padding: '8px 14px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '12px', textTransform: 'capitalize' }}>
                                {c === 'all' ? `All (${SECURITY_FEATURES.length})` : c}
                            </button>
                        ))}
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(260px, 1fr))', gap: '14px' }}>
                        {filtered.map(f => (
                            <div key={f.id} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '20px', border: '1px solid rgba(255,255,255,0.05)', transition: 'all 0.2s' }}
                                onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(5,255,145,0.2)'}
                                onMouseLeave={e => e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)'}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                                    <span style={{ fontSize: '24px' }}>{f.icon}</span>
                                    <span style={{ background: `${STAT_COLORS[f.status]}15`, color: STAT_COLORS[f.status], padding: '3px 10px', borderRadius: '100px', fontSize: '10px', fontWeight: '800', textTransform: 'uppercase' }}>{f.status}</span>
                                </div>
                                <div style={{ fontWeight: '800', fontSize: '14px', marginBottom: '4px' }}>{f.name}</div>
                                <div style={{ fontSize: '11px', color: '#64748b' }}>{f.detail}</div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Audit Log */}
            {tab === 'audit' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Time', 'User', 'Action', 'Detail', 'Severity'].map(h => (
                                <th key={h} style={{ padding: '16px 20px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {AUDIT_LOG.map((log, i) => (
                                <tr key={i} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '14px 20px', color: '#94a3b8', fontSize: '13px' }}>{log.time}</td>
                                    <td style={{ padding: '14px 20px', fontWeight: '600' }}>{log.user}</td>
                                    <td style={{ padding: '14px 20px', fontWeight: '700', color: '#f8fafc' }}>{log.action}</td>
                                    <td style={{ padding: '14px 20px', color: '#94a3b8', fontSize: '13px' }}>{log.detail}</td>
                                    <td style={{ padding: '14px 20px' }}>
                                        <span style={{ background: `${SEV_COLORS[log.severity]}15`, color: SEV_COLORS[log.severity], padding: '4px 12px', borderRadius: '100px', fontSize: '10px', fontWeight: '800', textTransform: 'uppercase' }}>{log.severity}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Access Control */}
            {tab === 'access' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#818cf8', fontSize: '15px' }}>👥 Role-Based Access Control</h3>
                        {[
                            { role: 'Super Admin', users: 1, perms: 'Full access to all modules', color: '#ef4444' },
                            { role: 'Admin', users: 2, perms: 'CRM + ERP management', color: '#f59e0b' },
                            { role: 'Sales Manager', users: 5, perms: 'Pipeline, Leads, Deals, Reports', color: '#00d2ff' },
                            { role: 'Sales Rep', users: 12, perms: 'Own leads/deals only', color: '#05ff91' },
                            { role: 'Viewer', users: 8, perms: 'Read-only access', color: '#64748b' },
                        ].map(r => (
                            <div key={r.role} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <div>
                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                        <span style={{ width: '10px', height: '10px', borderRadius: '50%', background: r.color }}></span>
                                        <span style={{ fontWeight: '700', fontSize: '14px' }}>{r.role}</span>
                                    </div>
                                    <div style={{ fontSize: '11px', color: '#64748b', marginLeft: '18px' }}>{r.perms}</div>
                                </div>
                                <span style={{ color: '#94a3b8', fontSize: '13px', fontWeight: '700' }}>{r.users} users</span>
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 14px', fontWeight: '800', color: '#05ff91', fontSize: '14px' }}>🗝️ Active API Keys</h3>
                            {[
                                { name: 'CRM Frontend', lastUsed: 'Just now', created: 'Mar 01', scope: 'read/write' },
                                { name: 'Integration Bridge', lastUsed: '5m ago', created: 'Mar 01', scope: 'admin' },
                                { name: 'Mobile App', lastUsed: '2h ago', created: 'Mar 05', scope: 'read' },
                                { name: 'Webhook Service', lastUsed: '1d ago', created: 'Mar 10', scope: 'write' },
                            ].map(k => (
                                <div key={k.name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                    <div>
                                        <div style={{ fontWeight: '600', fontSize: '13px' }}>{k.name}</div>
                                        <div style={{ fontSize: '10px', color: '#475569' }}>Last used: {k.lastUsed}</div>
                                    </div>
                                    <span style={{ background: 'rgba(129,140,248,0.1)', color: '#818cf8', padding: '3px 10px', borderRadius: '6px', fontSize: '10px', fontWeight: '700' }}>{k.scope}</span>
                                </div>
                            ))}
                        </div>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 14px', fontWeight: '800', color: '#f59e0b', fontSize: '14px' }}>🔐 Encryption Status</h3>
                            {['AES-256 at Rest', 'TLS 1.3 in Transit', 'E2E for Messages', 'Encrypted Backups'].map(e => (
                                <div key={e} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                    <span style={{ fontSize: '13px', color: '#94a3b8' }}>🔒 {e}</span>
                                    <span style={{ color: '#05ff91', fontSize: '11px', fontWeight: '800' }}>ACTIVE</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
