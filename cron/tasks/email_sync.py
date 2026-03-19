"""
cron/tasks/email_sync.py

EmailSyncWorker — Celery task for syncing connected Gmail and Microsoft 365 mailboxes.

Behaviour:
  - Runs every 60 seconds via Celery Beat (Requirement 13.2)
  - Queries connected_mailboxes where sync_status = 'active' and
    last_sync_at < NOW() - INTERVAL '60 seconds' (or never synced)
  - Calls PHP EmailSyncService via internal HTTP API for each mailbox
  - On failure (Requirement 13.5):
      * Updates connected_mailboxes.sync_status = 'error', last_error = <message>
      * Inserts a notification row for the mailbox owner (falls back to Redis
        pub/sub if the notifications table is not yet available)
      * Logs structured retry details to the email_sync_error_log table
  - Per-mailbox retry: exponential backoff (30 s → 60 s → 120 s), max 3 retries

Requirements: 13.2, 13.5
"""

from __future__ import annotations

import json
import logging
import os
from datetime import datetime, timezone
from typing import Any

import psycopg2
import psycopg2.extras
import redis as redis_lib
import requests
from celery import Celery
from celery.utils.log import get_task_logger

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

logger: logging.Logger = get_task_logger(__name__)

# ---------------------------------------------------------------------------
# Configuration (all overridable via environment variables)
# ---------------------------------------------------------------------------

BROKER_URL: str = os.environ.get("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672//")
DB_DSN: str = os.environ.get("DATABASE_URL", "postgresql://nexsaas:nexsaas@postgres:5432/nexsaas")
REDIS_URL: str = os.environ.get("REDIS_URL", "redis://redis:6379/0")
PHP_API_BASE: str = os.environ.get("PHP_API_BASE", "http://php-fpm/api/v1")
INTERNAL_API_KEY: str = os.environ.get("INTERNAL_API_KEY", "")

# Retry schedule (seconds): attempt 1 → 30 s, attempt 2 → 60 s, attempt 3 → 120 s
RETRY_DELAYS: list[int] = [30, 60, 120]
MAX_RETRIES: int = len(RETRY_DELAYS)

# ---------------------------------------------------------------------------
# Celery application
# ---------------------------------------------------------------------------

app = Celery("email_sync", broker=BROKER_URL)

app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    beat_schedule={
        "email-sync-every-60s": {
            "task": "email_sync.sync_all_mailboxes",
            "schedule": 60,  # Requirement 13.2: sync within 60 s
        },
    },
)


# ---------------------------------------------------------------------------
# EmailSyncWorker — encapsulates all sync logic
# ---------------------------------------------------------------------------


