# Design Document: Go-to-Market Infrastructure

## Overview

This design document outlines the technical architecture for NexSaaS's go-to-market infrastructure, covering three critical areas: comprehensive documentation systems, customer support infrastructure, and analytics/monitoring stack. The design ensures operational readiness for commercial launch by providing excellent developer experience, responsive customer support, and robust observability.

The system integrates with the existing NexSaaS platform (PHP 8.3 backend, React frontend, FastAPI AI engine) and leverages industry-standard tools for documentation generation, support ticketing, error tracking, and infrastructure monitoring.

### Key Design Goals

1. **Developer Experience**: Provide comprehensive, auto-generated API documentation with interactive testing capabilities
2. **Customer Success**: Enable self-service support through multilingual documentation and contextual help
3. **Operational Excellence**: Maintain platform reliability through proactive monitoring and alerting
4. **Data-Driven Decisions**: Track user behavior and system performance to inform product development

## Architecture

### High-Level System Architecture

```mermaid
graph TB
    subgraph "NexSaaS Platform"
        PHP[PHP Backend<br/>API Endpoints]
        React[React Frontend<br/>User Interface]
        FastAPI[FastAPI AI Engine]
    end
    
    subgraph "Documentation Layer"
        OpenAPI[OpenAPI Generator<br/>Swagger UI]
        HelpCenter[Help Center<br/>Docusaurus]
        Videos[Video Hosting<br/>Vimeo/YouTube]
        Postman[Postman Collection<br/>Auto-generated]
    end
    
    subgraph "Support Infrastructure"
        Chat[Chat Widget<br/>Crisp]
        Tickets[Ticketing System<br/>Linear API]
        Status[Status Page<br/>Upptime]
    end
    
    subgraph "Monitoring Stack"
        Sentry[Sentry<br/>Error Tracking]
        Analytics[Analytics DB<br/>PostgreSQL]
        Prometheus[Prometheus<br/>Metrics Collection]
        Grafana[Grafana<br/>Visualization]
        PagerDuty[PagerDuty<br/>Alerting]
    end
    
    PHP --> OpenAPI
    PHP --> Sentry
    PHP --> Analytics
    PHP --> Prometheus
    React --> Sentry
    React --> Chat
    React --> HelpCenter
    
    OpenAPI --> Postman
    Chat --> Tickets
    Prometheus --> Grafana
    Prometheus --> PagerDuty
    Status -.->|monitors| PHP
    
    style Documentation Layer fill:#e1f5ff
    style Support Infrastructure fill:#fff4e1
    style Monitoring Stack fill:#ffe1f5
```

### Technology Stack Selection

| Component | Technology | Rationale |
|-----------|-----------|-----------|
| API Documentation | OpenAPI 3.0 + Swagger UI | Industry standard, auto-generated from code annotations |
| Help Center | Docusaurus | Static site generator with excellent search, i18n support |
| Video Hosting | Vimeo Pro | Professional player, subtitle support, embeddable |
| API Testing | Postman | Developer-friendly, supports collections and environments |
| Chat Support | Crisp | Lightweight, multilingual, affordable for startups |
| Ticketing | Linear API Integration | Modern, developer-friendly, integrates with existing workflow |
| Status Page | Upptime | Open-source, GitHub Actions-based, zero infrastructure |
| Error Tracking | Sentry | Best-in-class error aggregation, supports PHP and JavaScript |
| Analytics Storage | PostgreSQL | Reuse existing database, JSONB for flexible event storage |
| Metrics Collection | Prometheus | Industry standard for time-series metrics |
| Visualization | Grafana | Powerful dashboards, integrates with Prometheus |
| Alerting | PagerDuty | Reliable incident management with escalation policies |


## Components and Interfaces

### 1. Documentation Architecture

#### 1.1 OpenAPI Specification Generator

**Purpose**: Auto-generate API documentation from PHP code annotations.

**Implementation Approach**:
- Use PHP attributes/annotations to document endpoints
- Build custom OpenAPI generator that scans controller files
- Generate OpenAPI 3.0 JSON specification
- Serve Swagger UI at `/api/docs`

**Component Structure**:

```php
// modular_core/core/OpenAPI/OpenAPIGenerator.php
class OpenAPIGenerator {
    public function scanControllers(string $modulePath): array;
    public function generateSpec(array $routes): array;
    public function writeSpecFile(array $spec, string $outputPath): void;
}

// modular_core/core/OpenAPI/Attributes/ApiEndpoint.php
#[Attribute(Attribute::TARGET_METHOD)]
class ApiEndpoint {
    public function __construct(
        public string $method,
        public string $path,
        public string $summary,
        public string $description,
        public array $parameters = [],
        public array $responses = [],
        public bool $requiresAuth = true
    ) {}
}


// Example usage in controller
#[ApiEndpoint(
    method: 'POST',
    path: '/api/crm/leads',
    summary: 'Create a new lead',
    description: 'Creates a new lead in the CRM system',
    parameters: [
        ['name' => 'name', 'type' => 'string', 'required' => true],
        ['name' => 'email', 'type' => 'string', 'required' => true],
        ['name' => 'phone', 'type' => 'string', 'required' => false]
    ],
    responses: [
        201 => ['description' => 'Lead created successfully', 'schema' => 'Lead'],
        400 => ['description' => 'Invalid input data'],
        401 => ['description' => 'Unauthorized']
    ]
)]
public function createLead(Request $request): Response {
    // Implementation
}
```

**Swagger UI Integration**:
- Serve static Swagger UI files from `/public/swagger-ui/`
- Configure Swagger UI to load OpenAPI spec from `/api/openapi.json`
- Enable "Try it out" functionality with JWT authentication

**Build Pipeline**:
```bash
# CLI command to regenerate OpenAPI spec
php cli/generate_openapi.php --output=public/openapi.json
```



#### 1.2 Postman Collection Generator

**Purpose**: Auto-generate Postman collection from OpenAPI specification.

**Implementation**:
- Convert OpenAPI 3.0 spec to Postman Collection v2.1 format
- Include environment variables for base URL and auth token
- Organize requests by module (CRM, Billing, Platform, etc.)

**Component Structure**:

```php
// modular_core/core/OpenAPI/PostmanGenerator.php
class PostmanGenerator {
    public function convertFromOpenAPI(array $openApiSpec): array;
    public function addEnvironmentVariables(array $collection): array;
    public function writeCollectionFile(array $collection, string $path): void;
}
```

**Generated Collection Structure**:
```json
{
  "info": {
    "name": "NexSaaS API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {"key": "baseUrl", "value": "https://api.nexsaas.com"},
    {"key": "authToken", "value": ""}
  ],
  "item": [
    {
      "name": "CRM",
      "item": [
        {
          "name": "Create Lead",
          "request": {
            "method": "POST",
            "header": [{"key": "Authorization", "value": "Bearer {{authToken}}"}],
            "url": "{{baseUrl}}/api/crm/leads",
            "body": {"mode": "raw", "raw": "{\n  \"name\": \"John Doe\",\n  \"email\": \"john@example.com\"\n}"}
          }
        }
      ]
    }
  ]
}
```



#### 1.3 Help Center (Docusaurus)

**Purpose**: Centralized documentation portal with search, i18n, and versioning.

**Directory Structure**:
```
docs/
├── docusaurus.config.js
├── sidebars.js
├── docs/
│   ├── getting-started.md
│   ├── user-guide/
│   │   ├── leads.md
│   │   ├── deals.md
│   │   └── contacts.md
│   ├── api/
│   │   ├── authentication.md
│   │   ├── webhooks.md
│   │   └── rate-limits.md
│   └── faq.md
├── i18n/
│   └── ar/
│       └── docusaurus-plugin-content-docs/
│           └── current/
│               ├── getting-started.md
│               └── user-guide/
└── static/
    ├── img/
    └── videos/
```

**Configuration**:
```javascript
// docusaurus.config.js
module.exports = {
  title: 'NexSaaS Documentation',
  url: 'https://docs.nexsaas.com',
  baseUrl: '/',
  i18n: {
    defaultLocale: 'en',
    locales: ['en', 'ar'],
    localeConfigs: {
      ar: {direction: 'rtl'}
    }
  },
  themeConfig: {
    algolia: {
      appId: 'YOUR_APP_ID',
      apiKey: 'YOUR_API_KEY',
      indexName: 'nexsaas'
    },
    navbar: {
      items: [
        {to: '/docs/getting-started', label: 'Getting Started'},
        {to: '/docs/user-guide', label: 'User Guide'},
        {to: '/docs/api', label: 'API Reference'},
        {to: '/docs/faq', label: 'FAQ'},
        {type: 'localeDropdown', position: 'right'}
      ]
    }
  }
};
```



**Deployment**:
- Build static site: `npm run build` (generates `build/` directory)
- Deploy to CDN (Cloudflare Pages, Netlify, or Vercel)
- Configure custom domain: `docs.nexsaas.com`

**Search Integration**:
- Use Algolia DocSearch (free for open-source projects)
- Crawler runs weekly to index new content
- Supports multilingual search

