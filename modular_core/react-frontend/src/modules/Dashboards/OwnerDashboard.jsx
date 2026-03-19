import React, { useState, useEffect } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = {
  green:  '#05ff91',
  blue:   '#00d2ff',
  purple: '#818cf8',
  pink:   '#ec4899',
  orange: '#f97316',
  yellow: '#f59e0b',
  red:    '#ef4444',
  bg:     '#0a0f1e',
  card:   'rgba(255,255,255,0.03)',
  border: 'rgba(255,255,255,0.07)',
  muted:  '#475569',
  text:   '#f1f5f9',
};

function StatCard({ icon, label, value, trend, color }) {
  const up = trend && !trend.startsWith('-') && trend !== '0';
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '20px', padding: '24px', display: 'flex', flexDirection: 'column', gap: '8px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <span style={{ fontSize: '22px' }}>{icon}</span>
        <span style={{ fontSize: '12px', fontWeight: '700', color: up ? C.green : C.red, background: up ? 'rgba(5,255,145,0.08)' : 'rgba(239,68,68,0.08)', padding: '3px 10px', borderRadius: '100px' }}>{trend}</span>
      </div>
      <div style={{ fontSize: '30px', fontWeight: '900', color: color || C.text, letterSpacing: '-1px' }}>{value}</div>
      <div style={{ fontSize: '12px', color: C.muted, fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.06em' }}>{label}</div>
    </div>
  );
}

function RevenueBar({ data }) {
  const max = Math.max(...data.map(d => d.value));
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '20px', padding: '28px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <div style={{ fontWeight: '800', fontSize: '16px' }}>Revenue Trend</div>
          <div style={{ fontSize: '12px', color: C.muted, marginTop: '2px' }}>Last 6 months</div>
        </div>
        <div style={{ fontSize: '24px', fontWeight: '900', color: C.green }}>$8.4M</div>
      </div>
      <div style={{ display: 'flex', gap: '12px', alignItems: 'flex-end', height: '120px' }}>
        {data.map((d, i) => {
          const h = Math.round((d.value / max) * 100);
          const isLast = i === data.length - 1;
          return (
            <div key={d.month} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px', height: '100%', justifyContent: 'flex-end' }}>
              <div style={{ fontSize: '11px', color: isLast ? C.green : C.muted, fontWeight: '700' }}>
                ${(d.value / 1000).toFixed(0)}K
              </div>
              <div style={{ width: '100%', height: `${h}%`, background: isLast ? `linear-gradient(180deg, ${C.green}, ${C.blue})` : 'rgba(255,255,255,0.08)', borderRadius: '8px 8px 0 0', transition: 'height 0.6s ease', minHeight: '4px' }} />
              <div style={{ fontSize: '11px', color: C.muted }}>{d.month}</div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function TeamTable({ team }) {
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '20px', overflow: 'hidden' }}>
      <div style={{ padding: '20px 24px', borderBottom: `1px solid ${C.border}`, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ fontWeight: '800', fontSize: '15px' }}>👥 Team Performance</div>
        <span style={{ fontSize: '11px', color: C.muted, background: 'rgba(255,255,255,0.04)', padding: '4px 10px', borderRadius: '8px' }}>Mar 2026</span>
      </div>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ background: 'rgba(255,255,255,0.02)' }}>
            {['Rep', 'Deals', 'Revenue', 'Quota', ''].map(h => (
              <th key={h} style={{ padding: '12px 20px', fontSize: '10px', color: C.muted, fontWeight: '800', textTransform: 'uppercase', textAlign: 'left' }}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {team.map((r, i) => {
            const pct = parseInt(r.quota);
            const color = pct >= 80 ? C.green : pct >= 60 ? C.yellow : C.red;
            return (
              <tr key={i} style={{ borderTop: `1px solid ${C.border}` }}>
                <td style={{ padding: '14px 20px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: `linear-gradient(135deg, ${C.purple}, ${C.blue})`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '800', fontSize: '13px' }}>
                      {r.name[0]}
                    </div>
                    <span style={{ fontWeight: '600', fontSize: '14px' }}>{r.name}</span>
                  </div>
                </td>
                <td style={{ padding: '14px 20px', color: C.muted, fontSize: '14px' }}>{r.deals}</td>
                <td style={{ padding: '14px 20px', color: C.green, fontWeight: '700', fontSize: '14px' }}>{r.revenue}</td>
                <td style={{ padding: '14px 20px' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <div style={{ width: '80px', height: '6px', background: 'rgba(255,255,255,0.06)', borderRadius: '100px', overflow: 'hidden' }}>
                      <div style={{ height: '100%', width: r.quota, background: color, borderRadius: '100px' }} />
                    </div>
                    <span style={{ fontSize: '13px', fontWeight: '800', color }}>{r.quota}</span>
                  </div>
                </td>
                <td style={{ padding: '14px 20px', fontSize: '18px', color: r.trend === '↑' ? C.green : C.red }}>{r.trend}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function PipelineFunnel({ stages }) {
  const max = Math.max(...stages.map(s => s.cnt));
  const colors = [C.purple, C.blue, C.yellow, C.pink, C.orange, C.green];
  return (
    <div style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '20px', padding: '24px' }}>
      <div style={{ fontWeight: '800', fontSize: '15px', marginBottom: '20px' }}>🏗️ Pipeline Funnel</div>
      {stages.map((s, i) => (
        <div key={s.lifecycle_stage} style={{ marginBottom: '12px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
            <span style={{ fontSize: '13px', color: C.muted }}>{s.lifecycle_stage}</span>
            <span style={{ fontSize: '13px', fontWeight: '800', color: colors[i] }}>{s.cnt}</span>
          </div>
          <div style={{ height: '8px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden' }}>
            <div style={{ height: '100%', width: `${(s.cnt / max) * 100}%`, background: colors[i], borderRadius: '100px', transition: 'width 0.8s ease' }} />
          </div>
        </div>
      ))}
    </div>
  );
}

export default function OwnerDashboard() {
  const { user } = useAuth();
  const [data, setData] = useState(null);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    fetch('/api/analytics/overview', { headers: { Authorization: `Bearer ${token}` } })
      .then(r => r.json())
      .then(d => setData(d.data))
      .catch(() => {});
  }, []);

  if (!data) return <div style={{ padding: '48px', textAlign: 'center', color: C.muted }}>Loading owner dashboard...</div>;

  return (
    <div style={{ padding: '28px', background: C.bg, minHeight: '100%', color: C.text }}>
      {/* Header */}
      <div style={{ marginBottom: '28px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '6px' }}>
            <span style={{ fontSize: '28px' }}>👑</span>
            <h1 style={{ margin: 0, fontSize: '26px', fontWeight: '900', letterSpacing: '-0.5px' }}>
              Owner Dashboard
            </h1>
            <span style={{ fontSize: '11px', background: 'rgba(5,255,145,0.12)', color: C.green, padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>FULL ACCESS</span>
          </div>
          <p style={{ margin: 0, color: C.muted, fontSize: '14px' }}>
            Welcome back, {user?.name} · Company: {user?.company_code} · {new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
          </p>
        </div>
        <div style={{ display: 'flex', gap: '10px' }}>
          {['📥 Export', '📅 Schedule Report', '⚙️ Settings'].map(btn => (
            <button key={btn} style={{ background: C.card, border: `1px solid ${C.border}`, color: C.muted, padding: '8px 16px', borderRadius: '10px', cursor: 'pointer', fontSize: '12px', fontWeight: '700' }}>{btn}</button>
          ))}
        </div>
      </div>

      {/* KPI Grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: '16px', marginBottom: '24px' }}>
        {data.kpis.map((k, i) => {
          const colors = [C.green, C.blue, C.purple, C.yellow, C.pink, C.orange];
          return <StatCard key={i} icon={k.icon} label={k.label} value={k.value} trend={k.trend} color={colors[i]} />;
        })}
      </div>

      {/* Revenue + Funnel */}
      <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '20px', marginBottom: '20px' }}>
        <RevenueBar data={data.revenue_by_month} />
        <PipelineFunnel stages={data.pipeline_breakdown} />
      </div>

      {/* Team Performance */}
      <TeamTable team={data.team_performance} />

      {/* Bottom row — quick actions */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginTop: '20px' }}>
        {[
          { icon: '🔄', label: 'Workflow Studio',  desc: '4 active automations',  color: C.purple },
          { icon: '🤖', label: 'AI Engine',         desc: 'Lead scoring · Win prob', color: C.blue   },
          { icon: '💳', label: 'Billing',           desc: '$142K MRR · 48 tenants', color: C.green  },
          { icon: '🛡️', label: 'RBAC & Security',  desc: '3 roles · JWT RS256',    color: C.orange },
        ].map(a => (
          <div key={a.label} style={{ background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', padding: '20px', cursor: 'pointer' }}
            onMouseEnter={e => e.currentTarget.style.borderColor = a.color}
            onMouseLeave={e => e.currentTarget.style.borderColor = C.border}>
            <div style={{ fontSize: '24px', marginBottom: '10px' }}>{a.icon}</div>
            <div style={{ fontWeight: '800', fontSize: '14px', color: a.color, marginBottom: '4px' }}>{a.label}</div>
            <div style={{ fontSize: '12px', color: C.muted }}>{a.desc}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
