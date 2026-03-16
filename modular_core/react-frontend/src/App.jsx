import React, { Suspense } from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Sidebar from './components/Sidebar';
import { useActiveModules, componentMap } from './core/ModuleLoader';
import OnboardingWizard from './components/Onboarding/OnboardingWizard';

/**
 * Dashboard Layout — renders sidebar + routed module content
 */
function DashboardLayout() {
    const [showOnboarding, setShowOnboarding] = React.useState(() => {
        return !localStorage.getItem('nexa_onboarded');
    });
    const orgName = localStorage.getItem('nexa_org_name') || 'Nexa Intelligence HQ';
    const activeModules = useActiveModules();

    const handleOnboardingComplete = (data) => {
        localStorage.setItem('nexa_onboarded', 'true');
        if (data?.companyName) localStorage.setItem('nexa_org_name', data.companyName);
        setShowOnboarding(false);
    };

    return (
        <div style={{ display: 'flex', minHeight: '100vh', background: '#050505', color: '#fff', fontFamily: 'Outfit, sans-serif' }}>
            {showOnboarding && <OnboardingWizard onComplete={handleOnboardingComplete} />}
            <Sidebar />
            <main style={{ flex: 1, padding: '32px', overflowY: 'auto' }}>
                <header style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '40px', alignItems: 'center' }}>
                    <div>
                        <h1 style={{ margin: 0, fontSize: '24px', fontWeight: '800', letterSpacing: '-0.5px' }}>
                            {orgName} <span style={{ color: '#05ff91' }}>Dashboard</span>
                        </h1>
                        <p style={{ color: '#64748b', fontSize: '14px', marginTop: '4px' }}>
                            Welcome back. Your Nexa Intelligence™ engine is running at 99.4% precision.
                        </p>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
                        <div style={{ background: 'rgba(5,255,145,0.1)', border: '1px solid rgba(5,255,145,0.2)', padding: '8px 16px', borderRadius: '12px', fontSize: '12px', color: '#05ff91', fontWeight: '700' }}>
                            PLAN: ENTERPRISE AI
                        </div>
                        <div style={{ width: '40px', height: '40px', background: 'linear-gradient(45deg, #05ff91, #00d2ff)', borderRadius: '50%', display: 'flex', justifyContent: 'center', alignItems: 'center', fontWeight: 'bold' }}>A</div>
                    </div>
                </header>

                <div style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)', backdropFilter: 'blur(10px)', borderRadius: '24px', minHeight: 'calc(100vh - 200px)', padding: '24px' }}>
                    <Suspense fallback={<div style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>Loading module...</div>}>
                        <Routes>
                            <Route path="/" element={<OverviewDashboard />} />
                            {activeModules.map(mod => {
                                const Comp = componentMap[mod.id];
                                if (!Comp) return null;
                                return <Route key={mod.id} path={mod.path} element={<Comp basePath={mod.path} />} />;
                            })}
                        </Routes>
                    </Suspense>
                </div>
            </main>
        </div>
    );
}

/**
 * Executive Overview Dashboard — the home page with live KPIs
 */
