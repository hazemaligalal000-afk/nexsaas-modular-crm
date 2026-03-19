import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';

const C = { bg:'#f0fdf4', card:'#fff', border:'#bbf7d0', accent:'#15803d', muted:'#6b7280', text:'#111827', green:'#16a34a', red:'#dc2626', yellow:'#d97706', blue:'#1d4ed8' };

const EMPLOYEES = [
  { id:1, name:'Ahmed Hassan',  dept:'Sales',       role:'Sales Rep',      status:'active',   salary:'$4,200', leave:2, perf:92 },
  { id:2, name:'Sara Ali',      dept:'Marketing',   role:'Marketing Lead', status:'active',   salary:'$5,100', leave:5, perf:88 },
  { id:3, name:'Karim Nabil',   dept:'Support',     role:'Support Agent',  status:'active',   salary:'$3,800', leave:0, perf:79 },
  { id:4, name:'Dina Mostafa',  dept:'HR',          role:'HR Staff',       status:'active',   salary:'$4,500', leave:1, perf:95 },
  { id:5, name:'Tarek Mansour', dept:'Engineering', role:'Senior Dev',     status:'on_leave', salary:'$7,200', leave:8, perf:91 },
  { id:6, name:'Mona Sherif',   dept:'Finance',     role:'Accountant',     status:'active',   salary:'$4,800', leave:3, perf:85 },
];

const LEAVE_REQUESTS = [
  { id:1, name:'Tarek Mansour', type:'Annual Leave', from:'Mar 18', to:'Mar 26', days:7, status:'approved' },
  { id:2, name:'Sara Ali',      type:'Sick Leave',   from:'Mar 20', to:'Mar 21', days:2, status:'pending'  },
  { id:3, name:'Karim Nabil',   type:'Annual Leave', from:'Apr 01', to:'Apr 05', days:5, status:'pending'  },
  { id:4, name:'Ahmed Hassan',  type:'Emergency',    from:'Mar 22', to:'Mar 22', days:1, status:'pending'  },
];

const ST = {
  active:   { bg:'#dcfce7', color:'#16a34a' },
  on_leave: { bg:'#fef9c3', color:'#ca8a04' },
  approved: { bg:'#dcfce7', color:'#16a34a' },
  pending:  { bg:'#fef9c3', color:'#ca8a04' },
  rejected: { bg:'#fee2e2', color:'#dc2626' },
};

