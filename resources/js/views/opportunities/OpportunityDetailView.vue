<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePermission } from '@/composables/usePermission'
import { useAuthStore } from '@/stores/auth'
import { useProductLabels } from '@/composables/useProductLabels'
import { useFeatures } from '@/composables/useFeatures'
import { useCustomFields } from '@/composables/useCustomFields'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { useToast } from '@/composables/useToast'
import { opportunityService } from '@/services/opportunityService'
import { opportunityActivityService } from '@/services/opportunityActivityService'
import { opportunityProductService } from '@/services/opportunityProductService'
import { quotationService } from '@/services/quotationService'
import { invoiceService } from '@/services/invoiceService'
import { noteService } from '@/services/noteService'
import { timelineService } from '@/services/timelineService'
import { OPPORTUNITY_STAGE_COLORS } from '@/utils/constants'
import { formatDate, formatDateTime } from '@/utils/formatters'
import type { Opportunity, OpportunityActivity, OpportunityProduct, Product, Note, TimelineEvent, Quotation, Invoice } from '@/types'
import type { ActivityOutcome, OpportunityStage } from '@/types/enums'
import type { OpportunityActivityType } from '@/types/models'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import UserAvatar from '@/components/shared/UserAvatar.vue'
import DocumentParties from '@/components/shared/DocumentParties.vue'
import AttachmentList from '@/components/shared/AttachmentList.vue'
import ProductPicker from '@/components/shared/ProductPicker.vue'
import CustomFieldValue from '@/components/shared/CustomFieldValue.vue'
import OpportunityActivityModal from '@/components/shared/OpportunityActivityModal.vue'
import ActivityOutcomePicker from '@/components/shared/ActivityOutcomePicker.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import StageDropdown from '@/components/shared/StageDropdown.vue'

type BadgeVariant = 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info' | 'light' | 'dark'
import WhatsAppSendModal from '@/components/shared/WhatsAppSendModal.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermission()
const auth = useAuthStore()
const { plural: productPlural } = useProductLabels()
const { showQuotations, showInvoices } = useFeatures()
const toast = useToast()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const customerId = Number(route.params.customerId)
const opportunityId = Number(route.params.id)
const customFields = useCustomFields('opportunity')

const STAGE_TRANSITIONS: Record<OpportunityStage, OpportunityStage[]> = {
  prospecting: ['qualification', 'lost'],
  qualification: ['prospecting', 'proposal', 'lost'],
  proposal: ['qualification', 'negotiation', 'lost'],
  negotiation: ['proposal', 'won', 'lost'],
  won: [],
  lost: ['prospecting'],
}

const ACTIVITY_TYPE_ICONS: Record<OpportunityActivityType, string> = {
  call: 'bi-telephone',
  email: 'bi-envelope',
  meeting: 'bi-people',
  task: 'bi-list-task',
  whatsapp: 'bi-whatsapp',
  note: 'bi-sticky',
}

const OUTCOME_VARIANTS: Record<ActivityOutcome, BadgeVariant> = {
  positive: 'success',
  neutral: 'secondary',
  negative: 'danger',
  escalated: 'warning',
}

const opportunity = ref<Opportunity | null>(null)
const loading = ref(true)
const movingStage = ref(false)

// --- Tabs ---
type Tab = 'details' | 'quotation' | 'invoice' | 'activities' | 'products' | 'notes' | 'timeline' | 'attachments'
const activeTab = ref<Tab>('details')
const loadedTabs = new Set<Tab>(['details'])

// --- Activities ---
const activities = ref<OpportunityActivity[]>([])
const activitiesLoading = ref(false)
const showActivityModal = ref(false)
const editingActivity = ref<OpportunityActivity | null>(null)
const selectedOutcomes = ref<Record<number, ActivityOutcome | ''>>({})
const completingId = ref<number | null>(null)

// --- Products ---
const products = ref<OpportunityProduct[]>([])
const productsLoading = ref(false)
const attaching = ref(false)
const editingProductId = ref<number | null>(null)
const editQuantity = ref(1)
const editUnitPrice = ref('')
const editDiscount = ref('')
const editDiscountMode = ref<DiscountMode>('amount')

type DiscountMode = 'amount' | 'percent'

interface ProductDraft {
  id: number
  product_id: number | null
  quantity: number
  unit_price: string
  discount: string
  discount_mode: DiscountMode
  notes: string
}

let nextProductDraftId = 1

function createProductDraft(): ProductDraft {
  return { id: nextProductDraftId++, product_id: null, quantity: 1, unit_price: '', discount: '', discount_mode: 'amount', notes: '' }
}

const productDrafts = ref<ProductDraft[]>([])

const excludedProductIds = computed(() => [
  ...products.value.map(product => product.id),
  ...productDrafts.value.flatMap(draft => draft.product_id == null ? [] : [draft.product_id]),
])

function onProductDraftPick(draft: ProductDraft, product: Product | null) {
  draft.product_id = product?.id ?? null
  draft.unit_price = product ? String(product.unit_price) : ''
}

function addProductDraft() {
  productDrafts.value.push(createProductDraft())
}

function removeProductDraft(draftId: number) {
  productDrafts.value = productDrafts.value.filter(draft => draft.id !== draftId)
}

// --- Notes ---
const notes = ref<Note[]>([])
const notesLoading = ref(false)
const newNoteBody = ref('')
const savingNote = ref(false)

// --- Timeline ---
const timelineEvents = ref<TimelineEvent[]>([])
const timelineLoading = ref(false)
const quotations = ref<Quotation[]>([])
const quotation = ref<Quotation | null>(null)
const quotationLoading = ref(false)
const sendingQuotation = ref(false)
const showQuotationPreview = ref(false)
const invoice = ref<Invoice | null>(null)
const invoiceLoading = ref(false)
const showInvoicePreview = ref(false)
const showWhatsAppModal = ref(false)
const whatsAppEntityType = ref<'quotation' | 'invoice'>('quotation')

const stageVariant = computed(() =>
  (OPPORTUNITY_STAGE_COLORS[opportunity.value?.stage as OpportunityStage] ?? 'secondary'),
)

function formatShortDate(value: string | null | undefined): string {
  return value ? formatDate(value, 'M/d/yyyy') : '—'
}

