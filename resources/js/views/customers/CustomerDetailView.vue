<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePermission } from '@/composables/usePermission'
import { useCustomFields } from '@/composables/useCustomFields'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { customerService } from '@/services/customerService'
import { customerActivityService } from '@/services/customerActivityService'
import { noteService } from '@/services/noteService'
import { timelineService } from '@/services/timelineService'
import { opportunityService } from '@/services/opportunityService'
import { CUSTOMER_STATUS_COLORS, LEAD_STATUS_COLORS, OPPORTUNITY_STAGE_COLORS } from '@/utils/constants'
import { formatDateTime } from '@/utils/formatters'
import type { Customer, CustomerActivity, Note, TimelineEvent, Opportunity, ContactEntry, AddressEntry } from '@/types'
import type { CustomerStatus, CustomerActivityType, ActivityOutcome, LeadStatus } from '@/types/enums'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import ActivityFormModal from '@/components/shared/ActivityFormModal.vue'
import ActivityOutcomePicker from '@/components/shared/ActivityOutcomePicker.vue'
import AttachmentList from '@/components/shared/AttachmentList.vue'
import TagList from '@/components/shared/TagList.vue'
import CustomFieldValue from '@/components/shared/CustomFieldValue.vue'
import UserAvatar from '@/components/shared/UserAvatar.vue'
import WhatsAppSendModal from '@/components/shared/WhatsAppSendModal.vue'

type BadgeVariant = 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'light' | 'dark'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const customerId = Number(route.params.id)
const customFields = useCustomFields('customer')

// --- Customer & Opportunities ---
const customer = ref<Customer | null>(null)
const opportunities = ref<Opportunity[]>([])
const opportunitiesLoading = ref(false)
const loading = ref(true)
const converting = ref(false)
const showWhatsAppModal = ref(false)

const LEAD_STATUSES: LeadStatus[] = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'lost']

// --- Tabs ---
type Tab = 'details' | 'activities' | 'opportunities' | 'notes' | 'timeline' | 'attachments'
const activeTab = ref<Tab>('details')
const loadedTabs = new Set<Tab>(['details'])

// --- Activities ---
const activities = ref<CustomerActivity[]>([])
const activitiesLoading = ref(false)
const showActivityModal = ref(false)
const editingActivity = ref<CustomerActivity | null>(null)
const completingId = ref<number | null>(null)
const selectedOutcomes = ref<Record<number, ActivityOutcome | ''>>({})

// --- Notes ---
const notes = ref<Note[]>([])
const notesLoading = ref(false)
const newNoteBody = ref('')
const savingNote = ref(false)

// --- Timeline ---
const timelineEvents = ref<TimelineEvent[]>([])
const timelineLoading = ref(false)

const ACTIVITY_TYPE_ICONS: Record<CustomerActivityType, string> = {
  onboarding: 'bi-rocket-takeoff',
  support: 'bi-headset',
  check_in: 'bi-telephone-forward',
  review: 'bi-clipboard-check',
  upsell: 'bi-graph-up-arrow',
  renewal: 'bi-arrow-repeat',
  training: 'bi-mortarboard',
  complaint: 'bi-exclamation-triangle',
  follow_up: 'bi-calendar-check',
  whatsapp: 'bi-whatsapp',
  general: 'bi-lightning-charge',
}

const OUTCOME_VARIANTS: Record<ActivityOutcome, BadgeVariant> = {
  positive: 'success',
  neutral: 'secondary',
  negative: 'danger',
  escalated: 'warning',
}

const customerStatusVariant = computed<BadgeVariant>(() => {
  if (customer.value?.is_lead) {
    return (LEAD_STATUS_COLORS[customer.value?.lead_status as LeadStatus] ?? 'secondary') as BadgeVariant
  }
  return (CUSTOMER_STATUS_COLORS[customer.value?.status as CustomerStatus] ?? 'secondary') as BadgeVariant
})

async function fetchData() {
  loading.value = true
  try {
    customer.value = await customerService.get(customerId)
  } finally {
    loading.value = false
  }
}

async function loadActivities() {
  if (activitiesLoading.value) return
  activitiesLoading.value = true
  try {
    const res = await customerActivityService.list(customerId, { per_page: 50, sort_dir: 'desc' })
    activities.value = res.data ?? []
  } finally {
    activitiesLoading.value = false
  }
}

