"""
ERP/Invoicing/RecurringInvoiceTask.py

Celery task for generating recurring invoices automatically.

Requirements: 19.6
Task 20.3: Implement recurring invoice Celery task
"""

from celery import Task
from datetime import datetime, timedelta
import psycopg2
import psycopg2.extras
import json
import logging

logger = logging.getLogger(__name__)


class RecurringInvoiceTask(Task):
    """
    Celery task that runs daily to generate invoices from active recurring schedules.
    
    Requirement 19.6: Support recurring Invoice schedules - when a schedule's next_run 
    date is reached, auto-generate and send the Invoice.
    """
    
    name = 'erp.invoicing.recurring_invoice_task'
    
    def __init__(self):
        self.db_config = {
            'host': 'localhost',
            'port': 5432,
            'database': 'nexsaas',
            'user': 'postgres',
            'password': 'postgres'
        }
    
    def run(self, *args, **kwargs):
        """
        Main task execution: find due recurring schedules and generate invoices.
        """
        logger.info("Starting recurring invoice generation task")
        
        try:
            conn = psycopg2.connect(**self.db_config)
            conn.autocommit = False
            cursor = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
            
            # Find all active schedules due for generation
            schedules = self._get_due_schedules(cursor)
            logger.info(f"Found {len(schedules)} recurring schedules due for generation")
            
            generated_count = 0
            failed_count = 0
            
            for schedule in schedules:
                try:
                    invoice_id = self._generate_invoice_from_schedule(cursor, schedule)
                    self._update_schedule_next_run(cursor, schedule)
                    conn.commit()
                    
                    logger.info(f"Generated invoice {invoice_id} from schedule {schedule['id']}")
                    generated_count += 1
                    
                except Exception as e:
                    conn.rollback()
                    logger.error(f"Failed to generate invoice from schedule {schedule['id']}: {str(e)}")
                    failed_count += 1
            
            cursor.close()
            conn.close()
            
            logger.info(f"Recurring invoice task completed: {generated_count} generated, {failed_count} failed")
            
            return {
                'success': True,
                'generated_count': generated_count,
                'failed_count': failed_count
            }
            
        except Exception as e:
            logger.error(f"Recurring invoice task failed: {str(e)}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def _get_due_schedules(self, cursor):
        """
        Get all active recurring schedules where next_run_date <= today.
        """
        query = """
            SELECT id, tenant_id, company_code, template_name, account_id,
                   frequency, interval, start_date, end_date, next_run_date,
                   invoice_prefix, currency_code, payment_terms_days, line_items
            FROM recurring_invoice_schedules
            WHERE deleted_at IS NULL
              AND is_active = true
              AND next_run_date <= CURRENT_DATE
              AND (end_date IS NULL OR end_date >= CURRENT_DATE)
            ORDER BY next_run_date
        """
        
        cursor.execute(query)
        return cursor.fetchall()
    
    def _generate_invoice_from_schedule(self, cursor, schedule):
        """
        Generate a new invoice from a recurring schedule.
        
        Returns the new invoice ID.
        """
        # Get next invoice number
        invoice_number_data = self._get_next_invoice_number(
            cursor,
            schedule['tenant_id'],
            schedule['company_code'],
            schedule['invoice_prefix']
        )
        
        invoice_no = invoice_number_data['invoice_no']
        invoice_sequence = invoice_number_data['sequence']
        
        # Parse line items
        line_items = json.loads(schedule['line_items']) if isinstance(schedule['line_items'], str) else schedule['line_items']
        
        # Calculate totals
        subtotal = sum(item['quantity'] * item['unit_price'] for item in line_items)
        tax_amount = sum(item.get('tax_amount', 0) for item in line_items)
        discount_amount = sum(item.get('discount_amount', 0) for item in line_items)
        total_amount = subtotal + tax_amount - discount_amount
        
        # Get account details
        account = self._get_account(cursor, schedule['account_id'])
        
        # Calculate dates
        invoice_date = datetime.now().date()
        due_date = invoice_date + timedelta(days=schedule['payment_terms_days'])
        
        # Insert invoice header
        insert_invoice_query = """
            INSERT INTO invoices (
                tenant_id, company_code, invoice_no, invoice_prefix, invoice_sequence,
                account_id, customer_name, customer_email, billing_address,
                currency_code, exchange_rate,
                subtotal, tax_amount, discount_amount, total_amount,
                paid_amount, outstanding_balance,
                invoice_date, due_date,
                status, is_recurring, recurring_schedule_id,
                created_at, updated_at
            ) VALUES (
                %s, %s, %s, %s, %s,
                %s, %s, %s, %s,
                %s, %s,
                %s, %s, %s, %s,
                %s, %s,
                %s, %s,
                %s, %s, %s,
                NOW(), NOW()
            ) RETURNING id
        """
        
        cursor.execute(insert_invoice_query, (
            schedule['tenant_id'],
            schedule['company_code'],
            invoice_no,
            schedule['invoice_prefix'],
            invoice_sequence,
            schedule['account_id'],
            account['company_name'] if account else 'Unknown Customer',
            account.get('billing_email') if account else None,
            json.dumps(account.get('billing_address')) if account and account.get('billing_address') else None,
            schedule['currency_code'],
            1.0,  # Default exchange rate
            subtotal,
            tax_amount,
            discount_amount,
            total_amount,
            0.0,  # paid_amount
            total_amount,  # outstanding_balance
            invoice_date,
            due_date,
            'draft',  # Will be finalized separately
            True,  # is_recurring
            schedule['id']
        ))
        
        invoice_id = cursor.fetchone()['id']
        
        # Insert invoice lines
        for idx, item in enumerate(line_items, start=1):
            self._insert_invoice_line(cursor, invoice_id, schedule, idx, item)
        
        return invoice_id
    
    def _insert_invoice_line(self, cursor, invoice_id, schedule, line_no, item):
        """
        Insert a single invoice line item.
        """
        line_subtotal = item['quantity'] * item['unit_price']
        discount_amount = item.get('discount_amount', 0)
        tax_amount = item.get('tax_amount', 0)
        line_total = line_subtotal - discount_amount + tax_amount
        
        insert_line_query = """
            INSERT INTO invoice_lines (
                tenant_id, company_code, invoice_id, line_no,
                description, quantity, unit_price,
                line_subtotal, discount_pct, discount_amount,
                tax_rate, tax_amount, line_total,
                account_code,
                created_at, updated_at
            ) VALUES (
                %s, %s, %s, %s,
                %s, %s, %s,
                %s, %s, %s,
                %s, %s, %s,
                %s,
                NOW(), NOW()
            )
        """
        
        cursor.execute(insert_line_query, (
            schedule['tenant_id'],
            schedule['company_code'],
            invoice_id,
            line_no,
            item['description'],
            item['quantity'],
            item['unit_price'],
            line_subtotal,
            item.get('discount_pct', 0),
            discount_amount,
            item.get('tax_rate', 0),
            tax_amount,
            line_total,
            item.get('account_code')
        ))
    
    def _update_schedule_next_run(self, cursor, schedule):
        """
        Update the schedule's next_run_date based on frequency and interval.
        """
        current_next_run = schedule['next_run_date']
        frequency = schedule['frequency']
        interval = schedule['interval']
        
        # Calculate next run date
        if frequency == 'daily':
            next_run = current_next_run + timedelta(days=interval)
        elif frequency == 'weekly':
            next_run = current_next_run + timedelta(weeks=interval)
        elif frequency == 'monthly':
            # Add months (approximate with 30 days)
            next_run = current_next_run + timedelta(days=30 * interval)
        elif frequency == 'quarterly':
            next_run = current_next_run + timedelta(days=90 * interval)
        elif frequency == 'yearly':
            next_run = current_next_run + timedelta(days=365 * interval)
        else:
            next_run = current_next_run + timedelta(days=30)  # Default to monthly
        
        # Update schedule
        update_query = """
            UPDATE recurring_invoice_schedules
            SET next_run_date = %s,
                last_generated_at = NOW(),
                updated_at = NOW()
            WHERE id = %s
        """
        
        cursor.execute(update_query, (next_run, schedule['id']))
    
    def _get_next_invoice_number(self, cursor, tenant_id, company_code, prefix):
        """
        Get the next invoice number for the given prefix.
        """
        query = """
            SELECT COALESCE(MAX(invoice_sequence), 0) + 1 as next_seq
            FROM invoices
            WHERE tenant_id = %s
              AND company_code = %s
              AND invoice_prefix = %s
              AND deleted_at IS NULL
        """
        
        cursor.execute(query, (tenant_id, company_code, prefix))
        result = cursor.fetchone()
        next_seq = result['next_seq'] if result else 1
        
        invoice_no = f"{prefix}-{next_seq:06d}"
        
        return {
            'invoice_no': invoice_no,
            'sequence': next_seq
        }
    
    def _get_account(self, cursor, account_id):
        """
        Get account details.
        """
        if not account_id:
            return None
        
        query = """
            SELECT id, company_name, billing_address
            FROM accounts
            WHERE id = %s AND deleted_at IS NULL
        """
        
        cursor.execute(query, (account_id,))
        return cursor.fetchone()


# Register task with Celery
def register_task(celery_app):
    """
    Register the recurring invoice task with the Celery app.
    """
    celery_app.register_task(RecurringInvoiceTask())
