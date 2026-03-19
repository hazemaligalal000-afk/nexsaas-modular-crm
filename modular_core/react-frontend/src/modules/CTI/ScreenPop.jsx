import React, { useEffect, useState } from 'react';

const C = {
  accent: '#3b82f6',
  green: '#10b981',
  red: '#ef4444',
  bg: '#f8fafc',
  card: '#ffffff',
  border: '#e2e8f0',
  text: '#0f172a',
  muted: '#64748b',
};

export default function ScreenPop({ callData, onClose, onAnswer, onReject }) {
  const [contact, setContact] = useState(null);
  const [deals, setDeals] = useState([]);
  const [tickets, setTickets] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (callData?.contact) {
      setContact(callData.contact);
      setDeals(callData.deals || []);
      setTickets(callData.tickets || []);
      setLoading(false);
    }
  }, [callData]);

  if (!callData) return null;

  return (
    <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999 }}>
      <div style={{ background: C.card, borderRadius: '16px', width: '600px', maxHeight: '80vh', overflow: 'auto', boxShadow: '0 20px 60px rgba(0,0,0,0.3)' }}>
        {/* Header */}
        <div style={{ padding: '24px', borderBottom: `1px solid ${C.border}`, background: C.accent, color: '#fff', borderRadius: '16px 16px 0 0' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <div style={{ fontSize: '18px', fontWeight: '700', marginBottom: '4px' }}>📞 Incoming Call</div>
              <div style={{ fontSize: '14px', opacity: 0.9 }}>{callData.from_number}</div>
            </div>
            <button onClick={onClose} style={{ background: 'rgba(255,255,255,0.2)', border: 'none', color: '#fff', width: '32px', height: '32px', borderRadius: '8px', cursor: 'pointer', fontSize: '18px' }}>×</button>
          </div>
        </div>

        {/* Contact Info */}
        <div style={{ padding: '24px' }}>
          {loading ? (
            <div style={{ textAlign: 'center', padding: '40px', color: C.muted }}>Loading contact...</div>
          ) : contact ? (
            <>
              <div style={{ display: 'flex', gap: '16px', marginBottom: '24px' }}>
                <div style={{ width: '64px', height: '64px', borderRadius: '12px', background: C.accent + '20', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '28px' }}>👤</div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: '20px', fontWeight: '700', color: C.text }}>{contact.first_name} {contact.last_name}</div>
                  <div style={{ fontSize: '14px', color: C.muted, marginTop: '4px' }}>{contact.email}</div>
                  <div style={{ fontSize: '14px', color: C.muted }}>{contact.phone}</div>
                </div>
              </div>

              {/* Deals */}
              {deals.length > 0 && (
                <div style={{ marginBottom: '20px' }}>
                  <div style={{ fontSize: '13px', fontWeight: '700', color: C.text, marginBottom: '8px' }}>Active Deals ({deals.length})</div>
                  {deals.slice(0, 3).map(deal => (
                    <div key={deal.id} style={{ padding: '12px', background: C.bg, borderRadius: '8px', marginBottom: '8px' }}>
                      <div style={{ fontSize: '14px', fontWeight: '600', color: C.text }}>{deal.name}</div>
                      <div style={{ fontSize: '12px', color: C.muted, marginTop: '4px' }}>{deal.stage} • {deal.amount} {deal.currency}</div>
                    </div>
                  ))}
                </div>
              )}

              {/* Tickets */}
              {tickets.length > 0 && (
                <div style={{ marginBottom: '20px' }}>
                  <div style={{ fontSize: '13px', fontWeight: '700', color: C.text, marginBottom: '8px' }}>Recent Tickets ({tickets.length})</div>
                  {tickets.slice(0, 3).map(ticket => (
                    <div key={ticket.id} style={{ padding: '12px', background: C.bg, borderRadius: '8px', marginBottom: '8px' }}>
                      <div style={{ fontSize: '14px', fontWeight: '600', color: C.text }}>{ticket.subject}</div>
                      <div style={{ fontSize: '12px', color: C.muted, marginTop: '4px' }}>{ticket.status} • {ticket.priority}</div>
                    </div>
                  ))}
                </div>
              )}
            </>
          ) : (
            <div style={{ textAlign: 'center', padding: '40px' }}>
              <div style={{ fontSize: '48px', marginBottom: '16px' }}>❓</div>
              <div style={{ fontSize: '16px', fontWeight: '600', color: C.text }}>Unknown Caller</div>
              <div style={{ fontSize: '14px', color: C.muted, marginTop: '8px' }}>No contact found for {callData.from_number}</div>
            </div>
          )}
        </div>

        {/* Actions */}
        <div style={{ padding: '20px 24px', borderTop: `1px solid ${C.border}`, display: 'flex', gap: '12px' }}>
          <button onClick={onReject} style={{ flex: 1, padding: '12px', background: C.red, color: '#fff', border: 'none', borderRadius: '10px', fontSize: '14px', fontWeight: '600', cursor: 'pointer' }}>Reject</button>
          <button onClick={onAnswer} style={{ flex: 2, padding: '12px', background: C.green, color: '#fff', border: 'none', borderRadius: '10px', fontSize: '14px', fontWeight: '600', cursor: 'pointer' }}>Answer Call</button>
        </div>
      </div>
    </div>
  );
}
