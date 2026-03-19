"""
cron/tasks/dashboard_refresh.py

DashboardRefresh — Celery beat task that runs every 5 minutes.

Behaviour:
  - Queries all active dashboard_widgets where refresh_interval_seconds has
    elapsed since last_refreshed_at (or last_refreshed_at IS NULL).
  - For each due widget, calls the PHP widget-data endpoint to fetch fresh data.
  - Publishes the result to Redis pub/sub channel:
      dashboard:{tenant_id}:{dashboard_id}
    with payload: { widget_id, data, refreshed_at }
  - The WebSocket server subscribes to these channels and pushes updates to
    connected clients in real time.

Requirements: 17.7
"""

from __future__ import annotations

import json
import logging
import os
from datetime import datetime, timezone
from typing import Any

import psycopg2
import psycopg2.extras
import redis
import requests
from celery import Celery
from celery.utils.log import get_task_logger

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

logger: logging.Logger = get_task_logger(__name__)

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

BROKER_URL: str = os.environ.get("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672//")
DB_DSN: str = os.environ.get("DATABASE_URL", "postgresql://nexsaas:nexsaas@postgres:5432/nexsaas")
PHP_API_BASE: str = os.environ.get("PHP_API_BASE", "http://php-fpm/api/v1")
INTERNAL_API_KEY: str = os.environ.get("INTERNAL_API_KEY", "")
REDIS_URL: str = os.environ.get("REDIS_URL", "redis://redis:6379/0")

# ---------------------------------------------------------------------------
# Celery application
# ---------------------------------------------------------------------------

app = Celery("dashboard_refresh", broker=BROKER_URL)

app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    beat_schedule={
        "dashboard-refresh-every-5-minutes": {
            "task": "dashboard_refresh.refresh_dashboard_widgets",
            "schedule": 300,  # every 5 minutes
        },
    },
)


# ---------------------------------------------------------------------------
# DashboardRefreshWorker
# ---------------------------------------------------------------------------


