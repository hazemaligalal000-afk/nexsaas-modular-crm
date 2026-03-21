/**
 * frontend/src/modules/CRM/Analytics/DashboardBuilder.tsx
 *
 * Dashboard builder with configurable 12-column CSS Grid layout.
 * Supports drag-and-drop widget repositioning and real-time WebSocket updates.
 *
 * Requirements: 17.6, 17.7
 */

import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import DashboardWidgetCard from './DashboardWidget';
import {
  useDashboard,
  useDashboardWebSocket,
  useWidgetData,
  type Dashboard,
  type DashboardWidget,
  type WsWidgetUpdate,
} from './hooks/useDashboard';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const GRID_COLS = 12;
const ROW_HEIGHT = 100; // px
const API_BASE = '/api/v1';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface DashboardBuilderProps {
  dashboardId: number;
  tenantId: string;
}

interface DragState {
  widgetId: number;
  startX: number;
  startY: number;
  origGridX: number;
  origGridY: number;
}

interface WidgetDataMap {
  [widgetId: number]: {
    data: unknown;
    refreshed_at: string;
    widget_type: string;
  };
}

// ---------------------------------------------------------------------------
// Add Widget Modal
// ---------------------------------------------------------------------------

const WIDGET_TYPES = [
  { value: 'pipeline_summary', label: 'Pipeline Summary' },
  { value: 'deal_velocity',    label: 'Deal Velocity' },
  { value: 'lead_conversion',  label: 'Lead Conversion' },
  { value: 'activity_summary', label: 'Activity Summary' },
  { value: 'revenue_forecast', label: 'Revenue Forecast' },
  { value: 'report',           label: 'Custom Report' },
];

interface AddWidgetModalProps {
  dashboardId: number;
  onClose: () => void;
  onAdded: () => void;
}

