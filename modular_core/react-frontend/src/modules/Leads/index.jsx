import React, { useState, useEffect } from 'react';
import { useAuth, Can } from '../../core/AuthContext';

/**
 * Leads Management Module
 * Demonstrates RBAC (Create/Read/Import) and Tenant Isolation.
 */
export default function LeadsPage() {
    const { can } = useAuth();
    const [leads, setLeads] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        const token = localStorage.getItem('access_token');
        fetch('/api/leads', {
            headers: { 'Authorization': `Bearer ${token}` }
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) setLeads(d.data);
            setLoading(false);
        })
        .catch(() => setLoading(false));
    }, []);

    const filteredLeads = leads.filter(lead => 
        (lead.first_name + ' ' + lead.last_name).toLowerCase().includes(searchTerm.toLowerCase()) ||
        lead.email.toLowerCase().includes(searchTerm.toLowerCase())
    );

    return (
        <div style={styles.container}>
            <header style={styles.header}>
                <div>
                    <h1 style={styles.title}>Leads Management</h1>
                    <p style={styles.subtitle}>Manage and track your omnichannel sales prospects</p>
                </div>
                <div style={styles.actions}>
                    <Can module="leads" action="import">
                        <button style={styles.secondaryBtn}>📥 Import CSV</button>
                    </Can>
                    <Can module="leads" action="create">
                        <button style={styles.primaryBtn}>+ Add New Lead</button>
                    </Can>
                </div>
            </header>

            <div style={styles.contentCard}>
                <div style={styles.tableToolbar}>
                    <div style={styles.searchWrapper}>
                        <span style={styles.searchIcon}>🔍</span>
                        <input 
                            type="text" 
                            placeholder="Search by name or email..." 
                            style={styles.searchInput}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>

                {loading ? (
                    <div style={styles.loader}>Fetching your records...</div>
                ) : (
                    <table style={styles.table}>
                        <thead>
                            <tr style={styles.thRow}>
                                <th style={styles.th}>Name</th>
                                <th style={styles.th}>Source</th>
                                <th style={styles.th}>Score</th>
                                <th style={styles.th}>Stage</th>
                                <th style={styles.th}>Created</th>
                                <th style={styles.th}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredLeads.map((lead) => (
                                <tr key={lead.id} style={styles.tr}>
                                    <td style={styles.td}>
                                        <div style={styles.leadInfo}>
                                            <span style={styles.leadName}>{lead.first_name} {lead.last_name}</span>
                                            <span style={styles.leadEmail}>{lead.email}</span>
                                        </div>
                                    </td>
                                    <td style={styles.td}>
                                        <span style={styles.sourceBadge}>{lead.source || 'Direct'}</span>
                                    </td>
                                    <td style={styles.td}>
                                        <div style={{...styles.scoreBadge, background: getScoreColor(lead.ai_score)}}>
                                            {lead.ai_score || 0}%
                                        </div>
                                    </td>
                                    <td style={styles.td}>
                                        <span style={styles.stageTag}>{lead.lifecycle_stage}</span>
                                    </td>
                                    <td style={styles.td}>{new Date(lead.created_at).toLocaleDateString()}</td>
                                    <td style={styles.td}>
                                        <div style={styles.rowActions}>
                                            <Can module="leads" action="update">
                                                <button style={styles.iconBtn} title="Edit Lead">✏️</button>
                                            </Can>
                                            <button 
                                                onClick={() => {
                                                    alert("🚀 AI Copilot: Generating follow-up for " + lead.first_name + "...");
                                                    fetch('/api/ai/claude/email/draft', {
                                                        method: 'POST',
                                                        headers: { 'Content-Type': 'application/json', 'X-API-Key': localStorage.getItem('api_key') },
                                                        body: JSON.stringify({
                                                            recipient_name: lead.first_name,
                                                            company_name: lead.company_name || 'Prospect Co',
                                                            scenario: 'follow_up',
                                                            goal: 'book_meeting',
                                                            key_points: ['Personalized solution', 'Next-gen CRM platform']
                                                        })
                                                    }).then(r => r.json()).then(d => {
                                                        console.log("AI Draft:", d);
                                                        alert("✨ AI Suggested Subject: " + d.data.professional.subject);
                                                    });
                                                }}
                                                style={{...styles.iconBtn, color: '#3b82f6'}} 
                                                title="AI Copilot Draft"
                                            >
                                                🤖
                                            </button>
                                            <Can module="leads" action="delete">
                                                <button style={{...styles.iconBtn, color: '#ef4444'}} title="Delete Lead">🗑️</button>
                                            </Can>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                {!loading && filteredLeads.length === 0 && (
                    <div style={styles.emptyState}>
                        <p>No leads found matching your criteria.</p>
                    </div>
                )}
            </div>
        </div>
    );
}

const getScoreColor = (score) => {
    if (!score) return '#94a3b8';
    if (score >= 80) return '#10b981';
    if (score >= 50) return '#f59e0b';
    return '#ef4444';
};

const styles = {
    container: { padding: '32px', maxWidth: '1200px', margin: '0 auto' },
    header: { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '32px' },
    title: { fontSize: '24px', fontWeight: '700', color: '#0f172a', marginBottom: '4px' },
    subtitle: { fontSize: '14px', color: '#64748b' },
    actions: { display: 'flex', gap: '12px' },
    primaryBtn: { background: '#3b82f6', color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '8px', fontWeight: '600', cursor: 'pointer' },
    secondaryBtn: { background: '#fff', color: '#475569', border: '1px solid #e2e8f0', padding: '10px 20px', borderRadius: '8px', fontWeight: '600', cursor: 'pointer' },
    contentCard: { background: '#fff', borderRadius: '12px', border: '1px solid #e2e8f0', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' },
    tableToolbar: { padding: '20px', borderBottom: '1px solid #f1f5f9', background: '#f8fafc' },
    searchWrapper: { position: 'relative', maxWidth: '300px' },
    searchIcon: { position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', opacity: 0.5 },
    searchInput: { width: '100%', padding: '10px 12px 10px 40px', borderRadius: '8px', border: '1px solid #e2e8f0', fontSize: '14px' },
    table: { width: '100%', borderCollapse: 'collapse', textAlign: 'left' },
    thRow: { background: '#f8fafc', borderBottom: '1px solid #e2e8f0' },
    th: { padding: '16px 20px', fontSize: '12px', fontWeight: '600', color: '#64748b', textTransform: 'uppercase' },
    tr: { borderBottom: '1px solid #f1f5f9', transition: 'background 0.2s' },
    td: { padding: '16px 20px', verticalAlign: 'middle' },
    leadInfo: { display: 'flex', flexDirection: 'column' },
    leadName: { fontSize: '14px', fontWeight: '600', color: '#0f172a' },
    leadEmail: { fontSize: '12px', color: '#64748b' },
    sourceBadge: { fontSize: '11px', background: '#eff6ff', color: '#3b82f6', padding: '4px 8px', borderRadius: '6px', fontWeight: '600' },
    scoreBadge: { width: '40px', height: '40px', borderRadius: '50%', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px', fontWeight: '700' },
    stageTag: { fontSize: '12px', color: '#475569', background: '#f1f5f9', padding: '4px 10px', borderRadius: '100px', fontWeight: '500' },
    rowActions: { display: 'flex', gap: '8px' },
    iconBtn: { background: 'none', border: 'none', cursor: 'pointer', fontSize: '16px', opacity: 0.7 },
    loader: { padding: '60px', textAlign: 'center', color: '#94a3b8' },
    emptyState: { padding: '60px', textAlign: 'center', color: '#94a3b8', fontSize: '14px' }
};
