import React from 'react';
import { NavLink } from 'react-router-dom';
import { useActiveModules } from '../core/ModuleLoader';

export default function Sidebar() {
    const activeModules = useActiveModules();

    // Group modules by section
    const sections = {};
    activeModules.forEach(m => {
        const sec = m.section || 'Other';
        if (!sections[sec]) sections[sec] = [];
        sections[sec].push(m);
    });

    const linkStyle = ({ isActive }) => ({
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        padding: '11px 24px 11px 28px',
        color: isActive ? '#05ff91' : '#94a3b8',
        textDecoration: 'none',
        fontSize: '13px',
        fontWeight: '600',
        borderLeft: isActive ? '3px solid #05ff91' : '3px solid transparent',
        background: isActive ? 'rgba(5, 255, 145, 0.05)' : 'transparent',
        transition: 'all 0.2s ease'
    });

    const logout = () => {
        localStorage.clear();
        window.location.href = '/login';
    };

    return (
        <aside style={{
            width: '260px',
            minWidth: '260px',
            background: '#0a0a0a',
            color: '#fff',
            height: '100vh',
            borderRight: '1px solid #1e293b',
            display: 'flex',
            flexDirection: 'column',
            overflowY: 'auto'
        }}>
            <div style={{ padding: '24px 28px', fontSize: '22px', fontWeight: '900', letterSpacing: '-1.5px', borderBottom: '1px solid #1e293b' }}>
                NEXA <span style={{ color: '#05ff91' }}>CRM</span>
                <span style={{ fontSize: '9px', color: '#64748b', display: 'block', fontWeight: '600', letterSpacing: '0.1em', marginTop: '2px' }}>CRM + ERP UNIFIED PLATFORM</span>
            </div>
            
            <nav style={{ flex: 1, paddingBottom: '20px' }}>
                <NavLink to="/dashboard" end style={linkStyle}>
                    <span>🏠</span> Overview
                </NavLink>

                {Object.entries(sections).map(([section, modules]) => (
                    <div key={section}>
                        <div style={{ padding: '16px 28px 6px', fontSize: '10px', fontWeight: '800', color: '#475569', textTransform: 'uppercase', letterSpacing: '0.15em' }}>
                            ── {section} ──
                        </div>
                        {modules.map(module => (
                            <NavLink key={module.id} to={`/dashboard${module.path}`} style={linkStyle}>
                                <span style={{ fontSize: '14px' }}>{module.icon || '📁'}</span> {module.label || module.id}
                            </NavLink>
                        ))}
                    </div>
                ))}
            </nav>

            <div style={{ padding: '16px 28px', borderTop: '1px solid #1e293b' }}>
                <div style={{ fontSize: '10px', color: '#334155', marginBottom: '12px', fontWeight: '700' }}>
                    Powered by ERPNext v15 + Vtiger
                </div>
                <button onClick={logout} style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontWeight: '700', fontSize: '13px' }}>
                    🚪 Logout
                </button>
            </div>
        </aside>
    );
}
