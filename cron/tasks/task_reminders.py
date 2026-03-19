"""
cron/tasks/task_reminders.py

TaskReminders — Celery beat task that sends due-date reminder notifications
for tasks whose due_date is within the next hour and have not yet received
a reminder.

Behaviour:
  - Runs every 15 minutes via Celery beat schedule
  - Queries tasks where:
      status NOT IN ('completed', 'cancelled')
      AND reminder_sent_at IS NULL
      AND deleted_at IS NULL
      AND due_date <= NOW() + 1 hour
      AND due_date >= NOW() - 1 day  (avoid re-processing very old tasks)
  - Pushes a WebSocket notification payload to Redis key:
      notifications:pending:{assigned_user_id}
  - Updates reminder_sent_at = NOW() on each processed task

Requirements: 15.3
"""

from __future__ import annotations

import json
import logging
import os
from datetime import datetime, timezone

import psycopg2
import psycopg2.extras
import redis as redis_lib
from celery import Celery
from celery.schedules import crontab
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

# ---------------------------------------------------------------------------
# Celery application
# ---------------------------------------------------------------------------

app = Celery("task_reminders", broker=BROKER_URL)

app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    beat_schedule={
        "send-task-due-reminders": {
            "task": "task_reminders.send_due_reminders",
            # Every 15 minutes — Requirement 15.3
            "schedule": crontab(minute="*/15"),
        },
    },
    timezone="UTC",
)

# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------


def _connect_db() -> psycopg2.extensions.connection:
    """Return a new psycopg2 connection."""
    return psycopg2.connect(DB_DSN)


def _connect_redis() -> redis_lib.Redis:
    """Return a Redis client."""
    return redis_lib.from_url(REDIS_URL, decode_responses=True)


def _now_utc() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


def _fetch_due_tasks(conn) -> list[dict]:
    """
    Fetch tasks that are due within the next hour and haven't had a reminder sent.

    Requirement 15.3: due_date <= NOW() + 1 hour, status != completed, reminder_sent_at IS NULL
    """
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(
            """
            SELECT id, title, due_date, assigned_user_id, tenant_id,
                   linked_type, linked_id
            FROM   tasks
            WHERE  status NOT IN ('completed', 'cancelled')
              AND  reminder_sent_at IS NULL
              AND  deleted_at IS NULL
              AND  due_date <= NOW() + INTERVAL '1 hour'
              AND  due_date >= NOW() - INTERVAL '1 day'
            ORDER  BY due_date ASC
            """
        )
        return [dict(row) for row in cur.fetchall()]


def _mark_reminder_sent(conn, task_id: int, now: str) -> None:
    """Update reminder_sent_at for a task."""
    with conn.cursor() as cur:
        cur.execute(
            "UPDATE tasks SET reminder_sent_at = %s WHERE id = %s AND deleted_at IS NULL",
            (now, task_id),
        )


def _push_notification(redis_client: redis_lib.Redis, task: dict) -> None:
    """
    Push a due-reminder notification payload to the Redis list for the assigned user.

    Redis key: notifications:pending:{user_id}
    The WebSocket server reads from this list and delivers to the connected client.
    """
    user_id = task.get("assigned_user_id")
    if not user_id:
        return

    payload = json.dumps(
        {
            "type": "task_due_reminder",
            "task_id": task["id"],
            "title": task["title"],
            "due_date": task["due_date"].isoformat() if hasattr(task["due_date"], "isoformat") else str(task["due_date"]),
            "linked_type": task.get("linked_type"),
            "linked_id": task.get("linked_id"),
            "tenant_id": str(task["tenant_id"]),
        }
    )

    redis_key = f"notifications:pending:{user_id}"
    redis_client.rpush(redis_key, payload)
    logger.debug("Pushed reminder notification to %s for task %d", redis_key, task["id"])


# ---------------------------------------------------------------------------
# Celery task
# ---------------------------------------------------------------------------


@app.task(
    name="task_reminders.send_due_reminders",
    bind=True,
    max_retries=3,
    default_retry_delay=60,
)
def send_due_reminders(self) -> dict:
    """
    Celery beat task: send due-date reminder notifications.

    Runs every 15 minutes. Finds tasks due within the next hour that
    haven't received a reminder, pushes WebSocket notifications via Redis,
    and marks reminder_sent_at on each processed task.

    Returns a summary dict: { sent: int, errors: int }

    Requirement 15.3
    """
    logger.info("send_due_reminders: starting run at %s", _now_utc())

    conn = None
    redis_client = None
    sent = 0
    errors = 0

    try:
        conn = _connect_db()
        conn.autocommit = False
        redis_client = _connect_redis()

        tasks = _fetch_due_tasks(conn)
        logger.info("send_due_reminders: found %d tasks due for reminder", len(tasks))

        now = _now_utc()

        for task in tasks:
            task_id = task["id"]
            try:
                _push_notification(redis_client, task)
                _mark_reminder_sent(conn, task_id, now)
                conn.commit()
                sent += 1
                logger.info(
                    "send_due_reminders: sent reminder for task %d (user=%s, due=%s)",
                    task_id,
                    task.get("assigned_user_id"),
                    task.get("due_date"),
                )
            except Exception as exc:
                conn.rollback()
                errors += 1
                logger.error(
                    "send_due_reminders: failed to process task %d: %s",
                    task_id,
                    exc,
                )

        logger.info(
            "send_due_reminders: completed — sent=%d errors=%d",
            sent,
            errors,
        )
        return {"sent": sent, "errors": errors}

    except Exception as exc:
        logger.exception("send_due_reminders: unhandled error: %s", exc)
        try:
            if conn:
                conn.rollback()
        except Exception:
            pass
        raise self.retry(exc=exc, countdown=60)

    finally:
        if conn:
            try:
                conn.close()
            except Exception:
                pass
        if redis_client:
            try:
                redis_client.close()
            except Exception:
                pass
