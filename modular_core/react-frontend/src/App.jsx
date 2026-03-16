import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, Link, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth, Can } from './core/AuthContext';
import LoginPage from './modules/Auth/LoginPage';
import AnalyticsDashboard from './modules/Analytics';
import LeadsPage from './modules/Leads';
const MessagingInbox = () => <div style={styles.page}><h1>Omnichannel Inbox</h1><p>Unified inbox for WhatsApp, Telegram, and Email.</p></div>;
const AICenter = () => <div style={styles.page}><h1>AI Intelligence Center</h1><p>Configure custom scoring and auto-reply rules.</p></div>;
const BillingSettings = () => <div style={styles.page}><h1>Billing & Subscriptions</h1><p>Manage your organization's plan and invoices.</p></div>;
const AdminSettings = () => <div style={styles.page}><h1>System Settings</h1><p>Manage users, roles, and integrations.</p></div>;

function ProtectedRoute({ children, module, action }) {
    const { user, loading, can } = useAuth();
    if (loading) return null;
    if (!user) return <Navigate to="/login" />;
    if (module && action && !can(module, action)) return <Navigate to="/dashboard" />;
    return children;
}

function Layout({ children }) {
    const { logout, user } = useAuth();
    const location = useLocation();

    const menuItems = [
        { label: 'Dashboard', path: '/dashboard', icon: '📊' },
        { label: 'Leads', path: '/leads', icon: '👤', module: 'leads', action: 'read' },
        { label: 'Inbox', path: '/inbox', icon: '💬', module: 'messaging', action: 'read' },
        { label: 'AI Center', path: '/ai', icon: '🤖', module: 'ai', action: 'scoring' },
        { label: 'Billing', path: '/billing', icon: '💳', module: 'billing', action: 'view' },
        { label: 'Settings', path: '/settings', icon: '⚙️', module: 'settings', action: 'branding' },
    ];

    return (
        <div style={styles.app}>
            <aside style={styles.sidebar}>
                <div style={styles.logo}>AI RevOS</div>
                <nav style={styles.nav}>
                    {menuItems.map(item => (
                        <MenuItem key={item.path} item={item} active={location.pathname === item.path} />
                    ))}
                </nav>
                <div style={styles.footer}>
                    <div style={styles.userInfo}>
                        <div style={styles.avatar}>{user?.name?.[0]}</div>
                        <div style={styles.userName}>{user?.name}</div>
                    </div>
                    <button onClick={logout} style={styles.logoutBtn}>Logout</button>
                </div>
            </aside>
            <main style={styles.main}>
                <header style={styles.header}>
                    <div style={styles.breadcrumb}>Home {location.pathname.replace('/', ' > ')}</div>
                    <div style={styles.headerActions}>
                        <span style={styles.tenantBadge}>Tenant ID: {user?.tenant_id}</span>
                    </div>
                </header>
                <div style={styles.content}>
                    {children}
                </div>
            </main>
        </div>
    );
}

function MenuItem({ item, active }) {
    const { can } = useAuth();
    if (item.module && item.action && !can(item.module, item.action)) return null;

    return (
        <Link to={item.path} style={{...styles.navLink, ...(active ? styles.navLinkActive : {})}}>
            <span style={styles.navIcon}>{item.icon}</span>
            <span style={styles.navLabel}>{item.label}</span>
        </Link>
    );
}

export default function App() {
    return (
        <AuthProvider>
            <Router>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    
                    <Route path="/dashboard" element={
                        <ProtectedRoute>
                            <Layout><AnalyticsDashboard /></Layout>
                        </ProtectedRoute>
                    } />

                    <Route path="/leads" element={
                        <ProtectedRoute module="leads" action="read">
                            <Layout><LeadsPage /></Layout>
                        </ProtectedRoute>
                    } />

                    <Route path="/inbox" element={
                        <ProtectedRoute module="messaging" action="read">
                            <Layout><MessagingInbox /></Layout>
                        </ProtectedRoute>
                    } />

                    <Route path="/ai" element={
                        <ProtectedRoute module="ai" action="scoring">
                            <Layout><AICenter /></Layout>
                        </ProtectedRoute>
                    } />

                    <Route path="/billing" element={
                        <ProtectedRoute module="billing" action="view">
                            <Layout><BillingSettings /></Layout>
                        </ProtectedRoute>
                    } />

                    <Route path="/settings" element={
                        <ProtectedRoute module="settings" action="branding">
                            <Layout><AdminSettings /></Layout>
                        </ProtectedRoute>
                    } />

                    <Route path="/" element={<Navigate to="/dashboard" />} />
                </Routes>
            </Router>
        </AuthProvider>
    );
}

const styles = {
    app: { display: 'flex', height: '100vh', background: '#f8fafc', color: '#1e293b', fontFamily: 'Inter, sans-serif' },
    sidebar: { width: '260px', background: '#0f172a', color: '#fff', display: 'flex', flexDirection: 'column' },
    logo: { padding: '24px', fontSize: '20px', fontWeight: '800', letterSpacing: '-0.5px', borderBottom: '1px solid #1e293b' },
    nav: { flex: 1, padding: '16px 12px' },
    navLink: { display: 'flex', alignItems: 'center', padding: '12px 16px', borderRadius: '8px', color: '#94a3b8', textDecoration: 'none', marginBottom: '4px', transition: 'all 0.2s' },
    navLinkActive: { background: '#1e293b', color: '#fff' },
    navIcon: { marginRight: '12px', fontSize: '18px' },
    navLabel: { fontSize: '14px', fontWeight: '500' },
    footer: { padding: '16px', borderTop: '1px solid #1e293b' },
    userInfo: { display: 'flex', alignItems: 'center', marginBottom: '16px' },
    avatar: { width: '32px', height: '32px', borderRadius: '8px', background: '#3b82f6', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', marginRight: '10px' },
    userName: { fontSize: '14px', fontWeight: '600' },
    logoutBtn: { width: '100%', padding: '8px', borderRadius: '6px', background: 'transparent', border: '1px solid #1e293b', color: '#94a3b8', cursor: 'pointer', fontSize: '13px' },
    main: { flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' },
    header: { height: '64px', background: '#fff', borderBottom: '1px solid #e2e8f0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 32px' },
    breadcrumb: { color: '#64748b', fontSize: '14px' },
    tenantBadge: { background: '#f1f5f9', color: '#475569', padding: '4px 12px', borderRadius: '100px', fontSize: '12px', fontWeight: '600' },
    content: { flex: 1, overflowY: 'auto' },
    page: { padding: '32px' }
};
