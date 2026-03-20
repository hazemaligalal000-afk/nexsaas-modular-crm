import React, { useState } from 'react';

/**
 * AI Engine Dashboard: Performance & Model Control (Requirement Phase 3 / F2)
 * High-quality UI for multi-tenant AI monitoring and orchestration.
 */
export default function AIEngineDashboard() {
  const [selectedModel, setSelectedModel] = useState('gpt-4-turbo');
  
  const [performance] = useState({
    avg_latency: '1.2s',
    intent_accuracy: '94.2%',
    tokens_today: '142,500',
    total_cost: '$124.50'
  });

  const MODELS = [
    { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', provider: 'OpenAI', cost: 'High', latency: 'Med' },
    { id: 'claude-3-opus', name: 'Claude 3 Opus', provider: 'Anthropic', cost: 'Highest', latency: 'Slow' },
    { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', provider: 'Google', cost: 'Low', latency: 'Fast' }
  ];

  return (
    <div style={{ minHeight: '100vh', background: '#0b1628', color: '#fff', padding: '60px' }}>
      <div style={{ maxWidth: '1200px', margin: '0 auto' }}>
        
        {/* Header with Model Select */}
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '60px' }}>
           <div>
              <h1 style={{ fontSize: '42px', fontWeight: '900', letterSpacing: '-0.03em', marginBottom: '8px' }}>AI Neural Hub.</h1>
              <p style={{ fontSize: '18px', color: '#64748b' }}>Orchestrate model routing, monitor intent accuracy, and optimize token costs.</p>
           </div>
           <div>
              <label style={{ fontSize: '12px', fontWeight: '900', color: '#475569', textTransform: 'uppercase', marginBottom: '8px', display: 'block' }}>Primary Model Routing</label>
              <select value={selectedModel} onChange={e => setSelectedModel(e.target.value)} style={{ background: '#0d1a30', border: '1.5px solid #3b82f6', borderRadius: '12px', padding: '12px 24px', color: '#fff', fontSize: '14px', fontWeight: '800', outline: 'none' }}>
                 {MODELS.map(m => (
                   <option key={m.id} value={m.id}>{m.name}</option>
                 ))}
              </select>
           </div>
        </div>

        {/* Global KPI Cards */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '24px', marginBottom: '60px' }}>
           {[
             { label: 'Avg Latency', value: performance.avg_latency, status: 'Fast' },
             { label: 'Intent Accuracy', value: performance.intent_accuracy, status: '+2.1%' },
             { label: 'Cloud Tokens (24h)', value: performance.tokens_today, status: 'Active' },
             { label: 'Unit Costing', value: performance.total_cost, status: 'Standard' }
           ].map((kpi, i) => (
             <div key={i} style={{ background: '#0d1a30', borderRadius: '24px', border: '1.5px solid #1e3a5f', padding: '32px' }}>
                <div style={{ fontSize: '12px', fontWeight: '900', color: '#475569', textTransform: 'uppercase', marginBottom: '16px' }}>{kpi.label}</div>
                <div style={{ fontSize: '32px', fontWeight: '900', marginBottom: '12px' }}>{kpi.value}</div>
                <div style={{ fontSize: '13px', fontWeight: '700', color: '#10b981' }}>{kpi.status}</div>
             </div>
           ))}
        </div>

        {/* Model Selection Details (Requirement 151) */}
        <div style={{ background: '#0d1a30', borderRadius: '32px', border: '1px solid #1e3a5f', padding: '40px' }}>
           <h2 style={{ fontSize: '24px', fontWeight: '900', marginBottom: '32px' }}>Provider Availability</h2>
           <div style={{ display: 'grid', gap: '16px' }}>
              {MODELS.map(m => (
                <div key={m.id} style={{ padding: '24px', background: selectedModel === m.id ? '#1e3a5f33' : '#0b1628', border: '1.5px solid', borderColor: selectedModel === m.id ? '#3b82f6' : '#1e3a5f', borderRadius: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                   <div>
                      <div style={{ fontSize: '18px', fontWeight: '900' }}>{m.name}</div>
                      <div style={{ fontSize: '12px', color: '#475569' }}>Provider: {m.provider}</div>
                   </div>
                   <div style={{ display: 'flex', gap: '32px', textAlign: 'right' }}>
                      <div style={{ fontSize: '12px', fontWeight: '800' }}>Cost Efficiency: <span style={{ color: m.cost === 'High' ? '#f87171' : '#10b981' }}>{m.cost}</span></div>
                      <div style={{ fontSize: '12px', fontWeight: '800' }}>Inference: <span style={{ color: '#3b82f6' }}>{m.latency}</span></div>
                      <button style={{ background: selectedModel === m.id ? '#3b82f6' : '#1e3a5f', padding: '8px 24px', borderRadius: '10px', border: 'none', color: '#fff', fontWeight: '800', cursor: m.id === selectedModel ? 'default' : 'pointer' }}>{m.id === selectedModel ? 'Routing On' : 'Activate Routing'}</button>
                   </div>
                </div>
              ))}
           </div>
        </div>

      </div>
    </div>
  );
}