#### 1.4 Video Tutorial System

**Purpose**: Host and embed video tutorials with multilingual subtitles.

**Video Hosting Strategy**:
- Platform: Vimeo Pro (supports subtitle tracks, embeddable player, analytics)
- Alternative: YouTube (free, but less control over branding)

**Video Production Workflow**:
1. Record screencast using OBS Studio or Loom
2. Add English voiceover
3. Generate Arabic subtitles using Vimeo's auto-transcription + manual review
4. Upload to Vimeo with privacy settings: "Hide from Vimeo.com"
5. Embed in Help Center using iframe

**Embedding in Docusaurus**:
```markdown
<!-- docs/user-guide/create-lead.md -->
# Creating a Lead

Watch this 3-minute tutorial to learn how to create leads:

<iframe src="https://player.vimeo.com/video/123456789?h=abc123&title=0&byline=0&portrait=0" 
        width="640" height="360" frameborder="0" allow="autoplay; fullscreen" allowfullscreen>
</iframe>

[Download Arabic subtitles](/subtitles/create-lead-ar.vtt)
```

**Video Metadata Storage**:
```php
// Store video metadata in database for analytics
CREATE TABLE video_tutorials (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    vimeo_id VARCHAR(50) NOT NULL,
    duration_seconds INT NOT NULL,
    workflow_category VARCHAR(100),
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);
```



### 2. Support Infrastructure

#### 2.1 Chat Widget Integration (Crisp)

**Purpose**: Provide real-time in-app chat support.

**Integration Approach**:
- Embed Crisp JavaScript widget in React frontend
- Pass user context (name, email, tenant ID) to Crisp
- Route Enterprise customers to priority support queue

**React Component**:
```jsx
// modular_core/react-frontend/src/components/SupportChat.jsx
import { useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';

export default function SupportChat() {
  const { user } = useAuth();

  useEffect(() => {
    // Load Crisp script
    window.$crisp = [];
    window.CRISP_WEBSITE_ID = "YOUR_CRISP_WEBSITE_ID";
    
    const script = document.createElement("script");
    script.src = "https://client.crisp.chat/l.js";
    script.async = true;
    document.head.appendChild(script);

    // Set user data when authenticated
    if (user) {
      window.$crisp.push(["set", "user:email", user.email]);
      window.$crisp.push(["set", "user:nickname", user.name]);
      window.$crisp.push(["set", "session:data", [[
        ["tenant_id", user.tenant_id],
        ["plan", user.tenant_plan],
        ["user_role", user.role]
      ]]]);

      // Route Enterprise customers to priority queue
      if (user.tenant_plan === 'enterprise') {
        window.$crisp.push(["set", "session:segments", [["enterprise"]]]);
      }
    }

    return () => {
      // Cleanup on unmount
      if (window.$crisp) {
        window.$crisp.push(["do", "chat:hide"]);
      }
    };
  }, [user]);

  return null; // Widget is injected by Crisp script
}
```



**Crisp Configuration**:
- Enable file attachments (max 10MB)
- Configure business hours and auto-responses
- Set up routing rules for Enterprise segment
- Enable chat history retention

#### 2.2 Ticketing System Integration (Linear)

**Purpose**: Track and manage technical support tickets.

**Integration Strategy**:
- Use Linear API to create issues from support requests
- Sync ticket status back to customer portal
- Escalate unresolved tickets after 24 hours

**Backend Service**:
```php
// modular_core/modules/Platform/Support/LinearTicketService.php
namespace Platform\Support;

class LinearTicketService {
    private string $apiKey;
    private string $teamId;
    
    public function __construct() {
        $this->apiKey = $_ENV['LINEAR_API_KEY'];
        $this->teamId = $_ENV['LINEAR_TEAM_ID'];
    }
    
    public function createTicket(array $data): string {
        $mutation = <<<GQL
        mutation {
          issueCreate(input: {
            teamId: "{$this->teamId}"
            title: "{$data['title']}"
            description: "{$data['description']}"
            priority: {$data['priority']}
            labelIds: ["{$data['label_id']}"]
          }) {
            success
            issue {
              id
              identifier
            }
          }
        }
        GQL;
        
        $response = $this->graphqlRequest($mutation);
        return $response['data']['issueCreate']['issue']['identifier'];
    }
    
    public function getTicketStatus(string $ticketId): string {
        $query = <<<GQL
        query {
          issue(id: "{$ticketId}") {
            state {
              name
            }
          }
        }
        GQL;
        
        $response = $this->graphqlRequest($query);
        return $response['data']['issue']['state']['name'];
    }
    
    private function graphqlRequest(string $query): array {
        $ch = curl_init('https://api.linear.app/graphql');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode(['query' => $query])
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```



**Database Schema**:
```sql
CREATE TABLE support_tickets (
    id SERIAL PRIMARY KEY,
    tenant_id INT NOT NULL REFERENCES tenants(id),
    user_id INT NOT NULL REFERENCES users(id),
    linear_issue_id VARCHAR(50) UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20) CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status VARCHAR(50) DEFAULT 'open',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP,
    CONSTRAINT fk_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX idx_tickets_tenant ON support_tickets(tenant_id);
CREATE INDEX idx_tickets_status ON support_tickets(status);
```

**Escalation Worker**:
```php
// modular_core/cli/escalate_tickets.php
// Run via cron every hour: 0 * * * * php cli/escalate_tickets.php

$db = Database::getInstance();
$linearService = new LinearTicketService();

// Find tickets unresolved for 24+ hours
$tickets = $db->query("
    SELECT id, linear_issue_id, title 
    FROM support_tickets 
    WHERE status = 'open' 
    AND created_at < NOW() - INTERVAL '24 hours'
    AND resolved_at IS NULL
");

foreach ($tickets as $ticket) {
    // Update priority in Linear
    $linearService->updatePriority($ticket['linear_issue_id'], 'high');
    
    // Notify senior support
    $notificationService->send([
        'channel' => 'slack',
        'message' => "Ticket #{$ticket['id']} requires escalation: {$ticket['title']}"
    ]);
}
```



#### 2.3 Status Page (Upptime)

**Purpose**: Public-facing system status and uptime monitoring.

**Implementation Strategy**:
- Use Upptime (open-source, GitHub Actions-based)
- Monitor critical endpoints every 5 minutes
- Display 90-day uptime history
- Send notifications on downtime

**Repository Structure**:
```
nexsaas-status/
├── .github/
│   └── workflows/
│       ├── graphs.yml
│       ├── response-time.yml
│       ├── setup.yml
│       ├── summary.yml
│       ├── update-template.yml
│       └── uptime.yml
├── .upptimerc.yml
├── history/
│   ├── api.yml
│   ├── app.yml
│   └── docs.yml
└── README.md
```

**Configuration**:
```yaml
# .upptimerc.yml
owner: nexsaas
repo: status
sites:
  - name: NexSaaS App
    url: https://app.nexsaas.com
    expectedStatusCodes:
      - 200
      - 401  # Login page returns 401 for unauthenticated requests
  - name: API
    url: https://api.nexsaas.com/health
    expectedStatusCodes:
      - 200
  - name: Documentation
    url: https://docs.nexsaas.com
  - name: AI Engine
    url: https://ai.nexsaas.com/health

status-website:
  cname: status.nexsaas.com
  logoUrl: https://app.nexsaas.com/logo.svg
  name: NexSaaS Status
  introTitle: "System Status"
  introMessage: Real-time status and uptime monitoring for NexSaaS services
  navbar:
    - title: Status
      href: /
    - title: GitHub
      href: https://github.com/nexsaas/status

notifications:
  - type: slack
    webhook-url: ${{ secrets.SLACK_WEBHOOK_URL }}
  - type: email
    email-to: ops@nexsaas.com

commitMessages:
  readmeContent: ":pencil: Update summary in README [skip ci]"
  statusChange: "$EMOJI $SITE_NAME is $STATUS ($RESPONSE_CODE in $RESPONSE_TIME ms)"
```



**Health Check Endpoints**:
```php
// modular_core/modules/Platform/Health/HealthController.php
namespace Platform\Health;

class HealthController {
    public function check(): array {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'rabbitmq' => $this->checkRabbitMQ(),
            'storage' => $this->checkStorage()
        ];
        
        $allHealthy = !in_array(false, $checks, true);
        
        return [
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => time(),
            'checks' => $checks
        ];
    }
    
    private function checkDatabase(): bool {
        try {
            $db = Database::getInstance();
            $db->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkRedis(): bool {
        try {
            $redis = Redis::getInstance();
            $redis->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkRabbitMQ(): bool {
        try {
            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                $_ENV['RABBITMQ_HOST'],
                $_ENV['RABBITMQ_PORT'],
                $_ENV['RABBITMQ_USER'],
                $_ENV['RABBITMQ_PASSWORD']
            );
            $connection->close();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function checkStorage(): bool {
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $usagePercent = (1 - ($diskFree / $diskTotal)) * 100;
        
        return $usagePercent < 90;
    }
}
```