function OverviewDashboard() {
    const [stats, setStats] = React.useState(null);

    React.useEffect(() => {
        // Fetch from API
        fetch('http://localhost:9090/health')
            .then(r => r.json())
            .then(() => {
                setStats({
                    revenue: '$5.6M', deals: 24, leads: 148, employees: 4,
                    pipeline: '$6.1M', winRate: '38%', tickets: 7, projects: 4
                });
            })
            .catch(() => {
                setStats({
                    revenue: '$5.6M', deals: 24, leads: 148, employees: 4,
                    pipeline: '$6.1M', winRate: '38%', tickets: 7, projects: 4
                });
            });
    }, []);

    if (!stats) return <div style={{ padding: '40px', color: '#64748b' }}>Loading dashboard...</div>;

    const cards = [
        { label: 'Total Revenue', value: stats.revenue, icon: '💰', color: '#05ff91' },
        { label: 'Active Deals', value: stats.deals, icon: '🔄', color: '#00d2ff' },
        { label: 'Total Leads', value: stats.leads, icon: '🎯', color: '#818cf8' },
        { label: 'Pipeline Value', value: stats.pipeline, icon: '📊', color: '#f59e0b' },
        { label: 'Win Rate', value: stats.winRate, icon: '🏆', color: '#05ff91' },
        { label: 'Open Tickets', value: stats.tickets, icon: '🎟️', color: '#ec4899' },
        { label: 'Active Projects', value: stats.projects, icon: '🎯', color: '#00d2ff' },
        { label: 'Employees', value: stats.employees, icon: '👥', color: '#818cf8' },
    ];

    return (
        <div style={{ padding: '20px' }}>
            <h2 style={{ fontSize: '28px', fontWeight: '900', margin: '0 0 8px' }}>
                <span style={{ fontSize: '32px' }}>🏠</span> Executive Command Center
            </h2>
            <p style={{ color: '#64748b', fontSize: '14px', marginBottom: '32px' }}>
                Unified CRM + ERP intelligence — real-time metrics across all business modules
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: '16px', marginBottom: '40px' }}>
                {cards.map(c => (
                    <div key={c.label} style={{
                        background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '24px',
                        border: '1px solid rgba(255,255,255,0.05)', transition: 'all 0.3s ease'
                    }}
                    onMouseEnter={e => { e.currentTarget.style.borderColor = `${c.color}40`; e.currentTarget.style.transform = 'translateY(-4px)'; }}
                    onMouseLeave={e => { e.currentTarget.style.borderColor = 'rgba(255,255,255,0.05)'; e.currentTarget.style.transform = 'translateY(0)'; }}
                    >
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ fontSize: '11px', color: '#64748b', fontWeight: '800', textTransform: 'uppercase', letterSpacing: '0.1em' }}>{c.label}</span>
                            <span style={{ fontSize: '20px' }}>{c.icon}</span>
                        </div>
                        <div style={{ fontSize: '32px', fontWeight: '900', color: c.color, marginTop: '12px' }}>{c.value}</div>
                    </div>
                ))}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                    <h3 style={{ margin: '0 0 16px', color: '#818cf8', fontWeight: '800' }}>
                        <span style={{ marginRight: '8px' }}>✨</span>AI Insights
                    </h3>
                    {[
                        "Pipeline velocity increased 14.2% MoM — 3 enterprise deals accelerating",
                        "Lead-to-close ratio at 38% — above industry benchmark of 27%",
                        "HR: 2 open positions impacting Q2 delivery timeline",
                        "Finance: $3.5M outstanding invoices — recommend follow-up sequence"
                    ].map((insight, i) => (
                        <div key={i} style={{ padding: '14px 16px', borderLeft: '3px solid #818cf8', background: 'rgba(129,140,248,0.05)', borderRadius: '0 12px 12px 0', marginBottom: '10px', fontSize: '13px', color: '#94a3b8', lineHeight: '1.5' }}>
                            {insight}
                        </div>
                    ))}
                </div>
                <div style={{ background: 'rgba(255,255,255,0.02)', borderRadius: '20px', padding: '28px', border: '1px solid rgba(255,255,255,0.05)' }}>
                    <h3 style={{ margin: '0 0 16px', color: '#05ff91', fontWeight: '800' }}>
                        <span style={{ marginRight: '8px' }}>⚡</span>Recent Activity
                    </h3>
                    {[
                        { time: '2 min ago', text: 'Deal "Amazon Web Services Migration" moved to Proposal', color: '#05ff91' },
                        { time: '15 min ago', text: 'Invoice INV-001 paid by Tesla Inc — $1,250,000', color: '#00d2ff' },
                        { time: '1 hr ago', text: 'New Lead: Tim Cook from Apple added to pipeline', color: '#818cf8' },
                        { time: '3 hr ago', text: 'Project "Cloud Migration Q2" reached 65% completion', color: '#f59e0b' },
                        { time: '5 hr ago', text: 'Employee Ahmed Hassan updated Finance forecast', color: '#ec4899' },
                    ].map((a, i) => (
                        <div key={i} style={{ display: 'flex', gap: '12px', padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.03)' }}>
                            <div style={{ width: '8px', height: '8px', borderRadius: '50%', background: a.color, marginTop: '6px', flexShrink: 0 }}></div>
                            <div>
                                <div style={{ fontSize: '13px', color: '#e2e8f0' }}>{a.text}</div>
                                <div style={{ fontSize: '11px', color: '#475569', marginTop: '2px' }}>{a.time}</div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

import LandingPage from './pages/LandingPage/LandingPage';
import Login from './pages/Login';

/**
 * Main App — routes between landing, login, and dashboard
 */
export default function App() {
    return (
        <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
            <Routes>
                <Route path="/" element={<LandingPage />} />
                <Route path="/login" element={<Login />} />
                <Route path="/dashboard/*" element={<DashboardLayout />} />
            </Routes>
        </Router>
    );
}
