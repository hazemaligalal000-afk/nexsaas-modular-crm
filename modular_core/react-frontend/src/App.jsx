import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, Link, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './core/AuthContext';
import LoginPage from './modules/Auth/LoginPage';
import CommandPalette from './core/CommandPalette';

// Dashboards
import OwnerDashboard from './modules/Dashboards/OwnerDashboard';
import AdminDashboard from './modules/Dashboards/AdminDashboard';
import UserDashboard from './modules/Dashboards/UserDashboard';
import AccountantDashboard from './modules/Dashboards/AccountantDashboard';
import FinanceManagerDashboard from './modules/Dashboards/FinanceManagerDashboard';
import HRManagerDashboard from './modules/Dashboards/HRManagerDashboard';
import SupportAgentDashboard from './modules/Dashboards/SupportAgentDashboard';
import SupportManagerDashboard from './modules/Dashboards/SupportManagerDashboard';
import MarketingDashboard from './modules/Dashboards/MarketingDashboard';
import ProjectManagerDashboard from './modules/Dashboards/ProjectManagerDashboard';
import InventoryManagerDashboard from './modules/Dashboards/InventoryManagerDashboard';
import ITAdminDashboard from './modules/Dashboards/ITAdminDashboard';
import HRStaffDashboard from './modules/Dashboards/HRStaffDashboard';

// Modules
import LeadsPage from './modules/Leads';
import SalesPipeline from './modules/SalesPipeline';
import SalesOps from './modules/SalesOps';
import WorkflowsModule from './modules/Workflows';
import HRModule from './modules/HRModule';
import PartnersModule from './modules/Partners';

// ── Role → Dashboard ───────────────────────────────────────────────────────
function RoleDashboard() {
  const { user } = useAuth();
  const map = {
    owner:            <OwnerDashboard />,
    admin:            <AdminDashboard />,
    sales_rep:        <UserDashboard />,
    accountant:       <AccountantDashboard />,
    finance_manager:  <FinanceManagerDashboard />,
    hr_manager:       <HRManagerDashboard />,
    support_agent:    <SupportAgentDashboard />,
    support_manager:  <SupportManagerDashboard />,
    marketing:        <MarketingDashboard />,
    project_manager:  <ProjectManagerDashboard />,
    inventory_manager:<InventoryManagerDashboard />,
    it_admin:         <ITAdminDashboard />,
    hr_staff:         <HRStaffDashboard />,
  };
  return map[user?.role] || <UserDashboard />;
}

// ── Role → Sidebar menu items ──────────────────────────────────────────────
const ROLE_MENUS = {
  admin: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Leads',      path: '/leads',     icon: '👤' },
    { label: 'Pipeline',   path: '/pipeline',  icon: '🏗️' },
    { label: 'Sales Ops',  path: '/salesops',  icon: '⚡' },
    { label: 'Inbox',      path: '/inbox',     icon: '💬' },
    { label: 'Workflows',  path: '/workflows', icon: '🔄' },
    { label: 'HR',         path: '/hr',        icon: '👥' },
    { label: 'AI Center',  path: '/ai',        icon: '🤖' },
    { label: 'Settings',   path: '/settings',  icon: '⚙️' },
  ],
  sales_rep: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'My Leads',   path: '/leads',     icon: '�' },
    { label: 'My Pipeline',path: '/pipeline',  icon: '🏗️' },
    { label: 'Inbox',      path: '/inbox',     icon: '💬' },
  ],
  accountant: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Vouchers',   path: '/inbox',     icon: '🧾' },
    { label: 'Reports',    path: '/ai',        icon: '📑' },
  ],
  finance_manager: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Reports',    path: '/ai',        icon: '📑' },
    { label: 'Settings',   path: '/settings',  icon: '⚙️' },
  ],
  hr_manager: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'HR Module',  path: '/hr',        icon: '👥' },
    { label: 'Reports',    path: '/ai',        icon: '📑' },
  ],
  support_agent: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Inbox',      path: '/inbox',     icon: '�' },
  ],
  support_manager: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Inbox',      path: '/inbox',     icon: '💬' },
    { label: 'Reports',    path: '/ai',        icon: '📑' },
    { label: 'Settings',   path: '/settings',  icon: '⚙️' },
  ],
  marketing: [
    { label: 'Dashboard',  path: '/dashboard', icon: '�' },
    { label: 'Leads',      path: '/leads',     icon: '👤' },
    { label: 'Workflows',  path: '/workflows', icon: '🔄' },
    { label: 'AI Center',  path: '/ai',        icon: '🤖' },
  ],
  project_manager: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'HR',         path: '/hr',        icon: '👥' },
    { label: 'Workflows',  path: '/workflows', icon: '🔄' },
  ],
  inventory_manager: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Reports',    path: '/ai',        icon: '📑' },
  ],
  it_admin: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'Settings',   path: '/settings',  icon: '⚙️' },
    { label: 'Workflows',  path: '/workflows', icon: '🔄' },
  ],
  hr_staff: [
    { label: 'Dashboard',  path: '/dashboard', icon: '📊' },
    { label: 'HR',         path: '/hr',        icon: '👥' },
  ],
};

