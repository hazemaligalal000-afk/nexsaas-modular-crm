"""
cron/tasks/deal_maintenance.py

Nightly Celery task for deal rotting and overdue checks.

- Deal rotting  (Req 10.8): sets is_stale = true when no activity for
  configurable days (default: 30). Reads rot_days from tenant config.
- Overdue check (Req 10.6): marks is_overdue = true when close_date < NOW()
  and the deal's stage is not a closed stage (is_closed_won OR is_closed_lost).

Schedule: run nightly via Celery Beat (e.g. 02:00 UTC).
"""

import logging
import os
from datetime import datetime, timezone

import psycopg2
from celery import Celery

logger = logging.getLogger(__name__)

BROKER_URL = os.environ.get("RABBITMQ_URL", "amqp://guest:guest@rabbitmq:5672//")
DB_DSN     = os.environ.get("DATABASE_URL", "postgresql://nexsaas:nexsaas@postgres:5432/nexsaas")

# Default rot threshold in days when no tenant config is found
DEFAULT_ROT_DAYS = 30

app = Celery("deal_maintenance", broker=BROKER_URL)

app.conf.beat_schedule = {
    "deal-maintenance-nightly": {
        "task":     "deal_maintenance.run_deal_maintenance",
        "schedule": 86400,  # every 24 hours
    },
}


@app.task(name="deal_maintenance.run_deal_maintenance", bind=True, max_retries=3)
def run_deal_maintenance(self) -> dict:
    """
    Main entry point. Runs both rotting and overdue checks for all tenants.
    Returns a summary dict with counts of updated deals.
    """
    try:
        conn = psycopg2.connect(DB_DSN)
        conn.autocommit = False
        try:
            stale_count   = _mark_stale_deals(conn)
            overdue_count = _mark_overdue_deals(conn)
            conn.commit()
            logger.info(
                "deal_maintenance complete: stale=%d overdue=%d",
                stale_count,
                overdue_count,
            )
            return {"stale_updated": stale_count, "overdue_updated": overdue_count}
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()
    except Exception as exc:
        logger.exception("deal_maintenance failed: %s", exc)
        raise self.retry(exc=exc, countdown=300)


def _mark_stale_deals(conn) -> int:
    """
    Set is_stale = true for deals with no activity for >= rot_days.

    rot_days is read from tenant_config.deal_rot_days (if the table exists),
    falling back to DEFAULT_ROT_DAYS.

    Requirements: 10.8
    """
    sql = """
        UPDATE deals d
        SET    is_stale   = true,
               updated_at = NOW()
        FROM   (
            -- Per-tenant rot threshold: join tenant_config if available,
            -- otherwise use the default.
            SELECT
                t.tenant_id,
                COALESCE(
                    (SELECT value::int
                     FROM   tenant_config
                     WHERE  tenant_id = t.tenant_id
                       AND  key       = 'deal_rot_days'
                     LIMIT  1),
                    %(default_rot_days)s
                ) AS rot_days
            FROM (SELECT DISTINCT tenant_id FROM deals WHERE deleted_at IS NULL) t
        ) cfg
        WHERE  d.tenant_id    = cfg.tenant_id
          AND  d.deleted_at   IS NULL
          AND  d.is_stale     = false
          AND  (
                  d.last_activity_at IS NULL
                  OR d.last_activity_at < NOW() - (cfg.rot_days || ' days')::INTERVAL
               )
          -- Exclude already-closed deals
          AND  d.stage_id NOT IN (
                  SELECT id FROM pipeline_stages
                  WHERE  (is_closed_won = true OR is_closed_lost = true)
                    AND  deleted_at IS NULL
               )
    """
    with conn.cursor() as cur:
        cur.execute(sql, {"default_rot_days": DEFAULT_ROT_DAYS})
        return cur.rowcount


def _mark_overdue_deals(conn) -> int:
    """
    Set is_overdue = true for deals whose close_date has passed and whose
    stage is not a closed stage.

    Requirements: 10.6
    """
    sql = """
        UPDATE deals d
        SET    is_overdue  = true,
               updated_at  = NOW()
        WHERE  d.deleted_at  IS NULL
          AND  d.is_overdue  = false
          AND  d.close_date  < CURRENT_DATE
          AND  d.stage_id NOT IN (
                  SELECT id FROM pipeline_stages
                  WHERE  (is_closed_won = true OR is_closed_lost = true)
                    AND  deleted_at IS NULL
               )
    """
    with conn.cursor() as cur:
        cur.execute(sql)
        return cur.rowcount
