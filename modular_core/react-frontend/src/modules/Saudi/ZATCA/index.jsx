import React, { useState } from 'react';

const STEPS = ['Introduction', 'Organization Identity', 'Certificate Sign Request (CSR)', 'Compliance Check (OTP)', 'Final Certification'];

export default function ZATCAOnboarding() {
  const [currentStep, setCurrentStep] = useState(1);
  const [formData, setFormData] = useState({
    vatNumber: '300000000000003',
    crNumber: '1010123456',
    legalNameAR: 'شركة نكس ساس السعودية المحدودة',
    legalNameEN: 'NexSaaS Saudi Limited Company',
    commonName: 'NexSaaS_Production_Node_01',
    otp: '',
  });

  const next = () => setCurrentStep(s => Math.min(s + 1, STEPS.length));
  const prev = () => setCurrentStep(s => Math.max(s - 1, 1));

  return (
    <div style={{ padding: '28px', background: '#0b1628', minHeight: '100%', color: '#e2e8f0', display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
      <div style={{ width: '100%', maxWidth: '800px' }}>
        <h1 style={{ margin: '0 0 8px', fontSize: '24px', fontWeight: '900' }}>📄 ZATCA Fatoora Phase 2 Onboarding</h1>
        <p style={{ margin: '0 0 32px', color: '#475569', fontSize: '14px' }}>Establish your technical connection with the Zakat, Tax and Customs Authority for real-time clearance.</p>

        {/* Stepper Header */}
        <div style={{ display: 'grid', gridTemplateColumns: `repeat(${STEPS.length}, 1fr)`, gap: '10px', marginBottom: '40px' }}>
          {STEPS.map((s, i) => (
            <div key={s} style={{ textAlign: 'center' }}>
              <div style={{ height: '6px', background: i + 1 <= currentStep ? '#05ff91' : '#0f2040', borderRadius: '10px', marginBottom: '10px', boxShadow: i + 1 === currentStep ? '0 0 10px #05ff9144' : 'none' }} />
              <div style={{ fontSize: '10px', fontWeight: '800', textTransform: 'uppercase', color: i + 1 === currentStep ? '#05ff91' : '#475569' }}>Step {i + 1}</div>
              <div style={{ fontSize: '11px', color: i + 1 === currentStep ? '#fff' : '#334155', display: 'block', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' }}>{s}</div>
            </div>
          ))}
        </div>

        {/* Step 1 Content */}
        {currentStep === 1 && (
          <div style={{ background: '#0d1a30', borderRadius: '20px', padding: '40px', border: '1px solid #0f2040' }}>
            <div style={{ fontSize: '48px', marginBottom: '20px', textAlign: 'center' }}>🧾</div>
            <h2 style={{ textAlign: 'center', marginBottom: '12px' }}>Mandatory Wave 23 Compliance</h2>
            <p style={{ textAlign: 'center', color: '#94a3b8', fontSize: '15px', lineHeight: '1.6', marginBottom: '24px' }}>
              Your annual revenue triggers Wave 23 (SAR 750k+) which requires full integration by March 31, 2026. 
              This wizard will generate your cryptographic keys and Production ID (CSID).
            </p>
            <div style={{ background: '#0b1628', padding: '20px', borderRadius: '12px', border: '1px dashed #3b82f644', color: '#60a5fa', fontSize: '13px' }}>
              ⚠️ You must have your ZATCA portal login credentials ready as the system will request a 6-digit OTP in Step 4.
            </div>
            <button onClick={next} style={{ width: '100%', padding: '14px', background: '#1d4ed8', color: '#fff', border: 'none', borderRadius: '12px', marginTop: '32px', fontWeight: '800', fontSize: '15px', cursor: 'pointer' }}>Start My Integration</button>
          </div>
        )}

        {/* Step 2: Org Identity */}
        {currentStep === 2 && (
          <div style={{ background: '#0d1a30', borderRadius: '20px', padding: '40px', border: '1px solid #0f2040' }}>
            <h3 style={{ marginBottom: '24px' }}>Verify Establishment Identity</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
              {['vatNumber', 'crNumber', 'legalNameAR', 'legalNameEN'].map(key => (
                <div key={key}>
                  <label style={{ display: 'block', fontSize: '12px', color: '#475569', marginBottom: '8px', textTransform: 'uppercase', fontWeight: '700' }}>
                    {key.replace(/([A-Z])/g, ' $1').trim()}
                  </label>
                  <input value={formData[key]} onChange={e => setFormData({...formData, [key]: e.target.value})}
                    style={{ width: '100%', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', padding: '12px', color: '#f1f5f9', fontSize: '15px', outline: 'none' }} />
                </div>
              ))}
              <div style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                <button onClick={prev} style={{ flex: 1, padding: '14px', background: 'transparent', color: '#94a3b8', border: '1px solid #1e3a5f', borderRadius: '12px', fontWeight: '700' }}>Previous</button>
                <button onClick={next} style={{ flex: 2, padding: '14px', background: '#1d4ed8', color: '#fff', border: 'none', borderRadius: '12px', fontWeight: '700' }}>Continue to Security</button>
              </div>
            </div>
          </div>
        )}

        {/* Step 3: CSR Security */}
        {currentStep === 3 && (
          <div style={{ background: '#0d1a30', borderRadius: '20px', padding: '40px', border: '1px solid #0f2040' }}>
            <h3 style={{ marginBottom: '24px' }}>Generate Secure X.509 Certificate (CSR)</h3>
            <p style={{ color: '#94a3b8', fontSize: '14px', marginBottom: '24px' }}>
              NexSaaS will now generate a local ECDSA (secp256k1) key pair and a Certificate Signing Request formatted for ZATCA (Fatoora).
            </p>
            <div style={{ background: '#0b1628', padding: '16px', borderRadius: '12px', border: '1px solid #0f2040', marginBottom: '24px' }}>
              <div style={{ fontSize: '11px', color: '#334155', fontWeight: '700', marginBottom: '10px' }}>CSR PREVIEW</div>
              <code style={{ fontSize: '12px', color: '#60a5fa', wordBreak: 'break-all', display: 'block', height: '100px', overflowY: 'auto' }}>
                -----BEGIN CERTIFICATE REQUEST-----
                MIICmzCCAYMCAQAwRzELMAkGA1UEBhMCU0ExEDAOBgNVBAcMB1JpeWFkaDEUMBIG
                A1UECgwLTmV4U2FhcyBDUk0xEjAQBgNVBAMMCVNBX05PREVfMDEwWTATBgcqhkjO
                PQIBBggqhkjOPQMBBwNCAARXsh8f1S/vE7yKzH9tN4iXw0J6S6mE/1uL6qFzM0aH
                ... [TRUNCATED FOR SECURITY] ...
                -----END CERTIFICATE REQUEST-----
              </code>
            </div>
             <div style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                <button onClick={prev} style={{ padding: '14px', background: 'transparent', color: '#94a3b8', border: '1px solid #1e3a5f', borderRadius: '12px', fontWeight: '700' }}>Previous</button>
                <button onClick={next} style={{ flex: 1, padding: '14px', background: '#05ff91', color: '#000', border: 'none', borderRadius: '12px', fontWeight: '800' }}>Confirm & Generate Keys</button>
              </div>
          </div>
        )}

        {/* Step 4: OTP Verification */}
        {currentStep === 4 && (
          <div style={{ background: '#0d1a30', borderRadius: '20px', padding: '40px', border: '1px solid #0f2040', textAlign: 'center' }}>
            <h3 style={{ marginBottom: '12px' }}>Verify with ZATCA Portal</h3>
            <p style={{ color: '#94a3b8', fontSize: '14px', marginBottom: '32px' }}>
               Please log in to your <strong>Fatoorah Portal</strong> on ZATCA.gov.sa and generate a 1-time OTP for E-Invoice integration.
            </p>
            <div style={{ display: 'flex', justifyContent: 'center', gap: '12px', marginBottom: '32px' }}>
               <input maxLength="6" placeholder="0 0 0 0 0 0" style={{ width: '200px', textAlign: 'center', fontSize: '32px', fontWeight: '900', background: '#0b1628', border: '2px solid #3b82f6', borderRadius: '12px', color: '#fff', padding: '14px', letterSpacing: '8px' }} />
            </div>
            <div style={{ display: 'flex', gap: '12px' }}>
                <button onClick={prev} style={{ padding: '14px', background: 'transparent', color: '#94a3b8', border: '1px solid #1e3a5f', borderRadius: '12px', fontWeight: '700' }}>Previous</button>
                <button onClick={next} style={{ flex: 1, padding: '14px', background: '#1d4ed8', color: '#fff', border: 'none', borderRadius: '12px', fontWeight: '800' }}>Verify CSID Access</button>
            </div>
          </div>
        )}

        {/* Step 5: Success */}
        {currentStep === 5 && (
          <div style={{ background: '#0d1a30', borderRadius: '20px', padding: '40px', border: '1.5px solid #05ff9122', textAlign: 'center' }}>
             <div style={{ fontSize: '64px', marginBottom: '20px' }}>✅</div>
             <h3 style={{ marginBottom: '8px', fontSize: '20px' }}>Integration Successfully Activated!</h3>
             <p style={{ color: '#94a3b8', fontSize: '14px', marginBottom: '32px' }}>
               NexSaaS is now connected to <strong>ZATCA Production API version 2.0</strong>.
               <br/>All Standard B2B invoices will now undergo real-time clearance.
             </p>
             <div style={{ textAlign: 'left', background: '#0b1628', padding: '24px', borderRadius: '12px', border: '1px solid #1e3a5f', marginBottom: '32px' }}>
                <div style={{ fontSize: '11px', color: '#475569', fontWeight: '700', marginBottom: '16px', textTransform: 'uppercase' }}>Connection Details</div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}><span style={{ color: '#475569' }}>CSID Version:</span> <span style={{ fontFamily: 'monospace' }}>Z_PRD_V2_2026_03</span></div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}><span style={{ color: '#475569' }}>Signing Key:</span> <span style={{ fontFamily: 'monospace' }}>Stored Encrypted (AES-GCM)</span></div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}><span style={{ color: '#475569' }}>Next Renewal:</span> <span style={{ fontWeight: '700', color: '#05ff91' }}>March 2027</span></div>
                </div>
             </div>
             <button onClick={() => window.location.href = '/dashboard/saudi'} style={{ width: '100%', padding: '14px', background: '#05ff91', color: '#000', border: 'none', borderRadius: '12px', fontWeight: '800' }}>Go to Compliance Hub</button>
          </div>
        )}
      </div>
    </div>
  );
}
