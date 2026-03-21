import React, { useState, useEffect } from 'react';

const BRIDGE = 'http://localhost:9090';

const MOCK_EMPLOYEES = [
    { name: 'EMP-001', employee_name: 'Ahmed Hassan', designation: 'Senior Engineer', department: 'Engineering', status: 'Active', date_of_joining: '2022-01-15' },
    { name: 'EMP-002', employee_name: 'Sara Ali', designation: 'Product Manager', department: 'Product', status: 'Active', date_of_joining: '2021-06-01' },
    { name: 'EMP-003', employee_name: 'John Doe', designation: 'Sales Director', department: 'Sales', status: 'Active', date_of_joining: '2020-03-10' },
    { name: 'EMP-004', employee_name: 'Maria Garcia', designation: 'UX Designer', department: 'Design', status: 'Active', date_of_joining: '2023-02-28' },
];

const MOCK_PAYROLL = [
    { id: 'PAY-001', employee: 'Ahmed Hassan', period: 'Mar 2026', gross: 12500, deductions: 1250, net: 11250, status: 'Paid' },
    { id: 'PAY-002', employee: 'Sara Ali', period: 'Mar 2026', gross: 15000, deductions: 1500, net: 13500, status: 'Pending' },
    { id: 'PAY-003', employee: 'John Doe', period: 'Mar 2026', gross: 18000, deductions: 1800, net: 16200, status: 'Paid' },
    { id: 'PAY-004', employee: 'Maria Garcia', period: 'Mar 2026', gross: 10000, deductions: 1000, net: 9000, status: 'Pending' },
];

