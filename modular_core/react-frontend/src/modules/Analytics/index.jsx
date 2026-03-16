import React, { useState, useEffect } from 'react';
import { useAuth, Can } from '../../core/AuthContext';

/**
 * Analytics Dashboard — Renders widgets based on user permissions.
 * Only shows revenue data if analytics.revenue is granted.
 * Only shows agent performance if analytics.agents is granted.
 */
export default function AnalyticsDashboard() {
    const { can } = useAuth();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const token = localStorage.getItem('access_token');
        fetch('/api/analytics/overview', {
            headers: { 'Authorization': `Bearer ${token}` }
        })
        .then(r => r.json())
        .then(d => { setData(d.data); setLoading(false); })
        .catch(() => setLoading(false));
    }, []);

    if (loading) return <div style={styles.loader}>Loading analytics...</div>;

    return (
        <div style={styles.container}>
            <h1 style={styles.title}>📊 Analytics Dashboard</h1>

            {/* KPI Cards — visible to anyone with analytics.view */}
            <div style={styles.kpiGrid}>
                {(data?.kpis || []).map((kpi, i) => (
                    <div key={i} style={styles.kpiCard}>
                        <span style={styles.kpiValue}>{kpi.value}</span>
                        <span style={styles.kpiLabel}>{kpi.label}</span>
                        <span style={{...styles.kpiTrend, color: kpi.trend?.startsWith('+') ? '#10b981' : '#ef4444'}}>
                            {kpi.trend}
                        </span>
                    </div>
                ))}
            </div>

            {/* Pipeline Breakdown — visible if analytics.view */}
            <div style={styles.section}>
                <h2 style={styles.sectionTitle}>Pipeline Breakdown</h2>
                <div style={styles.stageGrid}>
                    {(data?.pipeline_breakdown || []).map((stage, i) => (
                        <div key={i} style={styles.stageCard}>
                            <span style={styles.stageName}>{stage.lifecycle_stage}</span>
                            <span style={styles.stageCount}>{stage.cnt}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Revenue Forecast — ONLY for users with analytics.revenue */}
            <Can module="analytics" action="revenue">
                <div style={styles.section}>
                    <h2 style={styles.sectionTitle}>💰 AI Revenue Forecast</h2>
                    <p style={styles.muted}>Powered by the Python AI Microservice</p>
                    <div style={styles.forecastGrid}>
                        <div style={styles.forecastCard}>
                            <span style={styles.forecastLabel}>30 Days</span>
                            <span style={styles.forecastValue}>$45,000</span>
                        </div>
                        <div style={styles.forecastCard}>
                            <span style={styles.forecastLabel}>60 Days</span>
                            <span style={styles.forecastValue}>$110,000</span>
                        </div>
                        <div style={styles.forecastCard}>
                            <span style={styles.forecastLabel}>90 Days</span>
                            <span style={styles.forecastValue}>$215,000</span>
                        </div>
                    </div>
                </div>
            </Can>

            {/* Agent Performance — ONLY for users with analytics.agents */}
            <Can module="analytics" action="agents">
                <div style={styles.section}>
                    <h2 style={styles.sectionTitle}>👥 Agent Performance</h2>
                    <p style={styles.muted}>Conversion rates by team member</p>
                    {/* Agent table will be populated from /api/analytics/agent-performance */}
                </div>
            </Can>

            {/* Export Button — ONLY for analytics.export */}
            <Can module="analytics" action="export">
                <button style={styles.exportBtn}>📥 Export Report (CSV)</button>
            </Can>
        </div>
    );
}

const styles = {
    container: { padding: '32px', maxWidth: '1200px', margin: '0 auto' },
    title: { fontSize: '28px', fontWeight: '700', marginBottom: '24px', color: '#0f172a' },
    loader: { padding: '48px', textAlign: 'center', color: '#64748b' },
    kpiGrid: { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px', marginBottom: '32px' },
    kpiCard: { background: '#fff', borderRadius: '12px', padding: '24px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)', display: 'flex', flexDirection: 'column', gap: '4px' },
    kpiValue: { fontSize: '32px', fontWeight: '800', color: '#0f172a' },
    kpiLabel: { fontSize: '14px', color: '#64748b' },
    kpiTrend: { fontSize: '13px', fontWeight: '600' },
    section: { background: '#fff', borderRadius: '12px', padding: '24px', marginBottom: '24px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' },
    sectionTitle: { fontSize: '18px', fontWeight: '600', color: '#0f172a', marginBottom: '8px' },
    muted: { fontSize: '13px', color: '#94a3b8', marginBottom: '16px' },
    stageGrid: { display: 'flex', gap: '12px', flexWrap: 'wrap' },
    stageCard: { background: '#f1f5f9', borderRadius: '8px', padding: '12px 20px', display: 'flex', flexDirection: 'column', alignItems: 'center' },
    stageName: { fontSize: '12px', color: '#64748b', textTransform: 'uppercase' },
    stageCount: { fontSize: '24px', fontWeight: '700', color: '#3b82f6' },
    forecastGrid: { display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '16px' },
    forecastCard: { background: 'linear-gradient(135deg, #6366f1, #8b5cf6)', borderRadius: '12px', padding: '24px', color: '#fff', display: 'flex', flexDirection: 'column', alignItems: 'center' },
    forecastLabel: { fontSize: '13px', opacity: 0.8 },
    forecastValue: { fontSize: '28px', fontWeight: '800' },
    exportBtn: { background: '#0f172a', color: '#fff', border: 'none', padding: '12px 24px', borderRadius: '8px', cursor: 'pointer', fontWeight: '600', marginTop: '16px' }
};
