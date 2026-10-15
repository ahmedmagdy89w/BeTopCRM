import type {
  LeadStatus,
  CustomerType,
  OpportunityStage,
  LeadSource,
  CustomerStatus,
  OpportunityType,
  TaskPriority,
  TaskStatus,
  CustomerActivityType,
  ActivityChannel,
  ActivityOutcome,
  CampaignStatus,
} from './enums'

export interface Role {
  id: number
  name: string
  is_system: boolean
  permissions: string[]
  users_count: number
}

export interface User {
  id: number
  name: string
  email: string
  phone?: string
  timezone: string
  locale: 'en' | 'ar'
  role: string
  is_active: boolean
  avatar_url?: string
  permissions: string[]
  created_at: string
}

export interface ContactEntry {
  first_name: string
  last_name: string
  email?: string
  phone?: string
  title?: string
  is_primary?: boolean
}

export interface AddressEntry {
  label?: string
  type?: string
  line_1?: string
  line_2?: string
  city?: string
  state?: string
  postal_code?: string
  country?: string
  is_primary?: boolean
}

export interface Customer {
  id: number
  type: CustomerType
  is_lead: boolean
  lead_status?: LeadStatus | null
  status: CustomerStatus
  effective_status?: string
  display_name?: string
  first_name?: string
  last_name?: string
  full_name?: string
  email?: string
  phone?: string
  job_title?: string
  birthday?: string
  gender?: string
  company_name?: string
  industry?: string
  website?: string
  tax_id?: string
  contact_name?: string
  contact_email?: string
  contact_phone?: string
  source?: LeadSource
  estimated_value?: string
  expected_close_date?: string
  description?: string
  assigned_to?: { id: number; name: string } | number | null
  created_by?: { id: number; name: string }
  account_manager?: { id: number; name: string }
  contract_value?: string
  health_score: number
  last_contacted_at?: string
  next_follow_up_at?: string
  notes?: string
  converted_at?: string | null
  contacts?: ContactEntry[]
  addresses?: AddressEntry[]
  score?: number
  score_breakdown?: Record<string, number>
  scored_at?: string
  activities_count?: number
  opportunities_count?: number
  opportunities?: Opportunity[]
  tags?: Tag[]
  tag_ids?: number[]
  custom_fields?: Record<string, unknown>
  created_at: string
  updated_at: string
}

export interface Opportunity {
  id: number
  customer_id: number
  customer?: { id: number; display_name: string; type: CustomerType; is_lead: boolean }
  title: string
  type: OpportunityType
  stage: OpportunityStage
  is_open?: boolean
  amount?: string
  currency: string
  probability?: number
  expected_close_date?: string
  start_date?: string
  end_date?: string
  closed_at?: string | null
  assigned_to?: { id: number; name: string }
  created_by?: { id: number; name: string }
  notes?: string
  custom_fields?: Record<string, unknown>
  product_pivot?: { quantity: number; unit_price: string; notes?: string | null }
  tags?: Tag[]
  products?: OpportunityProduct[]
  activities_count?: number
  created_at: string
  updated_at: string
}

export interface OpportunityProduct {
  id: number
  name: string
  sku?: string
  unit_price: string
  currency: string
  category?: { id: number; name: string }
  tax_id?: number | null
  tax?: { id: number; name: string; percent: string } | null
  quantity: number
  discount_percent?: string | null
  discount_amount: string
  notes?: string
}

export interface DocumentCompany {
  company_name: string | null
  company_email: string | null
  company_phone: string | null
  company_website: string | null
  company_tax_number: string | null
  company_currency: string | null
  company_timezone: string | null
  address: {
    street: string | null
    city: string | null
    state: string | null
    country: string | null
    zip: string | null
  }
  logo_url: string | null
}

export interface DocumentCustomer {
  id: number
  name: string
  contact_name?: string | null
  contact_title?: string | null
  email?: string | null
  phone?: string | null
  website?: string | null
  tax_id?: string | null
  address: AddressEntry
}

export interface QuotationItem {
  id: number
  product_id?: number | null
  product_name: string
  sku?: string | null
  quantity: number
  unit_price: string
  subtotal: string
  discount_percent?: string | null
  discount_amount: string
  tax_id?: number | null
  tax_name?: string | null
  tax_percent: string
  tax_amount: string
  notes?: string | null
}