const ROLE_BADGE = {
  owner:            { label: '👑 Owner',            color: '#05ff91', bg: 'rgba(5,255,145,0.12)' },
  admin:            { label: '🛡️ Admin',            color: '#3b82f6', bg: 'rgba(59,130,246,0.12)' },
  sales_rep:        { label: '👤 Sales Rep',        color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)' },
  accountant:       { label: '🧾 Accountant',       color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
  finance_manager:  { label: '💹 Finance Manager',  color: '#10b981', bg: 'rgba(16,185,129,0.12)' },
  hr_manager:       { label: '👥 HR Manager',       color: '#ec4899', bg: 'rgba(236,72,153,0.12)' },
  support_agent:    { label: '🎧 Support Agent',    color: '#06b6d4', bg: 'rgba(6,182,212,0.12)' },
  support_manager:  { label: '🎯 Support Manager',  color: '#0ea5e9', bg: 'rgba(14,165,233,0.12)' },
  marketing:        { label: '📣 Marketing',        color: '#f97316', bg: 'rgba(249,115,22,0.12)' },
  project_manager:  { label: '📁 Project Manager',  color: '#a78bfa', bg: 'rgba(167,139,250,0.12)' },
  inventory_manager:{ label: '📦 Inventory Mgr',   color: '#84cc16', bg: 'rgba(132,204,22,0.12)' },
  it_admin:         { label: '🖥 IT Admin',         color: '#64748b', bg: 'rgba(100,116,139,0.12)' },
  hr_staff:         { label: '🙋 HR Staff',         color: '#fb7185', bg: 'rgba(251,113,133,0.12)' },
};

// ── Inline pages ───────────────────────────────────────────────────────────
import OmnichannelInbox from './modules/Omnichannel/OmnichannelInbox';

const MessagingInbox = () => <OmnichannelInbox />;

const AICenter = () => (
  <div style={styles.page}>
    <h1 style={{ fontSize: '24px', fontWeight: '700', marginBottom: '8px' }}>AI Intelligence Center</h1>
    <p style={{ color: '#64748b' }}>Lead scoring, win probability, and revenue forecasting powered by FastAPI.</p>
    <div style={{ marginTop: '24px', display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '16px' }}>
      {[
        { icon: '🎯', title: 'Lead Scoring',     desc: 'Gradient boosted model on 40+ behavioral signals', score: '94%', label: 'Accuracy' },
        { icon: '🏆', title: 'Win Probability',  desc: 'Logistic regression on stage, value, age, history', score: '87%', label: 'Precision' },
        { icon: '📈', title: 'Revenue Forecast', desc: '30/60/90-day pipeline forecast with confidence bands', score: '±8%', label: 'Error Rate' },
      ].map(m => (
        <div key={m.title} style={{ background: '#fff', borderRadius: '12px', padding: '24px', border: '1px solid #e2e8f0' }}>
          <div style={{ fontSize: '32px', marginBottom: '12px' }}>{m.icon}</div>
          <div style={{ fontWeight: '700', fontSize: '16px', color: '#0f172a', marginBottom: '6px' }}>{m.title}</div>
          <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '16px' }}>{m.desc}</div>
          <div style={{ fontSize: '28px', fontWeight: '800', color: '#3b82f6' }}>{m.score}</div>
          <div style={{ fontSize: '12px', color: '#94a3b8' }}>{m.label}</div>
        </div>
      ))}
    </div>
  </div>
);

