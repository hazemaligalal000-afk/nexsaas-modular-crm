#!/usr/bin/env python3
"""
ai_engine/workers/call_transcription.py

Transcribe call recordings via Whisper, extract summary/sentiment/actions.
Consumes: call.recording.ready
"""

import json
import logging
import os
import sys
from datetime import datetime, timezone

import pika
import psycopg2
import requests
import openai

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

# ── Process call transcription ───────────────────────────────────────────────
def process_transcription(payload: dict):
    """
    Transcribe call recording:
    1. Download recording from S3
    2. Transcribe with Whisper (OpenAI API)
    3. Extract summary, sentiment, action items with GPT-4
    4. Update call_log with AI analysis
    """
    call_sid = payload['call_sid']
    tenant_id = payload['tenant_id']
    recording_url = payload['recording_url']
    
    logger.info(f"Transcribing call {call_sid}")
    
    db = get_db()
    cur = db.cursor()
    
    try:
        # Update status to processing
        cur.execute(
            "UPDATE call_log SET transcript_status = %s WHERE call_sid = %s AND tenant_id = %s",
            ['processing', call_sid, tenant_id]
        )
        db.commit()
        
        # Download recording
        audio_path = download_recording(recording_url, call_sid)
        
        # Transcribe with Whisper
        transcript = transcribe_audio(audio_path)
        
        # AI analysis
        analysis = analyze_transcript(transcript)
        
        # Update call_log
        cur.execute(
            """UPDATE call_log SET
                transcript_text = %s,
                transcript_ar = %s,
                transcript_en = %s,
                ai_summary = %s,
                ai_action_items = %s,
                ai_sentiment = %s,
                ai_intent = %s,
                ai_keywords = %s,
                transcript_status = %s,
                updated_at = %s
            WHERE call_sid = %s AND tenant_id = %s""",
            [
                transcript['text'],
                transcript.get('text_ar'),
                transcript.get('text_en'),
                analysis['summary'],
                json.dumps(analysis['action_items']),
                analysis['sentiment'],
                analysis['intent'],
                json.dumps(analysis['keywords']),
                'completed',
                datetime.now(timezone.utc).isoformat(),
                call_sid,
                tenant_id
            ]
        )
        db.commit()
        
        logger.info(f"Transcription completed for call {call_sid}")
        
        # Cleanup
        if os.path.exists(audio_path):
            os.remove(audio_path)
        
    except Exception as e:
        db.rollback()
        cur.execute(
            "UPDATE call_log SET transcript_status = %s WHERE call_sid = %s AND tenant_id = %s",
            ['failed', call_sid, tenant_id]
        )
        db.commit()
        logger.error(f"Transcription failed for {call_sid}: {e}", exc_info=True)
        raise
    finally:
        cur.close()
        db.close()

def download_recording(url: str, call_sid: str) -> str:
    """Download recording to temp file."""
    temp_path = f"/tmp/recording_{call_sid}.wav"
    
    resp = requests.get(url, stream=True, timeout=60)
    resp.raise_for_status()
    
    with open(temp_path, 'wb') as f:
        for chunk in resp.iter_content(chunk_size=8192):
            f.write(chunk)
    
    logger.info(f"Downloaded recording to {temp_path}")
    return temp_path

def transcribe_audio(audio_path: str) -> dict:
    """Transcribe audio with OpenAI Whisper."""
    openai.api_key = os.getenv('OPENAI_API_KEY')
    
    with open(audio_path, 'rb') as audio_file:
        transcript = openai.Audio.transcribe(
            model="whisper-1",
            file=audio_file,
            language="ar",  # Auto-detect or specify
            response_format="verbose_json"
        )
    
    return {
        'text': transcript['text'],
        'language': transcript.get('language', 'ar'),
        'duration': transcript.get('duration'),
    }

def analyze_transcript(transcript: dict) -> dict:
    """Extract summary, sentiment, action items with GPT-4."""
    openai.api_key = os.getenv('OPENAI_API_KEY')
    
    prompt = f"""Analyze this call transcript and provide:
1. A concise summary (2-3 sentences)
2. Overall sentiment (positive/neutral/negative)
3. Customer intent (inquiry/complaint/purchase/support)
4. Action items (list of follow-up tasks)
5. Key topics/keywords (list)

Transcript:
{transcript['text']}

Respond in JSON format:
{{
  "summary": "...",
  "sentiment": "positive|neutral|negative",
  "intent": "inquiry|complaint|purchase|support|other",
  "action_items": ["task 1", "task 2"],
  "keywords": ["keyword1", "keyword2"]
}}
"""
    
    response = openai.ChatCompletion.create(
        model="gpt-4",
        messages=[
            {"role": "system", "content": "You are a call analysis assistant. Respond only with valid JSON."},
            {"role": "user", "content": prompt}
        ],
        temperature=0.3,
        max_tokens=500
    )
    
    result = json.loads(response.choices[0].message.content)
    return result

# ── RabbitMQ consumer ────────────────────────────────────────────────────────
def callback(ch, method, properties, body):
    try:
        payload = json.loads(body)
        process_transcription(payload)
        ch.basic_ack(delivery_tag=method.delivery_tag)
    except Exception as e:
        logger.error(f"Worker error: {e}", exc_info=True)
        ch.basic_nack(delivery_tag=method.delivery_tag, requeue=False)

def main():
    logger.info("Call Transcription Worker starting...")
    
    connection = get_rabbitmq()
    channel = connection.channel()
    
    channel.queue_declare(queue='call.recording.ready', durable=True)
    channel.basic_qos(prefetch_count=1)
    channel.basic_consume(queue='call.recording.ready', on_message_callback=callback)
    
    logger.info("Waiting for call recordings...")
    try:
        channel.start_consuming()
    except KeyboardInterrupt:
        logger.info("Shutting down...")
        channel.stop_consuming()
    finally:
        connection.close()

if __name__ == '__main__':
    main()
