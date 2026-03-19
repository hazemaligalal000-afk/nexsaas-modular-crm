"""
cron/tasks/report_scheduler.py

ReportScheduler — Celery task that runs every hour to deliver scheduled reports.

Behaviour:
  - Queries report_schedules where next_run_at <= NOW() AND is_active = TRUE
  - For each due schedule:
      1. Fetches the report definition from custom_reports
      2. Calls the PHP export endpoint (CSV or PDF) via internal HTTP API
      3. Sends the exported file as an email attachment to each recipient
      4. Marks the schedule as ran (advances next_run_at by frequency)
  - Handles errors gracefully per schedule; continues to next on failure
  - Logs success/failure for each schedule

Requirements: 17.4, 17.5
"""

from __future__ import annotations

import email as email_lib
import logging
import os
import smtplib
import tempfile
from datetime import datetime, timezone
from email import encoders
from email.mime.base import MIMEBase
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
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
PHP_API_BASE: str = os.environ.get("PHP_API_BASE", "http://php-fpm/api/v1")
INTERNAL_API_KEY: str = os.environ.get("INTERNAL_API_KEY", "")

SMTP_HOST: str = os.environ.get("SMTP_HOST", "localhost")
SMTP_PORT: int = int(os.environ.get("SMTP_PORT", "587"))
SMTP_USER: str = os.environ.get("SMTP_USER", "")
SMTP_PASS: str = os.environ.get("SMTP_PASS", "")
SMTP_FROM: str = os.environ.get("SMTP_FROM", "reports@nexsaas.com")
SMTP_USE_TLS: bool = os.environ.get("SMTP_USE_TLS", "true").lower() == "true"

# ---------------------------------------------------------------------------
# Celery application
# ---------------------------------------------------------------------------

app = Celery("report_scheduler", broker=BROKER_URL)

app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    beat_schedule={
        "report-scheduler-hourly": {
            "task": "report_scheduler.run_report_scheduler",
            "schedule": 3600,  # every hour
        },
    },
)


# ---------------------------------------------------------------------------
# ReportSchedulerWorker
# ---------------------------------------------------------------------------


