import React, { useState } from 'react';

const attendance = [
  { date: 'Mon Mar 17', checkIn: '08:55', checkOut: '17:10', hours: '8h 15m', status: 'present' },
  { date: 'Tue Mar 18', checkIn: '09:02', checkOut: '17:05', hours: '8h 03m', status: 'present' },
  { date: 'Wed Mar 19', checkIn: '08:48', checkOut: '—',     hours: 'In progress', status: 'active'  },
  { date: 'Thu Mar 13', checkIn: '—',     checkOut: '—',     hours: '—',      status: 'leave'   },
  { date: 'Fri Mar 14', checkIn: '09:15', checkOut: '17:00', hours: '7h 45m', status: 'present' },
];

const leaveBalance = [
  { type: 'Annual Leave',  total: 21, used: 8,  remaining: 13, color: '#3b82f6' },
  { type: 'Sick Leave',    total: 10, used: 2,  remaining: 8,  color: '#10b981' },
  { type: 'Emergency',     total: 3,  used: 0,  remaining: 3,  color: '#f59e0b' },
  { type: 'Unpaid Leave',  total: 30, used: 0,  remaining: 30, color: '#8b5cf6' },
];

const payslips = [
  { month: 'February 2026', gross: '$4,200', deductions: '$630', net: '$3,570', status: 'paid' },
  { month: 'January 2026',  gross: '$4,200', deductions: '$630', net: '$3,570', status: 'paid' },
  { month: 'December 2025', gross: '$4,500', deductions: '$675', net: '$3,825', status: 'paid' },
];

const myTasks = [
  { id: 1, title: 'Complete onboarding form for new hire',  due: 'Today',    done: false },
  { id: 2, title: 'Submit Q1 performance self-review',      due: 'Mar 22',   done: false },
  { id: 3, title: 'Attend team training session',           due: 'Mar 20',   done: true  },
  { id: 4, title: 'Update emergency contact info',          due: 'Mar 25',   done: false },
];

const statusColor = { present: '#10b981', active: '#3b82f6', leave: '#f59e0b', absent: '#ef4444' };

