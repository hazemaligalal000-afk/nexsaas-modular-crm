import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';
import { useNavigate } from 'react-router-dom';

const DEMO_ACCOUNTS = [
  { email: 'owner@acme.com',      label: 'Owner',            icon: '👑', color: '#05ff91', desc: 'Full access · Revenue · Billing · Team' },
  { email: 'admin@acme.com',      label: 'Admin',            icon: '🛡️', color: '#3b82f6', desc: 'CRM management · Analytics · Settings' },
  { email: 'rep@acme.com',        label: 'Sales Rep',        icon: '👤', color: '#8b5cf6', desc: 'My leads · My deals · My tasks' },
  { email: 'accountant@acme.com', label: 'Accountant',       icon: '🧾', color: '#f59e0b', desc: 'P&L · Vouchers · AR/AP · Reconciliation' },
  { email: 'finance@acme.com',    label: 'Finance Manager',  icon: '💹', color: '#10b981', desc: 'Consolidation · Cash flow · FX · Budget' },
  { email: 'hr@acme.com',         label: 'HR Manager',       icon: '👥', color: '#ec4899', desc: 'Employees · Leave · Payroll · Org chart' },
  { email: 'support@acme.com',    label: 'Support Agent',    icon: '🎧', color: '#06b6d4', desc: 'Ticket queue · CSAT · SLA tracking' },
  { email: 'support.mgr@acme.com',label: 'Support Manager',  icon: '🎯', color: '#0ea5e9', desc: 'Agent performance · Channel stats · CSAT' },
  { email: 'marketing@acme.com',  label: 'Marketing',        icon: '📣', color: '#f97316', desc: 'Campaigns · Funnel · Content · ROI' },
  { email: 'pm@acme.com',         label: 'Project Manager',  icon: '📁', color: '#a78bfa', desc: 'Projects · Milestones · Time tracking' },
  { email: 'inventory@acme.com',  label: 'Inventory Manager',icon: '📦', color: '#84cc16', desc: 'Stock levels · Reorder alerts · Movements' },
  { email: 'it@acme.com',         label: 'IT Admin',         icon: '🖥', color: '#64748b', desc: 'System health · Security logs · Integrations' },
  { email: 'hrstaff@acme.com',    label: 'HR Staff',         icon: '🙋', color: '#fb7185', desc: 'Attendance · Leave balance · Payslips' },
];

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('owner@acme.com');
  const [password, setPassword] = useState('demo');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const res = await login(email, password);
      if (res.success) navigate('/dashboard');
      else setError(res.error || 'Login failed');
    } catch {
      setError('Connection error — is the mock API running?');
    } finally {
      setLoading(false);
    }
  };

  const pickAccount = (acc) => { setEmail(acc.email); setPassword('demo'); setError(''); };

  return (
    <div style={s.page}>
      <div style={s.card}>
        {/* Logo */}
        <div style={s.logoRow}>
          <div style={s.logoIcon}>N</div>
          <div>
            <div style={s.logoText}><span style={{ color: '#3b82f6' }}>Nex</span>SaaS CRM</div>
            <div style={s.logoSub}>Modular Enterprise Platform · 13 Roles</div>
          </div>
        </div>

        {/* Demo account picker — scrollable */}
        <div style={s.pickerLabel}>Select a demo account</div>
        <div style={s.picker}>
          {DEMO_ACCOUNTS.map(acc => (
            <button key={acc.email} onClick={() => pickAccount(acc)}
              style={{ ...s.pickerBtn, borderColor: email === acc.email ? acc.color : '#1e293b', background: email === acc.email ? `${acc.color}10` : 'transparent' }}>
              <span style={{ fontSize: '18px', flexShrink: 0 }}>{acc.icon}</span>
              <div style={{ textAlign: 'left', minWidth: 0 }}>
                <div style={{ fontWeight: '700', fontSize: '12px', color: email === acc.email ? acc.color : '#94a3b8' }}>{acc.label}</div>
                <div style={{ fontSize: '10px', color: '#475569', marginTop: '1px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{acc.desc}</div>
              </div>
            </button>
          ))}
        </div>

        <div style={s.divider}><span style={s.dividerText}>or sign in manually</span></div>

        {error && <div style={s.error}>{error}</div>}

        <form onSubmit={handleSubmit} style={s.form}>
          <div style={s.field}>
            <label style={s.label}>Email</label>
            <input type="email" value={email} onChange={e => setEmail(e.target.value)} style={s.input} required />
          </div>
          <div style={s.field}>
            <label style={s.label}>Password</label>
            <input type="password" value={password} onChange={e => setPassword(e.target.value)} style={s.input} placeholder="any password works" required />
          </div>
          <button type="submit" disabled={loading} style={s.btn}>
            {loading ? 'Signing in...' : 'Sign In →'}
          </button>
        </form>

        <div style={s.footer}>Protected by JWT RS256 · Multi-tenant RBAC · 13 Roles</div>
      </div>
    </div>
  );
}

const s = {
  page:        { minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0a0f1e', padding: '24px' },
  card:        { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '20px', padding: '36px', width: '100%', maxWidth: '480px' },
  logoRow:     { display: 'flex', alignItems: 'center', gap: '14px', marginBottom: '28px' },
  logoIcon:    { width: '44px', height: '44px', borderRadius: '12px', background: 'linear-gradient(135deg, #3b82f6, #8b5cf6)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '900', fontSize: '20px', color: '#fff', flexShrink: 0 },
  logoText:    { fontSize: '20px', fontWeight: '800', color: '#f1f5f9' },
  logoSub:     { fontSize: '11px', color: '#475569', marginTop: '2px' },
  pickerLabel: { fontSize: '11px', fontWeight: '700', color: '#475569', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: '10px' },
  picker:      { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px', marginBottom: '20px', maxHeight: '320px', overflowY: 'auto', paddingRight: '4px' },
  pickerBtn:   { display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 12px', borderRadius: '10px', border: '1px solid', cursor: 'pointer', transition: 'all 0.15s', textAlign: 'left' },
  divider:     { position: 'relative', textAlign: 'center', margin: '16px 0', borderTop: '1px solid #1e293b' },
  dividerText: { position: 'relative', top: '-10px', background: '#0f172a', padding: '0 12px', fontSize: '12px', color: '#334155' },
  error:       { background: 'rgba(239,68,68,0.1)', color: '#ef4444', padding: '10px 14px', borderRadius: '8px', fontSize: '13px', marginBottom: '16px', border: '1px solid rgba(239,68,68,0.2)' },
  form:        { display: 'flex', flexDirection: 'column', gap: '14px' },
  field:       { display: 'flex', flexDirection: 'column', gap: '6px' },
  label:       { fontSize: '12px', fontWeight: '600', color: '#64748b' },
  input:       { padding: '11px 14px', borderRadius: '10px', border: '1px solid #1e293b', background: '#0a0f1e', color: '#f1f5f9', fontSize: '14px' },
  btn:         { background: 'linear-gradient(135deg, #3b82f6, #8b5cf6)', color: '#fff', border: 'none', padding: '13px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '15px', marginTop: '4px' },
  footer:      { marginTop: '20px', textAlign: 'center', color: '#334155', fontSize: '11px' },
};
