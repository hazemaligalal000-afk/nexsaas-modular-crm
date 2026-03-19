import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = { bg:'#eff6ff', card:'#fff', border:'#bfdbfe', accent:'#1d4ed8', muted:'#6b7280', text:'#111827', green:'#16a34a', red:'#dc2626', yellow:'#d97706' };

const COMPANIES = [
  { code:'ACME-01', name:'ACME Egypt',       revenue:'$1.42M', expenses:'$890K', profit:'$530K', margin:'37%', status:'healthy' },
  { code:'ACME-02', name:'ACME Gulf',        revenue:'$680K',  expenses:'$510K', profit:'$170K', margin:'25%', status:'watch'   },
  { code:'ACME-03', name:'ACME Europe',      revenue:'$920K',  expenses:'$740K', profit:'$180K', margin:'20%', status:'watch'   },
  { code:'ACME-04', name:'ACME Americas',    revenue:'$2.1M',  expenses:'$1.4M', profit:'$700K', margin:'33%', status:'healthy' },
  { code:'ACME-05', name:'ACME Asia',        revenue:'$540K',  expenses:'$480K', profit:'$60K',  margin:'11%', status:'risk'    },
  { code:'ACME-06', name:'ACME Holding',     revenue:'$320K',  expenses:'$210K', profit:'$110K', margin:'34%', status:'healthy' },
];

const STATUS_COLOR = { healthy:{ bg:'#dcfce7', color:'#16a34a' }, watch:{ bg:'#fef9c3', color:'#ca8a04' }, risk:{ bg:'#fee2e2', color:'#dc2626' } };

