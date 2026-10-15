<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { reportService, type UserPerformanceFilters, type ExportFormat } from '@/services/reportService'
import { asyncJobService } from '@/services/asyncJobService'
import type { UserPerformanceRow, UserPerformanceSummary } from '@/types'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import UserPicker from '@/components/shared/UserPicker.vue'

const { t } = useI18n()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()

const loading = ref(true)
const rows = ref<UserPerformanceRow[]>([])
const summary = ref<UserPerformanceSummary | null>(null)
const exportDownloadJobId = ref<number | null>(null)
const downloadingExport = ref(false)
const { dateFrom, dateTo } = useDefaultDateRange()
const userId = ref<number | null>(null)
const exportFormat = ref<ExportFormat>('xlsx')

type SortKey = 'name' | 'leads_assigned' | 'opportunities_won' | 'opportunities_lost'
  | 'opportunities_in_progress' | 'opportunity_win_rate' | 'total_won_value'
  | 'avg_opportunity_value' | 'activities_logged' | 'avg_days_to_close'

const sortKey = ref<SortKey>('total_won_value')
const sortDir = ref<'asc' | 'desc'>('desc')

function sortValue(row: UserPerformanceRow, key: SortKey): number | string {
  switch (key) {
    case 'name':
      return row.user.name
    case 'opportunity_win_rate':
      return parseFloat(row.opportunity_win_rate) || 0
    case 'total_won_value':
      return row.total_won_value_by_currency.reduce((sum, item) => sum + Number(item.value), 0)
    case 'avg_opportunity_value':
      return row.total_won_value_by_currency.reduce((sum, item) => sum + Number(item.avg_opportunity_value), 0)
    case 'avg_days_to_close':
      return row.avg_days_to_close ?? 0
    default:
      return row[key]
  }
}

const sortedRows = computed(() => [...rows.value].sort((a, b) => {
  if (sortKey.value === 'avg_days_to_close') {
    if (a.avg_days_to_close == null) return 1
    if (b.avg_days_to_close == null) return -1
  }
  const valueA = sortValue(a, sortKey.value)
  const valueB = sortValue(b, sortKey.value)
  const compared = typeof valueA === 'string'
    ? valueA.localeCompare(valueB as string)
    : valueA - (valueB as number)
  return sortDir.value === 'asc' ? compared : -compared
}))

function toggleSort(key: SortKey) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = key === 'name' ? 'asc' : 'desc'
  }
}

function buildParams(): UserPerformanceFilters {
  const params: UserPerformanceFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (userId.value != null) params.user_id = userId.value
  return params
}

async function fetchReport() {
  loading.value = true
  try {
    const res = await reportService.userPerformance(buildParams())
    rows.value = res.data
    summary.value = res.summary
  } finally {
    loading.value = false
  }
}

const exporting = ref(false)
async function downloadExport() {
  exporting.value = true
  try {
    await reportService.exportUserPerformance(buildParams(), exportFormat.value)
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.userPerformanceAsync(buildParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        const result = job.result as { data: UserPerformanceRow[]; summary: UserPerformanceSummary }
        rows.value = result.data
        summary.value = result.summary
      }
    },
  )
}

