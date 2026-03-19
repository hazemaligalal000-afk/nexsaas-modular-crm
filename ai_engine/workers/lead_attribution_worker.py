#!/usr/bin/env python3
"""
ai_engine/workers/lead_attribution_worker.py

Process ad lead webhooks, auto-link to contacts, send CAPI events.
Consumes: lead.attribution.webhook
"""

import json
import logging
import os
import sys
from datetime import datetime, timezone

import pika
import psycopg2
import requests

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger(__name__)

# ── Database connection ──────────────────────────────────────────────────────
def get_db():
    return psycopg2.connect(
        host=os.getenv('DB_HOST', 'localhost'),
        port=int(os.getenv('DB_PORT', '5432')),
        database=os.getenv('DB_NAME', 'nexsaas'),
        user=os.getenv('DB_USER', 'postgres'),
        password=os.getenv('DB_PASSWORD', '')
    )

# ── RabbitMQ connection ──────────────────────────────────────────────────────
def get_rabbitmq():
    return pika.BlockingConnection(pika.ConnectionParameters(
        host=os.getenv('RABBITMQ_HOST', 'localhost'),
        port=int(os.getenv('RABBITMQ_PORT', '5672')),
        credentials=pika.PlainCredentials(
            os.getenv('RABBITMQ_USER', 'guest'),
            os.getenv('RABBITMQ_PASS', 'guest')
        )
    ))

# ── Process attribution webhook ──────────────────────────────────────────────
def process_attribution(payload: dict):
    """
    Process ad lead webhook:
    1. Extract lead data + UTM/click IDs
    2. Find or create contact
    3. Create lead_attributions row
    4. Send CAPI event back to platform
    """
    tenant_id = payload['tenant_id']
    platform = payload['platform']  # meta|google|tiktok
    lead_data = payload['lead_data']
    
    logger.info(f"Processing {platform} lead for tenant {tenant_id}")
    
    db = get_db()
    cur = db.cursor()
    
    try:
        # Extract contact info
        email = lead_data.get('email', '').strip().lower()
        phone = lead_data.get('phone', '').strip()
        first_name = lead_data.get('first_name', '')
        last_name = lead_data.get('last_name', '')
        
        if not email and not phone:
            logger.warning("No email or phone in lead data, skipping")
            return
        
        # Find or create contact
        contact_id = find_or_create_contact(cur, tenant_id, email, phone, first_name, last_name)
        
        # Create attribution record
        attribution_id = create_attribution(cur, tenant_id, contact_id, platform, lead_data)
        
        # Send CAPI event
        send_capi_event(platform, tenant_id, lead_data, attribution_id)
        
        db.commit()
        logger.info(f"Attribution {attribution_id} created for contact {contact_id}")
        
    except Exception as e:
        db.rollback()
        logger.error(f"Attribution processing failed: {e}", exc_info=True)
        raise
    finally:
        cur.close()
        db.close()

def find_or_create_contact(cur, tenant_id: str, email: str, phone: str, first_name: str, last_name: str) -> int:
    """Find existing contact or create new one."""
    # Try to find by email or phone
    conditions = []
    params = [tenant_id, '01']  # Default company_code
    
    if email:
        conditions.append("LOWER(email) = %s")
        params.append(email)
    if phone:
        conditions.append("phone = %s")
        params.append(phone)
    
    if conditions:
        cur.execute(
            f"SELECT id FROM contacts WHERE tenant_id = %s AND company_code = %s AND ({' OR '.join(conditions)}) AND deleted_at IS NULL LIMIT 1",
            params
        )
        row = cur.fetchone()
        if row:
            return row[0]
    
    # Create new contact
    now = datetime.now(timezone.utc).isoformat()
    cur.execute(
        """INSERT INTO contacts (tenant_id, company_code, first_name, last_name, email, phone, source, created_at, updated_at, created_by)
           VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s) RETURNING id""",
        [tenant_id, '01', first_name, last_name, email, phone, 'ad_platform', now, now, 'system']
    )
    return cur.fetchone()[0]

