import React, { useState } from 'react';

const inventory = [
  { sku: 'SKU-001', name: 'Enterprise License',   category: 'Software',  stock: 450, reorder: 100, status: 'ok',      warehouse: 'WH-A', value: '$225K' },
  { sku: 'SKU-002', name: 'Pro License',          category: 'Software',  stock: 82,  reorder: 100, status: 'low',     warehouse: 'WH-A', value: '$41K'  },
  { sku: 'SKU-003', name: 'Server Hardware Unit', category: 'Hardware',  stock: 12,  reorder: 20,  status: 'critical',warehouse: 'WH-B', value: '$96K'  },
  { sku: 'SKU-004', name: 'Network Switch 48P',   category: 'Hardware',  stock: 35,  reorder: 15,  status: 'ok',      warehouse: 'WH-B', value: '$52K'  },
  { sku: 'SKU-005', name: 'Support Package 1Y',   category: 'Service',   stock: 200, reorder: 50,  status: 'ok',      warehouse: 'WH-A', value: '$100K' },
  { sku: 'SKU-006', name: 'Training Bundle',      category: 'Service',   stock: 8,   reorder: 25,  status: 'critical',warehouse: 'WH-A', value: '$12K'  },
];

const movements = [
  { date: 'Mar 19', sku: 'SKU-001', type: 'IN',  qty: 100, ref: 'PO-2026-041', by: 'Warehouse A' },
  { date: 'Mar 18', sku: 'SKU-003', type: 'OUT', qty: 5,   ref: 'SO-2026-118', by: 'Customer Ship' },
  { date: 'Mar 17', sku: 'SKU-002', type: 'OUT', qty: 18,  ref: 'SO-2026-115', by: 'Customer Ship' },
  { date: 'Mar 16', sku: 'SKU-004', type: 'IN',  qty: 20,  ref: 'PO-2026-039', by: 'Warehouse B' },
  { date: 'Mar 15', sku: 'SKU-006', type: 'OUT', qty: 4,   ref: 'SO-2026-110', by: 'Customer Ship' },
];

const statusColor = { ok: '#10b981', low: '#f59e0b', critical: '#ef4444' };