#### 2.4 SLA Documentation

**Purpose**: Define service level commitments for Enterprise customers.

**Document Structure**:
```markdown
# Service Level Agreement (SLA)

## 1. Service Availability

### 1.1 Uptime Guarantee
- **Enterprise Plan**: 99.9% uptime per calendar month
- **Professional Plan**: 99.5% uptime per calendar month
- **Starter Plan**: Best effort (no SLA)

### 1.2 Downtime Definition
Downtime is defined as the period when the NexSaaS platform is unavailable and returns 
HTTP 5xx errors for more than 5 consecutive minutes.

**Excluded from Downtime**:
- Scheduled maintenance (announced 48 hours in advance)
- Issues caused by customer's infrastructure or internet connectivity
- Force majeure events

### 1.3 Service Credits
If uptime falls below guaranteed levels, customers receive service credits:

| Uptime Achieved | Service Credit |
|-----------------|----------------|
| 99.0% - 99.9%   | 10% of monthly fee |
| 95.0% - 99.0%   | 25% of monthly fee |
| < 95.0%         | 50% of monthly fee |

## 2. Support Response Times

### 2.1 Enterprise Support
- **Critical Issues**: 4-hour response time, 24/7
- **High Priority**: 24-hour response time, business hours
- **Medium Priority**: 48-hour response time, business hours
- **Low Priority**: 5 business days

### 2.2 Professional Support
- **Critical Issues**: 24-hour response time, business hours
- **High Priority**: 48-hour response time, business hours
- **Medium/Low Priority**: 5 business days

### 2.3 Issue Severity Definitions
- **Critical**: Platform completely unavailable or data loss
- **High**: Major feature unavailable affecting multiple users
- **Medium**: Minor feature issue or workaround available
- **Low**: Cosmetic issue or feature request

## 3. Data Protection

### 3.1 Backup Frequency
- Database backups: Every 6 hours
- File storage backups: Daily
- Backup retention: 30 days

### 3.2 Disaster Recovery
- Recovery Time Objective (RTO): 4 hours
- Recovery Point Objective (RPO): 6 hours

## 4. Security Commitments

- Annual penetration testing
- SOC 2 Type II compliance (in progress)
- GDPR compliance for EU customers
- Data encryption at rest and in transit

## 5. Contact Information

- **Support Email**: support@nexsaas.com
- **Emergency Hotline**: +1-XXX-XXX-XXXX (Enterprise only)
- **Status Page**: https://status.nexsaas.com
```

**Storage and Delivery**:
- Store as PDF in `/public/legal/sla.pdf`
- Digitally sign using DocuSign API
- Attach to Enterprise customer onboarding emails
- Version control in Git repository



### 3. Monitoring & Analytics Stack

#### 3.1 Error Tracking (Sentry)

**Purpose**: Capture, aggregate, and alert on application errors.

**Integration Points**:
1. PHP Backend (Sentry PHP SDK)
2. React Frontend (Sentry JavaScript SDK)
3. FastAPI AI Engine (Sentry Python SDK)

**PHP Backend Integration**:
```php
// modular_core/bootstrap/sentry.php
use Sentry\State\Scope;

\Sentry\init([
    'dsn' => $_ENV['SENTRY_DSN'],
    'environment' => $_ENV['APP_ENV'],
    'release' => $_ENV['APP_VERSION'],
    'traces_sample_rate' => 0.2, // 20% of transactions for performance monitoring
    'profiles_sample_rate' => 0.2,
    'before_send' => function (\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Scrub sensitive data
        if ($event->getRequest()) {
            $request = $event->getRequest();
            $request['headers'] = array_filter($request['headers'], function($key) {
                return !in_array(strtolower($key), ['authorization', 'cookie']);
            }, ARRAY_FILTER_USE_KEY);
        }
        return $event;
    }
]);

// Set user context in middleware
function setSentryContext(User $user): void {
    \Sentry\configureScope(function (Scope $scope) use ($user): void {
        $scope->setUser([
            'id' => $user->id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);
        $scope->setTag('tenant_plan', $user->tenant->plan);
    });
}
```



**React Frontend Integration**:
```jsx
// modular_core/react-frontend/src/sentry.js
import * as Sentry from "@sentry/react";
import { BrowserTracing } from "@sentry/tracing";

Sentry.init({
  dsn: import.meta.env.VITE_SENTRY_DSN,
  environment: import.meta.env.VITE_APP_ENV,
  integrations: [
    new BrowserTracing(),
    new Sentry.Replay({
      maskAllText: true,
      blockAllMedia: true,
    }),
  ],
  tracesSampleRate: 0.2,
  replaysSessionSampleRate: 0.1,
  replaysOnErrorSampleRate: 1.0,
  beforeSend(event, hint) {
    // Filter out non-error console logs
    if (event.level === 'log' || event.level === 'info') {
      return null;
    }
    return event;
  }
});

// Set user context after login
export function setSentryUser(user) {
  Sentry.setUser({
    id: user.id,
    email: user.email,
    tenant_id: user.tenant_id
  });
  Sentry.setTag('tenant_plan', user.tenant_plan);
}
```

**Alert Rules**:
```yaml
# Sentry Alert Configuration (via UI or API)
alerts:
  - name: "High Error Rate"
    conditions:
      - type: event_frequency
        value: 10
        interval: 5m
    actions:
      - type: slack
        workspace: nexsaas
        channel: "#alerts"
      - type: pagerduty
        service: nexsaas-production
        
  - name: "New Error Type"
    conditions:
      - type: first_seen_event
    actions:
      - type: email
        targets: ["dev@nexsaas.com"]
      
  - name: "Performance Degradation"
    conditions:
      - type: transaction_duration
        value: 3000  # 3 seconds
        percentile: 95
    actions:
      - type: slack
        channel: "#performance"
```



#### 3.2 Business Analytics System

**Purpose**: Track user behavior and product usage for data-driven decisions.

**Event Tracking Architecture**:

```mermaid
graph LR
    A[User Action] --> B[Event Emitter]
    B --> C[RabbitMQ Queue]
    C --> D[Analytics Worker]
    D --> E[PostgreSQL Analytics DB]
    E --> F[Usage Dashboard]
    
    style C fill:#ffeb99
    style E fill:#99ccff
```

**Database Schema**:
```sql
CREATE TABLE business_events (
    id BIGSERIAL PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT,
    event_type VARCHAR(100) NOT NULL,
    event_data JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_events_tenant ON business_events(tenant_id);
CREATE INDEX idx_events_type ON business_events(event_type);
CREATE INDEX idx_events_created ON business_events(created_at);
CREATE INDEX idx_events_data ON business_events USING GIN(event_data);

-- Partitioning by month for performance
CREATE TABLE business_events_2024_01 PARTITION OF business_events
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
```



**Event Emitter Service**:
```php
// modular_core/modules/Platform/Analytics/EventEmitter.php
namespace Platform\Analytics;

class EventEmitter {
    private RabbitMQPublisher $publisher;
    
    public function __construct() {
        $this->publisher = new RabbitMQPublisher('analytics_events');
    }
    
    public function track(string $eventType, array $data, ?int $userId = null): void {
        $event = [
            'tenant_id' => $this->getCurrentTenantId(),
            'user_id' => $userId ?? $this->getCurrentUserId(),
            'event_type' => $eventType,
            'event_data' => $data,
            'timestamp' => time()
        ];
        
        $this->publisher->publish(json_encode($event));
    }
}

// Usage in application code
$eventEmitter = new EventEmitter();

// Track lead creation
$eventEmitter->track('lead.created', [
    'lead_id' => $lead->id,
    'source' => $lead->source,
    'value' => $lead->estimated_value
]);

// Track user login
$eventEmitter->track('user.login', [
    'login_method' => 'password',
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);

// Track email sent
$eventEmitter->track('email.sent', [
    'template_id' => $template->id,
    'recipient_count' => count($recipients)
]);
```



**Analytics Worker**:
```php
// modular_core/cli/analytics_worker.php
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD'],
    $_ENV['RABBITMQ_VHOST']
);

$channel = $connection->channel();
$channel->queue_declare('analytics_events', false, true, false, false);

$callback = function ($msg) {
    $event = json_decode($msg->body, true);
    
    $db = Database::getInstance();
    $db->insert('business_events', [
        'tenant_id' => $event['tenant_id'],
        'user_id' => $event['user_id'],
        'event_type' => $event['event_type'],
        'event_data' => json_encode($event['event_data']),
        'created_at' => date('Y-m-d H:i:s', $event['timestamp'])
    ]);
    
    $msg->ack();
};

$channel->basic_consume('analytics_events', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
```

**Tracked Events**:
- `user.login` - User authentication
- `user.invited` - Team member invitation
- `lead.created` - New lead added
- `deal.created` - New deal created
- `deal.closed` - Deal won or lost
- `email.sent` - Email campaign sent
- `module.activated` - Feature module enabled
- `report.generated` - Report exported
- `api.called` - API endpoint accessed



