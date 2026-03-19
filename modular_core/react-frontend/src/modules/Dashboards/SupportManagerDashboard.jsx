import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = { bg:'#fef3c7', card:'#fff', border:'#fde68a', accent:'#b45309', muted:'#6b7280', text:'#111827', green:'#16a34a', red:'#dc2626', blue:'#2563eb', orange:'#ea580c' };

const AGENTS = [
  { name:'Yara Samir',   tickets:14, resolved:11, csat:96, avgTime:'8m',  status:'Online'  },
  { name:'Karim Adel',   tickets:9,  resolved:7,  csat:91, avgTime:'14m', status:'Online'  },
  { name:'Nour Hassan',  tickets:12, resolved:10, csat:98, avgTime:'6m',  status:'Busy'    },
  { name:'Tarek Fawzy',  tickets:6,  resolved:4,  csat:88, avgTime:'22m', status:'Offline' },
];

const CHANNEL_STATS = [
  { channel:'📧 Email',     open:18, resolved:42, avgTime:'2h 14m',  sla:'87%' },
  { channel:'💬 Live Chat', open:5,  resolved:28, avgTime:'8m',      sla:'96%' },
  { channel:'📱 WhatsApp',  open:9,  resolved:19, avgTime:'34m',     sla:'91%' },
  { channel:'📞 VoIP',      open:3,  resolved:15, avgTime:'12m',     sla:'94%' },
  { channel:'📩 SMS',       open:4,  resolved:11, avgTime:'1h 5m',   sla:'89%' },
];