export default function InventoryManagerDashboard() {
  const [tab, setTab] = useState('stock');

  const kpis = [
    { label: 'Total SKUs',     value: '6',     icon: '📦', color: '#3b82f6' },
    { label: 'Critical Alerts',value: '2',     icon: '🚨', color: '#ef4444' },
    { label: 'Total Value',    value: '$526K', icon: '💰', color: '#10b981' },
    { label: 'Warehouses',     value: '2',     icon: '🏭', color: '#8b5cf6' },
  ];

  return (
    <div style={s.page}>
      <div style={s.topBar}>
        <div>
          <h1 style={s.title}>Inventory Manager Dashboard</h1>
          <p style={s.sub}>Stock levels · Reorder alerts · Warehouse movements</p>
        </div>
        <button style={s.btn}>+ New PO</button>
      </div>

      <div style={s.kpiRow}>
        {kpis.map(k => (
          <div key={k.label} style={s.kpiCard}>
            <div style={{ ...s.kpiIcon, background: k.color + '20', color: k.color }}>{k.icon}</div>
            <div style={s.kpiVal}>{k.value}</div>
            <div style={s.kpiLabel}>{k.label}</div>
          </div>
        ))}
      </div>

      {/* Reorder alerts banner */}
      <div style={s.alertBanner}>
        <span style={{ fontSize: '16px' }}>🚨</span>
        <span style={{ fontWeight: '700', color: '#ef4444' }}>2 items need immediate reorder:</span>
        <span style={{ color: '#fca5a5' }}>Server Hardware Unit (12 left) · Training Bundle (8 left)</span>
      </div>

      <div style={s.tabs}>
        {['stock', 'movements'].map(t => (
          <button key={t} onClick={() => setTab(t)}
            style={{ ...s.tab, ...(tab === t ? s.tabActive : {}) }}>
            {t === 'stock' ? '📦 Stock Levels' : '🔄 Movements'}
          </button>
        ))}
      </div>

      {tab === 'stock' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['SKU', 'Item Name', 'Category', 'Stock', 'Reorder At', 'Status', 'Warehouse', 'Value'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {inventory.map(item => (
                <tr key={item.sku} style={s.tr}>
                  <td style={{ ...s.td, color: '#64748b', fontFamily: 'monospace' }}>{item.sku}</td>
                  <td style={{ ...s.td, fontWeight: '600', color: '#f1f5f9' }}>{item.name}</td>
                  <td style={s.td}>{item.category}</td>
                  <td style={{ ...s.td, fontWeight: '700', color: statusColor[item.status] }}>{item.stock}</td>
                  <td style={s.td}>{item.reorder}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: statusColor[item.status] + '20', color: statusColor[item.status] }}>
                      {item.status === 'ok' ? '✅ OK' : item.status === 'low' ? '⚠️ Low' : '🚨 Critical'}
                    </span>
                  </td>
                  <td style={s.td}>{item.warehouse}</td>
                  <td style={s.td}>{item.value}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {tab === 'movements' && (
        <div style={s.card}>
          <table style={s.table}>
            <thead>
              <tr>{['Date', 'SKU', 'Type', 'Qty', 'Reference', 'By'].map(h => (
                <th key={h} style={s.th}>{h}</th>
              ))}</tr>
            </thead>
            <tbody>
              {movements.map((m, i) => (
                <tr key={i} style={s.tr}>
                  <td style={{ ...s.td, color: '#64748b' }}>{m.date}</td>
                  <td style={{ ...s.td, fontFamily: 'monospace', color: '#94a3b8' }}>{m.sku}</td>
                  <td style={s.td}>
                    <span style={{ ...s.badge, background: m.type === 'IN' ? '#10b98120' : '#3b82f620', color: m.type === 'IN' ? '#10b981' : '#3b82f6' }}>
                      {m.type === 'IN' ? '↓ IN' : '↑ OUT'}
                    </span>
                  </td>
                  <td style={{ ...s.td, fontWeight: '700', color: '#f1f5f9' }}>{m.qty}</td>
                  <td style={{ ...s.td, fontFamily: 'monospace', color: '#64748b' }}>{m.ref}</td>
                  <td style={s.td}>{m.by}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

const s = {
  page:        { padding: '32px', background: '#0a0f1e', minHeight: '100%', color: '#f1f5f9' },
  topBar:      { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '28px' },
  title:       { fontSize: '24px', fontWeight: '800', color: '#f1f5f9', margin: 0 },
  sub:         { color: '#64748b', fontSize: '14px', marginTop: '4px' },
  btn:         { background: '#3b82f6', color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '10px', fontWeight: '700', cursor: 'pointer', fontSize: '14px' },
  kpiRow:      { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px', marginBottom: '20px' },
  kpiCard:     { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', padding: '20px', textAlign: 'center' },
  kpiIcon:     { width: '44px', height: '44px', borderRadius: '12px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px', margin: '0 auto 12px' },
  kpiVal:      { fontSize: '28px', fontWeight: '800', color: '#f1f5f9' },
  kpiLabel:    { fontSize: '12px', color: '#64748b', marginTop: '4px' },
  alertBanner: { background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', borderRadius: '10px', padding: '12px 16px', display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '20px', flexWrap: 'wrap' },
  tabs:        { display: 'flex', gap: '8px', marginBottom: '20px' },
  tab:         { padding: '8px 18px', borderRadius: '8px', border: '1px solid #1e293b', background: 'transparent', color: '#64748b', cursor: 'pointer', fontSize: '13px', fontWeight: '600' },
  tabActive:   { background: '#1e293b', color: '#f1f5f9', borderColor: '#334155' },
  card:        { background: '#0f172a', border: '1px solid #1e293b', borderRadius: '14px', overflow: 'hidden' },
  table:       { width: '100%', borderCollapse: 'collapse' },
  th:          { padding: '12px 16px', textAlign: 'left', fontSize: '11px', fontWeight: '700', color: '#475569', textTransform: 'uppercase', borderBottom: '1px solid #1e293b' },
  tr:          { borderBottom: '1px solid #1e293b' },
  td:          { padding: '14px 16px', fontSize: '13px', color: '#94a3b8' },
  badge:       { padding: '3px 10px', borderRadius: '100px', fontSize: '11px', fontWeight: '700' },
};
