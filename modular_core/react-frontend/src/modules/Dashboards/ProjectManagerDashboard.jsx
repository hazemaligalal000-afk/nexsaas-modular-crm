import React, { useState } from 'react';

const projects = [
  { id: 1, name: 'CRM Platform v2', status: 'In Progress', progress: 68, due: '2026-04-30', team: 5, budget: '$120K', spent: '$82K' },
  { id: 2, name: 'Mobile App Launch', status: 'At Risk',    progress: 42, due: '2026-04-15', team: 4, budget: '$85K',  spent: '$61K' },
  { id: 3, name: 'API Integration Hub', status: 'On Track', progress: 81, due: '2026-05-10', team: 3, budget: '$60K',  spent: '$49K' },
  { id: 4, name: 'Data Warehouse Migration', status: 'Planning', progress: 15, due: '2026-06-30', team: 6, budget: '$200K', spent: '$18K' },
];

const milestones = [
  { project: 'CRM Platform v2',    milestone: 'Backend API Complete',   due: 'Mar 25', status: 'done'    },
  { project: 'CRM Platform v2',    milestone: 'Frontend Integration',   due: 'Apr 10', status: 'active'  },
  { project: 'Mobile App Launch',  milestone: 'UI Design Approved',     due: 'Mar 20', status: 'overdue' },
  { project: 'Mobile App Launch',  milestone: 'Beta Testing',           due: 'Apr 05', status: 'active'  },
  { project: 'API Integration Hub','milestone': 'Auth Module',          due: 'Mar 22', status: 'done'    },
];

const timeLog = [
  { member: 'Ali Hassan',    project: 'CRM Platform v2',    hours: 38, week: 'This Week' },
  { member: 'Sara Nour',     project: 'Mobile App Launch',  hours: 42, week: 'This Week' },
  { member: 'Omar Khalid',   project: 'API Integration Hub',hours: 35, week: 'This Week' },
  { member: 'Lina Farouk',   project: 'CRM Platform v2',    hours: 40, week: 'This Week' },
];

const statusColor = { 'In Progress': '#3b82f6', 'At Risk': '#ef4444', 'On Track': '#10b981', 'Planning': '#f59e0b' };
const milestoneColor = { done: '#10b981', active: '#3b82f6', overdue: '#ef4444' };

