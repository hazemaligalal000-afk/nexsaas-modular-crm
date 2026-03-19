import React, { useState, useEffect } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = {
  accent: '#6366f1',
  green:  '#10b981',
  yellow: '#f59e0b',
  red:    '#ef4444',
  blue:   '#3b82f6',
  bg:     '#f1f5f9',
  card:   '#ffffff',
  border: '#e2e8f0',
  muted:  '#94a3b8',
  text:   '#0f172a',
  sub:    '#475569',
};

const PRIORITY_STYLE = {
  high:   { bg: '#fef2f2', color: '#ef4444', label: 'High'   },
  medium: { bg: '#fffbeb', color: '#f59e0b', label: 'Medium' },
  low:    { bg: '#f0fdf4', color: '#10b981', label: 'Low'    },
};

function MyKpi({ icon, label, value, color }) {
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', padding: '22px', boxShadow: '0 1px 3px rgba(0,0,0,0.05)', display: 'flex', alignItems: 'center', gap: '16px' }}>
      <div style={{ width: '48px', height: '48px', borderRadius: '14px', background: `${color}15`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '22px', flexShrink: 0 }}>{icon}</div>
      <div>
        <div style={{ fontSize: '26px', fontWeight: '800', color: C.text, letterSpacing: '-0.5px' }}>{value}</div>
        <div style={{ fontSize: '12px', color: C.muted, marginTop: '2px' }}>{label}</div>
      </div>
    </div>
  );
}

function TaskList({ tasks }) {
  const [done, setDone] = useState([]);
  const toggle = id => setDone(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);

  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
      <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ fontWeight: '700', fontSize: '15px', color: C.text }}>📋 My Tasks Today</div>
        <span style={{ fontSize: '12px', background: '#eff6ff', color: C.blue, padding: '3px 10px', borderRadius: '100px', fontWeight: '700' }}>
          {tasks.length - done.length} remaining
        </span>
      </div>
      {tasks.map(t => {
        const p = PRIORITY_STYLE[t.priority];
        const isDone = done.includes(t.id);
        return (
          <div key={t.id} style={{ display: 'flex', gap: '14px', padding: '14px 22px', borderBottom: `1px solid ${C.border}`, alignItems: 'center', opacity: isDone ? 0.45 : 1, transition: 'opacity 0.2s' }}>
            <button onClick={() => toggle(t.id)} style={{ width: '20px', height: '20px', borderRadius: '6px', border: `2px solid ${isDone ? C.green : C.border}`, background: isDone ? C.green : 'transparent', cursor: 'pointer', flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: '11px' }}>
              {isDone ? '✓' : ''}
            </button>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: '14px', color: C.text, fontWeight: '500', textDecoration: isDone ? 'line-through' : 'none' }}>{t.title}</div>
              <div style={{ fontSize: '11px', color: C.muted, marginTop: '2px' }}>Due: {t.due}</div>
            </div>
            <span style={{ fontSize: '11px', fontWeight: '700', background: p.bg, color: p.color, padding: '3px 10px', borderRadius: '100px' }}>{p.label}</span>
          </div>
        );
      })}
    </div>
  );
}

