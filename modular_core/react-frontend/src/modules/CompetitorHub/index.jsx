import React, { useState } from 'react';

const COMPETITORS = [
  { id: 'salesforce', name: 'Salesforce', origin: 'USA', focus: 'Enterprise, GCC', price: '$75–$300', weakness: 'No ETA/ZATCA, Native WhatsApp, High Cost', badge: 'RED' },
  { id: 'hubspot',    name: 'HubSpot',    origin: 'USA', focus: 'SME to Enterprise', price: '$15–$150', weakness: 'Price Trap, No Arabic UI/RTL', badge: 'RED' },
  { id: 'zoho',       name: 'Zoho CRM',   origin: 'India', focus: 'SME, All MENA', price: '$14–$52', weakness: 'No ETA API, Dated UI, Clunky WhatsApp', badge: 'YELLOW' },
  { id: 'odoo',       name: 'Odoo',       origin: 'Belgium', focus: 'SME + ERP', price: '$25–$47', weakness: 'Complex Setup, Hidden Costs, No Native ETA', badge: 'YELLOW' },
  { id: 'flashlead',  name: 'Flash Lead', origin: 'Egypt', focus: 'Egypt + GCC SME', price: '$15–$30', weakness: 'No Accounting/ERP, No AI, No Multi-tenant', badge: 'GREEN' },
];

const BATTLE_CARDS = {
  salesforce: {
    script: "Salesforce is the right choice if you have a dedicated Salesforce admin, a $50K+ implementation budget, and no need for ETA invoicing or WhatsApp integration. For the Egyptian and Gulf market, NexSaaS gives you 80% of Salesforce's capability at 15% of the cost — with ETA compliance, ZATCA billing, and WhatsApp built in from day one.",
    win_condition: "Any company under 200 employees. Any company that mentions price. Any company that needs ETA/ZATCA compliance.",
    killers: ["No ETA e-invoice integration", "No ZATCA-compliant billing", "No native WhatsApp", "Implementation adds $10K-$100K"]
  },
  hubspot: {
    script: "HubSpot Starter is a great product. But the moment you need sequences, lead scoring, or marketing automation, you're looking at $800–$1,600 a month — for a product that still doesn't speak Arabic, doesn't know what ETA is, and sends your customers to WhatsApp via a third-party integration.",
    win_condition: "HubSpot Free/Starter users who are growing. Any company that does marketing in Arabic.",
    killers: ["Price jumps are brutal ($15 → $100/user)", "No Arabic UI/RTL", "No accounting module", "No ETA/ZATCA support"]
  },
  flashlead: {
    script: "Flash Lead is a good sales CRM built for Egypt. NexSaaS is a complete business management platform. You get everything Flash Lead has plus AI lead scoring, a full accounting module with ETA e-invoice, Egyptian payroll, and a Customer Portal. Flash Lead is a tool for your sales team. NexSaaS runs your entire business.",
    win_condition: "Flash Lead users who have grown beyond pure sales and need accounting/reporting.",
    killers: ["No accounting/ERP", "No payroll/tax compliance", "No AI lead scoring", "Small feature set overall"]
  }
};

