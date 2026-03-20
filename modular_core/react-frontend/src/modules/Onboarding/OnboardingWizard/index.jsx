import React, { useState } from 'react';

const STEPS = [
  { id: 1, title: 'Account Security', icon: '🔑' },
  { id: 2, title: 'Business Profile', icon: '🏗️' },
  { id: 3, title: 'Brand Identity', icon: '🎨' },
  { id: 4, title: 'Select Your Tier', icon: '💳' }
];

export default function OnboardingWizard() {
  const [step, setStep] = useState(1);
  const [formData, setFormData] = useState({
     email: '', password: '', 
     company_name: '', industry: 'SaaS', 
     primary_color: '#1d4ed8', 
     tier: 'starter'
  });

  const nextStep = () => setStep(s => Math.min(STEPS.length, s + 1));
  const prevStep = () => setStep(s => Math.max(1, s - 1));

  const handleFinish = () => {
     alert("Provisioning your workspace... Please wait 5 seconds. 🏗️");
     window.location.href = "/dashboard";
  };

  return (
    <div style={{ minHeight: '100vh', background: '#0b1628', color: '#fff', display: 'flex', justifyContent: 'center', alignItems: 'center', padding: '40px' }}>
      <div style={{ maxWidth: '1000px', width: '100%', background: '#0d1a30', borderRadius: '32px', border: '1px solid #1e3a5f', padding: '60px', boxShadow: '0 50px 100px rgba(0,0,0,0.5)' }}>
        
        {/* Progress Stepper */}
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '60px', position: 'relative' }}>
           <div style={{ position: 'absolute', top: '20px', left: 0, right: 0, height: '2px', background: '#1e3a5f', zIndex: 0 }}></div>
           <div style={{ position: 'absolute', top: '20px', left: 0, width: `${((step-1)/(STEPS.length-1))*100}%`, height: '2px', background: '#3b82f6', zIndex: 1, transition: '0.4s ease' }}></div>
           
           {STEPS.map(s => (
             <div key={s.id} style={{ position: 'relative', zIndex: 2, textAlign: 'center' }}>
                <div style={{ width: '40px', height: '40px', borderRadius: '12px', background: s.id <= step ? '#3b82f6' : '#0b1628', border: '2px solid', borderColor: s.id <= step ? '#3b82f6' : '#1e3a5f', display: 'flex', justifyContent: 'center', alignItems: 'center', margin: '0 auto 12px', transition: '0.4s' }}>
                   {s.id < step ? '✅' : s.icon}
                </div>
                <div style={{ fontSize: '11px', fontWeight: '800', color: s.id <= step ? '#fff' : '#475569', textTransform: 'uppercase' }}>{s.title}</div>
             </div>
           ))}
        </div>

        {/* Dynamic Content */}
        <div style={{ minHeight: '300px' }}>
           {step === 1 && (
             <div style={{ maxWidth: '500px', margin: '0 auto' }}>
                <h2 style={{ fontSize: '32px', fontWeight: '900', marginBottom: '8px' }}>Security First.</h2>
                <p style={{ color: '#64748b', marginBottom: '32px' }}>Define your administrator credentials to manage your tenant workspace.</p>
                <div style={{ marginBottom: '20px' }}>
                   <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#94a3b8', marginBottom: '8px' }}>Work Email</label>
                   <input type="email" placeholder="ceo@yourcompany.com" style={{ width: '100%', background: '#0b1628', border: '1.5px solid #1e3a5f', borderRadius: '14px', padding: '16px', color: '#fff', fontSize: '16px' }} />
                </div>
                <div style={{ marginBottom: '40px' }}>
                   <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#94a3b8', marginBottom: '8px' }}>Master Password</label>
                   <input type="password" placeholder="••••••••" style={{ width: '100%', background: '#0b1628', border: '1.5px solid #1e3a5f', borderRadius: '14px', padding: '16px', color: '#fff', fontSize: '16px' }} />
                </div>
             </div>
           )}

           {step === 2 && (
             <div style={{ maxWidth: '500px', margin: '0 auto' }}>
                <h2 style={{ fontSize: '32px', fontWeight: '900', marginBottom: '8px' }}>Company Blueprint.</h2>
                <p style={{ color: '#64748b', marginBottom: '32px' }}>Tell us about your business to optimize your AI modules.</p>
                <div style={{ marginBottom: '20px' }}>
                   <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#94a3b8', marginBottom: '8px' }}>Official Company Name</label>
                   <input type="text" placeholder="Globalize Holding Ltd." style={{ width: '100%', background: '#0b1628', border: '1.5px solid #1e3a5f', borderRadius: '14px', padding: '16px', color: '#fff', fontSize: '16px' }} />
                </div>
                <div>
                   <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#94a3b8', marginBottom: '8px' }}>Primary Industry</label>
                   <select style={{ width: '100%', background: '#0b1628', border: '1.5px solid #1e3a5f', borderRadius: '14px', padding: '16px', color: '#fff', fontSize: '16px' }}>
                      <option>Software & SaaS</option>
                      <option>Financial Services</option>
                      <option>Human Resources</option>
                      <option>Real Estate</option>
                   </select>
                </div>
             </div>
           )}

           {step === 3 && (
             <div style={{ display: 'flex', gap: '60px' }}>
                <div style={{ flex: 1 }}>
                   <h2 style={{ fontSize: '32px', fontWeight: '900', marginBottom: '8px' }}>Visual DNA.</h2>
                   <p style={{ color: '#64748b', marginBottom: '32px' }}>Define your primary brand color to theme your client portals and emails.</p>
                   <div>
                      <label style={{ display: 'block', fontSize: '12px', fontWeight: '700', color: '#94a3b8', marginBottom: '8px' }}>Primary Brand HEX</label>
                      <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                         <input type="color" value={formData.primary_color} onChange={e => setFormData({...formData, primary_color: e.target.value})} style={{ width: '80px', height: '60px', border: 'none', background: 'none', cursor: 'pointer' }} />
                         <input type="text" value={formData.primary_color} style={{ flex: 1, background: '#0b1628', border: '1.5px solid #1e3a5f', borderRadius: '14px', padding: '16px', color: '#fff', fontSize: '16px' }} />
                      </div>
                   </div>
                </div>
                <div style={{ width: '300px', height: '400px', background: formData.primary_color, borderRadius: '24px', opacity: 0.8, display: 'flex', justifyContent: 'center', alignItems: 'center', padding: '40px', textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
                   <div style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, background: 'linear-gradient(to bottom, transparent, rgba(0,0,0,0.5))' }}></div>
                   <div style={{ position: 'relative', zIndex: 1 }}>
                      <div style={{ background: '#fff', color: formData.primary_color, padding: '12px 24px', borderRadius: '12px', fontWeight: '900', fontSize: '18px' }}>NexSaaS Theme</div>
                      <p style={{ fontSize: '12px', color: '#eee', marginTop: '16px' }}>Live preview of your UI accent color in the platform.</p>
                   </div>
                </div>
             </div>
           )}

           {step === 4 && (
             <div style={{ textAlign: 'center' }}>
                <h2 style={{ fontSize: '32px', fontWeight: '900', marginBottom: '8px' }}>Enterprise Readiness.</h2>
                <p style={{ color: '#64748b', marginBottom: '40px' }}>Select the tier that best fits your business volume.</p>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '20px' }}>
                   {[
                     { id: 'starter', name: 'Starter', price: '$49', perks: '10k AI Tokens' },
                     { id: 'growth', name: 'Growth', price: '$149', perks: '50k AI Tokens' },
                     { id: 'enterprise', name: 'Infinite', price: '$499', perks: 'Unlimited AI' }
                   ].map(plan => (
                     <div key={plan.id} onClick={() => setFormData({...formData, tier: plan.id})} style={{ padding: '30px', background: formData.tier === plan.id ? '#3b82f611' : '#0b1628', border: '2.5px solid', borderColor: formData.tier === plan.id ? '#3b82f6' : '#1e3a5f', borderRadius: '24px', cursor: 'pointer', transition: '0.2s' }}>
                        <div style={{ fontSize: '14px', fontWeight: '800', color: '#64748b', textTransform: 'uppercase', marginBottom: '8px' }}>{plan.name}</div>
                        <div style={{ fontSize: '32px', fontWeight: '900', marginBottom: '16px' }}>{plan.price}<span style={{ fontSize: '14px', color: '#475569' }}>/mo</span></div>
                        <div style={{ fontSize: '12px', color: '#94a3b8' }}>{plan.perks}</div>
                     </div>
                   ))}
                </div>
             </div>
           )}
        </div>

        {/* Footer Navigation */}
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '60px', borderTop: '1px solid #1e3a5f', paddingTop: '40px' }}>
           <button onClick={prevStep} style={{ padding: '16px 40px', background: 'transparent', border: '1.5px solid #1e3a5f', borderRadius: '14px', color: '#64748b', fontSize: '16px', fontWeight: '800', cursor: 'pointer', visibility: step === 1 ? 'hidden' : 'visible' }}>Previous Step</button>
           <button onClick={step === 4 ? handleFinish : nextStep} style={{ padding: '16px 60px', background: '#3b82f6', border: 'none', borderRadius: '14px', color: '#fff', fontSize: '16px', fontWeight: '900', cursor: 'pointer', boxShadow: '0 10px 30px rgba(59, 130, 246, 0.4)' }}>
              {step === 4 ? 'Complete Onboarding 🚀' : 'Continue Step →'}
           </button>
        </div>

      </div>
    </div>
  );
}
