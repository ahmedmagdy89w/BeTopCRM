<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import CustomerPicker from '@/components/shared/CustomerPicker.vue'
import UserPicker from '@/components/shared/UserPicker.vue'
import { asyncJobService } from '@/services/asyncJobService'
import { reportService, type ExportFormat, type LeadPipelineFilters } from '@/services/reportService'
import type { LeadPipelineReport } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { error: showError } = useToast()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()
const report = ref<LeadPipelineReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const downloadingExport = ref(false)
const exportDownloadJobId = ref<number | null>(null)
const exportFormat = ref<ExportFormat>('xlsx')
const { dateFrom, dateTo } = useDefaultDateRange()
const ownerId = ref<number | null>(null)
const customerId = ref<number | null>(null)
const status = ref('')
const source = ref('')
const minScore = ref<number | undefined>()

function buildParams(): LeadPipelineFilters {
  const params: LeadPipelineFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (status.value) params.status = status.value
  if (ownerId.value != null) params.assigned_to = ownerId.value
  if (customerId.value != null) params.customer_id = customerId.value
  if (source.value.trim()) params.source = source.value.trim()
  if (minScore.value !== undefined) params.min_score = minScore.value
  return params
}

async function fetchReport() {
  loading.value = true
  try {
    report.value = await reportService.leadPipeline(buildParams())
  } catch {
    showError('common.error')
  } finally {
    loading.value = false
  }
}

async function downloadExport() {
  exporting.value = true
  try {
    await reportService.exportLeadPipeline(buildParams(), exportFormat.value)
  } catch {
    showError('common.error')
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.leadPipelineAsync(buildParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        report.value = job.result as LeadPipelineReport
      }
    },
  )
}

function exportAsync() {
  exportDownloadJobId.value = null
  startExportAsync(
    () => reportService.exportLeadPipelineAsync(buildParams(), exportFormat.value),
    (job) => { exportDownloadJobId.value = job.id },
  )
}

async function downloadAsyncExport() {
  if (!exportDownloadJobId.value) return
  downloadingExport.value = true
  try {
    await asyncJobService.download(exportDownloadJobId.value)
  } catch {
    showError('common.error')
  } finally {
    downloadingExport.value = false
  }
}

