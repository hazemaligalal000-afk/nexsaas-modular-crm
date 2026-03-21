import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

/**
 * Ctrl+K Command Palette
 * Requirement: 6.145 - Advanced Design System
 * Feature: Instant navigation, search, and AI actions.
 */
export default function CommandPalette() {
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const navigate = useNavigate();

    useEffect(() => {
        const handleKeyDown = (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                setIsOpen(prev => !prev);
            }
            if (e.key === 'Escape') setIsOpen(false);
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    if (!isOpen) return null;

    const COMMANDS = [
        { id: 'leads',   label: 'Go to Leads', icon: '👥', action: () => navigate('/api/leads') },
        { id: 'inbox',   label: 'Open Omnichannel Inbox', icon: '📥', action: () => navigate('/api/inbox') },
        { id: 'ai-gen',  label: 'Generate AI Marketing Copy', icon: '🤖', action: () => alert('AI Engine: Drafting Copy...') },
        { id: 'billing', label: 'Manage Billing/Stripe', icon: '💳', action: () => navigate('/api/settings?tab=billing') },
        { id: 'support', label: 'Ticket Status / Help', icon: '❓', action: () => alert('Opening Knowledge Base...') },
    ];

    const filtered = COMMANDS.filter(c => c.label.toLowerCase().includes(query.toLowerCase()));

    return (
        <div style={styles.overlay}>
            <div style={styles.modal}>
                <div style={styles.searchWrapper}>
                    <span style={styles.searchIcon}>🔍</span>
                    <input 
                        autoFocus
                        style={styles.input}
                        placeholder="Type a command or search..."
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <span style={styles.shortcutHint}>ESC to close</span>
                </div>
                <div style={styles.results}>
                    {filtered.map(cmd => (
                        <div 
                            key={cmd.id} 
                            style={styles.resultItem} 
                            onClick={() => { cmd.action(); setIsOpen(false); }}
                        >
                            <span style={styles.resultIcon}>{cmd.icon}</span>
                            <span style={styles.resultLabel}>{cmd.label}</span>
                            <span style={styles.jumpHint}>↵ Jump</span>
                        </div>
                    ))}
                    {filtered.length === 0 && (
                        <div style={styles.noResults}>No commands found for "{query}"</div>
                    )}
                </div>
            </div>
        </div>
    );
}

const styles = {
    overlay: {
        position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
        background: 'rgba(6, 13, 30, 0.8)', backdropFilter: 'blur(8px)',
        zIndex: 9999, display: 'flex', justifyContent: 'center', paddingTop: '10vh'
    },
    modal: {
        width: '600px', background: '#0d1a30', borderRadius: '16px',
        border: '1px solid #1e3a5f', boxShadow: '0 20px 50px rgba(0,0,0,0.5)',
        overflow: 'hidden', display: 'flex', flexDirection: 'column'
    },
    searchWrapper: {
        display: 'flex', alignItems: 'center', padding: '16px 20px',
        borderBottom: '1px solid #1e3a5f', background: '#0b1628'
    },
    searchIcon: { fontSize: '18px', marginRight: '12px', opacity: 0.5 },
    input: {
        flex: 1, background: 'none', border: 'none', color: '#fff',
        fontSize: '18px', outline: 'none', fontFamily: 'Inter, sans-serif'
    },
    shortcutHint: { fontSize: '10px', color: '#475569', textTransform: 'uppercase', fontWeight: '800' },
    results: { padding: '8px', maxHeight: '400px', overflowY: 'auto' },
    resultItem: {
        display: 'flex', alignItems: 'center', padding: '12px 16px',
        borderRadius: '8px', cursor: 'pointer', transition: 'background 0.2s',
        color: '#94a3b8'
    },
    resultIcon: { marginRight: '12px', fontSize: '18px' },
    resultLabel: { flex: 1, color: '#e2e8f0', fontWeight: '500' },
    jumpHint: { fontSize: '10px', color: '#334155', fontWeight: '700' },
    noResults: { padding: '40px', textAlign: 'center', color: '#475569' }
};