async function onWhatsAppSent() {
  await loadActivities()
  await refreshTimeline()
  await refreshCustomerHeader()
}

async function loadNotes() {
  if (notesLoading.value) return
  notesLoading.value = true
  try {
    const res = await noteService.list('customers', customerId, { per_page: 50 })
    notes.value = res.data ?? []
  } finally {
    notesLoading.value = false
  }
}

async function loadTimeline() {
  if (timelineLoading.value) return
  timelineLoading.value = true
  try {
    const res = await timelineService.forEntity('customers', customerId, { per_page: 50 })
    timelineEvents.value = res.data ?? []
  } finally {
    timelineLoading.value = false
  }
}

// Timeline and header show server-computed state (timeline events,
// health_score, last_contacted_at), so refresh them after mutations.
async function refreshTimeline() {
  if (loadedTabs.has('timeline')) await loadTimeline()
}

async function refreshCustomerHeader() {
  customer.value = await customerService.get(customerId)
}

function switchTab(tab: Tab) {
  activeTab.value = tab
  if (loadedTabs.has(tab)) return
  loadedTabs.add(tab)
  if (tab === 'activities') loadActivities()
  else if (tab === 'opportunities') loadOpportunities()
  else if (tab === 'notes') loadNotes()
  else if (tab === 'timeline') loadTimeline()
}

async function loadOpportunities() {
  if (opportunitiesLoading.value) return
  opportunitiesLoading.value = true
  try {
    const res = await opportunityService.list(customerId, { per_page: 50 })
    opportunities.value = res.data ?? []
  } finally {
    opportunitiesLoading.value = false
  }
}

async function changeLeadStatus(status: string) {
  if (!status || !customer.value) return
  try {
    customer.value = await customerService.updateLeadStatus(customerId, status)
  } catch {
    await fetchData()
  }
}

async function convertToCustomer() {
  const confirmed = await confirm(t('customers.convert'))
  if (!confirmed || !customer.value) return
  converting.value = true
  try {
    customer.value = await customerService.convert(customerId)
  } finally {
    converting.value = false
  }
}

async function deleteOpportunity(opp: Opportunity) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await opportunityService.delete(customerId, opp.id)
  opportunities.value = opportunities.value.filter(o => o.id !== opp.id)
}

async function handleDelete() {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await customerService.delete(customerId)
  router.push({ name: 'customers.index' })
}

const nextFollowUp = computed<CustomerActivity | null>(() => {
  const upcoming = activities.value
    .filter(a => a.type === 'follow_up' && !a.is_completed && a.due_date)
    .sort((a, b) => new Date(a.due_date!).getTime() - new Date(b.due_date!).getTime())
  return upcoming[0] ?? null
})

function openCreateActivity() {
  editingActivity.value = null
  showActivityModal.value = true
}

function openEditActivity(activity: CustomerActivity) {
  editingActivity.value = activity
  showActivityModal.value = true
}

async function onActivitySaved(saved: CustomerActivity) {
  const idx = activities.value.findIndex(a => a.id === saved.id)
  if (idx >= 0) {
    activities.value[idx] = saved
  } else {
    activities.value.unshift(saved)
    if (customer.value) customer.value.activities_count = (customer.value.activities_count ?? 0) + 1
  }
  await Promise.all([refreshTimeline(), refreshCustomerHeader()])
}

async function completeActivity(activity: CustomerActivity) {
  completingId.value = activity.id
  try {
    const outcome = selectedOutcomes.value[activity.id] || undefined
    const updated = await customerActivityService.complete(customerId, activity.id, outcome as ActivityOutcome | undefined)
    const idx = activities.value.findIndex(a => a.id === activity.id)
    if (idx >= 0) activities.value[idx] = updated
    delete selectedOutcomes.value[activity.id]
    await Promise.all([refreshTimeline(), refreshCustomerHeader()])
  } finally {
    completingId.value = null
  }
}

async function deleteActivity(activity: CustomerActivity) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await customerActivityService.delete(customerId, activity.id)
  activities.value = activities.value.filter(a => a.id !== activity.id)
  if (customer.value) customer.value.activities_count = Math.max(0, (customer.value.activities_count ?? 1) - 1)
  await Promise.all([refreshTimeline(), refreshCustomerHeader()])
}

