#!/usr/bin/env python3
"""
ai_engine/workers/cti_screen_pop.py

Handle inbound call events, trigger screen pop via WebSocket.
Consumes: call.inbound.ringing
"""

import json
import logging
import os
import sys
from datetime import datetime, timezone

import pika
import psycopg2
import redis
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

# ── Redis connection (for WebSocket pub/sub) ─────────────────────────────────
def get_redis():
    return redis.Redis(
        host=os.getenv('REDIS_HOST', 'localhost'),
        port=int(os.getenv('REDIS_PORT', '6379')),
        db=0,
        decode_responses=True
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

# ── Process screen pop ───────────────────────────────────────────────────────
def process_screen_pop(payload: dict):
    """
    Handle inbound call:
    1. Lookup contact by phone (3 strategies)
    2. Load contact/deal/ticket context
    3. Log screen pop event
    4. Publish to WebSocket channel for agent UI
    """
    call_sid = payload['call_sid']
    tenant_id = payload['tenant_id']
    from_number = payload['from_number']
    to_number = payload['to_number']
    agent_id = payload.get('agent_id')
    
    logger.info(f"Screen pop for call {call_sid} from {from_number}")
    
    db = get_db()
    cur = db.cursor()
    redis_client = get_redis()
    
    try:
        # Lookup contact
        contact = lookup_contact(cur, tenant_id, from_number)
        
        # Load related context
        context = {
            'call_sid': call_sid,
            'from_number': from_number,
            'to_number': to_number,
            'contact': contact,
            'deals': [],
            'tickets': [],
            'recent_calls': [],
        }
        
        if contact:
            context['deals'] = load_recent_deals(cur, tenant_id, contact['id'])
            context['tickets'] = load_recent_tickets(cur, tenant_id, contact['id'])
            context['recent_calls'] = load_recent_calls(cur, tenant_id, contact['id'])
        
        # Log screen pop event
        log_screen_pop(cur, tenant_id, call_sid, contact, agent_id)
        db.commit()
        
        # Publish to WebSocket channel
        if agent_id:
            channel = f"agent:{agent_id}:screen_pop"
            redis_client.publish(channel, json.dumps({
                'type': 'screen_pop',
                'data': context
            }))
            logger.info(f"Screen pop published to {channel}")
        
    except Exception as e:
        db.rollback()
        logger.error(f"Screen pop failed for {call_sid}: {e}", exc_info=True)
        raise
    finally:
        cur.close()
        db.close()

def lookup_contact(cur, tenant_id: str, phone: str) -> dict:
    """
    Lookup contact by phone using 3 strategies:
    1. Exact match
    2. Normalized match (strip country code)
    3. Fuzzy match (last 8 digits)
    """
    # Strategy 1: Exact match
    cur.execute(
        "SELECT * FROM contacts WHERE tenant_id = %s AND phone = %s AND deleted_at IS NULL LIMIT 1",
        [tenant_id, phone]
    )
    row = cur.fetchone()
    if row:
        return dict(zip([desc[0] for desc in cur.description], row))
    
    # Strategy 2: Normalized (strip +20 for Egypt)
    normalized = phone.lstrip('+').lstrip('20') if phone.startswith('+20') else phone
    cur.execute(
        "SELECT * FROM contacts WHERE tenant_id = %s AND (phone = %s OR phone LIKE %s) AND deleted_at IS NULL LIMIT 1",
        [tenant_id, normalized, f'%{normalized}']
    )
    row = cur.fetchone()
    if row:
        return dict(zip([desc[0] for desc in cur.description], row))
    
    # Strategy 3: Fuzzy (last 8 digits)
    last_8 = phone[-8:] if len(phone) >= 8 else phone
    cur.execute(
        "SELECT * FROM contacts WHERE tenant_id = %s AND phone LIKE %s AND deleted_at IS NULL LIMIT 1",
        [tenant_id, f'%{last_8}']
    )
    row = cur.fetchone()
    if row:
        return dict(zip([desc[0] for desc in cur.description], row))
    
    return None

def load_recent_deals(cur, tenant_id: str, contact_id: int) -> list:
    """Load recent deals for contact."""
    cur.execute(
        """SELECT id, name, stage, amount, currency, probability, close_date
           FROM deals
           WHERE tenant_id = %s AND contact_id = %s AND deleted_at IS NULL
           ORDER BY updated_at DESC LIMIT 5""",
        [tenant_id, contact_id]
    )
    return [dict(zip([desc[0] for desc in cur.description], row)) for row in cur.fetchall()]

def load_recent_tickets(cur, tenant_id: str, contact_id: int) -> list:
    """Load recent support tickets for contact."""
    cur.execute(
        """SELECT id, subject, status, priority, created_at
           FROM tickets
           WHERE tenant_id = %s AND contact_id = %s AND deleted_at IS NULL
           ORDER BY created_at DESC LIMIT 5""",
        [tenant_id, contact_id]
    )
    return [dict(zip([desc[0] for desc in cur.description], row)) for row in cur.fetchall()]

def load_recent_calls(cur, tenant_id: str, contact_id: int) -> list:
    """Load recent call history for contact."""
    cur.execute(
        """SELECT call_sid, direction, initiated_at, duration_seconds, disposition_code, ai_summary
           FROM call_log
           WHERE tenant_id = %s AND contact_id = %s AND deleted_at IS NULL
           ORDER BY initiated_at DESC LIMIT 10""",
        [tenant_id, contact_id]
    )
    return [dict(zip([desc[0] for desc in cur.description], row)) for row in cur.fetchall()]

def log_screen_pop(cur, tenant_id: str, call_sid: str, contact: dict, agent_id: str):
    """Log screen pop event."""
    now = datetime.now(timezone.utc).isoformat()
    cur.execute(
        """INSERT INTO cti_screen_pop_log (tenant_id, company_code, call_sid, agent_id, contact_id, lookup_strategy, popped_at, created_at)
           VALUES (%s, %s, %s, %s, %s, %s, %s, %s)""",
        [tenant_id, '01', call_sid, agent_id, contact['id'] if contact else None, 'exact' if contact else 'none', now, now]
    )

# ── RabbitMQ consumer ────────────────────────────────────────────────────────
def callback(ch, method, properties, body):
    try:
        payload = json.loads(body)
        process_screen_pop(payload)
        ch.basic_ack(delivery_tag=method.delivery_tag)
    except Exception as e:
        logger.error(f"Worker error: {e}", exc_info=True)
        ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

def main():
    logger.info("CTI Screen Pop Worker starting...")
    
    connection = get_rabbitmq()
    channel = connection.channel()
    
    channel.queue_declare(queue='call.inbound.ringing', durable=True)
    channel.basic_qos(prefetch_count=1)
    channel.basic_consume(queue='call.inbound.ringing', on_message_callback=callback)
    
    logger.info("Waiting for inbound calls...")
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        logger.info("Shutting down...")
        channel.stop_consuming()
    finally:
        connection.close()

if __name__ == '__main__':
    main()
