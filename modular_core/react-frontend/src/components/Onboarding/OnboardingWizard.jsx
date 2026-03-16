import React, { useState } from 'react';

export default function OnboardingWizard({ onComplete }) {
    const [step, setStep] = useState(1);
    const [data, setData] = useState({ companyName: '', industry: '', teamSize: '' });

    const nextStep = () => {
        if (step < 3) setStep(step + 1);
        else onComplete(data);
    };

    return (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999 }}>
            <div style={{ background: '#fff', padding: '40px', borderRadius: '12px', width: '500px', boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1)' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '32px' }}>
                    {[1, 2, 3].map(s => (
                        <div key={s} style={{ 
                            width: '30px', height: '30px', borderRadius: '50%', 
                            background: step >= s ? '#3b82f6' : '#e2e8f0', 
                            color: step >= s ? '#fff' : '#64748b',
                            display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold'
                        }}>
                            {s}
                        </div>
                    ))}
                </div>

                {step === 1 && (
                    <div>
                        <h2 style={{ marginTop: 0 }}>Welcome to NexaCRM ✨</h2>
                        <p style={{ color: '#475569' }}>Let's get your workspace set up in under two minutes.</p>
                        <input 
                            type="text" 
                            placeholder="Company Name" 
                            value={data.companyName}
                            onChange={(e) => setData({ ...data, companyName: e.target.value })}
                            style={{ width: '100%', padding: '12px', border: '1px solid #cbd5e1', borderRadius: '8px', marginBottom: '16px', boxSizing: 'border-box' }}
                        />
                        <select 
                            value={data.industry}
                            onChange={(e) => setData({ ...data, industry: e.target.value })}
                            style={{ width: '100%', padding: '12px', border: '1px solid #cbd5e1', borderRadius: '8px', boxSizing: 'border-box' }}
                        >
                            <option value="">Select Industry...</option>
                            <option value="SaaS">SaaS / Software</option>
                            <option value="Agency">Agency / Services</option>
                            <option value="RealEstate">Real Estate</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <h2 style={{ marginTop: 0 }}>Supercharge with AI 🧠</h2>
                        <p style={{ color: '#475569' }}>NexaCRM uses AI to predict deals. Connect your data to begin training your model.</p>
                        <div style={{ border: '1px solid #cbd5e1', padding: '20px', borderRadius: '8px', marginBottom: '16px' }}>
                            <strong>Import Contacts</strong><br/>
                            <span style={{ fontSize: '14px', color: '#64748b' }}>Connect Google Workspace or upload a CSV.</span>
                            <button style={{ marginTop: '12px', padding: '6px 16px', background: '#f8fafc', border: '1px solid #cbd5e1', borderRadius: '4px', cursor: 'pointer' }}>Connect Data</button>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <h2 style={{ marginTop: 0 }}>Finalize Setup 🚀</h2>
                        <p style={{ color: '#475569' }}>Your Tenant isolation is configured. Invite your team to get started.</p>
                        <input 
                            type="text" 
                            placeholder="Email addresses (comma separated)" 
                            style={{ width: '100%', padding: '12px', border: '1px solid #cbd5e1', borderRadius: '8px', marginBottom: '16px', boxSizing: 'border-box' }}
                        />
                    </div>
                )}

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '32px' }}>
                    {step > 1 && (
                        <button onClick={() => setStep(step - 1)} style={{ padding: '10px 20px', background: 'transparent', border: '1px solid #cbd5e1', borderRadius: '8px', cursor: 'pointer' }}>
                            Back
                        </button>
                    )}
                    <button 
                        onClick={nextStep} 
                        disabled={step === 1 && !data.companyName}
                        style={{ padding: '10px 20px', background: '#3b82f6', color: '#fff', border: 'none', borderRadius: '8px', cursor: 'pointer', fontWeight: 'bold' }}>
                        {step === 3 ? "Launch CRM Dashboard" : "Continue"}
                    </button>
                </div>
            </div>
        </div>
    );
}
