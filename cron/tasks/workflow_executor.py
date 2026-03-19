"""
cron/tasks/workflow_executor.py

WorkflowExecutor — Celery task that runs workflow actions sequentially.

Behaviour:
  - Consumes messages from the `workflow.execute` RabbitMQ queue
  - Loads the workflow + ordered actions from PostgreSQL
  - Executes each action in declared order (Req 14.5)
  - Records each step result in `workflow_execution_steps` (Req 14.5)
  - On action failure: retries with exponential backoff (1s, 2s, 4s), max 3 retries (Req 14.6)
  - If all retries exhausted: marks step + execution as failed, stops
  - Records execution start/end in `workflow_executions` (Req 14.7)

Action types supported (Req 14.3):
  send_email, send_sms, create_task, update_field, assign_owner,
  add_tag, create_deal, move_deal_stage, call_webhook, wait

Requirements: 14.3, 14.5, 14.6, 14.7
"""

from __future__ import annotations

import json
import logging
import os
import smtplib
import time
from datetime import datetime, timezone
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

# Exponential backoff delays in seconds: attempt 0→1s, 1→2s, 2→4s (Req 14.6)
RETRY_DELAYS: list[int] = [1, 2, 4]
MAX_ACTION_RETRIES: int = len(RETRY_DELAYS)

# ---------------------------------------------------------------------------
# Celery application
# ---------------------------------------------------------------------------

app = Celery("workflow_executor", broker=BROKER_URL)

app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    task_queues={
        "workflow.execute": {"exchange": "crm.events", "routing_key": "workflow.execute"},
    },
)


# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------


def _connect() -> psycopg2.extensions.connection:
    """Return a new psycopg2 connection."""
    return psycopg2.connect(DB_DSN)


def _now_utc() -> str:
    return datetime.now(timezone.utc).isoformat()


def _load_workflow(conn, workflow_id: int, tenant_id: str) -> dict | None:
    """Load workflow row scoped to tenant."""
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(
            """
            SELECT id, name, module, trigger_type, is_enabled
            FROM   workflows
            WHERE  id = %s AND tenant_id = %s AND deleted_at IS NULL
            """,
            (workflow_id, tenant_id),
        )
        row = cur.fetchone()
        return dict(row) if row else None


def _load_actions(conn, workflow_id: int, tenant_id: str) -> list[dict]:
    """Load ordered workflow actions scoped to tenant."""
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(
            """
            SELECT id, action_order, action_type, action_config
            FROM   workflow_actions
            WHERE  workflow_id = %s AND tenant_id = %s AND deleted_at IS NULL
            ORDER  BY action_order ASC
            """,
            (workflow_id, tenant_id),
        )
        return [dict(r) for r in cur.fetchall()]


def _create_execution(
    conn,
    workflow_id: int,
    tenant_id: str,
    company_code: str,
    trigger_event: str,
    context: dict,
) -> int:
    """Insert a workflow_executions row and return its id."""
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO workflow_executions
                (tenant_id, company_code, workflow_id, status, trigger_event,
                 context, started_at, created_at, updated_at)
            VALUES (%s, %s, %s, 'running', %s, %s::jsonb, NOW(), NOW(), NOW())
            RETURNING id
            """,
            (tenant_id, company_code, workflow_id, trigger_event, json.dumps(context)),
        )
        return cur.fetchone()[0]


def _update_execution_status(conn, execution_id: int, status: str) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE workflow_executions
            SET    status = %s, completed_at = NOW(), updated_at = NOW()
            WHERE  id = %s
            """,
            (status, execution_id),
        )


def _create_step(
    conn,
    execution_id: int,
    tenant_id: str,
    company_code: str,
    action: dict,
) -> int:
    """Insert a workflow_execution_steps row with status=running and return its id."""
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO workflow_execution_steps
                (tenant_id, company_code, execution_id, action_id, action_order,
                 action_type, status, retry_count, started_at, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, 'running', 0, NOW(), NOW(), NOW())
            RETURNING id
            """,
            (
                tenant_id,
                company_code,
                execution_id,
                action["id"],
                action["action_order"],
                action["action_type"],
            ),
        )
        return cur.fetchone()[0]


def _update_step(
    conn,
    step_id: int,
    status: str,
    result: dict | None = None,
    error_message: str | None = None,
    retry_count: int = 0,
) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE workflow_execution_steps
            SET    status = %s, result = %s::jsonb, error_message = %s,
                   retry_count = %s, completed_at = NOW(), updated_at = NOW()
            WHERE  id = %s
            """,
            (
                status,
                json.dumps(result) if result is not None else None,
                error_message,
                retry_count,
                step_id,
            ),
        )