def create_attribution(cur, tenant_id: str, contact_id: int, platform: str, lead_data: dict) -> int:
    """Create lead_attributions record."""
    now = datetime.now(timezone.utc).isoformat()
    
    cur.execute(
        """INSERT INTO lead_attributions (
            tenant_id, company_code, contact_id, source_platform, source_medium, source_campaign,
            utm_source, utm_medium, utm_campaign, utm_term, utm_content,
            click_id_fb, click_id_google, click_id_tiktok,
            ad_id, ad_name, adset_id, adset_name, campaign_id, campaign_name,
            landing_page, referrer, device_type, browser, ip_address,
            raw_attribution_data, created_at, updated_at
        ) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
        RETURNING id""",
        [
            tenant_id, '01', contact_id, platform, 'paid_social', lead_data.get('campaign_name', ''),
            lead_data.get('utm_source'), lead_data.get('utm_medium'), lead_data.get('utm_campaign'),
            lead_data.get('utm_term'), lead_data.get('utm_content'),
            lead_data.get('fbclid'), lead_data.get('gclid'), lead_data.get('ttclid'),
            lead_data.get('ad_id'), lead_data.get('ad_name'),
            lead_data.get('adset_id'), lead_data.get('adset_name'),
            lead_data.get('campaign_id'), lead_data.get('campaign_name'),
            lead_data.get('landing_page'), lead_data.get('referrer'),
            lead_data.get('device_type'), lead_data.get('browser'), lead_data.get('ip_address'),
            json.dumps(lead_data), now, now
        ]
    )
    return cur.fetchone()[0]

def send_capi_event(platform: str, tenant_id: str, lead_data: dict, attribution_id: int):
    """Send conversion event back to ad platform via CAPI."""
    try:
        if platform == 'meta':
            send_meta_capi(tenant_id, lead_data, attribution_id)
        elif platform == 'google':
            send_google_enhanced_conversion(tenant_id, lead_data, attribution_id)
        elif platform == 'tiktok':
            send_tiktok_events_api(tenant_id, lead_data, attribution_id)
    except Exception as e:
        logger.error(f"CAPI send failed for {platform}: {e}")

def send_meta_capi(tenant_id: str, lead_data: dict, attribution_id: int):
    """Send Meta Conversions API event."""
    # Load integration config (simplified - in production decrypt from DB)
    pixel_id = os.getenv('META_PIXEL_ID')
    access_token = os.getenv('META_ACCESS_TOKEN')
    
    if not pixel_id or not access_token:
        logger.warning("Meta CAPI credentials not configured")
        return
    
    url = f"https://graph.facebook.com/v18.0/{pixel_id}/events"
    
    payload = {
        "data": [{
            "event_name": "Lead",
            "event_time": int(datetime.now(timezone.utc).timestamp()),
            "event_source_url": lead_data.get('landing_page', ''),
            "action_source": "website",
            "user_data": {
                "em": [lead_data.get('email', '')],
                "ph": [lead_data.get('phone', '')],
                "client_ip_address": lead_data.get('ip_address'),
                "client_user_agent": lead_data.get('user_agent'),
                "fbc": lead_data.get('fbclid'),
                "fbp": lead_data.get('fbp'),
            },
            "custom_data": {
                "attribution_id": attribution_id,
                "tenant_id": tenant_id,
            }
        }],
        "access_token": access_token
    }
    
    resp = requests.post(url, json=payload, timeout=10)
    if resp.status_code == 200:
        logger.info(f"Meta CAPI event sent for attribution {attribution_id}")
    else:
        logger.error(f"Meta CAPI failed: {resp.status_code} {resp.text}")

def send_google_enhanced_conversion(tenant_id: str, lead_data: dict, attribution_id: int):
    """Send Google Ads Enhanced Conversions."""
    logger.info(f"Google Enhanced Conversion stub for attribution {attribution_id}")
    # Implementation: Google Ads API offline conversion upload

def send_tiktok_events_api(tenant_id: str, lead_data: dict, attribution_id: int):
    """Send TikTok Events API event."""
    logger.info(f"TikTok Events API stub for attribution {attribution_id}")
    # Implementation: TikTok Events API

# ── RabbitMQ consumer ────────────────────────────────────────────────────────
def callback(ch, method, properties, body):
    try:
        payload = json.loads(body)
        process_attribution(payload)
        ch.basic_ack(delivery_tag=method.delivery_tag)
    except Exception as e:
        logger.error(f"Worker error: {e}", exc_info=True)
        ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

def main():
    logger.info("Lead Attribution Worker starting...")
    
    connection = get_rabbitmq()
    channel = connection.channel()
    
    channel.queue_declare(queue='lead.attribution.webhook', durable=True)
    channel.basic_qos(prefetch_count=1)
    channel.basic_consume(queue='lead.attribution.webhook', on_message_callback=callback)
    
    logger.info("Waiting for attribution webhooks...")
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        logger.info("Shutting down...")
        channel.stop_consuming()
    finally:
        connection.close()

if __name__ == '__main__':
    main()