export default function SupportManagerDashboard() {
  const { user } = useAuth();
  const [tab, setTab] = useState('overview');

  const totalOpen = CHANNEL_STATS.reduce((s,c)=>s+c.open,0);
  const totalResolved = CHANNEL_STATS.reduce((s,c)=>s+c.resolved,0);
  const avgCsat = Math.round(AGENTS.reduce((s,a)=>s+a.csat,0)/AGENTS.length);

  return (
    <div style={{ padding:'28px', background:C.bg, minHeight:'100%' }}>
      <div style={{ marginBottom:'24px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'10px', marginBottom:'4px' }}>
            <span style={{ fontSize:'26px' }}>📞</span>
            <h1 style={{ margin:0, fontSize:'22px', fontWeight:'800', color:C.text }}>Support Manager Dashboard</h1>
            <span style={{ fontSize:'11px', background:'#fef3c7', color:C.accent, padding:'4px 12px', borderRadius:'100px', fontWeight:'700', border:`1px solid ${C.border}` }}>SUPPORT MGR</span>
          </div>
          <p style={{ margin:0, color:C.muted, fontSize:'13px' }}>{user?.name} · {AGENTS.length} agents · {totalOpen} open · CSAT: {avgCsat}%</p>
        </div>
        <div style={{ display:'flex', gap:'8px' }}>
          <button style={{ background:C.card, border:`1px solid ${C.border}`, color:C.muted, padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>📊 SLA Report</button>
          <button style={{ background:C.accent, border:'none', color:'#fff', padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'700' }}>⚙️ Assign Tickets</button>
        </div>
      </div>

      {/* KPIs */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'14px', marginBottom:'24px' }}>
        {[
          { icon:'🎫', label:'Open Tickets',    value:totalOpen,    color:C.orange },
          { icon:'✅', label:'Resolved Today',  value:totalResolved,color:C.green  },
          { icon:'😊', label:'CSAT Score',      value:`${avgCsat}%`,color:C.green  },
          { icon:'⏱️', label:'Avg Handle Time', value:'14m',         color:C.blue   },
          { icon:'📋', label:'SLA Compliance',  value:'91%',         color:C.accent },
        ].map(k => (
          <div key={k.label} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'14px', padding:'18px' }}>
            <div style={{ fontSize:'20px', marginBottom:'8px' }}>{k.icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color:k.color }}>{k.value}</div>
            <div style={{ fontSize:'12px', color:C.muted, marginTop:'2px' }}>{k.label}</div>
          </div>
        ))}
      </div>

      {/* Tabs */}
      <div style={{ display:'flex', gap:'4px', marginBottom:'20px', background:'#fde68a', borderRadius:'12px', padding:'4px', width:'fit-content' }}>
        {[{id:'overview',label:'📊 Overview'},{id:'agents',label:'👥 Agents'},{id:'channels',label:'📡 Channels'}].map(t=>(
          <button key={t.id} onClick={()=>setTab(t.id)} style={{ background:tab===t.id?C.card:'transparent', color:tab===t.id?C.accent:C.muted, border:'none', padding:'9px 18px', borderRadius:'8px', fontWeight:'700', cursor:'pointer', fontSize:'12px', boxShadow:tab===t.id?'0 1px 3px rgba(0,0,0,0.1)':'none' }}>{t.label}</button>
        ))}
      </div>

      {tab==='overview' && (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20px' }}>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>📡 Tickets by Channel</div>
            {CHANNEL_STATS.map(c => (
              <div key={c.channel} style={{ display:'flex', justifyContent:'space-between', alignItems:'center', padding:'10px 0', borderBottom:`1px solid ${C.border}` }}>
                <span style={{ fontSize:'13px', color:C.text }}>{c.channel}</span>
                <div style={{ display:'flex', gap:'16px' }}>
                  <span style={{ fontSize:'12px', color:C.red, fontWeight:'700' }}>{c.open} open</span>
                  <span style={{ fontSize:'12px', color:C.green, fontWeight:'700' }}>{c.resolved} done</span>
                  <span style={{ fontSize:'12px', color:C.accent, fontWeight:'700' }}>SLA {c.sla}</span>
                </div>
              </div>
            ))}
          </div>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>📈 CSAT Trend (7 days)</div>
            {[{day:'Mon',score:92},{day:'Tue',score:95},{day:'Wed',score:91},{day:'Thu',score:97},{day:'Fri',score:94},{day:'Sat',score:96},{day:'Sun',score:93}].map(d=>(
              <div key={d.day} style={{ display:'flex', alignItems:'center', gap:'12px', marginBottom:'10px' }}>
                <span style={{ fontSize:'12px', color:C.muted, width:'28px' }}>{d.day}</span>
                <div style={{ flex:1, height:'8px', background:'#f3f4f6', borderRadius:'100px', overflow:'hidden' }}>
                  <div style={{ height:'100%', width:`${d.score}%`, background:d.score>=95?C.green:d.score>=90?C.accent:C.red, borderRadius:'100px' }} />
                </div>
                <span style={{ fontSize:'12px', fontWeight:'700', color:C.accent, width:'36px' }}>{d.score}%</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {tab==='agents' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#fef3c7' }}>
              {['Agent','Tickets','Resolved','CSAT','Avg Time','Status'].map(h=>(
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {AGENTS.map(a => {
                const stColor = a.status==='Online'?C.green:a.status==='Busy'?C.orange:C.muted;
                return (
                  <tr key={a.name} style={{ borderTop:`1px solid ${C.border}` }}>
                    <td style={{ padding:'14px 20px' }}>
                      <div style={{ display:'flex', alignItems:'center', gap:'10px' }}>
                        <div style={{ width:'32px', height:'32px', borderRadius:'50%', background:`linear-gradient(135deg,${C.accent},${C.orange})`, display:'flex', alignItems:'center', justifyContent:'center', color:'#fff', fontWeight:'800', fontSize:'13px' }}>{a.name[0]}</div>
                        <span style={{ fontWeight:'700', fontSize:'13px', color:C.text }}>{a.name}</span>
                      </div>
                    </td>
                    <td style={{ padding:'14px 20px', fontWeight:'700', color:C.text }}>{a.tickets}</td>
                    <td style={{ padding:'14px 20px', color:C.green, fontWeight:'700' }}>{a.resolved}</td>
                    <td style={{ padding:'14px 20px', fontWeight:'800', color:a.csat>=95?C.green:a.csat>=90?C.accent:C.red }}>{a.csat}%</td>
                    <td style={{ padding:'14px 20px', color:C.muted, fontSize:'13px' }}>{a.avgTime}</td>
                    <td style={{ padding:'14px 20px' }}><span style={{ color:stColor, fontWeight:'700', fontSize:'12px' }}>● {a.status}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {tab==='channels' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#fef3c7' }}>
              {['Channel','Open','Resolved','Avg Handle Time','SLA Compliance'].map(h=>(
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {CHANNEL_STATS.map(c=>(
                <tr key={c.channel} style={{ borderTop:`1px solid ${C.border}` }}>
                  <td style={{ padding:'14px 20px', fontWeight:'700', fontSize:'14px', color:C.text }}>{c.channel}</td>
                  <td style={{ padding:'14px 20px', color:C.red, fontWeight:'700' }}>{c.open}</td>
                  <td style={{ padding:'14px 20px', color:C.green, fontWeight:'700' }}>{c.resolved}</td>
                  <td style={{ padding:'14px 20px', color:C.muted, fontSize:'13px' }}>{c.avgTime}</td>
                  <td style={{ padding:'14px 20px' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:'10px' }}>
                      <div style={{ width:'80px', height:'6px', background:'#f3f4f6', borderRadius:'100px', overflow:'hidden' }}>
                        <div style={{ height:'100%', width:c.sla, background:parseInt(c.sla)>=95?C.green:parseInt(c.sla)>=90?C.accent:C.red, borderRadius:'100px' }} />
                      </div>
                      <span style={{ fontWeight:'800', color:parseInt(c.sla)>=95?C.green:C.accent }}>{c.sla}</span>
                    </div>
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