class EmailSyncWorker:
    """
    Stateless worker class that handles mailbox sync orchestration.

    Separating logic from Celery task decorators makes unit testing straightforward.
    """

    def __init__(self, db_dsn: str = DB_DSN, redis_url: str = REDIS_URL) -> None:
        self._db_dsn = db_dsn
        self._redis_url = redis_url

    # ------------------------------------------------------------------
    # Public API
    # ------------------------------------------------------------------

    def run_all(self) -> dict[str, int]:
        """
        Fetch all due mailboxes and dispatch per-mailbox sync tasks.

        Returns a summary dict: {total, dispatched, fetch_error}.
        """
        try:
            mailboxes = self._fetch_due_mailboxes()
        except Exception as exc:
            logger.exception("EmailSyncWorker.run_all: failed to fetch mailboxes — %s", exc)
            raise  # let Celery retry the beat task

        stats: dict[str, int] = {"total": len(mailboxes), "dispatched": 0, "fetch_error": 0}

        for mailbox in mailboxes:
            try:
                sync_single_mailbox.apply_async(
                    kwargs={
                        "mailbox_id": mailbox["id"],
                        "tenant_id": mailbox["tenant_id"],
                        "user_id": mailbox["user_id"],
                    },
                    countdown=0,
                )
                stats["dispatched"] += 1
            except Exception as exc:
                stats["fetch_error"] += 1
                logger.error(
                    "EmailSyncWorker.run_all: failed to dispatch mailbox %d — %s",
                    mailbox["id"],
                    exc,
                )

        logger.info(
            "EmailSyncWorker.run_all: total=%d dispatched=%d errors=%d",
            stats["total"],
            stats["dispatched"],
            stats["fetch_error"],
        )
        return stats

    def sync_one(self, mailbox_id: int, tenant_id: str, user_id: int, attempt: int) -> dict[str, Any]:
        """
        Sync a single mailbox via the PHP internal API.

        On failure, records the error and notifies the owner.
        Returns the API result dict.
        """
        logger.info(
            "EmailSyncWorker.sync_one: mailbox=%d tenant=%s attempt=%d",
            mailbox_id,
            tenant_id,
            attempt,
        )

        result = self._call_sync_api(mailbox_id, tenant_id)

        if result["success"]:
            synced_count = result.get("data", {}).get("synced", 0)
            logger.info(
                "EmailSyncWorker.sync_one: mailbox=%d synced=%d",
                mailbox_id,
                synced_count,
            )
            return result

        error_msg: str = result.get("error") or "Unknown sync error"
        logger.warning(
            "EmailSyncWorker.sync_one: mailbox=%d attempt=%d failed — %s",
            mailbox_id,
            attempt,
            error_msg,
        )

        # Log the retry attempt before deciding whether to raise
        self._log_retry(mailbox_id, tenant_id, attempt, error_msg)

        # On final attempt, persist error state and notify owner
        if attempt >= MAX_RETRIES:
            self._handle_final_failure(mailbox_id, user_id, tenant_id, error_msg)

        # Signal failure so Celery can retry
        raise RuntimeError(error_msg)

    # ------------------------------------------------------------------
    # DB helpers
    # ------------------------------------------------------------------

    def _fetch_due_mailboxes(self) -> list[dict[str, Any]]:
        """
        Return active mailboxes whose last_sync_at is NULL or > 60 s ago.
        """
        sql = """
            SELECT id, tenant_id, company_code, user_id, provider, email_address
            FROM   connected_mailboxes
            WHERE  sync_status = 'active'
              AND  deleted_at  IS NULL
              AND  (
                      last_sync_at IS NULL
                      OR last_sync_at < NOW() - INTERVAL '60 seconds'
                   )
            ORDER BY last_sync_at ASC NULLS FIRST
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql)
                return [dict(row) for row in cur.fetchall()]

    def _log_retry(
        self,
        mailbox_id: int,
        tenant_id: str,
        attempt: int,
        error_msg: str,
    ) -> None:
        """
        Append a row to email_sync_error_log for structured retry tracking.

        Creates the table on first use if it doesn't exist (idempotent DDL).
        Requirement 13.5: log error with retry details.
        """
        create_sql = """
            CREATE TABLE IF NOT EXISTS email_sync_error_log (
                id          BIGSERIAL PRIMARY KEY,
                tenant_id   UUID         NOT NULL,
                mailbox_id  BIGINT       NOT NULL,
                attempt     SMALLINT     NOT NULL,
                error_msg   TEXT         NOT NULL,
                will_retry  BOOLEAN      NOT NULL,
                next_retry_in_seconds INT,
                logged_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
        """
        will_retry = attempt < MAX_RETRIES
        next_delay = RETRY_DELAYS[attempt] if will_retry else None

        insert_sql = """
            INSERT INTO email_sync_error_log
                (tenant_id, mailbox_id, attempt, error_msg, will_retry, next_retry_in_seconds)
            VALUES (%s, %s, %s, %s, %s, %s)
        """
        try:
            with self._connect() as conn:
                conn.autocommit = True
                with conn.cursor() as cur:
                    cur.execute(create_sql)
                    cur.execute(
                        insert_sql,
                        (tenant_id, mailbox_id, attempt, error_msg[:2000], will_retry, next_delay),
                    )
        except Exception as exc:
            # Non-fatal — log to stderr but don't block the retry flow
            logger.warning(
                "EmailSyncWorker._log_retry: could not write to error log — %s", exc
            )

    def _handle_final_failure(
        self,
        mailbox_id: int,
        user_id: int,
        tenant_id: str,
        error_msg: str,
    ) -> None:
        """
        After all retries are exhausted (Requirement 13.5):
          1. Mark connected_mailboxes.sync_status = 'error'
          2. Notify the mailbox owner (DB row + Redis pub/sub fallback)
        """
        try:
            with self._connect() as conn:
                with conn.cursor() as cur:
                    # 1. Mark mailbox as error
                    cur.execute(
                        """
                        UPDATE connected_mailboxes
                        SET    sync_status = 'error',
                               last_error  = %s,
                               updated_at  = NOW()
                        WHERE  id = %s
                        """,
                        (error_msg[:2000], mailbox_id),
                    )

                    # 2a. Try inserting into notifications table
                    notification_inserted = self._try_insert_notification(
                        cur, tenant_id, user_id, mailbox_id, error_msg
                    )

                conn.commit()

            # 2b. Fallback: Redis pub/sub if DB notification wasn't possible
            if not notification_inserted:
                self._publish_redis_notification(tenant_id, user_id, mailbox_id, error_msg)

        except Exception as exc:
            logger.exception(
                "EmailSyncWorker._handle_final_failure: DB update failed for mailbox %d — %s",
                mailbox_id,
                exc,
            )

    def _try_insert_notification(
        self,
        cur: Any,
        tenant_id: str,
        user_id: int,
        mailbox_id: int,
        error_msg: str,
    ) -> bool:
        """
        Insert a notification row. Returns True on success, False if the
        notifications table doesn't exist yet (task 43.2 not yet executed).
        """
        payload = json.dumps(
            {
                "type": "email_sync_error",
                "mailbox_id": mailbox_id,
                "error": error_msg[:500],
            }
        )
        try:
            cur.execute(
                """
                INSERT INTO notifications
                    (tenant_id, company_code, user_id, type, payload,
                     is_read, created_at, updated_at)
                VALUES
                    (%s, '01', %s, 'email_sync_error', %s::jsonb,
                     false, NOW(), NOW())
                """,
                (tenant_id, user_id, payload),
            )
            return True
        except psycopg2.errors.UndefinedTable:
            # notifications table not yet migrated — use Redis fallback
            logger.debug(
                "EmailSyncWorker: notifications table not found, using Redis fallback"
            )
            return False

    def _publish_redis_notification(
        self,
        tenant_id: str,
        user_id: int,
        mailbox_id: int,
        error_msg: str,
    ) -> None:
        """
        Publish a notification to Redis pub/sub channel and pending list.

        Channel: tenant:{tenant_id}:user:{user_id}  (matches WebSocket design)
        List key: notifications:pending:{user_id}    (flushed on WS reconnect)
        """
        payload = json.dumps(
            {
                "type": "email_sync_error",
                "mailbox_id": mailbox_id,
                "error": error_msg[:500],
                "timestamp": datetime.now(timezone.utc).isoformat(),
            }
        )
        try:
            r = redis_lib.from_url(self._redis_url, decode_responses=True)
            channel = f"tenant:{tenant_id}:user:{user_id}"
            r.publish(channel, payload)
            # Also push to pending list so offline users receive it on reconnect
            r.lpush(f"notifications:pending:{user_id}", payload)
            logger.info(
                "EmailSyncWorker: Redis notification published for user %d mailbox %d",
                user_id,
                mailbox_id,
            )
        except Exception as exc:
            logger.error(
                "EmailSyncWorker._publish_redis_notification: failed — %s", exc
            )

    # ------------------------------------------------------------------
    # PHP API call
    # ------------------------------------------------------------------

    def _call_sync_api(self, mailbox_id: int, tenant_id: str) -> dict[str, Any]:
        """
        POST /api/v1/crm/email/mailboxes/{id}/sync via internal PHP API.
        """
        url = f"{PHP_API_BASE}/crm/email/mailboxes/{mailbox_id}/sync"
        headers = {
            "Content-Type": "application/json",
            "X-Tenant-Id": tenant_id,
            "X-Internal-Key": INTERNAL_API_KEY,
        }
        try:
            resp = requests.post(url, headers=headers, timeout=55)
        except requests.RequestException as exc:
            return {"success": False, "error": str(exc)}

        try:
            body = resp.json()
        except ValueError:
            body = {}

        if resp.status_code == 200 and body.get("success"):
            return {"success": True, "data": body.get("data", {})}

        return {
            "success": False,
            "error": body.get("error") or f"HTTP {resp.status_code}",
        }

    # ------------------------------------------------------------------
    # DB connection
    # ------------------------------------------------------------------

    def _connect(self):
        """Return a new psycopg2 connection (caller manages lifecycle)."""
        return psycopg2.connect(self._db_dsn)


# ---------------------------------------------------------------------------
# Module-level worker instance (shared across tasks in the same process)
# ---------------------------------------------------------------------------

_worker = EmailSyncWorker()


# ---------------------------------------------------------------------------
# Celery tasks
# ---------------------------------------------------------------------------


@app.task(
    name="email_sync.sync_all_mailboxes",
    bind=True,
    max_retries=3,
    default_retry_delay=30,
)
def sync_all_mailboxes(self) -> dict[str, int]:
    """
    Beat task: dispatch per-mailbox sync tasks for all due mailboxes.
    Retries up to 3 times (30 s delay) if the DB fetch itself fails.
    """
    try:
        return _worker.run_all()
    except Exception as exc:
        logger.exception("sync_all_mailboxes: unhandled error — %s", exc)
        raise self.retry(exc=exc, countdown=30)


@app.task(
    name="email_sync.sync_single_mailbox",
    bind=True,
    max_retries=MAX_RETRIES,
)
def sync_single_mailbox(self, mailbox_id: int, tenant_id: str, user_id: int) -> dict[str, Any]:
    """
    Per-mailbox sync task with exponential backoff retry.

    Retry schedule: attempt 1 → 30 s, attempt 2 → 60 s, attempt 3 → 120 s.
    After all retries are exhausted, marks the mailbox as error and notifies
    the owner (Requirement 13.5).
    """
    attempt = self.request.retries + 1  # 1-based for human-readable logging
    try:
        return _worker.sync_one(mailbox_id, tenant_id, user_id, attempt)
    except Exception as exc:
        if self.request.retries < MAX_RETRIES:
            countdown = RETRY_DELAYS[self.request.retries]
            logger.info(
                "sync_single_mailbox: mailbox=%d retrying in %ds (attempt %d/%d)",
                mailbox_id,
                countdown,
                attempt,
                MAX_RETRIES,
            )
            raise self.retry(exc=exc, countdown=countdown)
        # Final failure already handled inside sync_one
        logger.error(
            "sync_single_mailbox: mailbox=%d exhausted all %d retries — %s",
            mailbox_id,
            MAX_RETRIES,
            exc,
        )
        return {"success": False, "error": str(exc), "mailbox_id": mailbox_id}
