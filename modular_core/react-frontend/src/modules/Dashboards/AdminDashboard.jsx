import React, { useState, useEffect } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = {
  accent: '#3b82f6',
  green:  '#10b981',
  yellow: '#f59e0b',
  red:    '#ef4444',
  purple: '#8b5cf6',
  bg:     '#f8fafc',
  card:   '#ffffff',
  border: '#e2e8f0',
  muted:  '#64748b',
  text:   '#0f172a',
  sub:    '#475569',
};

function KpiCard({ icon, label, value, trend, color }) {
  const up = trend && !trend.startsWith('-') && trend !== '0';
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', padding: '24px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px' }}>
        <div style={{ width: '44px', height: '44px', borderRadius: '12px', background: `${color}18`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px' }}>{icon}</div>
        <span style={{ fontSize: '12px', fontWeight: '700', color: up ? C.green : C.red, background: up ? '#ecfdf5' : '#fef2f2', padding: '3px 10px', borderRadius: '100px' }}>{trend}</span>
      </div>
      <div style={{ fontSize: '28px', fontWeight: '800', color: C.text, letterSpacing: '-0.5px' }}>{value}</div>
      <div style={{ fontSize: '13px', color: C.muted, marginTop: '4px' }}>{label}</div>
    </div>
  );
}

function PipelineBar({ stages }) {
  const colors = ['#818cf8', '#3b82f6', '#f59e0b', '#ec4899', '#f97316', '#10b981'];
  const total = stages.reduce((s, x) => s + x.cnt, 0);
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', padding: '24px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' }}>
      <div style={{ fontWeight: '700', fontSize: '15px', color: C.text, marginBottom: '4px' }}>Pipeline Breakdown</div>
      <div style={{ fontSize: '13px', color: C.muted, marginBottom: '20px' }}>{total} total leads across all stages</div>
      {stages.map((s, i) => {
        const pct = Math.round((s.cnt / total) * 100);
        return (
          <div key={s.lifecycle_stage} style={{ marginBottom: '14px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
              <span style={{ fontSize: '13px', color: C.sub, fontWeight: '500' }}>{s.lifecycle_stage}</span>
              <div style={{ display: 'flex', gap: '12px' }}>
                <span style={{ fontSize: '13px', color: C.muted }}>{pct}%</span>
                <span style={{ fontSize: '13px', fontWeight: '700', color: colors[i], minWidth: '28px', textAlign: 'right' }}>{s.cnt}</span>
              </div>
            </div>
            <div style={{ height: '8px', background: '#f1f5f9', borderRadius: '100px', overflow: 'hidden' }}>
              <div style={{ height: '100%', width: `${pct}%`, background: colors[i], borderRadius: '100px', transition: 'width 0.7s ease' }} />
            </div>
          </div>
        );
      })}
    </div>
  );
}

function RecentActivity() {
  const items = [
    { icon: '👤', text: 'New lead captured — Alice Johnson (score: 87)', time: '2 min ago',  color: '#3b82f6' },
    { icon: '💼', text: 'Deal moved to Proposal — Amazon Migration $3.5M', time: '18 min ago', color: '#10b981' },
    { icon: '🔄', text: 'Workflow triggered — Auto-Assign High Value Deal', time: '34 min ago', color: '#8b5cf6' },
    { icon: '✉️', text: 'Email opened — Carol Williams (Meta proposal)', time: '1 hr ago',   color: '#f59e0b' },
    { icon: '📞', text: 'Call logged — Bob Smith, 14 min, outcome: follow-up', time: '2 hr ago',   color: '#f97316' },
    { icon: '🤖', text: 'AI scored 12 new leads — avg score 71', time: '3 hr ago',   color: '#ec4899' },
  ];
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', overflow: 'hidden', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' }}>
      <div style={{ padding: '20px 24px', borderBottom: `1px solid ${C.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ fontWeight: '700', fontSize: '15px', color: C.text }}>Recent Activity</div>
        <button style={{ background: 'none', border: 'none', color: C.accent, fontSize: '13px', fontWeight: '600', cursor: 'pointer' }}>View all →</button>
      </div>
      {items.map((a, i) => (
        <div key={i} style={{ display: 'flex', gap: '14px', padding: '14px 24px', borderBottom: i < items.length - 1 ? `1px solid ${C.border}` : 'none', alignItems: 'flex-start' }}>
          <div style={{ width: '36px', height: '36px', borderRadius: '10px', background: `${a.color}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '16px', flexShrink: 0 }}>{a.icon}</div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: '13px', color: C.text, fontWeight: '500', lineHeight: '1.4' }}>{a.text}</div>
            <div style={{ fontSize: '11px', color: C.muted, marginTop: '3px' }}>{a.time}</div>
          </div>
        </div>
      ))}
    </div>
  );
}

