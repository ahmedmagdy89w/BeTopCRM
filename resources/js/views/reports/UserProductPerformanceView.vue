<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import { useProductLabels } from '@/composables/useProductLabels'
import { useToast } from '@/composables/useToast'
import { asyncJobService } from '@/services/asyncJobService'
import { reportService, type ExportFormat, type ReportPageMeta, type UserProductPerformanceFilters } from '@/services/reportService'
import type { UserProductPerformanceRow, UserProductPerformanceSummary } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BasePagination from '@/components/base/BasePagination.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ProductPicker from '@/components/shared/ProductPicker.vue'
import UserPicker from '@/components/shared/UserPicker.vue'

const { t } = useI18n()
const { error: showError } = useToast()
const { plural: productPlural, singular: productSingular } = useProductLabels()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()
const rows = ref<UserProductPerformanceRow[]>([])
const summary = ref<UserProductPerformanceSummary | null>(null)
const loading = ref(false)
const exporting = ref(false)
const downloadingExport = ref(false)
const exportDownloadJobId = ref<number | null>(null)
const exportFormat = ref<ExportFormat>('xlsx')
const { dateFrom, dateTo } = useDefaultDateRange()
const userId = ref<number | null>(null)
const productId = ref<number | null>(null)
const page = ref(1)
const perPage = ref(50)
const meta = ref<ReportPageMeta | null>(null)

type SortKey = 'user' | 'product' | 'opportunities_count' | 'won_count' | 'open_count' | 'quantity' | 'booked_value'

const sortKey = ref<SortKey>('booked_value')
const sortDir = ref<'asc' | 'desc'>('desc')

function buildParams(): UserProductPerformanceFilters {
  const params: UserProductPerformanceFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (userId.value != null) params.user_id = userId.value
  if (productId.value != null) params.product_id = productId.value
  params.page = page.value
  params.per_page = perPage.value
  params.sort_by = sortKey.value
  params.sort_dir = sortDir.value
  return params
}

function buildFullParams(): UserProductPerformanceFilters {
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

function toggleSort(key: SortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = ['user', 'product'].includes(key) ? 'asc' : 'desc'
  }
  page.value = 1
  fetchReport()
}

async function fetchReport() {
  loading.value = true
  try {
    const res = await reportService.userProductPerformance(buildParams())
    rows.value = res.data
    summary.value = res.summary
    meta.value = res.meta ?? null
    if (meta.value) {
      page.value = meta.value.current_page
      perPage.value = meta.value.per_page
    }
  } catch {
    showError('common.error')
  } finally {
    loading.value = false
  }
}