export default function HRManagerDashboard() {
  const { user } = useAuth();
  const [tab, setTab] = useState('employees');

  return (
    <div style={{ padding:'28px', background:C.bg, minHeight:'100%' }}>
      <div style={{ marginBottom:'24px', display:'flex', justifyContent:'space-between', alignItems:'center' }}>
        <div>
          <div style={{ display:'flex', alignItems:'center', gap:'10px', marginBottom:'4px' }}>
            <span style={{ fontSize:'26px' }}>👥</span>
            <h1 style={{ margin:0, fontSize:'22px', fontWeight:'800', color:C.text }}>HR Manager Dashboard</h1>
            <span style={{ fontSize:'11px', background:'#dcfce7', color:C.accent, padding:'4px 12px', borderRadius:'100px', fontWeight:'700' }}>HR MGR</span>
          </div>
          <p style={{ margin:0, color:C.muted, fontSize:'13px' }}>{user?.name} · {EMPLOYEES.length} Employees · Mar 2026</p>
        </div>
        <div style={{ display:'flex', gap:'8px' }}>
          <button style={{ background:C.card, border:`1px solid ${C.border}`, color:C.muted, padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>📊 HR Report</button>
          <button style={{ background:C.accent, border:'none', color:'#fff', padding:'8px 16px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'600' }}>➕ Add Employee</button>
        </div>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'repeat(5,1fr)', gap:'14px', marginBottom:'24px' }}>
        {[
          { icon:'👤', label:'Total Employees', value:'6',   color:C.accent },
          { icon:'✅', label:'Active',           value:'5',   color:C.green  },
          { icon:'🏖️', label:'On Leave',         value:'1',   color:C.yellow },
          { icon:'📋', label:'Pending Requests', value:'3',   color:C.blue   },
          { icon:'⭐', label:'Avg Performance',  value:'88%', color:C.green  },
        ].map(k => (
          <div key={k.label} style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'14px', padding:'18px', boxShadow:'0 1px 3px rgba(0,0,0,0.05)' }}>
            <div style={{ fontSize:'20px', marginBottom:'8px' }}>{k.icon}</div>
            <div style={{ fontSize:'22px', fontWeight:'800', color:k.color }}>{k.value}</div>
            <div style={{ fontSize:'12px', color:C.muted, marginTop:'2px' }}>{k.label}</div>
          </div>
        ))}
      </div>

      <div style={{ display:'flex', gap:'4px', marginBottom:'20px', background:'#bbf7d0', borderRadius:'12px', padding:'4px', width:'fit-content' }}>
        {[{id:'employees',label:'👥 Employees'},{id:'leave',label:'🏖️ Leave Requests'},{id:'payroll',label:'💰 Payroll'},{id:'performance',label:'⭐ Performance'}].map(t => (
          <button key={t.id} onClick={() => setTab(t.id)} style={{ background:tab===t.id?C.card:'transparent', color:tab===t.id?C.accent:C.muted, border:'none', padding:'9px 18px', borderRadius:'8px', fontWeight:'700', cursor:'pointer', fontSize:'12px', boxShadow:tab===t.id?'0 1px 3px rgba(0,0,0,0.1)':'none' }}>{t.label}</button>
        ))}
      </div>

      {tab==='employees' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#f0fdf4' }}>
              {['Employee','Department','Role','Status','Leave Balance','Performance'].map(h => (
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {EMPLOYEES.map(e => (
                <tr key={e.id} style={{ borderTop:`1px solid ${C.border}` }}>
                  <td style={{ padding:'14px 20px' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:'10px' }}>
                      <div style={{ width:'34px', height:'34px', borderRadius:'50%', background:'#dcfce7', display:'flex', alignItems:'center', justifyContent:'center', fontWeight:'800', color:C.accent, fontSize:'13px' }}>{e.name[0]}</div>
                      <span style={{ fontWeight:'700', fontSize:'13px', color:C.text }}>{e.name}</span>
                    </div>
                  </td>
                  <td style={{ padding:'14px 20px', color:C.muted, fontSize:'13px' }}>{e.dept}</td>
                  <td style={{ padding:'14px 20px', fontSize:'13px' }}>{e.role}</td>
                  <td style={{ padding:'14px 20px' }}>
                    <span style={{ background:ST[e.status].bg, color:ST[e.status].color, padding:'3px 10px', borderRadius:'100px', fontSize:'11px', fontWeight:'700', textTransform:'capitalize' }}>{e.status.replace('_',' ')}</span>
                  </td>
                  <td style={{ padding:'14px 20px', fontWeight:'700', color:e.leave>0?C.yellow:C.muted }}>{e.leave} days</td>
                  <td style={{ padding:'14px 20px' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:'8px' }}>
                      <div style={{ flex:1, height:'6px', background:'#f1f5f9', borderRadius:'100px', overflow:'hidden', minWidth:'60px' }}>
                        <div style={{ height:'100%', width:`${e.perf}%`, background:e.perf>=90?C.green:e.perf>=75?C.yellow:C.red, borderRadius:'100px' }} />
                      </div>
                      <span style={{ fontSize:'12px', fontWeight:'700', color:C.text }}>{e.perf}%</span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab==='leave' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', overflow:'hidden' }}>
          <table style={{ width:'100%', borderCollapse:'collapse', textAlign:'left' }}>
            <thead><tr style={{ background:'#f0fdf4' }}>
              {['Employee','Type','From','To','Days','Status','Actions'].map(h => (
                <th key={h} style={{ padding:'14px 20px', fontSize:'11px', color:C.muted, fontWeight:'800', textTransform:'uppercase' }}>{h}</th>
              ))}
            </tr></thead>
            <tbody>
              {LEAVE_REQUESTS.map(r => (
                <tr key={r.id} style={{ borderTop:`1px solid ${C.border}` }}>
                  <td style={{ padding:'14px 20px', fontWeight:'700', fontSize:'13px', color:C.text }}>{r.name}</td>
                  <td style={{ padding:'14px 20px', color:C.muted, fontSize:'13px' }}>{r.type}</td>
                  <td style={{ padding:'14px 20px', fontSize:'13px' }}>{r.from}</td>
                  <td style={{ padding:'14px 20px', fontSize:'13px' }}>{r.to}</td>
                  <td style={{ padding:'14px 20px', fontWeight:'700', color:C.accent }}>{r.days}</td>
                  <td style={{ padding:'14px 20px' }}>
                    <span style={{ background:ST[r.status].bg, color:ST[r.status].color, padding:'3px 10px', borderRadius:'100px', fontSize:'11px', fontWeight:'700' }}>{r.status}</span>
                  </td>
                  <td style={{ padding:'14px 20px' }}>
                    {r.status==='pending' && (
                      <div style={{ display:'flex', gap:'6px' }}>
                        <button style={{ background:'#dcfce7', border:'none', color:C.green, padding:'5px 12px', borderRadius:'8px', cursor:'pointer', fontSize:'12px', fontWeight:'700' }}>✓ Approve</button>
                        <button style={{ background:'#fee2e2', border:'none', color:C.red, padding:'5px 12px', borderRadius:'8px', cursor:'pointer', fontSize:'12px', fontWeight:'700' }}>✗ Reject</button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab==='payroll' && (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:'20px' }}>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>💰 Payroll Summary — Mar 2026</div>
            {[
              { label:'Gross Payroll',    value:'$29,600', color:C.text  },
              { label:'Tax Deductions',   value:'-$4,440', color:C.red   },
              { label:'Social Insurance', value:'-$1,480', color:C.red   },
              { label:'Net Payroll',      value:'$23,680', color:C.green },
            ].map(r => (
              <div key={r.label} style={{ display:'flex', justifyContent:'space-between', padding:'12px 0', borderBottom:`1px solid ${C.border}` }}>
                <span style={{ fontSize:'13px', color:C.muted }}>{r.label}</span>
                <span style={{ fontSize:'14px', fontWeight:'800', color:r.color }}>{r.value}</span>
              </div>
            ))}
            <button style={{ marginTop:'20px', width:'100%', background:C.accent, border:'none', color:'#fff', padding:'12px', borderRadius:'10px', cursor:'pointer', fontSize:'13px', fontWeight:'700' }}>Run Payroll</button>
          </div>
          <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
            <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'16px' }}>📋 Salary Breakdown</div>
            {EMPLOYEES.map(e => (
              <div key={e.id} style={{ display:'flex', justifyContent:'space-between', alignItems:'center', padding:'10px 0', borderBottom:`1px solid ${C.border}` }}>
                <div>
                  <div style={{ fontSize:'13px', fontWeight:'700', color:C.text }}>{e.name}</div>
                  <div style={{ fontSize:'11px', color:C.muted }}>{e.dept}</div>
                </div>
                <span style={{ fontWeight:'800', color:C.accent }}>{e.salary}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {tab==='performance' && (
        <div style={{ background:C.card, border:`1px solid ${C.border}`, borderRadius:'16px', padding:'24px' }}>
          <div style={{ fontWeight:'700', fontSize:'15px', color:C.text, marginBottom:'20px' }}>⭐ Performance Overview — Q1 2026</div>
          {EMPLOYEES.map(e => (
            <div key={e.id} style={{ marginBottom:'18px' }}>
              <div style={{ display:'flex', justifyContent:'space-between', marginBottom:'6px' }}>
                <div>
                  <span style={{ fontSize:'13px', fontWeight:'700', color:C.text }}>{e.name}</span>
                  <span style={{ fontSize:'11px', color:C.muted, marginLeft:'8px' }}>{e.role}</span>
                </div>
                <span style={{ fontSize:'14px', fontWeight:'800', color:e.perf>=90?C.green:e.perf>=75?C.yellow:C.red }}>{e.perf}%</span>
              </div>
              <div style={{ height:'10px', background:'#f1f5f9', borderRadius:'100px', overflow:'hidden' }}>
                <div style={{ height:'100%', width:`${e.perf}%`, background:e.perf>=90?C.green:e.perf>=75?C.yellow:C.red, borderRadius:'100px' }} />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
