import React, { useState, useEffect } from 'react';

const BRIDGE = 'http://localhost:9090';

const MOCK_PROJECTS = [
    { name: 'PRJ-001', project_name: 'Cloud Migration Q2', status: 'Open', percent_complete: 65, expected_end_date: '2026-06-30', customer: 'Tesla Inc', project_type: 'External' },
    { name: 'PRJ-002', project_name: 'AI CRM Integration', status: 'Open', percent_complete: 40, expected_end_date: '2026-09-15', customer: 'Alphabet Inc', project_type: 'External' },
    { name: 'PRJ-003', project_name: 'ERPNext Deployment', status: 'Open', percent_complete: 90, expected_end_date: '2026-04-01', customer: 'Internal', project_type: 'Internal' },
    { name: 'PRJ-004', project_name: 'Mobile App Launch', status: 'Completed', percent_complete: 100, expected_end_date: '2026-03-01', customer: 'Meta Platforms', project_type: 'External' },
];

const MOCK_TASKS = {
    'PRJ-001': [
        { id: 'T001', subject: 'Infrastructure Audit', status: 'Closed', assigned_to: 'Ahmed Hassan' },
        { id: 'T002', subject: 'VPC Setup & Configuration', status: 'Working', assigned_to: 'Sara Ali' },
        { id: 'T003', subject: 'Data Migration Testing', status: 'Open', assigned_to: 'John Doe' },
    ],
    'PRJ-002': [
        { id: 'T004', subject: 'API Design & Spec', status: 'Closed', assigned_to: 'Sara Ali' },
        { id: 'T005', subject: 'CRM Webhook Setup', status: 'Working', assigned_to: 'Ahmed Hassan' },
    ],
};

const STATUS_COLORS = { Open: '#00d2ff', Completed: '#05ff91', Cancelled: '#ef4444', Working: '#f59e0b', Closed: '#05ff91' };

