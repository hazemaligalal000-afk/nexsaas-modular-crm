import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = { bg:'#f0fdf4', card:'#fff', border:'#bbf7d0', accent:'#16a34a', muted:'#6b7280', text:'#111827', red:'#ef4444', yellow:'#f59e0b', blue:'#3b82f6' };

const VOUCHERS = [
  { id:'VCH-2026-0341', type:'Journal',  date:'Mar 19', amount:'$12,500', status:'Posted',   company:'ACME-01' },
  { id:'VCH-2026-0340', type:'AP Bill',  date:'Mar 19', amount:'$4,200',  status:'Pending',  company:'ACME-01' },
  { id:'VCH-2026-0339', type:'AR Inv',   date:'Mar 18', amount:'$28,000', status:'Posted',   company:'ACME-02' },
  { id:'VCH-2026-0338', type:'Expense',  date:'Mar 18', amount:'$850',    status:'Approved', company:'ACME-01' },
  { id:'VCH-2026-0337', type:'Payroll',  date:'Mar 17', amount:'$55,000', status:'Posted',   company:'ACME-01' },
];

const AR_AGING = [
  { bucket:'0–30 days',  amount:'$84,200',  pct:52, color:'#16a34a' },
  { bucket:'31–60 days', amount:'$31,500',  pct:19, color:'#f59e0b' },
  { bucket:'61–90 days', amount:'$18,900',  pct:12, color:'#f97316' },
  { bucket:'91–120 days',amount:'$12,400',  pct:8,  color:'#ef4444' },
  { bucket:'120+ days',  amount:'$14,800',  pct:9,  color:'#7f1d1d' },
];

const AP_AGING = [
  { bucket:'0–30 days',  amount:'$42,100',  pct:58, color:'#16a34a' },
  { bucket:'31–60 days', amount:'$18,600',  pct:26, color:'#f59e0b' },
  { bucket:'61–90 days', amount:'$8,200',   pct:11, color:'#f97316' },
  { bucket:'120+ days',  amount:'$3,900',   pct:5,  color:'#ef4444' },
];

const STATUS_STYLE = {
  Posted:   { bg:'#dcfce7', color:'#16a34a' },
  Pending:  { bg:'#fef9c3', color:'#ca8a04' },
  Approved: { bg:'#dbeafe', color:'#2563eb' },
  Rejected: { bg:'#fee2e2', color:'#dc2626' },
};