class ReportSchedulerWorker:
    """
    Encapsulates all report scheduling logic.
    Separating from Celery decorators makes unit testing straightforward.
    """

    def __init__(self, db_dsn: str = DB_DSN) -> None:
        self._db_dsn = db_dsn

    # ------------------------------------------------------------------
    # Main entry point
    # ------------------------------------------------------------------

    def run(self) -> dict[str, int]:
        """
        Fetch all due schedules and process each one.

        Returns a summary: {total, success, failed}.
        """
        schedules = self._fetch_due_schedules()
        stats = {"total": len(schedules), "success": 0, "failed": 0}

        logger.info("report_scheduler: found %d due schedules", len(schedules))

        for schedule in schedules:
            schedule_id = schedule["id"]
            try:
                self._process_schedule(schedule)
                self._mark_ran(schedule_id, schedule["frequency"])
                stats["success"] += 1
                logger.info("report_scheduler: schedule %d completed successfully", schedule_id)
            except Exception as exc:
                stats["failed"] += 1
                logger.error(
                    "report_scheduler: schedule %d failed — %s",
                    schedule_id,
                    exc,
                    exc_info=True,
                )

        logger.info(
            "report_scheduler: done — total=%d success=%d failed=%d",
            stats["total"],
            stats["success"],
            stats["failed"],
        )
        return stats

    # ------------------------------------------------------------------
    # Schedule processing
    # ------------------------------------------------------------------

    def _process_schedule(self, schedule: dict[str, Any]) -> None:
        """
        Execute the report, export it, and email it to all recipients.
        """
        report_id   = schedule["report_id"]
        fmt         = schedule["format"]          # 'csv' or 'pdf'
        recipients  = schedule.get("recipients") or []
        tenant_id   = schedule["tenant_id"]
        report_name = schedule.get("report_name", f"Report {report_id}")

        if not recipients:
            logger.warning(
                "report_scheduler: schedule %d has no recipients, skipping",
                schedule["id"],
            )
            return

        # Export via PHP internal API
        file_content, filename, content_type = self._export_report(
            report_id, fmt, tenant_id
        )

        # Send email to each recipient
        subject = f"Scheduled Report: {report_name}"
        body    = (
            f"Please find attached your scheduled report '{report_name}'.\n\n"
            f"Generated at: {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')}"
        )

        for recipient in recipients:
            try:
                self._send_email(
                    to_address=recipient,
                    subject=subject,
                    body=body,
                    attachment_content=file_content,
                    attachment_filename=filename,
                    content_type=content_type,
                )
                logger.info(
                    "report_scheduler: schedule %d sent to %s",
                    schedule["id"],
                    recipient,
                )
            except Exception as exc:
                logger.error(
                    "report_scheduler: schedule %d failed to send to %s — %s",
                    schedule["id"],
                    recipient,
                    exc,
                )
                raise  # re-raise so the outer handler marks the schedule as failed

    # ------------------------------------------------------------------
    # Export via PHP API
    # ------------------------------------------------------------------

    def _export_report(
        self,
        report_id: int,
        fmt: str,
        tenant_id: str,
    ) -> tuple[bytes, str, str]:
        """
        Call the PHP export endpoint and return (content_bytes, filename, content_type).

        Requirement 17.5: supports csv and pdf formats.
        """
        endpoint = f"{PHP_API_BASE}/crm/reports/{report_id}/export/{fmt}"
        headers = {
            "X-Tenant-Id":    tenant_id,
            "X-Internal-Key": INTERNAL_API_KEY,
        }

        resp = requests.get(endpoint, headers=headers, timeout=60)

        if resp.status_code != 200:
            raise RuntimeError(
                f"Export endpoint returned HTTP {resp.status_code} for report {report_id}"
            )

        # The PHP endpoint returns an API_Response envelope with csv/pdf data
        body = resp.json()
        if not body.get("success"):
            raise RuntimeError(
                f"Export failed for report {report_id}: {body.get('error', 'unknown error')}"
            )

        data = body.get("data", {})

        if fmt == "csv":
            content      = data.get("content", "").encode("utf-8")
            filename     = data.get("filename", f"report_{report_id}.csv")
            content_type = "text/csv"
        else:
            # PDF: the PHP endpoint returns a file path; fetch the file
            file_path = data.get("file_path", "")
            if not file_path or not os.path.isfile(file_path):
                raise RuntimeError(f"PDF file not found at path: {file_path}")
            with open(file_path, "rb") as f:
                content = f.read()
            filename     = data.get("filename", f"report_{report_id}.pdf")
            content_type = "application/pdf"

        return content, filename, content_type

    # ------------------------------------------------------------------
    # Email delivery
    # ------------------------------------------------------------------

    def _send_email(
        self,
        to_address: str,
        subject: str,
        body: str,
        attachment_content: bytes,
        attachment_filename: str,
        content_type: str,
    ) -> None:
        """
        Send an email with the report as an attachment via SMTP.

        Requirement 17.4: deliver results via email.
        """
        msg = MIMEMultipart()
        msg["From"]    = SMTP_FROM
        msg["To"]      = to_address
        msg["Subject"] = subject

        msg.attach(MIMEText(body, "plain", "utf-8"))

        # Attachment
        main_type, sub_type = content_type.split("/", 1)
        part = MIMEBase(main_type, sub_type)
        part.set_payload(attachment_content)
        encoders.encode_base64(part)
        part.add_header(
            "Content-Disposition",
            "attachment",
            filename=attachment_filename,
        )
        msg.attach(part)

        with smtplib.SMTP(SMTP_HOST, SMTP_PORT) as smtp:
            if SMTP_USE_TLS:
                smtp.starttls()
            if SMTP_USER and SMTP_PASS:
                smtp.login(SMTP_USER, SMTP_PASS)
            smtp.sendmail(SMTP_FROM, [to_address], msg.as_string())

    # ------------------------------------------------------------------
    # DB helpers
    # ------------------------------------------------------------------

    def _fetch_due_schedules(self) -> list[dict[str, Any]]:
        """
        Return all active schedules whose next_run_at is in the past.
        Joins custom_reports to get report metadata.
        """
        sql = """
            SELECT
                rs.id,
                rs.tenant_id,
                rs.company_code,
                rs.report_id,
                rs.frequency,
                rs.next_run_at,
                rs.recipients,
                rs.format,
                cr.name AS report_name
            FROM report_schedules rs
            JOIN custom_reports cr
                ON cr.id         = rs.report_id
               AND cr.deleted_at IS NULL
            WHERE rs.next_run_at <= NOW()
              AND rs.is_active    = TRUE
              AND rs.deleted_at   IS NULL
            ORDER BY rs.next_run_at ASC
        """
        with self._connect() as conn:
            with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
                cur.execute(sql)
                rows = cur.fetchall()
                result = []
                for row in rows:
                    r = dict(row)
                    # recipients is stored as JSONB — psycopg2 returns it as a Python list already
                    if isinstance(r.get("recipients"), str):
                        import json
                        r["recipients"] = json.loads(r["recipients"])
                    result.append(r)
                return result

    def _mark_ran(self, schedule_id: int, frequency: str) -> None:
        """
        Advance next_run_at based on frequency.

        daily   → +1 day
        weekly  → +7 days
        monthly → +1 month

        Requirement 17.4
        """
        interval_map = {
            "daily":   "1 day",
            "weekly":  "7 days",
            "monthly": "1 month",
        }
        interval = interval_map.get(frequency, "1 day")

        sql = """
            UPDATE report_schedules
            SET    next_run_at = next_run_at + INTERVAL %s,
                   updated_at  = NOW()
            WHERE  id = %s
        """
        with self._connect() as conn:
            conn.autocommit = True
            with conn.cursor() as cur:
                cur.execute(sql, (interval, schedule_id))

    def _connect(self):
        """Return a new psycopg2 connection."""
        return psycopg2.connect(self._db_dsn)


# ---------------------------------------------------------------------------
# Module-level worker instance
# ---------------------------------------------------------------------------

_worker = ReportSchedulerWorker()


# ---------------------------------------------------------------------------
# Celery task
# ---------------------------------------------------------------------------


@app.task(
    name="report_scheduler.run_report_scheduler",
    bind=True,
    max_retries=3,
    default_retry_delay=60,
)
def run_report_scheduler(self) -> dict[str, int]:
    """
    Hourly beat task: process all due report schedules.

    For each due schedule:
      1. Exports the report (CSV or PDF) via the PHP internal API
      2. Emails the result to all configured recipients
      3. Advances next_run_at based on frequency

    Requirements: 17.4, 17.5
    """
    try:
        return _worker.run()
    except Exception as exc:
        logger.exception("run_report_scheduler: unhandled error — %s", exc)
        raise self.retry(exc=exc, countdown=60)
