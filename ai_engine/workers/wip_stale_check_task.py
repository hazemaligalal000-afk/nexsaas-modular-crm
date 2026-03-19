"""
WIP Stale Balance Check Celery Task
Task 56.4
Requirement 57.4
"""

from celery import Celery
from datetime import datetime
import sys
sys.path.append('..')
from app.accounting_ai import flag_stale_wip_balances

# Celery configuration
celery_app = Celery(
    'wip_stale_check',
    broker='redis://localhost:6379/0',
    backend='redis://localhost:6379/0'
)


@celery_app.task(name='accounting.wip_stale_check')
def check_stale_wip_balances(tenant_id: str):
    """
    Monthly Celery task to flag WIP accounts with no movement > 90 days.
    
    Requirement 57.4: Notify Accountant of stale WIP balances
    """
    companies = ['01', '02', '03', '04', '05', '06']
    results = {}
    
    for company_code in companies:
        try:
            result = flag_stale_wip_balances(tenant_id, company_code)
            results[company_code] = result
            
            # If stale accounts found, send notification
            if result['stale_account_count'] > 0:
                # In production, send email/notification to Accountant
                print(f"[WIP Alert] Company {company_code}: {result['stale_account_count']} stale WIP accounts")
                
        except Exception as e:
            results[company_code] = {
                'success': False,
                'error': str(e)
            }
    
    return {
        'tenant_id': tenant_id,
        'check_date': datetime.now().isoformat(),
        'results': results
    }


# Schedule configuration (add to celerybeat-schedule.py)
"""
CELERYBEAT_SCHEDULE = {
    'monthly-wip-stale-check': {
        'task': 'accounting.wip_stale_check',
        'schedule': crontab(day_of_month=1, hour=9, minute=0),  # First day of month at 9 AM
    },
}
"""