async function addNote() {
  if (!newNoteBody.value.trim()) return
  savingNote.value = true
  try {
    const created = await noteService.create('customers', customerId, { body: newNoteBody.value })
    notes.value.unshift(created)
    newNoteBody.value = ''
    await refreshTimeline()
  } finally {
    savingNote.value = false
  }
}

async function togglePin(note: Note) {
  const updated = await noteService.togglePin(note.id)
  const idx = notes.value.findIndex(n => n.id === note.id)
  if (idx >= 0) notes.value[idx] = updated
  await refreshTimeline()
}

async function deleteNote(note: Note) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await noteService.delete(note.id)
  notes.value = notes.value.filter(n => n.id !== note.id)
  await refreshTimeline()
}

function contactName(c: ContactEntry): string {
  return `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || '—'
}

// Primary (or first) contact entry — preferred source for the header and
// Contact Email/Phone rows so records saved before the backend snapshot
// backfill still display correctly. Legacy snapshot columns are fallback.
const primaryContact = computed<ContactEntry | null>(() => {
  const entries = customer.value?.contacts ?? []
  return entries.find(c => c.is_primary) ?? entries[0] ?? null
})

const assigneeName = computed<string | null>(() => {
  const a = customer.value?.assigned_to
  return typeof a === 'object' && a !== null ? a.name : null
})

function formatAddress(a: AddressEntry): string {
  const cityLine = [a.city, a.state, a.postal_code].filter(Boolean).join(' ')
  const parts = [a.line_1, a.line_2, cityLine, a.country].filter(Boolean)
  return parts.length ? parts.join(', ') : '—'
}

function timelineIcon(type: string): string {
  const icons: Record<string, string> = {
    activity: 'bi-lightning-charge',
    note: 'bi-sticky',
    audit: 'bi-shield-check',
    campaign_email: 'bi-envelope-paper',
    opportunity: 'bi-briefcase',
  }
  return icons[type] ?? 'bi-circle'
}

onMounted(fetchData)
</script>

<template>
  <div>
    <div v-if="loading" class="text-center py-5">
      <BaseSpinner />
    </div>

    <template v-else-if="customer">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
          <h2 class="mb-1">{{ customer.display_name ?? customer.company_name }}</h2>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <BaseBadge variant="dark" pill>{{ customer.type }}</BaseBadge>
            <BaseBadge :variant="customerStatusVariant" pill>
              {{ customer.effective_status ?? customer.status }}
            </BaseBadge>
            <span v-if="customer.score != null && customer.is_lead" class="badge rounded-pill bg-info">Score: {{ customer.score }}</span>
            <span v-if="primaryContact || customer.contact_name" class="text-muted">{{ primaryContact ? contactName(primaryContact) : customer.contact_name }}</span>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'customers.index' }">
            {{ t('common.back') }}
          </BaseButton>
          <BaseButton
            v-if="can('create-activities')"
            variant="outline-success"
            icon="bi-whatsapp"
            @click="showWhatsAppModal = true"
          >
            {{ t('whatsapp.send') }}
          </BaseButton>
          <select
            v-if="customer.is_lead && can('update-customers')"
            class="form-select form-select-sm"
            style="width: auto;"
            :value="customer.lead_status ?? ''"
            @change="changeLeadStatus(($event.target as HTMLSelectElement).value)"
          >
            <option v-for="s in LEAD_STATUSES" :key="s" :value="s">{{ s }}</option>
          </select>
          <BaseButton
            v-if="customer.is_lead && can('update-customers')"
            variant="success"
            icon="bi-arrow-right-circle"
            :loading="converting"
            @click="convertToCustomer"
          >
            {{ t('customers.convert') }}
          </BaseButton>
          <BaseButton
            v-if="can('update-customers')"
            variant="primary"
            icon="bi-pencil"
            :to="{ name: 'customers.edit', params: { id: customer.id } }"
          >
            {{ t('common.edit') }}
          </BaseButton>
          <BaseButton v-if="can('delete-customers')" variant="outline-danger" icon="bi-trash" @click="handleDelete">
            {{ t('common.delete') }}
          </BaseButton>
        </div>
      </div>

      <!-- Tab Nav -->
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'details' }" @click="switchTab('details')">
            <i class="bi bi-info-circle me-1"></i>{{ t('common.details') }}
          </button>
        </li>
        <li v-if="can('view-activities')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'activities' }" @click="switchTab('activities')">
            <i class="bi bi-lightning-charge me-1"></i>{{ t('customers.activities') }}
            <span v-if="customer.activities_count" class="badge bg-secondary ms-1">{{ customer.activities_count }}</span>
          </button>
        </li>
        <li v-if="can('view-opportunities')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'opportunities' }" @click="switchTab('opportunities')">
            <i class="bi bi-kanban me-1"></i>{{ t('customers.opportunities') }}
            <span v-if="customer.opportunities_count" class="badge bg-secondary ms-1">{{ customer.opportunities_count }}</span>
          </button>
        </li>
        <li v-if="can('view-notes')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'notes' }" @click="switchTab('notes')">
            <i class="bi bi-sticky me-1"></i>{{ t('common.notes') }}
          </button>
        </li>
        <li v-if="can('view-timeline')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'timeline' }" @click="switchTab('timeline')">
            <i class="bi bi-clock-history me-1"></i>{{ t('customers.timeline') }}
          </button>
        </li>
        <li v-if="can('view-attachments')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'attachments' }" @click="switchTab('attachments')">
            <i class="bi bi-paperclip me-1"></i>{{ t('roles.moduleLabels.attachments') }}
          </button>
        </li>
      </ul>

      <!-- -- DETAILS TAB -- -->
      <div v-show="activeTab === 'details'">
        <div class="row g-4">
          <div class="col-md-8">
            <BaseCard :title="t('common.details')">
              <div class="row g-3">
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('customers.contactEmail') }}</small>
                  <span>{{ primaryContact?.email || customer.contact_email || '—' }}</span>
                </div>
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('customers.contactPhone') }}</small>
                  <span>{{ primaryContact?.phone || customer.contact_phone || '—' }}</span>
                </div>
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('customers.type') }}</small>
                  <span>{{ customer.type }}<span v-if="customer.is_lead" class="text-muted"> ({{ t('customers.isLead') }})</span></span>
                </div>
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('common.email') }}</small>
                  <span>{{ customer.email ?? customer.contact_email ?? '—' }}</span>
                </div>
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('common.phone') }}</small>
                  <span>{{ customer.phone ?? customer.contact_phone ?? '—' }}</span>
                </div>
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('customers.assignedTo') }}</small>
                  <span>{{ assigneeName || '—' }}</span>
                </div>
                <div class="col-sm-6">
                  <small class="text-muted d-block">{{ t('customers.estimatedValue') }}</small>
                  <span>{{ customer.estimated_value ? `$${Number(customer.estimated_value).toLocaleString()}` : '—' }}</span>
                </div>
                <div v-if="customer.notes" class="col-12">
                  <small class="text-muted d-block">{{ t('common.notes') }}</small>
                  <span style="white-space: pre-wrap">{{ customer.notes }}</span>
                </div>
                <div v-for="definition in customFields.definitions.value" :key="definition.id" class="col-sm-6">
                  <small class="text-muted d-block">{{ definition.label }}</small>
                  <CustomFieldValue :definition="definition" :value="customer.custom_fields?.[definition.name]" />
                </div>
              </div>
            </BaseCard>
          </div>

          <!-- Sidebar -->
          <div class="col-md-4">
            <BaseCard :title="t('common.status')">
              <div class="d-flex flex-column gap-3">
                <div>
                  <small class="text-muted d-block">{{ t('customers.healthScore') }}</small>
                  <div class="progress mb-1" style="height: 20px;">
                    <div
                      class="progress-bar"
                      :class="customer.health_score >= 70 ? 'bg-success' : customer.health_score >= 40 ? 'bg-warning' : 'bg-danger'"
                      :style="{ width: `${customer.health_score}%` }"
                    >
                      {{ customer.health_score }}
                    </div>
                  </div>
                </div>
                <div>
                  <small class="text-muted d-block">{{ t('opportunities.title') }}</small>
                  <span class="fs-4 fw-semibold">{{ customer.opportunities_count ?? 0 }}</span>
                  <small v-if="customer.contract_value" class="text-muted ms-2">{{ Number(customer.contract_value).toLocaleString() }}</small>
                </div>
                <div>
                  <small class="text-muted d-block">{{ t('customers.activities') }}</small>
                  <span class="fs-4 fw-semibold">{{ customer.activities_count ?? 0 }}</span>
                </div>
                <div v-if="customer.last_contacted_at">
                  <small class="text-muted d-block">Last Contacted</small>
                  <span>{{ new Date(customer.last_contacted_at).toLocaleDateString() }}</span>
                </div>
                <div v-if="customer.next_follow_up_at">
                  <small class="text-muted d-block">Next Follow-up</small>
                  <span>{{ new Date(customer.next_follow_up_at).toLocaleDateString() }}</span>
                </div>
                <div v-if="customer.converted_at">
                  <small class="text-muted d-block">Converted At</small>
                  <span>{{ new Date(customer.converted_at).toLocaleDateString() }}</span>
                </div>
                <div v-if="customer.tags?.length">
                  <small class="text-muted d-block">{{ t('tags.title') }}</small>
                  <TagList :tags="customer.tags" />
                </div>
              </div>
            </BaseCard>
          </div>

        </div>

        <!-- Contact persons & Addresses -->
        <div class="row g-4 mt-0">
          <div class="col-md-6">
            <BaseCard :title="t('customers.contacts')" icon="bi-people">
              <div v-if="!(customer.contacts ?? []).length" class="text-muted">{{ t('common.noData') }}</div>
              <div v-else class="d-flex flex-column gap-3">
                <div v-for="(c, i) in (customer.contacts ?? [])" :key="i" class="border-bottom pb-2">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-semibold">{{ contactName(c) }}</span>
                    <BaseBadge v-if="c.is_primary" variant="primary" pill>{{ t('customers.primary') }}</BaseBadge>
                  </div>
                  <small v-if="c.title" class="text-muted d-block">{{ c.title }}</small>
                  <small v-if="c.email" class="d-block">{{ c.email }}</small>
                  <small v-if="c.phone" class="d-block">{{ c.phone }}</small>
                </div>
              </div>
            </BaseCard>
          </div>

          <div class="col-md-6">
            <BaseCard :title="t('customers.addresses')" icon="bi-geo-alt">
              <div v-if="!(customer.addresses ?? []).length" class="text-muted">{{ t('common.noData') }}</div>
              <div v-else class="d-flex flex-column gap-3">
                <div v-for="(a, i) in (customer.addresses ?? [])" :key="i" class="border-bottom pb-2">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-semibold">{{ a.label || a.type || '—' }}</span>
                    <BaseBadge v-if="a.is_primary" variant="primary" pill>{{ t('customers.primary') }}</BaseBadge>
                  </div>
                  <small class="text-muted d-block">{{ formatAddress(a) }}</small>
                </div>
              </div>
            </BaseCard>
          </div>
        </div>
      </div>

      <!-- ── OPPORTUNITIES TAB ── -->
      <div v-show="activeTab === 'opportunities'">
        <BaseCard>
          <template #header-actions>
            <BaseButton
              v-if="can('create-opportunities')"
              variant="outline-primary"
              size="sm"
              icon="bi-plus-lg"
              :to="{ name: 'opportunities.create', params: { customerId: customerId } }"
            >
              {{ t('opportunities.newOpportunity') }}
            </BaseButton>
          </template>
          <div v-if="opportunitiesLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="!opportunities.length" class="text-muted text-center py-4">{{ t('common.noData') }}</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>{{ t('common.name') }}</th>
                  <th>{{ t('customers.status') }}</th>
                  <th class="text-end">{{ t('common.value') }}</th>
                  <th>{{ t('opportunities.assignee') }}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="opp in opportunities" :key="opp.id" role="button" @click="router.push({ name: 'opportunities.show', params: { customerId: customerId, id: opp.id } })">
                  <td><strong>{{ opp.title }}</strong></td>
                  <td><BaseBadge :variant="OPPORTUNITY_STAGE_COLORS[opp.stage] ?? 'secondary'" pill>{{ opp.stage }}</BaseBadge></td>
                  <td class="text-end">{{ opp.amount ? `${opp.currency} ${Number(opp.amount).toLocaleString()}` : '—' }}</td>
                  <td>
                    <span class="d-inline-flex align-items-center gap-1">
                      <UserAvatar :name="opp.assigned_to?.name" :id="opp.assigned_to?.id" :size="24" />
                      <small>{{ opp.assigned_to?.name ?? '—' }}</small>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                      <BaseButton
                        v-if="can('update-opportunities')"
                        variant="outline-primary" size="sm" icon="bi-pencil"
                        :to="{ name: 'opportunities.edit', params: { customerId: customerId, id: opp.id } }"
                        @click.stop
                      />
                      <BaseButton
                        v-if="can('delete-opportunities')"
                        variant="outline-danger" size="sm" icon="bi-trash"
                        @click.stop="deleteOpportunity(opp)"
                      />
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BaseCard>
      </div>

      <!-- ── ACTIVITIES TAB ── -->
      <div v-show="activeTab === 'activities'">
        <BaseCard>
          <template #header-actions>
            <BaseButton
              v-if="can('create-activities')"
              variant="outline-primary"
              size="sm"
              icon="bi-plus-lg"
              @click="openCreateActivity"
            >
              {{ t('customers.logActivity') }}
            </BaseButton>
          </template>

          <!-- Next follow-up banner -->
          <div v-if="nextFollowUp" class="alert d-flex align-items-center gap-2 mb-3" :class="nextFollowUp.is_overdue ? 'alert-danger' : 'alert-info'">
            <i class="bi bi-calendar-check fs-5"></i>
            <div>
              <strong>{{ nextFollowUp.subject }}</strong>
              <span class="ms-2 small">{{ new Date(nextFollowUp.due_date!).toLocaleString() }}</span>
              <span v-if="nextFollowUp.reminder_at" class="ms-2 small">
                <i class="bi bi-bell me-1"></i>{{ new Date(nextFollowUp.reminder_at).toLocaleString() }}
              </span>
            </div>
          </div>

          <!-- Activity List -->
          <div v-if="activitiesLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="activities.length === 0" class="text-center text-muted py-4">{{ t('common.noData') }}</div>
          <ul v-else class="activity-list">
            <li v-for="activity in activities" :key="activity.id" class="activity-item">
              <div class="activity-marker" :class="`activity-marker--${activity.type}`">
                <span>
                  <i :class="['bi', ACTIVITY_TYPE_ICONS[activity.type]]"></i>
                </span>
              </div>
              <div class="activity-content">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div class="d-flex align-items-center flex-wrap gap-1">
                    <span class="fw-semibold">{{ activity.subject }}</span>
                    <span class="badge ms-1" :class="activity.is_completed ? 'bg-success' : activity.is_overdue ? 'bg-danger' : 'bg-secondary'">
                      {{ activity.is_completed ? t('customers.markComplete') : activity.is_overdue ? t('activities.statuses.overdue') : t('tasks.statuses.pending') }}
                    </span>
                    <span v-if="activity.channel" class="badge ms-1 text-bg-info">
                      {{ t(`customers.activityChannels.${activity.channel}`) }}
                    </span>
                    <span v-if="activity.outcome" class="badge ms-1" :class="`text-bg-${OUTCOME_VARIANTS[activity.outcome]}`">
                      {{ t(`customers.activityOutcomes.${activity.outcome}`) }}
                    </span>
                    <span v-if="!activity.is_completed && activity.reminder_at" class="badge ms-1 text-bg-light border" :title="t('activities.reminder')">
                      <i class="bi bi-bell me-1"></i>{{ new Date(activity.reminder_at).toLocaleString() }}
                    </span>
                  </div>
                    <div class="d-flex gap-1 flex-shrink-0 align-items-center">
                      <!-- Complete with outcome -->
                      <template v-if="!activity.is_completed && can('update-activities')">
                        <ActivityOutcomePicker
                          v-model="selectedOutcomes[activity.id]"
                          :disabled="completingId === activity.id"
                          :loading="completingId === activity.id"
                          @complete="completeActivity(activity)"
                        />
                      </template>
                      <button
                        v-if="can('update-activities')"
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        :title="t('common.edit')"
                        @click="openEditActivity(activity)"
                      >
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button
                        v-if="can('delete-activities')"
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        @click="deleteActivity(activity)"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                  <div v-if="activity.description" class="text-muted small mt-1">{{ activity.description }}</div>
                  <div class="text-muted small mt-1 d-flex gap-3 flex-wrap">
                    <span><i class="bi bi-person me-1"></i>{{ activity.user.name }}</span>
                    <span v-if="activity.due_date"><i class="bi bi-calendar me-1"></i>{{ new Date(activity.due_date).toLocaleString() }}</span>
                    <span v-if="activity.completed_at"><i class="bi bi-check-circle me-1"></i>{{ formatDateTime(activity.completed_at) }}</span>
                  </div>
              </div>
            </li>
          </ul>
        </BaseCard>

        <ActivityFormModal
          v-model="showActivityModal"
          :customer-id="customerId"
          :activity="editingActivity"
          @saved="onActivitySaved"
        />
      </div>

      <!-- -- NOTES TAB -- -->
      <div v-show="activeTab === 'notes'">
        <BaseCard>

          <div v-if="can('update-customers')" class="note-composer mb-4">
            <textarea
              v-model="newNoteBody"
              class="form-control note-composer__input"
              rows="3"
              :placeholder="t('customers.addNote') + '—'"
            ></textarea>
            <div class="note-composer__footer">
              <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="!newNoteBody.trim() || savingNote"
                @click="addNote"
              >
                <span v-if="savingNote" class="spinner-border spinner-border-sm me-1"></span>
                {{ t('customers.addNote') }}
              </button>
            </div>
          </div>

          <div v-if="notesLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="notes.length === 0" class="text-center text-muted py-4">{{ t('common.noData') }}</div>
          <ul v-else class="notes-list">
            <li v-for="note in notes" :key="note.id" class="note-item" :class="{ 'note-item--pinned': note.is_pinned }">
              <div class="note-header">
                <div class="note-author-block">
                  <span class="note-avatar"><i class="bi bi-person"></i></span>
                  <span class="note-author">{{ note.author.name }}</span>
                  <time class="note-date">{{ formatDateTime(note.created_at) }}</time>
                  <span v-if="note.is_pinned" class="note-pinned"><i class="bi bi-pin-angle-fill me-1"></i>Pinned</span>
                </div>
                <div class="note-actions">
                  <button type="button" class="btn btn-sm btn-link" :title="note.is_pinned ? 'Unpin' : 'Pin'" @click="togglePin(note)">
                    <i :class="['bi', note.is_pinned ? 'bi-pin-angle-fill' : 'bi-pin-angle']"></i>
                  </button>
                  <button v-if="can('delete-customers')" type="button" class="btn btn-sm btn-link text-danger" title="Delete" @click="deleteNote(note)">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
              <p class="note-body">{{ note.body }}</p>
            </li>
          </ul>
        </BaseCard>
      </div>

      <!-- -- TIMELINE TAB -- -->
      <div v-show="activeTab === 'timeline'">
        <BaseCard>

          <div v-if="timelineLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="timelineEvents.length === 0" class="text-center text-muted py-4">{{ t('common.noData') }}</div>
          <ul v-else class="timeline-list">
            <li v-for="event in timelineEvents" :key="event.id" class="timeline-item">
              <div class="timeline-marker" :class="`timeline-marker--${event.type}`">
                <i :class="['bi', timelineIcon(event.type)]"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-heading">
                  <div class="timeline-title">{{ event.title }}</div>
                  <time class="timeline-time">{{ formatDateTime(event.timestamp) }}</time>
                </div>
                <div v-if="event.description" class="timeline-description">{{ event.description }}</div>
                <div class="timeline-meta">
                  <span><i class="bi bi-person me-1"></i>{{ event.user?.name }}</span>
                  <span v-if="event.subtype" class="timeline-type">{{ event.subtype }}</span>
                </div>
              </div>
            </li>
          </ul>
        </BaseCard>
      </div>
      <!-- -- ATTACHMENTS TAB -- -->
      <div v-if="activeTab === 'attachments'">
        <BaseCard :title="t('roles.moduleLabels.attachments')">
          <AttachmentList entity-type="customers" :entity-id="customerId" :can-upload="can('create-attachments')" @uploaded="refreshTimeline" @deleted="refreshTimeline" />
        </BaseCard>
      </div>
    </template>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
    <WhatsAppSendModal
      v-if="customer"
      v-model="showWhatsAppModal"
      entity-type="customer"
      :customer-id="customer.id"
      @sent="onWhatsAppSent"
    />
  </div>
</template>
