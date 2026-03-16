import React, { useState } from 'react';

const TERRITORIES = [
    { name: 'North America', rep: 'John Doe', quota: 5000000, achieved: 3200000, deals: 14 },
    { name: 'EMEA', rep: 'Sara Ali', quota: 3500000, achieved: 2800000, deals: 11 },
    { name: 'APAC', rep: 'Ahmed Hassan', quota: 2500000, achieved: 1900000, deals: 8 },
    { name: 'LATAM', rep: 'Maria Garcia', quota: 1500000, achieved: 950000, deals: 5 },
];

const ACTIVITY_LOG = [
    { time: '10:32 AM', rep: 'Sara Ali', type: 'Call', detail: 'Discovery call with Alphabet — 22 min', icon: '📞' },
    { time: '10:15 AM', rep: 'John Doe', type: 'Email', detail: 'Follow-up sent to Tesla — opened 2 min later', icon: '✉️' },
    { time: '09:45 AM', rep: 'Ahmed Hassan', type: 'Meeting', detail: 'Product demo with NVIDIA team — 4 attendees', icon: '📹' },
    { time: '09:12 AM', rep: 'System', type: 'Auto-Dialer', detail: 'Completed 23 outbound calls — 8 connected', icon: '🤖' },
    { time: '08:30 AM', rep: 'Sara Ali', type: 'Task', detail: 'Proposal sent for Meta Ad Platform Integration', icon: '📄' },
    { time: 'Yesterday', rep: 'System', type: 'Lead Dist.', detail: '12 new leads auto-distributed to 4 reps', icon: '🎯' },
];

const CUSTOM_STAGES = [
    { name: 'New Lead', count: 12, value: '$340K', color: '#818cf8' },
    { name: 'Qualified', count: 8, value: '$1.2M', color: '#00d2ff' },
    { name: 'Demo Scheduled', count: 5, value: '$890K', color: '#f59e0b' },
    { name: 'Proposal Sent', count: 4, value: '$2.1M', color: '#ec4899' },
    { name: 'Negotiation', count: 3, value: '$4.5M', color: '#f97316' },
    { name: 'Closed Won', count: 6, value: '$3.2M', color: '#05ff91' },
    { name: 'Lost / Recover', count: 2, value: '$650K', color: '#ef4444' },
];

const SALES_SCRIPTS = [
    { name: 'Cold Call — Enterprise', usage: 87, winRate: 34, lastUpdated: 'Mar 12' },
    { name: 'Discovery Call Framework', usage: 124, winRate: 42, lastUpdated: 'Mar 10' },
    { name: 'Product Demo Script', usage: 65, winRate: 58, lastUpdated: 'Mar 08' },
    { name: 'Objection Handling — Price', usage: 45, winRate: 61, lastUpdated: 'Mar 15' },
    { name: 'Closing Script — Enterprise', usage: 38, winRate: 72, lastUpdated: 'Mar 14' },
];

const WIN_LOSS = [
    { reason: 'Price', wins: 8, losses: 12, pct: 40 },
    { reason: 'Feature Fit', wins: 14, losses: 5, pct: 73 },
    { reason: 'Competition', wins: 6, losses: 9, pct: 40 },
    { reason: 'Timing', wins: 10, losses: 3, pct: 77 },
    { reason: 'Decision Maker', wins: 11, losses: 7, pct: 61 },
];