function AddWidgetModal({ dashboardId, onClose, onAdded }: AddWidgetModalProps) {
  const [widgetType, setWidgetType] = useState('pipeline_summary');
  const [title, setTitle] = useState('');
  const [reportId, setReportId] = useState('');
  const [error, setError] = useState('');

  const mutation = useMutation({
    mutationFn: async () => {
      const body: Record<string, unknown> = {
        widget_type: widgetType,
        title: title.trim() || WIDGET_TYPES.find((t) => t.value === widgetType)?.label ?? widgetType,
      };
      if (widgetType === 'report' && reportId) {
        body.report_id = parseInt(reportId, 10);
      }

      const res = await fetch(`${API_BASE}/crm/dashboards/${dashboardId}/widgets`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body),
      });

      const json = await res.json();
      if (!json.success) throw new Error(json.error ?? 'Failed to add widget');
      return json.data;
    },
    onSuccess: () => {
      onAdded();
      onClose();
    },
    onError: (err: Error) => setError(err.message),
  });

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="add-widget-title"
      style={{
        position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.4)',
        display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000,
      }}
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      <div
        style={{
          background: '#fff', borderRadius: 10, padding: 24, width: 400,
          boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
        }}
      >
        <h2 id="add-widget-title" style={{ margin: '0 0 16px', fontSize: 18 }}>
          Add Widget
        </h2>

        {error && (
          <p role="alert" style={{ color: '#e53e3e', fontSize: 13, marginBottom: 12 }}>
            {error}
          </p>
        )}

        <label style={{ display: 'block', marginBottom: 12 }}>
          <span style={{ fontSize: 13, fontWeight: 600 }}>Widget Type</span>
          <select
            value={widgetType}
            onChange={(e) => setWidgetType(e.target.value)}
            style={{ display: 'block', width: '100%', marginTop: 4, padding: '6px 8px', borderRadius: 6, border: '1px solid #cbd5e0' }}
          >
            {WIDGET_TYPES.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        </label>

        <label style={{ display: 'block', marginBottom: 12 }}>
          <span style={{ fontSize: 13, fontWeight: 600 }}>Title (optional)</span>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="Leave blank to use default"
            style={{ display: 'block', width: '100%', marginTop: 4, padding: '6px 8px', borderRadius: 6, border: '1px solid #cbd5e0', boxSizing: 'border-box' }}
          />
        </label>

        {widgetType === 'report' && (
          <label style={{ display: 'block', marginBottom: 12 }}>
            <span style={{ fontSize: 13, fontWeight: 600 }}>Report ID</span>
            <input
              type="number"
              value={reportId}
              onChange={(e) => setReportId(e.target.value)}
              placeholder="Enter saved report ID"
              style={{ display: 'block', width: '100%', marginTop: 4, padding: '6px 8px', borderRadius: 6, border: '1px solid #cbd5e0', boxSizing: 'border-box' }}
            />
          </label>
        )}

        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 16 }}>
          <button
            onClick={onClose}
            style={{ padding: '8px 16px', borderRadius: 6, border: '1px solid #cbd5e0', background: '#fff', cursor: 'pointer' }}
          >
            Cancel
          </button>
          <button
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending}
            style={{ padding: '8px 16px', borderRadius: 6, border: 'none', background: '#4a90e2', color: '#fff', cursor: 'pointer', fontWeight: 600 }}
          >
            {mutation.isPending ? 'Adding…' : 'Add Widget'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Widget wrapper — fetches its own data
// ---------------------------------------------------------------------------

interface WidgetWrapperProps {
  widget: DashboardWidget;
  overrideData?: { data: unknown; refreshed_at: string; widget_type: string };
  onRemove: (id: number) => void;
  onRefresh: (id: number) => void;
}

function WidgetWrapper({ widget, overrideData, onRemove, onRefresh }: WidgetWrapperProps) {
  const { data: fetchedData, isLoading } = useWidgetData(widget.dashboard_id, widget.id);

  const displayData = overrideData
    ? { widget_id: widget.id, widget_type: overrideData.widget_type || widget.widget_type, data: overrideData.data, refreshed_at: overrideData.refreshed_at }
    : fetchedData;

  return (
    <DashboardWidgetCard
      widget={widget}
      data={displayData}
      isLoading={isLoading && !overrideData}
      onRemove={onRemove}
      onRefresh={onRefresh}
    />
  );
}

// ---------------------------------------------------------------------------
// Main DashboardBuilder component
// ---------------------------------------------------------------------------

export default function DashboardBuilder({ dashboardId, tenantId }: DashboardBuilderProps) {
  const queryClient = useQueryClient();
  const { data: dashboard, isLoading, error } = useDashboard(dashboardId);

  const [showAddModal, setShowAddModal] = useState(false);
  const [widgetDataOverrides, setWidgetDataOverrides] = useState<WidgetDataMap>({});

  // Drag state
  const dragRef = useRef<DragState | null>(null);
  const [localWidgets, setLocalWidgets] = useState<DashboardWidget[]>([]);

  // Sync local widgets from query data
  useEffect(() => {
    if (dashboard?.widgets) {
      setLocalWidgets(dashboard.widgets);
    }
  }, [dashboard?.widgets]);

  // WebSocket: update widget data in real time
  const handleWidgetUpdate = useCallback((update: WsWidgetUpdate) => {
    setWidgetDataOverrides((prev) => ({
      ...prev,
      [update.widget_id]: {
        data: update.data,
        refreshed_at: update.refreshed_at,
        widget_type: prev[update.widget_id]?.widget_type ?? '',
      },
    }));
  }, []);

  useDashboardWebSocket(dashboardId, tenantId, handleWidgetUpdate);

  // ---------------------------------------------------------------------------
  // Mutations
  // ---------------------------------------------------------------------------

  const removeWidgetMutation = useMutation({
    mutationFn: async (widgetId: number) => {
      const res = await fetch(
        `${API_BASE}/crm/dashboards/${dashboardId}/widgets/${widgetId}`,
        { method: 'DELETE', credentials: 'include' },
      );
      const json = await res.json();
      if (!json.success) throw new Error(json.error ?? 'Failed to remove widget');
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dashboard', dashboardId] });
    },
  });

  const updateLayoutMutation = useMutation({
    mutationFn: async (widgets: DashboardWidget[]) => {
      const payload = widgets.map((w) => ({
        widget_id: w.id,
        grid_x: w.grid_x,
        grid_y: w.grid_y,
        grid_w: w.grid_w,
        grid_h: w.grid_h,
      }));

      const res = await fetch(`${API_BASE}/crm/dashboards/${dashboardId}/layout`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ widgets: payload }),
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.error ?? 'Failed to update layout');
    },
  });

  const refreshWidgetMutation = useMutation({
    mutationFn: async (widgetId: number) => {
      queryClient.invalidateQueries({ queryKey: ['widget-data', dashboardId, widgetId] });
    },
  });

  // ---------------------------------------------------------------------------
  // Drag handlers (CSS Grid-based, no external library)
  // ---------------------------------------------------------------------------

  const containerRef = useRef<HTMLDivElement>(null);

  const handleMouseDown = useCallback(
    (e: React.MouseEvent, widget: DashboardWidget) => {
      // Only drag on the header (data-drag attribute)
      const target = e.target as HTMLElement;
      if (!target.closest('[data-drag]')) return;

      e.preventDefault();
      dragRef.current = {
        widgetId: widget.id,
        startX: e.clientX,
        startY: e.clientY,
        origGridX: widget.grid_x,
        origGridY: widget.grid_y,
      };
    },
    [],
  );

  const handleMouseMove = useCallback(
    (e: MouseEvent) => {
      if (!dragRef.current || !containerRef.current) return;

      const container = containerRef.current;
      const rect = container.getBoundingClientRect();
      const colWidth = rect.width / GRID_COLS;

      const dx = e.clientX - dragRef.current.startX;
      const dy = e.clientY - dragRef.current.startY;

      const deltaCol = Math.round(dx / colWidth);
      const deltaRow = Math.round(dy / ROW_HEIGHT);

      const newX = Math.max(0, Math.min(GRID_COLS - 1, dragRef.current.origGridX + deltaCol));
      const newY = Math.max(0, dragRef.current.origGridY + deltaRow);

      setLocalWidgets((prev) =>
        prev.map((w) =>
          w.id === dragRef.current!.widgetId
            ? { ...w, grid_x: newX, grid_y: newY }
            : w,
        ),
      );
    },
    [],
  );

  const handleMouseUp = useCallback(() => {
    if (!dragRef.current) return;
    dragRef.current = null;
    // Persist layout after drag ends
    updateLayoutMutation.mutate(localWidgets);
  }, [localWidgets, updateLayoutMutation]);

  useEffect(() => {
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', handleMouseUp);
    return () => {
      window.removeEventListener('mousemove', handleMouseMove);
      window.removeEventListener('mouseup', handleMouseUp);
    };
  }, [handleMouseMove, handleMouseUp]);

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  if (isLoading) {
    return (
      <div style={{ padding: 32, textAlign: 'center', color: '#718096' }}>
        Loading dashboard…
      </div>
    );
  }

  if (error || !dashboard) {
    return (
      <div role="alert" style={{ padding: 32, color: '#e53e3e' }}>
        Failed to load dashboard: {(error as Error)?.message ?? 'Unknown error'}
      </div>
    );
  }

  // Compute grid height from max widget bottom edge
  const maxRow = localWidgets.reduce(
    (max, w) => Math.max(max, w.grid_y + w.grid_h),
    4,
  );

  return (
    <div style={{ fontFamily: 'sans-serif' }}>
      {/* Toolbar */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginBottom: 16,
          padding: '0 4px',
        }}
      >
        <h1 style={{ margin: 0, fontSize: 20, fontWeight: 700, color: '#2d3748' }}>
          {dashboard.name}
        </h1>
        <button
          onClick={() => setShowAddModal(true)}
          style={{
            padding: '8px 16px',
            background: '#4a90e2',
            color: '#fff',
            border: 'none',
            borderRadius: 6,
            cursor: 'pointer',
            fontWeight: 600,
            fontSize: 14,
          }}
        >
          + Add Widget
        </button>
      </div>

      {/* Grid */}
      <div
        ref={containerRef}
        style={{
          display: 'grid',
          gridTemplateColumns: `repeat(${GRID_COLS}, 1fr)`,
          gridAutoRows: `${ROW_HEIGHT}px`,
          gap: 8,
          minHeight: maxRow * ROW_HEIGHT + (maxRow - 1) * 8,
          position: 'relative',
          background: '#f0f4f8',
          borderRadius: 8,
          padding: 8,
        }}
      >
        {localWidgets.map((widget) => (
          <div
            key={widget.id}
            onMouseDown={(e) => handleMouseDown(e, widget)}
            style={{
              gridColumn: `${widget.grid_x + 1} / span ${widget.grid_w}`,
              gridRow: `${widget.grid_y + 1} / span ${widget.grid_h}`,
              cursor: 'default',
              userSelect: 'none',
            }}
          >
            {/* Drag handle overlay on header */}
            <div style={{ position: 'relative', height: '100%' }}>
              <div
                data-drag="true"
                style={{
                  position: 'absolute',
                  top: 0,
                  left: 0,
                  right: 40, // leave room for action buttons
                  height: 36,
                  cursor: 'grab',
                  zIndex: 1,
                }}
                aria-label="Drag to reposition widget"
              />
              <WidgetWrapper
                widget={widget}
                overrideData={widgetDataOverrides[widget.id]}
                onRemove={(id) => removeWidgetMutation.mutate(id)}
                onRefresh={(id) => refreshWidgetMutation.mutate(id)}
              />
            </div>
          </div>
        ))}

        {localWidgets.length === 0 && (
          <div
            style={{
              gridColumn: `1 / span ${GRID_COLS}`,
              gridRow: '1 / span 3',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: '#a0aec0',
              fontSize: 15,
              border: '2px dashed #cbd5e0',
              borderRadius: 8,
            }}
          >
            No widgets yet. Click "Add Widget" to get started.
          </div>
        )}
      </div>

      {/* Add Widget Modal */}
      {showAddModal && (
        <AddWidgetModal
          dashboardId={dashboardId}
          onClose={() => setShowAddModal(false)}
          onAdded={() =>
            queryClient.invalidateQueries({ queryKey: ['dashboard', dashboardId] })
          }
        />
      )}
    </div>
  );
}