const contractPeriod = computed(() => {
  const start = formatShortDate(opportunity.value?.start_date)
  const end = formatShortDate(opportunity.value?.end_date)
  if (start === '—' && end === '—') return '—'
  return `${start} – ${end}`
})

const lastQuotation = computed(() => quotations.value[0] ?? quotation.value)
const lastQuotationPdfUrl = computed(() => lastQuotation.value
  ? quotationService.pdfUrl(customerId, opportunityId, lastQuotation.value.id)
  : null,
)
const lastQuotationPreviewUrl = computed(() => lastQuotation.value
  ? quotationService.previewUrl(customerId, opportunityId, lastQuotation.value.id)
  : null,
)
const invoicePdfUrl = computed(() => invoice.value
  ? invoiceService.pdfUrl(customerId, opportunityId, invoice.value.id)
  : null,
)
const invoicePreviewUrl = computed(() => invoice.value
  ? invoiceService.previewUrl(customerId, opportunityId, invoice.value.id)
  : null,
)
const activeDocumentPreviewUrl = computed(() => {
  if (activeTab.value === 'quotation') return lastQuotationPreviewUrl.value
  if (activeTab.value === 'invoice') return invoicePreviewUrl.value

  return null
})
const activeDocumentDownloadUrl = computed(() => {
  if (activeTab.value === 'quotation') return lastQuotationPdfUrl.value
  if (activeTab.value === 'invoice') return invoicePdfUrl.value

  return null
})
const activeDocumentTitle = computed(() => activeTab.value === 'quotation'
  ? t('opportunities.quotation')
  : t('opportunities.invoice'),
)
const activeWhatsAppDocument = computed(() => activeTab.value === 'invoice' ? invoice.value : lastQuotation.value)

const allowedStages = computed<OpportunityStage[]>(() =>
  opportunity.value ? (STAGE_TRANSITIONS[opportunity.value.stage] ?? []) : [],
)

async function fetchData() {
  loading.value = true
  try {
    opportunity.value = await opportunityService.get(customerId, opportunityId)
    if (showQuotations.value) {
      await loadQuotations()
    }
    if (opportunity.value.stage === 'won' && showInvoices.value) {
      await loadInvoice()
    }
  } finally {
    loading.value = false
  }
}

async function loadQuotations() {
  try {
    quotations.value = await quotationService.list(customerId, opportunityId)
  } catch {
    quotations.value = []
  }
}

async function loadInvoice() {
  invoiceLoading.value = true
  try {
    invoice.value = await invoiceService.get(customerId, opportunityId)
  } catch {
    invoice.value = null
  } finally {
    invoiceLoading.value = false
  }
}

async function createInvoice() {
  invoiceLoading.value = true
  try {
    invoice.value = await invoiceService.create(customerId, opportunityId)
    showInvoicePreview.value = true
    toast.success(t('opportunities.invoiceCreated'))
  } catch {
    toast.error(t('opportunities.invoiceCreateFailed'))
  } finally {
    invoiceLoading.value = false
  }
}

function switchTab(tab: Tab) {
  activeTab.value = tab
  if (loadedTabs.has(tab)) return
  loadedTabs.add(tab)
  if (tab === 'activities') loadActivities()
  else if (tab === 'products') loadProducts()
  else if (tab === 'notes') loadNotes()
  else if (tab === 'timeline') loadTimeline()
}

// --- Stage ---
async function changeStage(stage: string) {
  if (!stage || !opportunity.value || stage === opportunity.value.stage) return
  if ((stage === 'won' || stage === 'lost') && !confirm(t('opportunities.confirmClose', { stage }))) {
    await fetchData()
    return
  }
  movingStage.value = true
  try {
    opportunity.value = await opportunityService.updateStage(customerId, opportunityId, stage)
    if (stage === 'won' && showInvoices.value) {
      await loadInvoice()
      toast.success(t('opportunities.converted'))
    } else if (stage === 'won') {
      toast.success(t('opportunities.converted'))
    }
  } catch {
    toast.error(t('opportunities.moveFailed'))
    await fetchData()
  } finally {
    movingStage.value = false
  }
}

async function handleDelete() {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await opportunityService.delete(customerId, opportunityId)
  router.push({ name: 'opportunities.index' })
}

// --- Activities ---
async function loadActivities() {
  if (activitiesLoading.value) return
  activitiesLoading.value = true
  try {
    const res = await opportunityActivityService.list(customerId, opportunityId, { per_page: 50 })
    activities.value = res.data ?? []
  } finally {
    activitiesLoading.value = false
  }
}

function openCreateActivity() {
  editingActivity.value = null
  showActivityModal.value = true
}

function openEditActivity(activity: OpportunityActivity) {
  editingActivity.value = activity
  showActivityModal.value = true
}

function onActivitySaved(saved: OpportunityActivity) {
  const idx = activities.value.findIndex(a => a.id === saved.id)
  if (idx >= 0) {
    activities.value[idx] = saved
  } else {
    activities.value.unshift(saved)
    if (opportunity.value) opportunity.value.activities_count = (opportunity.value.activities_count ?? 0) + 1
  }
  void refreshTimeline()
}

async function completeActivity(a: OpportunityActivity) {
  completingId.value = a.id
  try {
    const outcome = selectedOutcomes.value[a.id] || undefined
    const updated = await opportunityActivityService.complete(customerId, opportunityId, a.id, outcome)
    const idx = activities.value.findIndex(x => x.id === a.id)
    if (idx >= 0) activities.value[idx] = updated
    delete selectedOutcomes.value[a.id]
    await refreshTimeline()
  } finally {
    completingId.value = null
  }
}

async function deleteActivity(a: OpportunityActivity) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await opportunityActivityService.delete(customerId, opportunityId, a.id)
  activities.value = activities.value.filter(x => x.id !== a.id)
  if (opportunity.value) opportunity.value.activities_count = Math.max(0, (opportunity.value.activities_count ?? 1) - 1)
  await refreshTimeline()
}

// --- Products ---
async function reloadOpportunity() {
  opportunity.value = await opportunityService.get(customerId, opportunityId)
}

async function loadProducts() {
  if (productsLoading.value) return
  productsLoading.value = true
  try {
    products.value = await opportunityProductService.list(customerId, opportunityId)
  } finally {
    productsLoading.value = false
  }
}