export default function HRStaffDashboard() {
  const [tab, setTab] = useState('attendance');
  const [tasks, setTasks] = useState(myTasks);

  const toggleTask = (id) => setTasks(prev => prev.map(t => t.id === id ? { ...t, done: !t.done } : t));

  const kpis = [
    { label: 'Days Present (Mar)', value: '14',  icon: '✅', color: '#10b981' },
    { label: 'Leave Remaining',    value: '13d', icon: '🏖', color: '#3b82f6' },
    { label: 'Tasks Pending',      value: '3',   icon: '📋', color: '#f59e0b' },
    { label: 'Net Salary',         value: '$3,570', icon: '💰', color: '#8b5cf6' },
  ];

  return (
    <div style={s.page}>
      <div style={s.topBar}>
        <div>
          <h1 style={s.title}>My HR Portal</h1>
          <p style={s.sub}>Attendance · Leave balance · Payslips · My tasks</p>
        </div>
        <button style={s.btn}>📅 Request Leave</button>
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
        {['attendance', 'leave', 'payslips', 'tasks'].map(t => (
          <button key={t} onClick={() => setTab(t)}
            style={{ ...s.tab, ...(tab === t ? s.tabActive : {}) }}>
            {t === 'attendance' ? '🕐 Attendance' : t === 'leave' ? '🏖 Leave Balance' : t === 'payslips' ? '💰 Payslips' : '📋 My Tasks'}
          </button>
        ))}
      </div>

      {tab === 'attendance' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Date', 'Check In', 'Check Out', 'Hours', 'Status'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {attendance.map((a, i) => (
                <tr key={i} style={s.tr}>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{a.date}</td>
                  <td style={s.td}>{a.checkIn}</td>
                  <td style={s.td}>{a.checkOut}</td>
                  <td style={s.td}>{a.hours}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: statusColor[a.status] + '20', color: statusColor[a.status] }}>
                      {a.status === 'present' ? '✅ Present' : a.status === 'active' ? '🔵 Active' : '🏖 Leave'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'leave' && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '16px' }}>
          {leaveBalance.map(lb => (
            <div key={lb.type} style={s.leaveCard}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '12px' }}>
                <span style={{ fontWeight: '700', color: '#f1f5f9' }}>{lb.type}</span>
                <span style={{ color: lb.color, fontWeight: '800', fontSize: '18px' }}>{lb.remaining}d left</span>
              </div>
              <div style={s.progressBar}>
                <div style={{ ...s.progressFill, width: `${(lb.used / lb.total) * 100}%`, background: lb.color }} />
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '8px', fontSize: '12px', color: '#64748b' }}>
                <span>Used: {lb.used}d</span>
                <span>Total: {lb.total}d</span>
              </div>
            </div>
          ))}
        </div>
      )}

      {tab === 'payslips' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Month', 'Gross', 'Deductions', 'Net Pay', 'Status', ''].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {payslips.map((p, i) => (
                <tr key={i} style={s.tr}>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{p.month}</td>
                  <td style={s.td}>{p.gross}</td>
                  <td style={{ ...s.td, color: '#ef4444' }}>-{p.deductions}</td>
                  <td style={{ ...s.td, fontWeight: '700', color: '#10b981' }}>{p.net}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: '#10b98120', color: '#10b981' }}>✅ Paid</span>
                  </td>
                  <td style={s.td}>
                    <button style={s.dlBtn}>⬇ Download</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'tasks' && (
        <div style={s.card}>
          {tasks.map(task => (
            <div key={task.id} style={{ ...s.taskRow, opacity: task.done ? 0.5 : 1 }}>
              <input type="checkbox" checked={task.done} onChange={() => toggleTask(task.id)} style={{ cursor: 'pointer', width: '16px', height: '16px' }} />
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: '600', color: task.done ? '#475569' : '#f1f5f9', textDecoration: task.done ? 'line-through' : 'none', fontSize: '14px' }}>{task.title}</div>
                <div style={{ fontSize: '12px', color: '#475569', marginTop: '2px' }}>Due: {task.due}</div>
              </div>
              <span style={{ ...s.badge, background: task.done ? '#10b98120' : '#f59e0b20', color: task.done ? '#10b981' : '#f59e0b' }}>
                {task.done ? 'Done' : 'Pending'}
              </span>
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
  btn:         { background: '#3b82f6', color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '14px' },
  kpiRow:      { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '28px' },
  kpiCard:     { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', padding: '20px', textAlign: 'center' },
  kpiIcon:     { width: '44px', height: '44px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px', margin: '0 auto 12px' },
  kpiVal:      { fontSize: '28px', fontWeight: '800', color: '#f1f5f9' },
  kpiLabel:    { fontSize: '12px', color: '#64748b', marginTop: '4px' },
  tabs:        { display: 'flex', gap: '8px', marginBottom: '20px', flexWrap: 'wrap' },
  tab:         { padding: '8px 18px', borderRadius: '8px', border: '1px solid #1e293b', background: 'transparent', color: '#64748b', cursor: 'pointer', fontSize: '13px', fontWeight: '600' },
  tabActive:   { background: '#1e293b', color: '#f1f5f9', borderColor: '#334155' },
  card:        { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', overflow: 'hidden' },
  table:       { width: '100%', borderCollapse: 'collapse' },
  th:          { padding: '12px 16px', textAlign: 'left', fontSize: '11px', fontWeight: '700', color: '#475569', textTransform: 'uppercase', borderBottom: '1px solid #1e293b' },
  tr:          { borderBottom: '1px solid #1e293b' },
  td:          { padding: '14px 16px', fontSize: '13px', color: '#94a3b8' },
  badge:       { padding: '3px 10px', borderRadius: '100px', fontSize: '11px', fontWeight: '700' },
  progressBar: { height: '8px', background: '#1e293b', borderRadius: '4px', overflow: 'hidden' },
  progressFill:{ height: '100%', borderRadius: '4px' },
  leaveCard:   { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', padding: '20px' },
  taskRow:     { display: 'flex', alignItems: 'center', gap: '14px', padding: '16px 20px', borderBottom: '1px solid #1e293b' },
  dlBtn:       { background: 'transparent', border: '1px solid #1e293b', color: '#64748b', padding: '5px 12px', borderRadius: '6px', cursor: 'pointer', fontSize: '12px' },
};
