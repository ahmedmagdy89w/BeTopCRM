<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { usePermission } from '@/composables/usePermission'
import { useFeatures } from '@/composables/useFeatures'
import { invoiceService, type InvoiceListFilters } from '@/services/invoiceService'
import { quotationService, type QuotationListFilters } from '@/services/quotationService'
import type { SalesDocument } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import FilterControl from '@/components/shared/FilterControl.vue'

type DocumentType = 'quotation' | 'invoice'

const { t } = useI18n()
const router = useRouter()
const { can, canAny } = usePermission()
const { showQuotations, showInvoices } = useFeatures()

const canViewQuotations = computed(() => showQuotations.value && can('view-quotations'))
const canViewInvoices = computed(() => showInvoices.value && can('view-invoices'))
const activeDocumentType = ref<DocumentType>(canViewQuotations.value ? 'quotation' : 'invoice')
const statusFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const previewDocument = ref<SalesDocument | null>(null)
const showPreview = ref(false)

const columns = computed<DataTableColumn[]>(() => [
  { key: 'document_number', label: t('salesDocuments.number'), sortable: false },
  { key: 'customer.display_name', label: t('common.name'), sortable: false },
  { key: 'opportunity.title', label: t('opportunities.title'), sortable: false },
  { key: 'status', label: t('salesDocuments.status'), sortable: true },
  { key: 'total', label: t('common.total'), sortable: true, align: 'end' },
  { key: 'document_date', label: t('salesDocuments.date'), sortable: false },
])

const statusOptions = computed(() => activeDocumentType.value === 'quotation'
  ? ['draft', 'sent', 'accepted', 'rejected', 'expired']
  : ['issued', 'paid', 'cancelled'])

const {
  rows,
  loading,
  total,
  currentPage,
  perPage,
  sortBy,
  sortDir,
  filters,
  onSearch,
  onFilter,
  refresh,
} = useDataTable<SalesDocument>(
  (params) => activeDocumentType.value === 'quotation'
    ? quotationService.listGlobal(params as QuotationListFilters)
    : invoiceService.listGlobal(params as InvoiceListFilters),
  { defaultSort: 'created_at', defaultSortDir: 'desc', immediate: canAny('view-quotations', 'view-invoices') },
)

function switchDocumentType(type: DocumentType) {
  if (activeDocumentType.value === type) {
    return
  }

  if (type === 'quotation' && !canViewQuotations.value) {
    return
  }

  if (type === 'invoice' && !canViewInvoices.value) {
    return
  }

  const hadFilters = Object.keys(filters.value).length > 0 || currentPage.value !== 1
  activeDocumentType.value = type
  statusFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  filters.value = {}
  currentPage.value = 1

  if (!hadFilters) {
    void refresh()
  }
}

function updateStatusFilter(value: unknown): void {
  statusFilter.value = typeof value === 'string' ? value : ''
  onFilter('status', statusFilter.value || undefined)
}

watch([canViewQuotations, canViewInvoices], () => {
  if (activeDocumentType.value === 'quotation' && !canViewQuotations.value && canViewInvoices.value) {
    switchDocumentType('invoice')
  } else if (activeDocumentType.value === 'invoice' && !canViewInvoices.value && canViewQuotations.value) {
    switchDocumentType('quotation')
  }
})

onMounted(() => {
  if (activeDocumentType.value === 'quotation' && !canViewQuotations.value && canViewInvoices.value) {
    activeDocumentType.value = 'invoice'
    void refresh()
  }
})

function updateDateFilter(key: 'date_from' | 'date_to', value: unknown): void {
  const date = typeof value === 'string' ? value : ''

  if (key === 'date_from') {
    dateFrom.value = date
  } else {
    dateTo.value = date
  }

  onFilter(key, date || undefined)
}

function documentNumber(document: SalesDocument): string {
  return document.document_type === 'quotation' ? document.quotation_number : document.invoice_number
}

function documentDate(document: SalesDocument): string {
  return document.document_type === 'quotation' ? document.valid_until : document.issued_at
}

function formatDate(value?: string | null): string {
  return value ? new Date(value).toLocaleDateString() : '—'
}

function formatTotal(document: SalesDocument): string {
  return `${document.currency} ${Number(document.total).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`
}

function statusVariant(status: string): 'secondary' | 'info' | 'success' | 'danger' | 'warning' {
  const variants: Record<string, 'secondary' | 'info' | 'success' | 'danger' | 'warning'> = {
    draft: 'secondary',
    sent: 'info',
    accepted: 'success',
    rejected: 'danger',
    expired: 'warning',
    issued: 'info',
    paid: 'success',
    cancelled: 'danger',
  }

  return variants[status] ?? 'secondary'
}

function statusLabel(status: string): string {
  return t(`salesDocuments.statuses.${status}`)
}

function openPreview(document: SalesDocument) {
  previewDocument.value = document
  showPreview.value = true
}

const previewTitle = computed(() => {
  if (!previewDocument.value) {
    return ''
  }

  const typeLabel = t(previewDocument.value.document_type === 'quotation'
    ? 'salesDocuments.quotations'
    : 'salesDocuments.invoices')

  return `${typeLabel} — ${documentNumber(previewDocument.value)}`
})

