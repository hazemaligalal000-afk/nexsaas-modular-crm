"""
ai_engine/workers/email_sender.py

Celery worker: send emails via multiple providers (SendGrid, Mailgun, SES, SMTP).
"""

from celery import Celery
import psycopg2
import json
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
import requests
import boto3
import os

celery_app = Celery('email_sender', broker=os.getenv('RABBITMQ_URL', 'amqp://guest:guest@localhost//'))

def get_db():
    return psycopg2.connect(
        host=os.getenv('DB_HOST', 'localhost'),
        database=os.getenv('DB_NAME', 'nexsaas'),
        user=os.getenv('DB_USER', 'postgres'),
        password=os.getenv('DB_PASSWORD', '')
    )

def decrypt(encrypted: str) -> str:
    # Simplified — in production use proper AES-256-CBC decryption
    return encrypted

@celery_app.task(name='send_single_email', bind=True, max_retries=3)
def send_single_email(self, send_id: int, tenant_id: str):
    db = get_db()
    cur = db.cursor()

    # Fetch send record
    cur.execute('SELECT * FROM email_sends WHERE id = %s', (send_id,))
    send = cur.fetchone()
    if not send:
        return

    send_dict = dict(zip([desc[0] for desc in cur.description], send))

    # Fetch brand settings
    cur.execute(
        'SELECT * FROM email_brand_settings WHERE tenant_id = %s AND company_code = %s',
        (tenant_id, send_dict['company_code'])
    )
    brand = cur.fetchone()
    if not brand:
        return

    brand_dict = dict(zip([desc[0] for desc in cur.description], brand))
    provider = brand_dict.get('smtp_provider', 'smtp')

    # Check unsubscribe list
    cur.execute(
        'SELECT 1 FROM email_unsubscribes WHERE tenant_id = %s AND email = %s',
        (tenant_id, send_dict['to_email'])
    )
    if cur.fetchone():
        cur.execute(
            "UPDATE email_sends SET status='unsubscribed' WHERE id=%s",
            (send_id,)
        )
        db.commit()
        return

    try:
        message_id = None

        if provider == 'sendgrid':
            message_id = send_via_sendgrid(send_dict, brand_dict)
        elif provider == 'mailgun':
            message_id = send_via_mailgun(send_dict, brand_dict)
        elif provider == 'ses':
            message_id = send_via_ses(send_dict, brand_dict)
        else:
            message_id = send_via_smtp(send_dict, brand_dict)

        cur.execute(
            "UPDATE email_sends SET status='sent', sent_at=NOW(), message_id=%s WHERE id=%s",
            (message_id, send_id)
        )
        db.commit()

    except Exception as exc:
        retry_count = self.request.retries
        countdown = 60 * (2 ** retry_count)

        cur.execute(
            "UPDATE email_sends SET retry_count=%s, fail_reason=%s WHERE id=%s",
            (retry_count + 1, str(exc), send_id)
        )
        db.commit()

        if retry_count >= 2:
            cur.execute(
                "UPDATE email_sends SET status='failed', failed_at=NOW() WHERE id=%s",
                (send_id,)
            )
            db.commit()
            return

        raise self.retry(exc=exc, countdown=countdown)
    finally:
        cur.close()
        db.close()


def send_via_sendgrid(send: dict, brand: dict) -> str:
    import sendgrid
    from sendgrid.helpers.mail import Mail

    sg = sendgrid.SendGridAPIClient(api_key=decrypt(brand.get('api_key_enc', '')))
    msg = Mail(
        from_email=(send['from_email'], send['from_name']),
        to_emails=send['to_email'],
        subject=send['subject'],
        html_content=send['html_body']
    )
    if send.get('reply_to'):
        msg.reply_to = send['reply_to']

    res = sg.send(msg)
    return res.headers.get('X-Message-Id', '')


def send_via_mailgun(send: dict, brand: dict) -> str:
    res = requests.post(
        f"https://api.mailgun.net/v3/{brand['api_domain']}/messages",
        auth=('api', decrypt(brand.get('api_key_enc', ''))),
        data={
            'from': f"{send['from_name']} <{send['from_email']}>",
            'to': send['to_email'],
            'subject': send['subject'],
            'html': send['html_body'],
            'h:Reply-To': send.get('reply_to', ''),
        }
    )
    res.raise_for_status()
    return res.json().get('id', '')


def send_via_ses(send: dict, brand: dict) -> str:
    client = boto3.client('ses', region_name=brand.get('api_domain', 'us-east-1'))
    res = client.send_email(
        Source=f"{send['from_name']} <{send['from_email']}>",
        Destination={'ToAddresses': [send['to_email']]},
        Message={
            'Subject': {'Data': send['subject'], 'Charset': 'UTF-8'},
            'Body': {'Html': {'Data': send['html_body'], 'Charset': 'UTF-8'}}
        },
        ReplyToAddresses=[send['reply_to']] if send.get('reply_to') else []
    )
    return res['MessageId']


def send_via_smtp(send: dict, brand: dict) -> str:
    msg = MIMEMultipart('alternative')
    msg['Subject'] = send['subject']
    msg['From'] = f"{send['from_name']} <{send['from_email']}>"
    msg['To'] = send['to_email']
    if send.get('reply_to'):
        msg['Reply-To'] = send['reply_to']

    msg.attach(MIMEText(send['html_body'], 'html', 'utf-8'))

    with smtplib.SMTP(brand['smtp_host'], brand.get('smtp_port', 587)) as server:
        if brand.get('smtp_encryption') == 'tls':
            server.starttls()
        server.login(brand['smtp_username'], decrypt(brand.get('smtp_password_enc', '')))
        server.sendmail(send['from_email'], [send['to_email']], msg.as_string())

    return f"smtp_{send['id']}_{int(__import__('time').time())}"