#### 3.3 Infrastructure Monitoring (Prometheus + Grafana)

**Purpose**: Collect and visualize infrastructure metrics.

**Architecture**:

```mermaid
graph TB
    subgraph "Application Servers"
        PHP[PHP-FPM]
        React[React Frontend]
        FastAPI[FastAPI]
    end
    
    subgraph "Infrastructure"
        Postgres[(PostgreSQL)]
        Redis[(Redis)]
        RabbitMQ[(RabbitMQ)]
    end
    
    subgraph "Monitoring Stack"
        Exporters[Prometheus Exporters]
        Prometheus[Prometheus Server]
        Grafana[Grafana]
    end
    
    PHP --> Exporters
    Postgres --> Exporters
    Redis --> Exporters
    RabbitMQ --> Exporters
    
    Exporters --> Prometheus
    Prometheus --> Grafana
    
    style Prometheus fill:#ff9999
    style Grafana fill:#99ff99
```

**Prometheus Configuration**:
```yaml
# prometheus/prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'nexsaas-php'
    static_configs:
      - targets: ['php-fpm:9253']
    
  - job_name: 'postgres'
    static_configs:
      - targets: ['postgres-exporter:9187']
    
  - job_name: 'redis'
    static_configs:
      - targets: ['redis-exporter:9121']
    
  - job_name: 'rabbitmq'
    static_configs:
      - targets: ['rabbitmq:15692']
    
  - job_name: 'node'
    static_configs:
      - targets: ['node-exporter:9100']

alerting:
  alertmanagers:
    - static_configs:
        - targets: ['alertmanager:9093']
```



**PHP Metrics Exporter**:
```php
// modular_core/modules/Platform/Monitoring/MetricsController.php
namespace Platform\Monitoring;

class MetricsController {
    public function metrics(): string {
        $metrics = [];
        
        // HTTP request metrics
        $metrics[] = $this->formatMetric(
            'http_requests_total',
            'counter',
            'Total HTTP requests',
            $this->getRequestCount()
        );
        
        // Database connection pool
        $metrics[] = $this->formatMetric(
            'db_connections_active',
            'gauge',
            'Active database connections',
            $this->getActiveConnections()
        );
        
        // Memory usage
        $metrics[] = $this->formatMetric(
            'php_memory_usage_bytes',
            'gauge',
            'PHP memory usage in bytes',
            memory_get_usage(true)
        );
        
        // Queue depth
        $metrics[] = $this->formatMetric(
            'rabbitmq_queue_depth',
            'gauge',
            'RabbitMQ queue depth',
            $this->getQueueDepth()
        );
        
        return implode("\n", $metrics);
    }
    
    private function formatMetric(string $name, string $type, string $help, $value): string {
        return "# HELP {$name} {$help}\n# TYPE {$name} {$type}\n{$name} {$value}";
    }
    
    private function getActiveConnections(): int {
        $db = Database::getInstance();
        $result = $db->query("SELECT count(*) FROM pg_stat_activity WHERE state = 'active'");
        return $result[0]['count'];
    }
    
    private function getQueueDepth(): int {
        $redis = Redis::getInstance();
        return $redis->llen('analytics_events');
    }
}
```



**Alert Rules**:
```yaml
# prometheus/alerts.yml
groups:
  - name: nexsaas_alerts
    interval: 30s
    rules:
      - alert: HighErrorRate
        expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.05
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value | humanizePercentage }} over the last 5 minutes"
      
      - alert: HighCPUUsage
        expr: 100 - (avg by(instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 80
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High CPU usage on {{ $labels.instance }}"
          description: "CPU usage is {{ $value | humanize }}%"
      
      - alert: HighMemoryUsage
        expr: (node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes) / node_memory_MemTotal_bytes * 100 > 85
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High memory usage on {{ $labels.instance }}"
          description: "Memory usage is {{ $value | humanize }}%"
      
      - alert: DatabaseConnectionPoolExhausted
        expr: db_connections_active >= 95
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "Database connection pool nearly exhausted"
          description: "{{ $value }} active connections out of 100"
      
      - alert: DiskSpaceRunningOut
        expr: (node_filesystem_avail_bytes / node_filesystem_size_bytes) * 100 < 10
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Disk space running out on {{ $labels.instance }}"
          description: "Only {{ $value | humanize }}% disk space remaining"
      
      - alert: SlowAPIResponse
        expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 3
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Slow API response times"
          description: "95th percentile response time is {{ $value | humanizeDuration }}"
```



**Grafana Dashboards**:

Dashboard 1: Application Overview
```json
{
  "dashboard": {
    "title": "NexSaaS Application Overview",
    "panels": [
      {
        "title": "Request Rate",
        "targets": [{"expr": "rate(http_requests_total[5m])"}],
        "type": "graph"
      },
      {
        "title": "Error Rate",
        "targets": [{"expr": "rate(http_requests_total{status=~\"5..\"}[5m])"}],
        "type": "graph"
      },
      {
        "title": "Response Time (p95)",
        "targets": [{"expr": "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))"}],
        "type": "graph"
      },
      {
        "title": "Active Users",
        "targets": [{"expr": "count(user_sessions_active)"}],
        "type": "stat"
      }
    ]
  }
}
```

Dashboard 2: Infrastructure Health
```json
{
  "dashboard": {
    "title": "Infrastructure Health",
    "panels": [
      {
        "title": "CPU Usage",
        "targets": [{"expr": "100 - (avg(irate(node_cpu_seconds_total{mode=\"idle\"}[5m])) * 100)"}],
        "type": "gauge"
      },
      {
        "title": "Memory Usage",
        "targets": [{"expr": "(node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes) / node_memory_MemTotal_bytes * 100"}],
        "type": "gauge"
      },
      {
        "title": "Database Connections",
        "targets": [{"expr": "db_connections_active"}],
        "type": "graph"
      },
      {
        "title": "Queue Depth",
        "targets": [{"expr": "rabbitmq_queue_depth"}],
        "type": "graph"
      }
    ]
  }
}
```



#### 3.4 Alerting System (PagerDuty)

**Purpose**: Route critical alerts to on-call engineers with escalation.

**Integration Architecture**:
```
Prometheus Alertmanager → PagerDuty API → On-call Engineer
                                        ↓
                                   Escalation Policy
```

**Alertmanager Configuration**:
```yaml
# prometheus/alertmanager.yml
global:
  resolve_timeout: 5m
  pagerduty_url: 'https://events.pagerduty.com/v2/enqueue'

route:
  receiver: 'pagerduty-critical'
  group_by: ['alertname', 'cluster', 'service']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 12h
  
  routes:
    - match:
        severity: critical
      receiver: 'pagerduty-critical'
      continue: true
    
    - match:
        severity: warning
      receiver: 'slack-warnings'

receivers:
  - name: 'pagerduty-critical'
    pagerduty_configs:
      - service_key: '{{ env "PAGERDUTY_SERVICE_KEY" }}'
        description: '{{ .GroupLabels.alertname }}: {{ .CommonAnnotations.summary }}'
        details:
          firing: '{{ .Alerts.Firing | len }}'
          resolved: '{{ .Alerts.Resolved | len }}'
          description: '{{ .CommonAnnotations.description }}'
  
  - name: 'slack-warnings'
    slack_configs:
      - api_url: '{{ env "SLACK_WEBHOOK_URL" }}'
        channel: '#alerts'
        title: '{{ .GroupLabels.alertname }}'
        text: '{{ .CommonAnnotations.description }}'

inhibit_rules:
  - source_match:
      severity: 'critical'
    target_match:
      severity: 'warning'
    equal: ['alertname', 'instance']
```



**PagerDuty Escalation Policy**:
```
Level 1: Primary On-call Engineer (immediate notification)
  ↓ (10 minutes without acknowledgment)
Level 2: Secondary On-call Engineer
  ↓ (10 minutes without acknowledgment)
Level 3: Engineering Manager
  ↓ (10 minutes without acknowledgment)
Level 4: CTO
```

**Notification Channels**:
- Push notification (PagerDuty mobile app)
- SMS
- Phone call (for critical alerts only)
- Email (backup channel)

#### 3.5 Usage Dashboard

**Purpose**: Visualize product analytics and user behavior.