export default function CompetitorHub() {
  const [selectedID, setSelectedID] = useState('salesforce');
  const card = BATTLE_CARDS[selectedID] || BATTLE_CARDS['salesforce'];

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0' }}>
      <div style={{ marginBottom: '32px' }}>
        <h1 style={{ margin: '0 0 6px', fontSize: '26px', fontWeight: '900' }}>🏆 Competitor & Pricing Intelligence</h1>
        <p style={{ margin: 0, color: '#475569', fontSize: '14px' }}>Real-time battle cards and pricing intelligence for the Egypt & Arab World Market (March 2026)</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(300px, 1fr) 2fr', gap: '28px' }}>
         {/* List */}
         <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
            {COMPETITORS.map(comp => (
              <div key={comp.id} onClick={() => setSelectedID(comp.id)} style={{ padding: '16px', background: '#0d1a30', borderRadius: '16px', border: `1.5px solid ${selectedID === comp.id ? '#3b82f6' : '#0f2040'}`, cursor: 'pointer', transition: 'all 0.2s' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                  <span style={{ fontWeight: '800', fontSize: '15px' }}>{comp.name}</span>
                  <span style={{ fontSize: '10px', fontWeight: '900', color: comp.badge === 'RED' ? '#ef4444' : comp.badge === 'YELLOW' ? '#f59e0b' : '#10b981' }}>{comp.origin}</span>
                </div>
                <div style={{ fontSize: '12px', color: '#475569' }}>{comp.focus} · {comp.price} / user</div>
              </div>
            ))}
            <div style={{ marginTop: '20px', padding: '20px', background: 'rgba(5,255,145,0.05)', borderRadius: '16px', border: '1px dashed #05ff9144' }}>
              <div style={{ fontSize: '13px', color: '#05ff91', fontWeight: '800', marginBottom: '4px' }}>NEXSAAS ADVANTAGE</div>
              <div style={{ fontSize: '11px', color: '#94a3b8' }}>Starting at **$49/month** for the whole team. Includes **ETA**, **WhatsApp**, **AI**, and **Accounting**.</div>
            </div>
         </div>

         {/* Battle Card View */}
         <div style={{ background: '#0d1a30', borderRadius: '24px', padding: '32px', border: '1px solid #0f2040' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginBottom: '32px' }}>
               <div style={{ width: '64px', height: '64px', background: '#0b1628', borderRadius: '16px', border: '1px solid #1e3a5f', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '24px' }}>
                 {COMPETITORS.find(c => c.id === selectedID)?.name[0]}
               </div>
               <div>
                  <h2 style={{ margin: 0, fontSize: '24px', fontWeight: '900' }}>{COMPETITORS.find(c => c.id === selectedID)?.name} Battle Card</h2>
                  <div style={{ color: '#475569', fontSize: '13px' }}>Positioning: {COMPETITORS.find(c => c.id === selectedID)?.focus}</div>
               </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px' }}>
               <div>
                  <h4 style={{ margin: '0 0 12px', fontSize: '13px', color: '#3b82f6', textTransform: 'uppercase', fontWeight: '800' }}>Sales Script</h4>
                  <p style={{ margin: 0, fontSize: '15px', lineHeight: '1.7', color: '#94a3b8', fontStyle: 'italic' }}>"{card.script}"</p>
                  
                  <h4 style={{ margin: '24px 0 12px', fontSize: '13px', color: '#ef4444', textTransform: 'uppercase', fontWeight: '800' }}>Critical Weaknesses (Dealers Killers)</h4>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                    {card.killers.map((k, i) => (
                      <div key={i} style={{ display: 'flex', gap: '8px', alignItems: 'center', fontSize: '14px', color: '#f1f5f9' }}>
                         <span style={{ color: '#ef4444' }}>✕</span> {k}
                      </div>
                    ))}
                  </div>
               </div>

               <div>
                  <div style={{ background: '#0b1628', borderRadius: '16px', padding: '20px', border: '1px solid #1e3a5f', marginBottom: '24px' }}>
                    <h4 style={{ margin: '0 0 8px', fontSize: '13px', color: '#10b981', textTransform: 'uppercase', fontWeight: '800' }}>Win Condition</h4>
                    <p style={{ margin: 0, fontSize: '14px', color: '#94a3b8' }}>{card.win_condition}</p>
                  </div>

                  <div style={{ background: '#1d4ed811', borderRadius: '16px', padding: '20px', border: '1px solid #3b82f633' }}>
                    <h4 style={{ margin: '0 0 8px', fontSize: '13px', color: '#3b82f6', textTransform: 'uppercase', fontWeight: '800' }}>Pricing vs NexSaaS</h4>
                    <div style={{ fontSize: '28px', fontWeight: '900', color: '#fff' }}>SAVE 85%</div>
                    <div style={{ fontSize: '12px', color: '#94a3b8', marginTop: '4px' }}>Average monthly saving for a team of 10: **$980.00**</div>
                  </div>
               </div>
            </div>
         </div>
      </div>
    </div>
  );
}
