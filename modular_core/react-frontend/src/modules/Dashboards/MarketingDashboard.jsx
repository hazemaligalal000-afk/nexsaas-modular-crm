import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = { bg:'#fdf2f8', card:'#fff', border:'#fbcfe8', accent:'#be185d', muted:'#6b7280', text:'#111827', green:'#16a34a', red:'#dc2626', blue:'#2563eb', purple:'#7c3aed' };

const CAMPAIGNS = [
  { name:'Spring Product Launch',   channel:'Email',    sent:12400, opens:'38%', clicks:'12%', leads:148, status:'Active'   },
  { name:'LinkedIn B2B Outreach',   channel:'LinkedIn', sent:3200,  opens:'22%', clicks:'8%',  leads:64,  status:'Active'   },
  { name:'Google Ads — Enterprise', channel:'PPC',      sent:null,  opens:null,  clicks:'4.2%',leads:89,  status:'Active'   },
  { name:'Webinar: AI in CRM',      channel:'Event',    sent:850,   opens:'61%', clicks:'28%', leads:212, status:'Completed'},
  { name:'Retargeting — Q1 Leads',  channel:'Display',  sent:null,  opens:null,  clicks:'1.8%',leads:31,  status:'Paused'   },
];

const ST = { Active:{ bg:'#dcfce7', color:'#16a34a' }, Completed:{ bg:'#dbeafe', color:'#2563eb' }, Paused:{ bg:'#f3f4f6', color:'#6b7280' } };