**Dashboard Implementation**:
```php
// modular_core/modules/Platform/Analytics/UsageDashboardController.php
namespace Platform\Analytics;

class UsageDashboardController {
    private Database $db;
    
    public function getDailyActiveUsers(string $startDate, string $endDate): array {
        return $this->db->query("
            SELECT 
                DATE(created_at) as date,
                tenant_id,
                COUNT(DISTINCT user_id) as dau
            FROM business_events
            WHERE event_type = 'user.login'
            AND created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at), tenant_id
            ORDER BY date DESC
        ", [$startDate, $endDate]);
    }
    
    public function getFeatureAdoption(): array {
        return $this->db->query("
            SELECT 
                event_data->>'module_name' as module,
                COUNT(DISTINCT tenant_id) as tenant_count,
                COUNT(*) as activation_count
            FROM business_events
            WHERE event_type = 'module.activated'
            GROUP BY event_data->>'module_name'
            ORDER BY tenant_count DESC
        ");
    }
    
    public function getRetentionCohorts(): array {
        return $this->db->query("
            WITH cohorts AS (
                SELECT 
                    tenant_id,
                    DATE_TRUNC('month', created_at) as cohort_month
                FROM tenants
            ),
            activity AS (
                SELECT 
                    tenant_id,
                    DATE_TRUNC('month', created_at) as activity_month
                FROM business_events
                WHERE event_type = 'user.login'
            )
            SELECT 
                c.cohort_month,
                a.activity_month,
                COUNT(DISTINCT c.tenant_id) as cohort_size,
                COUNT(DISTINCT a.tenant_id) as active_tenants,
                ROUND(COUNT(DISTINCT a.tenant_id)::numeric / COUNT(DISTINCT c.tenant_id) * 100, 2) as retention_rate
            FROM cohorts c
            LEFT JOIN activity a ON c.tenant_id = a.tenant_id
            GROUP BY c.cohort_month, a.activity_month
            ORDER BY c.cohort_month, a.activity_month
        ");
    }
    
    public function getAverageSessionDuration(int $tenantId): float {
        $result = $this->db->query("
            SELECT AVG(session_duration_seconds) as avg_duration
            FROM (
                SELECT 
                    user_id,
                    DATE(created_at) as session_date,
                    MAX(created_at) - MIN(created_at) as session_duration_seconds
                FROM business_events
                WHERE tenant_id = ?
                AND created_at > NOW() - INTERVAL '30 days'
                GROUP BY user_id, DATE(created_at)
            ) sessions
        ", [$tenantId]);
        
        return $result[0]['avg_duration'] ?? 0;
    }
}
```



**React Dashboard Component**:
```jsx
// modular_core/react-frontend/src/modules/Analytics/UsageDashboard.jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

export default function UsageDashboard() {
  const [metrics, setMetrics] = useState({
    dau: [],
    featureAdoption: [],
    retention: []
  });
  const [dateRange, setDateRange] = useState({
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  });

  useEffect(() => {
    fetchMetrics();
    const interval = setInterval(fetchMetrics, 15 * 60 * 1000); // Refresh every 15 minutes
    return () => clearInterval(interval);
  }, [dateRange]);

  const fetchMetrics = async () => {
    const [dauRes, adoptionRes, retentionRes] = await Promise.all([
      axios.get('/api/analytics/dau', { params: dateRange }),
      axios.get('/api/analytics/feature-adoption'),
      axios.get('/api/analytics/retention-cohorts')
    ]);

    setMetrics({
      dau: dauRes.data,
      featureAdoption: adoptionRes.data,
      retention: retentionRes.data
    });
  };

  return (
    <div className="usage-dashboard">
      <h1>Usage Analytics</h1>
      
      <div className="date-range-picker">
        <input 
          type="date" 
          value={dateRange.start} 
          onChange={(e) => setDateRange({...dateRange, start: e.target.value})}
        />
        <input 
          type="date" 
          value={dateRange.end} 
          onChange={(e) => setDateRange({...dateRange, end: e.target.value})}
        />
      </div>

      <div className="metrics-grid">
        <div className="metric-card">
          <h3>Daily Active Users</h3>
          <LineChart data={metrics.dau} xKey="date" yKey="dau" />
        </div>

        <div className="metric-card">
          <h3>Feature Adoption</h3>
          <BarChart data={metrics.featureAdoption} xKey="module" yKey="tenant_count" />
        </div>

        <div className="metric-card">
          <h3>Retention Cohorts</h3>
          <CohortTable data={metrics.retention} />
        </div>
      </div>
    </div>
  );
}
```



## Data Models

### Documentation System

```sql
-- Video tutorials metadata
CREATE TABLE video_tutorials (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    vimeo_id VARCHAR(50) NOT NULL,
    duration_seconds INT NOT NULL,
    workflow_category VARCHAR(100),
    language VARCHAR(10) DEFAULT 'en',
    subtitle_url_ar VARCHAR(500),
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Documentation page views (for trending articles)
CREATE TABLE doc_page_views (
    id BIGSERIAL PRIMARY KEY,
    page_slug VARCHAR(255) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    user_id INT,
    tenant_id INT,
    viewed_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_doc_views_page ON doc_page_views(page_slug);
CREATE INDEX idx_doc_views_date ON doc_page_views(viewed_at);

-- Documentation feedback
CREATE TABLE doc_feedback (
    id SERIAL PRIMARY KEY,
    page_slug VARCHAR(255) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    user_id INT,
    helpful BOOLEAN NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);
```



### Support System

```sql
-- Support tickets
CREATE TABLE support_tickets (
    id SERIAL PRIMARY KEY,
    tenant_id INT NOT NULL REFERENCES tenants(id),
    user_id INT NOT NULL REFERENCES users(id),
    linear_issue_id VARCHAR(50) UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20) CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status VARCHAR(50) DEFAULT 'open',
    assigned_to VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP,
    CONSTRAINT fk_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

CREATE INDEX idx_tickets_tenant ON support_tickets(tenant_id);
CREATE INDEX idx_tickets_status ON support_tickets(status);
CREATE INDEX idx_tickets_priority ON support_tickets(priority);
CREATE INDEX idx_tickets_created ON support_tickets(created_at);

-- Ticket comments/updates
CREATE TABLE ticket_comments (
    id SERIAL PRIMARY KEY,
    ticket_id INT NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id),
    comment TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_comments_ticket ON ticket_comments(ticket_id);

-- Chat transcripts (from Crisp)
CREATE TABLE chat_transcripts (
    id SERIAL PRIMARY KEY,
    tenant_id INT NOT NULL REFERENCES tenants(id),
    user_id INT REFERENCES users(id),
    crisp_session_id VARCHAR(100) UNIQUE NOT NULL,
    messages JSONB NOT NULL,
    started_at TIMESTAMP NOT NULL,
    ended_at TIMESTAMP,
    satisfaction_rating INT CHECK (satisfaction_rating BETWEEN 1 AND 5)
);

CREATE INDEX idx_transcripts_tenant ON chat_transcripts(tenant_id);
CREATE INDEX idx_transcripts_started ON chat_transcripts(started_at);
```



### Analytics System

```sql
-- Business events (already defined earlier, repeated for completeness)
CREATE TABLE business_events (
    id BIGSERIAL PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT,
    event_type VARCHAR(100) NOT NULL,
    event_data JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
) PARTITION BY RANGE (created_at);

CREATE INDEX idx_events_tenant ON business_events(tenant_id);
CREATE INDEX idx_events_type ON business_events(event_type);
CREATE INDEX idx_events_created ON business_events(created_at);
CREATE INDEX idx_events_data ON business_events USING GIN(event_data);

-- Create partitions for each month
CREATE TABLE business_events_2024_01 PARTITION OF business_events
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
CREATE TABLE business_events_2024_02 PARTITION OF business_events
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');
-- ... continue for each month

-- Aggregated daily metrics (for faster dashboard queries)
CREATE TABLE daily_metrics (
    id SERIAL PRIMARY KEY,
    tenant_id INT NOT NULL,
    metric_date DATE NOT NULL,
    active_users INT DEFAULT 0,
    leads_created INT DEFAULT 0,
    deals_closed INT DEFAULT 0,
    emails_sent INT DEFAULT 0,
    api_calls INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(tenant_id, metric_date)
);

CREATE INDEX idx_daily_metrics_tenant ON daily_metrics(tenant_id);
CREATE INDEX idx_daily_metrics_date ON daily_metrics(metric_date);

-- Materialized view for retention cohorts (refreshed daily)
CREATE MATERIALIZED VIEW retention_cohorts AS
WITH cohorts AS (
    SELECT 
        tenant_id,
        DATE_TRUNC('month', created_at) as cohort_month
    FROM tenants
),
activity AS (
    SELECT 
        tenant_id,
        DATE_TRUNC('month', created_at) as activity_month
    FROM business_events
    WHERE event_type = 'user.login'
    GROUP BY tenant_id, DATE_TRUNC('month', created_at)
)
SELECT 
    c.cohort_month,
    a.activity_month,
    COUNT(DISTINCT c.tenant_id) as cohort_size,
    COUNT(DISTINCT a.tenant_id) as active_tenants,
    ROUND(COUNT(DISTINCT a.tenant_id)::numeric / NULLIF(COUNT(DISTINCT c.tenant_id), 0) * 100, 2) as retention_rate
FROM cohorts c
LEFT JOIN activity a ON c.tenant_id = a.tenant_id
GROUP BY c.cohort_month, a.activity_month;

CREATE UNIQUE INDEX idx_retention_cohorts ON retention_cohorts(cohort_month, activity_month);
```



## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, I identified several areas where properties can be consolidated:

**Consolidated Analytics Tracking**: Requirements 15.1-15.6 all test that specific events are tracked. These can be combined into a single property: "For any trackable user action, an analytics event should be recorded."

