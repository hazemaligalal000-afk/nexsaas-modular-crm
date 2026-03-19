"""
cron/tasks/calendar_sync.py

CalendarSyncWorker — Celery tasks for two-way Google Calendar and Outlook sync.

Behaviour:
  - push_calendar_event(activity_id): called when a meeting activity is created/updated.
    Fetches the activity, gets the user's calendar connections, pushes to Google/Outlook.
    Must complete within 30 seconds (Requirement 16.3).

  - pull_calendar_changes(): periodic task (every 60 seconds).
    Iterates all active calendar connections, fetches delta changes,
    updates local activities via internal PHP API (Requirement 16.4).

  - sync_calendar_for_user(user_id): manual sync trigger for a single user.

Requirements: 16.2, 16.3, 16.4
"""

from __future__ import annotations

import json
import logging
import os
from datetime import datetime, timezone
from typing import Any

import psycopg2
import psycopg2.extras
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
REDIS_URL: str = os.environ.get("REDIS_URL", "redis://redis:6379/0")
PHP_API_BASE: str = os.environ.get("PHP_API_BASE", "http://php-fpm/api/v1")
INTERNAL_API_KEY: str = os.environ.get("INTERNAL_API_KEY", "")

# Push SLA: 30 seconds (Requirement 16.3)
PUSH_TIMEOUT: int = 25

# Pull interval: 60 seconds (Requirement 16.4)
PULL_INTERVAL: int = 60

# ---------------------------------------------------------------------------
# Celery application
# ---------------------------------------------------------------------------

app = Celery("calendar_sync", broker=BROKER_URL)

app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    beat_schedule={
        "calendar-pull-every-60s": {
            "task": "calendar_sync.pull_calendar_changes",
            "schedule": PULL_INTERVAL,  # Requirement 16.4: reflect changes within 60s
        },
    },
)


# ---------------------------------------------------------------------------
# CalendarSyncWorker
# ---------------------------------------------------------------------------


