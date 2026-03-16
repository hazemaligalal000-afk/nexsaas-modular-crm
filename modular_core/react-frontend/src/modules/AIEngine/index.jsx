import React, { useState, useEffect } from 'react';

const AI_FEATURES = [
    { id: 'neural_scoring', name: 'Neural Lead Scoring', icon: '🧠', status: 'active', accuracy: 94.2, desc: 'ML-powered lead quality prediction', category: 'Scoring' },
    { id: 'sentiment', name: 'Sentiment Analysis', icon: '💬', status: 'active', accuracy: 91.8, desc: 'Real-time email/call sentiment detection', category: 'NLP' },
    { id: 'predictive_close', name: 'Predictive Closing', icon: '🎯', status: 'active', accuracy: 87.5, desc: 'Probability-based deal closing forecast', category: 'Prediction' },
    { id: 'churn', name: 'Churn Detection', icon: '🔴', status: 'active', accuracy: 92.1, desc: 'Early warning for at-risk accounts', category: 'Prediction' },
    { id: 'coaching', name: 'Automatic Coaching', icon: '🎓', status: 'active', accuracy: 89.3, desc: 'AI-driven rep performance coaching', category: 'Automation' },
    { id: 'email_drafter', name: 'Email Drafter AI', icon: '✉️', status: 'active', accuracy: 96.4, desc: 'GPT-powered email composition', category: 'NLP' },
    { id: 'smart_followups', name: 'Smart Follow-ups', icon: '📅', status: 'active', accuracy: 88.7, desc: 'Optimized timing for outreach', category: 'Automation' },
    { id: 'intent', name: 'Intent Detection', icon: '🔍', status: 'active', accuracy: 85.9, desc: 'Buyer intent signal detection', category: 'NLP' },
    { id: 'behavioral', name: 'Behavioral Alerts', icon: '🔔', status: 'active', accuracy: 93.6, desc: 'Real-time engagement pattern alerts', category: 'Prediction' },
    { id: 'growth', name: 'Growth Forecasting', icon: '📈', status: 'active', accuracy: 90.1, desc: 'Revenue trajectory prediction', category: 'Prediction' },
    { id: 'revenue_guard', name: 'Revenue Guard™', icon: '🛡️', status: 'active', accuracy: 95.8, desc: 'Pipeline risk monitoring system', category: 'Prediction' },
    { id: 'nlp_search', name: 'NLP Search', icon: '🔎', status: 'active', accuracy: 97.2, desc: 'Natural language CRM search', category: 'NLP' },
    { id: 'relationship', name: 'Relationship Health', icon: '💚', status: 'active', accuracy: 88.4, desc: 'Account relationship scoring', category: 'Scoring' },
    { id: 'dedup', name: 'Auto-DeDuplication', icon: '♻️', status: 'active', accuracy: 99.1, desc: 'Intelligent record deduplication', category: 'Automation' },
    { id: 'dynamic_pricing', name: 'Dynamic Pricing AI', icon: '💲', status: 'active', accuracy: 86.3, desc: 'Market-aware pricing optimization', category: 'Prediction' },
    { id: 'market_intel', name: 'Market Intelligence', icon: '🌐', status: 'active', accuracy: 91.5, desc: 'Competitive landscape analysis', category: 'NLP' },
    { id: 'anomaly', name: 'Anomaly Detection', icon: '⚡', status: 'active', accuracy: 94.7, desc: 'Unusual pattern identification', category: 'Prediction' },
    { id: 'lead_research', name: 'Lead Research', icon: '📋', status: 'active', accuracy: 89.9, desc: 'Automated prospect enrichment', category: 'Automation' },
    { id: 'predictive_ltv', name: 'Predictive LTV', icon: '💎', status: 'active', accuracy: 87.2, desc: 'Customer lifetime value forecast', category: 'Scoring' },
];