**Consolidated Monitoring Metrics**: Requirements 17.1-17.5 all test that specific metrics are tracked. These can be combined: "For any infrastructure component, relevant metrics should be collected."

**Consolidated Alert Thresholds**: Requirements 18.1-18.4 all test that alerts are sent when thresholds are exceeded. These can be combined: "For any monitored metric exceeding its threshold, an alert should be sent."

**Documentation Completeness**: Requirements 1.3, 1.4, 1.5 all test that generated OpenAPI specs are complete. These can be combined: "For any generated OpenAPI specification, all required fields should be present."

**Video Tutorial Properties**: Requirements 6.2, 6.4, 6.5 can be combined: "For any video tutorial, it should meet duration requirements, have Arabic subtitles, and be embedded in the help center."



### Property 1: OpenAPI Generation Validity

*For any* set of controller annotations, the generated OpenAPI specification should be valid OpenAPI 3.0 JSON that passes schema validation.

**Validates: Requirements 1.1**

### Property 2: OpenAPI Specification Completeness

*For any* endpoint in the generated OpenAPI specification, it should include request examples, response schemas with field descriptions, authentication requirements, and error code definitions.

**Validates: Requirements 1.3, 1.4, 1.5, 1.6**

### Property 3: API Change Triggers Regeneration

*For any* modification to controller annotations, running the build pipeline should regenerate the OpenAPI specification with the updated changes.

**Validates: Requirements 1.7**

### Property 4: Webhook Documentation Completeness

*For any* webhook event type in the system, the Developer Portal should document the event with payload examples.

**Validates: Requirements 3.1, 3.2**

### Property 5: Postman Collection Completeness

*For any* public API endpoint, it should be included in the generated Postman collection with example request bodies and organized by module.

**Validates: Requirements 4.1, 4.3, 4.4**

### Property 6: User Guide Feature Coverage

*For any* major platform feature, there should be corresponding documentation in the User Guide.

**Validates: Requirements 5.2**

### Property 7: User Guide Search Functionality

*For any* keyword search in the User Guide, results should be returned in the user's selected language (English or Arabic).

**Validates: Requirements 5.4**

### Property 8: Language Preference Routing

*For any* user with Arabic language preference, accessing the help system should display Arabic content by default.

**Validates: Requirements 5.6**

### Property 9: Video Tutorial Requirements

*For any* video tutorial, its duration should be between 2 and 5 minutes, it should have Arabic subtitles available, and it should be embedded in the Help Center.

**Validates: Requirements 6.2, 6.4, 6.5**

### Property 10: FAQ Entry Structure

*For any* FAQ entry, it should have an assigned category and include links to related User Guide sections where applicable.

**Validates: Requirements 7.2, 7.4**

### Property 11: FAQ Search Relevance

*For any* search query on the FAQ page, results should be returned ranked by relevance.

**Validates: Requirements 7.3, 7.6**

### Property 12: Changelog Entry Categorization

*For any* changelog entry, it should be categorized as either Feature, Improvement, or Fix, and include a release date.

**Validates: Requirements 8.1, 8.2, 8.3**

### Property 13: Chat Widget Availability

*For any* page in the application, the chat widget should be accessible and functional.

**Validates: Requirements 9.1**

### Property 14: Chat Message Delivery Time

*For any* message sent through the chat widget, it should be delivered to the support team within 5 seconds.

**Validates: Requirements 9.2**

### Property 15: Chat Session History Persistence

*For any* chat session, all messages should be retrievable for the duration of the session.

**Validates: Requirements 9.4**

### Property 16: File Attachment Size Validation

*For any* file attachment, files under 10MB should be accepted and files over 10MB should be rejected with an appropriate error message.

**Validates: Requirements 9.5**

### Property 17: Enterprise Support Routing

*For any* Enterprise customer sending a chat message, it should be routed to the priority support queue.

**Validates: Requirements 9.6**

### Property 18: Ticket Creation for Issues

*For any* technical issue reported, a support ticket should be created with a unique ticket ID.

**Validates: Requirements 10.1, 10.2**

### Property 19: Ticket Status Validity

*For any* support ticket, its status should be one of: Open, In Progress, Resolved, or Closed, and its priority should be one of: Low, Medium, High, or Critical.

**Validates: Requirements 10.3, 10.4**

### Property 20: Ticket Status Change Notifications

*For any* ticket status change, an email notification should be sent to the customer.

**Validates: Requirements 10.5**

### Property 21: Ticket Escalation After 24 Hours

*For any* ticket that remains unresolved for 24 hours, it should be escalated to senior support.

**Validates: Requirements 10.7**

### Property 22: Status Page Service Coverage

*For any* platform service, its current operational status should be displayed on the status page.

**Validates: Requirements 11.1**

### Property 23: Incident Report Timing

*For any* detected service degradation, an incident report should appear on the status page within 5 minutes.

**Validates: Requirements 11.4**

### Property 24: Maintenance Window Advance Notice

*For any* scheduled maintenance, it should be displayed on the status page at least 48 hours before the maintenance window.

**Validates: Requirements 11.5**

### Property 25: Enterprise SLA Document Delivery

*For any* Enterprise customer during onboarding, they should receive a digitally signed SLA document.

**Validates: Requirements 12.6**

### Property 26: Help Center Search Coverage

*For any* search query in the Help Center, results should include matches from User Guide, FAQ, Video Tutorials, and Changelog.

**Validates: Requirements 13.2**

### Property 27: Article View Count Tracking

*For any* article view in the Help Center, the view count for that article should increment by one.

**Validates: Requirements 13.4**

### Property 28: Exception Capture Completeness

*For any* unhandled exception in the PHP backend or JavaScript error in the frontend, it should be captured by the error tracking system with stack trace, request context, and user information.

**Validates: Requirements 14.1, 14.2, 14.4**

### Property 29: Error Grouping Logic

*For any* two errors with the same error type and similar stack traces, they should be grouped together in the error tracking system.

**Validates: Requirements 14.3**

### Property 30: High-Frequency Error Alerting

*For any* error that occurs more than 10 times within 5 minutes, an alert should be sent to the development team.

**Validates: Requirements 14.6**

### Property 31: Analytics Event Recording

*For any* trackable user action (login, lead creation, email sent, deal closed, module activation, user invitation), an analytics event should be recorded with tenant_id, user_id, event_type, and event_data.

**Validates: Requirements 15.1, 15.2, 15.3, 15.4, 15.5, 15.6**

### Property 32: Analytics Data Retention

*For any* business event, it should be retrievable from the analytics system for at least 2 years after creation.

**Validates: Requirements 15.7**

### Property 33: Performance Metric Tracking

*For any* API request, database query, or external API call, its response time should be tracked by the performance monitor.

**Validates: Requirements 16.1, 16.2, 16.3**

### Property 34: Performance Threshold Warnings

*For any* API endpoint with response time exceeding 1 second, a warning should be logged; for response times exceeding 3 seconds, an alert should be sent.

**Validates: Requirements 16.5, 16.6**

### Property 35: Infrastructure Metric Collection

*For any* application server, CPU usage, RAM usage, database connection pool utilization, database query response time, and disk space usage should be tracked.

**Validates: Requirements 17.1, 17.2, 17.3, 17.4, 17.5**

### Property 36: CPU Usage Alert Threshold

*For any* server with CPU usage exceeding 80% for 5 consecutive minutes, an alert should be sent.

**Validates: Requirements 17.8**

### Property 37: Alert Threshold Monitoring

*For any* monitored metric (error rate > 5%, API response time > 3s, connection pool exhausted, disk space > 90%), when the threshold is exceeded, an alert should be sent via email, SMS, and push notification.

**Validates: Requirements 18.1, 18.2, 18.3, 18.4, 18.5**

### Property 38: Duplicate Alert Suppression

*For any* alert, duplicate alerts of the same type should be suppressed within a 15-minute window.

**Validates: Requirements 18.7**

### Property 39: Critical Alert Escalation

*For any* critical severity alert that remains unacknowledged for 10 minutes, it should be escalated to the next level in the escalation policy.

**Validates: Requirements 18.8**

### Property 40: Usage Dashboard Filtering

*For any* filter applied on the usage dashboard (date range or tenant tier), the displayed data should match the filter criteria.

**Validates: Requirements 19.5**

### Property 41: Usage Dashboard Access Control

*For any* non-administrator user attempting to access the usage dashboard, access should be denied with a 403 Forbidden response.

**Validates: Requirements 19.7**

### Property 42: Documentation Timestamp Tracking

*For any* documentation page, it should have a last_updated timestamp that reflects the most recent modification.

**Validates: Requirements 20.3**

### Property 43: Stale Documentation Flagging

*For any* documentation page with a last_updated timestamp older than 90 days, it should be flagged for review.

**Validates: Requirements 20.4**



## Error Handling

### Documentation System Errors

**OpenAPI Generation Failures**:
- Invalid annotation syntax → Log error with file/line number, skip endpoint, continue generation
- Missing required fields → Use default values, log warning
- Circular schema references → Detect and break cycles, log warning

