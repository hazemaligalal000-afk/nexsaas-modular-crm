/**
 * Global TypeScript Type Definitions
 * Requirements: Master Spec - TypeScript Migration
 */

// ============================================================================
// COMMON TYPES
// ============================================================================

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  error?: string;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface SelectOption {
  value: string | number;
  label: string;
  disabled?: boolean;
}

// ============================================================================
// USER & AUTH TYPES
// ============================================================================

export interface User {
  id: number;
  tenant_id: number;
  email: string;
  first_name: string;
  last_name: string;
  role: UserRole;
  status: UserStatus;
  avatar_url?: string;
  created_at: string;
  updated_at: string;
}

export type UserRole = 
  | 'owner'
  | 'admin'
  | 'manager'
  | 'user'
  | 'sales_rep'
  | 'support_agent'
  | 'accountant';

export type UserStatus = 'active' | 'inactive' | 'suspended';

export interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

// ============================================================================
// CRM TYPES
// ============================================================================

export interface Lead {
  id: number;
  tenant_id: number;
  company_name: string;
  contact_name: string;
  email: string;
  phone?: string;
  industry?: string;
  company_size?: number;
  status: LeadStatus;
  score?: number;
  source?: string;
  assigned_to?: number;
  created_at: string;
  updated_at: string;
}

export type LeadStatus = 
  | 'new'
  | 'contacted'
  | 'qualified'
  | 'proposal'
  | 'negotiation'
  | 'won'
  | 'lost';

export interface Contact {
  id: number;
  tenant_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  company?: string;
  job_title?: string;
  status: 'active' | 'inactive';
  created_at: string;
  updated_at: string;
}

export interface Deal {
  id: number;
  tenant_id: number;
  name: string;
  value: number;
  currency: string;
  stage: DealStage;
  probability: number;
  expected_close_date?: string;
  contact_id?: number;
  assigned_to?: number;
  created_at: string;
  updated_at: string;
}

export type DealStage =
  | 'prospecting'
  | 'qualification'
  | 'proposal'
  | 'negotiation'
  | 'closed_won'
  | 'closed_lost';

// ============================================================================
// ERP TYPES
// ============================================================================

export interface Invoice {
  id: number;
  tenant_id: number;
  invoice_number: string;
  customer_id: number;
  issue_date: string;
  due_date: string;
  subtotal: number;
  tax: number;
  total: number;
  currency: string;
  status: InvoiceStatus;
  items: InvoiceItem[];
  created_at: string;
  updated_at: string;
}

export type InvoiceStatus = 
  | 'draft'
  | 'sent'
  | 'paid'
  | 'overdue'
  | 'cancelled';

export interface InvoiceItem {
  id: number;
  description: string;
  quantity: number;
  unit_price: number;
  tax_rate: number;
  total: number;
}

export interface Project {
  id: number;
  tenant_id: number;
  name: string;
  description?: string;
  status: ProjectStatus;
  start_date?: string;
  end_date?: string;
  budget?: number;
  manager_id?: number;
  created_at: string;
  updated_at: string;
}

export type ProjectStatus = 
  | 'planning'
  | 'in_progress'
  | 'on_hold'
  | 'completed'
  | 'cancelled';

// ============================================================================
// ACCOUNTING TYPES
// ============================================================================

export interface JournalEntry {
  id: number;
  tenant_id: number;
  entry_number: string;
  entry_date: string;
  description: string;
  status: 'draft' | 'posted' | 'void';
  lines: JournalEntryLine[];
  created_at: string;
  updated_at: string;
}

export interface JournalEntryLine {
  id: number;
  account_id: number;
  account_code: string;
  account_name: string;
  debit: number;
  credit: number;
  description?: string;
}

