<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { reportService, type CompanyReportFilters, type ExportFormat, type ReportPageMeta } from '@/services/reportService'
import { asyncJobService } from '@/services/asyncJobService'
import type { CompanyReportRow, CompanyReportSummary } from '@/types'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import { usePermission } from '@/composables/usePermission'
import BaseCard from '@/components/base/BaseCard.vue'
import BasePagination from '@/components/base/BasePagination.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const { can } = usePermission()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()

const loading = ref(true)
const rows = ref<CompanyReportRow[]>([])
const summary = ref<CompanyReportSummary | null>(null)
const exportDownloadJobId = ref<number | null>(null)
const downloadingExport = ref(false)
const { dateFrom, dateTo } = useDefaultDateRange()
const industry = ref('')
const exportFormat = ref<ExportFormat>('xlsx')
const page = ref(1)
const perPage = ref(50)
const meta = ref<ReportPageMeta | null>(null)

function buildParams(): CompanyReportFilters {
  const params: CompanyReportFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (industry.value) params.industry = industry.value
  params.page = page.value
  params.per_page = perPage.value
  return params
}

function buildFullParams(): CompanyReportFilters {
  const params = buildParams()
  delete params.page
  delete params.per_page
  return params
}

function applyFilters() {
  page.value = 1
  fetchReport()
}

function changePage(next: number) {
  page.value = next
  fetchReport()
}

function changePerPage(size: number) {
  perPage.value = size
  page.value = 1
  fetchReport()
}

async function fetchReport() {
  loading.value = true
  try {
    const res = await reportService.company(buildParams())
    rows.value = res.data
    summary.value = res.summary
    meta.value = res.meta ?? null
    if (meta.value) {
      page.value = meta.value.current_page
      perPage.value = meta.value.per_page
    }
  } finally {
    loading.value = false
  }
}

const exporting = ref(false)
async function downloadExport() {
  exporting.value = true
  try {
    await reportService.exportCompany(buildFullParams(), exportFormat.value)
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.companyAsync(buildFullParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        const result = job.result as { data: CompanyReportRow[]; summary: CompanyReportSummary }
        rows.value = result.data
        summary.value = result.summary
        meta.value = null
      }
    },
  )
}

function exportAsync() {
  exportDownloadJobId.value = null
  startExportAsync(
    () => reportService.exportCompanyAsync(buildFullParams(), exportFormat.value),
    (job) => {
      exportDownloadJobId.value = job.id
    },
  )
}

async function downloadAsyncExport() {
  if (!exportDownloadJobId.value) return
  downloadingExport.value = true
  try {
    await asyncJobService.download(exportDownloadJobId.value)
  } finally {
    downloadingExport.value = false
  }
}

function formatCurrencyValues(values: { currency: string; value: string }[]): string {
  return values.map(item => new Intl.NumberFormat(undefined, { style: 'currency', currency: item.currency }).format(Number(item.value))).join(' · ') || '—'
}