**Help Center Errors**:
- Search service unavailable → Display cached popular articles, show error banner
- Missing translation → Fall back to English version, log missing translation
- Video embed failure → Display placeholder with download link

**Postman Collection Errors**:
- Invalid endpoint definition → Skip endpoint, log error, continue generation
- Environment variable missing → Use placeholder values, document in README

### Support Infrastructure Errors

**Chat Widget Errors**:
- Crisp service unavailable → Display "Support temporarily unavailable" message, offer email alternative
- Message delivery failure → Retry 3 times with exponential backoff, then create support ticket
- File upload failure → Show error message, suggest file size reduction

**Ticketing System Errors**:
- Linear API unavailable → Queue ticket creation in RabbitMQ, retry when service recovers
- Duplicate ticket detection → Link to existing ticket, notify user
- Invalid priority/status value → Default to Medium/Open, log validation error

**Status Page Errors**:
- Health check timeout → Mark service as degraded after 3 consecutive failures
- GitHub Actions failure → Send alert to ops team, manual status update required
- False positive detection → Implement 5-minute grace period before declaring incident

### Monitoring & Analytics Errors

**Error Tracking Errors**:
- Sentry quota exceeded → Log errors locally, send daily summary email
- Network failure to Sentry → Buffer errors in Redis, flush when connection restored
- PII detected in error data → Scrub sensitive fields before sending

**Analytics Errors**:
- RabbitMQ queue full → Drop non-critical events, log dropped count, alert ops team
- Database write failure → Retry 3 times, then log to file for manual recovery
- Invalid event data → Log validation error, store in dead letter queue for investigation

**Monitoring Errors**:
- Prometheus scrape failure → Retry on next interval, alert if 3 consecutive failures
- Grafana dashboard load failure → Display cached version, show staleness warning
- Alert delivery failure → Retry via alternate channel (email if SMS fails), escalate if all channels fail

### Error Response Format

All API errors follow this structure:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": {
      "field": "Additional context"
    },
    "timestamp": "2024-01-15T10:30:00Z",
    "request_id": "req_abc123"
  }
}
```

### Retry Policies

| Service | Initial Delay | Max Retries | Backoff Strategy |
|---------|---------------|-------------|------------------|
| Linear API | 1s | 3 | Exponential (2x) |
| Sentry | 500ms | 5 | Exponential (2x) |
| RabbitMQ | 100ms | 10 | Linear (100ms) |
| Database | 50ms | 3 | Exponential (2x) |
| External APIs | 2s | 3 | Exponential (2x) |



## Testing Strategy

### Dual Testing Approach

This feature requires both unit testing and property-based testing for comprehensive coverage:

- **Unit tests**: Verify specific examples, edge cases, error conditions, and integration points
- **Property tests**: Verify universal properties across all inputs through randomization

Together, these approaches provide comprehensive coverage where unit tests catch concrete bugs and property tests verify general correctness.

### Property-Based Testing Configuration

**Library Selection**: Use `giorgiosironi/eris` for PHP property-based testing (already in composer.json)

**Test Configuration**:
- Minimum 100 iterations per property test (due to randomization)
- Each property test must reference its design document property
- Tag format: `@group Feature: go-to-market, Property {number}: {property_text}`

**Example Property Test Structure**:

```php
// modular_core/tests/Properties/OpenAPIGenerationTest.php
namespace Tests\Properties;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group Feature: go-to-market, Property 1: OpenAPI Generation Validity
 */
class OpenAPIGenerationTest extends TestCase {
    use TestTrait;
    
    public function testGeneratedSpecIsValidOpenAPI() {
        $this->forAll(
            Generator\associative([
                'method' => Generator\elements('GET', 'POST', 'PUT', 'DELETE'),
                'path' => Generator\regex('/api/[a-z]+/[a-z]+'),
                'summary' => Generator\string(),
                'parameters' => Generator\seq(Generator\associative([
                    'name' => Generator\string(),
                    'type' => Generator\elements('string', 'integer', 'boolean'),
                    'required' => Generator\bool()
                ]))
            ])
        )
        ->then(function ($annotation) {
            $generator = new OpenAPIGenerator();
            $spec = $generator->generateFromAnnotation($annotation);
            
            // Validate against OpenAPI 3.0 schema
            $validator = new OpenAPIValidator();
            $this->assertTrue(
                $validator->validate($spec),
                "Generated spec should be valid OpenAPI 3.0"
            );
        })
        ->withMaxSize(100);
    }
}
```



### Unit Testing Strategy

**Documentation System Tests**:
- Test OpenAPI generator with sample controller annotations
- Test Postman collection generation from OpenAPI spec
- Test Docusaurus build process
- Test video metadata storage and retrieval
- Mock external services (Vimeo API, Algolia search)

**Support Infrastructure Tests**:
- Test Crisp widget initialization with user context
- Test Linear API integration (create ticket, update status, query tickets)
- Test ticket escalation worker with time-mocked scenarios
- Test status page health check endpoints
- Test SLA document generation and signing

**Monitoring & Analytics Tests**:
- Test Sentry integration with sample errors
- Test event emitter publishes to RabbitMQ
- Test analytics worker consumes and stores events
- Test Prometheus metrics endpoint format
- Test alert rule evaluation logic
- Test PagerDuty webhook integration
- Test usage dashboard queries with sample data

**Edge Cases to Test**:
- Empty or malformed annotations in OpenAPI generation
- Chat messages with special characters or very long text
- Tickets with missing required fields
- Analytics events with invalid JSON
- Metrics with extreme values (negative, infinity, NaN)
- Concurrent ticket updates causing race conditions
- Alert storms (many alerts in short time)

### Integration Testing

**End-to-End Scenarios**:
1. User reports issue via chat → Ticket created in Linear → Status updated → Email sent
2. API endpoint added → OpenAPI regenerated → Postman collection updated → Documentation deployed
3. Error occurs → Sentry captures → Alert threshold exceeded → PagerDuty notified → Engineer acknowledges
4. User searches help center → Algolia returns results → Article viewed → View count incremented
5. Service degradation detected → Status page updated → Subscribers notified → Incident resolved

**Performance Testing**:
- Load test analytics event ingestion (10,000 events/second)
- Load test help center search (1,000 concurrent searches)
- Load test Prometheus metrics endpoint (100 requests/second)
- Verify alert suppression under high load

### Test Data Management

**Fixtures**:
- Sample controller annotations for OpenAPI generation
- Sample webhook events for documentation
- Sample support tickets with various statuses
- Sample analytics events for dashboard queries
- Sample error data for Sentry integration

**Test Database**:
- Use separate test database with migrations
- Seed with realistic test data
- Clean up after each test suite
- Use transactions for test isolation

### Continuous Integration

**CI Pipeline**:
```yaml
# .github/workflows/test.yml
name: Test Go-to-Market Infrastructure

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pgsql, redis
      
      - name: Install dependencies
        run: composer install
      
      - name: Run unit tests
        run: vendor/bin/phpunit --testsuite=unit
      
      - name: Run property tests
        run: vendor/bin/phpunit --testsuite=properties --group=go-to-market
      
      - name: Generate OpenAPI spec
        run: php cli/generate_openapi.php --output=public/openapi.json
      
      - name: Validate OpenAPI spec
        run: npx @apidevtools/swagger-cli validate public/openapi.json
      
      - name: Build documentation
        run: |
          cd docs
          npm install
          npm run build
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

### Monitoring Test Coverage

**Coverage Targets**:
- Unit test coverage: > 80%
- Property test coverage: All 43 properties implemented
- Integration test coverage: All 5 end-to-end scenarios
- Edge case coverage: All identified edge cases tested

**Coverage Reporting**:
- Use PHPUnit with Xdebug for code coverage
- Generate HTML coverage reports
- Track coverage trends over time
- Fail CI if coverage drops below threshold



## Deployment Considerations

### Infrastructure Requirements

**Additional Services**:
```yaml
# docker-compose.monitoring.yml
version: "3.9"

services:
  prometheus:
    image: prom/prometheus:latest
    container_name: nexsaas_prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus/prometheus.yml:/etc/prometheus/prometheus.yml
      - ./prometheus/alerts.yml:/etc/prometheus/alerts.yml
      - prometheus_data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
    networks:
      - nexsaas

  grafana:
    image: grafana/grafana:latest
    container_name: nexsaas_grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD}
      - GF_INSTALL_PLUGINS=redis-datasource
    volumes:
      - grafana_data:/var/lib/grafana
      - ./grafana/dashboards:/etc/grafana/provisioning/dashboards
    networks:
      - nexsaas

  alertmanager:
    image: prom/alertmanager:latest
    container_name: nexsaas_alertmanager
    ports:
      - "9093:9093"
    volumes:
      - ./prometheus/alertmanager.yml:/etc/alertmanager/alertmanager.yml
    networks:
      - nexsaas

  postgres-exporter:
    image: prometheuscommunity/postgres-exporter:latest
    container_name: nexsaas_postgres_exporter
    environment:
      DATA_SOURCE_NAME: "postgresql://${DB_USER}:${DB_PASSWORD}@postgres:5432/${DB_NAME}?sslmode=disable"
    ports:
      - "9187:9187"
    networks:
      - nexsaas

  redis-exporter:
    image: oliver006/redis_exporter:latest
    container_name: nexsaas_redis_exporter
    environment:
      REDIS_ADDR: "redis:6379"
      REDIS_PASSWORD: ${REDIS_PASSWORD}
    ports:
      - "9121:9121"
    networks:
      - nexsaas

volumes:
  prometheus_data:
  grafana_data:

networks:
  nexsaas:
    external: true
```

