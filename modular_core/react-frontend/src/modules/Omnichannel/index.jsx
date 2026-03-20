import React, { useState, useEffect, useRef } from 'react';

const CHANNELS = [
  { id: 'all',       label: 'All',        icon: '🗂️',  color: '#64748b' },
  { id: 'whatsapp',  label: 'WhatsApp',   icon: '💚',  color: '#25D366' },
  { id: 'telegram',  label: 'Telegram',   icon: '✈️',  color: '#2AABEE' },
  { id: 'email',     label: 'Email',      icon: '📧',  color: '#3b82f6' },
  { id: 'sms',       label: 'SMS',        icon: '📱',  color: '#8b5cf6' },
  { id: 'livechat',  label: 'Live Chat',  icon: '💬',  color: '#f59e0b' },
  { id: 'voip',      label: 'VoIP',       icon: '📞',  color: '#10b981' },
];

const STATUS_COLORS = {
  open:    { bg: '#fef3c7', text: '#92400e', label: 'Open' },
  pending: { bg: '#ede9fe', text: '#5b21b6', label: 'Pending' },
  resolved:{ bg: '#d1fae5', text: '#065f46', label: 'Resolved' },
  spam:    { bg: '#fee2e2', text: '#991b1b', label: 'Spam' },
};

const DEMO_CONVERSATIONS = [
  { id: 1, channel: 'whatsapp', name: 'Ahmed Hassan', phone: '+201001234567', avatar: 'AH', status: 'open',    unread: 3, lastMsg: 'I need help with my subscription billing issue', time: '2m ago',  assignee: 'Sara M.' },
  { id: 2, channel: 'email',    name: 'Fatima Al-Zahra', email: 'fatima@company.com', avatar: 'FZ', status: 'open', unread: 1, lastMsg: 'RE: Demo Request — Would love a call this week', time: '8m ago',  assignee: 'Omar K.' },
  { id: 3, channel: 'telegram', name: 'Karim Masri',  avatar: 'KM', status: 'pending', unread: 0, lastMsg: 'Is there an Arabic version of the mobile app?',  time: '15m ago', assignee: 'Unassigned' },
  { id: 4, channel: 'livechat', name: 'Maria Gonzalez', avatar: 'MG', status: 'open', unread: 5, lastMsg: 'Hello! I just signed up but can\'t log in',  time: '18m ago', assignee: 'Sara M.' },
  { id: 5, channel: 'sms',      name: 'David Chen',   avatar: 'DC', status: 'resolved', unread: 0, lastMsg: 'Thanks for the quick resolution!',             time: '1h ago',  assignee: 'Omar K.' },
  { id: 6, channel: 'voip',     name: 'Nour Khalil',  avatar: 'NK', status: 'open',    unread: 1, lastMsg: 'Missed call — 4m 22s',                           time: '2h ago',  assignee: 'Unassigned' },
  { id: 7, channel: 'whatsapp', name: 'Rania Boutros', avatar: 'RB', status: 'pending', unread: 2, lastMsg: 'Following up on the enterprise pricing proposal',  time: '3h ago',  assignee: 'Omar K.' },
  { id: 8, channel: 'email',    name: 'James Wilson', avatar: 'JW', status: 'open',    unread: 0, lastMsg: 'Integration documentation request',               time: '4h ago',  assignee: 'Unassigned' },
];