# ---------------------------------------------------------------------------
# Action handlers
# ---------------------------------------------------------------------------


def _load_tenant_smtp(conn, tenant_id: str, company_code: str) -> dict:
    """Load SMTP config from connected_mailboxes for the tenant."""
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(
            """
            SELECT smtp_host, smtp_port, smtp_user, smtp_password, email_address
            FROM   connected_mailboxes
            WHERE  tenant_id = %s AND company_code = %s
              AND  sync_status = 'active' AND deleted_at IS NULL
            LIMIT  1
            """,
            (tenant_id, company_code),
        )
        row = cur.fetchone()
        return dict(row) if row else {}


def _load_tenant_sms_config(conn, tenant_id: str, company_code: str) -> dict:
    """Load Twilio SMS config from inbox_channels for the tenant."""
    with conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor) as cur:
        cur.execute(
            """
            SELECT config
            FROM   inbox_channels
            WHERE  tenant_id = %s AND company_code = %s
              AND  channel_type = 'sms' AND is_active = TRUE AND deleted_at IS NULL
            LIMIT  1
            """,
            (tenant_id, company_code),
        )
        row = cur.fetchone()
        if row and row["config"]:
            cfg = row["config"] if isinstance(row["config"], dict) else json.loads(row["config"])
            return cfg
        return {}