const AdminSettings = () => (
  <div style={styles.page}>
    <h1 style={{ fontSize: '24px', fontWeight: '700', marginBottom: '8px' }}>System Settings</h1>
    <p style={{ color: '#64748b' }}>Manage users, roles, RBAC permissions, and integrations.</p>
    <div style={{ marginTop: '24px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', maxWidth: '800px' }}>
      {[['👥','Users & Roles','Manage team members and RBAC permissions'],['🔗','Integrations','Connect Gmail, Outlook, Slack, Stripe'],['🔐','Security','JWT keys, 2FA, SSO/SAML configuration'],['🏢','Tenant Config','Company profile, branding, and modules']].map(([icon,title,desc]) => (
        <div key={title} style={{ background: '#fff', borderRadius: '12px', padding: '20px', border: '1px solid #e2e8f0', cursor: 'pointer' }}>
          <div style={{ fontSize: '24px', marginBottom: '8px' }}>{icon}</div>
          <div style={{ fontWeight: '700', color: '#0f172a', marginBottom: '4px' }}>{title}</div>
          <div style={{ fontSize: '13px', color: '#64748b' }}>{desc}</div>
        </div>
      ))}
    </div>
  </div>
);

// ── Auth guard ─────────────────────────────────────────────────────────────
function ProtectedRoute({ children }) {
  const { user, loading } = useAuth();
  if (loading) return null;
  if (!user) return <Navigate to="/login" />;
  return children;
}

