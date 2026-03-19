import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = { bg:'#fff7ed', card:'#fff', border:'#fed7aa', accent:'#ea580c', muted:'#6b7280', text:'#111827', green:'#16a34a', red:'#dc2626', blue:'#2563eb' };

const TICKETS = [
  { id:'TKT-1041', customer:'Alice Johnson',  channel:'💬 Live Chat', subject:'Cannot login to portal',         priority:'high',   status:'Open',       wait:'2m'  },
  { id:'TKT-1040', customer:'Bob Smith',      channel:'📧 Email',     subject:'Invoice not received',           priority:'medium', status:'In Progress',wait:'18m' },
  { id:'TKT-1039', customer:'Carol Williams', channel:'📱 WhatsApp',  subject:'Feature request — bulk export',  priority:'low',    status:'Open',       wait:'45m' },
  { id:'TKT-1038', customer:'David Brown',    channel:'📞 VoIP',      subject:'Payment failed — card declined', priority:'high',   status:'Resolved',   wait:'—'   },
  { id:'TKT-1037', customer:'Eva Martinez',   channel:'📩 SMS',       subject:'Account upgrade question',       priority:'medium', status:'In Progress',wait:'1h'  },
];

const PR = { high:{ bg:'#fee2e2', color:'#dc2626' }, medium:{ bg:'#fef9c3', color:'#ca8a04' }, low:{ bg:'#dcfce7', color:'#16a34a' } };
const SR = { Open:{ bg:'#dbeafe', color:'#2563eb' }, 'In Progress':{ bg:'#fef9c3', color:'#ca8a04' }, Resolved:{ bg:'#dcfce7', color:'#16a34a' } };

export default function SupportAgentDashboard() {
  const { user } = useAuth();
  const [active, setActive] = useState(null);

  const open = TICKETS.filter(t => t.status !== 'Resolved').length;

  return (
    <div style={{ padding:'28px', background:C.bg, minHeight:'100%' }}>
      <div style={{ marginBottom:'24px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'10px', marginBottom:'4px' }}>
            <span style={{ fontSize:'26px' }}>🎧</span>
            <h1 style={{ margin:0, fontSize:'22px', fontWeight:'800', color:C.text }}>Support Agent Dashboard</h1>
            <span style={{ fontSize:'11px', background:'#ffedd5', color:C.accent, padding:'4px 12px', borderRadius:'100px', fontWeight:'700' }}>SUPPORT AGENT</span>
          </div>
          <p style={{ margin:0, color:C.muted, fontSize:'13px' }}>{user?.name} · {open} open tickets · Avg response: 12 min</p>
        </div>
        <div style={{ display:'flex', gap:'8px' }}>
          <span style={{ background:'#fee2e2', color:'#dc2626', padding:'8px 16px', borderRadius:'10px', fontSize:'13px', fontWeight:'700' }}>🔴 {TICKETS.filter(t=>t.priority==='high'&&t.status!=='Resolved').length} High Priority</span>
          <button style={{ background:C.accent, border:'none', color:'#fff', padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'700' }}>+ New Ticket</button>
        </div>
      </div>

      {/* KPIs */}
      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'14px', marginBottom:'24px' }}>
        {[
          { icon:'🎫', label:'Open Tickets',      value:open,                                                                color:C.blue   },
          { icon:'⚡', label:'High Priority',     value:TICKETS.filter(t=>t.priority==='high').length,                      color:C.red    },
          { icon:'✅', label:'Resolved Today',    value:TICKETS.filter(t=>t.status==='Resolved').length,                    color:C.green  },
          { icon:'⏱️', label:'Avg Response',      value:'12 min',                                                            color:C.accent },
          { icon:'😊', label:'CSAT Score',        value:'94%',                                                               color:C.green  },
        ].map(k => (
          <div key={k.label} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'14px', padding:'18px' }}>
            <div style={{ fontSize:'20px', marginBottom:'8px' }}>{k.icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color:k.color }}>{k.value}</div>
            <div style={{ fontSize:'12px', color:C.muted, marginTop:'2px' }}>{k.label}</div>
          </div>
        ))}
      </div>

      {/* Ticket Queue */}
      <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
        <div style={{ padding:'16px 20px', borderBottom:`1px solid ${C.border}`, display:'flex', justifyContent:'space-between', alignItems:'center' }}>
          <div style={{ fontWeight:'700', fontSize:'15px', color:C.text }}>🎫 My Ticket Queue</div>
          <div style={{ display:'flex', gap:'8px' }}>
            {['All','Open','In Progress','Resolved'].map(f => (
              <button key={f} style={{ background:'#fff7ed', border:`1px solid ${C.border}`, color:C.muted, padding:'5px 12px', borderRadius:'8px', cursor:'pointer', fontSize:'12px', fontWeight:'600' }}>{f}</button>
            ))}
          </div>
        </div>
        <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
          <thead><tr style={{ background:'#fff7ed' }}>
            {['Ticket','Customer','Channel','Subject','Priority','Status','Wait','Action'].map(h=>(
              <th key={h} style={{ padding:'12px 16px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
            ))}
          </tr></thead>
          <tbody>
            {TICKETS.map(t => {
              const pr = PR[t.priority]; const sr = SR[t.status] || SR.Open;
              return (
                <tr key={t.id} style={{ borderTop:`1px solid ${C.border}`, background:active===t.id?'#fff7ed':'transparent', cursor:'pointer' }} onClick={()=>setActive(active===t.id?null:t.id)}>
                  <td style={{ padding:'12px 16px', fontWeight:'700', color:C.accent, fontSize:'12px' }}>{t.id}</td>
                  <td style={{ padding:'12px 16px', fontWeight:'600', fontSize:'13px', color:C.text }}>{t.customer}</td>
                  <td style={{ padding:'12px 16px', fontSize:'12px', color:C.muted }}>{t.channel}</td>
                  <td style={{ padding:'12px 16px', fontSize:'13px', color:C.text, maxWidth:'200px' }}>{t.subject}</td>
                  <td style={{ padding:'12px 16px' }}><span style={{ background:pr.bg, color:pr.color, padding:'2px 8px', borderRadius:'100px', fontSize:'11px', fontWeight:'700', textTransform:'capitalize' }}>{t.priority}</span></td>
                  <td style={{ padding:'12px 16px' }}><span style={{ background:sr.bg, color:sr.color, padding:'2px 8px', borderRadius:'100px', fontSize:'11px', fontWeight:'700' }}>{t.status}</span></td>
                  <td style={{ padding:'12px 16px', fontSize:'12px', color:t.wait==='—'?C.muted:C.red, fontWeight:'600' }}>{t.wait}</td>
                  <td style={{ padding:'12px 16px' }}>
                    <button style={{ background:C.accent, border:'none', color:'#fff', padding:'5px 12px', borderRadius:'8px', cursor:'pointer', fontSize:'11px', fontWeight:'700' }}>Reply</button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
