# NexSaaS CRM — AGPL & Commercial Licensing Strategy (LICENSING_STRATEGY.md)
# (Requirement 5: AGPL Licensing Strategy)

## ⚖️ Core Licensing Strategy
NexSaaS is built on a **Dual-Licensing Model**.

1. **NexSaaS Open (AGPLv3)**:
   - For open-source contributions and non-commercial local development.
   - **Obligation**: If you modify NexSaaS and provide it as a hosted service (SaaS), you MUST make your modified source code available to your users (The "SaaS Loophole" fix in AGPL). (Requirement 5.2)

2. **NexSaaS Enterprise (Commercial)**:
   - Proprietary license for commercial revenue-generating operations.
   - **Benefit**: No obligation to disclose your modified source code or proprietary integrations. (Requirement 5.3)

## 📂 Source Code Disclosure (Requirement 5.4)
- **AGPL Users**: Source code link MUST be visible in the application footer.
- **Enterprise Users**: Source code link is REMOVED from the footer.

## 🛠️ AGPL-Licensed Dependencies (Requirement 5.6)
- NexSaaS Core: AGPLv3
- Soketi Server: GPLv3 (Managed via Docker)
- pgvector: PostgreSQL Extension (GPLv2-compatible)

## 💰 Commercial License Process (Requirement 5.5)
To purchase a commercial license and remove AGPL obligations:
1. Contact sales@nexsaas.com.
2. Sign the Master License Agreement (MLA).
3. Pay the annual license fee ($5,000 / year).
4. Receive a 'Commercial License Key' to activate Enterprise mode. (Requirement 5.10)
