import React, { useState } from 'react';

const services = [
  { name: 'API Gateway',      status: 'healthy', uptime: '99.98%', latency: '42ms',  load: 28 },
  { name: 'Auth Service',     status: 'healthy', uptime: '99.99%', latency: '18ms',  load: 15 },
  { name: 'Database (PG)',    status: 'healthy', uptime: '99.95%', latency: '8ms',   load: 62 },
  { name: 'Redis Cache',      status: 'healthy', uptime: '100%',   latency: '2ms',   load: 41 },
  { name: 'AI Engine',        status: 'warning', uptime: '98.2%',  latency: '320ms', load: 87 },
  { name: 'Email Service',    status: 'healthy', uptime: '99.7%',  latency: '95ms',  load: 22 },
  { name: 'File Storage',     status: 'healthy', uptime: '99.9%',  latency: '55ms',  load: 34 },
  { name: 'WebSocket Server', status: 'down',    uptime: '94.1%',  latency: '—',     load: 0  },
];

const securityLogs = [
  { time: '09:42', event: 'Failed login attempt',     user: 'unknown@ext.com',    ip: '185.220.101.x', severity: 'high'   },
  { time: '09:15', event: 'JWT key rotation',         user: 'system',             ip: 'internal',      severity: 'info'   },
  { time: '08:58', event: 'New user created',         user: 'admin@acme.com',     ip: '10.0.1.5',      severity: 'info'   },
  { time: '08:30', event: 'Permission escalation',    user: 'rep@acme.com',       ip: '10.0.1.12',     severity: 'medium' },
  { time: '07:55', event: 'API rate limit exceeded',  user: 'api_key_7f3a',       ip: '203.0.113.x',   severity: 'medium' },
  { time: '07:20', event: 'Successful 2FA bypass',    user: 'unknown',            ip: '91.108.4.x',    severity: 'high'   },
];

const integrations = [
  { name: 'Google OAuth',   status: 'connected', lastSync: '2 min ago'  },
  { name: 'Stripe',         status: 'connected', lastSync: '5 min ago'  },
  { name: 'Slack',          status: 'connected', lastSync: '1 min ago'  },
  { name: 'SendGrid',       status: 'connected', lastSync: '10 min ago' },
  { name: 'Twilio',         status: 'warning',   lastSync: '2h ago'     },
  { name: 'GitHub Actions', status: 'connected', lastSync: '30 min ago' },
];

const statusColor = { healthy: '#10b981', warning: '#f59e0b', down: '#ef4444' };
const severityColor = { high: '#ef4444', medium: '#f59e0b', info: '#3b82f6' };

export default function ITAdminDashboard() {
  const [tab, setTab] = useState('health');

  const kpis = [
    { label: 'Services Up',    value: '6/8',   icon: '🟢', color: '#10b981' },
    { label: 'Avg Uptime',     value: '99.1%', icon: '⏱',  color: '#3b82f6' },
    { label: 'Security Alerts',value: '2',     icon: '🔐', color: '#ef4444' },
    { label: 'Active Users',   value: '47',    icon: '👥', color: '#8b5cf6' },
  ];

  return (
    <div style={s.page}>
      <div style={s.topBar}>
        <div>
          <h1 style={s.title}>IT Admin Dashboard</h1>
          <p style={s.sub}>System health · Security logs · Integrations · User management</p>
        </div>
        <button style={s.btn}>🔄 Refresh</button>
      </div>

      <div style={s.kpiRow}>
        {kpis.map(k => (
          <div key={k.label} style={s.kpiCard}>
            <div style={{ ...s.kpiIcon, background: k.color + '20', color: k.color }}>{k.icon}</div>
            <div style={s.kpiVal}>{k.value}</div>
            <div style={s.kpiLabel}>{k.label}</div>
          </div>
        ))}
      </div>

      <div style={s.tabs}>
        {['health', 'security', 'integrations'].map(t => (
          <button key={t} onClick={() => setTab(t)}
            style={{ ...s.tab, ...(tab === t ? s.tabActive : {}) }}>
            {t === 'health' ? '🖥 System Health' : t === 'security' ? '🔐 Security Logs' : '🔗 Integrations'}
          </button>
        ))}
      </div>

      {tab === 'health' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Service', 'Status', 'Uptime', 'Latency', 'Load'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {services.map(svc => (
                <tr key={svc.name} style={s.tr}>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{svc.name}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: statusColor[svc.status] + '20', color: statusColor[svc.status] }}>
                      {svc.status === 'healthy' ? '● Healthy' : svc.status === 'warning' ? '⚠ Warning' : '✕ Down'}
                    </span>
                  </td>
                  <td style={{ ...s.td, color: statusColor[svc.status] }}>{svc.uptime}</td>
                  <td style={s.td}>{svc.latency}</td>
                  <td style={s.td}>
                    <div style={s.progressBar}>
                      <div style={{ ...s.progressFill, width: `${svc.load}%`, background: svc.load > 80 ? '#ef4444' : svc.load > 60 ? '#f59e0b' : '#10b981' }} />
                    </div>
                    <span style={{ fontSize: '11px', color: '#94a3b8' }}>{svc.load}%</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'security' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Time', 'Event', 'User', 'IP Address', 'Severity'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {securityLogs.map((log, i) => (
                <tr key={i} style={s.tr}>
                  <td style={{ ...s.td, fontFamily: 'monospace', color: '#64748b' }}>{log.time}</td>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{log.event}</td>
                  <td style={{ ...s.td, fontFamily: 'monospace', color: '#94a3b8' }}>{log.user}</td>
                  <td style={{ ...s.td, fontFamily: 'monospace', color: '#64748b' }}>{log.ip}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: severityColor[log.severity] + '20', color: severityColor[log.severity] }}>
                      {log.severity.toUpperCase()}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'integrations' && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '16px' }}>
          {integrations.map(intg => (
            <div key={intg.name} style={s.intgCard}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                <span style={{ fontWeight: '700', color: '#f1f5f9', fontSize: '15px' }}>{intg.name}</span>
                <span style={{ ...s.badge, background: (intg.status === 'connected' ? '#10b981' : '#f59e0b') + '20', color: intg.status === 'connected' ? '#10b981' : '#f59e0b' }}>
                  {intg.status === 'connected' ? '● Connected' : '⚠ Warning'}
                </span>
              </div>
              <div style={{ fontSize: '12px', color: '#475569' }}>Last sync: {intg.lastSync}</div>
              <button style={s.intgBtn}>Configure</button>
            </div>
          ))}
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
  btn:         { background: '#1e293b', color: '#94a3b8', border: '1px solid #334155', padding: '10px 20px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '14px' },
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
  progressFill:{ height: '100%', borderRadius: '3px' },
  intgCard:    { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', padding: '20px' },
  intgBtn:     { marginTop: '14px', width: '100%', padding: '8px', borderRadius: '8px', background: 'transparent', border: '1px solid #1e293b', color: '#64748b', cursor: 'pointer', fontSize: '12px', fontWeight: '600' },
};