function MyDeals({ deals }) {
  const STAGE_COLOR = {
    'New Lead': '#818cf8', 'Qualified': '#3b82f6', 'Demo': '#f59e0b',
    'Proposal': '#ec4899', 'Negotiation': '#f97316', 'Closed Won': '#10b981',
  };
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
      <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ fontWeight: '700', fontSize: '15px', color: C.text }}>💼 My Deals</div>
        <span style={{ fontSize: '12px', color: C.muted }}>{deals.length} open</span>
      </div>
      {deals.length === 0 && (
        <div style={{ padding: '32px', textAlign: 'center', color: C.muted, fontSize: '14px' }}>No deals assigned yet.</div>
      )}
      {deals.map(d => {
        const color = STAGE_COLOR[d.sales_stage] || C.accent;
        const prob = d.win_probability || 0;
        return (
          <div key={d.potentialid} style={{ padding: '16px 22px', borderBottom: `1px solid ${C.border}`, display: 'flex', gap: '14px', alignItems: 'center' }}>
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: '14px', fontWeight: '600', color: C.text, marginBottom: '4px' }}>{d.potentialname}</div>
              <div style={{ fontSize: '12px', color: C.muted }}>{d.company_name}</div>
            </div>
            <div style={{ textAlign: 'right' }}>
              <div style={{ fontSize: '15px', fontWeight: '800', color: C.text }}>${parseFloat(d.amount).toLocaleString()}</div>
              <span style={{ fontSize: '11px', fontWeight: '700', color, background: `${color}15`, padding: '2px 8px', borderRadius: '100px' }}>{d.sales_stage}</span>
            </div>
            <div style={{ textAlign: 'center', minWidth: '52px' }}>
              <div style={{ fontSize: '16px', fontWeight: '900', color: prob >= 70 ? C.green : prob >= 40 ? C.yellow : C.red }}>{prob}%</div>
              <div style={{ fontSize: '10px', color: C.muted }}>win prob</div>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function MyLeads({ leads }) {
  const scoreColor = s => s >= 80 ? C.green : s >= 50 ? C.yellow : C.red;
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', overflow: 'hidden', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
      <div style={{ padding: '18px 22px', borderBottom: `1px solid ${C.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ fontWeight: '700', fontSize: '15px', color: C.text }}>👤 My Leads</div>
        <button style={{ background: C.accent, border: 'none', color: '#fff', padding: '6px 14px', borderRadius: '8px', cursor: 'pointer', fontSize: '12px', fontWeight: '700' }}>+ Add Lead</button>
      </div>
      {leads.map(l => (
        <div key={l.id} style={{ display: 'flex', gap: '14px', padding: '14px 22px', borderBottom: `1px solid ${C.border}`, alignItems: 'center' }}>
          <div style={{ width: '36px', height: '36px', borderRadius: '50%', background: `${C.accent}20`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '800', color: C.accent, fontSize: '14px', flexShrink: 0 }}>
            {l.first_name[0]}
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: '14px', fontWeight: '600', color: C.text }}>{l.first_name} {l.last_name}</div>
            <div style={{ fontSize: '12px', color: C.muted }}>{l.email} · {l.source}</div>
          </div>
          <div style={{ textAlign: 'center' }}>
            <div style={{ width: '38px', height: '38px', borderRadius: '50%', background: `${scoreColor(l.ai_score)}20`, border: `2px solid ${scoreColor(l.ai_score)}`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px', fontWeight: '800', color: scoreColor(l.ai_score) }}>
              {l.ai_score}
            </div>
          </div>
          <span style={{ fontSize: '11px', background: '#f1f5f9', color: C.sub, padding: '3px 10px', borderRadius: '100px', fontWeight: '600' }}>{l.lifecycle_stage}</span>
        </div>
      ))}
    </div>
  );
}

export default function UserDashboard() {
  const { user } = useAuth();
  const [analytics, setAnalytics] = useState(null);
  const [leads, setLeads] = useState([]);
  const [deals, setDeals] = useState([]);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    const h = { Authorization: `Bearer ${token}` };
    Promise.all([
      fetch('/api/analytics/overview', { headers: h }).then(r => r.json()),
      fetch('/api/leads',              { headers: h }).then(r => r.json()),
      fetch('/api/deals',              { headers: h }).then(r => r.json()),
    ]).then(([a, l, d]) => {
      setAnalytics(a.data);
      setLeads(l.data || []);
      setDeals(d.data || []);
    }).catch(() => {});
  }, []);

  if (!analytics) return <div style={{ padding: '48px', textAlign: 'center', color: C.muted }}>Loading your workspace...</div>;

  return (
    <div style={{ padding: '28px', background: C.bg, minHeight: '100%' }}>
      {/* Header */}
      <div style={{ marginBottom: '24px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '4px' }}>
            <span style={{ fontSize: '26px' }}>👤</span>
            <h1 style={{ margin: 0, fontSize: '22px', fontWeight: '800', color: C.text }}>My Workspace</h1>
            <span style={{ fontSize: '11px', background: '#ede9fe', color: C.accent, padding: '4px 12px', borderRadius: '100px', fontWeight: '700' }}>SALES REP</span>
          </div>
          <p style={{ margin: 0, color: C.muted, fontSize: '13px' }}>
            Good {new Date().getHours() < 12 ? 'morning' : 'afternoon'}, {user?.name?.split(' ')[0]} · {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })}
          </p>
        </div>
        <div style={{ display: 'flex', gap: '8px' }}>
          <button style={{ background: C.card, border: `1px solid ${C.border}`, color: C.sub, padding: '8px 16px', borderRadius: '10px', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>📅 Calendar</button>
          <button style={{ background: C.accent, border: 'none', color: '#fff', padding: '8px 16px', borderRadius: '10px', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>+ Log Activity</button>
        </div>
      </div>

      {/* My KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '24px' }}>
        {analytics.kpis.map((k, i) => {
          const colors = [C.accent, C.blue, C.green, C.yellow];
          return <MyKpi key={i} icon={k.icon} label={k.label} value={k.value} color={colors[i]} />;
        })}
      </div>

      {/* Tasks + Deals */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' }}>
        <TaskList tasks={analytics.my_tasks || []} />
        <MyDeals deals={deals} />
      </div>

      {/* My Leads */}
      <MyLeads leads={leads} />
    </div>
  );
}