export default function SalesOps() {
    const [tab, setTab] = useState('overview');

    const totalQuota = TERRITORIES.reduce((s, t) => s + t.quota, 0);
    const totalAchieved = TERRITORIES.reduce((s, t) => s + t.achieved, 0);
    const quotaPct = Math.round((totalAchieved / totalQuota) * 100);

    const tabs = [
        { id: 'overview', label: '🏠 Overview' },
        { id: 'territories', label: '🗺️ Territories' },
        { id: 'activity', label: '📋 Activity' },
        { id: 'scripts', label: '📝 Scripts' },
        { id: 'analytics', label: '📊 Win/Loss' },
        { id: 'velocity', label: '⚡ Velocity' },
    ];

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(135deg, rgba(249,115,22,0.1) 0%, rgba(236,72,153,0.05) 100%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(249,115,22,0.15)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>⚡</span> Sales Operations
                            <span style={{ fontSize: '11px', background: 'rgba(249,115,22,0.15)', color: '#f97316', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>23 TOOLS ACTIVE</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            Quota: <span style={{ color: '#f97316', fontWeight: '800' }}>{quotaPct}%</span> achieved · Pipeline velocity: <span style={{ color: '#05ff91', fontWeight: '800' }}>4.2x</span> · {TERRITORIES.reduce((s, t) => s + t.deals, 0)} active deals
                        </p>
                    </div>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: '14px' }}>
                    {[
                        { label: 'Quota Attainment', value: `${quotaPct}%`, color: '#f97316', icon: '🎯' },
                        { label: 'Active Deals', value: TERRITORIES.reduce((s, t) => s + t.deals, 0), color: '#00d2ff', icon: '💼' },
                        { label: 'Avg Deal Size', value: '$285K', color: '#05ff91', icon: '💰' },
                        { label: 'Sales Velocity', value: '4.2x', color: '#818cf8', icon: '⚡' },
                        { label: 'Win Rate', value: '34%', color: '#ec4899', icon: '🏆' },
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
                    <button key={t.id} onClick={() => setTab(t.id)} style={{ background: tab === t.id ? 'rgba(249,115,22,0.12)' : 'transparent', color: tab === t.id ? '#f97316' : '#64748b', border: 'none', padding: '10px 18px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', fontSize: '12px', transition: 'all 0.2s', whiteSpace: 'nowrap' }}>{t.label}</button>
                ))}
            </div>

            {/* Overview */}
            {tab === 'overview' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#f97316', fontSize: '15px' }}>📊 Pipeline by Stage</h3>
                        {CUSTOM_STAGES.map(s => (
                            <div key={s.name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                                    <span style={{ width: '10px', height: '10px', borderRadius: '50%', background: s.color }}></span>
                                    <span style={{ fontSize: '13px', fontWeight: '600' }}>{s.name}</span>
                                </div>
                                <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                                    <span style={{ fontSize: '12px', color: '#64748b' }}>{s.count} deals</span>
                                    <span style={{ fontSize: '14px', color: s.color, fontWeight: '900' }}>{s.value}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#00d2ff', fontSize: '15px' }}>📋 Live Activity Feed</h3>
                        {ACTIVITY_LOG.map((a, i) => (
                            <div key={i} style={{ display: 'flex', gap: '12px', alignItems: 'flex-start', padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <span style={{ fontSize: '18px', marginTop: '2px' }}>{a.icon}</span>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontSize: '13px', fontWeight: '600' }}>{a.detail}</div>
                                    <div style={{ fontSize: '11px', color: '#64748b', marginTop: '2px' }}>{a.rep} · {a.time}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Territories */}
            {tab === 'territories' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Territory', 'Rep', 'Quota', 'Achieved', 'Attainment', 'Deals'].map(h => (
                                <th key={h} style={{ padding: '16px 20px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {TERRITORIES.map(t => {
                                const pct = Math.round((t.achieved / t.quota) * 100);
                                return (
                                    <tr key={t.name} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                        onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                        onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                        <td style={{ padding: '16px 20px', fontWeight: '800', fontSize: '14px' }}>{t.name}</td>
                                        <td style={{ padding: '16px 20px', color: '#94a3b8' }}>{t.rep}</td>
                                        <td style={{ padding: '16px 20px', color: '#64748b' }}>${(t.quota / 1e6).toFixed(1)}M</td>
                                        <td style={{ padding: '16px 20px', color: '#05ff91', fontWeight: '800' }}>${(t.achieved / 1e6).toFixed(1)}M</td>
                                        <td style={{ padding: '16px 20px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                <div style={{ height: '6px', width: '80px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px' }}>
                                                    <div style={{ height: '100%', width: `${Math.min(100, pct)}%`, background: pct >= 80 ? '#05ff91' : pct >= 60 ? '#f59e0b' : '#ef4444', borderRadius: '100px' }} />
                                                </div>
                                                <span style={{ fontSize: '14px', fontWeight: '800', color: pct >= 80 ? '#05ff91' : pct >= 60 ? '#f59e0b' : '#ef4444' }}>{pct}%</span>
                                            </div>
                                        </td>
                                        <td style={{ padding: '16px 20px', fontWeight: '700' }}>{t.deals}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Activity Log */}
            {tab === 'activity' && (
                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                        <div style={{ padding: '20px', borderBottom: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: 0, fontWeight: '800', fontSize: '15px' }}>📋 Full Activity Log</h3>
                        </div>
                        {ACTIVITY_LOG.map((a, i) => (
                            <div key={i} style={{ display: 'flex', gap: '16px', padding: '18px 20px', borderBottom: '1px solid rgba(255,255,255,0.03)', alignItems: 'center' }}
                                onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                <span style={{ fontSize: '24px' }}>{a.icon}</span>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontWeight: '700', fontSize: '14px' }}>{a.detail}</div>
                                    <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>{a.rep} · {a.type} · {a.time}</div>
                                </div>
                                <span style={{ background: 'rgba(249,115,22,0.1)', color: '#f97316', padding: '4px 10px', borderRadius: '8px', fontSize: '10px', fontWeight: '800' }}>{a.type}</span>
                            </div>
                        ))}
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#818cf8', fontSize: '14px' }}>📊 Activity Stats Today</h3>
                        {[
                            { label: 'Calls Made', value: '47', icon: '📞', color: '#00d2ff' },
                            { label: 'Emails Sent', value: '89', icon: '✉️', color: '#818cf8' },
                            { label: 'Meetings Held', value: '6', icon: '📹', color: '#05ff91' },
                            { label: 'Proposals Sent', value: '3', icon: '📄', color: '#f59e0b' },
                            { label: 'Tasks Completed', value: '21', icon: '✅', color: '#ec4899' },
                            { label: 'Leads Distributed', value: '12', icon: '🎯', color: '#f97316' },
                        ].map(s => (
                            <div key={s.label} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <span style={{ fontSize: '13px', color: '#94a3b8' }}>{s.icon} {s.label}</span>
                                <span style={{ fontSize: '18px', fontWeight: '900', color: s.color }}>{s.value}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Sales Scripts */}
            {tab === 'scripts' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Script Name', 'Times Used', 'Win Rate', 'Last Updated', 'Action'].map(h => (
                                <th key={h} style={{ padding: '16px 20px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {SALES_SCRIPTS.map(s => (
                                <tr key={s.name} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 20px', fontWeight: '700' }}>{s.name}</td>
                                    <td style={{ padding: '16px 20px', color: '#94a3b8' }}>{s.usage}</td>
                                    <td style={{ padding: '16px 20px' }}>
                                        <span style={{ color: s.winRate > 50 ? '#05ff91' : '#f59e0b', fontWeight: '900', fontSize: '16px' }}>{s.winRate}%</span>
                                    </td>
                                    <td style={{ padding: '16px 20px', color: '#64748b', fontSize: '13px' }}>{s.lastUpdated}</td>
                                    <td style={{ padding: '16px 20px' }}>
                                        <button style={{ background: 'rgba(249,115,22,0.1)', color: '#f97316', border: 'none', padding: '6px 14px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '12px' }}>View Script</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Win/Loss Analytics */}
            {tab === 'analytics' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', fontWeight: '800', color: '#05ff91', fontSize: '15px' }}>🏆 Win/Loss Analysis by Reason</h3>
                        {WIN_LOSS.map(w => (
                            <div key={w.reason} style={{ marginBottom: '16px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                                    <span style={{ fontSize: '13px', color: '#94a3b8' }}>{w.reason}</span>
                                    <div style={{ display: 'flex', gap: '12px' }}>
                                        <span style={{ color: '#05ff91', fontSize: '12px', fontWeight: '700' }}>✅ {w.wins}</span>
                                        <span style={{ color: '#ef4444', fontSize: '12px', fontWeight: '700' }}>❌ {w.losses}</span>
                                        <span style={{ color: w.pct > 60 ? '#05ff91' : '#f59e0b', fontSize: '12px', fontWeight: '900' }}>{w.pct}%</span>
                                    </div>
                                </div>
                                <div style={{ height: '8px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden', display: 'flex' }}>
                                    <div style={{ height: '100%', width: `${w.pct}%`, background: '#05ff91', borderRadius: '100px 0 0 100px' }} />
                                    <div style={{ height: '100%', width: `${100 - w.pct}%`, background: '#ef4444', borderRadius: '0 100px 100px 0' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 14px', fontWeight: '800', color: '#ec4899', fontSize: '14px' }}>🔄 Lost Deal Recovery</h3>
                            {[{ deal: 'Oracle DB Migration', value: '$420K', reason: 'Budget', recovery: '68%' }, { deal: 'Samsung IoT Platform', value: '$185K', reason: 'Timing', recovery: '82%' }].map(d => (
                                <div key={d.deal} style={{ padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                        <span style={{ fontWeight: '700', fontSize: '13px' }}>{d.deal}</span>
                                        <span style={{ color: '#05ff91', fontWeight: '900' }}>{d.recovery}</span>
                                    </div>
                                    <div style={{ fontSize: '11px', color: '#64748b' }}>{d.value} — Lost: {d.reason} — AI suggests re-engage</div>
                                </div>
                            ))}
                        </div>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 14px', fontWeight: '800', color: '#f97316', fontSize: '14px' }}>📈 Historical Trends</h3>
                            {['Jan', 'Feb', 'Mar'].map((m, i) => {
                                const vals = [1.2, 1.8, 2.4];
                                return (
                                    <div key={m} style={{ marginBottom: '10px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                                            <span style={{ fontSize: '12px', color: '#94a3b8' }}>{m} 2026</span>
                                            <span style={{ fontSize: '14px', color: '#05ff91', fontWeight: '800' }}>${vals[i]}M</span>
                                        </div>
                                        <div style={{ height: '6px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px' }}>
                                            <div style={{ height: '100%', width: `${(vals[i] / 2.4) * 100}%`, background: 'linear-gradient(90deg, #f97316, #05ff91)', borderRadius: '100px' }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}

            {/* Sales Velocity */}
            {tab === 'velocity' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', fontWeight: '800', color: '#818cf8', fontSize: '16px' }}>⚡ Sales Velocity Formula</h3>
                        <div style={{ background: 'rgba(129,140,248,0.05)', borderRadius: '16px', padding: '24px', border: '1px solid rgba(129,140,248,0.1)', textAlign: 'center', marginBottom: '24px' }}>
                            <div style={{ fontSize: '14px', color: '#94a3b8', marginBottom: '8px' }}>Velocity = (Deals × Avg Size × Win Rate) ÷ Cycle Length</div>
                            <div style={{ fontSize: '28px', fontWeight: '900', color: '#818cf8' }}>$142K / day</div>
                        </div>
                        {[
                            { label: '# Qualified Deals', value: '38', color: '#00d2ff' },
                            { label: 'Avg Deal Size', value: '$285K', color: '#05ff91' },
                            { label: 'Win Rate', value: '34%', color: '#f59e0b' },
                            { label: 'Avg Cycle Length', value: '26 days', color: '#ec4899' },
                        ].map(m => (
                            <div key={m.label} style={{ display: 'flex', justifyContent: 'space-between', padding: '14px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <span style={{ fontSize: '14px', color: '#94a3b8' }}>{m.label}</span>
                                <span style={{ fontSize: '18px', fontWeight: '900', color: m.color }}>{m.value}</span>
                            </div>
                        ))}
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', fontWeight: '800', color: '#05ff91', fontSize: '16px' }}>🏆 Rep Leaderboard</h3>
                        {[
                            { name: 'Sara Ali', deals: 11, revenue: '$2.8M', velocity: '5.1x', rank: 1 },
                            { name: 'John Doe', deals: 14, revenue: '$3.2M', velocity: '4.8x', rank: 2 },
                            { name: 'Ahmed Hassan', deals: 8, revenue: '$1.9M', velocity: '3.6x', rank: 3 },
                            { name: 'Maria Garcia', deals: 5, revenue: '$950K', velocity: '2.9x', rank: 4 },
                        ].map(r => (
                            <div key={r.name} style={{ display: 'flex', alignItems: 'center', gap: '14px', padding: '14px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <span style={{ fontSize: '20px', fontWeight: '900', color: r.rank === 1 ? '#f59e0b' : r.rank === 2 ? '#94a3b8' : '#964B00', width: '28px' }}>#{r.rank}</span>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontWeight: '800', fontSize: '14px' }}>{r.name}</div>
                                    <div style={{ fontSize: '11px', color: '#64748b' }}>{r.deals} deals · {r.revenue}</div>
                                </div>
                                <span style={{ fontSize: '18px', fontWeight: '900', color: '#05ff91' }}>{r.velocity}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