// ── Owner Layout (completely isolated — no shared sidebar) ─────────────────
function OwnerLayout({ children }) {
  const { logout, user } = useAuth();
  const location = useLocation();

  const ownerMenu = [
    { label: 'Overview',   path: '/dashboard', icon: '📊' },
    { label: 'Leads',      path: '/leads',     icon: '👤' },
    { label: 'Pipeline',   path: '/pipeline',  icon: '🏗️' },
    { label: 'Sales Ops',  path: '/salesops',  icon: '⚡' },
    { label: 'Inbox',      path: '/inbox',     icon: '💬' },
    { label: 'Workflows',  path: '/workflows', icon: '🔄' },
    { label: 'HR',         path: '/hr',        icon: '👥' },
    { label: 'Partners',   path: '/partners',  icon: '🤝' },
    { label: 'AI Center',  path: '/ai',        icon: '🤖' },
    { label: 'Settings',   path: '/settings',  icon: '⚙️' },
  ];

  return (
    <div style={{ display: 'flex', height: '100vh', background: '#050d1a', color: '#f1f5f9', fontFamily: 'Inter, system-ui, sans-serif' }}>
      {/* Owner sidebar — dark gold accent */}
      <aside style={{ width: '230px', background: '#060e1f', borderRight: '1px solid #0d1f3c', display: 'flex', flexDirection: 'column', flexShrink: 0 }}>
        <div style={{ padding: '20px 24px', borderBottom: '1px solid #0d1f3c' }}>
          <div style={{ fontSize: '18px', fontWeight: '900', letterSpacing: '-0.5px' }}>
            <span style={{ color: '#05ff91' }}>Nex</span>SaaS
          </div>
          <div style={{ fontSize: '11px', color: '#334155', marginTop: '2px', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.08em' }}>Owner Console</div>
        </div>
        <nav style={{ flex: 1, padding: '12px 8px', overflowY: 'auto' }}>
          {ownerMenu.map(item => (
            <Link key={item.path} to={item.path}
              style={{ display: 'flex', alignItems: 'center', padding: '10px 14px', borderRadius: '8px', color: location.pathname === item.path ? '#05ff91' : '#475569', textDecoration: 'none', marginBottom: '2px', background: location.pathname === item.path ? 'rgba(5,255,145,0.08)' : 'transparent', fontWeight: location.pathname === item.path ? '700' : '500', fontSize: '13px' }}>
              <span style={{ marginRight: '10px', fontSize: '16px', width: '20px', textAlign: 'center' }}>{item.icon}</span>
              {item.label}
            </Link>
          ))}
        </nav>
        <div style={{ padding: '14px', borderTop: '1px solid #0d1f3c' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '12px' }}>
            <div style={{ width: '34px', height: '34px', borderRadius: '10px', background: 'linear-gradient(135deg, #05ff91, #3b82f6)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '900', fontSize: '15px', color: '#050d1a', flexShrink: 0 }}>{user?.name?.[0]}</div>
            <div>
              <div style={{ fontSize: '13px', fontWeight: '700', color: '#f1f5f9' }}>{user?.name}</div>
              <div style={{ fontSize: '11px', marginTop: '2px', background: 'rgba(5,255,145,0.12)', color: '#05ff91', padding: '2px 8px', borderRadius: '100px', display: 'inline-block', fontWeight: '700' }}>👑 Owner</div>
            </div>
          </div>
          <button onClick={logout} style={{ width: '100%', padding: '7px', borderRadius: '6px', background: 'transparent', border: '1px solid #0d1f3c', color: '#475569', cursor: 'pointer', fontSize: '12px' }}>Logout</button>
        </div>
      </aside>
      <main style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        <header style={{ height: '52px', background: '#060e1f', borderBottom: '1px solid #0d1f3c', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 28px', flexShrink: 0 }}>
          <div style={{ color: '#334155', fontSize: '13px', fontWeight: '600' }}>
            {location.pathname.replace('/', '').replace(/^\w/, c => c.toUpperCase()) || 'Overview'}
          </div>
          <div style={{ display: 'flex', gap: '8px' }}>
            <span style={{ background: 'rgba(5,255,145,0.08)', color: '#05ff91', padding: '4px 10px', borderRadius: '100px', fontSize: '11px', fontWeight: '700' }}>🏢 {user?.company_code}</span>
            <span style={{ background: '#0d1f3c', color: '#334155', padding: '4px 10px', borderRadius: '100px', fontSize: '11px', fontWeight: '600' }}>Tenant: {user?.tenant_id}</span>
          </div>
        </header>
        <div style={{ flex: 1, overflowY: 'auto' }}>{children}</div>
      </main>
    </div>
  );
}

// ── Standard Layout (all non-owner roles) ──────────────────────────────────
function Layout({ children }) {
  const { logout, user } = useAuth();
  const location = useLocation();

  const menuItems = ROLE_MENUS[user?.role] || ROLE_MENUS['sales_rep'];
  const badge = ROLE_BADGE[user?.role] || { label: user?.role, color: '#94a3b8', bg: 'rgba(255,255,255,0.06)' };

  return (
    <div style={styles.app}>
      <aside style={styles.sidebar}>
        <div style={styles.logo}><span style={{ color: '#3b82f6' }}>Nex</span>SaaS CRM</div>
        <nav style={styles.nav}>
          {menuItems.map(item => (
            <Link key={item.path} to={item.path}
              style={{ ...styles.navLink, ...(location.pathname === item.path ? styles.navLinkActive : {}) }}>
              <span style={styles.navIcon}>{item.icon}</span>
              <span style={styles.navLabel}>{item.label}</span>
            </Link>
          ))}
        </nav>
        <div style={styles.footer}>
          <div style={styles.userInfo}>
            <div style={styles.avatar}>{user?.name?.[0]}</div>
            <div>
              <div style={styles.userName}>{user?.name}</div>
              <div style={{ fontSize: '11px', marginTop: '3px', background: badge.bg, color: badge.color, padding: '2px 8px', borderRadius: '100px', display: 'inline-block', fontWeight: '700' }}>{badge.label}</div>
            </div>
          </div>
          <button onClick={logout} style={styles.logoutBtn}>Logout</button>
        </div>
      </aside>
      <main style={styles.main}>
        <header style={styles.header}>
          <div style={styles.breadcrumb}>{location.pathname.replace('/', '').replace(/^\w/, c => c.toUpperCase()) || 'Dashboard'}</div>
          <div style={styles.headerActions}>
            <span style={styles.tenantBadge}>🏢 {user?.company_code}</span>
            <span style={{ ...styles.tenantBadge, marginLeft: '8px' }}>Tenant: {user?.tenant_id}</span>
          </div>
        </header>
        <div style={styles.content}>{children}</div>
      </main>
    </div>
  );
}

import LandingPage from './modules/Marketing/LandingPage';
import OnboardingWizard from './modules/Onboarding/OnboardingWizard';

// ── Smart layout picker ────────────────────────────────────────────────────
function AppLayout({ children }) {
  const { user } = useAuth();
  if (user?.role === 'owner') return <OwnerLayout>{children}</OwnerLayout>;
  return <Layout>{children}</Layout>;
}

import { I18nProvider } from './i18n';

// ── Shared Page Wrapper ────────────────────────────────────────────────────
const PageContainer = ({ children }) => (
  <div style={{ padding: '24px', background: '#0f172a', minHeight: '100%' }}>
    {children}
  </div>
);

// ── Routes ─────────────────────────────────────────────────────────────────
export default function App() {
  return (
    <I18nProvider>
      <AuthProvider>
        <Router>
          <CommandPalette />
        <Routes>
          <Route path="/" element={<LandingPage />} />
          <Route path="/onboarding" element={<OnboardingWizard />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/dashboard" element={<ProtectedRoute><AppLayout><RoleDashboard /></AppLayout></ProtectedRoute>} />
          <Route path="/leads"     element={<ProtectedRoute><AppLayout><LeadsPage /></AppLayout></ProtectedRoute>} />
          <Route path="/pipeline"  element={<ProtectedRoute><AppLayout><div style={{ padding: '24px', background: '#0f172a', minHeight: '100%' }}><SalesPipeline /></div></AppLayout></ProtectedRoute>} />
          <Route path="/salesops"  element={<ProtectedRoute><AppLayout><div style={{ padding: '24px', background: '#0f172a', minHeight: '100%' }}><SalesOps /></div></AppLayout></ProtectedRoute>} />
          <Route path="/inbox"     element={<ProtectedRoute><AppLayout><MessagingInbox /></AppLayout></ProtectedRoute>} />
          <Route path="/workflows" element={<ProtectedRoute><AppLayout><WorkflowsModule /></AppLayout></ProtectedRoute>} />
          <Route path="/hr"        element={<ProtectedRoute><AppLayout><div style={{ padding: '24px', background: '#0f172a', minHeight: '100%' }}><HRModule /></div></AppLayout></ProtectedRoute>} />
          <Route path="/partners"  element={<ProtectedRoute><AppLayout><PartnersModule /></AppLayout></ProtectedRoute>} />
          <Route path="/ai"        element={<ProtectedRoute><AppLayout><AICenter /></AppLayout></ProtectedRoute>} />
          <Route path="/settings"  element={<ProtectedRoute><AppLayout><AdminSettings /></AppLayout></ProtectedRoute>} />
        </Routes>
      </Router>
    </AuthProvider>
    </I18nProvider>
  );
}

const styles = {
  app:           { display: 'flex', height: '100vh', background: '#f8fafc', color: '#1e293b', fontFamily: 'Inter, system-ui, sans-serif' },
  sidebar:       { width: '220px', background: '#0f172a', color: '#fff', display: 'flex', flexDirection: 'column', flexShrink: 0 },
  logo:          { padding: '20px 24px', fontSize: '18px', fontWeight: '800', letterSpacing: '-0.5px', borderBottom: '1px solid #1e293b' },
  nav:           { flex: 1, padding: '12px 8px', overflowY: 'auto' },
  navLink:       { display: 'flex', alignItems: 'center', padding: '10px 14px', borderRadius: '8px', color: '#94a3b8', textDecoration: 'none', marginBottom: '2px', transition: 'all 0.15s' },
  navLinkActive: { background: '#1e293b', color: '#fff' },
  navIcon:       { marginRight: '10px', fontSize: '16px', width: '20px', textAlign: 'center' },
  navLabel:      { fontSize: '13px', fontWeight: '500' },
  footer:        { padding: '14px', borderTop: '1px solid #1e293b' },
  userInfo:      { display: 'flex', alignItems: 'center', marginBottom: '12px', gap: '10px' },
  avatar:        { width: '32px', height: '32px', borderRadius: '8px', background: '#3b82f6', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '14px', flexShrink: 0 },
  userName:      { fontSize: '13px', fontWeight: '600' },
  logoutBtn:     { width: '100%', padding: '7px', borderRadius: '6px', background: 'transparent', border: '1px solid #1e293b', color: '#94a3b8', cursor: 'pointer', fontSize: '12px' },
  main:          { flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' },
  header:        { height: '56px', background: '#fff', borderBottom: '1px solid #e2e8f0', display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 28px', flexShrink: 0 },
  breadcrumb:    { color: '#64748b', fontSize: '14px', fontWeight: '500' },
  headerActions: { display: 'flex', alignItems: 'center' },
  tenantBadge:   { background: '#f1f5f9', color: '#475569', padding: '4px 10px', borderRadius: '100px', fontSize: '11px', fontWeight: '600' },
  content:       { flex: 1, overflowY: 'auto' },
  page:          { padding: '32px' },
};