export default function ProjectManagerDashboard() {
  const [activeTab, setActiveTab] = useState('projects');

  const kpis = [
    { label: 'Active Projects', value: '4',    icon: '📁', color: '#3b82f6' },
    { label: 'On-Time Rate',    value: '75%',  icon: '✅', color: '#10b981' },
    { label: 'Total Budget',    value: '$465K',icon: '💰', color: '#8b5cf6' },
    { label: 'Team Members',    value: '12',   icon: '👥', color: '#f59e0b' },
  ];

  return (
    <div style={s.page}>
      <div style={s.topBar}>
        <div>
          <h1 style={s.title}>Project Manager Dashboard</h1>
          <p style={s.sub}>Project portfolio · Milestones · Time tracking</p>
        </div>
        <button style={s.btn}>+ New Project</button>
      </div>

      {/* KPIs */}
      <div style={s.kpiRow}>
        {kpis.map(k => (
          <div key={k.label} style={s.kpiCard}>
            <div style={{ ...s.kpiIcon, background: k.color + '20', color: k.color }}>{k.icon}</div>
            <div style={s.kpiVal}>{k.value}</div>
            <div style={s.kpiLabel}>{k.label}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={s.tabs}>
        {['projects', 'milestones', 'time'].map(t => (
          <button key={t} onClick={() => setActiveTab(t)}
            style={{ ...s.tab, ...(activeTab === t ? s.tabActive : {}) }}>
            {t === 'projects' ? '📁 Projects' : t === 'milestones' ? '🏁 Milestones' : '⏱ Time Log'}
          </button>
        ))}
      </div>

      {activeTab === 'projects' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Project', 'Status', 'Progress', 'Due Date', 'Team', 'Budget', 'Spent'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {projects.map(p => (
                <tr key={p.id} style={s.tr}>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{p.name}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: statusColor[p.status] + '20', color: statusColor[p.status] }}>{p.status}</span>
                  </td>
                  <td style={s.td}>
                    <div style={s.progressBar}>
                      <div style={{ ...s.progressFill, width: `${p.progress}%`, background: statusColor[p.status] }} />
                    </div>
                    <span style={{ fontSize: '11px', color: '#94a3b8' }}>{p.progress}%</span>
                  </td>
                  <td style={s.td}>{p.due}</td>
                  <td style={s.td}>{p.team} members</td>
                  <td style={s.td}>{p.budget}</td>
                  <td style={s.td}>{p.spent}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {activeTab === 'milestones' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Project', 'Milestone', 'Due Date', 'Status'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {milestones.map((m, i) => (
                <tr key={i} style={s.tr}>
                  <td style={{ ...s.td, color: '#94a3b8' }}>{m.project}</td>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{m.milestone}</td>
                  <td style={s.td}>{m.due}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: milestoneColor[m.status] + '20', color: milestoneColor[m.status] }}>
                      {m.status === 'done' ? '✅ Done' : m.status === 'active' ? '🔵 Active' : '🔴 Overdue'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {activeTab === 'time' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Team Member', 'Project', 'Hours This Week', 'Status'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {timeLog.map((t, i) => (
                <tr key={i} style={s.tr}>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{t.member}</td>
                  <td style={{ ...s.td, color: '#94a3b8' }}>{t.project}</td>
                  <td style={s.td}>
                    <div style={s.progressBar}>
                      <div style={{ ...s.progressFill, width: `${(t.hours / 45) * 100}%`, background: t.hours > 40 ? '#ef4444' : '#3b82f6' }} />
                    </div>
                    <span style={{ fontSize: '11px', color: '#94a3b8' }}>{t.hours}h / 45h</span>
                  </td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: t.hours > 40 ? '#ef444420' : '#10b98120', color: t.hours > 40 ? '#ef4444' : '#10b981' }}>
                      {t.hours > 40 ? 'Overtime' : 'Normal'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

const s = {
  page:        { padding: '32px', background: '#0a0f1e', minHeight: '100%', color: '#f1f5f9' },
  topBar:      { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '28px' },
  title:       { fontSize: '24px', fontWeight: '800', color: '#f1f5f9', margin: 0 },
  sub:         { color: '#64748b', fontSize: '14px', marginTop: '4px' },
  btn:         { background: '#3b82f6', color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '14px' },
  kpiRow:      { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '28px' },
  kpiCard:     { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', padding: '20px', textAlign: 'center' },
  kpiIcon:     { width: '44px', height: '44px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px', margin: '0 auto 12px' },
  kpiVal:      { fontSize: '28px', fontWeight: '800', color: '#f1f5f9' },
  kpiLabel:    { fontSize: '12px', color: '#64748b', marginTop: '4px' },
  tabs:        { display: 'flex', gap: '8px', marginBottom: '20px' },
  tab:         { padding: '8px 18px', borderRadius: '8px', border: '1px solid #1e293b', background: 'transparent', color: '#64748b', cursor: 'pointer', fontSize: '13px', fontWeight: '600' },
  tabActive:   { background: '#1e293b', color: '#f1f5f9', borderColor: '#334155' },
  card:        { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', overflow: 'hidden' },
  table:       { width: '100%', borderCollapse: 'collapse' },
  th:          { padding: '12px 16px', textAlign: 'left', fontSize: '11px', fontWeight: '700', color: '#475569', textTransform: 'uppercase', borderBottom: '1px solid #1e293b' },
  tr:          { borderBottom: '1px solid #1e293b' },
  td:          { padding: '14px 16px', fontSize: '13px', color: '#94a3b8' },
  badge:       { padding: '3px 10px', borderRadius: '100px', fontSize: '11px', fontWeight: '700' },
  progressBar: { height: '6px', background: '#1e293b', borderRadius: '3px', marginBottom: '4px', overflow: 'hidden' },
  progressFill:{ height: '100%', borderRadius: '3px', transition: 'width 0.3s' },
};