def handle_send_email(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Send email via tenant SMTP config."""
    to_email = config.get("to_email") or context.get("record_email", "")
    subject = config.get("subject", "(no subject)")
    body = config.get("body", "")

    if not to_email:
        raise ValueError("send_email: no recipient email address in config or context")

    smtp_cfg = _load_tenant_smtp(conn, tenant_id, company_code)
    host = smtp_cfg.get("smtp_host") or os.environ.get("SMTP_HOST", "localhost")
    port = int(smtp_cfg.get("smtp_port") or os.environ.get("SMTP_PORT", 587))
    user = smtp_cfg.get("smtp_user") or os.environ.get("SMTP_USER", "")
    password = smtp_cfg.get("smtp_password") or os.environ.get("SMTP_PASSWORD", "")
    from_addr = smtp_cfg.get("email_address") or user

    msg = MIMEText(body, "plain", "utf-8")
    msg["Subject"] = subject
    msg["From"] = from_addr
    msg["To"] = to_email

    with smtplib.SMTP(host, port, timeout=15) as server:
        server.ehlo()
        if port != 25:
            server.starttls()
        if user and password:
            server.login(user, password)
        server.sendmail(from_addr, [to_email], msg.as_string())

    return {"to": to_email, "subject": subject}


def handle_send_sms(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Send SMS via Twilio using tenant channel config."""
    to_phone = config.get("to_phone") or context.get("record_phone", "")
    body = config.get("body", "")

    if not to_phone:
        raise ValueError("send_sms: no recipient phone in config or context")

    sms_cfg = _load_tenant_sms_config(conn, tenant_id, company_code)
    account_sid = sms_cfg.get("account_sid") or os.environ.get("TWILIO_ACCOUNT_SID", "")
    auth_token = sms_cfg.get("auth_token") or os.environ.get("TWILIO_AUTH_TOKEN", "")
    from_number = sms_cfg.get("from_number") or os.environ.get("TWILIO_FROM_NUMBER", "")

    if not (account_sid and auth_token and from_number):
        raise RuntimeError("send_sms: Twilio credentials not configured")

    url = f"https://api.twilio.com/2010-04-01/Accounts/{account_sid}/Messages.json"
    resp = requests.post(
        url,
        data={"To": to_phone, "From": from_number, "Body": body},
        auth=(account_sid, auth_token),
        timeout=15,
    )
    if resp.status_code not in (200, 201):
        raise RuntimeError(f"send_sms: Twilio error HTTP {resp.status_code}: {resp.text[:200]}")

    return {"to": to_phone, "sid": resp.json().get("sid")}


def handle_create_task(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Create a task record via direct DB insert."""
    title = config.get("title", "Workflow Task")
    due_date = config.get("due_date")
    assigned_to = config.get("assigned_to") or context.get("user_id")
    record_type = config.get("record_type") or context.get("record_type")
    record_id = config.get("record_id") or context.get("record_id")

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO tasks
                (tenant_id, company_code, title, due_date, assigned_to,
                 related_type, related_id, status, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending', NOW(), NOW())
            RETURNING id
            """,
            (tenant_id, company_code, title, due_date, assigned_to, record_type, record_id),
        )
        task_id = cur.fetchone()[0]

    return {"task_id": task_id, "title": title}


def handle_update_field(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Update a field on the target record."""
    record_type = config.get("record_type") or context.get("record_type")
    record_id = int(config.get("record_id") or context.get("record_id", 0))
    field_name = config.get("field_name", "")
    field_value = config.get("field_value")

    if not record_type or not record_id or not field_name:
        raise ValueError("update_field: record_type, record_id, and field_name are required")

    # Map record_type to table name (whitelist for safety)
    table_map = {
        "leads": "leads",
        "contacts": "contacts",
        "deals": "deals",
        "accounts": "accounts",
    }
    table = table_map.get(record_type)
    if not table:
        raise ValueError(f"update_field: unsupported record_type '{record_type}'")

    # Validate field_name is a simple identifier (no SQL injection)
    if not field_name.replace("_", "").isalnum():
        raise ValueError(f"update_field: invalid field_name '{field_name}'")

    with conn.cursor() as cur:
        cur.execute(
            f"UPDATE {table} SET {field_name} = %s, updated_at = NOW() "
            f"WHERE id = %s AND tenant_id = %s AND deleted_at IS NULL",
            (field_value, record_id, tenant_id),
        )
        affected = cur.rowcount

    return {"table": table, "record_id": record_id, "field": field_name, "rows_updated": affected}


def handle_assign_owner(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Assign owner_id on the target record."""
    record_type = config.get("record_type") or context.get("record_type")
    record_id = int(config.get("record_id") or context.get("record_id", 0))
    owner_id = config.get("owner_id")

    if not record_type or not record_id or not owner_id:
        raise ValueError("assign_owner: record_type, record_id, and owner_id are required")

    table_map = {"leads": "leads", "contacts": "contacts", "deals": "deals", "accounts": "accounts"}
    table = table_map.get(record_type)
    if not table:
        raise ValueError(f"assign_owner: unsupported record_type '{record_type}'")

    with conn.cursor() as cur:
        cur.execute(
            f"UPDATE {table} SET owner_id = %s, updated_at = NOW() "
            f"WHERE id = %s AND tenant_id = %s AND deleted_at IS NULL",
            (owner_id, record_id, tenant_id),
        )

    return {"record_type": record_type, "record_id": record_id, "owner_id": owner_id}


def handle_add_tag(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Append a tag to the target record's tags array."""
    record_type = config.get("record_type") or context.get("record_type")
    record_id = int(config.get("record_id") or context.get("record_id", 0))
    tag = config.get("tag", "")

    if not record_type or not record_id or not tag:
        raise ValueError("add_tag: record_type, record_id, and tag are required")

    table_map = {"leads": "leads", "contacts": "contacts", "deals": "deals", "accounts": "accounts"}
    table = table_map.get(record_type)
    if not table:
        raise ValueError(f"add_tag: unsupported record_type '{record_type}'")

    with conn.cursor() as cur:
        cur.execute(
            f"UPDATE {table} SET tags = array_append(COALESCE(tags, ARRAY[]::text[]), %s), "
            f"updated_at = NOW() "
            f"WHERE id = %s AND tenant_id = %s AND deleted_at IS NULL",
            (tag, record_id, tenant_id),
        )

    return {"record_type": record_type, "record_id": record_id, "tag": tag}


def handle_create_deal(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Insert a new deal record."""
    title = config.get("title", "Workflow Deal")
    value = config.get("value", 0)
    stage_id = config.get("stage_id")
    pipeline_id = config.get("pipeline_id")
    contact_id = config.get("contact_id") or context.get("record_id")
    owner_id = config.get("owner_id") or context.get("user_id")

    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO deals
                (tenant_id, company_code, title, value, stage_id, pipeline_id,
                 contact_id, owner_id, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
            RETURNING id
            """,
            (tenant_id, company_code, title, value, stage_id, pipeline_id, contact_id, owner_id),
        )
        deal_id = cur.fetchone()[0]

    return {"deal_id": deal_id, "title": title}


def handle_move_deal_stage(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Move a deal to a new stage and record the transition."""
    deal_id = int(config.get("deal_id") or context.get("record_id", 0))
    new_stage_id = config.get("stage_id")
    moved_by = context.get("user_id")

    if not deal_id or not new_stage_id:
        raise ValueError("move_deal_stage: deal_id and stage_id are required")

    with conn.cursor() as cur:
        # Fetch current stage
        cur.execute(
            "SELECT stage_id FROM deals WHERE id = %s AND tenant_id = %s AND deleted_at IS NULL",
            (deal_id, tenant_id),
        )
        row = cur.fetchone()
        if not row:
            raise ValueError(f"move_deal_stage: deal {deal_id} not found")
        old_stage_id = row[0]

        # Update deal stage
        cur.execute(
            "UPDATE deals SET stage_id = %s, updated_at = NOW() "
            "WHERE id = %s AND tenant_id = %s AND deleted_at IS NULL",
            (new_stage_id, deal_id, tenant_id),
        )

        # Record stage history
        cur.execute(
            """
            INSERT INTO deal_stage_history
                (tenant_id, company_code, deal_id, from_stage_id, to_stage_id,
                 changed_by, changed_at, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
            """,
            (tenant_id, company_code, deal_id, old_stage_id, new_stage_id, moved_by),
        )

    return {"deal_id": deal_id, "from_stage_id": old_stage_id, "to_stage_id": new_stage_id}


def handle_call_webhook(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """HTTP POST to configured webhook URL with payload."""
    url = config.get("url", "")
    if not url:
        raise ValueError("call_webhook: url is required in action config")

    headers = config.get("headers") or {}
    payload = config.get("payload") or context

    resp = requests.post(url, json=payload, headers=headers, timeout=30)
    if resp.status_code >= 400:
        raise RuntimeError(
            f"call_webhook: HTTP {resp.status_code} from {url}: {resp.text[:200]}"
        )

    return {"url": url, "status_code": resp.status_code}


def handle_wait(
    conn, config: dict, context: dict, tenant_id: str, company_code: str
) -> dict:
    """Wait for a configured number of seconds (blocking sleep for simplicity)."""
    seconds = int(config.get("seconds", 0))
    if seconds > 0:
        time.sleep(seconds)
    return {"waited_seconds": seconds}


# ---------------------------------------------------------------------------
# Action dispatcher
# ---------------------------------------------------------------------------

ACTION_HANDLERS = {
    "send_email":      handle_send_email,
    "send_sms":        handle_send_sms,
    "create_task":     handle_create_task,
    "update_field":    handle_update_field,
    "assign_owner":    handle_assign_owner,
    "add_tag":         handle_add_tag,
    "create_deal":     handle_create_deal,
    "move_deal_stage": handle_move_deal_stage,
    "call_webhook":    handle_call_webhook,
    "wait":            handle_wait,
}


def _execute_action(
    conn,
    action: dict,
    context: dict,
    tenant_id: str,
    company_code: str,
) -> dict:
    """Dispatch to the correct action handler. Raises on failure."""
    action_type = action["action_type"]
    handler = ACTION_HANDLERS.get(action_type)
    if handler is None:
        raise ValueError(f"Unknown action type: '{action_type}'")

    cfg = action.get("action_config") or {}
    if isinstance(cfg, str):
        cfg = json.loads(cfg)

    return handler(conn, cfg, context, tenant_id, company_code)


# ---------------------------------------------------------------------------
# Core executor logic
# ---------------------------------------------------------------------------


def _run_workflow(
    workflow_id: int,
    tenant_id: str,
    company_code: str,
    trigger_event: str,
    context: dict,
) -> dict:
    """
    Load and execute a workflow sequentially.

    Returns a summary dict with execution_id, steps_run, status.
    Requirements: 14.3, 14.5, 14.6, 14.7
    """
    conn = _connect()
    conn.autocommit = False

    try:
        # Load workflow
        workflow = _load_workflow(conn, workflow_id, tenant_id)
        if not workflow:
            logger.warning("execute_workflow: workflow %d not found for tenant %s", workflow_id, tenant_id)
            return {"status": "skipped", "reason": "workflow not found"}

        if not workflow["is_enabled"]:
            logger.info("execute_workflow: workflow %d is disabled — skipping", workflow_id)
            return {"status": "skipped", "reason": "workflow disabled"}

        # Load ordered actions
        actions = _load_actions(conn, workflow_id, tenant_id)
        if not actions:
            logger.info("execute_workflow: workflow %d has no actions", workflow_id)
            return {"status": "completed", "steps_run": 0}

        # Create execution record
        execution_id = _create_execution(
            conn, workflow_id, tenant_id, company_code, trigger_event, context
        )
        conn.commit()

        steps_run = 0
        final_status = "completed"

        for action in actions:
            step_id = _create_step(conn, execution_id, tenant_id, company_code, action)
            conn.commit()

            attempt = 0
            step_status = "failed"
            step_result = None
            step_error = None

            # Retry loop with exponential backoff (Req 14.6)
            while attempt <= MAX_ACTION_RETRIES:
                try:
                    step_result = _execute_action(conn, action, context, tenant_id, company_code)
                    conn.commit()
                    step_status = "completed"
                    break
                except Exception as exc:
                    conn.rollback()
                    step_error = str(exc)
                    logger.warning(
                        "execute_workflow: execution=%d action=%d type=%s attempt=%d/%d failed: %s",
                        execution_id,
                        action["id"],
                        action["action_type"],
                        attempt + 1,
                        MAX_ACTION_RETRIES,
                        step_error,
                    )
                    if attempt < MAX_ACTION_RETRIES:
                        delay = RETRY_DELAYS[attempt]
                        time.sleep(delay)
                        attempt += 1
                    else:
                        # All retries exhausted
                        break

            _update_step(conn, step_id, step_status, step_result, step_error, attempt)
            conn.commit()
            steps_run += 1

            if step_status == "failed":
                # Stop execution on first failed step (Req 14.6)
                final_status = "failed"
                logger.error(
                    "execute_workflow: execution=%d step=%d action_type=%s failed after %d retries — stopping",
                    execution_id,
                    step_id,
                    action["action_type"],
                    attempt,
                )
                break

        _update_execution_status(conn, execution_id, final_status)
        conn.commit()

        logger.info(
            "execute_workflow: execution=%d workflow=%d status=%s steps=%d",
            execution_id,
            workflow_id,
            final_status,
            steps_run,
        )

        return {
            "execution_id": execution_id,
            "workflow_id": workflow_id,
            "status": final_status,
            "steps_run": steps_run,
        }

    except Exception as exc:
        try:
            conn.rollback()
        except Exception:
            pass
        logger.exception("execute_workflow: unhandled error for workflow %d: %s", workflow_id, exc)
        raise
    finally:
        conn.close()


# ---------------------------------------------------------------------------
# Celery task
# ---------------------------------------------------------------------------


@app.task(
    name="workflow_executor.execute_workflow",
    bind=True,
    max_retries=3,
    default_retry_delay=30,
    queue="workflow.execute",
)
def execute_workflow(
    self,
    workflow_id: int,
    tenant_id: str,
    company_code: str = "01",
    trigger_event: str = "",
    context: dict | None = None,
    **kwargs: Any,
) -> dict:
    """
    Celery task: execute a workflow's actions sequentially.

    Consumed from the `workflow.execute` RabbitMQ queue.
    Published by WorkflowEngine::enqueueExecution() (PHP).

    Parameters
    ----------
    workflow_id    : ID of the workflow to execute.
    tenant_id      : Tenant UUID (required for data isolation).
    company_code   : Two-digit company code (default '01').
    trigger_event  : The event that triggered this execution.
    context        : Full trigger context dict from WorkflowEngine.

    Requirements: 14.3, 14.5, 14.6, 14.7
    """
    ctx = context or {}

    # Support flat message format published by WorkflowEngine (PHP)
    if not workflow_id and "workflow_id" in ctx:
        workflow_id = ctx["workflow_id"]
    if not tenant_id and "tenant_id" in ctx:
        tenant_id = ctx["tenant_id"]
    if not trigger_event and "event" in ctx:
        trigger_event = ctx["event"]

    logger.info(
        "execute_workflow: workflow=%d tenant=%s event=%s",
        workflow_id,
        tenant_id,
        trigger_event,
    )

    try:
        return _run_workflow(workflow_id, tenant_id, company_code, trigger_event, ctx)
    except Exception as exc:
        logger.exception("execute_workflow: task-level error for workflow %d: %s", workflow_id, exc)
        raise self.retry(exc=exc, countdown=30)
