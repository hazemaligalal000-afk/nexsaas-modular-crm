"""
Webhook Delivery Task
Celery worker for async webhook delivery with retry logic
Requirements: 31.2, 31.3, 31.4, 31.5
"""

from celery import Celery
import requests
import hmac
import hashlib
import json
import time
import os
import psycopg2

app = Celery('webhook_delivery', broker=os.getenv('RABBITMQ_URL', 'amqp://guest@rabbitmq//'))

@app.task(name='webhook.deliver', bind=True, max_retries=5)
def deliver_webhook(self, delivery_id, webhook_id, url, secret, event_type, payload, attempt=1):
    """
    Deliver webhook via HTTP POST with HMAC-SHA256 signature
    Retry up to 5 times with exponential backoff over 24 hours
    """
    try:
        # Generate HMAC-SHA256 signature
        payload_json = json.dumps(payload)
        signature = hmac.new(
            secret.encode('utf-8'),
            payload_json.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()
        
        # Send HTTP POST request with 10s timeout
        headers = {
            'Content-Type': 'application/json',
            'X-Webhook-Signature': signature,
            'X-Webhook-Event': event_type,
            'X-Webhook-Delivery': str(delivery_id)
        }
        
        response = requests.post(
            url,
            data=payload_json,
            headers=headers,
            timeout=10
        )
        
        # Update delivery record
        conn = get_db_connection()
        cursor = conn.cursor()
        
        if 200 <= response.status_code < 300:
            # Success
            cursor.execute("""
                UPDATE webhook_deliveries SET
                    status = 'success',
                    http_status_code = %s,
                    response_body = %s,
                    delivered_at = NOW(),
                    attempt_number = %s
                WHERE id = %s
            """, (response.status_code, response.text[:1000], attempt, delivery_id))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"Webhook {webhook_id} delivered successfully (delivery {delivery_id})")
            return {
                'success': True,
                'delivery_id': delivery_id,
                'http_status': response.status_code
            }
        else:
            # Failed - record and retry
            cursor.execute("""
                UPDATE webhook_deliveries SET
                    status = 'failed',
                    http_status_code = %s,
                    response_body = %s,
                    error_message = %s,
                    attempt_number = %s
                WHERE id = %s
            """, (
                response.status_code,
                response.text[:1000],
                f'HTTP {response.status_code}',
                attempt,
                delivery_id
            ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            # Retry with exponential backoff if attempts remaining
            if attempt < 5:
                # Delays: 60s, 300s, 1800s, 7200s, 43200s
                delays = [60, 300, 1800, 7200, 43200]
                delay = delays[attempt - 1]
                
                print(f"Webhook delivery {delivery_id} failed (attempt {attempt}), retrying in {delay}s")
                
                raise self.retry(
                    args=[delivery_id, webhook_id, url, secret, event_type, payload, attempt + 1],
                    countdown=delay
                )
            else:
                print(f"Webhook delivery {delivery_id} failed after {attempt} attempts")
                return {
                    'success': False,
                    'delivery_id': delivery_id,
                    'error': f'Failed after {attempt} attempts'
                }
                
    except requests.exceptions.Timeout:
        # Timeout - retry
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE webhook_deliveries SET
                status = 'failed',
                error_message = 'Request timeout',
                attempt_number = %s
            WHERE id = %s
        """, (attempt, delivery_id))
        conn.commit()
        cursor.close()
        conn.close()
        
        if attempt < 5:
            delays = [60, 300, 1800, 7200, 43200]
            delay = delays[attempt - 1]
            raise self.retry(
                args=[delivery_id, webhook_id, url, secret, event_type, payload, attempt + 1],
                countdown=delay
            )
        
    except Exception as e:
        # Other errors - retry
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE webhook_deliveries SET
                status = 'failed',
                error_message = %s,
                attempt_number = %s
            WHERE id = %s
        """, (str(e)[:500], attempt, delivery_id))
        conn.commit()
        cursor.close()
        conn.close()
        
        if attempt < 5:
            delays = [60, 300, 1800, 7200, 43200]
            delay = delays[attempt - 1]
            raise self.retry(
                args=[delivery_id, webhook_id, url, secret, event_type, payload, attempt + 1],
                countdown=delay
            )
        
        raise

@app.task(name='webhook.cleanup')
def cleanup_old_deliveries():
    """
    Cleanup webhook deliveries older than 30 days
    Runs daily
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            DELETE FROM webhook_deliveries 
            WHERE created_at < NOW() - INTERVAL '30 days'
        """)
        
        deleted_count = cursor.rowcount
        conn.commit()
        cursor.close()
        conn.close()
        
        print(f"Cleaned up {deleted_count} old webhook deliveries")
        
        return {
            'success': True,
            'deleted_count': deleted_count
        }
        
    except Exception as e:
        print(f"Error cleaning up webhook deliveries: {str(e)}")
        return {
            'success': False,
            'error': str(e)
        }

def get_db_connection():
    """Get PostgreSQL database connection"""
    return psycopg2.connect(
        host=os.getenv('DB_HOST', 'postgres'),
        database=os.getenv('DB_NAME', 'nexsaas'),
        user=os.getenv('DB_USER', 'postgres'),
        password=os.getenv('DB_PASSWORD', 'postgres')
    )

if __name__ == '__main__':
    # For testing
    test_payload = {
        'event': 'test.event',
        'data': {'message': 'Hello webhook'}
    }
    result = deliver_webhook(1, 1, 'https://example.com/webhook', 'test_secret', 'test.event', test_payload, 1)
    print(result)
