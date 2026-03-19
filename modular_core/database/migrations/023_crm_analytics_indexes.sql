-- Migration: 023_crm_analytics_indexes.sql
-- Purpose: Add composite indexes to support pre-built CRM analytics reports
--          within the 10-second SLA for datasets up to 500K rows.
-- Requirements: 17.1, 17.3

-- -------------------------------------------------------------------------
-- Deals: indexes for pipeline summary and revenue forecast queries
-- -------------------------------------------------------------------------

-- Pipeline summary: stage grouping + win_probability aggregation
CREATE INDEX IF NOT EXISTS idx_deals_pipeline_stage_analytics
    ON deals (tenant_id, company_code, pipeline_id, stage_id)
    WHERE deleted_at IS NULL;

-- Revenue forecast: close_date range scans
CREATE INDEX IF NOT EXISTS idx_deals_close_date_analytics
    ON deals (tenant_id, company_code, close_date)
    WHERE deleted_at IS NULL;

-- -------------------------------------------------------------------------
-- Deal stage history: velocity queries
-- -------------------------------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_deal_stage_history_analytics
    ON deal_stage_history (tenant_id, company_code, deal_id, changed_at);

-- -------------------------------------------------------------------------
-- Leads: conversion rate queries
-- -------------------------------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_leads_conversion_analytics
    ON leads (tenant_id, company_code, source, converted_at, created_at)
    WHERE deleted_at IS NULL;

-- -------------------------------------------------------------------------
-- Activities: activity summary queries
-- -------------------------------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_activities_summary_analytics
    ON activities (tenant_id, company_code, type, status, assigned_user_id, created_at)
    WHERE deleted_at IS NULL;