const DEMO_MESSAGES = {
  1: [
    { id: 1, from: 'contact', text: 'Hi, I have a billing issue with my subscription. It charged me twice this month.', time: '10:02 AM', channel: 'whatsapp' },
    { id: 2, from: 'agent',   text: 'Hi Ahmed! I\'m sorry to hear about the double charge. Let me look into your account right now.', time: '10:05 AM', agent: 'Sara M.' },
    { id: 3, from: 'contact', text: 'My invoice number is INV-2026-0342. Please check it.', time: '10:07 AM', channel: 'whatsapp' },
    { id: 4, from: 'agent',   text: 'Found it! I can see the duplicate charge. I\'ve initiated a refund for $49. It will show on your card within 3-5 business days.', time: '10:12 AM', agent: 'Sara M.' },
    { id: 5, from: 'contact', text: 'I need help with my subscription billing issue', time: '10:15 AM', channel: 'whatsapp' },
  ],
  2: [
    { id: 1, from: 'contact', text: 'Hello! We received your product demo request. I\'d love to schedule a call this week.', time: '9:30 AM', channel: 'email' },
    { id: 2, from: 'agent',   text: 'Hi Fatima! Absolutely. I\'m available Tuesday 2pm or Wednesday 10am EET. Which works better?', time: '9:45 AM', agent: 'Omar K.' },
    { id: 3, from: 'contact', text: 'RE: Demo Request — Would love a call this week', time: '10:00 AM', channel: 'email' },
  ],
};

const AI_SUGGESTIONS = [
  "Thank you for reaching out! I'll look into this right away.",
  "I understand your concern. Let me connect you with the right team.",
  "Your request has been escalated to our specialist team.",
  "I've checked your account and found the issue. Here's how we'll fix it:",
];

