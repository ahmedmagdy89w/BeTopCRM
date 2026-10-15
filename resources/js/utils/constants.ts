import type { LeadStatus, CustomerStatus, OpportunityStage, TaskPriority, TaskStatus } from '@/types'

export type BadgeVariant =
  | 'primary'
  | 'secondary'
  | 'success'
  | 'danger'
  | 'warning'
  | 'info'
  | 'light'
  | 'dark'

export const LEAD_STATUS_COLORS: Record<LeadStatus, BadgeVariant> = {
  new: 'info',
  contacted: 'primary',
  qualified: 'warning',
  proposal: 'secondary',
  negotiation: 'dark',
  lost: 'danger',
}

export const OPPORTUNITY_STAGE_COLORS: Record<OpportunityStage, BadgeVariant> = {
  prospecting: 'info',
  qualification: 'primary',
  proposal: 'warning',
  negotiation: 'dark',
  won: 'success',
  lost: 'danger',
}

export const OPEN_OPPORTUNITY_STAGES: OpportunityStage[] = ['prospecting', 'qualification', 'proposal', 'negotiation']

export const CLOSED_OPPORTUNITY_STAGES: OpportunityStage[] = ['won', 'lost']

export const CUSTOMER_TYPE_COLORS: Record<string, BadgeVariant> = {
  individual: 'primary',
  company: 'dark',
}

export const CUSTOMER_STATUS_COLORS: Record<CustomerStatus, BadgeVariant> = {
  active: 'success',
  at_risk: 'warning',
  churned: 'danger',
  paused: 'secondary',
  renewed: 'info',
}

export const TASK_PRIORITY_COLORS: Record<TaskPriority, BadgeVariant> = {
  low: 'info',
  medium: 'warning',
  high: 'danger',
  urgent: 'dark',
}

export const TASK_STATUS_COLORS: Record<TaskStatus, BadgeVariant> = {
  pending: 'secondary',
  in_progress: 'primary',
  completed: 'success',
  cancelled: 'danger',
}