const INSIGHTS = [
    { type: 'alert', message: 'Deal "Amazon Web Services Migration" has 87% probability of closing this quarter', priority: 'high', time: '2m ago' },
    { type: 'coaching', message: 'Rep Sara Ali has 23% lower email response rate — suggest template optimization', priority: 'medium', time: '15m ago' },
    { type: 'churn', message: 'Account "Meta Platforms" engagement dropped 45% — schedule executive touch', priority: 'critical', time: '1h ago' },
    { type: 'opportunity', message: 'Lead score for Jensen Huang (NVIDIA) jumped to 92 — recommend immediate outreach', priority: 'high', time: '2h ago' },
    { type: 'revenue', message: 'Revenue Guard™: $3.5M pipeline at risk due to 3 stalled deals in Negotiation', priority: 'critical', time: '3h ago' },
    { type: 'forecast', message: 'Q2 revenue forecast: $8.2M (+14.2% vs Q1) — 3 enterprise deals accelerating', priority: 'medium', time: '5h ago' },
    { type: 'dedup', message: 'Auto-DeDup found 12 duplicate contacts — 8 merged automatically, 4 need review', priority: 'low', time: '6h ago' },
];

const LEAD_SCORES = [
    { name: 'Jensen Huang', company: 'NVIDIA', score: 92, trend: '+8', signals: ['High intent', 'Multiple visits', 'Downloaded whitepaper'] },
    { name: 'Tim Cook', company: 'Apple', score: 78, trend: '+3', signals: ['Email opened', 'Pricing page visit'] },
    { name: 'Sundar Pichai', company: 'Alphabet', score: 95, trend: '+12', signals: ['Demo scheduled', 'Proposal requested', 'Budget confirmed'] },
    { name: 'Mark Zuckerberg', company: 'Meta', score: 64, trend: '-5', signals: ['Low engagement', 'Competitor evaluation'] },
];

const PRIORITY_COLORS = { critical: '#ef4444', high: '#f59e0b', medium: '#00d2ff', low: '#64748b' };
const CATEGORY_COLORS = { Scoring: '#ec4899', NLP: '#818cf8', Prediction: '#f59e0b', Automation: '#05ff91' };

