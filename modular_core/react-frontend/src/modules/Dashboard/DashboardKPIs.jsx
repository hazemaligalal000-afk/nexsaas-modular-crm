import React from 'react';
import { useTranslation } from 'react-i18next';

/**
 * Requirement 14: Modern Dashboard with KPIs (Phase 3)
 */
const KPICard = ({ title, value, trend, percentage, icon }) => {
  const isUp = trend === 'up';
  
  return (
    <div style={{ background: '#1e3a5f11', border: '1px solid #1e3a5f', borderRadius: '24px', padding: '30px', flex: 1, minWidth: '240px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <div style={{ fontSize: '24px' }}>{icon}</div>
        <div style={{ fontSize: '12px', fontWeight: '800', padding: '6px 14px', borderRadius: '10px', background: isUp ? '#22c55e22' : '#ef444422', color: isUp ? '#22c55e' : '#ef4444' }}>
          {isUp ? '↑' : '↓'} {percentage}%
        </div>
      </div>
      <div style={{ fontSize: '13px', fontWeight: '600', color: '#64748b', textTransform: 'uppercase', letterSpacing: '1px', marginBottom: '8px' }}>{title}</div>
      <div style={{ fontSize: '32px', fontWeight: '900', color: '#fff' }}>{value}</div>
    </div>
  );
};

export default function DashboardKPIs() {
  const { t } = useTranslation();

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '24px', marginBottom: '40px' }}>
      <KPICard title={t('leads')} value="1,280" trend="up" percentage="12.5" icon="⚡" />
      <KPICard title={t('deals')} value="84" trend="up" percentage="8.2" icon="💼" />
      <KPICard title={t('ai_score')} value="78%" trend="down" percentage="3.1" icon="🧠" />
      <KPICard title="Revenue" value="$42,500" trend="up" percentage="18.4" icon="💰" />
    </div>
  );
}
