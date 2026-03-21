import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

const C = {
  bg: '#050d1a',
  card: 'rgba(13, 25, 48, 0.7)',
  blue: '#3b82f6',
  cyan: '#06b6d4',
  mint: '#05ff91',
  text: '#f1f5f9',
  muted: '#94a3b8',
  border: 'rgba(255, 255, 255, 0.1)',
};

export default function LandingPage() {
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 50);
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <div style={{ background: C.bg, color: C.text, minHeight: '100vh', fontFamily: 'Inter, sans-serif' }}>
      {/* Background Glows */}
      <div style={{ position: 'fixed', top: '-10%', left: '-5%', width: '40vw', height: '40vw', background: C.blue, filter: 'blur(150px)', opacity: 0.15, zIndex: 0 }}></div>
      <div style={{ position: 'fixed', bottom: '0', right: '-5%', width: '30vw', height: '30vw', background: C.mint, filter: 'blur(120px)', opacity: 0.1, zIndex: 0 }}></div>

      {/* Navbar */}
      <nav style={{ 
        position: 'fixed', top: 0, left: 0, right: 0, height: '80px', display: 'flex', alignItems: 'center', 
        justifyContent: 'space-between', padding: '0 8%', zIndex: 100,
        background: scrolled ? 'rgba(5, 13, 26, 0.8)' : 'transparent',
        backdropFilter: scrolled ? 'blur(10px)' : 'none',
        borderBottom: scrolled ? `1px solid ${C.border}` : 'none',
        transition: '0.3s'
      }}>
        <div style={{ fontSize: '24px', fontWeight: '900', letterSpacing: '-1px' }}>
          Nex<span style={{ color: C.blue }}>SaaS</span>
        </div>
        <div style={{ display: 'flex', gap: '40px', fontSize: '14px', fontWeight: '600' }}>
          {['Features', 'Pricing', 'Integrations', 'Docs'].map(item => (
            <a key={item} href={`#${item.toLowerCase()}`} style={{ color: C.muted, textDecoration: 'none' }}>{item}</a>
          ))}
        </div>
        <div>
          <Link to="/login" style={{ color: C.text, textDecoration: 'none', marginRight: '24px', fontSize: '14px', fontWeight: '700' }}>Login</Link>
          <Link to="/onboarding" style={{ 
            background: `linear-gradient(135deg, ${C.blue}, ${C.cyan})`, color: '#fff', padding: '12px 28px', 
            borderRadius: '12px', textDecoration: 'none', fontWeight: '800', fontSize: '14px',
            boxShadow: '0 10px 20px -5px rgba(59, 130, 246, 0.4)'
          }}>Start Trial</Link>
        </div>
      </nav>

      {/* Hero */}
      <header style={{ paddingTop: '160px', paddingBottom: '100px', textAlign: 'center', position: 'relative', zIndex: 1 }}>
        <div className="container" style={{ maxWidth: '1000px', margin: '0 auto' }}>
          <div style={{ 
             display: 'inline-block', padding: '6px 16px', background: 'rgba(5, 255, 145, 0.1)', 
             color: C.mint, borderRadius: '100px', fontSize: '11px', fontWeight: '800', marginBottom: '24px' 
          }}>MARKET READY RELEASE 🚀</div>
          <h1 style={{ fontSize: 'clamp(48px, 8vw, 84px)', fontWeight: '900', letterSpacing: '-3px', marginBottom: '24px', lineHeight: 1.1 }}>
            The Intelligent <br />
            <span style={{ background: `linear-gradient(to right, #fff, ${C.muted})`, WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent' }}>Revenue OS.</span>
          </h1>
          <p style={{ fontSize: '18px', color: C.muted, maxWidth: '600px', margin: '0 auto 48px', lineHeight: 1.6 }}>
            NexSaaS combines AI-driven lead scoring, multi-tenant workspace isolation, and streamlined billing into a single modular platform.
          </p>
          <div style={{ display: 'flex', justifyContent: 'center', gap: '20px' }}>
            <Link to="/onboarding" style={{ padding: '18px 48px', background: C.blue, color: '#fff', borderRadius: '16px', textDecoration: 'none', fontWeight: '900', fontSize: '16px', boxShadow: '0 15px 30px -10px rgba(59,130,246,0.6)' }}>Deploy Console</Link>
            <a href="#demo" style={{ padding: '18px 48px', border: `1px solid ${C.border}`, color: C.text, borderRadius: '16px', textDecoration: 'none', fontWeight: '800', fontSize: '16px' }}>Live Demo</a>
          </div>

          <div style={{ marginTop: '100px', padding: '12px', background: C.border, borderRadius: '32px', boxShadow: '0 40px 120px rgba(0,0,0,0.5)' }}>
            <img 
               src="https://images.unsplash.com/photo-1551288049-bb8482c4ad89?auto=format&fit=crop&q=80&w=2000" 
               alt="Dashboard" 
               style={{ width: '100%', borderRadius: '24px', border: `1px solid ${C.border}` }}
            />
          </div>
        </div>
      </header>

      {/* Pricing Grid */}
      <section id="pricing" style={{ padding: '100px 0', borderTop: `1px solid ${C.border}` }}>
        <div style={{ textAlign: 'center', marginBottom: '64px' }}>
            <h2 style={{ fontSize: '42px', fontWeight: '900', marginBottom: '16px' }}>Transparent, Scalable.</h2>
            <p style={{ color: C.muted }}>Select the tier that matches your business velocity.</p>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '32px', maxWidth: '1200px', margin: '0 auto', padding: '0 24px' }}>
            {[
              { id: 'starter', name: 'Starter', price: '$49', perks: ['5 Users', '500 Lead Scores', 'Basic AI', 'Shared Pool'] },
              { id: 'growth', name: 'Growth', price: '$149', perks: ['25 Users', '2,000 Lead Scores', 'Claude AI Insights', 'Advanced RBAC'], featured: true },
              { id: 'enterprise', name: 'Enterprise', price: '$499', perks: ['Unlimited Users', 'Unlimited AI', 'Dedicated DB', 'Custom Training'] },
            ].map(plan => (
              <div key={plan.name} style={{ 
                padding: '48px', background: plan.featured ? 'rgba(59, 130, 246, 0.05)' : C.card, 
                border: '1px solid', borderColor: plan.featured ? C.blue : C.border, 
                borderRadius: '32px', textAlign: 'center', position: 'relative',
                transform: plan.featured ? 'scale(1.05)' : 'none',
                zIndex: plan.featured ? 2 : 1
              }}>
                {plan.featured && <div style={{ position: 'absolute', top: '16px', right: '16px', fontSize: '10px', color: C.mint, fontWeight: '900' }}>POPULAR</div>}
                <div style={{ color: C.muted, fontWeight: '800', fontSize: '13px', textTransform: 'uppercase', letterSpacing: '2px', marginBottom: '16px' }}>{plan.name}</div>
                <div style={{ fontSize: '56px', fontWeight: '900', marginBottom: '8px' }}>{plan.price}<span style={{ fontSize: '18px', color: C.muted, fontWeight: '500' }}>/mo</span></div>
                <ul style={{ listStyle: 'none', padding: 0, margin: '40px 0', textAlign: 'left', minHeight: '160px' }}>
                  {plan.perks.map(p => <li key={p} style={{ marginBottom: '12px', fontSize: '15px', color: '#cbd5e1' }}>✅ {p}</li>)}
                </ul>
                <Link to={`/onboarding?tier=${plan.id}`} style={{ 
                  display: 'block', padding: '16px', 
                  background: plan.featured ? C.blue : 'transparent', 
                  border: plan.featured ? 'none' : `1px solid ${C.border}`, 
                  color: '#fff', borderRadius: '14px', textDecoration: 'none', fontWeight: '800'
                }}>Get Started</Link>
              </div>
            ))}
        </div>
      </section>

      <footer style={{ padding: '60px 0', textAlign: 'center', color: C.muted, fontSize: '13px' }}>
        <p>© 2026 NexSaaS Modular CRM. All Rights Reserved.</p>
      </footer>
    </div>
  );
}