function exportAsync() {
  exportDownloadJobId.value = null
  startExportAsync(
    () => reportService.exportUserPerformanceAsync(buildParams(), exportFormat.value),
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
  return values.map(item => `${new Intl.NumberFormat(undefined, { style: 'currency', currency: item.currency }).format(Number(item.value))}`).join(' · ') || '—'
}

function formatOpportunityValues(values: { currency: string; avg_opportunity_value: string }[]): string {
  return values.map(item => `${new Intl.NumberFormat(undefined, { style: 'currency', currency: item.currency }).format(Number(item.avg_opportunity_value))}`).join(' · ') || '—'
}

function formatActivityTypes(types: Record<string, number>): string {
  return Object.entries(types).map(([type, count]) => `${t(`customers.activityTypes.${type}`)}: ${count}`).join(', ') || '—'
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('reports.userPerformance') }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.userPerformanceDesc') }}</p>
      </div>
    </div>

    <!-- Filter & Actions Card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-3">
        <div class="row g-3 align-items-end">
          <!-- Filters -->
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.dateFrom') }}</label>
            <input v-model="dateFrom" type="date" class="form-control">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.dateTo') }}</label>
            <input v-model="dateTo" type="date" class="form-control">
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.user') }}</label>
            <UserPicker v-model="userId" :teleport-dropdown="true" />
          </div>
          <div class="col-sm-auto">
            <button class="btn btn-primary px-4" @click="fetchReport">
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
            <div class="fs-5 fw-bold text-primary">{{ summary.total_users }}</div>
            <small class="text-muted">{{ t('nav.users') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="fs-5 fw-bold text-info">{{ summary.total_leads_assigned }}</div>
            <small class="text-muted">{{ t('reports.totalLeadsAssigned') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="fs-6 fw-bold text-success text-break lh-sm">{{ formatCurrencyValues(summary.total_won_value_by_currency) }}</div>
            <small class="text-muted">{{ t('reports.totalWonValue') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center">
          <div class="card-body">
            <div class="fs-5 fw-bold text-warning">{{ summary.overall_opportunity_win_rate }}%</div>
            <small class="text-muted">{{ t('reports.opportunityWinRate') }}</small>
          </div>
        </div>
      </div>
    </div>

    <BaseCard :title="t('reports.userPerformance')" icon="bi-people">
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
              <th class="sortable-header" @click="toggleSort('name')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('common.name') }}
                  <i v-if="sortKey === 'name'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('leads_assigned')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.leadsAssigned') }}
                  <i v-if="sortKey === 'leads_assigned'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('opportunities_won')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.opportunitiesWon') }}
                  <i v-if="sortKey === 'opportunities_won'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('opportunities_lost')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.opportunitiesLost') }}
                  <i v-if="sortKey === 'opportunities_lost'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('opportunities_in_progress')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.opportunitiesInProgress') }}
                  <i v-if="sortKey === 'opportunities_in_progress'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('opportunity_win_rate')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.opportunityWinRate') }}
                  <i v-if="sortKey === 'opportunity_win_rate'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('total_won_value')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.totalWonValue') }}
                  <i v-if="sortKey === 'total_won_value'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('avg_opportunity_value')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.avgOpportunityValue') }}
                  <i v-if="sortKey === 'avg_opportunity_value'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th class="text-end sortable-header" @click="toggleSort('activities_logged')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.activitiesLogged') }}
                  <i v-if="sortKey === 'activities_logged'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
              <th>{{ t('reports.activitiesByType') }}</th>
              <th class="text-end sortable-header" @click="toggleSort('avg_days_to_close')">
                <span class="d-inline-flex align-items-center gap-1">
                  {{ t('reports.avgDaysToClose') }}
                  <i v-if="sortKey === 'avg_days_to_close'" :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in sortedRows" :key="row.user.id">
              <td>{{ row.user.name }}</td>
              <td class="text-end">{{ row.leads_assigned }}</td>
              <td class="text-end text-success fw-semibold">{{ row.opportunities_won }}</td>
              <td class="text-end text-danger">{{ row.opportunities_lost }}</td>
              <td class="text-end">{{ row.opportunities_in_progress }}</td>
              <td class="text-end">{{ row.opportunity_win_rate }}%</td>
              <td class="text-end">{{ formatCurrencyValues(row.total_won_value_by_currency) }}</td>
              <td class="text-end">{{ formatOpportunityValues(row.total_won_value_by_currency) }}</td>
              <td class="text-end">{{ row.activities_logged }}</td>
              <td><small>{{ formatActivityTypes(row.activities_by_type) }}</small></td>
              <td class="text-end">{{ row.avg_days_to_close }}d</td>
            </tr>
          </tbody>
        </table>
      </div>
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
