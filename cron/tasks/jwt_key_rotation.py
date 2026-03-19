"""
cron/tasks/jwt_key_rotation.py

Celery task for JWT signing key rotation.

Scheduled every 90 days via Celery beat.
Delegates actual rotation logic to the PHP CLI script
modular_core/cli/rotate_jwt_keys.php, which instantiates
JwtKeyRotationService and calls rotate().

Requirements: 42.7
"""

import subprocess
import logging
from celery import Celery
from celery.schedules import crontab

logger = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Celery app
# ---------------------------------------------------------------------------

app = Celery("nexsaas_cron")
app.config_from_object("cron.celeryconfig", silent=True)

# ---------------------------------------------------------------------------
# Beat schedule — run once every 90 days
# ---------------------------------------------------------------------------

app.conf.beat_schedule = {
    "jwt-key-rotation-every-90-days": {
        "task": "jwt.key_rotation",
        # Run at 02:00 UTC on day 1 of every 3rd month (≈ 90 days).
        # For a strict 90-day interval, the PHP service itself checks
        # the Redis timestamp and skips rotation when not yet due.
        "schedule": crontab(hour=2, minute=0, day_of_month=1, month_of_year="1,4,7,10"),
    },
}

# ---------------------------------------------------------------------------
# Task
# ---------------------------------------------------------------------------

PHP_CLI_SCRIPT = "/app/modular_core/cli/rotate_jwt_keys.php"


@app.task(
    name="jwt.key_rotation",
    bind=True,
    max_retries=3,
    default_retry_delay=300,  # 5 minutes between retries
)
def rotate_jwt_keys(self) -> dict:
    """
    Invoke the PHP CLI rotation script.

    The PHP script handles the 90-day guard check internally via Redis,
    so calling this task more frequently than needed is safe.

    Returns a dict with keys:
        success (bool), returncode (int), stdout (str), stderr (str)
    """
    logger.info("jwt.key_rotation: starting PHP CLI rotation script")

    try:
        result = subprocess.run(
            ["php", PHP_CLI_SCRIPT],
            capture_output=True,
            text=True,
            timeout=120,  # 2-minute timeout
        )
    except subprocess.TimeoutExpired as exc:
        logger.error("jwt.key_rotation: PHP script timed out after 120s")
        raise self.retry(exc=exc)
    except FileNotFoundError as exc:
        logger.error("jwt.key_rotation: 'php' binary not found or script missing: %s", exc)
        raise self.retry(exc=exc)

    stdout = result.stdout.strip()
    stderr = result.stderr.strip()

    if result.returncode == 0:
        logger.info("jwt.key_rotation: SUCCESS — %s", stdout)
        return {"success": True, "returncode": 0, "stdout": stdout, "stderr": stderr}

    logger.error(
        "jwt.key_rotation: FAILED (exit %d) — stdout=%s stderr=%s",
        result.returncode,
        stdout,
        stderr,
    )
    exc = RuntimeError(
        f"rotate_jwt_keys.php exited with code {result.returncode}: {stderr or stdout}"
    )
    raise self.retry(exc=exc)