export interface Quotation {
  id: number
  quotation_number: string
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired'
  currency: string
  subtotal: string
  tax_percent: string
  tax_amount: string
  discount_amount: string
  total: string
  valid_until: string
  notes?: string | null
  terms?: string | null
  sent_at?: string | null
  opportunity_id: number
  customer_id: number
  company: DocumentCompany
  customer: DocumentCustomer
  items: QuotationItem[]
}

export interface InvoiceItem extends QuotationItem {}

export interface Invoice {
  id: number
  invoice_number: string
  status: 'issued' | 'paid' | 'cancelled'
  currency: string
  subtotal: string
  tax_percent: string
  tax_amount: string
  discount_amount: string
  total: string
  issued_at: string
  opportunity_id: number
  customer_id: number
  quotation_id?: number | null
  quotation_number?: string | null
  company: DocumentCompany
  customer: DocumentCustomer
  items: InvoiceItem[]
}

export interface DocumentCustomerSummary {
  id: number
  display_name: string
}

export interface DocumentOpportunitySummary {
  id: number
  title: string
}

export interface QuotationListItem {
  document_type: 'quotation'
  id: number
  quotation_number: string
  status: Quotation['status']
  currency: string
  total: string
  valid_until: string
  sent_at?: string | null
  customer_id: number
  opportunity_id: number
  customer?: DocumentCustomerSummary
  opportunity?: DocumentOpportunitySummary
  created_at: string
}

export interface InvoiceListItem {
  document_type: 'invoice'
  id: number
  invoice_number: string
  status: Invoice['status']
  currency: string
  total: string
  issued_at: string
  customer_id: number
  opportunity_id: number
  quotation_id?: number | null
  customer?: DocumentCustomerSummary
  opportunity?: DocumentOpportunitySummary
  created_at: string
}

export type SalesDocument = QuotationListItem | InvoiceListItem




export type OpportunityActivityType = 'call' | 'email' | 'meeting' | 'task' | 'whatsapp' | 'note'