export default function AIEngine() {
    const [tab, setTab] = useState('dashboard');
    const [searchQuery, setSearchQuery] = useState('');
    const [nlpResult, setNlpResult] = useState(null);
    const [emailDraft, setEmailDraft] = useState('');
    const [emailPrompt, setEmailPrompt] = useState('');
    const [generating, setGenerating] = useState(false);
    const [catFilter, setCatFilter] = useState('all');

    const avgAccuracy = (AI_FEATURES.reduce((s, f) => s + f.accuracy, 0) / AI_FEATURES.length).toFixed(1);

    const handleNLPSearch = () => {
        if (!searchQuery.trim()) return;
        const q = searchQuery.toLowerCase();
        const results = [];
        if (q.includes('deal') || q.includes('pipeline')) results.push({ type: 'Deal', name: 'Amazon Web Services Migration', detail: '$3.5M — Proposal stage' });
        if (q.includes('lead') || q.includes('nvidia') || q.includes('jensen')) results.push({ type: 'Lead', name: 'Jensen Huang', detail: 'NVIDIA — Score: 92' });
        if (q.includes('account') || q.includes('tesla')) results.push({ type: 'Account', name: 'Tesla Inc', detail: 'Automotive — Active' });
        if (q.includes('revenue') || q.includes('forecast')) results.push({ type: 'Forecast', name: 'Q2 2026 Revenue', detail: '$8.2M projected (+14.2%)' });
        if (results.length === 0) results.push({ type: 'AI', name: 'No exact match', detail: `Searched across 24 records for "${searchQuery}"` });
        setNlpResult(results);
    };

    const generateEmail = () => {
        if (!emailPrompt.trim()) return;
        setGenerating(true);
        setTimeout(() => {
            setEmailDraft(`Subject: Partnership Opportunity — ${emailPrompt}\n\nDear [Name],\n\nI hope this email finds you well. I'm reaching out regarding ${emailPrompt}.\n\nBased on our AI analysis of your company's growth trajectory and strategic priorities, I believe there's a compelling opportunity for us to collaborate.\n\nKey value propositions:\n• 38% improvement in operational efficiency\n• $2.4M projected annual savings\n• 99.4% platform uptime SLA\n\nWould you be available for a 15-minute discovery call this week? I've identified Thursday at 2 PM as an optimal time based on your engagement patterns.\n\nBest regards,\nNexa Intelligence™ AI Sales Assistant`);
            setGenerating(false);
        }, 1500);
    };

    const tabs = [
        { id: 'dashboard', label: '🏠 Command Center' },
        { id: 'features', label: '⚡ AI Features' },
        { id: 'scoring', label: '🧠 Lead Scoring' },
        { id: 'search', label: '🔎 NLP Search' },
        { id: 'email', label: '✉️ Email AI' },
        { id: 'predictions', label: '📈 Predictions' },
    ];

    const filteredFeatures = catFilter === 'all' ? AI_FEATURES : AI_FEATURES.filter(f => f.category === catFilter);

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(135deg, rgba(129,140,248,0.1) 0%, rgba(236,72,153,0.08) 50%, rgba(5,255,145,0.05) 100%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(129,140,248,0.15)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>🤖</span> AI Engine
                            <span style={{ fontSize: '12px', background: 'linear-gradient(135deg, #818cf8, #ec4899)', WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent', fontWeight: '900' }}>NEXA INTELLIGENCE™</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            {AI_FEATURES.length} AI models active · Avg accuracy: <span style={{ color: '#05ff91', fontWeight: '800' }}>{avgAccuracy}%</span> · Processing 24/7
                        </p>
                    </div>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <div style={{ background: 'rgba(5,255,145,0.1)', border: '1px solid rgba(5,255,145,0.2)', padding: '8px 16px', borderRadius: '12px', fontSize: '11px', fontWeight: '800', display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#05ff91', display: 'inline-block', animation: 'pulse 2s infinite' }}></span>
                            <span style={{ color: '#05ff91' }}>ALL SYSTEMS ONLINE</span>
                        </div>
                    </div>
                </div>

                {/* KPIs */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: '14px' }}>
                    {[
                        { label: 'Active Models', value: AI_FEATURES.length, color: '#818cf8', icon: '🧠' },
                        { label: 'Avg Accuracy', value: `${avgAccuracy}%`, color: '#05ff91', icon: '🎯' },
                        { label: 'Predictions/Day', value: '12.4K', color: '#00d2ff', icon: '📊' },
                        { label: 'Revenue Protected', value: '$5.6M', color: '#f59e0b', icon: '🛡️' },
                        { label: 'Leads Scored', value: '2,847', color: '#ec4899', icon: '📋' },
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
                    <button key={t.id} onClick={() => setTab(t.id)} style={{ background: tab === t.id ? 'rgba(129,140,248,0.15)' : 'transparent', color: tab === t.id ? '#818cf8' : '#64748b', border: 'none', padding: '10px 18px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', fontSize: '12px', transition: 'all 0.2s', whiteSpace: 'nowrap' }}>
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Command Center Dashboard */}
            {tab === 'dashboard' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', color: '#818cf8', fontWeight: '800', fontSize: '16px' }}>🔔 Live AI Insights</h3>
                        {INSIGHTS.map((insight, i) => (
                            <div key={i} style={{ padding: '14px', borderLeft: `3px solid ${PRIORITY_COLORS[insight.priority]}`, background: `${PRIORITY_COLORS[insight.priority]}08`, borderRadius: '0 12px 12px 0', marginBottom: '10px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                    <div style={{ fontSize: '13px', color: '#e2e8f0', lineHeight: '1.5', flex: 1 }}>{insight.message}</div>
                                    <span style={{ fontSize: '10px', color: '#475569', whiteSpace: 'nowrap', marginLeft: '12px' }}>{insight.time}</span>
                                </div>
                                <span style={{ fontSize: '10px', color: PRIORITY_COLORS[insight.priority], fontWeight: '800', textTransform: 'uppercase', marginTop: '6px', display: 'inline-block' }}>{insight.priority}</span>
                            </div>
                        ))}
                    </div>
                    <div>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '20px' }}>
                            <h3 style={{ margin: '0 0 16px', color: '#05ff91', fontWeight: '800', fontSize: '16px' }}>📊 Model Performance</h3>
                            {AI_FEATURES.slice(0, 6).map(f => (
                                <div key={f.id} style={{ marginBottom: '12px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                                        <span style={{ fontSize: '12px', color: '#94a3b8' }}>{f.icon} {f.name}</span>
                                        <span style={{ fontSize: '12px', color: f.accuracy > 93 ? '#05ff91' : f.accuracy > 88 ? '#00d2ff' : '#f59e0b', fontWeight: '800' }}>{f.accuracy}%</span>
                                    </div>
                                    <div style={{ height: '4px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden' }}>
                                        <div style={{ height: '100%', width: `${f.accuracy}%`, background: f.accuracy > 93 ? '#05ff91' : f.accuracy > 88 ? '#00d2ff' : '#f59e0b', borderRadius: '100px' }} />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 16px', color: '#ec4899', fontWeight: '800', fontSize: '16px' }}>⚡ Quick Actions</h3>
                            {[
                                { label: 'Run Lead Scoring Batch', action: 'scoring', icon: '🧠' },
                                { label: 'Generate Pipeline Forecast', action: 'predictions', icon: '📈' },
                                { label: 'Scan for Duplicates', action: 'features', icon: '♻️' },
                                { label: 'Draft Outreach Email', action: 'email', icon: '✉️' },
                            ].map(a => (
                                <button key={a.label} onClick={() => setTab(a.action)} style={{ display: 'flex', alignItems: 'center', gap: '10px', width: '100%', background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.05)', borderRadius: '12px', padding: '14px 16px', color: '#e2e8f0', cursor: 'pointer', fontSize: '13px', fontWeight: '600', marginBottom: '8px', transition: 'all 0.2s', textAlign: 'left' }}
                                    onMouseEnter={e => { e.currentTarget.style.borderColor = '#818cf8'; e.currentTarget.style.background = 'rgba(129,140,248,0.05)'; }}
                                    onMouseLeave={e => { e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)'; e.currentTarget.style.background = 'rgba(255,255,255,0.03)'; }}
                                >{a.icon} {a.label}</button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* AI Features Grid */}
            {tab === 'features' && (
                <div>
                    <div style={{ display: 'flex', gap: '6px', marginBottom: '20px', flexWrap: 'wrap' }}>
                        {['all', ...Object.keys(CATEGORY_COLORS)].map(c => (
                            <button key={c} onClick={() => setCatFilter(c)} style={{ background: catFilter === c ? `${CATEGORY_COLORS[c] || '#818cf8'}20` : 'rgba(255,255,255,0.03)', color: catFilter === c ? (CATEGORY_COLORS[c] || '#818cf8') : '#64748b', border: 'none', padding: '8px 16px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '12px', textTransform: 'capitalize' }}>
                                {c === 'all' ? `All (${AI_FEATURES.length})` : `${c} (${AI_FEATURES.filter(f => f.category === c).length})`}
                            </button>
                        ))}
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '16px' }}>
                        {filteredFeatures.map(f => (
                            <div key={f.id} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)', transition: 'all 0.3s' }}
                                onMouseEnter={e => { e.currentTarget.style.borderColor = `${CATEGORY_COLORS[f.category]}40`; e.currentTarget.style.transform = 'translateY(-2px)'; }}
                                onMouseLeave={e => { e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)'; e.currentTarget.style.transform = 'translateY(0)'; }}
                            >
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px' }}>
                                    <div style={{ fontSize: '28px' }}>{f.icon}</div>
                                    <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                                        <span style={{ background: `${CATEGORY_COLORS[f.category]}15`, color: CATEGORY_COLORS[f.category], padding: '3px 10px', borderRadius: '100px', fontSize: '10px', fontWeight: '800' }}>{f.category}</span>
                                        <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#05ff91' }}></span>
                                    </div>
                                </div>
                                <div style={{ fontWeight: '800', fontSize: '15px', marginBottom: '4px' }}>{f.name}</div>
                                <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '14px', lineHeight: '1.4' }}>{f.desc}</div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontSize: '12px', color: '#64748b' }}>Accuracy</span>
                                    <span style={{ fontSize: '16px', fontWeight: '900', color: f.accuracy > 93 ? '#05ff91' : f.accuracy > 88 ? '#00d2ff' : '#f59e0b' }}>{f.accuracy}%</span>
                                </div>
                                <div style={{ height: '4px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden', marginTop: '6px' }}>
                                    <div style={{ height: '100%', width: `${f.accuracy}%`, background: `linear-gradient(90deg, ${CATEGORY_COLORS[f.category]}, ${f.accuracy > 93 ? '#05ff91' : '#00d2ff'})`, borderRadius: '100px' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Lead Scoring */}
            {tab === 'scoring' && (
                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                        <div style={{ padding: '20px 24px', borderBottom: '1px solid rgba(255,255,255,0.05)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <h3 style={{ margin: 0, fontWeight: '800', fontSize: '16px' }}>🧠 Neural Lead Scores — Real-Time</h3>
                            <span style={{ fontSize: '11px', color: '#05ff91', fontWeight: '700' }}>Updated 2 min ago</span>
                        </div>
                        {LEAD_SCORES.map(lead => (
                            <div key={lead.name} style={{ padding: '20px 24px', borderBottom: '1px solid rgba(255,255,255,0.03)', display: 'flex', alignItems: 'center', gap: '16px' }}
                                onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                <div style={{ width: '56px', height: '56px', borderRadius: '50%', background: `conic-gradient(${lead.score > 80 ? '#05ff91' : lead.score > 60 ? '#f59e0b' : '#ef4444'} ${lead.score * 3.6}deg, rgba(255,255,255,0.05) 0deg)`, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                                    <div style={{ width: '44px', height: '44px', borderRadius: '50%', background: '#0a0a0a', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '16px', fontWeight: '900', color: lead.score > 80 ? '#05ff91' : '#f59e0b' }}>{lead.score}</div>
                                </div>
                                <div style={{ flex: 1 }}>
                                    <div style={{ fontWeight: '800', fontSize: '15px' }}>{lead.name}</div>
                                    <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>{lead.company}</div>
                                    <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                                        {lead.signals.map(s => (
                                            <span key={s} style={{ background: 'rgba(129,140,248,0.1)', color: '#818cf8', padding: '2px 8px', borderRadius: '6px', fontSize: '10px', fontWeight: '700' }}>{s}</span>
                                        ))}
                                    </div>
                                </div>
                                <div style={{ textAlign: 'right' }}>
                                    <div style={{ color: lead.trend.startsWith('+') ? '#05ff91' : '#ef4444', fontWeight: '800', fontSize: '14px' }}>{lead.trend}</div>
                                    <div style={{ fontSize: '10px', color: '#475569' }}>7d trend</div>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#ec4899', fontSize: '14px' }}>Score Distribution</h3>
                            {[{ range: '90-100', count: 2, color: '#05ff91', pct: 50 }, { range: '70-89', count: 1, color: '#00d2ff', pct: 25 }, { range: '50-69', count: 1, color: '#f59e0b', pct: 25 }, { range: '0-49', count: 0, color: '#ef4444', pct: 0 }].map(b => (
                                <div key={b.range} style={{ marginBottom: '12px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '12px', marginBottom: '4px' }}>
                                        <span style={{ color: '#94a3b8' }}>{b.range}</span>
                                        <span style={{ color: b.color, fontWeight: '800' }}>{b.count} leads</span>
                                    </div>
                                    <div style={{ height: '6px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px' }}>
                                        <div style={{ height: '100%', width: `${b.pct}%`, background: b.color, borderRadius: '100px' }} />
                                    </div>
                                </div>
                            ))}
                        </div>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 12px', fontWeight: '800', color: '#818cf8', fontSize: '14px' }}>Scoring Factors</h3>
                            {['Email engagement (25%)', 'Website behavior (20%)', 'Company size (15%)', 'Budget signals (15%)', 'Decision authority (10%)', 'Past purchases (10%)', 'Social signals (5%)'].map(f => (
                                <div key={f} style={{ fontSize: '12px', color: '#94a3b8', padding: '6px 0', borderBottom: '1px solid rgba(255,255,255,0.03)' }}>✦ {f}</div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* NLP Search */}
            {tab === 'search' && (
                <div style={{ maxWidth: '800px', margin: '0 auto' }}>
                    <div style={{ background: 'rgba(129,140,248,0.05)', borderRadius: '24px', padding: '40px', border: '1px solid rgba(129,140,248,0.15)', textAlign: 'center', marginBottom: '24px' }}>
                        <h3 style={{ margin: '0 0 8px', fontSize: '20px', fontWeight: '900' }}>🔎 Natural Language CRM Search</h3>
                        <p style={{ color: '#64748b', fontSize: '13px', marginBottom: '24px' }}>Ask anything about your CRM data in plain English</p>
                        <div style={{ display: 'flex', gap: '12px' }}>
                            <input value={searchQuery} onChange={e => setSearchQuery(e.target.value)} onKeyDown={e => e.key === 'Enter' && handleNLPSearch()} placeholder='Try: "Show me all deals in pipeline" or "Find NVIDIA lead"'
                                style={{ flex: 1, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(129,140,248,0.2)', borderRadius: '14px', padding: '14px 20px', color: '#fff', fontSize: '15px', outline: 'none' }} />
                            <button onClick={handleNLPSearch} style={{ background: 'linear-gradient(135deg, #818cf8, #ec4899)', color: '#fff', border: 'none', padding: '14px 28px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer', fontSize: '14px' }}>Search</button>
                        </div>
                        <div style={{ display: 'flex', gap: '8px', marginTop: '16px', justifyContent: 'center', flexWrap: 'wrap' }}>
                            {['deals in pipeline', 'leads scored above 80', 'tesla account', 'revenue forecast'].map(s => (
                                <button key={s} onClick={() => { setSearchQuery(s); handleNLPSearch(); }} style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: '100px', padding: '6px 14px', color: '#94a3b8', fontSize: '11px', cursor: 'pointer' }}>{s}</button>
                            ))}
                        </div>
                    </div>
                    {nlpResult && (
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#818cf8', fontSize: '14px' }}>Results ({nlpResult.length})</h3>
                            {nlpResult.map((r, i) => (
                                <div key={i} style={{ padding: '16px', borderRadius: '12px', background: 'rgba(255,255,255,0.02)', marginBottom: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <div>
                                        <span style={{ fontSize: '10px', background: 'rgba(129,140,248,0.1)', color: '#818cf8', padding: '2px 8px', borderRadius: '6px', fontWeight: '800', marginRight: '8px' }}>{r.type}</span>
                                        <span style={{ fontWeight: '700', fontSize: '15px' }}>{r.name}</span>
                                        <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>{r.detail}</div>
                                    </div>
                                    <button style={{ background: 'rgba(5,255,145,0.1)', color: '#05ff91', border: 'none', padding: '6px 14px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '12px' }}>View →</button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Email AI */}
            {tab === 'email' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', fontWeight: '800', color: '#818cf8', fontSize: '16px' }}>✉️ AI Email Composer</h3>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '8px', textTransform: 'uppercase' }}>What is this email about?</label>
                        <textarea value={emailPrompt} onChange={e => setEmailPrompt(e.target.value)} placeholder="e.g. Follow up on cloud migration proposal with Amazon"
                            style={{ width: '100%', height: '100px', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '14px', padding: '14px', color: '#fff', fontSize: '14px', resize: 'none', boxSizing: 'border-box', outline: 'none' }} />
                        <button onClick={generateEmail} disabled={generating} style={{ marginTop: '16px', background: generating ? '#334155' : 'linear-gradient(135deg, #818cf8, #ec4899)', color: '#fff', border: 'none', padding: '12px 24px', borderRadius: '12px', fontWeight: '800', cursor: generating ? 'wait' : 'pointer', width: '100%', fontSize: '14px' }}>
                            {generating ? '🔄 Generating with GPT-4...' : '✨ Generate Email'}
                        </button>
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 16px', fontWeight: '800', color: '#05ff91', fontSize: '16px' }}>📝 Generated Draft</h3>
                        {emailDraft ? (
                            <div>
                                <pre style={{ background: 'rgba(255,255,255,0.03)', borderRadius: '12px', padding: '20px', fontSize: '13px', color: '#e2e8f0', whiteSpace: 'pre-wrap', lineHeight: '1.6', border: '1px solid rgba(255,255,255,0.05)', maxHeight: '360px', overflowY: 'auto' }}>{emailDraft}</pre>
                                <div style={{ display: 'flex', gap: '10px', marginTop: '16px' }}>
                                    <button style={{ background: '#05ff91', color: '#000', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', flex: 1 }}>📧 Send Email</button>
                                    <button onClick={() => navigator.clipboard.writeText(emailDraft)} style={{ background: 'rgba(255,255,255,0.05)', color: '#94a3b8', border: 'none', padding: '10px 20px', borderRadius: '10px', cursor: 'pointer', fontWeight: '600' }}>📋 Copy</button>
                                </div>
                            </div>
                        ) : (
                            <div style={{ padding: '60px 20px', textAlign: 'center', color: '#475569', fontSize: '13px' }}>
                                <div style={{ fontSize: '48px', marginBottom: '12px' }}>✉️</div>
                                Enter a topic and click Generate to create an AI-powered email draft
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Predictions */}
            {tab === 'predictions' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', fontWeight: '800', color: '#f59e0b', fontSize: '16px' }}>📈 Revenue Forecast</h3>
                        {[
                            { quarter: 'Q1 2026', value: '$5.6M', status: 'Actual', pct: 70 },
                            { quarter: 'Q2 2026', value: '$8.2M', status: 'Projected', pct: 100 },
                            { quarter: 'Q3 2026', value: '$11.4M', status: 'Projected', pct: 90 },
                            { quarter: 'Q4 2026', value: '$14.8M', status: 'Projected', pct: 75 },
                        ].map(q => (
                            <div key={q.quarter} style={{ marginBottom: '16px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                                    <span style={{ fontSize: '13px', color: '#94a3b8' }}>{q.quarter}</span>
                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                        <span style={{ fontSize: '16px', color: '#05ff91', fontWeight: '900' }}>{q.value}</span>
                                        <span style={{ fontSize: '10px', color: q.status === 'Actual' ? '#05ff91' : '#f59e0b', background: q.status === 'Actual' ? 'rgba(5,255,145,0.1)' : 'rgba(245,158,11,0.1)', padding: '2px 8px', borderRadius: '6px', fontWeight: '800' }}>{q.status}</span>
                                    </div>
                                </div>
                                <div style={{ height: '8px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px' }}>
                                    <div style={{ height: '100%', width: `${q.pct}%`, background: q.status === 'Actual' ? '#05ff91' : 'linear-gradient(90deg, #f59e0b, #f97316)', borderRadius: '100px' }} />
                                </div>
                            </div>
                        ))}
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 14px', fontWeight: '800', color: '#ec4899', fontSize: '14px' }}>🔴 Churn Risk Accounts</h3>
                            {[{ name: 'Meta Platforms', risk: 72, reason: '45% engagement drop' }, { name: 'Netflix', risk: 34, reason: 'Contract renewal in 30 days' }].map(a => (
                                <div key={a.name} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                    <div>
                                        <div style={{ fontWeight: '700', fontSize: '14px' }}>{a.name}</div>
                                        <div style={{ fontSize: '11px', color: '#64748b' }}>{a.reason}</div>
                                    </div>
                                    <span style={{ fontSize: '18px', fontWeight: '900', color: a.risk > 50 ? '#ef4444' : '#f59e0b' }}>{a.risk}%</span>
                                </div>
                            ))}
                        </div>
                        <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <h3 style={{ margin: '0 0 14px', fontWeight: '800', color: '#00d2ff', fontSize: '14px' }}>💎 Predictive LTV — Top 4</h3>
                            {[{ name: 'Amazon', ltv: '$12.4M' }, { name: 'Alphabet', ltv: '$8.8M' }, { name: 'Tesla', ltv: '$6.2M' }, { name: 'NVIDIA', ltv: '$4.5M' }].map(c => (
                                <div key={c.name} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                    <span style={{ fontSize: '13px', color: '#94a3b8' }}>{c.name}</span>
                                    <span style={{ color: '#00d2ff', fontWeight: '900', fontSize: '15px' }}>{c.ltv}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