class DashboardRefreshWorker:
    """
    Encapsulates all dashboard widget refresh logic.
    Separating from Celery decorators makes unit testing straightforward.
    """

    def __init__(self, db_dsn: str = DB_DSN, redis_url: str = REDIS_URL) -> None:
        self._db_dsn = db_dsn
        self._redis_url = redis_url
        self._redis_client: redis.Redis | None = None

    # ------------------------------------------------------------------
    # Main entry point
    # ------------------------------------------------------------------

    def run(self) -> dict[str, int]:
        """
        Find all widgets due for refresh and process each one.

        Returns a summary: {total, success, failed}.
        """
        widgets = self._fetch_due_widgets()
        stats = {"total": len(widgets), "success": 0, "failed": 0}

        logger.info("dashboard_refresh: found %d widgets due for refresh", len(widgets))

        for widget in widgets:
            widget_id = widget["id"]
            try:
                self._process_widget(widget)
                stats["success"] += 1
                logger.info(
                    "dashboard_refresh: widget %d refreshed successfully", widget_id
                )
            except Exception as exc:
                stats["failed"] += 1
                logger.error(
                    "dashboard_refresh: widget %d failed — %s",
                    widget_id,
                    exc,
                    exc_info=True,
                )

        logger.info(
            "dashboard_refresh: done — total=%d success=%d failed=%d",
            stats["total"],
            stats["success"],
            stats["failed"],
        )
        return stats

    # ------------------------------------------------------------------
    # Widget processing
    # ------------------------------------------------------------------

    def _process_widget(self, widget: dict[str, Any]) -> None:
        """
        Fetch fresh data for a widget and publish to Redis pub/sub.

        Requirement 17.7: real-time widget refresh via WebSocket.
        """
        widget_id    = widget["id"]
        dashboard_id = widget["dashboard_id"]
        tenant_id    = widget["tenant_id"]

        # Fetch fresh data via PHP internal API
        data = self._fetch_widget_data(widget_id, tenant_id)

        refreshed_at = datetime.now(timezone.utc).isoformat()

        # Publish to Redis pub/sub channel for WebSocket server
        channel = f"dashboard:{tenant_id}:{dashboard_id}"
        payload = json.dumps({
            "widget_id":    widget_id,
            "data":         data,
            "refreshed_at": refreshed_at,
        })

        redis_client = self._get_redis()
        redis_client.publish(channel, payload)

        logger.debug(
            "dashboard_refresh: published widget %d update to channel %s",
            widget_id,
            channel,
        )

        # Mark widget as refreshed in DB
        self._mark_refreshed(widget_id, refreshed_at)

    # ------------------------------------------------------------------
    # PHP API call
    # ------------------------------------------------------------------

    def _fetch_widget_data(self, widget_id: int, tenant_id: str) -> Any:
        """
        Call the PHP widget-data endpoint and return the data payload.
        """
        # We need the dashboard_id for the URL; fetch it from the widget record
        widget = self._fetch_widget(widget_id)
        dashboard_id = widget["dashboard_id"]

        endpoint = (
            f"{PHP_API_BASE}/crm/dashboards/{dashboard_id}/widgets/{widget_id}/data"
        )
        headers = {
            "X-Tenant-Id":    tenant_id,
            "X-Internal-Key": INTERNAL_API_KEY,
        }

        resp = requests.get(endpoint, headers=headers, timeout=30)

        if resp.status_code != 200:
            raise RuntimeError(
                f"Widget data endpoint returned HTTP {resp.status_code} "
                f"for widget {widget_id}"
            )

        body = resp.json()
        if not body.get("success"):
            raise RuntimeError(
                f"Widget data fetch failed for widget {widget_id}: "
                f"{body.get('error', 'unknown error')}"
            )

        return body.get("data", {}).get("data", {})

    # ------------------------------------------------------------------
    # DB helpers
    # ------------------------------------------------------------------

    def _fetch_due_widgets(self) -> list[dict[str, Any]]:
        """
        Return all active widgets whose refresh interval has elapsed.

        A widget is due when:
          last_refreshed_at IS NULL
          OR NOW() >= last_refreshed_at + refresh_interval_seconds * INTERVAL '1 second'
        """
        sql = """
            SELECT
                dw.id,
                dw.dashboard_id,
                dw.tenant_id,
                dw.company_code,
                dw.widget_type,
                dw.refresh_interval_seconds,
                dw.last_refreshed_at
            FROM dashboard_widgets dw
            JOIN dashboards d
                ON d.id           = dw.dashboard_id
               AND d.deleted_at   IS NULL
            WHERE dw.deleted_at IS NULL
              AND (
                  dw.last_refreshed_at IS NULL
                  OR NOW() >= dw.last_refreshed_at
                      + (dw.refresh_interval_seconds || ' seconds')::INTERVAL
              )
            ORDER BY dw.last_refreshed_at ASC NULLS FIRST
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql)
                return [dict(row) for row in cur.fetchall()]

    def _fetch_widget(self, widget_id: int) -> dict[str, Any]:
        """Fetch a single widget row by ID."""
        sql = """
            SELECT id, dashboard_id, tenant_id, company_code
            FROM dashboard_widgets
            WHERE id = %s AND deleted_at IS NULL
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql, (widget_id,))
                row = cur.fetchone()
                if row is None:
                    raise RuntimeError(f"Widget {widget_id} not found.")
                return dict(row)

    def _mark_refreshed(self, widget_id: int, refreshed_at: str) -> None:
        """Update last_refreshed_at for a widget after successful refresh."""
        sql = """
            UPDATE dashboard_widgets
            SET last_refreshed_at = %s,
                updated_at        = NOW()
            WHERE id = %s
        """
        with self._connect() as conn:
            conn.autocommit = True
            with conn.cursor() as cur:
                cur.execute(sql, (refreshed_at, widget_id))

    def _connect(self):
        """Return a new psycopg2 connection."""
        return psycopg2.connect(self._db_dsn)

    def _get_redis(self) -> redis.Redis:
        """Return a cached Redis client."""
        if self._redis_client is None:
            self._redis_client = redis.from_url(self._redis_url, decode_responses=True)
        return self._redis_client


# ---------------------------------------------------------------------------
# Module-level worker instance
# ---------------------------------------------------------------------------

_worker = DashboardRefreshWorker()


# ---------------------------------------------------------------------------
# Celery task
# ---------------------------------------------------------------------------


@app.task(
    name="dashboard_refresh.refresh_dashboard_widgets",
    bind=True,
    max_retries=3,
    default_retry_delay=60,
)
def refresh_dashboard_widgets(self) -> dict[str, int]:
    """
    Beat task running every 5 minutes: refresh all due dashboard widgets.

    For each widget whose refresh_interval_seconds has elapsed:
      1. Fetches fresh data via the PHP internal API
      2. Publishes to Redis pub/sub channel dashboard:{tenant_id}:{dashboard_id}
      3. WebSocket server pushes the update to subscribed clients

    Requirement 17.7: real-time dashboard widgets via WebSocket.
    """
    try:
        return _worker.run()
    except Exception as exc:
        logger.exception("refresh_dashboard_widgets: unhandled error — %s", exc)
        raise self.retry(exc=exc, countdown=60)
