# NexSaaS Modular CRM - Enterprise Edition 🚀

An enterprise-grade, multi-tenant SaaS CRM platform built on an advanced modular architecture. It leverages a custom PHP Core for extreme scaling, database isolation per tenant, and a modern React frontend for lightning-fast user experiences.

---

## 🏗️ System Architecture

Our newly designed modular core fundamentally transforms the traditional CRM monolithic structure into a scalable, Tenant-Aware cloud application using the **Control Plane Data Model**.

- **Multi-Tenant Global Resolvers:** `TenantEnforcer` & `TenantResolver` handle requests dynamically securely injecting tenant contexts via API Key or subdomain.
- **Data Isolation Strategies:** Natively supports both **Shared Database** (scoped by `organization_id`) and **Dedicated Database** environments based on subscription level.
- **Dynamic Module Manager:** Bootstraps domain-driven logic (Leads, Contacts, Invoicing) individually ensuring robust security and role-based access.

### 🗂️ Core Technology Stack
- **Backend Core**: Custom PHP Modular MVC (Fast, Lightweight)
- **Frontend SPA**: React 18 & Vite (`modular_core/react-frontend`)
- **Database Layer**: Robust multi-tenant schema via PostgreSQL/MySQL
- **Containerization**: Fully Dockerized environments (`docker-compose`)

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.2+
- Composer
- Node.js & npm / yarn (for the React Frontend)
- Relational Database (MySQL 8.0 or PostgreSQL)
- Docker & Docker Compose (Optional but Recommended)

### Local Setup & Installation

1. **Database Initialization**
   Run the `modular_core/database/setup_saas.sql` script into your primary database. This initializes the global SaaS tables (`tenants`, `saas_api_keys`) and the sample tenant data.

2. **React Frontend Quickstart**
   ```bash
   cd modular_core/react-frontend
   npm install
   npm run dev
   ```

3. **Backend API Environment**
   Direct requests to the unified API entry point:
   ```bash
   cd modular_core
   php -S localhost:8080 -t public/
   ```

---

## 🧩 Exploring the Leads Module

We have deployed a reference module (`Modules/Leads`) that implements our new multi-tenant architecture perfectly:
1. `module.json`: Defines routes, navigation config, and RBAC permissions.
2. `ApiController.php`: Features native `TenantEnforcer` injections verifying clients only touch their own data.

Make a test request to your local API:
```bash
curl -X GET http://localhost:8080/api/leads \
  -H "X-API-Key: demo_tenant_key_123"
```

---

## 🚧 What's Missing / Next Iterations (Roadmap)

To fully transition this into a massive enterprise powerhouse, the following areas require development:

### 1. **Robust Authentication & RBAC Sync**
- Ensure OAuth2 flow (via Laravel Passport or a JWT implementation) replaces the simplified API-Key mapping currently in place.

### 2. **AI Engine Integration (Python API)**
- Complete the local AI Engine (FastAPI microservice in `/ai_engine`) to offer:
  - **Machine Learning Lead Scoring:** Predicting Deal closures.
  - **Creative Copy Assistant:** Generating cold emails.

### 3. **Omnichannel Comms Service**
- Integrate Telegram unified inbox and Whatsapp Business API webhook receivers.
- Inject the **Truecaller Data Enrichment** logic to automatically tag and name unknown incoming leads.

### 4. **Stripe Billing Module**
- Develop a standalone `Subscriptions` module linking SaaS Tenants to Stripe Webhooks for automatic downgrades/suspensions on failed payments.

### 5. **Hybrid DB Connections**
- Finalize the `Database.php` routing logic to dynamically switch MySQL PDO connections for Enterprise clients who pay for **Dedicated Server** resources.
