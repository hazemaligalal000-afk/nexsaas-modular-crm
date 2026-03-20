/**
 * i18n Test Component
 * Simple component to verify i18n setup is working
 */

import React from 'react';
import { useTranslation } from 'react-i18next';
import { useNumberFormatter, useDateFormatter } from '../i18n/hooks';
import { LanguageSwitcher } from './LanguageSwitcher';

export const I18nTest: React.FC = () => {
  const { t } = useTranslation();
  const { formatNumber, formatCurrency, formatPercent } = useNumberFormatter();
  const { formatShortDate, formatDateTime, formatRelativeTime } = useDateFormatter();

  const testDate = new Date('2024-01-15T10:30:00');
  const recentDate = new Date(Date.now() - 2 * 60 * 60 * 1000); // 2 hours ago

  return (
    <div style={{ padding: '2rem', maxWidth: '800px', margin: '0 auto' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2rem' }}>
        <h1>{t('app.name')} - {t('app.tagline')}</h1>
        <LanguageSwitcher />
      </div>

      <section style={{ marginBottom: '2rem' }}>
        <h2>{t('common.info')}</h2>
        <p>{t('common.loading')}</p>
        <p>{t('common.success')}</p>
        <p>{t('common.error')}</p>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>{t('navigation.dashboard')}</h2>
        <ul>
          <li>{t('navigation.crm')}</li>
          <li>{t('navigation.erp')}</li>
          <li>{t('navigation.accounting')}</li>
          <li>{t('navigation.settings')}</li>
        </ul>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>{t('actions.search')}</h2>
        <button>{t('actions.save')}</button>
        <button>{t('actions.cancel')}</button>
        <button>{t('actions.delete')}</button>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>Number Formatting</h2>
        <p>Number: {formatNumber(1234567.89)}</p>
        <p>Currency: {formatCurrency(1234.56, 'USD')}</p>
        <p>Percent: {formatPercent(75.5)}</p>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>Date Formatting</h2>
        <p>Short Date: {formatShortDate(testDate)}</p>
        <p>Date Time: {formatDateTime(testDate)}</p>
        <p>Relative Time: {formatRelativeTime(recentDate)}</p>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>{t('dashboard.title')}</h2>
        <ul>
          <li>{t('dashboard.totalLeads')}</li>
          <li>{t('dashboard.conversionRate')}</li>
          <li>{t('dashboard.revenuePipeline')}</li>
          <li>{t('dashboard.averageDealSize')}</li>
        </ul>
      </section>

      <section style={{ marginBottom: '2rem' }}>
        <h2>{t('leads.title')}</h2>
        <p>{t('leads.searchPlaceholder')}</p>
        <div>
          <span>{t('leads.filters.all')}</span> | 
          <span>{t('leads.filters.hot')}</span> | 
          <span>{t('leads.filters.warm')}</span> | 
          <span>{t('leads.filters.cold')}</span>
        </div>
      </section>
    </div>
  );
};
