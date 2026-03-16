import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import '../pages/LandingPage/LandingPage.css';

const Login = () => {
    const [credentials, setCredentials] = useState({ username: '', password: '' });
    const [error, setError] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const navigate = useNavigate();

    const handleChange = (e) => {
        setCredentials({ ...credentials, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setIsLoading(true);

        try {
            // Pointing to our PHP REST API
            const response = await fetch('http://localhost:8080/api/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(credentials)
            });

            const result = await response.json();

            if (result.status === 'success') {
                // Store the API Key and Organization ID for persistent context
                localStorage.setItem('nexa_api_key', result.data.api_key);
                localStorage.setItem('nexa_org_id', result.data.organization_id);
                localStorage.setItem('nexa_user_id', result.data.user_id);
                
                // Redirect to the intelligent dashboard
                navigate('/dashboard');
            } else {
                setError(result.message || 'Authentication failed');
            }
        } catch (err) {
            setError('Could not connect to the Nexa Core. Please ensure the backend is running.');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="landing-page" style={{ height: '100vh', display: 'flex', justifyContent: 'center', alignItems: 'center', background: 'var(--nexa-bg)' }}>
            <div className="nexa-card reveal" style={{ width: '100%', maxWidth: '450px', padding: '60px' }}>
                <div style={{ textAlign: 'center', marginBottom: '40px' }}>
                    <div className="logo" style={{ fontSize: '32px', fontWeight: '900', letterSpacing: '-2px', marginBottom: '12px' }}>
                        NEXA <span style={{ color: 'var(--nexa-primary)' }}>CRM</span>
                    </div>
                    <p>Enter your credentials to access Nexa Intelligence™</p>
                </div>

                {error && <div style={{ background: 'rgba(239, 68, 68, 0.1)', border: '1px solid var(--nexa-danger)', color: 'var(--nexa-danger)', padding: '12px', borderRadius: '8px', marginBottom: '24px', fontSize: '14px', textAlign: 'center' }}>{error}</div>}

                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: '20px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', fontSize: '14px', fontWeight: '600' }}>Username</label>
                        <input 
                            type="text" 
                            name="username"
                            className="nexa-input" 
                            placeholder="e.g. admin" 
                            value={credentials.username}
                            onChange={handleChange}
                            required
                        />
                    </div>
                    <div style={{ marginBottom: '32px' }}>
                        <label style={{ display: 'block', marginBottom: '8px', fontSize: '14px', fontWeight: '600' }}>Password</label>
                        <input 
                            type="password" 
                            name="password"
                            className="nexa-input" 
                            placeholder="••••••••" 
                            value={credentials.password}
                            onChange={handleChange}
                            required
                        />
                    </div>
                    <button type="submit" className="btn btn-primary pulse-button" style={{ width: '100%', justifyContent: 'center' }} disabled={isLoading}>
                        {isLoading ? 'Decrypting Access...' : 'Login to Dashboard'}
                    </button>
                    <div style={{ textAlign: 'center', marginTop: '24px', fontSize: '14px', color: 'var(--nexa-muted)' }}>
                        Don't have an account? <a href="/signup" style={{ color: 'var(--nexa-primary)', textDecoration: 'none', fontWeight: '800' }}>Start Free Trial</a>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default Login;