class CalendarSyncWorker:
    """
    Encapsulates all calendar sync logic.
    Separating from Celery decorators makes unit testing straightforward.
    """

    def __init__(self, db_dsn: str = DB_DSN) -> None:
        self._db_dsn = db_dsn

    # ------------------------------------------------------------------
    # Push: activity → external calendar (Requirement 16.3)
    # ------------------------------------------------------------------

    def push_event(self, activity_id: int) -> dict[str, Any]:
        """
        Push a meeting activity to all connected external calendars for its owner.

        Must complete within 30 seconds (Requirement 16.3).
        """
        logger.info("CalendarSyncWorker.push_event: activity_id=%d", activity_id)

        activity = self._fetch_activity(activity_id)
        if activity is None:
            logger.warning("push_event: activity %d not found", activity_id)
            return {"success": False, "error": f"Activity {activity_id} not found"}

        if activity.get("type") != "meeting":
            logger.debug("push_event: activity %d is not a meeting, skipping", activity_id)
            return {"success": True, "skipped": True, "reason": "not a meeting"}

        user_id = activity.get("performed_by") or activity.get("created_by")
        if not user_id:
            return {"success": False, "error": "Activity has no owner"}

        connections = self._fetch_active_connections(user_id)
        if not connections:
            logger.debug("push_event: user %d has no active calendar connections", user_id)
            return {"success": True, "skipped": True, "reason": "no connections"}

        results = []
        for conn in connections:
            result = self._push_to_provider(activity, conn)
            results.append(result)

        return {"success": True, "activity_id": activity_id, "results": results}

    def _push_to_provider(self, activity: dict, conn: dict) -> dict[str, Any]:
        """Push a single activity to a single provider connection via PHP API."""
        provider = conn["provider"]
        conn_id  = conn["id"]

        url = f"{PHP_API_BASE}/crm/calendar/internal/push"
        headers = {
            "Content-Type": "application/json",
            "X-Tenant-Id": conn["tenant_id"],
            "X-Internal-Key": INTERNAL_API_KEY,
        }
        payload = {
            "activity_id":   activity["id"],
            "connection_id": conn_id,
            "provider":      provider,
        }

        try:
            resp = requests.post(url, json=payload, headers=headers, timeout=PUSH_TIMEOUT)
            body = resp.json() if resp.content else {}
            if resp.status_code == 200 and body.get("success"):
                logger.info(
                    "push_event: activity=%d provider=%s connection=%d → success",
                    activity["id"], provider, conn_id,
                )
                return {"provider": provider, "connection_id": conn_id, "success": True}
            else:
                error = body.get("error") or f"HTTP {resp.status_code}"
                logger.warning(
                    "push_event: activity=%d provider=%s → %s",
                    activity["id"], provider, error,
                )
                self._log_sync(
                    conn["tenant_id"], conn["company_code"], conn["user_id"],
                    activity["id"], provider, None, "push", "failed", error,
                )
                return {"provider": provider, "connection_id": conn_id, "success": False, "error": error}
        except requests.RequestException as exc:
            error = str(exc)
            logger.error("push_event: request failed for connection %d — %s", conn_id, error)
            self._log_sync(
                conn["tenant_id"], conn["company_code"], conn["user_id"],
                activity["id"], provider, None, "push", "failed", error,
            )
            return {"provider": provider, "connection_id": conn_id, "success": False, "error": error}

    # ------------------------------------------------------------------
    # Pull: external calendar → activities (Requirement 16.4)
    # ------------------------------------------------------------------

    def pull_all(self) -> dict[str, int]:
        """
        Fetch delta changes from all active calendar connections and update
        local activities via the PHP internal API.

        Runs every 60 seconds (Requirement 16.4).
        """
        connections = self._fetch_all_active_connections()
        stats = {"total": len(connections), "success": 0, "failed": 0}

        for conn in connections:
            try:
                self._pull_for_connection(conn)
                stats["success"] += 1
            except Exception as exc:
                stats["failed"] += 1
                logger.error(
                    "pull_all: connection=%d provider=%s failed — %s",
                    conn["id"], conn["provider"], exc,
                )

        logger.info(
            "pull_all: total=%d success=%d failed=%d",
            stats["total"], stats["success"], stats["failed"],
        )
        return stats

    def pull_for_user(self, user_id: int) -> dict[str, Any]:
        """Pull changes for a specific user's connections."""
        connections = self._fetch_active_connections(user_id)
        results = []

        for conn in connections:
            try:
                result = self._pull_for_connection(conn)
                results.append({"connection_id": conn["id"], "success": True, "result": result})
            except Exception as exc:
                results.append({"connection_id": conn["id"], "success": False, "error": str(exc)})

        return {"user_id": user_id, "results": results}

    def _pull_for_connection(self, conn: dict) -> dict[str, Any]:
        """Pull delta changes for a single connection via PHP API."""
        url = f"{PHP_API_BASE}/crm/calendar/internal/pull"
        headers = {
            "Content-Type": "application/json",
            "X-Tenant-Id": conn["tenant_id"],
            "X-Internal-Key": INTERNAL_API_KEY,
        }
        payload = {
            "connection_id": conn["id"],
            "provider":      conn["provider"],
            "user_id":       conn["user_id"],
        }

        resp = requests.post(url, json=payload, headers=headers, timeout=55)
        body = resp.json() if resp.content else {}

        if resp.status_code == 200 and body.get("success"):
            self._log_sync(
                conn["tenant_id"], conn["company_code"], conn["user_id"],
                None, conn["provider"], None, "pull", "success", None,
            )
            return body.get("data", {})
        else:
            error = body.get("error") or f"HTTP {resp.status_code}"
            self._log_sync(
                conn["tenant_id"], conn["company_code"], conn["user_id"],
                None, conn["provider"], None, "pull", "failed", error,
            )
            raise RuntimeError(f"Pull failed for connection {conn['id']}: {error}")

    # ------------------------------------------------------------------
    # DB helpers
    # ------------------------------------------------------------------

    def _fetch_activity(self, activity_id: int) -> dict[str, Any] | None:
        sql = """
            SELECT id, tenant_id, company_code, type, subject, body, duration_minutes,
                   activity_date, performed_by, created_by, external_event_id,
                   external_calendar_provider
            FROM activities
            WHERE id = %s AND deleted_at IS NULL
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql, (activity_id,))
                row = cur.fetchone()
                return dict(row) if row else None

    def _fetch_active_connections(self, user_id: int) -> list[dict[str, Any]]:
        sql = """
            SELECT id, tenant_id, company_code, user_id, provider,
                   access_token, refresh_token, token_expires_at,
                   sync_token, delta_link
            FROM calendar_connections
            WHERE user_id = %s AND is_active = TRUE AND deleted_at IS NULL
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql, (user_id,))
                return [dict(row) for row in cur.fetchall()]

    def _fetch_all_active_connections(self) -> list[dict[str, Any]]:
        sql = """
            SELECT id, tenant_id, company_code, user_id, provider,
                   access_token, refresh_token, token_expires_at,
                   sync_token, delta_link
            FROM calendar_connections
            WHERE is_active = TRUE AND deleted_at IS NULL
            ORDER BY id ASC
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql)
                return [dict(row) for row in cur.fetchall()]

    def _log_sync(
        self,
        tenant_id: str,
        company_code: str,
        user_id: int,
        activity_id: int | None,
        provider: str,
        external_event_id: str | None,
        direction: str,
        status: str,
        error_message: str | None,
    ) -> None:
        """Insert a row into calendar_sync_log for debugging."""
        sql = """
            INSERT INTO calendar_sync_log
                (tenant_id, company_code, user_id, activity_id, provider,
                 external_event_id, sync_direction, status, error_message, synced_at, created_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """
        try:
            with self._connect() as conn:
                conn.autocommit = True
                with conn.cursor() as cur:
                    cur.execute(sql, (
                        tenant_id, company_code, user_id, activity_id, provider,
                        external_event_id, direction, status,
                        (error_message or "")[:2000] if error_message else None,
                    ))
        except Exception as exc:
            logger.warning("_log_sync: failed to write sync log — %s", exc)

    def _connect(self):
        return psycopg2.connect(self._db_dsn)


# ---------------------------------------------------------------------------
# Module-level worker instance
# ---------------------------------------------------------------------------

_worker = CalendarSyncWorker()


# ---------------------------------------------------------------------------
# Celery tasks
# ---------------------------------------------------------------------------


@app.task(
    name="calendar_sync.push_calendar_event",
    bind=True,
    max_retries=3,
    default_retry_delay=5,
    time_limit=30,  # Requirement 16.3: must complete within 30s
    soft_time_limit=25,
)
def push_calendar_event(self, activity_id: int) -> dict[str, Any]:
    """
    Push a meeting activity to connected external calendars.

    Called immediately when a meeting activity is created or updated.
    Must complete within 30 seconds (Requirement 16.3).
    """
    try:
        return _worker.push_event(activity_id)
    except Exception as exc:
        logger.exception("push_calendar_event: activity=%d failed — %s", activity_id, exc)
        if self.request.retries < 3:
            raise self.retry(exc=exc, countdown=5)
        return {"success": False, "error": str(exc), "activity_id": activity_id}


@app.task(
    name="calendar_sync.pull_calendar_changes",
    bind=True,
    max_retries=2,
    default_retry_delay=10,
)
def pull_calendar_changes(self) -> dict[str, int]:
    """
    Beat task: pull delta changes from all active calendar connections.

    Runs every 60 seconds (Requirement 16.4).
    """
    try:
        return _worker.pull_all()
    except Exception as exc:
        logger.exception("pull_calendar_changes: failed — %s", exc)
        raise self.retry(exc=exc, countdown=10)


@app.task(
    name="calendar_sync.sync_calendar_for_user",
    bind=True,
    max_retries=2,
    default_retry_delay=10,
)
def sync_calendar_for_user(self, user_id: int) -> dict[str, Any]:
    """
    Manual sync trigger for a single user: pull external changes.
    """
    try:
        return _worker.pull_for_user(user_id)
    except Exception as exc:
        logger.exception("sync_calendar_for_user: user=%d failed — %s", user_id, exc)
        if self.request.retries < 2:
            raise self.retry(exc=exc, countdown=10)
        return {"success": False, "error": str(exc), "user_id": user_id}
