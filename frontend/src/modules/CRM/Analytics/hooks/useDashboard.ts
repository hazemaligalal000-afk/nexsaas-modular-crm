/**
 * frontend/src/modules/CRM/Analytics/hooks/useDashboard.ts
 *
 * React Query hooks for dashboard data fetching and WebSocket real-time updates.
 *
 * Requirements: 17.6, 17.7
 */

import { useEffect, useRef } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { z } from 'zod';

// ---------------------------------------------------------------------------
// Zod schemas
// ---------------------------------------------------------------------------

const WidgetSchema = z.object({
  id: z.number(),
  dashboard_id: z.number(),
  widget_type: z.enum([
    'report',
    'pipeline_summary',
    'deal_velocity',
    'lead_conversion',
    'activity_summary',
    'revenue_forecast',
    'custom',
  ]),
  report_id: z.number().nullable().optional(),
  title: z.string(),
  config: z.record(z.unknown()).optional().default({}),
  grid_x: z.number(),
  grid_y: z.number(),
  grid_w: z.number(),
  grid_h: z.number(),
  refresh_interval_seconds: z.number(),
  last_refreshed_at: z.string().nullable().optional(),
  created_at: z.string(),
  updated_at: z.string(),
});

const DashboardSchema = z.object({
  id: z.number(),
  name: z.string(),
  owner_id: z.number(),
  layout_config: z.record(z.unknown()).optional().default({}),
  is_default: z.boolean(),
  created_at: z.string(),
  updated_at: z.string(),
  widgets: z.array(WidgetSchema).optional().default([]),
});

const ApiResponseSchema = <T extends z.ZodTypeAny>(dataSchema: T) =>
  z.object({
    success: z.boolean(),
    data: dataSchema.nullable(),
    error: z.string().nullable(),
    meta: z.object({
      company_code: z.string(),
      tenant_id: z.string(),
      user_id: z.string(),
      currency: z.string(),
      fin_period: z.string(),
      timestamp: z.string(),
    }),
  });

const WidgetDataSchema = z.object({
  widget_id: z.number(),
  widget_type: z.string(),
  data: z.unknown(),
  refreshed_at: z.string(),
});

export type Dashboard = z.infer<typeof DashboardSchema>;
export type DashboardWidget = z.infer<typeof WidgetSchema>;
export type WidgetData = z.infer<typeof WidgetDataSchema>;

// ---------------------------------------------------------------------------
// WebSocket message schema
// ---------------------------------------------------------------------------

const WsWidgetUpdateSchema = z.object({
  widget_id: z.number(),
  data: z.unknown(),
  refreshed_at: z.string(),
});

export type WsWidgetUpdate = z.infer<typeof WsWidgetUpdateSchema>;

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------

const API_BASE = '/api/v1';

async function apiFetch<T>(url: string, schema: z.ZodType<T>): Promise<T> {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
  });

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
  }

  const json = await res.json();
  const envelope = ApiResponseSchema(schema).parse(json);

  if (!envelope.success || envelope.data === null) {
    throw new Error(envelope.error ?? 'Unknown API error');
  }

  return envelope.data;
}

// ---------------------------------------------------------------------------
// useDashboard — fetch dashboard + widgets
// ---------------------------------------------------------------------------

/**
 * Fetch a dashboard with all its widgets.
 *
 * Requirement 17.6
 */
export function useDashboard(dashboardId: number) {
  return useQuery({
    queryKey: ['dashboard', dashboardId],
    queryFn: () =>
      apiFetch(
        `${API_BASE}/crm/dashboards/${dashboardId}`,
        DashboardSchema,
      ),
    enabled: dashboardId > 0,
    staleTime: 30_000,
  });
}

/**
 * Fetch all dashboards for the current tenant.
 *
 * Requirement 17.6
 */
export function useDashboardList() {
  return useQuery({
    queryKey: ['dashboards'],
    queryFn: () =>
      apiFetch(
        `${API_BASE}/crm/dashboards`,
        z.array(DashboardSchema.omit({ widgets: true })),
      ),
    staleTime: 60_000,
  });
}

// ---------------------------------------------------------------------------
// useWidgetData — fetch data for a single widget
// ---------------------------------------------------------------------------

/**
 * Fetch the underlying report/analytics data for a widget.
 *
 * Requirement 17.6
 */
export function useWidgetData(dashboardId: number, widgetId: number) {
  return useQuery({
    queryKey: ['widget-data', dashboardId, widgetId],
    queryFn: () =>
      apiFetch(
        `${API_BASE}/crm/dashboards/${dashboardId}/widgets/${widgetId}/data`,
        WidgetDataSchema,
      ),
    enabled: dashboardId > 0 && widgetId > 0,
    staleTime: 60_000,
  });
}

// ---------------------------------------------------------------------------
// useDashboardWebSocket — real-time widget updates via WebSocket
// ---------------------------------------------------------------------------

/**
 * Connect to the WebSocket server and call onWidgetUpdate whenever a widget
 * refresh message arrives for this dashboard.
 *
 * Channel: dashboard:{tenant_id}:{dashboard_id}
 *
 * Requirement 17.7
 */
export function useDashboardWebSocket(
  dashboardId: number,
  tenantId: string,
  onWidgetUpdate: (update: WsWidgetUpdate) => void,
): void {
  const queryClient = useQueryClient();
  const wsRef = useRef<WebSocket | null>(null);
  const onUpdateRef = useRef(onWidgetUpdate);

  // Keep callback ref current without re-connecting
  useEffect(() => {
    onUpdateRef.current = onWidgetUpdate;
  }, [onWidgetUpdate]);

  useEffect(() => {
    if (!dashboardId || !tenantId) return;

    const wsBase =
      (window.location.protocol === 'https:' ? 'wss://' : 'ws://') +
      window.location.host;

    const channel = `dashboard:${tenantId}:${dashboardId}`;
    const url = `${wsBase}/ws?channel=${encodeURIComponent(channel)}`;

    const ws = new WebSocket(url);
    wsRef.current = ws;

    ws.onopen = () => {
      // Subscribe to the dashboard channel
      ws.send(JSON.stringify({ action: 'subscribe', channel }));
    };

    ws.onmessage = (event: MessageEvent) => {
      try {
        const raw = JSON.parse(event.data as string);
        const parsed = WsWidgetUpdateSchema.safeParse(raw);

        if (!parsed.success) return;

        const update = parsed.data;

        // Update React Query cache for this widget's data
        queryClient.setQueryData(
          ['widget-data', dashboardId, update.widget_id],
          (old: WidgetData | undefined): WidgetData => ({
            widget_id: update.widget_id,
            widget_type: (old?.widget_type ?? ''),
            data: update.data,
            refreshed_at: update.refreshed_at,
          }),
        );

        // Call the consumer callback
        onUpdateRef.current(update);
      } catch {
        // Ignore malformed messages
      }
    };

    ws.onerror = (err) => {
      console.error('[DashboardWebSocket] error', err);
    };

    ws.onclose = () => {
      wsRef.current = null;
    };

    return () => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ action: 'unsubscribe', channel }));
        ws.close();
      }
    };
  }, [dashboardId, tenantId, queryClient]);
}