export interface Account {
  id: number;
  tenant_id: number;
  code: string;
  name: string;
  type: AccountType;
  parent_id?: number;
  balance: number;
  currency: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export type AccountType =
  | 'asset'
  | 'liability'
  | 'equity'
  | 'revenue'
  | 'expense';

// ============================================================================
// AI TYPES
// ============================================================================

export interface LeadScore {
  score: number;
  category: 'hot' | 'warm' | 'cold';
  reasoning: string;
  next_action: string;
  priority: 'high' | 'medium' | 'low';
}

export interface IntentDetection {
  primary_intent: string;
  confidence: number;
  secondary_intents: string[];
  sentiment: 'positive' | 'neutral' | 'negative';
  urgency: 'high' | 'medium' | 'low';
  suggested_response: string;
}

export interface EmailVariant {
  subject: string;
  body: string;
  tone: 'professional' | 'friendly' | 'casual';
  length: 'short' | 'medium' | 'brief';
}

export interface DealForecast {
  close_probability: number;
  predicted_close_date: string;
  confidence: number;
  risk_factors: string[];
  positive_signals: string[];
  recommended_actions: string[];
  forecast_category: 'commit' | 'best_case' | 'pipeline' | 'omitted';
}

// ============================================================================
// BILLING TYPES
// ============================================================================

export interface Subscription {
  id: number;
  tenant_id: number;
  plan_id: string;
  status: SubscriptionStatus;
  seat_count: number;
  price_per_seat: number;
  current_period_start: string;
  current_period_end: string;
  trial_end?: string;
  cancel_at_period_end: boolean;
  created_at: string;
  updated_at: string;
}

export type SubscriptionStatus =
  | 'trialing'
  | 'active'
  | 'past_due'
  | 'cancelled'
  | 'unpaid';

export interface UsageSummary {
  tenant_id: number;
  period: string;
  usage: UsageMetric[];
}

export interface UsageMetric {
  service: string;
  metric: string;
  total_quantity: number;
  record_count: number;
}

// ============================================================================
// NOTIFICATION TYPES
// ============================================================================

export interface Notification {
  id: number;
  tenant_id: number;
  user_id: number;
  type: NotificationType;
  title: string;
  message: string;
  data?: Record<string, any>;
  read: boolean;
  created_at: string;
}

export type NotificationType =
  | 'info'
  | 'success'
  | 'warning'
  | 'error'
  | 'lead_assigned'
  | 'deal_won'
  | 'payment_received'
  | 'trial_ending';

// ============================================================================
// FORM TYPES
// ============================================================================

export interface FormField {
  name: string;
  label: string;
  type: 'text' | 'email' | 'number' | 'select' | 'textarea' | 'date' | 'checkbox';
  required?: boolean;
  placeholder?: string;
  options?: SelectOption[];
  validation?: ValidationRule[];
}

export interface ValidationRule {
  type: 'required' | 'email' | 'min' | 'max' | 'pattern';
  value?: any;
  message: string;
}

// ============================================================================
// TABLE TYPES
// ============================================================================

export interface TableColumn<T = any> {
  key: keyof T | string;
  label: string;
  sortable?: boolean;
  render?: (value: any, row: T) => React.ReactNode;
  width?: string;
}

export interface TableProps<T> {
  data: T[];
  columns: TableColumn<T>[];
  loading?: boolean;
  onRowClick?: (row: T) => void;
  pagination?: PaginationProps;
}

export interface PaginationProps {
  page: number;
  perPage: number;
  total: number;
  onPageChange: (page: number) => void;
}

// ============================================================================
// DASHBOARD TYPES
// ============================================================================

export interface DashboardWidget {
  id: string;
  type: 'metric' | 'chart' | 'table' | 'list';
  title: string;
  data: any;
  size: 'small' | 'medium' | 'large';
}

export interface MetricWidget {
  label: string;
  value: number | string;
  change?: number;
  trend?: 'up' | 'down' | 'neutral';
  icon?: string;
}

// ============================================================================
// FILTER TYPES
// ============================================================================

export interface FilterConfig {
  field: string;
  operator: FilterOperator;
  value: any;
}

export type FilterOperator =
  | 'equals'
  | 'not_equals'
  | 'contains'
  | 'starts_with'
  | 'ends_with'
  | 'greater_than'
  | 'less_than'
  | 'between'
  | 'in'
  | 'not_in';

// ============================================================================
// EXPORT TYPES
// ============================================================================

export interface ExportOptions {
  format: 'csv' | 'xlsx' | 'pdf';
  filename: string;
  columns?: string[];
  filters?: FilterConfig[];
}
