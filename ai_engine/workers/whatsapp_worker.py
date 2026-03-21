"""
ai_engine/workers/whatsapp_worker.py
Celery worker: Send messages via Meta WhatsApp Cloud API.
"""

from celery import Celery
import requests
import psycopg2
import os
import json

celery_app = Celery('whatsapp_worker', broker=os.getenv('RABBITMQ_URL', 'amqp://guest:guest@localhost//'))

def get_db():
    return psycopg2.connect(
        host=os.getenv('DB_HOST', 'postgres'),
        database=os.getenv('DB_NAME', 'nexsaas'),
        user=os.getenv('DB_USER', 'nexsaas'),
        password=os.getenv('DB_PASSWORD', 'secret')
    )

@celery_app.task(name='send_whatsapp_message', bind=True, max_retries=5)
def send_whatsapp_message(self, tenant_id: str, company_code: str, to: str, message: str, template: dict = None):
    """
    Sends a WhatsApp message via Meta Graph API.
    Requirement: 3.1 (SME Growth - WhatsApp Integration)
    """
    db = get_db()
    cur = db.cursor()
    
    # 1. Fetch Integration Config (token and phone_id) from 027_integration_configs table
    cur.execute(
        "SELECT config FROM integration_configs WHERE tenant_id = %s AND provider = 'whatsapp_meta' AND is_active = TRUE",
        (tenant_id,)
    )
    res = cur.fetchone()
    if not res:
        print(f"WhatsApp config not found for tenant {tenant_id}")
        return
    
    config = res[0]
    access_token = config.get('access_token')
    phone_number_id = config.get('phone_number_id')
    
    url = f"https://graph.facebook.com/v18.0/{phone_number_id}/messages"
    headers = {
        "Authorization": f"Bearer {access_token}",
        "Content-Type": "application/json"
    }

    if template:
        payload = {
            "messaging_product": "whatsapp",
            "to": to,
            "type": "template",
            "template": template
        }
    else:
        payload = {
            "messaging_product": "whatsapp",
            "recipient_type": "individual",
            "to": to,
            "type": "text",
            "text": {"body": message}
        }

    try:
        response = requests.post(url, headers=headers, json=payload, timeout=10)
        response.raise_for_status()
        
        # Log success in history (if table exists)
        # cur.execute("INSERT INTO whatsapp_history ...") 
        # db.commit()
    except Exception as exc:
        print(f"WhatsApp send failed: {str(exc)}")
        # Exponential backoff for retries
        raise self.retry(exc=exc, countdown=60 * (2 ** self.request.retries))
    finally:
        cur.close()
        db.close()