export default function HRModule() {
    const [employees, setEmployees] = useState([]);
    const [payroll, setPayroll] = useState(MOCK_PAYROLL);
    const [loading, setLoading] = useState(true);
    const [tab, setTab] = useState('employees');
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ employee_name: '', designation: '', department: '', date_of_joining: '' });

    useEffect(() => {
        fetch(`${BRIDGE}/erp/Employee`)
            .then(r => r.json())
            .then(d => setEmployees(d.data?.length ? d.data : MOCK_EMPLOYEES))
            .catch(() => setEmployees(MOCK_EMPLOYEES))
            .finally(() => setLoading(false));
    }, []);

    const depts = [...new Set(employees.map(e => e.department))];
    const totalPayroll = payroll.reduce((s, p) => s + p.net, 0);

    const addEmployee = () => {
        if (!form.employee_name) return;
        setEmployees(prev => [...prev, { ...form, name: `EMP-${Date.now()}`, status: 'Active', date_of_joining: form.date_of_joining || new Date().toISOString().split('T')[0] }]);
        setForm({ employee_name: '', designation: '', department: '', date_of_joining: '' });
        setShowForm(false);
    };

    const tabs = [{ id: 'employees', label: '👥 Employees' }, { id: 'payroll', label: '💵 Payroll' }, { id: 'analytics', label: '📊 HR Analytics' }];

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(90deg, rgba(236,72,153,0.08) 0%, transparent 60%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>👥</span> Human Resources
                            <span style={{ fontSize: '11px', background: 'rgba(5,255,145,0.15)', color: '#05ff91', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>Qiwa & GOSI Synced</span>
                            <span style={{ fontSize: '11px', background: 'rgba(236,72,153,0.15)', color: '#ec4899', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>ERPNext Active</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            {employees.length} employees · {depts.length} departments · Monthly payroll: <span style={{ color: '#ec4899', fontWeight: '800' }}>SAR {totalPayroll.toLocaleString()}</span>
                        </p>
                    </div>
                    <div style={{ display: 'flex', gap: '12px' }}>
                        <button onClick={() => alert("Syncing with Qiwa... ⏳")} style={{ background: 'rgba(255,255,255,0.05)', color: '#fff', border: '1px solid rgba(255,255,255,0.1)', padding: '12px 20px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer' }}>
                            🔄 Sync Qiwa
                        </button>
                        <button onClick={() => setShowForm(true)} style={{ background: 'linear-gradient(135deg, #ec4899, #f97316)', color: '#fff', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer' }}>
                            + Add Employee
                        </button>
                    </div>
                </div>

                {/* Stats */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginTop: '24px' }}>
                    {[
                        { label: 'Total Staff', value: employees.length, color: '#ec4899', icon: '👥' },
                        { label: 'Departments', value: depts.length, color: '#818cf8', icon: '🏢' },
                        { label: 'Payroll / Mo', value: `$${(totalPayroll / 1000).toFixed(0)}K`, color: '#05ff91', icon: '💰' },
                        { label: 'Active Roles', value: employees.filter(e => e.status === 'Active').length, color: '#00d2ff', icon: '✅' },
                    ].map(s => (
                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '20px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'flex', gap: '6px', alignItems: 'center' }}><span>{s.icon}</span>{s.label}</div>
                            <div style={{ fontSize: '28px', fontWeight: '900', color: s.color, marginTop: '8px' }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Tabs */}
            <div style={{ display: 'flex', gap: '4px', marginBottom: '20px', background: 'rgba(255,255,255,0.02)', borderRadius: '14px', padding: '4px', width: 'fit-content' }}>
                {tabs.map(t => (
                    <button key={t.id} onClick={() => setTab(t.id)} style={{ background: tab === t.id ? 'rgba(236,72,153,0.15)' : 'transparent', color: tab === t.id ? '#ec4899' : '#64748b', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', fontSize: '13px', transition: 'all 0.2s' }}>
                        {t.label}
                    </button>
                ))}
            </div>

            {/* Add Employee Form */}
            {showForm && (
                <div style={{ background: 'rgba(236,72,153,0.04)', border: '1px solid rgba(236,72,153,0.15)', borderRadius: '20px', padding: '28px', marginBottom: '20px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '16px', alignItems: 'end' }}>
                    {[['Name', 'employee_name'], ['Role', 'designation'], ['Dept', 'department'], ['Join Date', 'date_of_joining']].map(([label, key]) => (
                        <div key={key}>
                            <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>{label}</label>
                            <input value={form[key]} onChange={e => setForm({ ...form, [key]: e.target.value })} type={key === 'date_of_joining' ? 'date' : 'text'} style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', boxSizing: 'border-box' }} />
                        </div>
                    ))}
                    <button onClick={addEmployee} style={{ background: '#ec4899', color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', whiteSpace: 'nowrap' }}>Add</button>
                </div>
            )}

            {/* Employees Table */}
            {tab === 'employees' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Employee', 'Role', 'Department', 'Joined', 'Status'].map(h => (
                                <th key={h} style={{ padding: '16px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {employees.map((e, i) => (
                                <tr key={e.name || i} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={ev => ev.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={ev => ev.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 24px' }}>
                                        <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                                            <div style={{ width: '36px', height: '36px', borderRadius: '50%', background: `linear-gradient(135deg, #ec4899, #f97316)`, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '800', fontSize: '14px', color: '#fff' }}>
                                                {(e.employee_name || '?')[0]}
                                            </div>
                                            <div>
                                                <div style={{ fontWeight: '700', color: '#f8fafc' }}>{e.employee_name}</div>
                                                <div style={{ fontSize: '11px', color: '#475569' }}>{e.name}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style={{ padding: '16px 24px', color: '#94a3b8' }}>{e.designation}</td>
                                    <td style={{ padding: '16px 24px' }}><span style={{ background: 'rgba(129,140,248,0.1)', color: '#818cf8', padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800' }}>{e.department}</span></td>
                                    <td style={{ padding: '16px 24px', color: '#94a3b8', fontSize: '13px' }}>{e.date_of_joining}</td>
                                    <td style={{ padding: '16px 24px' }}><span style={{ background: 'rgba(5,255,145,0.1)', color: '#05ff91', padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800' }}>Active</span></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Payroll Table */}
            {tab === 'payroll' && (
                <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', border: '1px solid rgba(255,255,255,0.05)', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead><tr style={{ background: 'rgba(255,255,255,0.02)' }}>
                            {['Slip #', 'Employee', 'Period', 'Gross', 'Deductions', 'Net Pay', 'Status'].map(h => (
                                <th key={h} style={{ padding: '16px 24px', color: '#64748b', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase' }}>{h}</th>
                            ))}
                        </tr></thead>
                        <tbody>
                            {payroll.map(p => (
                                <tr key={p.id} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}
                                    onMouseEnter={e => e.currentTarget.style.background = 'rgba(255,255,255,0.02)'}
                                    onMouseLeave={e => e.currentTarget.style.background = 'transparent'}>
                                    <td style={{ padding: '16px 24px', color: '#818cf8', fontWeight: '700' }}>{p.id}</td>
                                    <td style={{ padding: '16px 24px', fontWeight: '600' }}>{p.employee}</td>
                                    <td style={{ padding: '16px 24px', color: '#94a3b8' }}>{p.period}</td>
                                    <td style={{ padding: '16px 24px', color: '#94a3b8' }}>${p.gross.toLocaleString()}</td>
                                    <td style={{ padding: '16px 24px', color: '#ef4444' }}>-${p.deductions.toLocaleString()}</td>
                                    <td style={{ padding: '16px 24px', color: '#05ff91', fontWeight: '900', fontSize: '16px' }}>${p.net.toLocaleString()}</td>
                                    <td style={{ padding: '16px 24px' }}>
                                        <span style={{ background: p.status === 'Paid' ? 'rgba(5,255,145,0.1)' : 'rgba(245,158,11,0.1)', color: p.status === 'Paid' ? '#05ff91' : '#f59e0b', padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800' }}>{p.status}</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* HR Analytics */}
            {tab === 'analytics' && (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', color: '#ec4899', fontWeight: '800' }}>Department Distribution</h3>
                        {depts.map(d => {
                            const count = employees.filter(e => e.department === d).length;
                            const pct = Math.round((count / employees.length) * 100);
                            return (
                                <div key={d} style={{ marginBottom: '16px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
                                        <span style={{ fontSize: '13px', color: '#94a3b8' }}>{d}</span>
                                        <span style={{ fontSize: '13px', color: '#ec4899', fontWeight: '800' }}>{count} ({pct}%)</span>
                                    </div>
                                    <div style={{ height: '6px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden' }}>
                                        <div style={{ height: '100%', width: `${pct}%`, background: 'linear-gradient(90deg, #ec4899, #f97316)', borderRadius: '100px', transition: 'width 1s ease' }} />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                        <h3 style={{ margin: '0 0 20px', color: '#05ff91', fontWeight: '800' }}>Payroll Summary</h3>
                        {[
                            { label: 'Total Gross', value: `$${payroll.reduce((s, p) => s + p.gross, 0).toLocaleString()}`, color: '#818cf8' },
                            { label: 'Total Deductions', value: `$${payroll.reduce((s, p) => s + p.deductions, 0).toLocaleString()}`, color: '#ef4444' },
                            { label: 'Total Net Pay', value: `$${totalPayroll.toLocaleString()}`, color: '#05ff91' },
                            { label: 'Paid Slips', value: payroll.filter(p => p.status === 'Paid').length, color: '#00d2ff' },
                        ].map(s => (
                            <div key={s.label} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                                <span style={{ fontSize: '13px', color: '#94a3b8' }}>{s.label}</span>
                                <span style={{ fontSize: '18px', color: s.color, fontWeight: '900' }}>{s.value}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
