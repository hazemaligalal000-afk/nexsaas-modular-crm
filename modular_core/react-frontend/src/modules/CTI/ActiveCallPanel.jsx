import React, { useState, useEffect } from 'react';

const C = {
  accent: '#3b82f6',
  green: '#10b981',
  red: '#ef4444',
  card: '#ffffff',
  border: '#e2e8f0',
  text: '#0f172a',
  muted: '#64748b',
};

export default function ActiveCallPanel({ callData, onHangup, onMute, onHold, onTransfer }) {
  const [duration, setDuration] = useState(0);
  const [isMuted, setIsMuted] = useState(false);
  const [isOnHold, setIsOnHold] = useState(false);
  const [notes, setNotes] = useState('');

  useEffect(() => {
    const timer = setInterval(() => {
      setDuration(d => d + 1);
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const formatDuration = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const handleMute = () => {
    setIsMuted(!isMuted);
    onMute(!isMuted);
  };

  const handleHold = () => {
    setIsOnHold(!isOnHold);
    onHold(!isOnHold);
  };

  return (
    <div style={{ position: 'fixed', right: '20px', bottom: '20px', width: '320px', background: C.card, border: `1px solid ${C.border}`, borderRadius: '16px', boxShadow: '0 10px 40px rgba(0,0,0,0.15)', zIndex: 9998 }}>
      {/* Header */}
      <div style={{ padding: '16px', borderBottom: `1px solid ${C.border}`, background: C.green, color: '#fff', borderRadius: '16px 16px 0 0' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <div style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#fff', animation: 'pulse 2s infinite' }} />
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: '14px', fontWeight: '700' }}>Active Call</div>
            <div style={{ fontSize: '12px', opacity: 0.9 }}>{callData?.from_number || 'Unknown'}</div>
          </div>
          <div style={{ fontSize: '16px', fontWeight: '700' }}>{formatDuration(duration)}</div>
        </div>
      </div>

      {/* Controls */}
      <div style={{ padding: '16px', display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
        <button onClick={handleMute} style={{ padding: '12px', background: isMuted ? C.red : C.card, color: isMuted ? '#fff' : C.text, border: `1px solid ${C.border}`, borderRadius: '8px', cursor: 'pointer', fontSize: '20px' }}>
          {isMuted ? '🔇' : '🔊'}
        </button>
        <button onClick={handleHold} style={{ padding: '12px', background: isOnHold ? C.accent : C.card, color: isOnHold ? '#fff' : C.text, border: `1px solid ${C.border}`, borderRadius: '8px', cursor: 'pointer', fontSize: '20px' }}>
          ⏸️
        </button>
        <button onClick={onTransfer} style={{ padding: '12px', background: C.card, color: C.text, border: `1px solid ${C.border}`, borderRadius: '8px', cursor: 'pointer', fontSize: '20px' }}>
          ↪️
        </button>
      </div>

      {/* Notes */}
      <div style={{ padding: '16px', borderTop: `1px solid ${C.border}` }}>
        <textarea
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Call notes..."
          style={{ width: '100%', minHeight: '80px', padding: '10px', border: `1px solid ${C.border}`, borderRadius: '8px', fontSize: '13px', fontFamily: 'inherit', resize: 'vertical' }}
        />
      </div>

      {/* Hangup */}
      <div style={{ padding: '16px', borderTop: `1px solid ${C.border}` }}>
        <button onClick={() => onHangup(notes)} style={{ width: '100%', padding: '12px', background: C.red, color: '#fff', border: 'none', borderRadius: '10px', fontSize: '14px', fontWeight: '600', cursor: 'pointer' }}>
          End Call
        </button>
      </div>
    </div>
  );
}