async function downloadExport() {
  exporting.value = true
  try {
    await reportService.exportUserProductPerformance(buildFullParams(), exportFormat.value)
  } catch {
    showError('common.error')
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.userProductPerformanceAsync(buildFullParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        const result = job.result as { data: UserProductPerformanceRow[]; summary: UserProductPerformanceSummary }
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
    () => reportService.exportUserProductPerformanceAsync(buildFullParams(), exportFormat.value),
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

function formatCurrencyValues(values: { currency: string; value: string }[]): string {
  return values.map(item => new Intl.NumberFormat(undefined, { style: 'currency', currency: item.currency }).format(Number(item.value))).join(' · ') || '—'
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('reports.userProductPerformance') }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.userProductPerformanceDesc') }}</p>
      </div>
      <div class="d-flex align-items-end gap-2">
        <label class="visually-hidden" for="user-product-export-format">{{ t('reports.exportFormat') }}</label>
        <select id="user-product-export-format" v-model="exportFormat" class="form-select w-auto">
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
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold">{{ t('reports.user') }}</label>
            <UserPicker v-model="userId" :teleport-dropdown="true" />
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold">{{ productSingular }}</label>
            <ProductPicker v-model="productId" :teleport-dropdown="true" />
          </div>
          <div class="col-sm-auto"><button class="btn btn-primary" :disabled="loading" @click="applyFilters"><i class="bi bi-funnel me-2"></i>{{ t('common.apply') }}</button></div>
        </div>
      </div>
    </div>

    <div v-if="asyncJob && polling" class="alert alert-info d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.asyncProcessing') }}</div>
    <div v-if="exportJob && exportPolling" class="alert alert-warning d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm"></span>{{ t('reports.exportProcessing') }}</div>
    <div v-if="exportDownloadJobId && !exportPolling" class="alert alert-success d-flex align-items-center justify-content-between"><span>{{ t('reports.exportCompleted') }}</span><button class="btn btn-sm btn-success" :disabled="downloadingExport" @click="downloadAsyncExport"><i class="bi bi-download me-1"></i>{{ t('reports.downloadFile') }}</button></div>

    <div v-if="summary" class="row g-3 mb-3">
      <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('nav.users') }}</small><div class="fs-3 fw-semibold">{{ summary.users }}</div></div></div></div>
      <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ productPlural }}</small><div class="fs-3 fw-semibold">{{ summary.products }}</div></div></div></div>
      <div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ t('reports.bookedSalesByCurrency') }}</small><div v-for="item in summary.booked_sales_by_currency" :key="item.currency" class="d-flex justify-content-between gap-2"><span class="text-muted">{{ item.currency }}</span><strong>{{ formatCurrencyValues([item]) }}</strong></div><span v-if="!summary.booked_sales_by_currency.length" class="text-muted">—</span></div></div></div>
    </div>

    <BaseCard :title="t('reports.userProductPerformance')" icon="bi-person-badge">
      <div v-if="loading" class="text-center py-4"><BaseSpinner /></div>
      <div v-else-if="!rows.length" class="text-center text-muted py-4">{{ t('common.noData') }}</div>
      <div v-else class="table-responsive"><table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light"><tr>
          <th class="sortable-header" @click="toggleSort('user')">
            <span class="d-inline-flex align-items-center gap-1">{{ t('reports.user') }}
              <i v-if="sortKey === 'user'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
          <th class="sortable-header" @click="toggleSort('product')">
            <span class="d-inline-flex align-items-center gap-1">{{ productSingular }}
              <i v-if="sortKey === 'product'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
          <th class="text-end sortable-header" @click="toggleSort('opportunities_count')">
            <span class="d-inline-flex align-items-center gap-1">{{ t('reports.totalOpportunities') }}
              <i v-if="sortKey === 'opportunities_count'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
          <th class="text-end sortable-header" @click="toggleSort('won_count')">
            <span class="d-inline-flex align-items-center gap-1">{{ t('reports.opportunitiesWon') }}
              <i v-if="sortKey === 'won_count'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
          <th class="text-end sortable-header" @click="toggleSort('open_count')">
            <span class="d-inline-flex align-items-center gap-1">{{ t('reports.opportunitiesInProgress') }}
              <i v-if="sortKey === 'open_count'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
          <th class="text-end sortable-header" @click="toggleSort('quantity')">
            <span class="d-inline-flex align-items-center gap-1">{{ t('reports.quantity') }}
              <i v-if="sortKey === 'quantity'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
          <th class="text-end sortable-header" @click="toggleSort('booked_value')">
            <span class="d-inline-flex align-items-center gap-1">{{ t('reports.totalWonValue') }}
              <i v-if="sortKey === 'booked_value'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
              <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
            </span>
          </th>
        </tr></thead>
        <tbody><tr v-for="row in rows" :key="`${row.user.id}-${row.product.id}`">
          <td><strong>{{ row.user.name }}</strong></td>
          <td><strong>{{ row.product.name }}</strong><small v-if="row.product.sku" class="d-block text-muted">{{ row.product.sku }}</small></td>
          <td class="text-end">{{ row.opportunities_count }}</td>
          <td class="text-end text-success fw-semibold">{{ row.won_count }}</td>
          <td class="text-end">{{ row.open_count }}</td>
          <td class="text-end">{{ row.quantity }}</td>
          <td class="text-end">{{ formatCurrencyValues(row.booked_value_by_currency) }}</td>
        </tr></tbody>
      </table></div>
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

<style scoped>
.sortable-header {
  cursor: pointer;
  user-select: none;
}

.sortable-header:hover {
  background-color: var(--bs-tertiary-bg);
}
</style>