function openCustomer(id: number) {
  if (!can('view-customers')) return
  void router.push({ name: 'customers.show', params: { id } })
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('reports.leadPipeline') }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.leadPipelineDesc') }}</p>
      </div>
      <div class="d-flex align-items-end gap-2">
        <label class="visually-hidden" for="lead-export-format">{{ t('reports.exportFormat') }}</label>
        <select id="lead-export-format" v-model="exportFormat" class="form-select w-auto">
          <option value="xlsx">Excel (.xlsx)</option><option value="pdf">PDF (.pdf)</option>
        </select>
        <button class="btn btn-outline-success" :disabled="exporting" @click="downloadExport"><span v-if="exporting" class="spinner-border spinner-border-sm me-2"></span><i v-else class="bi bi-download me-2"></i>{{ t('reports.export') }}</button>
        <button class="btn btn-outline-secondary" :disabled="exportPolling" @click="exportAsync"><span v-if="exportPolling" class="spinner-border spinner-border-sm me-2"></span><i v-else class="bi bi-file-earmark-arrow-down me-2"></i>{{ t('reports.exportAsync') }}</button>
        <button class="btn btn-outline-primary" :disabled="polling" @click="generateAsync"><span v-if="polling" class="spinner-border spinner-border-sm me-2"></span><i v-else class="bi bi-cloud-arrow-down me-2"></i>{{ t('reports.generateAsync') }}</button>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold">{{ t('reports.dateFrom') }}</label>
            <input v-model="dateFrom" type="date" class="form-control">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold">{{ t('reports.dateTo') }}</label>
            <input v-model="dateTo" type="date" class="form-control">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold">{{ t('common.status') }}</label>
            <select v-model="status" class="form-select">
              <option value="">{{ t('common.all') }}</option>
              <option v-for="item in ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'lost']" :key="item" :value="item">{{ item }}</option>
            </select>
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold">{{ t('activities.owner') }}</label>
            <UserPicker v-model="ownerId" :teleport-dropdown="true" />
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold">{{ t('reports.customer_name') }}</label>
            <CustomerPicker v-model="customerId" :show-quick-add="false" :teleport-dropdown="true" />
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold">{{ t('reports.source') }}</label>
            <input v-model="source" type="text" class="form-control">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold">{{ t('reports.minimumScore') }}</label>
            <input v-model.number="minScore" type="number" min="0" max="100" class="form-control">
          </div>
          <div class="col-sm-auto">
            <button class="btn btn-primary" :disabled="loading" @click="fetchReport">
              <i class="bi bi-funnel me-2"></i>{{ t('common.apply') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="asyncJob && polling" class="alert alert-info d-flex align-items-center gap-2">
      <span class="spinner-border spinner-border-sm"></span>{{ t('reports.asyncProcessing') }}
    </div>
    <div v-if="exportJob && exportPolling" class="alert alert-warning d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.exportProcessing') }}</div>
    <div v-if="exportDownloadJobId && !exportPolling" class="alert alert-success d-flex align-items-center justify-content-between"><span>{{ t('reports.exportCompleted') }}</span><button class="btn btn-sm btn-success" :disabled="downloadingExport" @click="downloadAsyncExport"><i class="bi bi-download me-1"></i>{{ t('reports.downloadFile') }}</button></div>

    <div v-if="report" class="row g-3 mb-3">
      <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.totalLeads') }}</small><div class="fs-3 fw-semibold">{{ report.summary.total_leads }}</div></div></div></div>
      <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.leadsInProgress') }}</small><div class="fs-3 fw-semibold">{{ report.summary.open_leads }}</div></div></div></div>
      <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.scoredLeads') }}</small><div class="fs-3 fw-semibold">{{ report.summary.scored_leads }}</div></div></div></div>
      <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.avgLeadScore') }}</small><div class="fs-3 fw-semibold">{{ report.summary.avg_score ?? '—' }}</div></div></div></div>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>
    <div v-else-if="!report" class="text-center text-muted py-5">{{ t('common.noData') }}</div>
    <template v-else>
      <div class="row g-3 mb-3">
        <div class="col-xl-5">
          <BaseCard :title="t('reports.leadsByStatus')" icon="bi-bar-chart-steps">
            <div class="table-responsive"><table class="table table-sm mb-0"><tbody>
              <tr v-for="(count, key) in report.data.by_status" :key="key"><th class="fw-normal">{{ key }}</th><td class="text-end fw-semibold">{{ count }}</td></tr>
            </tbody></table></div>
          </BaseCard>
        </div>
        <div class="col-xl-7">
          <BaseCard :title="t('reports.leadsBySource')" icon="bi-bullseye">
            <div v-if="report.data.by_source.length" class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ t('reports.source') }}</th><th class="text-end">{{ t('reports.totalLeads') }}</th><th class="text-end">{{ t('reports.avgLeadScore') }}</th></tr></thead><tbody>
              <tr v-for="item in report.data.by_source" :key="item.source ?? 'unknown'"><td>{{ item.source ?? t('reports.unknownSource') }}</td><td class="text-end">{{ item.lead_count }}</td><td class="text-end">{{ item.avg_score ?? '—' }}</td></tr>
            </tbody></table></div>
            <div v-else class="text-muted small py-2">{{ t('common.noData') }}</div>
          </BaseCard>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-5">
          <BaseCard :title="t('reports.leadsByOwner')" icon="bi-people">
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ t('reports.accountManager') }}</th><th class="text-end">{{ t('reports.totalLeads') }}</th><th class="text-end">{{ t('reports.avgLeadScore') }}</th></tr></thead><tbody>
              <tr v-for="(item, index) in report.data.by_owner" :key="item.owner?.id ?? `unassigned-${index}`"><td>{{ item.owner?.name ?? t('reports.unassigned') }}</td><td class="text-end">{{ item.lead_count }}</td><td class="text-end">{{ item.avg_score ?? '—' }}</td></tr>
            </tbody></table></div>
          </BaseCard>
        </div>
        <div class="col-xl-7">
          <BaseCard :title="t('reports.topScoredLeads')" icon="bi-award">
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>{{ t('common.name') }}</th><th>{{ t('common.status') }}</th><th>{{ t('reports.source') }}</th><th class="text-end">{{ t('reports.healthScore') }}</th></tr></thead><tbody>
              <tr v-for="lead in report.data.top_scored_leads" :key="lead.customer.id"><td><button v-if="can('view-customers')" class="btn btn-link p-0 text-start" @click="openCustomer(lead.customer.id)">{{ lead.customer.name }}</button><span v-else>{{ lead.customer.name }}</span></td><td>{{ lead.status }}</td><td>{{ lead.source ?? '—' }}</td><td class="text-end fw-semibold">{{ lead.score }}</td></tr>
            </tbody></table></div>
            <div v-if="!report.data.top_scored_leads.length" class="text-muted small py-2">{{ t('common.noData') }}</div>
          </BaseCard>
        </div>
      </div>
    </template>
  </div>
</template>