export default function MarketingDashboard() {
  const { user } = useAuth();
  const [tab, setTab] = useState('campaigns');

  const totalLeads = CAMPAIGNS.reduce((s,c)=>s+c.leads,0);

  return (
    <div style={{ padding:'28px', background:C.bg, minHeight:'100%' }}>
      <div style={{ marginBottom:'24px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'10px', marginBottom:'4px' }}>
            <span style={{ fontSize:'26px' }}>📣</span>
            <h1 style={{ margin:0, fontSize:'22px', fontWeight:'800', color:C.text }}>Marketing Dashboard</h1>
            <span style={{ fontSize:'11px', background:'#fce7f3', color:C.accent, padding:'4px 12px', borderRadius:'100px', fontWeight:'700' }}>MARKETING</span>
          </div>
          <p style={{ margin:0, color:C.muted, fontSize:'13px' }}>{user?.name} · {CAMPAIGNS.filter(c=>c.status==='Active').length} active campaigns · {totalLeads} leads generated</p>
        </div>
        <button style={{ background:C.accent, border:'none', color:'#fff', padding:'9px 18px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'700' }}>+ New Campaign</button>
      </div>

      {/* KPIs */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'14px', marginBottom:'24px' }}>
        {[
          { icon:'📣', label:'Active Campaigns', value:CAMPAIGNS.filter(c=>c.status==='Active').length, color:C.accent  },
          { icon:'👤', label:'Leads Generated',  value:totalLeads,                                       color:C.green   },
          { icon:'📧', label:'Emails Sent',       value:'16.4K',                                          color:C.blue    },
          { icon:'🖱️', label:'Avg CTR',           value:'8.4%',                                           color:C.purple  },
          { icon:'💰', label:'Cost per Lead',     value:'$12.40',                                         color:C.muted   },
        ].map(k => (
          <div key={k.label} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'14px', padding:'18px' }}>
            <div style={{ fontSize:'20px', marginBottom:'8px' }}>{k.icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color:k.color }}>{k.value}</div>
            <div style={{ fontSize:'12px', color:C.muted, marginTop:'2px' }}>{k.label}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px', background:'#fce7f3', borderRadius:'12px', padding:'4px', width:'fit-content' }}>
        {[{id:'campaigns',label:'📣 Campaigns'},{id:'funnel',label:'🔽 Funnel'},{id:'content',label:'✍️ Content'}].map(t=>(
          <button key={t.id} onClick={()=>setTab(t.id)} style={{ background:tab===t.id?C.card:'transparent', color:tab===t.id?C.accent:C.muted, border:'none', padding:'9px 18px', borderRadius:'8px', fontWeight:'700', cursor:'pointer', fontSize:'12px', boxShadow:tab===t.id?'0 1px 3px rgba(0,0,0,0.1)':'none' }}>{t.label}</button>
        ))}
      </div>

      {tab==='campaigns' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#fdf2f8' }}>
              {['Campaign','Channel','Sent','Open Rate','CTR','Leads','Status'].map(h=>(
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {CAMPAIGNS.map(c => {
                const st = ST[c.status];
                return (
                  <tr key={c.name} style={{ borderTop:`1px solid ${C.border}` }}>
                    <td style={{ padding:'14px 20px', fontWeight:'700', fontSize:'13px', color:C.text }}>{c.name}</td>
                    <td style={{ padding:'14px 20px' }}><span style={{ background:'#fce7f3', color:C.accent, padding:'3px 10px', borderRadius:'100px', fontSize:'11px', fontWeight:'700' }}>{c.channel}</span></td>
                    <td style={{ padding:'14px 20px', color:C.muted, fontSize:'13px' }}>{c.sent ? c.sent.toLocaleString() : '—'}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.blue }}>{c.opens || '—'}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.purple }}>{c.clicks}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'800', color:C.green }}>{c.leads}</td>
                    <td style={{ padding:'14px 20px' }}><span style={{ background:st.bg, color:st.color, padding:'3px 10px', borderRadius:'100px', fontSize:'11px', fontWeight:'700' }}>{c.status}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {tab==='funnel' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'32px', display:'flex', flexDirection:'column', alignItems:'center', gap:'0' }}>
          <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'28px', alignSelf:'flex-start' }}>🔽 Marketing Funnel</div>
          {[
            { stage:'Impressions',    value:'284,000', pct:100, color:'#be185d' },
            { stage:'Clicks',         value:'23,856',  pct:84,  color:'#db2777' },
            { stage:'Landing Page',   value:'14,200',  pct:65,  color:'#ec4899' },
            { stage:'Form Submitted', value:'2,840',   pct:40,  color:'#f472b6' },
            { stage:'MQL',            value:'544',     pct:25,  color:'#f9a8d4' },
            { stage:'SQL',            value:'148',     pct:12,  color:'#fce7f3' },
          ].map((s,i) => (
            <div key={s.stage} style={{ width:`${s.pct}%`, background:s.color, padding:'14px 24px', display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:'2px', borderRadius:i===0?'12px 12px 0 0':i===5?'0 0 12px 12px':'0', transition:'width 0.6s ease' }}>
              <span style={{ fontSize:'13px', fontWeight:'700', color:i>3?C.accent:'#fff' }}>{s.stage}</span>
              <span style={{ fontSize:'14px', fontWeight:'900', color:i>3?C.accent:'#fff' }}>{s.value}</span>
            </div>
          ))}
        </div>
      )}

      {tab==='content' && (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20px' }}>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>✍️ Content Calendar</div>
            {[
              { date:'Mar 20', type:'Blog Post',    title:'10 CRM Trends for 2026',         status:'Draft'     },
              { date:'Mar 22', type:'Email',        title:'Q1 Product Update Newsletter',   status:'Scheduled' },
              { date:'Mar 25', type:'Webinar',      title:'AI-Powered Sales Automation',    status:'Confirmed' },
              { date:'Mar 28', type:'Social',       title:'LinkedIn Thought Leadership',    status:'Draft'     },
              { date:'Apr 01', type:'Case Study',   title:'How ACME 3x\'d Pipeline',        status:'Review'    },
            ].map(c => (
              <div key={c.title} style={{ display:'flex', gap:'12px', padding:'10px 0', borderBottom:`1px solid ${C.border}`, alignItems:'center' }}>
                <div style={{ fontSize:'11px', color:C.muted, width:'40px', flexShrink:0 }}>{c.date}</div>
                <span style={{ background:'#fce7f3', color:C.accent, padding:'2px 8px', borderRadius:'6px', fontSize:'10px', fontWeight:'700', flexShrink:0 }}>{c.type}</span>
                <span style={{ fontSize:'13px', color:C.text, flex:1 }}>{c.title}</span>
                <span style={{ fontSize:'11px', color:C.muted }}>{c.status}</span>
              </div>
            ))}
          </div>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>📊 Channel Performance</div>
            {[
              { ch:'Organic Search', leads:312, cost:'$0',     roi:'∞'    },
              { ch:'Email',          leads:148, cost:'$1,240', roi:'840%' },
              { ch:'LinkedIn',       leads:64,  cost:'$3,200', roi:'320%' },
              { ch:'Google Ads',     leads:89,  cost:'$4,100', roi:'280%' },
              { ch:'Events',         leads:212, cost:'$8,500', roi:'410%' },
            ].map(r => (
              <div key={r.ch} style={{ display:'flex', justifyContent:'space-between', padding:'10px 0', borderBottom:`1px solid ${C.border}` }}>
                <span style={{ fontSize:'13px', color:C.text, fontWeight:'600' }}>{r.ch}</span>
                <div style={{ display:'flex', gap:'16px' }}>
                  <span style={{ fontSize:'12px', color:C.green, fontWeight:'700' }}>{r.leads} leads</span>
                  <span style={{ fontSize:'12px', color:C.muted }}>{r.cost}</span>
                  <span style={{ fontSize:'12px', color:C.accent, fontWeight:'800' }}>ROI {r.roi}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
