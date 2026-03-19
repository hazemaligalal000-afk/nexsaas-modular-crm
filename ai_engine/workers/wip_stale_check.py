"""
ai_engine/workers/wip_stale_check.py

Celery worker: Check for stale WIP accounts (no movement > 90 days) and notify Accountant.
"""

from celery import Celery
import psycopg2
import os
from datetime import datetime, timedelta
import json

celery_app = Celery('wip_stale_check', broker=os.getenv('RABBITMQ_URL', 'amqp://guest:guest@localhost//'))

def get_db():
    return psycopg2.connect(
        host=os.getenv('DB_HOST', 'localhost'),
        database=os.getenv('DB_NAME', 'nexsaas'),
        user=os.getenv('DB_USER', 'postgres'),
        password=os.getenv('DB_PASSWORD', '')
    )

@celery_app.task(name='check_stale_wip_accounts', bind=True)
def check_stale_wip_accounts(self, tenant_id: str = None, company_code: str = None, days: int = 90):
    """
    Check for WIP accounts with no movement in specified days.
    
    Args:
        tenant_id: Optional tenant ID to check (if None, checks all tenants)
        company_code: Optional company code to check (if None, checks all companies)
        days: Number of days to consider an account stale (default: 90)
    
    Returns:
        dict: Summary of stale accounts found and notifications sent
    """
    db = get_db()
    cur = db.cursor()
    
    try:
        # Build query to find stale WIP accounts
        query = """
            SELECT 
                coa.tenant_id,
                coa.company_code,
                coa.account_code,
                coa.account_name_en,
                coa.account_name_ar,
                MAX(jel.voucher_date) as last_movement_date,
                CURRENT_DATE - MAX(jel.voucher_date) as days_since_movement,
                COALESCE(SUM(jel.dr_value_egp) - SUM(jel.cr_value_egp), 0) as current_balance
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_lines jel ON coa.account_code = jel.account_code 
                AND coa.tenant_id = jel.tenant_id 
                AND coa.company_code = jel.company_code
                AND jel.deleted_at IS NULL
            WHERE coa.account_subtype = 'WIP'
              AND coa.is_active = TRUE
              AND coa.deleted_at IS NULL
        """
        
        params = []
        
        if tenant_id:
            query += " AND coa.tenant_id = %s"
            params.append(tenant_id)
        
        if company_code:
            query += " AND coa.company_code = %s"
            params.append(company_code)
        
        query += """
            GROUP BY coa.tenant_id, coa.company_code, coa.account_code, 
                     coa.account_name_en, coa.account_name_ar
            HAVING MAX(jel.voucher_date) IS NULL 
                OR CURRENT_DATE - MAX(jel.voucher_date) > %s
            ORDER BY days_since_movement DESC NULLS FIRST
        """
        params.append(days)
        
        cur.execute(query, params)
        stale_accounts = cur.fetchall()
        
        if not stale_accounts:
            return {
                'status': 'success',
                'stale_accounts_found': 0,
                'notifications_sent': 0,
                'message': 'No stale WIP accounts found'
            }
        
        # Process each stale account
        notifications_sent = 0
        stale_account_list = []
        
        for account in stale_accounts:
            account_dict = {
                'tenant_id': account[0],
                'company_code': account[1],
                'account_code': account[2],
                'account_name_en': account[3],
                'account_name_ar': account[4],
                'last_movement_date': account[5].isoformat() if account[5] else None,
                'days_since_movement': account[6] if account[6] else None,
                'current_balance': float(account[7])
            }
            stale_account_list.append(account_dict)
            
            # Find Accountant users for this tenant and company
            cur.execute("""
                SELECT id, email, full_name
                FROM users
                WHERE tenant_id = %s 
                  AND company_code = %s
                  AND accounting_role IN ('Accountant', 'Admin', 'Owner')
                  AND is_active = TRUE
                  AND deleted_at IS NULL
            """, (account[0], account[1]))
            
            accountants = cur.fetchall()
            
            # Create notification for each accountant
            for accountant in accountants:
                notification_payload = {
                    'type': 'wip_stale_alert',
                    'account_code': account[2],
                    'account_name': account[3],
                    'last_movement_date': account[5].isoformat() if account[5] else 'Never',
                    'days_since_movement': account[6] if account[6] else 'N/A',
                    'current_balance': float(account[7]),
                    'company_code': account[1]
                }
                
                cur.execute("""
                    INSERT INTO notifications 
                    (tenant_id, company_code, user_id, type, payload, created_at)
                    VALUES (%s, %s, %s, %s, %s, NOW())
                """, (
                    account[0],
                    account[1],
                    accountant[0],
                    'wip_stale_alert',
                    json.dumps(notification_payload)
                ))
                
                notifications_sent += 1
        
        db.commit()
        
        return {
            'status': 'success',
            'stale_accounts_found': len(stale_accounts),
            'notifications_sent': notifications_sent,
            'stale_accounts': stale_account_list,
            'message': f'Found {len(stale_accounts)} stale WIP accounts, sent {notifications_sent} notifications'
        }
        
    except Exception as e:
        db.rollback()
        return {
            'status': 'error',
            'error': str(e),
            'message': f'Failed to check stale WIP accounts: {str(e)}'
        }
    finally:
        cur.close()
        db.close()


@celery_app.task(name='schedule_wip_stale_check_all_tenants')
def schedule_wip_stale_check_all_tenants():
    """
    Scheduled task to check all tenants for stale WIP accounts.
    Should be run monthly via Celery Beat.
    """
    db = get_db()
    cur = db.cursor()
    
    try:
        # Get all active tenants
        cur.execute("""
            SELECT DISTINCT tenant_id, company_code
            FROM chart_of_accounts
            WHERE account_subtype = 'WIP'
              AND is_active = TRUE
              AND deleted_at IS NULL
        """)
        
        tenant_companies = cur.fetchall()
        
        results = []
        for tenant_id, company_code in tenant_companies:
            result = check_stale_wip_accounts(tenant_id=tenant_id, company_code=company_code)
            results.append({
                'tenant_id': tenant_id,
                'company_code': company_code,
                'result': result
            })
        
        return {
            'status': 'success',
            'tenants_checked': len(tenant_companies),
            'results': results
        }
        
    except Exception as e:
        return {
            'status': 'error',
            'error': str(e)
        }
    finally:
        cur.close()
        db.close()


# Configure Celery Beat schedule (add to celeryconfig.py)
celery_app.conf.beat_schedule = {
    'check-stale-wip-monthly': {
        'task': 'schedule_wip_stale_check_all_tenants',
        'schedule': 2592000.0,  # 30 days in seconds
    },
}