async function saveProductDrafts() {
  const draftsToSave = productDrafts.value.filter(draft => draft.product_id != null)
  if (!draftsToSave.length) return

  attaching.value = true
  const savedDraftIds: number[] = []
  try {
    for (const draft of draftsToSave) {
      await opportunityProductService.attach(customerId, opportunityId, {
        product_id: draft.product_id!,
        quantity: draft.quantity,
        unit_price: draft.unit_price || undefined,
        discount_percent: draft.discount_mode === 'percent' ? (draft.discount || undefined) : null,
        discount_amount: draft.discount_mode === 'amount' ? (draft.discount || undefined) : undefined,
        notes: draft.notes || undefined,
      })
      savedDraftIds.push(draft.id)
    }

    await loadProductsRefresh()
    await reloadOpportunity()
    productDrafts.value = []
    toast.success(t('common.saveSuccess'))
  } catch {
    if (savedDraftIds.length) {
      await loadProductsRefresh()
      productDrafts.value = productDrafts.value.filter(draft => !savedDraftIds.includes(draft.id))
    }
    toast.error(t('common.somethingWentWrong'))
  } finally {
    attaching.value = false
  }
}

async function loadProductsRefresh() {
  productsLoading.value = true
  try {
    products.value = await opportunityProductService.list(customerId, opportunityId)
  } finally {
    productsLoading.value = false
  }
}

async function saveProductQuantity(p: OpportunityProduct) {
  try {
    await opportunityProductService.update(customerId, opportunityId, p.id, {
      quantity: editQuantity.value,
      unit_price: editUnitPrice.value || undefined,
      discount_percent: editDiscountMode.value === 'percent' ? (editDiscount.value || undefined) : null,
      discount_amount: editDiscountMode.value === 'amount' ? (editDiscount.value || undefined) : undefined,
    })
    await loadProductsRefresh()
    editingProductId.value = null
    await reloadOpportunity()
    toast.success(t('common.saveSuccess'))
  } catch {
    toast.error(t('common.somethingWentWrong'))
  }
}

async function detachProduct(p: OpportunityProduct) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  try {
    await opportunityProductService.detach(customerId, opportunityId, p.id)
    products.value = products.value.filter(x => x.id !== p.id)
    await reloadOpportunity()
    toast.success(t('common.deleteSuccess'))
  } catch {
    toast.error(t('common.somethingWentWrong'))
  }
}

const productsTotal = computed(() => {
  const attachedTotal = products.value.reduce((sum, product) => sum + productSubtotal(product), 0)
  const draftTotal = productDrafts.value.reduce((sum, draft) => sum + draftSubtotal(draft), 0)

  return attachedTotal + draftTotal
})

function lineDiscount(gross: number, discount: number | string | null | undefined): number {
  return Math.min(Math.max(Number(discount ?? 0) || 0, 0), gross)
}

/** Percent wins when present; otherwise the stored amount, capped at gross. */
function lineDiscountAmount(gross: number, percent: number | string | null | undefined, amount: number | string | null | undefined): number {
  const pct = percent === null || percent === undefined || percent === '' ? null : Math.min(Math.max(Number(percent) || 0, 0), 100)

  if (pct !== null && pct > 0) {
    return Math.round(gross * (pct / 100) * 100) / 100
  }

  return lineDiscount(gross, amount)
}

function productDiscountAmount(p: OpportunityProduct): number {
  const gross = Number(p.unit_price ?? 0) * (p.quantity ?? 1)

  return lineDiscountAmount(gross, p.discount_percent, p.discount_amount)
}

function productSubtotal(p: OpportunityProduct): number {
  const gross = Number(p.unit_price ?? 0) * (p.quantity ?? 1)

  return gross - productDiscountAmount(p)
}

function productTaxAmount(p: OpportunityProduct): number {
  const percent = Number(p.tax?.percent ?? 0) || 0

  return Math.round(productSubtotal(p) * (percent / 100) * 100) / 100
}

function draftSubtotal(draft: ProductDraft): number {
  if (draft.product_id == null) return 0

  const gross = Number(draft.unit_price || 0) * (draft.quantity || 0)

  return gross - (draft.discount_mode === 'percent'
    ? lineDiscountAmount(gross, draft.discount, null)
    : lineDiscount(gross, draft.discount))
}

function draftDiscountAmount(draft: ProductDraft): number {
  const gross = Number(draft.unit_price || 0) * (draft.quantity || 0)

  return draft.discount_mode === 'percent'
    ? lineDiscountAmount(gross, draft.discount, null)
    : lineDiscount(gross, draft.discount)
}

const productsDiscountTotal = computed(() => {
  const attached = products.value.reduce((sum, p) => sum + productDiscountAmount(p), 0)
  const drafts = productDrafts.value.reduce((sum, draft) => sum + draftDiscountAmount(draft), 0)

  return attached + drafts
})

const productsTaxTotal = computed(() =>
  products.value.reduce((sum, p) => sum + productTaxAmount(p), 0),
)

function defaultQuotationValidity(): string {
  const date = new Date()
  date.setDate(date.getDate() + 30)
  return date.toISOString().slice(0, 10)
}

async function createQuotation() {
  quotationLoading.value = true
  try {
    const createdQuotation = await quotationService.create(customerId, opportunityId, {
      valid_until: defaultQuotationValidity(),
    })
    quotation.value = createdQuotation
    quotations.value = [
      createdQuotation,
      ...quotations.value.filter(item => item.id !== createdQuotation.id),
    ]
    showQuotationPreview.value = true
  } catch {
    toast.error(t('opportunities.quotationCreateFailed'))
  } finally {
    quotationLoading.value = false
  }
}

async function sendQuotation() {
  if (!quotation.value || !await confirm(t('opportunities.confirmSendQuotation'))) return

  sendingQuotation.value = true
  try {
    quotation.value = await quotationService.send(customerId, opportunityId, quotation.value.id)
    toast.success(t('opportunities.quotationSent'))
  } catch {
    toast.error(t('opportunities.quotationSendFailed'))
  } finally {
    sendingQuotation.value = false
  }
}

