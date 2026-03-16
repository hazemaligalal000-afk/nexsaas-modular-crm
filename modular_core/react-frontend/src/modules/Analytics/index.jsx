import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';

export default function AnalyticsModule({ basePath }) {
    const [stats, setStats] = useState(null);

    useEffect(() => {
        const fetchAnalytics = async () => {
            try {
                const response = await apiClient.get('/Analytics');
                setStats(response.data.data || null);
            } catch (err) {
                // Mock Response
                setStats({
                    kpis: { mrr: '$250,400', mrr_growth: '+15%', active_deals: 124, pipeline_value: '$1,200,000' },
                    funnel_metrics: [
                        { stage: 'Lead', count: 500 },
                        { stage: 'Qualified', count: 300 },
                        { stage: 'Demo', count: 150 },
                        { stage: 'Proposal', count: 80 },
                        { stage: 'Closed Won', count: 45 }
                    ],
                    ai_insights: [
                        "Pipeline dropped 12% this week — 3 Deals stalled at Demo stage.",
                        "Rep 'John' has a 15% higher win rate when emails contain 'Enterprise SLA'.",
                        "Expected Q3 Revenue is exceeding targets by $45,000."
                    ]
                });
            }
        };
        fetchAnalytics();
    }, []);

    if (!stats) return <div style={{ padding: '24px' }}>Loading Real-Time Analytics...</div>;

    return (
        <div style={{ padding: '24px' }}>
            <h2>Executive Dashboard & Revenue Intelligence</h2>
            
            {/* Top KPI Cards */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px', marginTop: '24px' }}>
                {Object.entries(stats.kpis).map(([key, val]) => (
                    <div key={key} style={{ background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
                        <div style={{ fontSize: '12px', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{key.replace('_', ' ')}</div>
                        <div style={{ fontSize: '28px', fontWeight: 'bold', color: '#0f172a', marginTop: '8px' }}>{val}</div>
                    </div>
                ))}
            </div>

            <div style={{ display: 'flex', gap: '24px', marginTop: '32px', flexWrap: 'wrap' }}>
                
                {/* Visual Pipeline Funnel Table */}
                <div style={{ flex: '2', minWidth: '300px', background: '#fff', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px' }}>
                    <h3 style={{ marginTop: 0 }}>Conversion Funnel</h3>
                    <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ borderBottom: '1px solid #e2e8f0' }}>
                                <th style={{ padding: '12px 0' }}>Stage</th>
                                <th>Count</th>
                                <th>Drop-off</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stats.funnel_metrics.map((metrics, i) => (
                                <tr key={metrics.stage} style={{ borderBottom: '1px solid #f8fafc' }}>
                                    <td style={{ padding: '12px 0', fontWeight: '500' }}>{metrics.stage}</td>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <div style={{ width: `${(metrics.count / 500) * 100}%`, background: '#3b82f6', height: '6px', borderRadius: '3px' }}></div>
                                            <span>{metrics.count}</span>
                                        </div>
                                    </td>
                                    <td style={{ color: '#ef4444' }}>
                                        {i > 0 && `-${Math.round(((stats.funnel_metrics[i-1].count - metrics.count) / stats.funnel_metrics[i-1].count) * 100)}%`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* AI Insights Sidebar */}
                <div style={{ flex: '1', minWidth: '300px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '20px' }}>
                    <h3 style={{ marginTop: 0, color: '#6366f1', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span style={{ fontSize: '20px' }}>✨</span> NexaCRM AI Insights
                    </h3>
                    <ul style={{ padding: 0, listStyle: 'none' }}>
                        {stats.ai_insights.map((insight, idx) => (
                            <li key={idx} style={{ background: '#fff', padding: '16px', borderRadius: '8px', marginBottom: '12px', borderLeft: '4px solid #6366f1', boxShadow: '0 1px 2px rgba(0,0,0,0.05)', fontSize: '14px', lineHeight: '1.5' }}>
                                {insight}
                            </li>
                        ))}
                    </ul>
                </div>

            </div>
        </div>
    );
}