export interface OpportunityActivity {
  id: number
  opportunity_id: number
  type: OpportunityActivityType
  channel?: ActivityChannel | null
  subject: string
  description?: string
  outcome?: ActivityOutcome
  due_date?: string
  reminder_at?: string | null
  reminder_sent_at?: string | null
  completed_at?: string
  is_completed: boolean
  is_overdue?: boolean
  user: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface Task {
  id: number
  title: string
  description?: string
  priority: TaskPriority
  status: TaskStatus
  due_date?: string
  completed_at?: string
  is_overdue: boolean
  assignee: { id: number; name: string }
  created_by: { id: number; name: string }
  entity?: { type: string; id: number; name: string }
  created_at: string
  updated_at: string
}

export interface Tag {
  id: number
  name: string
  color?: string
  created_at: string
}

export interface AppNotification {
  id: string
  type: string
  data: {
    title: string
    body: string
    action_url: string
    entity_type?: string
    entity_id?: number
    [key: string]: unknown
  }
  read_at: string | null
  created_at: string
}

export interface CustomerActivity {
  id: number
  type: CustomerActivityType
  channel?: ActivityChannel
  subject: string
  description?: string
  outcome?: ActivityOutcome
  due_date?: string
  reminder_at?: string
  reminder_sent_at?: string
  completed_at?: string
  is_completed: boolean
  is_overdue: boolean
  user: { id: number; name: string }
  customer?: { id: number; display_name: string }
  created_at: string
  updated_at: string
}

export type LeadActivity = CustomerActivity

export interface Attachment {
  id: number
  original_name: string
  mime_type: string
  size: number
  size_human: string
  download_url: string
  uploaded_by: { id: number; name: string }
  created_at: string
}

export interface Note {
  id: number
  body: string
  is_pinned: boolean
  author: { id: number; name: string }
  parent_id: number | null
  replies_count: number
  replies?: Note[]
  created_at: string
  updated_at: string
}

export interface TimelineEvent {
  id: string
  type: string
  subtype?: string
  title: string
  description?: string
  user: { id: number; name: string }
  metadata?: Record<string, unknown>
  timestamp: string
}

export interface AuditLog {
  id: number
  user: { id: number; name: string } | null
  entity_type: string
  entity_id: number
  action: 'created' | 'updated' | 'deleted'
  changes: Record<string, { old: unknown; new: unknown }>
  ip_address?: string
  created_at: string
}

export interface EmailTemplate {
  id: number
  name: string
  subject: string
  body: string
  placeholders: string[]
  created_at: string
}

export interface GenerateTemplateParams {
  topic: string
  audience?: string
  tone?: 'professional' | 'friendly' | 'urgent'
  context?: string
  language?: 'en' | 'ar'
}

export interface GenerateTemplateResult {
  subject: string
  body: string
  placeholders: string[]
}

export interface Campaign {
  id: number
  name: string
  template: { id: number; name: string }
  status: CampaignStatus
  segment_filters?: Record<string, unknown>
  stats: { total_recipients: number; sent: number; failed: number; pending: number }
  scheduled_at?: string
  sent_at?: string
  created_at: string
}

export interface Product {
  id: number
  name: string
  sku?: string
  description?: string
  unit_price: string
  currency: string
  category_id?: number | null
  category?: { id: number; name: string; slug: string }
  tax_id?: number | null
  tax?: { id: number; name: string; percent: string } | null
  custom_fields?: Record<string, unknown>
  category_custom_fields?: Record<string, unknown>
  created_by?: { id: number; name: string }
  is_active: boolean
  created_at: string
}

export interface ProductCategory {
  id: number
  name: string
  slug: string
  parent_id: number | null
  description?: string
  sort_order: number
  children?: ProductCategory[]
}

export interface WebhookEndpoint {
  id: number
  url: string
  events: string[]
  secret?: string
  is_active: boolean
  description?: string
  last_triggered_at?: string
  failure_count: number
  created_at: string
}

export interface CustomFieldDefinition {
  id: number
  group_id: number
  entity_type: string
  scope: 'entity' | 'category'
  category_id: number | null
  name: string
  label: string
  type: CustomFieldType
  options?: string[]
  is_required: boolean
  is_filterable: boolean
  sort_order: number
  created_at: string
}

export interface CustomFieldDefinitionInput {
  id?: number
  name: string
  label: string
  type: CustomFieldType
  options?: string[] | null
  is_required: boolean
  is_filterable: boolean
  sort_order: number
}

export type CustomFieldType =
  | 'text'
  | 'textarea'
  | 'number'
  | 'decimal'
  | 'date'
  | 'datetime'
  | 'select'
  | 'multi_select'
  | 'boolean'
  | 'color'
  | 'url'
  | 'email'
  | 'phone'

export interface CustomFieldGroup {
  id: number
  entity_type: string
  scope: 'entity' | 'category'
  category_id: number | null
  scope_key: string
  name: string
  description: string | null
  created_by: number
  created_at: string
  updated_at: string
}

export interface CustomFieldEntity {
  entity_type: string
  label: string
  supports_categories: boolean
}

export interface CustomFieldGroupDefinitions {
  group: CustomFieldGroup | null
  definitions: CustomFieldDefinition[]
}

export interface Import {
  id: number
  entity_type: string
  original_filename: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  total_rows?: number
  processed_rows: number
  successful_rows: number
  failed_rows: number
  errors?: { row: number; field: string; message: string }[]
  completed_at?: string
  created_at: string
}

export interface AsyncJob {
  id: number
  type: string
  status: 'pending' | 'processing' | 'completed' | 'failed'
  progress: number
  input?: { method: string; params: unknown[] }
  result?: unknown
  error?: string | null
  started_at?: string
  completed_at?: string
  created_at: string
  updated_at: string
}

// Dashboard types
export interface DashboardPipeline {
  total_leads: number
  by_status: Record<string, number>
  total_estimated_value: string
  won_value: string
  conversion_rate: string
  pipeline_value: string
}

export interface DashboardCustomers {
  total: number
  by_status: Record<string, number>
  avg_health_score: number
  needing_follow_up: number
  total_won_opportunity_revenue: string
}

export interface DashboardTasks {
  overdue: number
  due_today: number
  upcoming_7_days: number
  in_progress: number
  completed_this_period: number
}

export interface DashboardRevenueMonth {
  month: string
  revenue: string
  opportunities_won: number
}

export interface DashboardRevenue {
  this_period: string
  previous_period: string
  change_percent: string
  by_month: DashboardRevenueMonth[]
}

export interface DashboardTopPerformer {
  user: { id: number; name: string } | null
  leads_won: number
  won_value: string
}

export interface DashboardCampaigns {
  active: number
  total_sent_this_period: number
  total_recipients: number
}

export interface DashboardData {
  pipeline: DashboardPipeline
  customers: DashboardCustomers
  tasks: DashboardTasks
  revenue: DashboardRevenue
  top_performers?: DashboardTopPerformer[]
  campaigns?: DashboardCampaigns
}

export interface ActivityFeedItem {
  id: number
  type: string
  description: string
  entity_type: string
  entity_id: number
  entity_name: string
  metadata?: Record<string, unknown>
  user: { id: number; name: string } | null
  created_at: string
}

// Report types
export interface UserPerformanceRow {
  user: { id: number; name: string }
  leads_assigned: number
  opportunities_assigned: number
  opportunities_won: number
  opportunities_lost: number
  opportunities_in_progress: number
  opportunity_win_rate: string
  leads_won: number
  leads_lost: number
  leads_in_progress: number
  conversion_rate: string
  total_won_value: string | null
  total_won_value_by_currency: { currency: string; value: string; won_count: number; avg_opportunity_value: string }[]
  avg_opportunity_value: string | null
  activities_logged: number
  activities_by_type: Record<string, number>
  avg_days_to_close: number | null
}

export interface UserPerformanceSummary {
  total_users: number
  total_leads_assigned: number
  total_opportunities_assigned: number
  total_won_value: string | null
  total_won_value_by_currency: { currency: string; value: string }[]
  overall_opportunity_win_rate: string
  overall_conversion_rate: string
}

export interface CompanyReportRow {
  organization: { id: number; name: string; industry?: string }
  total_leads: number
  leads_by_status: Record<string, number>
  conversion_rate: string
  total_estimated_value: string | null
  total_estimated_value_by_currency: { currency: string; value: string }[]
  total_won_value: string | null
  total_won_value_by_currency: { currency: string; value: string }[]
  contacts_count: number
  latest_activity_date: string | null
}

export interface CompanyReportSummary {
  total_organizations: number
  total_leads_across_orgs: number
  total_won_value: string | null
  total_won_value_by_currency: { currency: string; value: string }[]
  top_industries: { industry: string; won_value: string | null; won_value_by_currency: { currency: string; value: string }[]; lead_count: number }[]
}

export interface CustomerReportRow {
  customer: {
    id: number
    company_name: string
    contact_name: string
    status: string
    health_score: number
  }
  account_manager: { id: number; name: string }
  total_won_opportunity_value: string | null
  total_won_opportunity_value_by_currency: { currency: string; value: string }[]
  won_opportunities_count: number
  activities_count: number
  activities_by_type: Record<string, number>
  last_contacted_at: string | null
  days_since_last_contact: number | null
  next_follow_up_at: string | null
  days_as_customer: number | null
  converted_at: string | null
}

export interface CustomerReportSummary {
  total_customers: number
  by_status: Record<string, number>
  total_won_opportunity_revenue: string | null
  total_won_opportunity_revenue_by_currency: { currency: string; value: string }[]
  avg_health_score: number
  customers_needing_follow_up: number
  avg_activities_per_customer: number
  churn_rate: string
  retention_rate: string
}

export interface LeadPipelineReport {
  data: {
    by_status: Record<string, number>
    by_source: { source: string | null; lead_count: number; avg_score: number | null }[]
    by_owner: { owner: { id: number; name: string } | null; lead_count: number; avg_score: number | null }[]
    top_scored_leads: {
      customer: { id: number; name: string }
      status: string
      source: string | null
      score: number
      assigned_to: number | null
    }[]
  }
  summary: {
    total_leads: number
    open_leads: number
    lost_leads: number
    scored_leads: number
    avg_score: number | null
  }
}

export interface ProductPerformanceReport {
  data: {
    product: {
      id: number
      name: string
      sku: string | null
      is_active: boolean
      category: { id: number; name: string } | null
    }
    booked_sales: { currency: string; opportunities_count: number; quantity: number; booked_value: string }[]
    open_pipeline: { opportunities_count: number; quantity: number }
  }[]
  summary: {
    products: number
    products_with_sales: number
    products_with_open_pipeline: number
    open_pipeline_opportunities: number
    open_pipeline_quantity: number
    booked_sales_by_currency: { currency: string; quantity: number; booked_value: string }[]
  }
}

export interface UserProductPerformanceRow {
  user: { id: number; name: string }
  product: { id: number; name: string; sku: string | null }
  opportunities_count: number
  won_count: number
  open_count: number
  quantity: number
  booked_value_by_currency: { currency: string; value: string }[]
  total_won_value: string | null
}

export interface UserProductPerformanceSummary {
  users: number
  products: number
  booked_sales_by_currency: { currency: string; value: string }[]
}

export interface SalesReportRow {
  period: string
  won_count: number
  revenue_by_currency: { currency: string; value: string }[]
  total_revenue: string | null
  open_count: number
  open_value: string
}

export interface SalesReportSummary {
  group_by: 'month' | 'year'
  periods: number
  total_won_count: number
  total_revenue_by_currency: { currency: string; value: string }[]
}