export default function FinanceManagerDashboard() {
  const { user } = useAuth();
  const [tab, setTab] = useState('consolidation');

  const totalRevenue = COMPANIES.reduce((s, c) => s + parseFloat(c.revenue.replace(/[$MK,]/g, '') * (c.revenue.includes('M') ? 1000 : 1)), 0);

  return (
    <div style={{ padding:'28px', background:C.bg, minHeight:'100%' }}>
      <div style={{ marginBottom:'24px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'10px', marginBottom:'4px' }}>
            <span style={{ fontSize:'26px' }}>💼</span>
            <h1 style={{ margin:0, fontSize:'22px', fontWeight:'800', color:C.text }}>Finance Manager Dashboard</h1>
            <span style={{ fontSize:'11px', background:'#dbeafe', color:C.accent, padding:'4px 12px', borderRadius:'100px', fontWeight:'700' }}>FINANCE MGR</span>
          </div>
          <p style={{ margin:0, color:C.muted, fontSize:'13px' }}>{user?.name} · 6 Companies · Fin Period: Mar 2026</p>
        </div>
        <div style={{ display:'flex', gap:'8px' }}>
          <button style={{ background:C.card, border:`1px solid ${C.border}`, color:C.muted, padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>📊 Consolidated Report</button>
          <button style={{ background:C.accent, border:'none', color:'#fff', padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>📅 Close Period</button>
        </div>
      </div>

      {/* Group KPIs */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'14px', marginBottom:'24px' }}>
        {[
          { icon:'🏢', label:'Group Revenue',   value:'$5.98M', color:C.accent },
          { icon:'💸', label:'Group Expenses',  value:'$4.23M', color:C.red    },
          { icon:'📈', label:'Group Net Profit',value:'$1.75M', color:C.green  },
          { icon:'📊', label:'Avg Margin',      value:'27%',    color:C.yellow },
          { icon:'⚠️', label:'At-Risk Entities',value:'1',      color:C.red    },
        ].map(k => (
          <div key={k.label} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'14px', padding:'18px', boxShadow:'0 1px 3px rgba(0,0,0,0.05)' }}>
            <div style={{ fontSize:'20px', marginBottom:'8px' }}>{k.icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color:k.color }}>{k.value}</div>
            <div style={{ fontSize:'12px', color:C.muted, marginTop:'2px' }}>{k.label}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px', background:'#dbeafe', borderRadius:'12px', padding:'4px', width:'fit-content' }}>
        {[{id:'consolidation',label:'🏢 Consolidation'},{id:'cashflow',label:'💧 Cash Flow'},{id:'fx',label:'💱 FX Rates'},{id:'budget',label:'📋 Budget vs Actual'}].map(t => (
          <button key={t.id} onClick={() => setTab(t.id)} style={{ background:tab===t.id?C.card:'transparent', color:tab===t.id?C.accent:C.muted, border:'none', padding:'9px 18px', borderRadius:'8px', fontWeight:'700', cursor:'pointer', fontSize:'12px', boxShadow:tab===t.id?'0 1px 3px rgba(0,0,0,0.1)':'none' }}>{t.label}</button>
        ))}
      </div>

      {/* Consolidation */}
      {tab==='consolidation' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#eff6ff' }}>
              {['Company','Revenue','Expenses','Net Profit','Margin','Status'].map(h => (
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {COMPANIES.map(c => {
                const st = STATUS_COLOR[c.status];
                return (
                  <tr key={c.code} style={{ borderTop:`1px solid ${C.border}` }}>
                    <td style={{ padding:'14px 20px' }}>
                      <div style={{ fontWeight:'700', fontSize:'13px', color:C.text }}>{c.name}</div>
                      <div style={{ fontSize:'11px', color:C.muted }}>{c.code}</div>
                    </td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.green }}>{c.revenue}</td>
                    <td style={{ padding:'14px 20px', color:C.red }}>{c.expenses}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.accent }}>{c.profit}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:parseFloat(c.margin)>25?C.green:C.yellow }}>{c.margin}</td>
                    <td style={{ padding:'14px 20px' }}>
                      <span style={{ background:st.bg, color:st.color, padding:'3px 10px', borderRadius:'100px', fontSize:'11px', fontWeight:'700', textTransform:'capitalize' }}>{c.status}</span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Cash Flow */}
      {tab==='cashflow' && (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20px' }}>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>💧 Cash Flow Forecast</div>
            {[
              { period:'30 Days', inflow:'$1.8M', outflow:'$1.2M', net:'+$600K', color:C.green  },
              { period:'60 Days', inflow:'$3.4M', outflow:'$2.6M', net:'+$800K', color:C.green  },
              { period:'90 Days', inflow:'$5.1M', outflow:'$4.0M', net:'+$1.1M', color:C.green  },
            ].map(r => (
              <div key={r.period} style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr 1fr', gap:'8px', padding:'12px 0', borderBottom:`1px solid ${C.border}`, alignItems:'center' }}>
                <span style={{ fontWeight:'700', color:C.text }}>{r.period}</span>
                <span style={{ color:C.green, fontSize:'13px' }}>↑ {r.inflow}</span>
                <span style={{ color:C.red, fontSize:'13px' }}>↓ {r.outflow}</span>
                <span style={{ fontWeight:'800', color:r.color }}>{r.net}</span>
              </div>
            ))}
          </div>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>🏦 Cash Position by Currency</div>
            {[
              { currency:'EGP', amount:'EGP 3,196,600', equiv:'$65,200' },
              { currency:'USD', amount:'$48,200',        equiv:'$48,200' },
              { currency:'AED', amount:'AED 185,000',    equiv:'$50,400' },
              { currency:'EUR', amount:'€22,000',        equiv:'$23,900' },
            ].map(r => (
              <div key={r.currency} style={{ display:'flex', justifyContent:'space-between', padding:'12px 0', borderBottom:`1px solid ${C.border}` }}>
                <span style={{ fontWeight:'700', color:C.text }}>{r.currency}</span>
                <span style={{ color:C.muted, fontSize:'13px' }}>{r.amount}</span>
                <span style={{ fontWeight:'700', color:C.accent }}>{r.equiv}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* FX */}
      {tab==='fx' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
          <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'20px' }}>💱 Exchange Rates — Mar 19, 2026</div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(4,1fr)', gap:'16px' }}>
            {[
              { pair:'USD/EGP', rate:'49.05', change:'+0.12', src:'CBE' },
              { pair:'EUR/EGP', rate:'53.20', change:'-0.08', src:'CBE' },
              { pair:'AED/EGP', rate:'13.35', change:'+0.03', src:'CBE' },
              { pair:'GBP/EGP', rate:'62.10', change:'+0.22', src:'CBE' },
            ].map(r => {
              const up = r.change.startsWith('+');
              return (
                <div key={r.pair} style={{ background:'#eff6ff', borderRadius:'14px', padding:'20px', border:`1px solid ${C.border}` }}>
                  <div style={{ fontSize:'14px', fontWeight:'800', color:C.text, marginBottom:'8px' }}>{r.pair}</div>
                  <div style={{ fontSize:'28px', fontWeight:'900', color:C.accent }}>{r.rate}</div>
                  <div style={{ display:'flex', justifyContent:'space-between', marginTop:'8px' }}>
                    <span style={{ fontSize:'12px', color:up?C.green:C.red, fontWeight:'700' }}>{r.change}</span>
                    <span style={{ fontSize:'11px', color:C.muted }}>Source: {r.src}</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Budget */}
      {tab==='budget' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
          <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'20px' }}>📋 Budget vs Actual — Mar 2026</div>
          {[
            { dept:'Sales',       budget:'$1,500K', actual:'$1,420K', pct:95, color:C.green  },
            { dept:'Marketing',   budget:'$200K',   actual:'$218K',   pct:109,color:C.red    },
            { dept:'Engineering', budget:'$450K',   actual:'$412K',   pct:92, color:C.green  },
            { dept:'HR & Admin',  budget:'$180K',   actual:'$175K',   pct:97, color:C.green  },
            { dept:'Operations',  budget:'$320K',   actual:'$298K',   pct:93, color:C.green  },
          ].map(r => (
            <div key={r.dept} style={{ marginBottom:'16px' }}>
              <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'6px' }}>
                <span style={{ fontSize:'13px', fontWeight:'600', color:C.text }}>{r.dept}</span>
                <div style={{ display:'flex', gap:'16px' }}>
                  <span style={{ fontSize:'12px', color:C.muted }}>Budget: {r.budget}</span>
                  <span style={{ fontSize:'12px', color:C.muted }}>Actual: {r.actual}</span>
                  <span style={{ fontSize:'13px', fontWeight:'800', color:r.color }}>{r.pct}%</span>
                </div>
              </div>
              <div style={{ height:'8px', background:'#f1f5f9', borderRadius:'100px', overflow:'hidden' }}>
                <div style={{ height:'100%', width:`${Math.min(r.pct,100)}%`, background:r.color, borderRadius:'100px' }} />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