function QuickStats() {
  const stats = [
    { label: 'Emails Sent Today',    value: '89',  icon: '✉️',  color: C.accent  },
    { label: 'Calls Logged',         value: '47',  icon: '📞',  color: C.purple  },
    { label: 'Tasks Completed',      value: '21',  icon: '✅',  color: C.green   },
    { label: 'Workflows Triggered',  value: '14',  icon: '🔄',  color: C.yellow  },
  ];
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', padding: '24px', boxShadow: '0 1px 4px rgba(0,0,0,0.06)' }}>
      <div style={{ fontWeight: '700', fontSize: '15px', color: C.text, marginBottom: '20px' }}>Today's Activity</div>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
        {stats.map(s => (
          <div key={s.label} style={{ background: '#f8fafc', borderRadius: '12px', padding: '16px', border: `1px solid ${C.border}` }}>
            <div style={{ fontSize: '20px', marginBottom: '8px' }}>{s.icon}</div>
            <div style={{ fontSize: '22px', fontWeight: '800', color: s.color }}>{s.value}</div>
            <div style={{ fontSize: '11px', color: C.muted, marginTop: '2px' }}>{s.label}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

export default function AdminDashboard() {
  const { user } = useAuth();
  const [data, setData] = useState(null);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    fetch('/api/analytics/overview', { headers: { Authorization: `Bearer ${token}` } })
      .then(r => r.json())
      .then(d => setData(d.data))
      .catch(() => {});
  }, []);

  if (!data) return <div style={{ padding: '48px', textAlign: 'center', color: C.muted }}>Loading...</div>;

  return (
    <div style={{ padding: '28px', background: C.bg, minHeight: '100%' }}>
      {/* Header */}
      <div style={{ marginBottom: '28px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '6px' }}>
            <span style={{ fontSize: '26px' }}>🛡️</span>
            <h1 style={{ margin: 0, fontSize: '24px', fontWeight: '800', color: C.text }}>Admin Dashboard</h1>
            <span style={{ fontSize: '11px', background: '#eff6ff', color: C.accent, padding: '4px 12px', borderRadius: '100px', fontWeight: '700' }}>ADMIN</span>
          </div>
          <p style={{ margin: 0, color: C.muted, fontSize: '14px' }}>
            {user?.name} · {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
          </p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          <button style={{ background: C.card, border: `1px solid ${C.border}`, color: C.sub, padding: '9px 18px', borderRadius: '10px', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>📥 Export</button>
          <button style={{ background: C.accent, border: 'none', color: '#fff', padding: '9px 18px', borderRadius: '10px', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>+ Add Lead</button>
        </div>
      </div>

      {/* KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: '16px', marginBottom: '24px' }}>
        {data.kpis.map((k, i) => {
          const colors = [C.accent, C.green, C.purple, C.yellow, '#f97316'];
          return <KpiCard key={i} icon={k.icon} label={k.label} value={k.value} trend={k.trend} color={colors[i]} />;
        })}
      </div>

      {/* Middle row */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' }}>
        <PipelineBar stages={data.pipeline_breakdown} />
        <QuickStats />
      </div>

      {/* Activity feed */}
      <RecentActivity />
    </div>
  );
}
