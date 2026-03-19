"""
Notification Cleanup Task
Celery task to cleanup notifications older than 90 days
Requirements: 27.5
"""

from celery import Celery
from datetime import datetime, timedelta
import psycopg2
import os

app = Celery('notification_cleanup', broker=os.getenv('RABBITMQ_URL', 'amqp://guest@rabbitmq//'))

@app.task(name='notification.cleanup')
def cleanup_old_notifications():
    """
    Delete notifications older than 90 days
    Runs nightly
    """
    try:
        conn = psycopg2.connect(
            host=os.getenv('DB_HOST', 'postgres'),
            database=os.getenv('DB_NAME', 'nexsaas'),
            user=os.getenv('DB_USER', 'postgres'),
            password=os.getenv('DB_PASSWORD', 'postgres')
        )
        
        cursor = conn.cursor()
        
        # Delete notifications older than 90 days
        cutoff_date = datetime.now() - timedelta(days=90)
        
        cursor.execute("""
            DELETE FROM notifications 
            WHERE created_at < %s
        """, (cutoff_date,))
        
        deleted_count = cursor.rowcount
        conn.commit()
        
        cursor.close()
        conn.close()
        
        print(f"Cleaned up {deleted_count} old notifications")
        
        return {
            'success': True,
            'deleted_count': deleted_count,
            'cutoff_date': cutoff_date.isoformat()
        }
        
    except Exception as e:
        print(f"Error cleaning up notifications: {str(e)}")
        return {
            'success': False,
            'error': str(e)
        }

if __name__ == '__main__':
    # For testing
    result = cleanup_old_notifications()
    print(result)