function formatStages(stages: Record<string, number>): string {
  return Object.entries(stages).map(([stage, count]) => `${t(`opportunities.stages.${stage}`)}: ${count}`).join(', ')
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('reports.company') }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.companyDesc') }}</p>
      </div>
    </div>

    <!-- Filter & Actions Card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-3">
        <div class="row g-3 align-items-end">
          <!-- Filters -->
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.industry') }}</label>
            <input v-model="industry" type="text" class="form-control" :placeholder="t('common.all')" style="min-width:160px;">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.dateFrom') }}</label>
            <input v-model="dateFrom" type="date" class="form-control">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.dateTo') }}</label>
            <input v-model="dateTo" type="date" class="form-control">
          </div>
          <div class="col-sm-auto">
            <button class="btn btn-primary px-4" @click="applyFilters">
              <i class="bi bi-funnel me-2"></i>{{ t('common.apply') }}
            </button>
          </div>

          <!-- Divider -->
          <div class="col-sm-auto d-none d-sm-block">
            <div style="width:1px;height:36px;background:var(--bs-border-color);"></div>
          </div>

          <!-- Export Actions -->
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.exportFormat') }}</label>
            <select v-model="exportFormat" class="form-select">
              <option value="xlsx">Excel (.xlsx)</option>
              <option value="pdf">PDF (.pdf)</option>
            </select>
          </div>
          <div class="col-sm-auto d-flex gap-2">
            <button class="btn btn-outline-success" :disabled="exporting" @click="downloadExport">
              <span v-if="exporting" class="spinner-border spinner-border-sm me-2"></span>
              <i v-else class="bi bi-download me-2"></i>{{ t('reports.export') }}
            </button>
            <button class="btn btn-outline-primary" :disabled="polling" @click="generateAsync">
              <span v-if="polling" class="spinner-border spinner-border-sm me-2"></span>
              <i v-else class="bi bi-cloud-arrow-down me-2"></i>{{ t('reports.generateAsync') }}
            </button>
            <button class="btn btn-outline-secondary" :disabled="exportPolling" @click="exportAsync">
              <span v-if="exportPolling" class="spinner-border spinner-border-sm me-2"></span>
              <i v-else class="bi bi-file-earmark-arrow-down me-2"></i>{{ t('reports.exportAsync') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Async Job Progress -->
    <div v-if="asyncJob && polling" class="alert alert-info d-flex align-items-center gap-3 mb-3">
      <div class="spinner-border spinner-border-sm"></div>
      <div class="flex-grow-1">
        <strong>{{ t('reports.asyncProcessing') }}</strong>
        <div class="progress mt-1" style="height: 6px;">
          <div class="progress-bar" :style="{ width: `${asyncJob.progress}%` }"></div>
        </div>
      </div>
      <small>{{ asyncJob.progress }}%</small>
    </div>

    <!-- Async Export Progress -->
    <div v-if="exportJob && exportPolling" class="alert alert-warning d-flex align-items-center gap-3 mb-3">
      <div class="spinner-border spinner-border-sm"></div>
      <div class="flex-grow-1">
        <strong>{{ t('reports.exportProcessing') }}</strong>
        <div class="progress mt-1" style="height: 6px;">
          <div class="progress-bar bg-warning" :style="{ width: `${exportJob.progress}%` }"></div>
        </div>
      </div>
      <small>{{ exportJob.progress }}%</small>
    </div>

    <!-- Download Ready -->
    <div v-if="exportDownloadJobId && !exportPolling" class="alert alert-success d-flex align-items-center gap-3 mb-3">
      <i class="bi bi-check-circle-fill fs-5"></i>
      <div class="flex-grow-1">
        <strong>{{ t('reports.exportCompleted') }}</strong>
      </div>
      <button class="btn btn-sm btn-success" :disabled="downloadingExport" @click="downloadAsyncExport">
        <span v-if="downloadingExport" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="bi bi-download me-1"></i>{{ t('reports.downloadFile') }}
      </button>
    </div>

    <!-- Summary Cards -->
    <div v-if="summary" class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="fs-3 fw-bold text-primary">{{ summary.total_organizations }}</div>
            <small class="text-muted">{{ t('reports.totalOrganizations') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="fs-3 fw-bold text-info">{{ summary.total_leads_across_orgs }}</div>
            <small class="text-muted">{{ t('reports.totalLeadsAcrossOrgs') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="fs-3 fw-bold text-success">{{ formatCurrencyValues(summary.total_won_value_by_currency) }}</div>
            <small class="text-muted">{{ t('reports.totalWonValue') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <BaseCard :title="t('reports.topIndustries')" :noPadding="true">
          <div class="list-group list-group-flush">
            <div v-for="ind in summary.top_industries" :key="ind.industry" class="list-group-item d-flex justify-content-between py-1 px-2">
              <small>{{ ind.industry }}</small>
              <small class="fw-semibold">{{ formatCurrencyValues(ind.won_value_by_currency) }}</small>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>

    <BaseCard :title="t('reports.company')" icon="bi-building">
      <div v-if="loading" class="text-center py-4">
        <BaseSpinner />
      </div>
      <div v-else-if="rows.length === 0" class="text-center text-muted py-4">
        {{ t('common.noData') }}
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ t('reports.organization') }}</th>
              <th>{{ t('reports.industry') }}</th>
              <th class="text-end">{{ t('reports.totalLeads') }}</th>
              <th>{{ t('reports.opportunitiesByStage') }}</th>
              <th class="text-end">{{ t('reports.conversionRate') }}</th>
              <th class="text-end">{{ t('reports.totalEstimatedValue') }}</th>
              <th class="text-end">{{ t('reports.totalWonValue') }}</th>
              <th class="text-end">{{ t('reports.contactsCount') }}</th>
              <th>{{ t('reports.latestActivity') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.organization.id">
              <td>
                <router-link v-if="can('view-customers')" :to="{ name: 'customers.show', params: { id: row.organization.id } }">
                  {{ row.organization.name }}
                </router-link>
                <span v-else>{{ row.organization.name }}</span>
              </td>
              <td>{{ row.organization.industry ?? '—' }}</td>
              <td class="text-end">{{ row.total_leads }}</td>
              <td><small>{{ formatStages(row.leads_by_status) }}</small></td>
              <td class="text-end">{{ row.conversion_rate }}%</td>
              <td class="text-end">{{ formatCurrencyValues(row.total_estimated_value_by_currency) }}</td>
              <td class="text-end text-success fw-semibold">{{ formatCurrencyValues(row.total_won_value_by_currency) }}</td>
              <td class="text-end">{{ row.contacts_count }}</td>
              <td>
                <small class="text-muted">
                  {{ row.latest_activity_date ? new Date(row.latest_activity_date).toLocaleDateString() : '—' }}
                </small>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <template v-if="meta && meta.total > 0" #footer>
        <BasePagination
          :total="meta.total"
          :per-page="perPage"
          :current-page="page"
          @update:current-page="changePage"
          @update:per-page="changePerPage"
        />
      </template>
    </BaseCard>
  </div>
</template>
