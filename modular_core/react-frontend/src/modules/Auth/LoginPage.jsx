import React, { useState } from 'react';
import { useAuth } from '../../core/AuthContext';
import { useNavigate } from 'react-router-dom';

export default function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const [email, setEmail] = useState('hazem@acme.com');
    const [password, setPassword] = useState('SecureP@ss2026');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError('');
        try {
            const res = await login(email, password);
            if (res.success) {
                navigate('/dashboard');
            } else {
                setError(res.error || 'Login failed');
            }
        } catch (err) {
            setError('Connection error');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={styles.container}>
            <div style={styles.card}>
                <div style={styles.header}>
                    <h1 style={styles.logo}>AI RevOS</h1>
                    <p style={styles.subtitle}>Sign in to your enterprise account</p>
                </div>

                {error && <div style={styles.error}>{error}</div>}

                <form onSubmit={handleSubmit} style={styles.form}>
                    <div style={styles.field}>
                        <label style={styles.label}>Email Address</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            style={styles.input}
                            placeholder="name@company.com"
                            required
                        />
                    </div>
                    <div style={styles.field}>
                        <label style={styles.label}>Password</label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            style={styles.input}
                            placeholder="••••••••"
                            required
                        />
                    </div>
                    <button type="submit" disabled={loading} style={styles.button}>
                        {loading ? 'Authenticating...' : 'Sign In'}
                    </button>
                </form>

                <div style={styles.footer}>
                    <p>Protected by Enterprise-grade RBAC & Multi-tenant Isolation</p>
                </div>
            </div>
        </div>
    );
}

const styles = {
    container: { height: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0f172a' },
    card: { background: '#fff', padding: '48px', borderRadius: '16px', width: '100%', maxWidth: '440px', boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)' },
    header: { textAlign: 'center', marginBottom: '32px' },
    logo: { fontSize: '28px', fontWeight: '800', color: '#0f172a', marginBottom: '8px' },
    subtitle: { color: '#64748b', fontSize: '14px' },
    error: { background: '#fef2f2', color: '#ef4444', padding: '12px', borderRadius: '8px', fontSize: '14px', marginBottom: '20px', textAlign: 'center', border: '1px solid #fee2e2' },
    form: { display: 'flex', flexDirection: 'column', gap: '20px' },
    field: { display: 'flex', flexDirection: 'column', gap: '8px' },
    label: { fontSize: '14px', fontWeight: '600', color: '#1e293b' },
    input: { padding: '12px 16px', borderRadius: '8px', border: '1px solid #e2e8f0', fontSize: '15px', transition: 'border 0.2s' },
    button: { background: '#3b82f6', color: '#fff', border: 'none', padding: '14px', borderRadius: '8px', fontWeight: '700', cursor: 'pointer', fontSize: '16px', marginTop: '8px' },
    footer: { marginTop: '32px', textAlign: 'center', color: '#94a3b8', fontSize: '12px' }
};
