/**
 * frontend/src/modules/CRM/Analytics/DashboardWidget.tsx
 *
 * Individual dashboard widget card component.
 * Renders data based on widget_type: table for reports, metric card for summaries.
 *
 * Requirements: 17.6, 17.7
 */

import React from 'react';
import type { DashboardWidget as WidgetConfig, WidgetData } from './hooks/useDashboard';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface DashboardWidgetProps {
  widget: WidgetConfig;
  data: WidgetData | undefined;
  isLoading: boolean;
  onRemove: (widgetId: number) => void;
  onRefresh: (widgetId: number) => void;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatTimestamp(ts: string | null | undefined): string {
  if (!ts) return 'Never';
  try {
    return new Date(ts).toLocaleString();
  } catch {
    return ts;
  }
}

// ---------------------------------------------------------------------------
// Summary metric types (single-value display)
// ---------------------------------------------------------------------------

const SUMMARY_TYPES = new Set([
  'pipeline_summary',
  'deal_velocity',
  'lead_conversion',
  'activity_summary',
  'revenue_forecast',
]);

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function LoadingSpinner() {
  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        height: '100%',
        minHeight: 80,
      }}
      aria-label="Loading widget data"
    >
      <div
        style={{
          width: 32,
          height: 32,
          border: '3px solid #e2e8f0',
          borderTopColor: '#4a90e2',
          borderRadius: '50%',
          animation: 'spin 0.8s linear infinite',
        }}
      />
      <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}

function DataTable({ rows, columns }: { rows: Record<string, unknown>[]; columns: string[] }) {
  if (rows.length === 0) {
    return <p style={{ color: '#718096', fontSize: 13 }}>No data available.</p>;
  }

  return (
    <div style={{ overflowX: 'auto', maxHeight: 220, overflowY: 'auto' }}>
      <table
        style={{
          width: '100%',
          borderCollapse: 'collapse',
          fontSize: 12,
        }}
      >
        <thead>
          <tr>
            {columns.map((col) => (
              <th
                key={col}
                style={{
                  textAlign: 'left',
                  padding: '4px 8px',
                  background: '#f7fafc',
                  borderBottom: '1px solid #e2e8f0',
                  fontWeight: 600,
                  whiteSpace: 'nowrap',
                }}
              >
                {col}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={i} style={{ background: i % 2 === 0 ? '#fff' : '#f7fafc' }}>
              {columns.map((col) => (
                <td
                  key={col}
                  style={{ padding: '4px 8px', borderBottom: '1px solid #edf2f7' }}
                >
                  {String(row[col] ?? '')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function SummaryCard({ data }: { data: unknown }) {
  if (!data || typeof data !== 'object') {
    return <p style={{ color: '#718096', fontSize: 13 }}>No data available.</p>;
  }

  const entries = Object.entries(data as Record<string, unknown>).slice(0, 6);

  return (
    <div
      style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))',
        gap: 8,
      }}
    >
      {entries.map(([key, value]) => (
        <div
          key={key}
          style={{
            background: '#f7fafc',
            borderRadius: 6,
            padding: '8px 10px',
            textAlign: 'center',
          }}
        >
          <div style={{ fontSize: 18, fontWeight: 700, color: '#2d3748' }}>
            {typeof value === 'object' ? JSON.stringify(value) : String(value ?? '—')}
          </div>
          <div style={{ fontSize: 11, color: '#718096', marginTop: 2 }}>
            {key.replace(/_/g, ' ')}
          </div>
        </div>
      ))}
    </div>
  );
}

function WidgetContent({
  widgetType,
  data,
}: {
  widgetType: string;
  data: WidgetData | undefined;
}) {
  if (!data) {
    return <p style={{ color: '#718096', fontSize: 13 }}>No data loaded yet.</p>;
  }

  const payload = data.data;

  if (SUMMARY_TYPES.has(widgetType)) {
    return <SummaryCard data={payload} />;
  }

  // Report / custom: expect { columns, rows, total }
  if (
    payload &&
    typeof payload === 'object' &&
    'rows' in (payload as object) &&
    'columns' in (payload as object)
  ) {
    const { columns, rows } = payload as {
      columns: string[];
      rows: Record<string, unknown>[];
    };
    return <DataTable rows={rows} columns={columns} />;
  }

  // Fallback: raw JSON
  return (
    <pre style={{ fontSize: 11, overflow: 'auto', maxHeight: 200 }}>
      {JSON.stringify(payload, null, 2)}
    </pre>
  );
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

export default function DashboardWidgetCard({
  widget,
  data,
  isLoading,
  onRemove,
  onRefresh,
}: DashboardWidgetProps) {
  return (
    <div
      style={{
        background: '#fff',
        border: '1px solid #e2e8f0',
        borderRadius: 8,
        display: 'flex',
        flexDirection: 'column',
        height: '100%',
        overflow: 'hidden',
        boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
      }}
    >
      {/* Header */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '8px 12px',
          borderBottom: '1px solid #e2e8f0',
          background: '#f7fafc',
          flexShrink: 0,
        }}
      >
        <span
          style={{ fontWeight: 600, fontSize: 13, color: '#2d3748', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
          title={widget.title}
        >
          {widget.title}
        </span>
        <div style={{ display: 'flex', gap: 4, flexShrink: 0 }}>
          <button
            onClick={() => onRefresh(widget.id)}
            title="Refresh widget"
            aria-label="Refresh widget"
            style={{
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              padding: '2px 6px',
              borderRadius: 4,
              color: '#4a90e2',
              fontSize: 14,
            }}
          >
            ↻
          </button>
          <button
            onClick={() => onRemove(widget.id)}
            title="Remove widget"
            aria-label="Remove widget"
            style={{
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              padding: '2px 6px',
              borderRadius: 4,
              color: '#e53e3e',
              fontSize: 14,
            }}
          >
            ✕
          </button>
        </div>
      </div>

      {/* Body */}
      <div style={{ flex: 1, padding: '10px 12px', overflow: 'hidden' }}>
        {isLoading ? (
          <LoadingSpinner />
        ) : (
          <WidgetContent widgetType={widget.widget_type} data={data} />
        )}
      </div>

      {/* Footer: last refreshed */}
      <div
        style={{
          padding: '4px 12px',
          borderTop: '1px solid #e2e8f0',
          fontSize: 11,
          color: '#a0aec0',
          flexShrink: 0,
        }}
      >
        Last refreshed: {formatTimestamp(data?.refreshed_at ?? widget.last_refreshed_at)}
      </div>
    </div>
  );
}
