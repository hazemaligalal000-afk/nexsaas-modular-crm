# NexSaaS Modular CRM — Extended Feature Roadmap

> **Purpose:** This document defines the story, requirements, technical design, and task breakdown
> for every new feature and integration to be built on top of the core platform.
> **Companion to:** `NEXSAAS_READY_TO_SELL.md` — complete Phase 0–4 before starting anything here.

---

## Table of Contents

### Product Features
- [F1 — Email Marketing Automation](#f1--email-marketing-automation)
- [F2 — Advanced Reporting & Analytics](#f2--advanced-reporting--analytics)
- [F3 — Customer Portal](#f3--customer-portal)
- [F4 — Mobile App (iOS & Android)](#f4--mobile-app-ios--android)

### Integrations
- [I1 — Slack & Microsoft Teams](#i1--slack--microsoft-teams)
- [I2 — Zapier & Make (No-code Automation)](#i2--zapier--make-no-code-automation)
- [I3 — Google Workspace](#i3--google-workspace)
- [I4 — Payment Gateways (PayPal, Paymob, Fawry)](#i4--payment-gateways-paypal-paymob-fawry)

### SaaS Business Features
- [S1 — Affiliate & Referral Program](#s1--affiliate--referral-program)
- [S2 — White-label & Reseller Mode](#s2--white-label--reseller-mode)
- [S3 — Agency Multi-client Dashboard](#s3--agency-multi-client-dashboard)
- [S4 — AI Chatbot for Customer Support](#s4--ai-chatbot-for-customer-support)

---

## Effort & Priority Overview

| ID | Feature | Priority | Estimated Days |
|----|---------|----------|---------------|
| F1 | Email Marketing Automation | 🔴 High | 12 days |
| F2 | Advanced Reporting & Analytics | 🔴 High | 10 days |
| F3 | Customer Portal | 🟠 Medium | 8 days |
| F4 | Mobile App | 🟠 Medium | 20 days |
| I1 | Slack & Microsoft Teams | 🟠 Medium | 5 days |
| I2 | Zapier & Make | 🟠 Medium | 7 days |
| I3 | Google Workspace | 🔴 High | 8 days |
| I4 | Payment Gateways (PayPal, Paymob, Fawry) | 🔴 High | 6 days |
| S1 | Affiliate & Referral Program | 🟡 Low | 7 days |
| S2 | White-label & Reseller Mode | 🔴 High | 10 days |
| S3 | Agency Multi-client Dashboard | 🟠 Medium | 8 days |
| S4 | AI Chatbot for Customer Support | 🔴 High | 12 days |
| **Total** | | | **~113 days** |

---

---

# Product Features

---

## F1 — Email Marketing Automation

### The Story

A sales team that relies entirely on one-to-one outreach will always hit a ceiling. The moment a company grows past 20 leads per day, manually writing individual follow-up emails becomes a full-time job on its own. Email marketing automation is the feature that lets NexSaaS compete directly with HubSpot and ActiveCampaign — and it is the feature most likely to become a primary reason customers choose the platform over a simpler CRM.

The goal is not just bulk email. The goal is behavior-triggered, personalized sequences that run on their own. A lead visits the pricing page three times? They automatically enter a "high-intent" nurture sequence. A deal goes cold for 14 days? A re-engagement campaign fires without anyone touching it.

### Requirements

**Campaign Builder:**
- Drag-and-drop email editor with reusable blocks (text, image, CTA button, divider).
- Template library: 10 pre-built templates for common scenarios (welcome, follow-up, re-engagement, invoice sent, trial expiring).
- Personalization tokens: `{{contact.first_name}}`, `{{company.name}}`, `{{lead.score}}`, `{{agent.name}}`.
- Preview mode: desktop and mobile preview before sending.
- Plain-text fallback generated automatically from the HTML version.

**Sequence Automation:**
- Visual sequence builder: drag steps onto a timeline (email, wait, condition, action).
- Trigger types: lead created, lead score crosses threshold, deal stage changed, form submitted, tag added, date-based.
- Condition branching: if email opened → send follow-up A; if not opened after 3 days → send follow-up B.
- Exit conditions: contact replied, deal won, contact unsubscribed.
- Time-zone aware sending: deliver at 9 AM in the contact's local timezone.

**Deliverability:**
- Dedicated sending domain per tenant with SPF, DKIM, and DMARC setup guidance.
- Bounce handling: soft bounces retry 3 times, hard bounces auto-unsubscribe.
- Spam score check before sending (SpamAssassin integration).
- Unsubscribe link mandatory in every email, one-click compliance.
- CAN-SPAM and GDPR compliant opt-out handling.

**Analytics per Campaign:**
- Open rate, click rate, bounce rate, unsubscribe rate.
- Click map: which links inside the email were clicked and how many times.
- Revenue attribution: deals won from contacts in this campaign.
- A/B test: split subject lines or content blocks, auto-pick winner after 4 hours.

### Technical Design

```
modular_core/modules/EmailMarketing/
├── CampaignController.php
├── SequenceController.php
├── SequenceEngine.php          # Cron-driven sequence step processor
├── EmailSender.php             # Sends via SES / Mailgun / SendGrid
├── TrackingService.php         # Open pixel + click redirect tracking
├── BounceHandler.php           # Webhook handler for bounce/complaint events
├── UnsubscribeService.php
├── SpamChecker.php             # SpamAssassin API call
├── TemplateRenderer.php        # Liquid/Twig template rendering with tokens
└── views/
    ├── campaign_builder.tpl    # React-based drag-and-drop editor
    ├── sequence_builder.tpl    # Visual timeline builder
    └── analytics.tpl

database/migrations/
├── email_campaigns.sql
├── email_sequences.sql
├── email_sequence_steps.sql
├── email_sends.sql             # One row per contact per send (open/click tracking)
└── email_unsubscribes.sql

Recommended sending infrastructure:
- Amazon SES: $0.10 per 1,000 emails (cheapest at scale)
- Mailgun: better deliverability tools, free tier available
- Fallback: SMTP relay (Postfix on the same Docker host)
```

**Sequence Engine — Cron Logic:**
```
Every 5 minutes:
1. SELECT all active sequence enrollments where next_step_at <= NOW()
2. For each enrollment:
   a. Fetch the next step definition
   b. Evaluate conditions (if any)
   c. Execute action: send email / add tag / notify agent
   d. Calculate next_step_at based on delay + contact timezone
   e. Mark step as completed
```

**Tracking Pixel:**
```
Open tracking:  <img src="https://app.nexsaas.com/track/open/{send_id}" width="1" height="1">
Click tracking: All links rewritten to https://app.nexsaas.com/track/click/{send_id}/{link_hash}
                → logs the click → redirects to original URL
```

### Tasks

- [ ] Design and create all email marketing database tables with proper indexes.
- [ ] Build drag-and-drop email editor in React (use `react-email` or `mjml` as the rendering engine).
- [ ] Build 10 starter templates using MJML for reliable cross-client rendering.
- [ ] Build personalization token system with preview rendering.
- [ ] Build campaign send flow: audience selection → preview → schedule → send.
- [ ] Integrate Amazon SES or Mailgun as the sending provider via the `.env` config.
- [ ] Build open tracking pixel endpoint and click redirect endpoint.
- [ ] Build `SequenceEngine.php` cron processor (runs every 5 minutes).
- [ ] Build visual sequence builder in React with drag-and-drop steps.
- [ ] Build condition branching logic (if/else on open, click, reply, tag).
- [ ] Build bounce and complaint webhook handler per provider.
- [ ] Build one-click unsubscribe endpoint and suppression list.
- [ ] Build SpamAssassin check before any campaign send.
- [ ] Build A/B test framework: split audience, track winner, auto-promote.
- [ ] Build per-campaign analytics dashboard with open rate, click map, revenue attribution.
- [ ] Write end-to-end test: enroll contact in sequence → verify all 3 steps fire in order.

**Estimated time:** 12 days.

---

## F2 — Advanced Reporting & Analytics

### The Story

The current CRM shows data — leads exist, deals exist, activities are logged. But it does not answer the questions that management actually asks: "Which sales rep is closing the most deals this quarter? Which lead source has the highest conversion rate? If the current pipeline converts at our historical rate, what is our expected revenue next month?"

Advanced reporting turns raw CRM data into decisions. It is the feature that justifies the subscription to a CFO or VP of Sales, not just to the individual agent using the system every day.

### Requirements

**Standard Report Library (pre-built, available on day one):**
- Sales Performance by Rep: deals won/lost, revenue, average deal size, cycle length per agent.
- Pipeline Velocity: average time in each pipeline stage, where deals stall most.
- Lead Source ROI: conversion rate and deal value by acquisition source.
- Activity Report: calls logged, emails sent, meetings held per rep per period.
- Revenue Forecast: projected revenue based on pipeline stages × historical close rates.
- Churn Risk Report: contacts flagged by the AI engine as churn_risk in the last 30 days.

**Custom Report Builder:**
- Choose any combination of fields across Leads, Contacts, Deals, Activities, and Emails.
- Group by: rep, team, date range, pipeline stage, lead source, industry, plan tier.
- Aggregations: count, sum, average, min, max, percentage of total.
- Filter builder: add any number of AND/OR conditions on any field.
- Save custom reports and pin them to the dashboard.

**Dashboard Builder:**
- Drag-and-drop dashboard canvas.
- Widget types: number card (single KPI), bar chart, line chart, pie chart, data table, funnel chart.
- Up to 12 widgets per dashboard.
- Date range picker applied globally to the whole dashboard.
- Share dashboard via a read-only public link (for sharing with stakeholders who don't have a login).

**Scheduled Reports:**
- Send any saved report to any email address on a daily, weekly, or monthly schedule.
- Export formats: PDF snapshot and CSV raw data.

### Technical Design

```
modular_core/modules/Analytics/
├── ReportController.php
├── QueryBuilder.php            # Dynamic SQL builder from report config JSON
├── ReportRenderer.php          # Generates PDF and CSV exports
├── ScheduledReportService.php  # Cron-driven scheduled delivery
├── ForecastEngine.php          # Revenue forecast calculation
└── views/
    ├── report_library.tpl
    ├── report_builder.tpl      # Custom report builder UI
    ├── dashboard_builder.tpl   # Drag-and-drop dashboard canvas
    └── report_view.tpl

Frontend:
- Recharts for all chart types
- React DnD for dashboard builder drag-and-drop
- react-pdf for client-side PDF preview

Caching strategy:
- Heavy reports cached in Redis for 15 minutes
- Cache key: tenant_id + report_config_hash
- Cache invalidated on any write to the relevant module tables
```

**Forecast Engine Logic:**
```
For each open deal:
  expected_value = deal_value × stage_close_probability
  expected_close_date = deal_created_at + avg_cycle_length_for_stage

Revenue forecast = SUM(expected_value) grouped by expected_close_date week
```

**Report Config JSON Schema:**
```json
{
  "entity": "deals",
  "fields": ["owner_name", "deal_value", "stage", "close_date"],
  "group_by": "owner_name",
  "aggregations": { "deal_value": "sum", "id": "count" },
  "filters": [
    { "field": "stage", "operator": "not_eq", "value": "lost" },
    { "field": "close_date", "operator": "between", "value": ["2026-01-01", "2026-03-31"] }
  ],
  "sort": { "field": "deal_value_sum", "direction": "desc" }
}
```

### Tasks

- [ ] Build `QueryBuilder.php`: translates report config JSON into safe parameterized SQL.
- [ ] Build 6 standard pre-built reports with hardcoded optimized queries.
- [ ] Build custom report builder UI in React with field picker, group-by, filter, and aggregation controls.
- [ ] Build `ForecastEngine.php` with stage probability table configurable per tenant.
- [ ] Build drag-and-drop dashboard canvas in React with 6 widget types.
- [ ] Build widget configuration panel: pick report, choose chart type, set title.
- [ ] Build global date range picker that re-queries all widgets simultaneously.
- [ ] Build Redis caching layer for heavy report queries.
- [ ] Build PDF export using `dompdf` or `wkhtmltopdf`.
- [ ] Build CSV export with proper UTF-8 BOM for Arabic character support in Excel.
- [ ] Build `ScheduledReportService.php` cron job and email delivery.
- [ ] Build read-only shareable dashboard link with a token-based access URL.
- [ ] Write performance test: run the 6 standard reports on a dataset of 100,000 leads and verify sub-3-second response.

**Estimated time:** 10 days.

---

## F3 — Customer Portal

### The Story

Right now, the CRM is entirely internal — agents use it, customers never see it. But in many industries (consulting, agencies, real estate, software development), clients want visibility into their own account: see the status of their deals, download their invoices, submit support requests, and communicate with their account manager — all without sending an email and waiting.

The Customer Portal gives each client their own private, branded login where they can see exactly what the business wants them to see. It reduces support volume, increases trust, and makes the business look professional.

### Requirements

**Client Access:**
- Unique login per client contact (email + password, no account sharing).
- Password reset via email link.
- Session tied to a specific tenant — a client of Company A can never access Company B.
- Two-factor authentication option for sensitive industries.

**Portal Sections (configurable per tenant — enable/disable each section):**
- **My Deals:** see all open and closed deals related to their account, current stage, expected close date.
- **Documents:** download files shared by the agent (proposals, contracts, reports). Agent uploads from CRM side.
- **Invoices:** see all invoices, status (paid/unpaid), and download PDF.
- **Support Tickets:** submit new tickets, see status of existing ones, receive replies in-portal.
- **Messages:** direct messaging thread with their assigned account manager.
- **Account Profile:** edit their own contact details and preferences.

**Agent-Side Controls:**
- Toggle which portal sections are visible to each client.
- Share specific documents with specific clients from within the CRM deal view.
- Receive a notification when a client logs into the portal or submits a ticket.
- See a log of all client portal activity per contact.

**Branding:**
- Portal URL: `portal.{tenantsubdomain}.nexsaas.com` or custom domain (`portal.clientcompany.com`).
- Tenant can set their own logo, primary color, and welcome message.
- No NexSaaS branding visible to the end client.

### Technical Design

```
modular_core/modules/CustomerPortal/
├── PortalAuthController.php       # Separate auth from main CRM auth
├── PortalDashboardController.php
├── PortalDocumentController.php
├── PortalInvoiceController.php
├── PortalTicketController.php
├── PortalMessageController.php
├── PortalSettingsController.php   # Agent-side config
├── PortalAccessLog.php
└── views/portal/                  # Separate theme from main CRM
    ├── login.tpl
    ├── dashboard.tpl
    ├── deals.tpl
    ├── documents.tpl
    ├── invoices.tpl
    ├── tickets.tpl
    └── messages.tpl

database/migrations/
├── portal_users.sql               # Separate from CRM users table
├── portal_documents.sql           # Shared document records
├── portal_tickets.sql
├── portal_messages.sql
└── portal_access_logs.sql

Routing strategy:
- Subdomain: portal.{tenant}.nexsaas.com → PortalRouter
- Custom domain: CNAME → NexSaaS load balancer → resolve tenant from domain mapping table
```

### Tasks

- [ ] Create separate `portal_users` table with bcrypt passwords, independent of CRM users.
- [ ] Build `PortalAuthController.php` with login, logout, and password reset flows.
- [ ] Build portal routing: subdomain detection → load correct tenant theme and sections.
- [ ] Build portal dashboard home with summary cards (open deals, unread messages, unpaid invoices).
- [ ] Build Deals section: read-only view of deals the client is associated with.
- [ ] Build Documents section: file list with download links (files stored in tenant S3/storage).
- [ ] Build Invoices section: list with status badges and PDF download.
- [ ] Build Support Tickets section: submission form + conversation thread per ticket.
- [ ] Build Messages section: simple threaded messaging with the account manager.
- [ ] Build agent-side portal settings panel: toggle sections, manage client access.
- [ ] Build document sharing flow from within the CRM deal view.
- [ ] Build portal branding settings: logo upload, primary color picker, welcome text.
- [ ] Build custom domain mapping: store domain → tenant_id, resolve at the nginx level.
- [ ] Build portal activity log visible to agents in the CRM contact view.
- [ ] Write access control test: verify a client can only see their own data across all sections.

**Estimated time:** 8 days.

---

## F4 — Mobile App (iOS & Android)

### The Story

Sales happens everywhere except at a desk. A rep leaving a client meeting needs to log a note while the details are fresh. A manager traveling needs to see where the pipeline stands before a board call. A support agent on the move needs to respond to an urgent WhatsApp message without opening a laptop.

The mobile app is not a second product — it is the CRM in your pocket. It covers the core workflows that matter most when away from a desk: checking the pipeline, logging activities, reading and responding to messages, and seeing AI scores on the go.

### Requirements

**Authentication:**
- Login with email/password, biometric unlock (Face ID, fingerprint) after first login.
- Remember session for 30 days with a secure refresh token.
- Instant logout option from settings.

**Core Screens:**
- **Dashboard:** today's KPIs — new leads, open deals, tasks due today, AI alerts.
- **Leads & Contacts:** searchable list with filters; tap to open full profile.
- **Deal Pipeline:** Kanban-style board; drag a deal card between stages.
- **Inbox:** unified message feed (WhatsApp + Email); tap to open and reply.
- **Activity Log:** log a call, note, email, or meeting in under 10 seconds.
- **Notifications:** push notifications for new messages, AI alerts, task reminders.
- **AI Insights:** see lead score, intent flag, and AI-suggested next action per contact.

**Offline Support:**
- Read access to the last-synced data when offline.
- Queue write actions (log activity, update deal stage) and sync when connection returns.

**Platform:**
- React Native (single codebase for iOS and Android).
- iOS minimum: iOS 15. Android minimum: Android 10.
- Published on App Store and Google Play.

### Technical Design

```
mobile_app/
├── src/
│   ├── api/
│   │   ├── client.ts           # Axios instance with auth headers
│   │   ├── leads.ts
│   │   ├── deals.ts
│   │   ├── inbox.ts
│   │   └── activities.ts
│   ├── screens/
│   │   ├── Dashboard/
│   │   ├── Leads/
│   │   ├── Deals/              # Kanban board
│   │   ├── Inbox/
│   │   ├── ActivityLog/
│   │   └── Settings/
│   ├── components/             # Reusable: cards, badges, avatar, skeleton
│   ├── store/                  # Zustand or Redux Toolkit
│   ├── hooks/                  # useLeads, useDeals, useInbox
│   ├── navigation/             # React Navigation stack + bottom tabs
│   └── utils/
│       ├── offlineQueue.ts     # Persist writes to AsyncStorage when offline
│       └── pushNotifications.ts
├── ios/
├── android/
└── app.config.ts               # Expo config (if using Expo)

Backend additions needed:
- Push notification endpoint: POST /api/mobile/push-token
- Mobile-optimized API responses (fewer fields, paginated)
- Background sync endpoint for offline queue flushing
```

**Offline Queue Design:**
```typescript
// When offline, writes are stored in AsyncStorage
offlineQueue = [
  { action: "log_activity", payload: { lead_id, type: "call", note }, timestamp },
  { action: "update_deal_stage", payload: { deal_id, stage: "negotiation" }, timestamp }
]

// On reconnect, flush the queue in order
onNetworkReconnect() {
  for (const item of offlineQueue) {
    await api.post(`/api/${item.action}`, item.payload)
  }
  clearOfflineQueue()
}
```

### Tasks

- [ ] Initialize React Native project with Expo (managed workflow for faster build pipeline).
- [ ] Set up React Navigation with bottom tab navigator (Dashboard, Leads, Deals, Inbox, More).
- [ ] Build API client layer with JWT auth, refresh token rotation, and request interceptors.
- [ ] Build Dashboard screen with today's KPI cards fetched from the existing API.
- [ ] Build Leads screen with search, filter chips, and infinite scroll pagination.
- [ ] Build Lead Profile screen with all sections: score, contact info, deal history, activities.
- [ ] Build Deals screen as a horizontal Kanban board with drag-and-drop stage updates.
- [ ] Build Inbox screen: unified feed with WhatsApp and Email messages.
- [ ] Build Activity Log bottom sheet: log call/note/meeting in 3 taps.
- [ ] Set up Firebase Cloud Messaging (FCM) for push notifications on both platforms.
- [ ] Build push notification handler: deep link directly to the relevant lead or message.
- [ ] Build offline queue using AsyncStorage with network state listener.
- [ ] Build biometric authentication using `expo-local-authentication`.
- [ ] Build AI Insights screen: lead score gauge, intent badge, suggested next action card.
- [ ] Add mobile-specific API endpoints with lightweight response payloads.
- [ ] Submit to App Store and Google Play (handle review process, privacy labels, etc.).

**Estimated time:** 20 days.

---
---

# Integrations

---

## I1 — Slack & Microsoft Teams

### The Story

Sales teams and support teams already live inside Slack or Microsoft Teams. Asking them to switch context to the CRM for every notification is friction that gets ignored. The integration brings CRM events into the channels where the team already works — and lets them take quick actions without ever leaving their chat tool.

### Requirements

**Notifications Sent to Slack/Teams:**
- New lead assigned to you.
- Lead score crosses a threshold (e.g., score jumps above 80).
- AI detects `buying_intent` on a contact.
- A deal stage is updated.
- A new support ticket is submitted via the Customer Portal.
- Payment received or payment failed (for the billing admin).

**Interactive Actions (Slack only — Block Kit):**
- Reply to a notification with a quick note logged directly to the CRM.
- Change a deal stage from the Slack message using a dropdown.
- Assign a lead to a team member from the notification card.

**Configuration (per tenant):**
- Choose which events trigger notifications.
- Choose which Slack channel or Teams channel each event type goes to.
- Scope notifications: all events, or only events where the user is the assigned agent.

### Technical Design

```
modular_core/modules/Integrations/Slack/
├── SlackService.php            # Send messages via Incoming Webhooks or Bot Token
├── SlackEventHandler.php       # Handle interactive action callbacks
├── SlackOAuth.php              # Slack App OAuth install flow
└── views/slack_settings.tpl

modular_core/modules/Integrations/Teams/
├── TeamsService.php            # Send Adaptive Cards via Power Automate webhook
└── views/teams_settings.tpl

Integration trigger points (hooked into existing event system):
- LeadCreated → notify
- LeadScoreUpdated → notify if threshold crossed
- DealStageChanged → notify
- TicketCreated → notify
- PaymentEvent → notify billing admin channel
```

**Slack Block Kit Message Example:**
```json
{
  "blocks": [
    {
      "type": "section",
      "text": { "type": "mrkdwn", "text": "*New High-Intent Lead* — Ahmed Hassan (Acme Corp)\nAI Score: *87/100* — Buying Intent Detected" }
    },
    {
      "type": "actions",
      "elements": [
        { "type": "button", "text": { "type": "plain_text", "text": "View Lead" }, "url": "https://app.nexsaas.com/leads/123" },
        { "type": "button", "text": { "type": "plain_text", "text": "Log a Note" }, "action_id": "log_note_123" }
      ]
    }
  ]
}
```

### Tasks

- [ ] Create a Slack App in the Slack API dashboard with the required Bot Token scopes.
- [ ] Build `SlackOAuth.php`: install flow, store access token per tenant, handle revocation.
- [ ] Build `SlackService.php`: send messages using the Slack Web API `chat.postMessage`.
- [ ] Build notification formatter: map each CRM event type to a Block Kit message template.
- [ ] Build `SlackEventHandler.php`: handle interactive action payloads (note logging, stage change).
- [ ] Build Slack settings UI: channel picker per event type, scope toggle.
- [ ] Build Microsoft Teams connector via Incoming Webhook (simpler than Bot — no OAuth needed).
- [ ] Build Adaptive Card templates for Teams notifications.
- [ ] Build Teams settings UI: webhook URL input per event type.
- [ ] Write test: trigger each event type, verify the correct message appears in the correct channel.

**Estimated time:** 5 days.

---

## I2 — Zapier & Make (No-code Automation)

### The Story

A significant portion of small business customers will never use the CRM API directly. But they absolutely use Zapier or Make to connect their tools. The moment NexSaaS has a Zapier integration, it can connect to 6,000+ other apps — Typeform, Calendly, Shopify, Google Sheets, Mailchimp — without writing a single custom integration for each. This dramatically expands the addressable market.

### Requirements

**Zapier Triggers (events NexSaaS sends to Zapier):**
- Lead Created
- Lead Updated
- Deal Stage Changed
- Deal Won
- Deal Lost
- New Inbound Message (WhatsApp or Email)
- Invoice Paid

**Zapier Actions (things Zapier can do inside NexSaaS):**
- Create Lead
- Update Lead Fields
- Create Deal
- Log Activity (call, note, meeting)
- Add Tag to Contact
- Send WhatsApp Message via NexSaaS
- Create Support Ticket

**Make (Integromat) — same triggers and actions exposed via REST webhooks.**

### Technical Design

```
modular_core/modules/Integrations/Zapier/
├── ZapierWebhookController.php  # Trigger delivery via subscription webhooks
├── ZapierActionController.php   # REST endpoints for Zapier actions
├── ZapierSubscription.php       # Store/manage Zapier webhook subscriptions per tenant
└── views/zapier_settings.tpl

Zapier integration type: Zapier Developer Platform (polling + REST hooks)

Trigger delivery pattern (REST Hooks):
  1. Zapier sends POST /api/zapier/subscribe with {event, target_url}
  2. NexSaaS stores the subscription
  3. On event, NexSaaS POSTs payload to target_url
  4. On unsubscribe, Zapier sends DELETE /api/zapier/subscribe/{id}

Action endpoints (standard REST):
  POST /api/zapier/leads           → create lead
  PATCH /api/zapier/leads/{id}     → update lead
  POST /api/zapier/activities      → log activity
  POST /api/zapier/whatsapp/send   → send WhatsApp message
```

**Zapier App Configuration:**
```
Authentication: API Key (per tenant, generated in Settings > Integrations)
Base URL: https://app.nexsaas.com/api/zapier
```

### Tasks

- [ ] Generate API key per tenant from the Integrations settings page.
- [ ] Build `ZapierSubscription.php`: store webhook subscriptions in the database.
- [ ] Build subscription management endpoints: POST and DELETE `/api/zapier/subscribe`.
- [ ] Hook all 7 trigger events into the subscription delivery system.
- [ ] Build all 7 action endpoints with input validation and proper error responses.
- [ ] Register the integration on the Zapier Developer Platform.
- [ ] Write and pass all Zapier review requirements (test Zap for each trigger and action).
- [ ] Build Make (Integromat) connector via the same REST webhook architecture.
- [ ] Add Zapier/Make settings page in the CRM with API key generation and connected Zaps list.
- [ ] Write integration tests for all trigger and action endpoints using Zapier's test runner.

**Estimated time:** 7 days.

---

## I3 — Google Workspace

### The Story

Most companies run on Gmail and Google Calendar. A CRM that forces a sales rep to copy-paste emails into an activity log, or manually add meetings from Google Calendar, will be abandoned within two weeks. The Google Workspace integration makes the CRM invisible — work happens in Gmail, and the data appears in NexSaaS automatically.

### Requirements

**Gmail Integration:**
- Two-way sync: emails sent and received in Gmail appear as activities in the CRM contact timeline.
- Compose CRM emails from inside Gmail using a Gmail sidebar extension (Google Workspace Add-on).
- Auto-link: incoming emails from known contacts auto-attach to the correct lead or deal.
- BCC-to-CRM: a unique BCC address per tenant (`log@{tenant}.nexsaas.com`) auto-logs any email.

**Google Calendar Integration:**
- Two-way sync: meetings in Google Calendar appear as CRM activities on the relevant contact.
- Create a CRM meeting from the deal or contact view → it appears in Google Calendar automatically.
- Meeting prep: 30 minutes before a calendar event with a CRM contact, send the agent a summary: contact's AI score, last 5 interactions, and open deals.

**Google Drive Integration:**
- Attach Google Drive files to CRM deals and contacts by pasting a link.
- Generate a shared Google Drive folder per deal automatically.
- Files shared in the Customer Portal can be stored in a Google Drive folder owned by the tenant.

### Technical Design

```
modular_core/modules/Integrations/Google/
├── GoogleOAuth.php             # OAuth2 flow, token storage and refresh
├── GmailSyncService.php        # Gmail API: fetch/send messages
├── CalendarSyncService.php     # Google Calendar API: create/read events
├── DriveService.php            # Google Drive API: folder creation, file links
├── GmailWatchService.php       # Gmail Push Notifications (webhook on new emails)
└── views/google_settings.tpl

Cron jobs:
- Every 10 min: GmailSyncService → pull new emails, match to contacts
- Every 5 min:  CalendarSyncService → pull new/updated events, match to contacts
- 30 min before event: MeetingPrepService → generate and email briefing to agent

BCC-to-CRM:
- Inbound SMTP address: log@{tenant}.nexsaas.com
- Postfix routes to PHP processor
- Processor: parse email → match sender/recipient to CRM contact → log as activity
```

### Tasks

- [ ] Create Google Cloud Project with Gmail API, Calendar API, and Drive API enabled.
- [ ] Build `GoogleOAuth.php`: authorization flow, token storage per tenant, refresh logic.
- [ ] Build `GmailSyncService.php`: pull new emails, parse sender/recipient, match to CRM contacts.
- [ ] Build Gmail Push Notifications (Gmail Watch API): receive instant webhook on new emails.
- [ ] Build BCC-to-CRM inbound email processor via Postfix routing.
- [ ] Build `CalendarSyncService.php`: two-way sync of events with CRM activities.
- [ ] Build meeting creation from CRM deal/contact view → writes to Google Calendar.
- [ ] Build `MeetingPrepService.php`: 30-minute pre-meeting briefing email with AI summary.
- [ ] Build `DriveService.php`: auto-create deal folder, attach Drive links to deals.
- [ ] Build Google Workspace Add-on (Gmail sidebar) for composing and logging from Gmail.
- [ ] Build Google integration settings page with scopes shown and per-feature toggles.
- [ ] Write test: send an email to a known CRM contact and verify it appears in the contact timeline within 60 seconds.

**Estimated time:** 8 days.

---

## I4 — Payment Gateways (PayPal, Paymob, Fawry)

### The Story

Stripe is the right choice for North America and Europe. But in Egypt, Saudi Arabia, and across MENA, customers pay via Paymob, Fawry, and local bank transfers — not credit cards on Stripe. If NexSaaS only accepts Stripe payments, it cannot sell to the Egyptian market at all. Adding Paymob and Fawry is not a nice-to-have — it is a market access requirement for the region.

### Requirements

**PayPal:**
- Accept PayPal as an alternative to Stripe credit card checkout.
- PayPal subscriptions for recurring billing.
- Webhook handler for PayPal IPN events (payment completed, subscription canceled).

**Paymob (Egypt, KSA, UAE, Pakistan):**
- Accept local card payments, mobile wallets (Vodafone Cash, Fawry, Orange Money), and installments.
- Hosted payment page redirect (no PCI burden on NexSaaS servers).
- Webhook handler for payment confirmation events.
- Recurring billing via Paymob token-based payments.

**Fawry (Egypt):**
- Generate a Fawry reference code the customer takes to any Fawry point (store, ATM, mobile app).
- Mark the invoice as paid automatically when Fawry sends the confirmation callback.
- Expiry: Fawry codes expire after 24 or 48 hours; send a reminder before expiry.

**Gateway Abstraction:**
- The billing system should not know or care which gateway processed a payment.
- A single `PaymentGateway` interface that all three gateways implement.
- Tenants configure which gateways are active in their billing settings.

### Technical Design

```
modular_core/modules/Billing/Gateways/
├── PaymentGatewayInterface.php   # createCharge(), createSubscription(), cancelSubscription()
├── StripeGateway.php             # existing
├── PayPalGateway.php
├── PaymobGateway.php
├── FawryGateway.php
└── GatewayFactory.php            # returns the correct gateway based on tenant config

modular_core/modules/Billing/
├── WebhookRouter.php             # routes incoming webhook to the correct gateway handler
└── views/
    └── checkout.tpl              # updated to show available gateways as payment options

database/migrations/
└── payment_gateways.sql          # gateway_name, tenant_id, config_json, is_active
```

**Checkout Flow with Multiple Gateways:**
```
Customer reaches checkout →
  System reads tenant's active gateways →
  Display payment method selector:
    ○ Credit/Debit Card (Stripe)
    ○ PayPal
    ○ Paymob (card, wallet, installments)
    ○ Fawry (pay at any Fawry outlet)
  Customer selects → gateway-specific flow starts →
  On success → provision tenant → send invoice
```

### Tasks

- [ ] Define `PaymentGatewayInterface.php` with all required method signatures.
- [ ] Refactor existing Stripe code into `StripeGateway.php` implementing the interface.
- [ ] Build `PayPalGateway.php` using PayPal Orders API and Subscriptions API.
- [ ] Build `PayPalWebhookHandler.php` for IPN event processing.
- [ ] Build `PaymobGateway.php` using Paymob's payment API (intention → iframe token → redirect).
- [ ] Build `PaymobWebhookHandler.php` for HMAC-verified payment callbacks.
- [ ] Build `FawryGateway.php`: generate reference code, handle expiry logic.
- [ ] Build `FawryWebhookHandler.php` for payment confirmation callbacks.
- [ ] Update checkout UI to show available gateway options based on tenant config.
- [ ] Build `GatewayFactory.php` to resolve the correct gateway at runtime.
- [ ] Build `WebhookRouter.php` to direct incoming webhooks to the correct handler.
- [ ] Build gateway settings page: enter credentials per gateway, toggle active/inactive.
- [ ] Write end-to-end tests for each gateway using sandbox/test credentials.

**Estimated time:** 6 days.

---
---

# SaaS Business Features

---

## S1 — Affiliate & Referral Program

### The Story

The most cost-effective customer acquisition channel for a SaaS product is word-of-mouth made systematic. An affiliate program turns every happy customer into a salesperson. A referral program gives existing users a reason to invite their peers. Combined, they can generate 20–30% of new revenue at a fraction of the cost of paid advertising.

### Requirements

**Referral Program (for existing customers):**
- Every tenant gets a unique referral link: `nexsaas.com/r/{code}`.
- When a referred company signs up and completes their first payment, the referrer receives a reward.
- Reward options (configurable by NexSaaS admin): account credit, cash payout via PayPal, or a free month added.
- Referral dashboard visible to the tenant: total referrals, pending conversions, rewards earned.

**Affiliate Program (for external partners — agencies, consultants, bloggers):**
- Apply to be an affiliate via a dedicated signup page.
- Approved affiliates get an affiliate link and access to a marketing kit (banners, copy, product screenshots).
- Commission: configurable percentage of each referred subscription's monthly revenue, paid monthly.
- Affiliate dashboard: clicks, signups, conversions, commission earned, payout history.
- Payout via PayPal or bank transfer, minimum payout threshold configurable.

**Admin Controls (NexSaaS super-admin):**
- Approve or reject affiliate applications.
- Set commission rates globally or per-affiliate.
- View full program analytics: total referrals, conversion rate, total commission paid.
- Manually adjust rewards or commissions if disputes arise.

### Technical Design

```
modular_core/modules/Affiliate/
├── AffiliateController.php
├── ReferralController.php
├── CommissionService.php        # Calculates and records commissions on payment events
├── PayoutService.php            # Triggers PayPal mass payouts
├── AffiliateTracker.php         # Reads referral cookie, attributes signup to affiliate
└── views/
    ├── affiliate_portal.tpl     # Affiliate-facing dashboard
    ├── referral_dashboard.tpl   # Tenant-facing referral page
    └── admin_affiliates.tpl     # Super-admin program management

database/migrations/
├── affiliates.sql               # affiliate accounts, status, commission_rate
├── referral_codes.sql           # code, tenant_id (referrer), type (tenant|affiliate)
├── referral_clicks.sql          # click tracking with IP and user agent
├── referral_conversions.sql     # signup_tenant_id, referrer_id, attributed_at
└── commissions.sql              # amount, status (pending|paid), payout_batch_id

Attribution window: 90 days (cookie + server-side tracking)
```

### Tasks

- [ ] Build referral code generation and assignment on tenant signup.
- [ ] Build affiliate application page with form validation and auto-email confirmation.
- [ ] Build affiliate approval workflow in the super-admin panel.
- [ ] Build `AffiliateTracker.php`: set cookie on click, attribute signup to referral code.
- [ ] Build server-side fallback attribution using the referral code stored in the signup session.
- [ ] Build `CommissionService.php`: on `invoice.payment_succeeded`, calculate and record commission.
- [ ] Build affiliate dashboard: clicks funnel, conversion stats, earnings summary, payout history.
- [ ] Build tenant referral dashboard: referrals sent, conversions, credits earned.
- [ ] Build `PayoutService.php`: generate monthly payout batch via PayPal Payouts API.
- [ ] Build admin panel: affiliate list, application queue, commission rate editor.
- [ ] Write test: simulate full referral flow from click → signup → payment → commission record.

**Estimated time:** 7 days.

---

## S2 — White-label & Reseller Mode

### The Story

Agencies and consultants who implement CRM systems for their clients do not want to hand the client a product that says "NexSaaS" on it. They want to hand them "PowerCRM by ABC Agency" or "SalesPro by XYZ Consulting." White-labeling turns NexSaaS into a platform that other businesses can resell under their own brand, dramatically expanding the distribution network without any additional sales effort from the NexSaaS team.

### Requirements

**White-label Customization (per reseller):**
- Custom product name: appears in the browser tab, emails, and PDF documents.
- Custom logo: replaces NexSaaS logo everywhere in the UI.
- Custom primary and accent colors: applied across the entire UI.
- Custom domain: reseller's clients access the product at `crm.resellerdomain.com`.
- Custom favicon.
- Remove all NexSaaS branding from emails, login pages, and PDF footers.
- Custom "powered by" text option: "Powered by ABC Agency" or nothing at all.

**Reseller Account Management:**
- Resellers have a super-tenant account that can create and manage client tenants.
- Reseller sets the price for their clients independently of NexSaaS pricing.
- Reseller billing: NexSaaS charges the reseller at wholesale price; reseller charges their client at retail.
- Reseller dashboard: list of all client tenants, status, plan, and usage.
- Reseller can impersonate any of their client tenants for support purposes (with a clear "impersonating" banner visible).

**Limits:**
- Reseller plan caps the maximum number of client tenants they can create.
- Reseller plan includes a white-label feature flag — standard tenant plans do not have white-label access.

### Technical Design

```
modular_core/modules/WhiteLabel/
├── WhiteLabelConfig.php         # Loads brand config for the current domain/tenant
├── ThemeRenderer.php            # Injects CSS variables from brand config into page
├── ResellerController.php       # Reseller super-tenant management
├── ImpersonationService.php     # Secure tenant impersonation with audit log
└── views/
    ├── brand_settings.tpl       # Reseller brand configuration page
    └── reseller_dashboard.tpl   # Client tenant management

database/migrations/
├── white_label_configs.sql      # tenant_id, product_name, logo_url, colors, domain
└── reseller_relationships.sql   # reseller_tenant_id, client_tenant_id

CSS variable injection approach:
  On every page load:
  1. Resolve current tenant from subdomain or custom domain
  2. Load white_label_config for that tenant
  3. Inject as <style> block:
     :root {
       --brand-primary: #1A73E8;
       --brand-logo: url('/storage/tenants/123/logo.png');
       --brand-name: "PowerCRM";
     }

Email branding:
  All transactional emails use the brand name and logo from the config
  "From" name: configured brand name
  Footer: configured support email and optional "powered by" text
```

### Tasks

- [ ] Build `WhiteLabelConfig.php`: load brand config by tenant ID with Redis caching.
- [ ] Build CSS variable injection system applied on every page load for the correct tenant.
- [ ] Build brand settings page: logo upload, color pickers, product name, favicon upload.
- [ ] Build custom domain resolution: CNAME → NexSaaS IP → resolve tenant from domain mapping.
- [ ] Update all transactional email templates to read brand name, logo, and colors from config.
- [ ] Update all PDF generation (invoices, reports) to use brand config.
- [ ] Build Reseller account type: a tenant that can create and manage sub-tenants.
- [ ] Build Reseller dashboard: client tenant list with status, plan, and usage summary.
- [ ] Build tenant creation flow for resellers: create a client tenant from within the reseller dashboard.
- [ ] Build `ImpersonationService.php`: reseller can log in as a client tenant with a visible banner and full audit log.
- [ ] Build reseller billing: NexSaaS bills the reseller, reseller bills clients independently.
- [ ] Write test: verify NexSaaS brand elements are completely absent on a white-labeled tenant's domain.

**Estimated time:** 10 days.

---

## S3 — Agency Multi-client Dashboard

### The Story

A digital agency or consulting firm manages CRM instances for multiple clients simultaneously. Right now, if they use NexSaaS, they need to log in and out of each client's account separately. The Agency Dashboard gives them a single command center where they can monitor all their clients at a glance — which clients need attention, which deals are moving, which accounts are healthy — without switching sessions.

### Requirements

**Agency Overview Dashboard:**
- One login → see all managed client tenants in a single view.
- Per-client summary card: company name, plan, number of leads, open deals value, last activity date, health score.
- Health score: a calculated indicator of how actively the client is using the CRM (logins, leads created, deals updated, emails sent in the last 30 days).
- Filter and search across all clients.
- Sort by health score, deal value, last activity, or plan tier.

**Cross-client Reporting:**
- Aggregate report: total pipeline value across all managed clients.
- Comparative report: side-by-side performance of two or more clients.
- Export combined report as a PDF for client meetings.

**One-click Client Access:**
- Click any client card to switch into that tenant's CRM without logging out.
- Persistent "Back to Agency Dashboard" button visible when operating inside a client account.
- Activity inside a client account logged under the agency user's ID for audit purposes.

**Client Onboarding from Agency Dashboard:**
- Agency can create a new client tenant from the dashboard.
- Pre-fill the client's company name, industry, and plan.
- Invite the client's primary user directly from the dashboard.

### Technical Design

```
modular_core/modules/Agency/
├── AgencyController.php
├── AgencySessionService.php     # Handles cross-tenant session switching
├── ClientHealthScorer.php       # Calculates health score per client tenant
├── AggregatReportService.php    # Cross-client reporting queries
└── views/
    ├── agency_dashboard.tpl     # Main multi-client overview
    ├── client_card.tpl          # Individual client summary card component
    └── cross_client_report.tpl

Agency session switching:
  1. Agency user clicks client card
  2. Server validates agency_user has access to that client_tenant_id
  3. Creates a short-lived cross-tenant session token (15 min, stored in Redis)
  4. Redirects to client tenant's CRM with the session token
  5. Client CRM validates the token → grants access → shows "Return to Agency" banner
  6. All writes in client session tagged with agency_user_id in audit log

Health Score formula:
  score = (
    logins_last_30d         × 10  (max 30 pts)
    + leads_created_last_30d × 5  (max 25 pts)
    + deals_updated_last_30d × 5  (max 25 pts)
    + emails_sent_last_30d   × 4  (max 20 pts)
  )
  Bands: 0-30 = At Risk, 31-60 = Needs Attention, 61-85 = Healthy, 86-100 = Excellent
```

### Tasks

- [ ] Build agency account type with many-to-many relationship to managed client tenants.
- [ ] Build Agency Overview Dashboard UI with client cards, filters, and sort controls.
- [ ] Build `ClientHealthScorer.php`: calculate and cache health score per client weekly.
- [ ] Build health score display: color-coded badge on each client card.
- [ ] Build cross-tenant session switching: secure token flow with Redis TTL.
- [ ] Build "Return to Agency Dashboard" persistent banner inside client CRM sessions.
- [ ] Build cross-client aggregate pipeline report.
- [ ] Build side-by-side client comparison report.
- [ ] Build combined PDF export for multi-client reports.
- [ ] Build new client tenant creation flow from the agency dashboard.
- [ ] Build client user invitation flow from the agency dashboard.
- [ ] Write test: switch into client A, perform an action, verify audit log shows agency_user_id.

**Estimated time:** 8 days.

---

## S4 — AI Chatbot for Customer Support

### The Story

Every SaaS product has the same support problem: the same 20 questions get asked by 80% of customers. "How do I import my leads?" "How do I connect WhatsApp?" "Why did my payment fail?" A support agent answering these individually is an expensive way to deliver information that is already in the documentation.

An AI chatbot handles these repetitive questions instantly, 24/7, in any language. It lives inside the product, in the Customer Portal, and optionally on the landing page. When a question is too complex, it escalates to a human with full context. The result is faster support at a fraction of the cost, and happier customers who get answers immediately.

### Requirements

**Chatbot Capabilities:**
- Answer questions about NexSaaS features using the documentation as its knowledge base.
- Answer account-specific questions: "What is my current plan?", "When does my trial end?", "How many leads have I created this month?" — by querying the CRM API for the current user's data.
- Guide users through common tasks step-by-step: "How do I import leads?" → show steps with screenshots.
- Detect when a question is outside its knowledge and escalate to a human agent with full context.
- Support English and Arabic.

**Placement:**
- In-app chat bubble (bottom-right corner of the CRM, visible on all pages).
- Customer Portal chat bubble (available to client contacts).
- Optional: embed widget on the landing page for pre-sales questions.

**Knowledge Base Management:**
- Admin uploads or pastes documentation articles.
- Documents are chunked, embedded, and stored in a vector database.
- Chatbot retrieves the most relevant chunks using semantic similarity search (RAG pattern).
- Admin can test any question against the knowledge base from the settings panel.

**Escalation to Human:**
- If the chatbot cannot answer with confidence (score below threshold), it offers to connect to a human.
- Escalated conversations appear in the CRM's support queue as a ticket.
- The assigned agent sees the full chatbot conversation history before responding.
- Once the agent takes over, the chat widget shows the agent's name and avatar.

**Analytics:**
- Resolution rate: percentage of conversations resolved without human escalation.
- Top unanswered questions: questions the bot failed to answer confidently (helps prioritize documentation gaps).
- Average time to first answer.
- Customer satisfaction: thumbs up/down after each resolved conversation.

### Technical Design

```
ai_engine/routers/
└── chatbot.py                  # FastAPI chatbot endpoint

ai_engine/services/
├── chatbot_service.py          # Orchestrates RAG pipeline
├── vector_store.py             # Pinecone or pgvector for embeddings storage
├── document_chunker.py         # Splits documents into chunks for embedding
├── context_loader.py           # Loads user-specific CRM data for account questions
└── escalation_detector.py      # Decides when to escalate based on confidence score

modular_core/modules/Chatbot/
├── ChatbotController.php       # PHP API proxy between frontend and AI engine
├── EscalationHandler.php       # Creates CRM ticket from escalated conversation
├── KnowledgeBaseController.php # Admin: upload, manage, and test documents
└── views/
    ├── chat_widget.tpl         # Floating chat bubble (injected on all pages)
    ├── knowledge_base.tpl      # Admin knowledge base management
    └── chatbot_analytics.tpl

RAG Pipeline:
  User sends message →
  1. Embed the message using text-embedding-ada-002
  2. Query vector store for top-5 similar document chunks
  3. Build prompt: [system role] + [retrieved chunks] + [conversation history] + [user message]
  4. If account question detected: fetch live CRM data for this user and append to context
  5. Call GPT-4o (or Claude) for the response
  6. Evaluate confidence: if below 0.7, flag for potential escalation
  7. Stream response back to the chat widget

Vector store options:
  - pgvector (PostgreSQL extension): zero extra infra, perfect for early stage
  - Pinecone: managed, scales better at high volume
```

**Confidence & Escalation Logic:**
```python
def should_escalate(response: str, retrieved_chunks: list, user_message: str) -> bool:
    # Escalate if:
    # 1. No relevant chunks found (max similarity score < 0.7)
    # 2. GPT response contains uncertainty markers ("I'm not sure", "I don't know")
    # 3. User explicitly asks for a human
    # 4. Conversation has exceeded 8 turns without resolution

    max_similarity = max(chunk.score for chunk in retrieved_chunks) if retrieved_chunks else 0
    uncertainty_markers = ["i'm not sure", "i don't know", "i cannot find", "please contact support"]
    human_request = any(phrase in user_message.lower() for phrase in ["human", "agent", "person", "support"])

    return (
        max_similarity < 0.7
        or any(marker in response.lower() for marker in uncertainty_markers)
        or human_request
    )
```

### Tasks

- [ ] Set up pgvector extension in PostgreSQL (or provision a Pinecone index).
- [ ] Build `document_chunker.py`: split uploaded docs into 500-token chunks with 50-token overlap.
- [ ] Build `vector_store.py`: embed chunks using OpenAI embeddings API and store with metadata.
- [ ] Build `chatbot_service.py`: full RAG pipeline (embed query → retrieve → build prompt → call LLM → stream response).
- [ ] Build `context_loader.py`: detect account-specific questions and fetch live CRM data.
- [ ] Build `escalation_detector.py`: confidence scoring and escalation trigger logic.
- [ ] Build FastAPI `/chatbot/message` endpoint with streaming response support.
- [ ] Build `ChatbotController.php`: PHP API proxy that adds authentication and rate limiting.
- [ ] Build chat widget UI in React: floating bubble, message thread, typing indicator, streaming text render.
- [ ] Build escalation flow: show "Connect to a human" button → create CRM ticket → notify agent.
- [ ] Build agent takeover: agent sees chat history in the CRM support queue, continues from within CRM.
- [ ] Build Knowledge Base admin page: upload documents, view chunks, test any question.
- [ ] Build chatbot analytics dashboard: resolution rate, top failures, satisfaction scores.
- [ ] Build Arabic language support: detect language, respond in the same language as the user.
- [ ] Write test: upload 5 documentation articles, ask 10 questions, verify 8+ are answered correctly without escalation.

**Estimated time:** 12 days.

---

## 📊 Full Feature Timeline

| Week | Features Being Built |
|------|---------------------|
| Week 1–2 | F1 — Email Marketing (campaigns + templates) |
| Week 3 | F1 — Email Marketing (sequences + analytics) |
| Week 4–5 | F2 — Advanced Reporting & Analytics |
| Week 6 | F3 — Customer Portal |
| Week 7–8 | I3 — Google Workspace (Gmail + Calendar) |
| Week 9 | I4 — Payment Gateways (Paymob + Fawry + PayPal) |
| Week 10 | I1 — Slack & Teams + I2 — Zapier & Make |
| Week 11 | S2 — White-label & Reseller Mode |
| Week 12 | S3 — Agency Dashboard |
| Week 13 | S1 — Affiliate & Referral Program |
| Week 14–15 | S4 — AI Chatbot (RAG pipeline + widget) |
| Week 16 | F4 — Mobile App (foundation + core screens) |
| Week 17–18 | F4 — Mobile App (offline, push, App Store submission) |

**Total: ~18 weeks / ~113 working days**

---

## ✅ Feature Readiness Checklist

### Product Features
- [ ] Email campaigns send successfully and track opens + clicks
- [ ] Email sequences trigger on the correct events and branch on conditions
- [ ] Reports return correct data verified against raw database counts
- [ ] Dashboard builder allows saving and sharing with a read-only link
- [ ] Customer Portal client login is fully isolated from the CRM user session
- [ ] Customer Portal shows zero NexSaaS branding (white-labeled)
- [ ] Mobile app passes App Store and Google Play review
- [ ] Mobile app works offline and syncs cleanly on reconnection

### Integrations
- [ ] Slack notifications fire within 5 seconds of the triggering CRM event
- [ ] Zapier triggers pass Zapier's official test suite
- [ ] Gmail emails appear in the CRM contact timeline within 60 seconds
- [ ] Calendar events sync bidirectionally without duplicates
- [ ] Paymob payment completes end-to-end in a sandbox environment
- [ ] Fawry reference code is generated and confirmed payment is received

### SaaS Business Features
- [ ] Referral attribution correctly tracks click → signup → payment
- [ ] Affiliate commissions are calculated correctly on every payment event
- [ ] White-labeled tenant shows zero NexSaaS brand elements on any page or email
- [ ] Reseller can create and switch between client tenants without logging out
- [ ] Agency health scores update weekly and reflect real usage patterns
- [ ] AI Chatbot resolves at least 75% of test questions without escalation
- [ ] Chatbot escalation creates a properly formatted CRM ticket with full conversation history

---

*Last updated: March 2026 — hazem ali galal*