// --- Notes ---
async function loadNotes() {
  if (notesLoading.value) return
  notesLoading.value = true
  try {
    const res = await noteService.list('opportunities', opportunityId, { per_page: 50 })
    notes.value = res.data ?? []
  } finally {
    notesLoading.value = false
  }
}

async function addNote() {
  if (!newNoteBody.value.trim()) return
  savingNote.value = true
  try {
    const created = await noteService.create('opportunities', opportunityId, { body: newNoteBody.value })
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

// --- Timeline ---
async function loadTimeline() {
  if (timelineLoading.value) return
  timelineLoading.value = true
  try {
    const res = await timelineService.forEntity('opportunities', opportunityId, { per_page: 50 })
    timelineEvents.value = res.data ?? []
  } finally {
    timelineLoading.value = false
  }
}

async function refreshTimeline() {
  if (loadedTabs.has('timeline')) await loadTimeline()
}

async function onWhatsAppSent() {
  await Promise.all([
    loadedTabs.has('activities') ? loadActivities() : Promise.resolve(),
    loadedTabs.has('timeline') ? loadTimeline() : Promise.resolve(),
  ])
}

onMounted(fetchData)
</script>

<template>
  <div>
    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <template v-else-if="opportunity">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
          <h2 class="mb-1">{{ opportunity.title }}</h2>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <BaseBadge :variant="stageVariant" pill>{{ t(`opportunities.stages.${opportunity.stage}`) }}</BaseBadge>
            <span v-if="opportunity.amount" class="fw-semibold">{{ opportunity.currency }} {{ Number(opportunity.amount).toLocaleString() }}</span>
            <button v-if="can('view-customers')" class="btn btn-link btn-sm p-0 text-decoration-none" @click="router.push({ name: 'customers.show', params: { id: opportunity.customer_id } })">
              {{ opportunity.customer?.display_name ?? `#${opportunity.customer_id}` }}
            </button>
            <span v-else class="small text-muted">
              {{ opportunity.customer?.display_name ?? `#${opportunity.customer_id}` }}
            </span>
            <span class="d-inline-flex align-items-center gap-1">
              <UserAvatar :name="opportunity.assigned_to?.name" :id="opportunity.assigned_to?.id" :size="22" />
              <small class="text-muted">{{ opportunity.assigned_to?.name ?? t('opportunities.allAssignees') }}</small>
            </span>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'opportunities.index' }">
            {{ t('common.back') }}
          </BaseButton>
          <StageDropdown
            v-if="can('update-opportunities') && allowedStages.length"
            :stages="allowedStages"
            :loading="movingStage"
            @select="changeStage"
          />
          <BaseButton
            v-if="can('update-opportunities')"
            variant="primary"
            icon="bi-pencil"
            :to="{ name: 'opportunities.edit', params: { customerId: customerId, id: opportunity.id } }"
          >
            {{ t('common.edit') }}
          </BaseButton>
          <BaseButton
            v-if="showQuotations && can('create-quotations') && !['won', 'lost'].includes(opportunity.stage)"
            variant="outline-primary"
            icon="bi-file-earmark-text"
            :loading="quotationLoading"
            @click="createQuotation"
          >
            {{ t('opportunities.createQuotation') }}
          </BaseButton>
          <BaseButton
            v-if="showInvoices && opportunity.stage === 'won' && can('create-invoices') && !invoiceLoading && !invoice"
            variant="success"
            icon="bi-receipt"
            :loading="invoiceLoading"
            @click="createInvoice"
          >
            {{ t('opportunities.createInvoice') }}
          </BaseButton>
          <BaseButton
            v-if="showInvoices && can('view-invoices') && invoice"
            variant="outline-success"
            icon="bi-receipt"
            @click="showInvoicePreview = true"
          >
            {{ t('opportunities.viewInvoice') }}
          </BaseButton>
          <BaseButton v-if="can('delete-opportunities')" variant="outline-danger" icon="bi-trash" @click="handleDelete">
            {{ t('common.delete') }}
          </BaseButton>
        </div>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'details' }" @click="switchTab('details')">
            <i class="bi bi-info-circle me-1"></i>{{ t('common.details') }}
          </button>
        </li>
        <li v-if="showQuotations && can('view-quotations') && lastQuotation && lastQuotationPreviewUrl" class="nav-item">
          <button class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'quotation' }" @click="switchTab('quotation')">
            <i class="bi bi-file-earmark-pdf me-1"></i>{{ t('opportunities.quotation') }}
          </button>
        </li>
        <li v-if="showInvoices && can('view-invoices') && invoice && invoicePreviewUrl" class="nav-item">
          <button class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'invoice' }" @click="switchTab('invoice')">
            <i class="bi bi-file-earmark-pdf me-1"></i>{{ t('opportunities.invoice') }}
          </button>
        </li>
        <li v-if="can('view-activities')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'activities' }" @click="switchTab('activities')">
            <i class="bi bi-lightning-charge me-1"></i>{{ t('customers.activities') }}
            <span v-if="opportunity.activities_count" class="badge bg-secondary ms-1">{{ opportunity.activities_count }}</span>
          </button>
        </li>
        <li v-if="can('view-products')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'products' }" @click="switchTab('products')">
            <i class="bi bi-box-seam me-1"></i>{{ productPlural }}
            <span v-if="products.length" class="badge bg-secondary ms-1">{{ products.length }}</span>
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

      <div v-if="activeTab === 'quotation' || activeTab === 'invoice'" class="document-preview">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">{{ activeDocumentTitle }}</h5>
          <div class="d-flex gap-2">
            <BaseButton
              v-if="can('create-activities') && activeWhatsAppDocument && (activeTab === 'invoice' || can('send-quotations'))"
              variant="outline-success"
              size="sm"
              icon="bi-whatsapp"
              @click="whatsAppEntityType = activeTab === 'invoice' ? 'invoice' : 'quotation'; showWhatsAppModal = true"
            >
              {{ t('whatsapp.send') }}
            </BaseButton>
            <a
              v-if="activeDocumentDownloadUrl"
              :href="activeDocumentDownloadUrl"
              class="btn btn-outline-primary btn-sm"
              download
            >
              <i class="bi bi-download me-1"></i>{{ t('common.download') }}
            </a>
          </div>
        </div>
        <iframe
          v-if="activeDocumentPreviewUrl"
          :src="activeDocumentPreviewUrl"
          :title="activeDocumentTitle"
          class="document-preview__frame"
        ></iframe>
      </div>

      <!-- DETAILS -->
      <div v-show="activeTab === 'details'">
        <BaseCard :title="t('common.details')">
          <div class="row g-3">
            <div class="col-sm-6">
              <small class="text-muted d-block">{{ t('opportunities.amount') }}</small>
              <span>{{ opportunity.amount ? `${opportunity.currency} ${Number(opportunity.amount).toLocaleString()}` : '—' }}</span>
            </div>
            <div class="col-sm-6">
              <small class="text-muted d-block">Probability</small>
              <span>{{ opportunity.probability != null ? `${opportunity.probability}%` : '—' }}</span>
            </div>
            <div class="col-sm-6">
              <small class="text-muted d-block">{{ t('customers.expectedCloseDate') }}</small>
              <span>{{ formatShortDate(opportunity.expected_close_date) }}</span>
            </div>
            <div class="col-sm-6">
              <small class="text-muted d-block">{{ t('customers.contractStart') }} – {{ t('customers.contractEnd') }}</small>
              <span>{{ contractPeriod }}</span>
            </div>
            <div class="col-sm-6">
              <small class="text-muted d-block">{{ t('opportunities.assignee') }}</small>
              <span>{{ opportunity.assigned_to?.name ?? '—' }}</span>
            </div>
            <div v-if="opportunity.notes" class="col-12">
              <small class="text-muted d-block">{{ t('common.notes') }}</small>
              <span style="white-space: pre-wrap">{{ opportunity.notes }}</span>
            </div>
            <div v-for="definition in customFields.definitions.value" :key="definition.id" class="col-sm-6">
              <small class="text-muted d-block">{{ definition.label }}</small>
              <CustomFieldValue :definition="definition" :value="opportunity.custom_fields?.[definition.name]" />
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- ACTIVITIES -->
      <div v-show="activeTab === 'activities'">
        <BaseCard>
          <template #header-actions>
            <BaseButton
              v-if="can('update-opportunities')"
              variant="outline-primary" size="sm" icon="bi-plus-lg"
              @click="openCreateActivity"
            >
              {{ t('customers.logActivity') }}
            </BaseButton>
          </template>

          <div v-if="activitiesLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="!activities.length" class="text-muted text-center py-4">{{ t('common.noData') }}</div>
          <ul v-else class="activity-list">
            <li v-for="a in activities" :key="a.id" class="activity-item">
              <div class="activity-marker" :class="`activity-marker--${a.type}`">
                <span>
                  <i :class="['bi', ACTIVITY_TYPE_ICONS[a.type]]"></i>
                </span>
              </div>
              <div class="activity-content">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <div class="d-flex align-items-center flex-wrap gap-1">
                    <span class="fw-semibold">{{ a.subject }}</span>
                    <span class="badge ms-1" :class="a.is_completed ? 'bg-success' : a.is_overdue ? 'bg-danger' : 'bg-secondary'">
                      {{ a.is_completed ? t('customers.markComplete') : a.is_overdue ? t('activities.statuses.overdue') : t('tasks.statuses.pending') }}
                    </span>
                    <span v-if="a.channel" class="badge ms-1 text-bg-info">
                      {{ t(`customers.activityChannels.${a.channel}`) }}
                    </span>
                    <span v-if="a.outcome" class="badge ms-1" :class="`text-bg-${OUTCOME_VARIANTS[a.outcome]}`">
                      {{ t(`customers.activityOutcomes.${a.outcome}`) }}
                    </span>
                    <span v-if="!a.is_completed && a.reminder_at" class="badge ms-1 text-bg-light border" :title="t('activities.reminder')">
                      <i class="bi bi-bell me-1"></i>{{ new Date(a.reminder_at).toLocaleString() }}
                    </span>
                  </div>
                  <div class="d-flex gap-1 flex-shrink-0 align-items-center">
                    <!-- Complete with outcome -->
                    <template v-if="!a.is_completed && can('update-opportunities')">
                      <ActivityOutcomePicker
                        v-model="selectedOutcomes[a.id]"
                        :disabled="completingId === a.id"
                        :loading="completingId === a.id"
                        @complete="completeActivity(a)"
                      />
                    </template>
                    <button
                      v-if="can('update-opportunities')"
                      type="button"
                      class="btn btn-sm btn-outline-secondary"
                      :title="t('common.edit')"
                      @click="openEditActivity(a)"
                    >
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button
                      v-if="can('update-opportunities')"
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      @click="deleteActivity(a)"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
                <div v-if="a.description" class="text-muted small mt-1">{{ a.description }}</div>
                <div class="text-muted small mt-1 d-flex gap-3 flex-wrap">
                  <span><i class="bi bi-person me-1"></i>{{ a.user?.name }}</span>
                  <span v-if="a.due_date"><i class="bi bi-calendar me-1"></i>{{ new Date(a.due_date).toLocaleString() }}</span>
                  <span v-if="a.completed_at"><i class="bi bi-check-circle me-1"></i>{{ formatDateTime(a.completed_at) }}</span>
                </div>
              </div>
            </li>
          </ul>
        </BaseCard>

        <OpportunityActivityModal
          v-model="showActivityModal"
          :customer-id="customerId"
          :opportunity-id="opportunityId"
          :activity="editingActivity"
          @saved="onActivitySaved"
        />
      </div>

      <!-- PRODUCTS -->
      <div v-show="activeTab === 'products'">
        <BaseCard>
          <div v-if="productsLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="products.length || productDrafts.length" class="table-responsive products-invoice-table-wrap">
            <table class="table align-middle mb-0 products-invoice-table">
              <thead>
                <tr>
                  <th style="min-width: 260px;">{{ t('products.name') }}</th>
                  <th class="text-end" style="width: 150px;">{{ t('products.unitPrice') }}</th>
                  <th class="text-center" style="width: 110px;">{{ t('products.quantity') }}</th>
                  <th class="text-end" style="width: 130px;">{{ t('products.discount') }}</th>
                  <th class="text-end" style="width: 130px;">{{ t('products.tax') }}</th>
                  <th class="text-end" style="width: 160px;">{{ t('products.subtotal') }}</th>
                  <th style="width: 72px;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in products" :key="p.id" class="products-invoice-line">
                  <td>
                    <strong class="products-invoice-product-name">{{ p.name }}<router-link
                      v-if="can('view-products')"
                      :to="{ name: 'products.show', params: { id: p.id } }"
                      target="_blank"
                      class="ms-1 text-decoration-none"
                      :aria-label="p.name"
                      @click.stop
                    ><i class="bi bi-box-arrow-up-right small"></i></router-link></strong>
                    <small v-if="p.sku" class="products-invoice-meta">{{ p.sku }}</small>
                    <small v-if="p.tax" class="products-invoice-meta">{{ p.tax.name }} <span dir="ltr">({{ p.tax.percent }}%)</span></small>
                    <small v-if="p.notes" class="products-invoice-meta">{{ p.notes }}</small>
                  </td>
                  <td class="text-end">
                    <span v-if="editingProductId !== p.id">{{ Number(p.unit_price).toLocaleString() }}</span>
                    <input v-else v-model="editUnitPrice" type="number" min="0" step="0.01" class="form-control form-control-sm text-end" />
                  </td>
                  <td class="text-center">
                    <span v-if="editingProductId !== p.id">{{ p.quantity }}</span>
                    <input v-else v-model.number="editQuantity" type="number" min="1" class="form-control form-control-sm" />
                  </td>
                  <td class="text-end">
                    <span v-if="editingProductId !== p.id">
                      {{ Number(p.discount_amount ?? 0).toLocaleString() }}
                      <small v-if="p.discount_percent" class="text-muted" dir="ltr">({{ Number(p.discount_percent).toLocaleString() }}%)</small>
                    </span>
                    <div v-else class="d-flex gap-1 discount-toggle">
                      <input v-model="editDiscount" type="number" min="0" :max="editDiscountMode === 'percent' ? 100 : undefined" step="0.01" class="form-control form-control-sm text-end" :aria-label="t('products.discount')" />
                      <button type="button" class="btn btn-sm btn-outline-secondary" :class="{ active: editDiscountMode === 'percent' }" :aria-pressed="editDiscountMode === 'percent'" :title="t('products.discountPercent')" :aria-label="t('products.discountPercent')" @click="editDiscountMode = editDiscountMode === 'percent' ? 'amount' : 'percent'">%</button>
                    </div>
                  </td>
                  <td class="text-end text-muted">{{ productTaxAmount(p).toLocaleString() }}</td>
                  <td class="text-end fw-semibold">{{ productSubtotal(p).toLocaleString() }}</td>
                  <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                      <template v-if="editingProductId === p.id">
                        <BaseButton variant="success" size="sm" icon="bi-check-lg" @click="saveProductQuantity(p)" />
                        <BaseButton variant="secondary" size="sm" icon="bi-x-lg" @click="editingProductId = null" />
                      </template>
                      <template v-else-if="can('update-opportunities')">
                        <BaseButton variant="outline-primary" size="sm" icon="bi-pencil" @click="editingProductId = p.id; editQuantity = p.quantity; editUnitPrice = String(p.unit_price); editDiscountMode = p.discount_percent ? 'percent' : 'amount'; editDiscount = String(p.discount_percent ?? p.discount_amount ?? 0)" />
                        <BaseButton variant="outline-danger" size="sm" icon="bi-trash" @click="detachProduct(p)" />
                      </template>
                    </div>
                  </td>
                </tr>
                <tr v-for="draft in productDrafts" :key="`draft-${draft.id}`" class="products-invoice-line products-invoice-line--draft">
                  <td>
                    <ProductPicker
                      :model-value="draft.product_id"
                      :show-price="false"
                      :exclude-ids="excludedProductIds"
                      :teleport-dropdown="true"
                      :disabled="attaching"
                      @update:model-value="draft.product_id = $event"
                      @select="onProductDraftPick(draft, $event)"
                    />
                    <input
                      v-model="draft.notes"
                      class="form-control form-control-sm products-invoice-notes mt-2"
                      :placeholder="t('common.notes')"
                      :disabled="attaching"
                    />
                  </td>
                  <td>
                    <input v-model="draft.unit_price" type="number" min="0" step="0.01" class="form-control form-control-sm text-end" :disabled="attaching" :aria-label="t('products.unitPrice')" />
                  </td>
                  <td>
                    <input v-model.number="draft.quantity" type="number" min="1" class="form-control form-control-sm text-center" :disabled="attaching" :aria-label="t('products.quantity')" />
                  </td>
                  <td>
                    <div class="d-flex gap-1 discount-toggle">
                      <input v-model="draft.discount" type="number" min="0" :max="draft.discount_mode === 'percent' ? 100 : undefined" step="0.01" class="form-control form-control-sm text-end" :disabled="attaching" :aria-label="t('products.discount')" />
                      <button type="button" class="btn btn-sm btn-outline-secondary" :class="{ active: draft.discount_mode === 'percent' }" :disabled="attaching" :aria-pressed="draft.discount_mode === 'percent'" :title="t('products.discountPercent')" :aria-label="t('products.discountPercent')" @click="draft.discount_mode = draft.discount_mode === 'percent' ? 'amount' : 'percent'">%</button>
                    </div>
                  </td>
                  <td></td>
                  <td class="text-end fw-semibold">{{ draftSubtotal(draft).toLocaleString() }}</td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-light text-danger" :disabled="attaching" :aria-label="t('common.delete')" @click="removeProductDraft(draft.id)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="products-invoice-footer" :class="{ 'products-invoice-footer--with-lines': products.length || productDrafts.length }">
            <div v-if="can('update-opportunities')" class="products-invoice-actions">
              <BaseButton variant="outline-primary" icon="bi-plus-lg" :disabled="attaching" @click="addProductDraft">
                {{ t('products.addLine') }}
              </BaseButton>
              <BaseButton
                v-if="productDrafts.length"
                variant="primary"
                icon="bi-save"
                :loading="attaching"
                :disabled="!productDrafts.some(draft => draft.product_id != null)"
                @click="saveProductDrafts"
              >
                {{ t('common.save') }}
              </BaseButton>
            </div>
            <div class="products-invoice-total">
              <span>{{ t('products.subtotal') }}</span>
              <strong>{{ opportunity.currency }} {{ (productsTotal + productsDiscountTotal).toLocaleString() }}</strong>
            </div>
            <div v-if="productsDiscountTotal" class="products-invoice-total">
              <span>{{ t('products.discount') }}</span>
              <strong>−{{ opportunity.currency }} {{ productsDiscountTotal.toLocaleString() }}</strong>
            </div>
            <div v-if="productsTaxTotal" class="products-invoice-total">
              <span>{{ t('products.tax') }}</span>
              <strong>{{ opportunity.currency }} {{ productsTaxTotal.toLocaleString() }}</strong>
            </div>
            <div class="products-invoice-total">
              <span>{{ t('common.total') }}</span>
              <strong>{{ opportunity.currency }} {{ (productsTotal + productsTaxTotal).toLocaleString() }}</strong>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- NOTES -->
      <div v-show="activeTab === 'notes'">
        <BaseCard>
          <div v-if="can('view-notes')" class="note-composer note-composer--inline mb-4">
            <input v-model="newNoteBody" class="form-control note-composer__input" :placeholder="t('customers.addNote')" @keyup.enter="addNote" />
            <BaseButton variant="primary" size="sm" :loading="savingNote" @click="addNote">{{ t('common.add') }}</BaseButton>
          </div>
          <div v-if="notesLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="!notes.length" class="text-muted text-center py-4">{{ t('common.noData') }}</div>
          <div v-else class="notes-list">
            <div v-for="n in notes" :key="n.id" class="note-item" :class="{ 'note-item--pinned': n.is_pinned }">
              <div class="note-header">
                <div class="note-author-block">
                  <span class="note-avatar"><i class="bi bi-person"></i></span>
                  <span class="note-author">{{ n.author?.name }}</span>
                  <time class="note-date">{{ new Date(n.created_at).toLocaleString() }}</time>
                  <span v-if="n.is_pinned" class="note-pinned"><i class="bi bi-pin-angle-fill me-1"></i>Pinned</span>
                </div>
                <div class="note-actions">
                  <BaseButton v-if="can('update-opportunities')" variant="outline-secondary" size="sm" icon="bi-pin" :title="n.is_pinned ? 'Unpin' : 'Pin'" @click="togglePin(n)" />
                  <BaseButton v-if="n.author?.id === auth.user?.id || can('delete-opportunities')" variant="outline-danger" size="sm" icon="bi-trash" title="Delete" @click="deleteNote(n)" />
                </div>
              </div>
              <p class="note-body">{{ n.body }}</p>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- TIMELINE -->
      <div v-show="activeTab === 'timeline'">
        <BaseCard>
          <div v-if="timelineLoading" class="text-center py-4"><BaseSpinner /></div>
          <div v-else-if="!timelineEvents.length" class="text-muted text-center py-4">{{ t('common.noData') }}</div>
          <ul v-else class="timeline-list">
            <li v-for="e in timelineEvents" :key="e.id" class="timeline-item">
              <div class="timeline-marker" :class="`timeline-marker--${e.type}`">
                <i class="bi bi-clock-history"></i>
              </div>
              <div class="timeline-content">
                <div class="timeline-heading">
                  <div class="timeline-title">{{ e.title }}</div>
                  <time class="timeline-time">{{ new Date(e.timestamp).toLocaleString() }}</time>
                </div>
                <div v-if="e.description" class="timeline-description">{{ e.description }}</div>
                <div class="timeline-meta">
                  <span><i class="bi bi-person me-1"></i>{{ e.user?.name }}</span>
                  <span class="timeline-type">{{ e.type }}</span>
                </div>
              </div>
            </li>
          </ul>
        </BaseCard>
      </div>

      <!-- ATTACHMENTS -->
      <div v-if="activeTab === 'attachments'">
        <BaseCard :title="t('roles.moduleLabels.attachments')">
          <AttachmentList entity-type="opportunities" :entity-id="opportunityId" :can-upload="can('create-attachments')" @uploaded="refreshTimeline" @deleted="refreshTimeline" />
        </BaseCard>
      </div>
    </template>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />

    <WhatsAppSendModal
      v-if="opportunity"
      v-model="showWhatsAppModal"
      :entity-type="whatsAppEntityType"
      :customer-id="customerId"
      :opportunity-id="opportunityId"
      :quotation-id="whatsAppEntityType === 'quotation' ? (activeWhatsAppDocument?.id ?? lastQuotation?.id) : undefined"
      :invoice-id="whatsAppEntityType === 'invoice' ? invoice?.id : undefined"
      @sent="onWhatsAppSent"
    />

    <BaseModal v-model="showQuotationPreview" :title="t('opportunities.quotationPreview')" size="xl" scrollable>
      <div v-if="quotation" class="quotation-preview">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
          <div>
            <h3 class="mb-1">{{ t('opportunities.quotation') }}</h3>
            <div class="text-muted">{{ quotation.quotation_number }}</div>
          </div>
          <div class="text-end">
            <small class="text-muted">{{ t('opportunities.validUntil') }}: {{ quotation.valid_until }}</small>
          </div>
        </div>
        <DocumentParties :company="quotation.company" :buyer="quotation.customer" />
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>{{ t('products.name') }}</th>
                <th>{{ t('products.sku') }}</th>
                <th class="text-end">{{ t('products.quantity') }}</th>
                <th class="text-end">{{ t('products.unitPrice') }}</th>
                <th class="text-end">{{ t('products.discount') }}</th>
                <th class="text-end">{{ t('products.tax') }}</th>
                <th class="text-end">{{ t('products.subtotal') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in quotation.items" :key="item.id">
                <td>{{ item.product_name }}</td>
                <td>{{ item.sku || '—' }}</td>
                <td class="text-end">{{ item.quantity }}</td>
                <td class="text-end">{{ quotation.currency }} {{ Number(item.unit_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                <td class="text-end">{{ quotation.currency }} {{ Number(item.discount_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}<span v-if="item.discount_percent" dir="ltr"> ({{ item.discount_percent }}%)</span></td>
                <td class="text-end">{{ item.tax_name || '—' }}<span v-if="item.tax_name" dir="ltr"> ({{ item.tax_percent }}%)</span></td>
                <td class="text-end">{{ quotation.currency }} {{ Number(item.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="ms-auto quotation-preview__totals">
          <div><span>{{ t('products.subtotal') }}</span><strong>{{ quotation.currency }} {{ Number(quotation.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
          <div><span>{{ t('opportunities.discount') }}</span><strong>{{ quotation.currency }} {{ Number(quotation.discount_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
          <div><span>{{ t('opportunities.tax') }}</span><strong>{{ quotation.currency }} {{ Number(quotation.tax_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
          <div class="border-top pt-2 mt-2 fs-5"><span>{{ t('common.total') }}</span><strong>{{ quotation.currency }} {{ Number(quotation.total).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
        </div>
        <div v-if="quotation.notes" class="mt-4"><strong>{{ t('opportunities.quotationNotes') }}</strong><p class="mb-0">{{ quotation.notes }}</p></div>
        <div class="mt-3"><strong>{{ t('opportunities.quotationTerms') }}</strong><p class="mb-0">{{ quotation.terms ?? t('opportunities.quotationDefaultTerms') }}</p></div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="showQuotationPreview = false">{{ t('common.close') }}</BaseButton>
        <BaseButton v-if="can('send-quotations') && quotation?.status !== 'sent'" variant="primary" icon="bi-send" :loading="sendingQuotation" @click="sendQuotation">
          {{ t('opportunities.sendQuotation') }}
        </BaseButton>
        <span v-else class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>{{ t('opportunities.quotationSent') }}</span>
      </template>
    </BaseModal>

    <BaseModal v-model="showInvoicePreview" :title="t('opportunities.invoicePreview')" size="xl" scrollable>
      <div v-if="invoice" class="quotation-preview">
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
          <div>
            <h3 class="mb-1">{{ t('opportunities.invoice') }}</h3>
            <div class="text-muted">{{ invoice.invoice_number }}</div>
          </div>
          <div class="text-end">
            <div class="small text-muted">{{ t('opportunities.issuedAt') }}: {{ invoice.issued_at }}</div>
            <small v-if="invoice.quotation_number" class="text-muted">{{ t('opportunities.basedOnQuotation') }}: {{ invoice.quotation_number }}</small>
          </div>
        </div>
        <DocumentParties :company="invoice.company" :buyer="invoice.customer" />
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>{{ t('products.name') }}</th><th>{{ t('products.sku') }}</th><th class="text-end">{{ t('products.quantity') }}</th><th class="text-end">{{ t('products.unitPrice') }}</th><th class="text-end">{{ t('products.discount') }}</th><th class="text-end">{{ t('products.tax') }}</th><th class="text-end">{{ t('products.subtotal') }}</th></tr></thead>
            <tbody>
              <tr v-for="item in invoice.items" :key="item.id">
                <td>{{ item.product_name }}</td><td>{{ item.sku || '—' }}</td><td class="text-end">{{ item.quantity }}</td>
                <td class="text-end">{{ invoice.currency }} {{ Number(item.unit_price).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                <td class="text-end">{{ invoice.currency }} {{ Number(item.discount_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}<span v-if="item.discount_percent" dir="ltr"> ({{ item.discount_percent }}%)</span></td>
                <td class="text-end">{{ item.tax_name || '—' }}<span v-if="item.tax_name" dir="ltr"> ({{ item.tax_percent }}%)</span></td>
                <td class="text-end">{{ invoice.currency }} {{ Number(item.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="ms-auto quotation-preview__totals">
          <div><span>{{ t('products.subtotal') }}</span><strong>{{ invoice.currency }} {{ Number(invoice.subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
          <div><span>{{ t('opportunities.discount') }}</span><strong>{{ invoice.currency }} {{ Number(invoice.discount_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
          <div><span>{{ t('opportunities.tax') }}</span><strong>{{ invoice.currency }} {{ Number(invoice.tax_amount).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
          <div class="border-top pt-2 mt-2 fs-5"><span>{{ t('common.total') }}</span><strong>{{ invoice.currency }} {{ Number(invoice.total).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</strong></div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="showInvoicePreview = false">{{ t('common.close') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.document-preview {
  min-height: 70vh;
}

.document-preview__frame {
  width: 100%;
  min-height: 70vh;
  border: 1px solid var(--bs-border-color);
  border-radius: 0.65rem;
  background: var(--bs-body-bg);
}

.quotation-preview__totals {
  width: min(100%, 360px);
  margin-top: 1rem;
}

.quotation-preview__totals > div {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.25rem 0;
}

.products-invoice-table-wrap {
  border: 1px solid var(--bs-border-color);
  border-radius: 0.65rem;
}

.products-invoice-table {
  min-width: 980px;
}

.products-invoice-table thead th {
  padding: 0.85rem 0.75rem;
  border-bottom: 1px solid var(--bs-border-color);
  background: var(--bs-tertiary-bg);
  color: var(--bs-secondary-color);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.045em;
  text-transform: uppercase;
  white-space: nowrap;
}

.products-invoice-table tbody td {
  padding: 0.9rem 0.75rem;
}

.products-invoice-table tbody tr:last-child td {
  border-bottom: 0;
}

/* Discount %/amount toggle: plain flex (not input-group) so it renders
   identically in LTR and RTL — Bootstrap's input-group uses physical
   LTR-only radius/margins that break under dir="rtl". */
.discount-toggle > .form-control {
  min-width: 0;
}

.discount-toggle > .btn {
  flex-shrink: 0;
}

.products-invoice-line--draft td {
  background: rgba(var(--bs-primary-rgb), 0.035);
}

.products-invoice-product-name {
  display: block;
}

.products-invoice-meta {
  display: block;
  margin-top: 0.2rem;
  color: var(--bs-secondary-color);
}

.products-invoice-notes {
  width: 100%;
}

.products-invoice-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 1rem;
}

.products-invoice-footer--with-lines {
  margin-top: 1rem;
  border-top: 1px solid var(--bs-border-color);
}

.products-invoice-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.products-invoice-total {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.1rem;
  color: var(--bs-secondary-color);
  font-size: 0.875rem;
}

.products-invoice-total strong {
  color: var(--bs-emphasis-color);
  font-size: 1.35rem;
  font-weight: 700;
}

@media (max-width: 575.98px) {
  .products-invoice-footer {
    align-items: stretch;
    flex-direction: column-reverse;
  }

  .products-invoice-total {
    align-items: flex-start;
  }
}
</style>
