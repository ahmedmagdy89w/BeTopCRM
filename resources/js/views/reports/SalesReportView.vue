<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import { useToast } from '@/composables/useToast'
import { asyncJobService } from '@/services/asyncJobService'
import { reportService, type ExportFormat, type SalesReportFilters } from '@/services/reportService'
import type { SalesReportRow, SalesReportSummary } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import UserPicker from '@/components/shared/UserPicker.vue'

const { t, locale } = useI18n()
const { error: showError } = useToast()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()
const rows = ref<SalesReportRow[]>([])
const summary = ref<SalesReportSummary | null>(null)
const loading = ref(false)
const exporting = ref(false)
const downloadingExport = ref(false)
const exportDownloadJobId = ref<number | null>(null)
const exportFormat = ref<ExportFormat>('xlsx')
const { dateFrom, dateTo } = useDefaultDateRange(365)
const userId = ref<number | null>(null)
const groupBy = ref<'month' | 'year'>('month')

function buildParams(): SalesReportFilters {
  const params: SalesReportFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (userId.value != null) params.user_id = userId.value
  params.group_by = groupBy.value
  return params
}

async function fetchReport() {
  loading.value = true
  try {
    const res = await reportService.sales(buildParams())
    rows.value = res.data
    summary.value = res.summary
  } catch {
    showError('common.error')
  } finally {
    loading.value = false
  }
}

async function downloadExport() {
  exporting.value = true
  try {
    await reportService.exportSales(buildParams(), exportFormat.value)
  } catch {
    showError('common.error')
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.salesAsync(buildParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        const result = job.result as { data: SalesReportRow[]; summary: SalesReportSummary }
        rows.value = result.data
        summary.value = result.summary
      }
    },
  )
}

function exportAsync() {
  exportDownloadJobId.value = null
  startExportAsync(
    () => reportService.exportSalesAsync(buildParams(), exportFormat.value),
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

function formatPeriod(period: string): string {
  if (groupBy.value === 'year' || period.length === 4) return period
  const date = new Date(`${period}-01T00:00:00`)
  if (Number.isNaN(date.getTime())) return period
  return date.toLocaleDateString(locale.value === 'ar' ? 'ar' : 'en', { year: 'numeric', month: 'short' })
}

function formatCurrencyValues(values: { currency: string; value: string }[]): string {
  return values.map(item => new Intl.NumberFormat(undefined, { style: 'currency', currency: item.currency }).format(Number(item.value))).join(' · ') || '—'
}

function formatOpenValue(row: SalesReportRow): string {
  return new Intl.NumberFormat(undefined).format(Number(row.open_value))
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('reports.sales') }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.salesDesc') }}</p>
      </div>
      <div class="d-flex align-items-end gap-2">
        <label class="visually-hidden" for="sales-export-format">{{ t('reports.exportFormat') }}</label>
        <select id="sales-export-format" v-model="exportFormat" class="form-select w-auto">
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
          <div class="col-sm-auto"><label class="form-label small fw-semibold">{{ t('reports.dateFrom') }}</label><input v-model="dateFrom" type="date" class="form-control"></div>
          <div class="col-sm-auto"><label class="form-label small fw-semibold">{{ t('reports.dateTo') }}</label><input v-model="dateTo" type="date" class="form-control"></div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold">{{ t('reports.groupBy') }}</label>
            <select v-model="groupBy" class="form-select">
              <option value="month">{{ t('reports.monthly') }}</option>
              <option value="year">{{ t('reports.yearly') }}</option>
            </select>
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold">{{ t('reports.user') }}</label>
            <UserPicker v-model="userId" :teleport-dropdown="true" />
          </div>
          <div class="col-sm-auto"><button class="btn btn-primary" :disabled="loading" @click="fetchReport"><i class="bi bi-funnel me-2"></i>{{ t('common.apply') }}</button></div>
        </div>
      </div>
    </div>

    <div v-if="asyncJob && polling" class="alert alert-info d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.asyncProcessing') }}</div>
    <div v-if="exportJob && exportPolling" class="alert alert-warning d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.exportProcessing') }}</div>
    <div v-if="exportDownloadJobId && !exportPolling" class="alert alert-success d-flex align-items-center justify-content-between"><span>{{ t('reports.exportCompleted') }}</span><button class="btn btn-sm btn-success" :disabled="downloadingExport" @click="downloadAsyncExport"><i class="bi bi-download me-1"></i>{{ t('reports.downloadFile') }}</button></div>

    <div v-if="summary" class="row g-3 mb-3">
      <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.totalOpportunities') }}</small><div class="fs-3 fw-semibold">{{ summary.total_won_count }}</div></div></div></div>
      <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.totalWonValue') }}</small><div class="fs-6 fw-semibold text-break lh-sm">{{ formatCurrencyValues(summary.total_revenue_by_currency) }}</div></div></div></div>
      <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.periods') }}</small><div class="fs-3 fw-semibold">{{ summary.periods }}</div></div></div></div>
    </div>

    <BaseCard :title="t('reports.sales')" icon="bi-graph-up">
      <div v-if="loading" class="text-center py-4"><BaseSpinner /></div>
      <div v-else-if="!rows.length" class="text-center text-muted py-4">{{ t('common.noData') }}</div>
      <div v-else class="table-responsive"><table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light"><tr>
          <th>{{ t('reports.period') }}</th>
          <th class="text-end">{{ t('reports.opportunitiesWon') }}</th>
          <th class="text-end">{{ t('reports.totalWonValue') }}</th>
          <th class="text-end">{{ t('reports.opportunitiesInProgress') }}</th>
          <th class="text-end">{{ t('reports.openValue') }}</th>
        </tr></thead>
        <tbody><tr v-for="row in rows" :key="row.period">
          <td><strong>{{ formatPeriod(row.period) }}</strong></td>
          <td class="text-end text-success fw-semibold">{{ row.won_count }}</td>
          <td class="text-end">{{ formatCurrencyValues(row.revenue_by_currency) }}</td>
          <td class="text-end">{{ row.open_count }}</td>
          <td class="text-end">{{ formatOpenValue(row) }}</td>
        </tr></tbody>
      </table></div>
    </BaseCard>
  </div>
</template>
