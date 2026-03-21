"""
ai_engine/app/celery_app.py
Central Celery application instance for the AI Engine.
"""

from celery import Celery
import os

celery_app = Celery(
    'nexsaas_ai',
    broker=os.getenv('RABBITMQ_URL', 'amqp://nexsaas:secret@rabbitmq:5672/nexsaas'),
    include=[
        'workers.workflow_executor',
        'workers.whatsapp_worker',
        'workers.email_sender'
    ]
)

celery_app.conf.update(
    task_serializer='json',
    accept_content=['json'],
    result_serializer='json',
    timezone='UTC',
    enable_utc=True,
    task_acks_late=True,
    task_reject_on_worker_lost=True,
    worker_prefetch_multiplier=1
)