### Environment Variables

```bash
# .env additions for go-to-market infrastructure

# Sentry
SENTRY_DSN=https://xxx@xxx.ingest.sentry.io/xxx
SENTRY_ENVIRONMENT=production
APP_VERSION=1.0.0

# Crisp
CRISP_WEBSITE_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx

# Linear
LINEAR_API_KEY=lin_api_xxxxxxxxxxxxxxxxxxxxx
LINEAR_TEAM_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx

# PagerDuty
PAGERDUTY_SERVICE_KEY=xxxxxxxxxxxxxxxxxxxxx
PAGERDUTY_INTEGRATION_KEY=xxxxxxxxxxxxxxxxxxxxx

# Vimeo
VIMEO_ACCESS_TOKEN=xxxxxxxxxxxxxxxxxxxxx

# Algolia (for documentation search)
ALGOLIA_APP_ID=XXXXXXXXXXXXX
ALGOLIA_API_KEY=xxxxxxxxxxxxxxxxxxxxx
ALGOLIA_INDEX_NAME=nexsaas_docs

# Grafana
GRAFANA_PASSWORD=secure_password_here

# Slack (for alerts)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx/xxx/xxx
```

### Deployment Checklist

**Pre-Launch**:
- [ ] OpenAPI specification generated and validated
- [ ] Swagger UI accessible at /api/docs
- [ ] Postman collection generated and tested
- [ ] Help Center deployed to docs.nexsaas.com
- [ ] All documentation translated to Arabic
- [ ] 10 video tutorials recorded and uploaded to Vimeo
- [ ] Crisp chat widget integrated and tested
- [ ] Linear integration configured
- [ ] Status page deployed to status.nexsaas.com
- [ ] SLA documents prepared and reviewed by legal
- [ ] Sentry projects created for PHP and JavaScript
- [ ] Prometheus and Grafana dashboards configured
- [ ] PagerDuty escalation policies defined
- [ ] Alert rules tested with synthetic incidents
- [ ] Analytics event tracking verified in production
- [ ] Usage dashboard accessible to admins

**Post-Launch Monitoring**:
- Monitor error rates in Sentry (target: < 0.1%)
- Monitor API response times (target: p95 < 500ms)
- Monitor help center search performance
- Monitor chat widget response times
- Monitor ticket resolution times (target: < 24 hours for high priority)
- Monitor status page uptime (target: 99.99%)
- Monitor analytics event ingestion lag (target: < 1 minute)
- Monitor alert delivery success rate (target: 100%)

### Rollback Plan

If critical issues are detected:

1. **Documentation Issues**: Revert to previous Docusaurus build, update DNS if needed
2. **Chat Widget Issues**: Disable Crisp widget, display email support form
3. **Monitoring Issues**: Fall back to manual monitoring, use backup alerting channels
4. **Analytics Issues**: Pause event collection, investigate data loss, restore from backups

### Scaling Considerations

**Documentation**:
- Use CDN (Cloudflare) for help center static assets
- Enable Algolia search caching
- Implement lazy loading for video embeds

**Support**:
- Scale Crisp plan based on concurrent chat sessions
- Implement ticket queue prioritization
- Add support agent capacity monitoring

**Monitoring**:
- Partition analytics events table by month
- Implement Prometheus federation for multi-region deployments
- Use Grafana query caching for dashboards
- Implement alert aggregation to prevent alert storms

**Analytics**:
- Use read replicas for usage dashboard queries
- Implement materialized views for complex aggregations
- Archive events older than 2 years to cold storage
- Implement event sampling for high-volume tenants (> 1M events/day)



## Security Considerations

### API Documentation Security

- Swagger UI requires authentication for "Try it out" functionality
- OpenAPI spec excludes internal/admin endpoints
- Rate limiting on /api/docs endpoint (100 requests/minute per IP)
- No sensitive data (API keys, passwords) in example requests

### Support System Security

- Chat transcripts encrypted at rest
- Support tickets follow tenant isolation rules
- Linear API key stored in secrets manager, not environment variables
- File uploads scanned for malware before storage
- Customer portal requires authentication and RBAC

### Monitoring Security

- Grafana dashboards require admin authentication
- Prometheus metrics endpoint restricted to internal network
- PII scrubbed from error reports before sending to Sentry
- Alert webhooks use HMAC signature verification
- Usage dashboard enforces row-level security by tenant

### Data Privacy

- Analytics events anonymized for GDPR compliance
- User consent required before tracking non-essential events
- Data retention policies enforced (2 years for analytics, 90 days for logs)
- Right to deletion implemented for user data
- Data export API for GDPR data portability requests

## Performance Optimization

### Documentation Performance

- OpenAPI spec cached in Redis (TTL: 1 hour)
- Help center uses static site generation (build time: ~2 minutes)
- Video thumbnails lazy-loaded
- Search results cached for 5 minutes
- Postman collection served from CDN

### Support Performance

- Chat widget loads asynchronously (non-blocking)
- Ticket queries use database indexes on tenant_id, status, created_at
- Escalation worker runs hourly (low priority background job)
- Status page uses GitHub Pages (globally distributed)

### Monitoring Performance

- Prometheus scrape interval: 15 seconds (balance between freshness and load)
- Grafana query timeout: 30 seconds
- Analytics events batched (100 events per write)
- Materialized views refreshed daily at 2 AM UTC
- Usage dashboard queries use pre-aggregated daily_metrics table

## Maintenance and Operations

### Regular Maintenance Tasks

**Daily**:
- Review Sentry error reports
- Check PagerDuty incident log
- Verify analytics event ingestion lag
- Review support ticket backlog

**Weekly**:
- Update help center content based on support tickets
- Review and update FAQ based on common questions
- Check video tutorial view counts, identify gaps
- Review Grafana dashboards for anomalies

**Monthly**:
- Regenerate OpenAPI spec and Postman collection
- Update changelog with new releases
- Review and update SLA documents
- Audit alert rules for false positives
- Archive old analytics events (> 2 years)
- Review documentation staleness flags

**Quarterly**:
- Conduct documentation review with product team
- Update video tutorials for changed workflows
- Review and optimize Prometheus retention policies
- Audit support ticket resolution times
- Review and update escalation policies

### Runbook: Common Issues

**Issue: OpenAPI generation fails**
- Check controller annotation syntax
- Verify PHP version compatibility
- Review generation logs in `/var/log/openapi-generator.log`
- Manually fix invalid annotations
- Re-run generation: `php cli/generate_openapi.php`

**Issue: Chat widget not loading**
- Check Crisp service status
- Verify CRISP_WEBSITE_ID environment variable
- Check browser console for JavaScript errors
- Test with Crisp widget disabled (fallback to email form)

**Issue: Alerts not being sent**
- Check Alertmanager status: `curl http://localhost:9093/-/healthy`
- Verify PagerDuty integration key
- Check alert rule evaluation: `curl http://localhost:9090/api/v1/rules`
- Test alert manually: `curl -X POST http://localhost:9093/api/v1/alerts`

**Issue: Analytics events not appearing in dashboard**
- Check RabbitMQ queue depth: `rabbitmqctl list_queues`
- Verify analytics worker is running: `ps aux | grep analytics_worker`
- Check database connection
- Review worker logs: `tail -f /var/log/analytics-worker.log`

**Issue: Help center search not working**
- Check Algolia service status
- Verify Algolia API key and app ID
- Re-index documentation: `npm run algolia-reindex`
- Check search query logs in Algolia dashboard

## Conclusion

This design provides a comprehensive go-to-market infrastructure covering documentation, support, and monitoring. The architecture leverages industry-standard tools (Sentry, Prometheus, Grafana, PagerDuty) while maintaining flexibility for future enhancements.

Key design decisions:
- **OpenAPI-first approach** ensures API documentation stays synchronized with code
- **Docusaurus** provides excellent multilingual support and search capabilities
- **Crisp + Linear integration** creates seamless support workflow
- **Upptime** offers zero-infrastructure status page solution
- **PostgreSQL for analytics** reuses existing infrastructure and provides powerful JSONB querying
- **Prometheus + Grafana** industry-standard monitoring with extensive ecosystem

The 43 correctness properties ensure comprehensive test coverage, while the dual testing approach (unit + property-based) provides confidence in system reliability. The design prioritizes operational excellence through proactive monitoring, clear error handling, and well-defined escalation policies.