export default function AccountantDashboard() {
  const { user } = useAuth();
  const [tab, setTab] = useState('overview');

  const tabs = [
    { id:'overview', label:'📊 Overview' },
    { id:'vouchers', label:'📄 Vouchers' },
    { id:'aging',    label:'⏳ Aging' },
    { id:'bank',     label:'🏦 Bank' },
  ];

  return (
    <div style={{ padding:'28px', background:C.bg, minHeight:'100%' }}>
      {/* Header */}
      <div style={{ marginBottom:'24px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'10px', marginBottom:'4px' }}>
            <span style={{ fontSize:'26px' }}>🧾</span>
            <h1 style={{ margin:0, fontSize:'22px', fontWeight:'800', color:C.text }}>Accounting Dashboard</h1>
            <span style={{ fontSize:'11px', background:'#dcfce7', color:C.accent, padding:'4px 12px', borderRadius:'100px', fontWeight:'700' }}>ACCOUNTANT</span>
          </div>
          <p style={{ margin:0, color:C.muted, fontSize:'13px' }}>{user?.name} · Fin Period: Mar 2026 · Company: {user?.company_code}</p>
        </div>
        <div style={{ display:'flex', gap:'8px' }}>
          <button style={{ background:C.card, border:`1px solid ${C.border}`, color:C.muted, padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>📥 Export GL</button>
          <button style={{ background:C.accent, border:'none', color:'#fff', padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>+ New Voucher</button>
        </div>
      </div>

      {/* KPIs */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'14px', marginBottom:'24px' }}>
        {[
          { icon:'💰', label:'Total Revenue',    value:'$1.42M', color:C.accent, sub:'Mar 2026' },
          { icon:'📤', label:'Total Expenses',   value:'$890K',  color:C.red,    sub:'Mar 2026' },
          { icon:'📈', label:'Net Profit',       value:'$530K',  color:C.blue,   sub:'+8% vs Feb' },
          { icon:'🔴', label:'AR Outstanding',   value:'$161.8K',color:C.yellow, sub:'5 clients' },
          { icon:'🔵', label:'AP Outstanding',   value:'$72.8K', color:C.muted,  sub:'3 vendors' },
        ].map(k => (
          <div key={k.label} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'14px', padding:'18px', boxShadow:'0 1px 3px rgba(0,0,0,0.05)' }}>
            <div style={{ fontSize:'20px', marginBottom:'8px' }}>{k.icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color:k.color }}>{k.value}</div>
            <div style={{ fontSize:'12px', color:C.muted, marginTop:'2px' }}>{k.label}</div>
            <div style={{ fontSize:'11px', color:'#9ca3af', marginTop:'2px' }}>{k.sub}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px', background:'#e7f7ee', borderRadius:'12px', padding:'4px', width:'fit-content' }}>
        {tabs.map(t => (
          <button key={t.id} onClick={() => setTab(t.id)} style={{ background:tab===t.id?C.card:'transparent', color:tab===t.id?C.accent:C.muted, border:'none', padding:'9px 18px', borderRadius:'8px', fontWeight:'700', cursor:'pointer', fontSize:'12px', boxShadow:tab===t.id?'0 1px 3px rgba(0,0,0,0.1)':'none' }}>{t.label}</button>
        ))}
      </div>

      {/* Overview */}
      {tab==='overview' && (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20px' }}>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>📊 P&L Summary — Mar 2026</div>
            {[
              { label:'Revenue',          value:'$1,420,000', color:C.accent },
              { label:'Cost of Goods',    value:'-$568,000',  color:C.red    },
              { label:'Gross Profit',     value:'$852,000',   color:C.blue   },
              { label:'Operating Exp.',   value:'-$322,000',  color:C.red    },
              { label:'Net Profit',       value:'$530,000',   color:C.accent },
            ].map(r => (
              <div key={r.label} style={{ display:'flex', justifyContent:'space-between', padding:'10px 0', borderBottom:`1px solid ${C.border}` }}>
                <span style={{ fontSize:'13px', color:C.muted }}>{r.label}</span>
                <span style={{ fontSize:'14px', fontWeight:'800', color:r.color }}>{r.value}</span>
              </div>
            ))}
          </div>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>🏦 Bank Balances</div>
            {[
              { bank:'SAIB — EGP',       balance:'EGP 2,840,000', flag:'🇪🇬' },
              { bank:'QNB — E-Wallet',   balance:'EGP 124,500',   flag:'💳' },
              { bank:'PAYPALL — USD',    balance:'$48,200',        flag:'🇺🇸' },
              { bank:'AMARAT — AED',     balance:'AED 185,000',    flag:'🇦🇪' },
              { bank:'INSTAPAY NBE',     balance:'EGP 32,100',     flag:'📱' },
            ].map(b => (
              <div key={b.bank} style={{ display:'flex', justifyContent:'space-between', alignItems:'center', padding:'10px 0', borderBottom:`1px solid ${C.border}` }}>
                <span style={{ fontSize:'13px', color:C.muted }}>{b.flag} {b.bank}</span>
                <span style={{ fontSize:'14px', fontWeight:'700', color:C.text }}>{b.balance}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Vouchers */}
      {tab==='vouchers' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#f0fdf4' }}>
              {['Voucher #','Type','Date','Amount','Company','Status','Action'].map(h => (
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {VOUCHERS.map(v => {
                const st = STATUS_STYLE[v.status] || STATUS_STYLE.Pending;
                return (
                  <tr key={v.id} style={{ borderTop:`1px solid ${C.border}` }}>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.accent, fontSize:'13px' }}>{v.id}</td>
                    <td style={{ padding:'14px 20px', fontSize:'13px', color:C.muted }}>{v.type}</td>
                    <td style={{ padding:'14px 20px', fontSize:'13px', color:C.muted }}>{v.date}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.text }}>{v.amount}</td>
                    <td style={{ padding:'14px 20px', fontSize:'12px', color:C.muted }}>{v.company}</td>
                    <td style={{ padding:'14px 20px' }}>
                      <span style={{ background:st.bg, color:st.color, padding:'3px 10px', borderRadius:'100px', fontSize:'11px', fontWeight:'700' }}>{v.status}</span>
                    </td>
                    <td style={{ padding:'14px 20px' }}>
                      <button style={{ background:'none', border:`1px solid ${C.border}`, color:C.accent, padding:'5px 12px', borderRadius:'8px', cursor:'pointer', fontSize:'12px', fontWeight:'600' }}>View</button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Aging */}
      {tab==='aging' && (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20px' }}>
          {[{ title:'📥 AR Aging (Receivables)', data:AR_AGING }, { title:'📤 AP Aging (Payables)', data:AP_AGING }].map(({ title, data }) => (
            <div key={title} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
              <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'20px' }}>{title}</div>
              {data.map(r => (
                <div key={r.bucket} style={{ marginBottom:'14px' }}>
                  <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'6px' }}>
                    <span style={{ fontSize:'13px', color:C.muted }}>{r.bucket}</span>
                    <div style={{ display:'flex', gap:'12px' }}>
                      <span style={{ fontSize:'12px', color:C.muted }}>{r.pct}%</span>
                      <span style={{ fontSize:'13px', fontWeight:'700', color:r.color }}>{r.amount}</span>
                    </div>
                  </div>
                  <div style={{ height:'8px', background:'#f1f5f9', borderRadius:'100px', overflow:'hidden' }}>
                    <div style={{ height:'100%', width:`${r.pct}%`, background:r.color, borderRadius:'100px' }} />
                  </div>
                </div>
              ))}
            </div>
          ))}
        </div>
      )}

      {/* Bank */}
      {tab==='bank' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
          <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'20px' }}>🏦 Bank Reconciliation — Mar 2026</div>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(3,1fr)', gap:'16px' }}>
            {[
              { label:'Book Balance',         value:'EGP 2,840,000', color:C.accent },
              { label:'Bank Statement Bal.',  value:'EGP 2,856,400', color:C.blue   },
              { label:'Reconciling Diff.',    value:'EGP 16,400',    color:C.yellow },
              { label:'Outstanding Deposits', value:'EGP 24,000',    color:C.muted  },
              { label:'Outstanding Payments', value:'EGP 7,600',     color:C.red    },
              { label:'Bank Charges (MTD)',   value:'EGP 1,200',     color:C.muted  },
            ].map(s => (
              <div key={s.label} style={{ background:'#f0fdf4', borderRadius:'12px', padding:'18px', border:`1px solid ${C.border}` }}>
                <div style={{ fontSize:'12px', color:C.muted, marginBottom:'6px' }}>{s.label}</div>
                <div style={{ fontSize:'20px', fontWeight:'800', color:s.color }}>{s.value}</div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