const previewUrl = computed(() => {
  if (!previewDocument.value) {
    return ''
  }

  const document = previewDocument.value

  return document.document_type === 'quotation'
    ? quotationService.previewUrl(document.customer_id, document.opportunity_id, document.id)
    : invoiceService.previewUrl(document.customer_id, document.opportunity_id, document.id)
})

function downloadUrl(document: SalesDocument): string {
  return document.document_type === 'quotation'
    ? quotationService.pdfUrl(document.customer_id, document.opportunity_id, document.id)
    : invoiceService.pdfUrl(document.customer_id, document.opportunity_id, document.id)
}

function goToOpportunity(row: unknown) {
  if (!can('view-opportunities')) return
  const document = row as SalesDocument

  router.push({
    name: 'opportunities.show',
    params: { customerId: document.customer_id, id: document.opportunity_id },
  })
}
</script>

<template>
  <div class="sales-documents-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <h2 class="mb-0">{{ t('salesDocuments.title') }}</h2>
    </div>

    <ul class="nav nav-pills mb-3" role="tablist">
      <li v-if="canViewQuotations" class="nav-item">
        <button
          type="button"
          class="nav-link"
          :class="{ active: activeDocumentType === 'quotation' }"
          @click="switchDocumentType('quotation')"
        >
          <i class="bi bi-file-earmark-text me-1"></i>
          {{ t('salesDocuments.quotations') }}
        </button>
      </li>
      <li v-if="canViewInvoices" class="nav-item">
        <button
          type="button"
          class="nav-link"
          :class="{ active: activeDocumentType === 'invoice' }"
          @click="switchDocumentType('invoice')"
        >
          <i class="bi bi-receipt me-1"></i>
          {{ t('salesDocuments.invoices') }}
        </button>
      </li>
    </ul>

    <BaseDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :total="total"
      :current-page="currentPage"
      :per-page="perPage"
      :sort-by="sortBy"
      :sort-dir="sortDir"
      @update:current-page="currentPage = $event"
      @update:per-page="perPage = $event"
      @update:sort-by="sortBy = $event"
      @update:sort-dir="sortDir = $event"
      @search="onSearch"
      @row-click="goToOpportunity"
    >
      <template #filters>
        <FilterControl
          :label="t('salesDocuments.status')"
          :model-value="statusFilter || undefined"
          :options="statusOptions.map(status => ({ label: statusLabel(status), value: status }))"
          @update:model-value="updateStatusFilter"
        />
        <FilterControl
          :label="t('salesDocuments.fromDate')"
          type="date"
          :model-value="dateFrom || undefined"
          @update:model-value="updateDateFilter('date_from', $event)"
        />
        <FilterControl
          :label="t('salesDocuments.toDate')"
          type="date"
          :model-value="dateTo || undefined"
          @update:model-value="updateDateFilter('date_to', $event)"
        />
      </template>

      <template #cell-document_number="{ row }">
        <div class="d-flex align-items-center gap-2">
          <i
            :class="[
              'bi',
              (row as SalesDocument).document_type === 'quotation' ? 'bi-file-earmark-text' : 'bi-receipt',
            ]"
          ></i>
          <span>{{ documentNumber(row as SalesDocument) }}</span>
        </div>
      </template>

      <template #cell-status="{ row }">
        <BaseBadge :variant="statusVariant((row as SalesDocument).status)" pill>
          {{ statusLabel((row as SalesDocument).status) }}
        </BaseBadge>
      </template>

      <template #cell-total="{ row }">
        {{ formatTotal(row as SalesDocument) }}
      </template>

      <template #cell-document_date="{ row }">
        {{ formatDate(documentDate(row as SalesDocument)) }}
      </template>

      <template #row-actions="{ row }">
        <div class="d-flex justify-content-end gap-1">
          <BaseButton
            variant="outline-primary"
            size="sm"
            icon="bi-eye"
            :aria-label="t('salesDocuments.preview')"
            @click.stop="openPreview(row as SalesDocument)"
          />
          <a
            class="btn btn-outline-secondary btn-sm"
            :href="downloadUrl(row as SalesDocument)"
            :download="documentNumber(row as SalesDocument)"
            :aria-label="t('common.download')"
            @click.stop
          >
            <i class="bi bi-download"></i>
          </a>
        </div>
      </template>
    </BaseDataTable>

    <BaseModal v-model="showPreview" :title="previewTitle" size="xl">
      <div v-if="previewUrl" class="document-preview">
        <iframe :src="previewUrl" :title="previewTitle"></iframe>
      </div>
      <template #footer>
        <div class="d-flex justify-content-between align-items-center w-100">
          <a
            v-if="previewDocument"
            class="btn btn-primary"
            :href="downloadUrl(previewDocument)"
            :download="documentNumber(previewDocument)"
          >
            <i class="bi bi-download me-1"></i>
            {{ t('common.download') }}
          </a>
          <BaseButton variant="secondary" @click="showPreview = false">
            {{ t('common.close') }}
          </BaseButton>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.document-preview {
  height: 70vh;
  min-height: 500px;
}

.document-preview iframe {
  width: 100%;
  height: 100%;
  border: 0;
}
</style>
