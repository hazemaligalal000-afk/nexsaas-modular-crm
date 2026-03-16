import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import './LandingPage.css';

const LandingPage = () => {
  const [showExitIntent, setShowExitIntent] = useState(false);
  const [activeTab, setActiveTab] = useState('pipeline');
  const [isStickyVisible, setIsStickyVisible] = useState(false);
  const [timeLeft, setTimeLeft] = useState({ minutes: 59, seconds: 59 });
  const [scrollProgress, setScrollProgress] = useState(0);
  const [roiTeamSize, setRoiTeamSize] = useState(10);
  const [roiRevenue, setRoiRevenue] = useState(50000);
  const [activeFaq, setActiveFaq] = useState(0);
  const [toast, setToast] = useState({ visible: false, text: "" });

  useEffect(() => {
    const handleScroll = () => {
      const scrollPx = document.documentElement.scrollTop;
      const winHeightPx = document.documentElement.scrollHeight - document.documentElement.clientHeight;
      const scrolled = (scrollPx / winHeightPx) * 100;
      setScrollProgress(scrolled);
      setIsStickyVisible(window.scrollY > 800);
    };

    const handleMouseOut = (e) => {
      if (e.clientY <= 0) {
        setShowExitIntent(true);
        window.removeEventListener('mouseout', handleMouseOut);
      }
    };

    window.addEventListener('scroll', handleScroll);
    window.addEventListener('mouseout', handleMouseOut);

    const timer = setInterval(() => {
      setTimeLeft(prev => {
        let { minutes, seconds } = prev;
        if (seconds > 0) seconds--;
        else {
          seconds = 59;
          if (minutes > 0) minutes--;
        }
        return { minutes, seconds };
      });
    }, 1000);

    const toastInterval = setInterval(() => {
      const cities = ["New York", "London", "Dubai", "Cairo", "Riyadh", "Berlin", "Paris", "Austin"];
      const city = cities[Math.floor(Math.random() * cities.length)];
      setToast({ visible: true, text: `⚡ Someone in ${city} just started a Nexa Pro trial.` });
      setTimeout(() => setToast({ visible: false, text: "" }), 5000);
    }, 20000);

    return () => {
      window.removeEventListener('scroll', handleScroll);
      window.removeEventListener('mouseout', handleMouseOut);
      clearInterval(timer);
      clearInterval(toastInterval);
    };
  }, []);

  const features = [
    { cat: "Sales Ops", items: ["Lead Scoring", "Opportunity Mapping", "Pipeline Management", "Predictive Forecasting", "Territory Setup", "Quota Management", "Auto-Dialer", "Email Tracking", "Activity Logs", "Deal Analysis", "Contact Sync", "Calendar Integration", "Lead Distribution", "Task Automation", "Sales Scripts", "Role-Based Access", "Shared Pipelines", "Custom Stages", "Historical Trends", "Sales Velocity", "Lost Deal Recovery", "Win-Loss Analysis", "Account Hierarchies"] },
    { cat: "AI Engine", items: ["Neural Lead Scoring", "Sentiment Analysis", "Predictive Closing", "Churn Detection", "Automatic Coaching", "Email Drafter AI", "Smart Follow-ups", "Intent Detection", "Behavioral Alerts", "Growth Forecasting", "Revenue Guard™", "NLP Search", "Relationship Health", "Auto-DeDuplication", "Dynamic Pricing AI", "Market Intelligence", "Anomaly Detection", "Lead Research", "Predictive LTV"] },
    { cat: "Operations", items: ["Invoicing", "Sales Orders", "Quotes & Proposals", "Purchase Orders", "Inventory Tracking", "Vendor Mgmt", "Multi-Currency", "Tax Automation", "Payment Gateway", "Recurring Billing", "Subscription Mgmt", "Stock Alerts", "Warehouse Mgmt", "SKU Tracking", "Price Books", "Unit Converters", "Expense Tracking", "Work Orders", "Project Boards"] },
    { cat: "Security", items: ["AES-256 Encryption", "SOC2 Type II", "GDPR Ready", "HIPAA Compliant", "Two-Factor Auth", "SAML SSO", "Audit Logs", "IP Whitelisting", "Data Sovereignty", "Regional Storage", "E2E Encryption", "Password Policies", "Role Permissions", "Field-Level Security", "API Keys", "Disaster Recovery", "Zero-Trust Arch"] }
  ];

  const calculateROI = () => {
    const productivityGain = 0.35; // 35% gain
    const closingBoost = 0.20; // 20% boost
    const savings = (roiTeamSize * 1500) + (roiRevenue * closingBoost);
    return Math.floor(savings).toLocaleString();
  };

  return (
    <div className="landing-page">
      <div className="scroll-progress" style={{ width: `${scrollProgress}%` }}></div>

      {/* ⚡ LIVE SOCIAL PROOF */}
      {toast.visible && (
        <div className="social-toast reveal">
           <div style={{ background: 'var(--nexa-primary)', width: '8px', height: '8px', borderRadius: '50%' }}></div>
           <p style={{ margin: 0, fontSize: '14px', color: '#fff' }}>{toast.text}</p>
        </div>
      )}

      {/* 🛑 EXIT INTENT POPUP */}
      {showExitIntent && (
        <div className="exit-intent-overlay" onClick={() => setShowExitIntent(false)}>
          <div className="exit-intent-modal reveal" onClick={e => e.stopPropagation()}>
            <span className="pill-ai feature-pill">LIMITED TIME OFFER</span>
            <h2 style={{ marginTop: '20px' }}>Wait! Nexa has more for you.</h2>
            <p style={{ margin: '20px 0 30px' }}>Lock in a <strong>20% Life-Time Discount</strong> before this timer hits zero.</p>
            <div style={{ fontSize: '48px', fontWeight: '900', color: 'var(--nexa-primary)', marginBottom: '30px' }}>
              {String(timeLeft.minutes).padStart(2, '0')}:{String(timeLeft.seconds).padStart(2, '0')}
            </div>
            <Link to="/login" className="btn btn-primary" style={{ width: '100%', textDecoration: 'none' }}>Claim My Exclusive Access</Link>
            <button onClick={() => setShowExitIntent(false)} style={{ marginTop: '20px', background: 'none', border: 'none', color: '#666', cursor: 'pointer' }}>No thanks, I'll pay full price</button>
          </div>
        </div>
      )}

      {/* 🧭 STICKY NAVIGATION */}
      <header className="nexa-header">
        <div className="nexa-container nexa-flex" style={{ justifyContent: 'space-between', width: '100%' }}>
          <div className="logo" style={{ fontSize: '32px', fontWeight: '900', letterSpacing: '-2px' }}>
            NEXA <span style={{ color: 'var(--nexa-primary)' }}>CRM</span>
          </div>
          <nav className="nexa-flex" style={{ gap: '30px' }}>
            <a href="#features" className="btn btn-outline" style={{ border: 'none' }}>Features</a>
            <a href="#roi" className="btn btn-outline" style={{ border: 'none' }}>ROI Calc</a>
            <Link to="/login" className="btn btn-primary" style={{ textDecoration: 'none' }}>Start Trial</Link>
          </nav>
        </div>
      </header>

      {/* 🚀 STICKY CTA BAR */}
      <div className={`sticky-cta-bar ${isStickyVisible ? 'visible' : ''}`}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
          <span className="pill-ai feature-pill">GROWTH ALERT</span>
          <p style={{ margin: 0, fontSize: '14px' }}>Join 10,000+ teams winning with Nexa Intelligence™.</p>
        </div>
        <Link to="/login" className="btn btn-primary" style={{ padding: '8px 24px', fontSize: '14px', textDecoration: 'none' }}>Get Started Free</Link>
      </div>

      {/* 1. HERO SECTION */}
      <section className="nexa-section" style={{ textAlign: 'center', padding: '160px 0 100px 0', background: 'radial-gradient(circle at top, rgba(5, 255, 145, 0.2) 0%, transparent 70%)' }}>
        <div className="nexa-container reveal">
          <span className="feature-pill pill-ai">AUTHENTIC AI DOMINANCE</span>
          <h1 style={{ marginTop: '24px' }}>The CRM That <br/> <span style={{ color: 'var(--nexa-primary)' }}>Closes The Gap.</span></h1>
          <p style={{ maxWidth: '800px', margin: '32px auto 48px auto', fontSize: '22px' }}>
             Autonomous Sales Intelligence. Predictive Forecasting. Automated Follow-ups. <br/> Stop running a database, start running an empire.
          </p>
          <div className="nexa-flex" style={{ justifyContent: 'center', gap: '24px' }}>
            <Link to="/login" className="btn btn-primary" style={{ padding: '24px 60px', fontSize: '20px', textDecoration: 'none' }}>Start Free Trial</Link>
            <Link to="/login" className="btn btn-outline" style={{ padding: '24px 60px', fontSize: '20px', textDecoration: 'none' }}>Watch Product Demo</Link>
          </div>
          
          <div className="animate-float" style={{ marginTop: '100px', background: 'rgba(255,255,255,0.02)', border: '1px solid var(--nexa-border)', borderRadius: '32px', padding: '1px' }}>
             <div style={{ background: '#000', borderRadius: '31px', height: '600px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
                <div style={{ textAlign: 'center' }}>
                   <div style={{ fontSize: '160px', filter: 'drop-shadow(0 0 40px var(--nexa-primary))' }}>🏎️</div>
                   <h2 style={{ fontSize: '42px' }}>Deep Intelligence Dashboard</h2>
                   <p>One unified view. Infinite results.</p>
                </div>
             </div>
          </div>
        </div>
      </section>

      {/* 📊 INTERACTIVE ROI CALCULATOR */}
      <section className="nexa-section" id="roi" style={{ background: '#050505' }}>
        <div className="nexa-container text-center" style={{ textAlign: 'center' }}>
          <h2>Your Growth, <span style={{ color: 'var(--nexa-primary)' }}>In Numbers.</span></h2>
          <p style={{ marginBottom: '60px' }}>Calculate exactly how much revenue Nexa Intelligence™ can unlock for you.</p>
          
          <div className="roi-calculator">
             <div className="nexa-grid" style={{ gridTemplateColumns: '1fr 1fr', textAlign: 'left', gap: '60px' }}>
                <div>
                   <div style={{ marginBottom: '40px' }}>
                      <label style={{ display: 'block', marginBottom: '16px', fontWeight: '700' }}>Team Size: <span style={{ color: 'var(--nexa-primary)' }}>{roiTeamSize} Users</span></label>
                      <input type="range" min="1" max="500" value={roiTeamSize} onChange={e => setRoiTeamSize(e.target.value)} className="range-slider" />
                   </div>
                   <div>
                      <label style={{ display: 'block', marginBottom: '16px', fontWeight: '700' }}>Monthly Revenue: <span style={{ color: 'var(--nexa-primary)' }}>${roiRevenue.toLocaleString()}</span></label>
                      <input type="range" min="1000" max="1000000" step="1000" value={roiRevenue} onChange={e => setRoiRevenue(e.target.value)} className="range-slider" />
                   </div>
                </div>
                <div style={{ background: 'rgba(5,255,145,0.05)', borderRadius: '24px', padding: '40px', border: '1px solid var(--nexa-primary)' }}>
                   <p style={{ color: '#fff', fontSize: '14px', textTransform: 'uppercase', letterSpacing: '2px' }}>Estimated Annual Growth Gain</p>
                   <div style={{ fontSize: '72px', fontWeight: '900', color: 'var(--nexa-primary)', margin: '20px 0' }}>${calculateROI()}</div>
                   <p style={{ fontSize: '14px' }}>Based on 35% productivity gain and 20% closing boost.</p>
                   <Link to="/login" className="btn btn-primary" style={{ marginTop: '30px', width: '100%', textDecoration: 'none', textAlign: 'center' }}>Unlock This Growth</Link>
                </div>
             </div>
          </div>
        </div>
      </section>

      {/* 🧬 INTERACTIVE PRODUCT DEMO */}
      <section className="nexa-section" id="demo">
        <div className="nexa-container text-center" style={{ textAlign: 'center' }}>
          <h2>Experience the <span style={{ color: 'var(--nexa-primary)' }}>Future.</span></h2>
          <div style={{ marginTop: '60px', background: 'var(--nexa-card-bg)', border: '1px solid var(--nexa-border)', borderRadius: '32px', overflow: 'hidden' }}>
             <div style={{ padding: '40px', background: '#0a0a0a', borderBottom: '1px solid var(--nexa-border)', display: 'flex', gap: '40px', justifyContent: 'center' }}>
                {['pipeline', 'intelligence', 'automation', 'reports'].map(tab => (
                  <button key={tab} 
                    onClick={() => setActiveTab(tab)}
                    style={{ background: 'none', border: 'none', color: activeTab === tab ? 'var(--nexa-primary)' : '#666', borderBottom: activeTab === tab ? '2px solid var(--nexa-primary)' : 'none', padding: '10px 0', fontSize: '18px', fontWeight: '800', cursor: 'pointer', textTransform: 'uppercase' }}>
                    {tab}
                  </button>
                ))}
             </div>
             <div style={{ height: '500px', display: 'flex', justifyContent: 'center', alignItems: 'center', transition: 'all 0.5s' }}>
                {activeTab === 'pipeline' && <div className="reveal"><h3>Smart Pipeline Control</h3><p>Visual, autonomous deal management.</p></div>}
                {activeTab === 'intelligence' && <div className="reveal"><h3>Neural Insights View</h3><p>Real-time win probability & intent detection.</p></div>}
                {activeTab === 'automation' && <div className="reveal"><h3>Workflow Canvas</h3><p>No-code business logic orchestrator.</p></div>}
                {activeTab === 'reports' && <div className="reveal"><h3>Crystal Reports</h3><p>99.4% accurate revenue forecasting.</p></div>}
             </div>
          </div>
        </div>
      </section>

      {/* 🧩 FEATURE SHOWCASE */}
      <section className="nexa-section" id="features" style={{ background: '#020202' }}>
        <div className="nexa-container">
          <div style={{ textAlign: 'center', marginBottom: '100px' }}>
            <h2 style={{ fontSize: '56px' }}>80+ Core Capabilities.</h2>
            <p>The unified growth engine that replaces 10 separate tools.</p>
          </div>
          <div className="nexa-grid" style={{ gridTemplateColumns: 'repeat(4, 1fr)', gap: '40px' }}>
            {features.map((group, idx) => (
              <div key={idx} className="reveal">
                <h3 style={{ fontSize: '20px', color: 'var(--nexa-primary)', borderBottom: '1px solid var(--nexa-border)', paddingBottom: '16px', marginBottom: '24px' }}>{group.cat}</h3>
                <ul className="lp-feature-list" style={{ listStyle: 'none', padding: 0 }}>
                  {group.items.map((item, fidx) => (
                    <li key={fidx} style={{ marginBottom: '12px', fontSize: '14px', color: '#888', display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <span style={{ color: 'var(--nexa-primary)' }}>✓</span> {item}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ❓ INTERACTIVE FAQ */}
      <section className="nexa-section">
        <div className="nexa-container" style={{ maxWidth: '800px' }}>
          <h2 style={{ textAlign: 'center', marginBottom: '60px' }}>Your Questions, <span style={{ color: 'var(--nexa-primary)' }}>Settled.</span></h2>
          <div className="faq-grid">
             {[
                { q: "How fast is the setup?", a: "Nexa is built for speed. You can import your data and have your first automated dashboard running in under 5 minutes." },
                { q: "Is my data secure?", a: "We use AES-256 encryption, SOC2 Type II compliance, and GDPR-ready infrastructure across all regions." },
                { q: "Can I cancel anytime?", a: "Yes. No long-term contracts, no hidden fees. We win by being the best product for you every day." }
             ].map((faq, i) => (
                <div key={i} className={`faq-item ${activeFaq === i ? 'active' : ''}`} onClick={() => setActiveFaq(i)}>
                   <div className="faq-question">{faq.q} <span>{activeFaq === i ? '▴' : '▾'}</span></div>
                   <div className="faq-answer">{faq.a}</div>
                </div>
             ))}
          </div>
        </div>
      </section>

      {/* 🏁 FINAL CTA */}
      <section className="nexa-section" style={{ textAlign: 'center', background: 'radial-gradient(circle at bottom, rgba(5, 255, 145, 0.25) 0%, transparent 70%)' }}>
        <div className="nexa-container reveal">
          <h2 style={{ fontSize: '72px' }}>Start Your Empire.</h2>
          <p style={{ maxWidth: '600px', margin: '40px auto' }}>Join 10,000+ high-growth teams. No credit card required.</p>
          <Link to="/login" className="btn btn-primary pulse-button" style={{ padding: '24px 100px', fontSize: '24px', textDecoration: 'none' }}>Launch Nexa Instance</Link>
        </div>
      </section>

      <footer style={{ padding: '100px 0 60px 0', borderTop: '1px solid var(--nexa-border)', background: 'var(--nexa-bg)' }}>
        <div className="nexa-container">
           <div className="nexa-grid" style={{ gridTemplateColumns: '2fr repeat(3, 1fr)', gap: '60px' }}>
              <div>
                 <h3>NEXA CRM</h3>
                 <p style={{ marginTop: '20px' }}>The world's most intelligent revenue platform. One Unified View. Total Dominance.</p>
              </div>
              {['Product', 'Global', 'Company'].map(title => (
                <div key={title}>
                  <h4 style={{ color: '#fff', marginBottom: '24px' }}>{title}</h4>
                  <ul style={{ listStyle: 'none', padding: 0, fontSize: '14px', color: 'var(--nexa-muted)' }}>
                    <li>Feature Hub</li>
                    <li>Security Center</li>
                    <li>Global Compliance</li>
                  </ul>
                </div>
              ))}
           </div>
        </div>
      </footer>
    </div>
  );
};

export default LandingPage;
