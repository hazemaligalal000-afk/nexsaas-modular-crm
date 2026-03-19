"""
cron/tasks/lead_import.py

Celery task for bulk lead CSV import.

Reads a CSV file, maps columns to lead fields, detects duplicates by
email/phone, and creates each lead via the PHP API (or direct DB insert).

Requirements: 7.6, 7.7
"""

import csv
import logging
import os
import requests
from celery import Celery

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Celery app
# ---------------------------------------------------------------------------

app = Celery("nexsaas_cron")
app.config_from_object("cron.celeryconfig", silent=True)

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

PHP_API_BASE = os.environ.get("PHP_API_BASE", "http://php-fpm/api/v1")
INTERNAL_API_KEY = os.environ.get("INTERNAL_API_KEY", "")


# ---------------------------------------------------------------------------
# Task
# ---------------------------------------------------------------------------


@app.task(
    name="crm.lead_import",
    bind=True,
    max_retries=3,
    default_retry_delay=60,
)
def lead_import(
    self,
    file_path: str,
    tenant_id: str,
    company_code: str,
    field_mapping: dict,
    job_id: str = "",
    requested_by: int = 0,
) -> dict:
    """
    Import leads from a CSV file.

    Parameters
    ----------
    file_path     : Absolute path to the uploaded CSV file on shared storage.
    tenant_id     : Tenant UUID.
    company_code  : Two-digit company code.
    field_mapping : Dict mapping CSV column names → lead field names.
                    e.g. {"Email Address": "email", "Full Name": "first_name"}
    job_id        : Optional job reference UUID for status tracking.
    requested_by  : User ID who triggered the import (0 = system).

    Returns
    -------
    dict with keys: total, imported, duplicates_skipped, errors
    """
    logger.info(
        "crm.lead_import [%s]: starting import from %s (tenant=%s, company=%s)",
        job_id,
        file_path,
        tenant_id,
        company_code,
    )

    if not os.path.isfile(file_path):
        raise FileNotFoundError(f"CSV file not found: {file_path}")

    stats = {
        "job_id": job_id,
        "total": 0,
        "imported": 0,
        "duplicates_skipped": 0,
        "errors": [],
    }

    # Track seen emails and phones within this import batch to catch
    # intra-file duplicates before hitting the API.
    seen_emails: set[str] = set()
    seen_phones: set[str] = set()

    try:
        with open(file_path, newline="", encoding="utf-8-sig") as fh:
            reader = csv.DictReader(fh)

            for row_num, row in enumerate(reader, start=2):  # row 1 = header
                stats["total"] += 1

                # Map CSV columns to lead fields using field_mapping
                lead_data = _map_row(row, field_mapping)

                email = (lead_data.get("email") or "").strip().lower()
                phone = (lead_data.get("phone") or "").strip()

                # Intra-file duplicate detection
                if email and email in seen_emails:
                    logger.debug("row %d: duplicate email '%s' — skipping", row_num, email)
                    stats["duplicates_skipped"] += 1
                    continue

                if phone and phone in seen_phones:
                    logger.debug("row %d: duplicate phone '%s' — skipping", row_num, phone)
                    stats["duplicates_skipped"] += 1
                    continue

                # Attempt to create lead via PHP API
                result = _create_lead_via_api(
                    lead_data=lead_data,
                    tenant_id=tenant_id,
                    company_code=company_code,
                    created_by=requested_by,
                )

                if result["success"]:
                    stats["imported"] += 1
                    if email:
                        seen_emails.add(email)
                    if phone:
                        seen_phones.add(phone)
                elif result.get("duplicate"):
                    stats["duplicates_skipped"] += 1
                else:
                    stats["errors"].append({
                        "row": row_num,
                        "error": result.get("error", "Unknown error"),
                        "data": lead_data,
                    })

    except Exception as exc:
        logger.exception("crm.lead_import [%s]: unexpected error — %s", job_id, exc)
        raise self.retry(exc=exc)

    logger.info(
        "crm.lead_import [%s]: done — total=%d imported=%d duplicates=%d errors=%d",
        job_id,
        stats["total"],
        stats["imported"],
        stats["duplicates_skipped"],
        len(stats["errors"]),
    )

    return stats


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def _map_row(row: dict, field_mapping: dict) -> dict:
    """
    Map a CSV row dict to lead field names using field_mapping.

    If field_mapping is empty, assume CSV columns already match lead fields.
    """
    if not field_mapping:
        return dict(row)

    lead_data: dict = {}
    for csv_col, lead_field in field_mapping.items():
        if csv_col in row:
            lead_data[lead_field] = row[csv_col]

    # Pass through any unmapped columns as-is
    mapped_csv_cols = set(field_mapping.keys())
    for col, val in row.items():
        if col not in mapped_csv_cols:
            lead_data[col] = val

    return lead_data


def _create_lead_via_api(
    lead_data: dict,
    tenant_id: str,
    company_code: str,
    created_by: int,
) -> dict:
    """
    POST lead_data to the internal PHP API.

    Returns dict with keys:
        success (bool), duplicate (bool), error (str|None)
    """
    url = f"{PHP_API_BASE}/crm/leads"
    headers = {
        "Content-Type": "application/json",
        "X-Tenant-Id": tenant_id,
        "X-Company-Code": company_code,
        "X-Internal-Key": INTERNAL_API_KEY,
        "X-Created-By": str(created_by),
    }

    try:
        resp = requests.post(url, json=lead_data, headers=headers, timeout=10)
    except requests.RequestException as exc:
        return {"success": False, "duplicate": False, "error": str(exc)}

    if resp.status_code == 201:
        return {"success": True, "duplicate": False, "error": None}

    body = {}
    try:
        body = resp.json()
    except ValueError:
        pass

    error_msg = body.get("error") or resp.text or f"HTTP {resp.status_code}"

    # 409 Conflict = duplicate
    is_duplicate = resp.status_code == 409

    return {
        "success": False,
        "duplicate": is_duplicate,
        "error": error_msg,
    }
