/**
 * Date Range Filter Component
 * Requirements: 14 - Modern Dashboard with KPIs
 * 
 * Provides preset date ranges (7/30/90/365 days) and custom date picker
 */

import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DateRange } from '../../types/dashboard';
import './DateRangeFilter.css';

interface DateRangeFilterProps {
  value: DateRange;
  onChange: (range: DateRange) => void;
}

const PRESETS = [
  { key: 'last_7_days' as const, days: 7 },
  { key: 'last_30_days' as const, days: 30 },
  { key: 'last_quarter' as const, days: 90 },
  { key: 'last_year' as const, days: 365 }
];

export const DateRangeFilter: React.FC<DateRangeFilterProps> = ({ value, onChange }) => {
  const { t } = useTranslation();
  const [showCustomPicker, setShowCustomPicker] = useState(false);
  const [customStart, setCustomStart] = useState<string>('');
  const [customEnd, setCustomEnd] = useState<string>('');

  const handlePresetClick = (preset: typeof PRESETS[0]) => {
    const end = new Date();
    const start = new Date(Date.now() - preset.days * 24 * 60 * 60 * 1000);
    
    onChange({
      start,
      end,
      preset: preset.key
    });
    setShowCustomPicker(false);
  };

  const handleCustomRange = () => {
    if (customStart && customEnd) {
      const start = new Date(customStart);
      const end = new Date(customEnd);
      
      if (start <= end) {
        onChange({ start, end, preset: 'custom' });
        setShowCustomPicker(false);
      }
    }
  };

  const formatDateForInput = (date: Date): string => {
    return date.toISOString().split('T')[0];
  };

  return (
    <div className="date-range-filter">
      <div className="preset-buttons">
        {PRESETS.map((preset) => (
          <button
            key={preset.key}
            className={`preset-button ${value.preset === preset.key ? 'active' : ''}`}
            onClick={() => handlePresetClick(preset)}
          >
            {t(`dashboard.dateRange.${preset.key}`)}
          </button>
        ))}
        
        <button
          className={`preset-button ${value.preset === 'custom' ? 'active' : ''}`}
          onClick={() => setShowCustomPicker(!showCustomPicker)}
        >
          {t('dashboard.dateRange.custom')}
        </button>
      </div>
      
      {showCustomPicker && (
        <div className="custom-date-picker">
          <div className="date-inputs">
            <div className="date-input-group">
              <label htmlFor="start-date">{t('date.from')}</label>
              <input
                id="start-date"
                type="date"
                value={customStart || formatDateForInput(value.start)}
                onChange={(e) => setCustomStart(e.target.value)}
                max={customEnd || formatDateForInput(new Date())}
              />
            </div>
            
            <div className="date-input-group">
              <label htmlFor="end-date">{t('date.to')}</label>
              <input
                id="end-date"
                type="date"
                value={customEnd || formatDateForInput(value.end)}
                onChange={(e) => setCustomEnd(e.target.value)}
                min={customStart}
                max={formatDateForInput(new Date())}
              />
            </div>
          </div>
          
          <div className="custom-picker-actions">
            <button
              className="apply-button"
              onClick={handleCustomRange}
              disabled={!customStart || !customEnd}
            >
              {t('actions.apply')}
            </button>
            <button
              className="cancel-button"
              onClick={() => setShowCustomPicker(false)}
            >
              {t('actions.cancel')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
