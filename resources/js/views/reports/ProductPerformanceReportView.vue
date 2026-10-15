<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import { useProductLabels } from '@/composables/useProductLabels'
import { useToast } from '@/composables/useToast'
import { asyncJobService } from '@/services/asyncJobService'
import { reportService, type ExportFormat, type ProductPerformanceFilters } from '@/services/reportService'
import type { ProductPerformanceReport } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const { plural: productPlural, productPerformanceTitle } = useProductLabels()
const { error: showError } = useToast()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()
const report = ref<ProductPerformanceReport | null>(null)
const loading = ref(false)
const exporting = ref(false)
const downloadingExport = ref(false)
const exportDownloadJobId = ref<number | null>(null)
const exportFormat = ref<ExportFormat>('xlsx')
const { dateFrom, dateTo } = useDefaultDateRange()

function buildParams(): ProductPerformanceFilters {
  const params: ProductPerformanceFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  return params
}

async function fetchReport() {
  loading.value = true
  try {
    report.value = await reportService.productPerformance(buildParams())
  } catch {
    showError('common.error')
  } finally {
    loading.value = false
  }
}

async function downloadExport() {
  exporting.value = true
  try {
    await reportService.exportProductPerformance(buildParams(), exportFormat.value)
  } catch {
    showError('common.error')
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.productPerformanceAsync(buildParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        report.value = job.result as ProductPerformanceReport
      }
    },
  )
}

function exportAsync() {
  exportDownloadJobId.value = null
  startExportAsync(
    () => reportService.exportProductPerformanceAsync(buildParams(), exportFormat.value),
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

function formatCurrency(value: string, currency: string): string {
  return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(value))
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ productPerformanceTitle }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.productPerformanceDesc') }}</p>
      </div>
      <div class="d-flex align-items-end gap-2">
        <label class="visually-hidden" for="product-export-format">{{ t('reports.exportFormat') }}</label>
        <select id="product-export-format" v-model="exportFormat" class="form-select w-auto">
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
          <div class="col-sm-auto"><button class="btn btn-primary" :disabled="loading" @click="fetchReport"><i class="bi bi-funnel me-2"></i>{{ t('common.apply') }}</button></div>
        </div>
      </div>
    </div>

    <div v-if="asyncJob && polling" class="alert alert-info d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.asyncProcessing') }}</div>
    <div v-if="exportJob && exportPolling" class="alert alert-warning d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.exportProcessing') }}</div>
    <div v-if="exportDownloadJobId && !exportPolling" class="alert alert-success d-flex align-items-center justify-content-between"><span>{{ t('reports.exportCompleted') }}</span><button class="btn btn-sm btn-success" :disabled="downloadingExport" @click="downloadAsyncExport"><i class="bi bi-download me-1"></i>{{ t('reports.downloadFile') }}</button></div>

    <template v-if="report">
      <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.productsTracked') }}</small><div class="fs-3 fw-semibold">{{ report.summary.products }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.productsWithSales') }}</small><div class="fs-3 fw-semibold">{{ report.summary.products_with_sales }}</div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.openPipelineOpportunities') }}</small><div class="fs-3 fw-semibold">{{ report.summary.open_pipeline_opportunities }}</div><small class="text-muted">{{ report.summary.open_pipeline_quantity }} {{ t('reports.unitsInterested') }}</small></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.bookedSalesByCurrency') }}</small><div v-for="item in report.summary.booked_sales_by_currency" :key="item.currency" class="d-flex justify-content-between gap-2"><span class="text-muted">{{ item.currency }}</span><strong>{{ formatCurrency(item.booked_value, item.currency) }}</strong></div><span v-if="!report.summary.booked_sales_by_currency.length" class="text-muted">—</span></div></div></div>
      </div>
    </template>

    <BaseCard :title="productPerformanceTitle" icon="bi-box-seam">
      <div v-if="loading" class="text-center py-4"><BaseSpinner /></div>
      <div v-else-if="!report || report.data.length === 0" class="text-center text-muted py-4">{{ t('common.noData') }}</div>
      <div v-else class="table-responsive"><table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>{{ productPlural }}</th><th>{{ t('reports.industry') }}</th><th>{{ t('reports.bookedSalesByCurrency') }}</th><th class="text-end">{{ t('reports.openPipelineOpportunities') }}</th><th class="text-end">{{ t('reports.unitsInterested') }}</th></tr></thead>
        <tbody><tr v-for="row in report.data" :key="row.product.id">
          <td><strong>{{ row.product.name }}</strong><small v-if="row.product.sku" class="d-block text-muted">{{ row.product.sku }}</small><small v-if="!row.product.is_active" class="d-block text-muted">{{ t('common.inactive') }}</small></td>
          <td>{{ row.product.category?.name ?? '—' }}</td>
          <td><span v-if="!row.booked_sales.length" class="text-muted">—</span><span v-for="sale in row.booked_sales" :key="sale.currency" class="d-block">{{ formatCurrency(sale.booked_value, sale.currency) }} <small class="text-muted">({{ sale.quantity }})</small></span></td>
          <td class="text-end">{{ row.open_pipeline.opportunities_count }}</td>
          <td class="text-end">{{ row.open_pipeline.quantity }}</td>
        </tr></tbody>
      </table></div>
    </BaseCard>
  </div>
</template>