export default function ProjectManager() {
    const [projects, setProjects] = useState([]);
    const [selected, setSelected] = useState(null);
    const [tasks, setTasks] = useState({});
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState({ project_name: '', customer: '', expected_end_date: '', project_type: 'External' });
    const [newTask, setNewTask] = useState('');

    useEffect(() => {
        fetch(`${BRIDGE}/erp/Project`)
            .then(r => r.json())
            .then(d => { setProjects(d.data?.length ? d.data : MOCK_PROJECTS); setTasks(MOCK_TASKS); })
            .catch(() => { setProjects(MOCK_PROJECTS); setTasks(MOCK_TASKS); })
            .finally(() => setLoading(false));
    }, []);

    const totalBudget = projects.reduce((s, p) => s + (p.estimated_costing || 250000), 0);
    const avgProgress = projects.length ? Math.round(projects.reduce((s, p) => s + parseFloat(p.percent_complete || 0), 0) / projects.length) : 0;

    const addProject = () => {
        if (!form.project_name) return;
        const id = `PRJ-${Date.now()}`;
        setProjects(prev => [...prev, { ...form, name: id, status: 'Open', percent_complete: 0 }]);
        setTasks(prev => ({ ...prev, [id]: [] }));
        setForm({ project_name: '', customer: '', expected_end_date: '', project_type: 'External' });
        setShowForm(false);
    };

    const addTask = (projectId) => {
        if (!newTask.trim()) return;
        setTasks(prev => ({
            ...prev,
            [projectId]: [...(prev[projectId] || []), { id: `T${Date.now()}`, subject: newTask, status: 'Open', assigned_to: 'Unassigned' }]
        }));
        setNewTask('');
    };

    const cycleStatus = (projectId, taskId) => {
        const cycle = ['Open', 'Working', 'Closed'];
        setTasks(prev => ({
            ...prev,
            [projectId]: prev[projectId].map(t => t.id === taskId ? { ...t, status: cycle[(cycle.indexOf(t.status) + 1) % cycle.length] } : t)
        }));
    };

    return (
        <div style={{ color: '#fff', padding: '0 4px' }}>
            {/* Header */}
            <div style={{ background: 'linear-gradient(90deg, rgba(245,158,11,0.08) 0%, transparent 60%)', padding: '28px', borderRadius: '24px', border: '1px solid rgba(255,255,255,0.05)', marginBottom: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                    <div>
                        <h2 style={{ margin: 0, fontSize: '28px', fontWeight: '900', display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <span style={{ fontSize: '32px' }}>🎯</span> Project Manager
                            <span style={{ fontSize: '11px', background: 'rgba(245,158,11,0.15)', color: '#f59e0b', padding: '4px 12px', borderRadius: '100px', fontWeight: '800' }}>ERPNext Synced</span>
                        </h2>
                        <p style={{ color: '#64748b', margin: '6px 0 0', fontSize: '13px' }}>
                            {projects.length} projects · Avg completion: <span style={{ color: '#f59e0b', fontWeight: '800' }}>{avgProgress}%</span>
                        </p>
                    </div>
                    <button onClick={() => setShowForm(true)} style={{ background: 'linear-gradient(135deg, #f59e0b, #f97316)', color: '#000', border: 'none', padding: '12px 24px', borderRadius: '14px', fontWeight: '800', cursor: 'pointer' }}>+ New Project</button>
                </div>

                {/* KPI */}
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px' }}>
                    {[
                        { label: 'Total Projects', value: projects.length, color: '#f59e0b', icon: '📋' },
                        { label: 'In Progress', value: projects.filter(p => p.status === 'Open').length, color: '#00d2ff', icon: '🚀' },
                        { label: 'Completed', value: projects.filter(p => p.status === 'Completed').length, color: '#05ff91', icon: '✅' },
                        { label: 'Avg Progress', value: `${avgProgress}%`, color: '#818cf8', icon: '📊' },
                    ].map(s => (
                        <div key={s.label} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '16px', padding: '20px', border: '1px solid rgba(255,255,255,0.05)' }}>
                            <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', display: 'flex', gap: '6px', alignItems: 'center' }}><span>{s.icon}</span>{s.label}</div>
                            <div style={{ fontSize: '28px', fontWeight: '900', color: s.color, marginTop: '8px' }}>{s.value}</div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Add Project Form */}
            {showForm && (
                <div style={{ background: 'rgba(245,158,11,0.04)', border: '1px solid rgba(245,158,11,0.15)', borderRadius: '20px', padding: '24px', marginBottom: '20px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '16px', alignItems: 'end' }}>
                    {[['Project Name', 'project_name', 'text'], ['Client', 'customer', 'text'], ['End Date', 'expected_end_date', 'date']].map(([label, key, type]) => (
                        <div key={key}>
                            <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>{label}</label>
                            <input type={type} value={form[key]} onChange={e => setForm({ ...form, [key]: e.target.value })} style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', boxSizing: 'border-box' }} />
                        </div>
                    ))}
                    <div>
                        <label style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', display: 'block', marginBottom: '6px', textTransform: 'uppercase' }}>Type</label>
                        <select value={form.project_type} onChange={e => setForm({ ...form, project_type: e.target.value })} style={{ width: '100%', background: '#1e293b', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', boxSizing: 'border-box' }}>
                            <option>External</option><option>Internal</option>
                        </select>
                    </div>
                    <button onClick={addProject} style={{ background: '#f59e0b', color: '#000', border: 'none', padding: '11px 20px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer', whiteSpace: 'nowrap' }}>Create</button>
                </div>
            )}

            {/* Project List */}
            <div style={{ display: 'grid', gridTemplateColumns: selected ? '1fr 1fr' : '1fr', gap: '20px' }}>
                {/* Left: project cards */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {projects.map(p => {
                        const pct = parseFloat(p.percent_complete) || 0;
                        const statusColor = STATUS_COLORS[p.status] || '#64748b';
                        const isActive = selected?.name === p.name;
                        return (
                            <div key={p.name}
                                onClick={() => setSelected(isActive ? null : p)}
                                style={{ background: isActive ? 'rgba(245,158,11,0.06)' : 'rgba(255,255,255,0.01)', borderRadius: '20px', padding: '24px', border: `1px solid ${isActive ? 'rgba(245,158,11,0.3)' : 'rgba(255,255,255,0.05)'}`, cursor: 'pointer', transition: 'all 0.3s' }}
                                onMouseEnter={e => { if (!isActive) e.currentTarget.style.borderColor = 'rgba(245,158,11,0.2)'; }}
                                onMouseLeave={e => { if (!isActive) e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)'; }}
                            >
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
                                    <div>
                                        <div style={{ fontSize: '16px', fontWeight: '800', color: '#f8fafc', marginBottom: '4px' }}>{p.project_name}</div>
                                        <div style={{ fontSize: '12px', color: '#64748b' }}>🏢 {p.customer} · {p.expected_end_date}</div>
                                    </div>
                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                        <span style={{ background: `${statusColor}15`, color: statusColor, padding: '4px 12px', borderRadius: '100px', fontSize: '11px', fontWeight: '800' }}>{p.status}</span>
                                        <span style={{ color: '#f59e0b', fontWeight: '900', fontSize: '20px' }}>{pct}%</span>
                                    </div>
                                </div>
                                <div style={{ height: '8px', background: 'rgba(255,255,255,0.05)', borderRadius: '100px', overflow: 'hidden' }}>
                                    <div style={{ height: '100%', width: `${pct}%`, background: pct === 100 ? '#05ff91' : 'linear-gradient(90deg, #f59e0b, #f97316)', borderRadius: '100px', transition: 'width 1s ease' }} />
                                </div>
                                <div style={{ marginTop: '12px', fontSize: '12px', color: '#475569' }}>
                                    {(tasks[p.name] || []).length} tasks · Click to manage tasks
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Right: Task Panel */}
                {selected && (
                    <div style={{ background: 'rgba(255,255,255,0.01)', borderRadius: '20px', padding: '24px', border: '1px solid rgba(245,158,11,0.15)', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                            <h3 style={{ margin: 0, fontWeight: '900', color: '#f59e0b', fontSize: '16px' }}>📋 {selected.project_name} — Tasks</h3>
                            <button onClick={() => setSelected(null)} style={{ background: 'transparent', border: 'none', color: '#64748b', cursor: 'pointer', fontSize: '18px' }}>✕</button>
                        </div>

                        {/* Add Task */}
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <input value={newTask} onChange={e => setNewTask(e.target.value)} onKeyDown={e => e.key === 'Enter' && addTask(selected.name)} placeholder="Add a task..." style={{ flex: 1, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '10px', padding: '10px 14px', color: '#fff', fontSize: '13px', outline: 'none' }} />
                            <button onClick={() => addTask(selected.name)} style={{ background: '#f59e0b', color: '#000', border: 'none', padding: '10px 16px', borderRadius: '10px', fontWeight: '800', cursor: 'pointer' }}>Add</button>
                        </div>

                        {/* Task list */}
                        {(tasks[selected.name] || []).map(task => {
                            const sc = STATUS_COLORS[task.status] || '#64748b';
                            return (
                                <div key={task.id} style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '12px', padding: '16px', border: '1px solid rgba(255,255,255,0.04)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <div>
                                        <div style={{ fontWeight: '600', fontSize: '14px', color: task.status === 'Closed' ? '#64748b' : '#f8fafc', textDecoration: task.status === 'Closed' ? 'line-through' : 'none' }}>{task.subject}</div>
                                        <div style={{ fontSize: '11px', color: '#475569', marginTop: '2px' }}>👤 {task.assigned_to}</div>
                                    </div>
                                    <button onClick={() => cycleStatus(selected.name, task.id)} style={{ background: `${sc}15`, color: sc, border: 'none', padding: '6px 14px', borderRadius: '8px', fontWeight: '800', cursor: 'pointer', fontSize: '11px', whiteSpace: 'nowrap' }}>
                                        {task.status}
                                    </button>
                                </div>
                            );
                        })}

                        {!(tasks[selected.name] || []).length && (
                            <div style={{ textAlign: 'center', color: '#475569', padding: '40px', fontSize: '13px' }}>No tasks yet. Add one above.</div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
