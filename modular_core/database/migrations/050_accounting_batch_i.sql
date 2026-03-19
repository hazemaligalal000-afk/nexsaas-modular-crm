-- Migration 050: Partner Withdrawals and Distributions (Batch I)
-- Task 37.1 (Expanded): Track withdrawals and approval states

CREATE TABLE partner_withdrawals (
    id                  BIGSERIAL PRIMARY KEY,
    tenant_id           UUID NOT NULL,
    company_code        VARCHAR(2) NOT NULL DEFAULT '01',
    partner_id          BIGINT NOT NULL REFERENCES partners(id),
    amount              DECIMAL(15, 2) NOT NULL,
    currency            VARCHAR(3) NOT NULL DEFAULT 'EGP',
    status              VARCHAR(20) DEFAULT 'pending_1st', -- pending_1st, pending_2nd, approved, posted, rejected
    requested_by        BIGINT NOT NULL,
    first_approver_id   BIGINT,
    second_approver_id  BIGINT,
    journal_entry_id    BIGINT,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE partner_withdrawals IS 'Tracks partner withdrawal requests requiring dual-approval thresholds (Req 53.3, 53.4)';
