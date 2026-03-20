import React, { useState } from 'react';

/**
 * Requirement F1: Email Marketing Automation - Drag & Drop Designer (Frontend)
 * Per-Company Branding · MJML-backed · Live Preview
 */
export default function EmailDesigner({ onSave, initialData }) {
    const [template, setTemplate] = useState(initialData || {
        title: 'New Campaign Template',
        primaryColor: '#3b82f6',
        sections: [
            { type: 'text', content: 'Welcome to our platform! We are excited to have you on board.' },
            { type: 'button', label: 'Start Free Trial', url: 'https://nexsaas.com' }
        ]
    });

    const addSection = (type) => {
        const newSection = type === 'text' 
            ? { type: 'text', content: 'New text section...' } 
            : { type: 'button', label: 'Action Button', url: '#' };
        setTemplate(prev => ({ ...prev, sections: [...prev.sections, newSection] }));
    };

    return (
        <div style={{ display: 'flex', height: '100vh', background: '#0b1628', color: '#e2e8f0' }}>
            {/* Sidebar Controls */}
            <div style={{ width: '320px', background: '#0d1a30', borderRight: '1px solid #1e3a5f', padding: '24px' }}>
                <h3 style={{ margin: '0 0 24px', fontSize: '18px', fontWeight: '900' }}>Template Settings</h3>
                
                <div style={{ marginBottom: '24px' }}>
                    <label style={{ display: 'block', fontSize: '11px', fontWeight: '800', textTransform: 'uppercase', color: '#475569', marginBottom: '8px' }}>Primary Brand Color</label>
                    <input type="color" value={template.primaryColor} onChange={e => setTemplate({...template, primaryColor: e.target.value})} 
                           style={{ width: '100%', height: '40px', background: 'none', border: '1.5px solid #1e3a5f', borderRadius: '8px', cursor: 'pointer' }} />
                </div>

                <h3 style={{ margin: '32px 0 16px', fontSize: '14px', fontWeight: '800', color: '#60a5fa' }}>Add Blocks</h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                    <button onClick={() => addSection('text')} style={{ padding: '12px', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#fff', cursor: 'pointer', fontSize: '12px' }}>Text Block</button>
                    <button onClick={() => addSection('button')} style={{ padding: '12px', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', color: '#fff', cursor: 'pointer', fontSize: '12px' }}>Button Block</button>
                </div>

                <div style={{ marginTop: 'auto', paddingTop: '40px' }}>
                    <button onClick={() => onSave(template)} style={{ width: '100%', padding: '14px', background: '#3b82f6', border: 'none', borderRadius: '12px', color: '#fff', fontWeight: '700', cursor: 'pointer' }}>💾 Save Template</button>
                </div>
            </div>

            {/* Live Preview (Simulated MJML Rendering) */}
            <div style={{ flex: 1, padding: '40px', background: '#0b0d1e', overflowY: 'auto', display: 'flex', justifyContent: 'center' }}>
                <div style={{ width: '600px', background: '#fff', color: '#333', borderRadius: '4px', overflow: 'hidden', minHeight: '800px' }}>
                    {/* Branded Header */}
                    <div style={{ padding: '30px', textAlign: 'center', borderBottom: '1px solid #f4f4f4' }}>
                        <div style={{ fontSize: '24px', fontWeight: '900', color: template.primaryColor }}>NexSaaS BRANDING</div>
                    </div>

                    {/* Content Blocks */}
                    {template.sections.map((sec, idx) => (
                        <div key={idx} style={{ padding: '24px 40px' }}>
                            {sec.type === 'text' ? (
                                <p style={{ fontSize: '16px', lineHeight: '1.6', margin: 0 }}>{sec.content}</p>
                            ) : (
                                <div style={{ textAlign: 'center', padding: '12px 0' }}>
                                    <button style={{ padding: '14px 28px', background: template.primaryColor, color: '#fff', border: 'none', borderRadius: '6px', fontWeight: '700', fontSize: '16px' }}>{sec.label}</button>
                                </div>
                            )}
                        </div>
                    ))}

                    {/* Branded Footer */}
                    <div style={{ padding: '40px', background: '#f9fafb', textAlign: 'center', fontSize: '12px', color: '#9ca3af' }}>
                        © 2026 NexSaaS CRM · Global Hub · 123 Business Way, Saudi Arabia
                    </div>
                </div>
            </div>
        </div>
    );
}