export default function OmnichannelInbox() {
  const [activeChannel, setActiveChannel] = useState('all');
  const [selectedConv, setSelectedConv] = useState(DEMO_CONVERSATIONS[0]);
  const [messages, setMessages] = useState(DEMO_MESSAGES[1] || []);
  const [reply, setReply] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [showAISuggest, setShowAISuggest] = useState(false);
  const [isTyping, setIsTyping] = useState(false);
  const messagesEndRef = useRef(null);

  const filtered = DEMO_CONVERSATIONS.filter(c => {
    const matchChannel = activeChannel === 'all' || c.channel === activeChannel;
    const matchStatus = statusFilter === 'all' || c.status === statusFilter;
    const matchSearch = c.name.toLowerCase().includes(search.toLowerCase()) ||
                        c.lastMsg.toLowerCase().includes(search.toLowerCase());
    return matchChannel && matchStatus && matchSearch;
  });

  const channelCounts = CHANNELS.reduce((acc, ch) => {
    acc[ch.id] = ch.id === 'all'
      ? DEMO_CONVERSATIONS.filter(c => c.unread > 0).length
      : DEMO_CONVERSATIONS.filter(c => c.channel === ch.id && c.unread > 0).length;
    return acc;
  }, {});

  const selectConv = (conv) => {
    setSelectedConv(conv);
    setMessages(DEMO_MESSAGES[conv.id] || [
      { id: 1, from: 'contact', text: conv.lastMsg, time: conv.time, channel: conv.channel }
    ]);
    setShowAISuggest(false);
  };

  const sendMessage = () => {
    if (!reply.trim()) return;
    const newMsg = { id: Date.now(), from: 'agent', text: reply, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), agent: 'You' };
    setMessages(prev => [...prev, newMsg]);
    setReply('');
    setShowAISuggest(false);
    // Simulate contact typing response
    setTimeout(() => setIsTyping(true), 1000);
    setTimeout(() => setIsTyping(false), 3500);
  };

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const chanInfo = CHANNELS.find(c => c.id === selectedConv?.channel) || CHANNELS[1];
  const statusInfo = STATUS_COLORS[selectedConv?.status] || STATUS_COLORS.open;

  return (
    <div style={{ display: 'flex', height: '100%', background: '#0b1628', color: '#e2e8f0', fontFamily: 'Inter, system-ui, sans-serif', overflow: 'hidden' }}>

      {/* Channel Rail */}
      <div style={{ width: '64px', background: '#060d1e', borderRight: '1px solid #0f2040', display: 'flex', flexDirection: 'column', alignItems: 'center', padding: '16px 0', gap: '4px' }}>
        {CHANNELS.map(ch => (
          <button key={ch.id}
            onClick={() => setActiveChannel(ch.id)}
            title={ch.label}
            style={{
              width: '44px', height: '44px', borderRadius: '12px', border: 'none', cursor: 'pointer',
              background: activeChannel === ch.id ? `${ch.color}22` : 'transparent',
              outline: activeChannel === ch.id ? `2px solid ${ch.color}44` : 'none',
              fontSize: '20px', display: 'flex', alignItems: 'center', justifyContent: 'center',
              position: 'relative', transition: 'all 0.2s',
            }}>
            {ch.icon}
            {channelCounts[ch.id] > 0 && (
              <span style={{ position: 'absolute', top: '4px', right: '4px', background: '#ef4444', color: '#fff', width: '16px', height: '16px', borderRadius: '50%', fontSize: '10px', fontWeight: '700', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                {channelCounts[ch.id]}
              </span>
            )}
          </button>
        ))}
      </div>

      {/* Conversations List */}
      <div style={{ width: '320px', background: '#0d1a30', borderRight: '1px solid #0f2040', display: 'flex', flexDirection: 'column', flexShrink: 0 }}>
        {/* Header */}
        <div style={{ padding: '16px', borderBottom: '1px solid #0f2040' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
            <h2 style={{ margin: 0, fontSize: '16px', fontWeight: '700' }}>
              {CHANNELS.find(c => c.id === activeChannel)?.icon} {CHANNELS.find(c => c.id === activeChannel)?.label} Inbox
            </h2>
            <span style={{ background: '#1e3a5f', color: '#60a5fa', padding: '2px 8px', borderRadius: '12px', fontSize: '11px', fontWeight: '700' }}>
              {filtered.length}
            </span>
          </div>
          <input
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Search conversations..."
            style={{ width: '100%', padding: '8px 12px', background: '#0b1628', border: '1px solid #0f2040', borderRadius: '8px', color: '#e2e8f0', fontSize: '13px', boxSizing: 'border-box' }}
          />
          <div style={{ display: 'flex', gap: '4px', marginTop: '8px' }}>
            {['all', 'open', 'pending', 'resolved'].map(s => (
              <button key={s} onClick={() => setStatusFilter(s)}
                style={{ flex: 1, padding: '4px 0', borderRadius: '6px', border: 'none', cursor: 'pointer', fontSize: '11px', fontWeight: '600',
                  background: statusFilter === s ? '#1e3a5f' : 'transparent',
                  color: statusFilter === s ? '#60a5fa' : '#475569',
                  textTransform: 'capitalize' }}>
                {s}
              </button>
            ))}
          </div>
        </div>

        {/* Conversation List */}
        <div style={{ flex: 1, overflowY: 'auto' }}>
          {filtered.map(conv => {
            const ch = CHANNELS.find(c => c.id === conv.channel);
            const st = STATUS_COLORS[conv.status];
            const isActive = selectedConv?.id === conv.id;
            return (
              <div key={conv.id} onClick={() => selectConv(conv)}
                style={{ padding: '14px 16px', borderBottom: '1px solid #0f2040', cursor: 'pointer', transition: 'background 0.15s',
                  background: isActive ? '#0f2040' : 'transparent' }}>
                <div style={{ display: 'flex', gap: '10px', alignItems: 'flex-start' }}>
                  {/* Avatar */}
                  <div style={{ position: 'relative', flexShrink: 0 }}>
                    <div style={{ width: '38px', height: '38px', borderRadius: '12px', background: `${ch?.color}22`, color: ch?.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700', fontSize: '13px' }}>
                      {conv.avatar}
                    </div>
                    <span style={{ position: 'absolute', bottom: '-2px', right: '-2px', fontSize: '12px' }}>{ch?.icon}</span>
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '3px' }}>
                      <span style={{ fontWeight: '600', fontSize: '13px', color: '#f1f5f9' }}>{conv.name}</span>
                      <span style={{ fontSize: '11px', color: '#475569' }}>{conv.time}</span>
                    </div>
                    <div style={{ fontSize: '12px', color: '#64748b', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', marginBottom: '6px' }}>
                      {conv.lastMsg}
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontSize: '10px', background: st.bg, color: st.text, padding: '2px 6px', borderRadius: '4px', fontWeight: '600' }}>{st.label}</span>
                      <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
                        <span style={{ fontSize: '10px', color: '#334155' }}>👤 {conv.assignee}</span>
                        {conv.unread > 0 && (
                          <span style={{ background: '#3b82f6', color: '#fff', width: '18px', height: '18px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px', fontWeight: '700' }}>
                            {conv.unread}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Main Chat Area */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        {selectedConv ? (
          <>
            {/* Chat Header */}
            <div style={{ padding: '14px 20px', background: '#0d1a30', borderBottom: '1px solid #0f2040', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                <div style={{ width: '42px', height: '42px', borderRadius: '12px', background: `${chanInfo.color}22`, color: chanInfo.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700', fontSize: '15px' }}>
                  {selectedConv.avatar}
                </div>
                <div>
                  <div style={{ fontWeight: '700', fontSize: '15px' }}>{selectedConv.name}</div>
                  <div style={{ fontSize: '12px', color: '#475569' }}>
                    {chanInfo.icon} {chanInfo.label}
                    {selectedConv.phone && ` · ${selectedConv.phone}`}
                    {selectedConv.email && ` · ${selectedConv.email}`}
                  </div>
                </div>
              </div>
              <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                <span style={{ fontSize: '11px', background: STATUS_COLORS[selectedConv.status].bg, color: STATUS_COLORS[selectedConv.status].text, padding: '4px 10px', borderRadius: '6px', fontWeight: '700' }}>
                  {STATUS_COLORS[selectedConv.status].label}
                </span>
                <select style={{ padding: '6px 10px', background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '6px', color: '#94a3b8', fontSize: '12px', cursor: 'pointer' }}>
                  <option>Assign to…</option>
                  <option>Sara M.</option>
                  <option>Omar K.</option>
                  <option>Unassigned</option>
                </select>
                <button style={{ padding: '6px 14px', background: '#10b981', border: 'none', borderRadius: '6px', color: '#fff', fontWeight: '600', fontSize: '12px', cursor: 'pointer' }}>
                  ✓ Resolve
                </button>
                <button style={{ padding: '6px 10px', background: 'transparent', border: '1px solid #1e3a5f', borderRadius: '6px', color: '#64748b', fontSize: '12px', cursor: 'pointer' }}>
                  ⋯
                </button>
              </div>
            </div>

            {/* Messages */}
            <div style={{ flex: 1, overflowY: 'auto', padding: '20px', display: 'flex', flexDirection: 'column', gap: '12px' }}>
              {/* Date divider */}
              <div style={{ textAlign: 'center', color: '#334155', fontSize: '11px', fontWeight: '600' }}>
                Today, {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })}
              </div>

              {messages.map(msg => (
                <div key={msg.id} style={{ display: 'flex', flexDirection: msg.from === 'agent' ? 'row-reverse' : 'row', gap: '8px', alignItems: 'flex-end' }}>
                  {msg.from === 'contact' && (
                    <div style={{ width: '28px', height: '28px', borderRadius: '8px', background: `${chanInfo.color}22`, color: chanInfo.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700', fontSize: '11px', flexShrink: 0 }}>
                      {selectedConv.avatar}
                    </div>
                  )}
                  <div style={{ maxWidth: '60%' }}>
                    <div style={{
                      padding: '10px 14px', borderRadius: msg.from === 'agent' ? '14px 14px 4px 14px' : '14px 14px 14px 4px',
                      background: msg.from === 'agent' ? '#1d4ed8' : '#0f2040',
                      color: msg.from === 'agent' ? '#fff' : '#e2e8f0',
                      fontSize: '13px', lineHeight: '1.5'
                    }}>
                      {msg.text}
                    </div>
                    <div style={{ fontSize: '10px', color: '#334155', marginTop: '3px', textAlign: msg.from === 'agent' ? 'right' : 'left' }}>
                      {msg.time} {msg.from === 'agent' && `· ${msg.agent}`}
                    </div>
                  </div>
                </div>
              ))}

              {isTyping && (
                <div style={{ display: 'flex', gap: '8px', alignItems: 'flex-end' }}>
                  <div style={{ width: '28px', height: '28px', borderRadius: '8px', background: `${chanInfo.color}22`, color: chanInfo.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px' }}>
                    {selectedConv.avatar}
                  </div>
                  <div style={{ padding: '10px 16px', background: '#0f2040', borderRadius: '14px 14px 14px 4px', display: 'flex', gap: '4px', alignItems: 'center' }}>
                    {[0, 0.2, 0.4].map((d, i) => (
                      <div key={i} style={{ width: '6px', height: '6px', borderRadius: '50%', background: '#475569', animation: `bounce 1s ${d}s infinite` }} />
                    ))}
                  </div>
                </div>
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* AI Suggestions */}
            {showAISuggest && (
              <div style={{ padding: '10px 20px', background: '#060d1e', borderTop: '1px solid #0f2040' }}>
                <div style={{ fontSize: '11px', color: '#3b82f6', fontWeight: '700', marginBottom: '8px' }}>🤖 AI Reply Suggestions</div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                  {AI_SUGGESTIONS.map((s, i) => (
                    <button key={i} onClick={() => { setReply(s); setShowAISuggest(false); }}
                      style={{ padding: '6px 12px', background: '#0f2040', border: '1px solid #1e3a5f', borderRadius: '20px', color: '#94a3b8', fontSize: '12px', cursor: 'pointer', textAlign: 'left' }}>
                      {s.length > 60 ? s.slice(0,60) + '…' : s}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Compose Area */}
            <div style={{ padding: '14px 20px', background: '#0d1a30', borderTop: '1px solid #0f2040' }}>
              <div style={{ display: 'flex', gap: '8px', marginBottom: '10px' }}>
                {['Reply', 'Note', 'Email', 'Transfer'].map(tab => (
                  <button key={tab} style={{ padding: '5px 12px', borderRadius: '6px', border: 'none', cursor: 'pointer', fontSize: '12px', fontWeight: '600',
                    background: tab === 'Reply' ? '#1e3a5f' : 'transparent',
                    color: tab === 'Reply' ? '#60a5fa' : '#475569' }}>
                    {tab}
                  </button>
                ))}
              </div>
              <div style={{ display: 'flex', gap: '8px', alignItems: 'flex-end' }}>
                <div style={{ flex: 1, background: '#0b1628', border: '1px solid #1e3a5f', borderRadius: '10px', padding: '10px 14px' }}>
                  <textarea
                    value={reply}
                    onChange={e => setReply(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }}
                    placeholder={`Reply via ${chanInfo.label}… (Enter to send, Shift+Enter for newline)`}
                    rows={3}
                    style={{ width: '100%', background: 'transparent', border: 'none', color: '#e2e8f0', fontSize: '13px', resize: 'none', outline: 'none', fontFamily: 'inherit' }}
                  />
                  <div style={{ display: 'flex', gap: '6px', marginTop: '6px' }}>
                    {['📎', '📷', '📍', '😊'].map(ico => (
                      <button key={ico} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: '16px', opacity: 0.6 }}>{ico}</button>
                    ))}
                  </div>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                  <button onClick={() => setShowAISuggest(s => !s)}
                    title="AI Suggestions"
                    style={{ padding: '10px', background: showAISuggest ? '#1d4ed8' : '#0f2040', border: '1px solid #1e3a5f', borderRadius: '8px', color: '#60a5fa', cursor: 'pointer', fontSize: '18px' }}>
                    🤖
                  </button>
                  <button onClick={sendMessage}
                    style={{ padding: '10px 18px', background: '#1d4ed8', border: 'none', borderRadius: '8px', color: '#fff', fontWeight: '700', cursor: 'pointer', fontSize: '14px' }}>
                    ↑
                  </button>
                </div>
              </div>
            </div>
          </>
        ) : (
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#334155' }}>
            Select a conversation to start
          </div>
        )}
      </div>

      {/* Contact Panel */}
      {selectedConv && (
        <div style={{ width: '260px', background: '#0d1a30', borderLeft: '1px solid #0f2040', overflowY: 'auto', padding: '20px', flexShrink: 0 }}>
          {/* Contact Card */}
          <div style={{ textAlign: 'center', marginBottom: '20px' }}>
            <div style={{ width: '56px', height: '56px', borderRadius: '16px', background: `${chanInfo.color}22`, color: chanInfo.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: '700', fontSize: '20px', margin: '0 auto 10px' }}>
              {selectedConv.avatar}
            </div>
            <div style={{ fontWeight: '700', fontSize: '15px', marginBottom: '4px' }}>{selectedConv.name}</div>
            <div style={{ fontSize: '11px', color: '#475569' }}>{selectedConv.email || selectedConv.phone || 'No contact info'}</div>
          </div>

          {/* Quick Actions */}
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '6px', marginBottom: '20px' }}>
            {[['📞', 'Call'], ['📧', 'Email'], ['📅', 'Meeting'], ['👤', 'CRM']].map(([ico, lbl]) => (
              <button key={lbl} style={{ padding: '8px', background: '#0b1628', border: '1px solid #0f2040', borderRadius: '8px', color: '#64748b', cursor: 'pointer', fontSize: '11px', fontWeight: '600', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '4px' }}>
                <span style={{ fontSize: '18px' }}>{ico}</span>{lbl}
              </button>
            ))}
          </div>

          {/* Details */}
          {[
            { label: 'Channel', value: `${chanInfo.icon} ${chanInfo.label}` },
            { label: 'Status', value: STATUS_COLORS[selectedConv.status].label },
            { label: 'Assignee', value: selectedConv.assignee },
            { label: 'First Contact', value: '2026-02-10' },
            { label: 'Conversations', value: '4' },
            { label: 'CSAT Score', value: '4.8 ⭐' },
          ].map(item => (
            <div key={item.label} style={{ marginBottom: '12px' }}>
              <div style={{ fontSize: '10px', color: '#334155', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '3px' }}>{item.label}</div>
              <div style={{ fontSize: '13px', color: '#94a3b8' }}>{item.value}</div>
            </div>
          ))}

          {/* Tags */}
          <div style={{ marginTop: '16px' }}>
            <div style={{ fontSize: '10px', color: '#334155', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '8px' }}>Tags</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
              {['VIP', 'Billing', 'Enterprise'].map(tag => (
                <span key={tag} style={{ padding: '3px 8px', background: '#0f2040', border: '1px solid #1e3a5f', borderRadius: '12px', fontSize: '11px', color: '#60a5fa' }}>{tag}</span>
              ))}
              <button style={{ padding: '3px 8px', background: 'transparent', border: '1px dashed #1e3a5f', borderRadius: '12px', fontSize: '11px', color: '#334155', cursor: 'pointer' }}>+</button>
            </div>
          </div>

          {/* Previous Conversations */}
          <div style={{ marginTop: '20px', borderTop: '1px solid #0f2040', paddingTop: '16px' }}>
            <div style={{ fontSize: '11px', color: '#334155', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '10px' }}>Previous Convs.</div>
            {[
              { ch: '📧', txt: 'Billing inquiry', date: 'Feb 10' },
              { ch: '💚', txt: 'Product question', date: 'Jan 22' },
            ].map((prev, i) => (
              <div key={i} style={{ padding: '8px', background: '#0b1628', borderRadius: '6px', marginBottom: '6px', cursor: 'pointer' }}>
                <div style={{ fontSize: '12px', color: '#94a3b8' }}>{prev.ch} {prev.txt}</div>
                <div style={{ fontSize: '10px', color: '#334155', marginTop: '2px' }}>{prev.date}</div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
