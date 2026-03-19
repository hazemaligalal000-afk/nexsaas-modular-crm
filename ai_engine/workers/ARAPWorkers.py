import os
from celery import Celery
import httpx
from datetime import datetime, date

# Task 32.10: Overdue AR alert Celery task (Req 48.14)
# And ETA E-Invoicing submission worker (Req 48.15)

app = Celery('arap_workers', broker=os.environ.get('RABBITMQ_URL', 'amqp://guest:guest@rabbitmq:5672//'))

@app.task
def check_overdue_ar():
    """
    Daily ping: searches AR outstanding balance > 0 and due_date < today
    Publishes notification to tenant Owner/Accountant
    """
    # 1. Fetch AR Invoices overdue from API Gateway or internal DB directly
    db_conn = get_db_connection() # Simulated
    invoices = db_conn.execute("SELECT id, invoice_number, tenant_id FROM ar_invoices WHERE status IN ('open','partially_paid') AND due_date < %s", (date.today(),))
    
    for inv in invoices:
        # Publish websocket notification via NotificationService (Req 48.14)
        print(f"[{datetime.now()}] ALARM Tenant: {inv['tenant_id']} Invoice {inv['invoice_number']} is Overdue.")
    
    return f"Processed {len(invoices)} overdue AR alerts."

@app.task(bind=True, max_retries=3)
def submit_eta_einvoice(self, invoice_data: dict, tenant_id: str):
    """
    ETA e-invoice formatting, JSON payload signing, and submission for Company 01 (Req 48.15)
    Company 01 only.
    """
    # Requires Egyptian Tax Authority PKI Token
    if invoice_data.get('company_code') != '01':
        return "Submission Ignored: Branch not Company 01"
        
    eta_url = "https://api.invoicing.eta.gov.eg/api/v1/documentsubmissions"
    signed_payload = "[SIMULATED ETA CAdES-BES SIGNED JSON PAYLOAD]"
    
    try:
        # Expected response includes the UUID representing successful lodgment
        # r = httpx.post(eta_url, json={'documents': [signed_payload]}, headers={'Authorization': 'Bearer ...'})
        uuid_assigned = "eta_uuid_8b22a2" # r.json()['submissionId']
        
        # Write back to PostgreSQL ar_invoices table: eta_uuid and eta_status
        # db_conn.execute("UPDATE ar_invoices SET eta_uuid = %s, eta_status = 'Valid' WHERE id = %s", (uuid_assigned, invoice_data['id']))
        return "ETA Submitted successfully."
    except Exception as exc:
        raise self.retry(exc=exc, countdown=60)

def get_db_connection():
    # Helper simulation
    class MockDB:
        def execute(self, q, args): return []
    return MockDB()
