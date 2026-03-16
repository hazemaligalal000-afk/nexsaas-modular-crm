# 🚀 AI Revenue Operating System (Enterprise SaaS)

## 📌 Project Overview
The **AI Revenue Operating System** is a next-generation, multi-tenant CRM platform integrating predictive AI, omnichannel communication, and enterprise-grade RBAC. It is designed to scale to thousands of tenants with absolute data isolation and high-performance throughput.

---

## 🛠 Tech Stack
| Component | Technology | Role |
| :--- | :--- | :--- |
| **Backend Core** | PHP 8.3 (Modular MVC) | Business logic & API orchestration |
| **AI Workloads** | Python FastAPI | Scoring, intent detection, & LLM generation |
| **Frontend** | React 18 / Vite | Modern SaaS Dashboard (RBAC-aware) |
| **Primary DB** | PostgreSQL 16 / MySQL | Polyglot support (Multi-tenant schema) |
| **Caching** | Redis 7 | Permission caching & session management |
| **Async Jobs** | RabbitMQ / Celery | Enrichment & Webhook processing |
| **Infrastructure** | Docker & Kubernetes | Containerized orchestration |

---

## 🏗 Key Features

### 1. Multi-Tenant Mastery
- **Hybrid DB Strategy**: Shared database pool for SMEs; Dedicated isolated DB clusters for Enterprise clients.
- **Tenant Context Propagator**: Mandatory resolution of `tenant_id` at the kernel level for DB, Cache, and Queues.

### 2. Radical RBAC (Role-Based Access Control)
- **Granular Permissions**: 5 default roles (Owner, Admin, Manager, Agent, Support) with a merged permission matrix.
- **Tenant-Extendable**: Companies can define custom roles with specific feature-flags.
- **Cache-Optimized**: Permissions are cached in Redis and invalidated globally upon role updates.

### 3. AI Intelligence Engine
- **Lead Scoring**: Predictive probablity of conversion.
- **Intent Discovery**: Real-time NLP detection of "Buying Intent" or "Churn Risk" in chat.
- **Content Gen**: Automated context-aware email and WhatsApp drafting.

### 4. Omnichannel Unified Inbox
- **Meta Integrations**: Direct receivers for WhatsApp Business and Telegram.
- **Background Processing**: Webhooks are ingested and queued instantly, keeping the API fast.

---

## 📂 Project Structure
```text
/
├── ai_engine/               # Python FastAPI Microservice
│   ├── main.py              # AI Endpoints (Scoring, NLP, Forecasting)
│   ├── requirements.txt
│   └── Dockerfile
├── modular_core/            # PHP Backend Core
│   ├── core/                # System Level (Auth, DB, RBAC, Queue)
│   ├── modules/             # SaaS Modules (Leads, Billing, Analytics)
│   ├── public/api/          # API Gateway
│   └── database/            # Schema Migrations
├── react-frontend/          # React Dashboard
│   ├── src/core/            # AuthContext & RBAC Hooks
│   ├── src/modules/         # Permission-aware UI Components
└── docker-compose.yml       # Full Stack Orchestration
```

---

## 🚀 Deployment (Production Ready)

### 1. Requirements
- Docker & Docker Compose
- SSL Certificate (for Meta Webhooks)

### 2. Fast Launch (Development)
```bash
# Clone the repository
git clone https://github.com/hazemaligalal000-afk/nexsaas-modular-crm.git
cd nexsaas-modular-crm

# Set environment variables (.env)
cp .env.example .env

# Build and Start
docker-compose up --build -d
```

### 3. Kubernetes (SaaS Scale)
The project includes K8s manifests for:
- **Autoscaling PHP-FPM pods** based on CPU/RAM metrics.
- **Python AI deployments** with custom horizontal pod autoscalers (HPA).
- **Persistent Volume Claims (PVC)** for isolated DB storage.

---

## ⚠️ Roadmap & Missing Pieces
- [ ] **Data Lake Integration**: Exporting anonymized tenant data to BigQuery/Snowflake for global training.
- [ ] **Pusher/WebSockets**: Implement real-time notifications for the Unified Inbox.
- [ ] **Stripe Advanced Billing**: Implementing Tax/VAT compliance for global SaaS sales.

---
**hazem ali galal | 2026**
