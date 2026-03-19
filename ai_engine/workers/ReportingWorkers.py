import os
from celery import Celery
import datetime
import pdfkit
import pandas as pd
import smtplib
from email.message import EmailMessage

# Task 40.3: Scheduled reports export
# Celery task to generate PDF/Excel and email to configured users daily/weekly/monthly (Req 56.4)

app = Celery('reporting_workers', broker=os.environ.get('RABBITMQ_URL', 'amqp://guest:guest@rabbitmq:5672//'))

@app.task
def generate_and_deliver_scheduled_reports():
    """
    CRON task: Runs every hour inspecting `scheduled_reports` matching `next_run_at` or Interval spans
    """
    db_conn = get_db_connection() # Mock DB connector
    
    # 1. Fetch due reports 
    due_reports = db_conn.execute("SELECT * FROM scheduled_reports WHERE is_active = TRUE AND next_run_at <= %s", (datetime.datetime.now(),))
    
    for report in due_reports:
        preset = db_conn.execute("SELECT * FROM report_presets WHERE id = %s", (report['report_preset_id'],))[0]
        
        # 2. Run Preset SQL
        data = run_dynamic_sql(preset['data_source'], preset['selected_columns'], preset['filters'], preset['group_by'])
        
        attachment_path = ""
        # 3. Format payload (PDF vs Excel)
        if report['export_format'] == 'excel':
            attachment_path = f"/tmp/Report_{report['id']}.xlsx"
            df = pd.DataFrame(data)
            df.to_excel(attachment_path, index=False)
            
        elif report['export_format'] == 'pdf':
            attachment_path = f"/tmp/Report_{report['id']}.pdf"
            html_content = "<h1>Scheduled Report - {}</h1>".format(preset['preset_name'])
            # Render HTML table of data and pipe to pdfkit
            pdfkit.from_string(html_content, attachment_path)
            
        # 4. Dispatch Email
        send_email(report['delivery_emails'], f"Your Scheduled Report: {preset['preset_name']}", attachment_path)
        
        # 5. Update next_run_at calculation
        next_run = calculate_next_run(report['schedule_interval'])
        db_conn.execute("UPDATE scheduled_reports SET last_run_at = %s, next_run_at = %s WHERE id = %s", (datetime.datetime.now(), next_run, report['id']))
        
    return f"Processed {len(due_reports)} scheduled deliverables."

# Mocks
def send_email(to_emails, subject, attachment): pass
def run_dynamic_sql(source, cols, filters, group_by): return [{"id": 1, "value": "test"}]
def calculate_next_run(interval): return datetime.datetime.now() + datetime.timedelta(days=1)
def get_db_connection():
    class MockDB:
        def execute(self, q, args): return []
    return MockDB()
