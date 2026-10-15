<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { reportService, type CustomerReportFilters, type ExportFormat, type ReportPageMeta } from '@/services/reportService'
import { asyncJobService } from '@/services/asyncJobService'
import type { CustomerReportRow, CustomerReportSummary } from '@/types'
import { useAsyncReport } from '@/composables/useAsyncReport'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import { usePermission } from '@/composables/usePermission'
import BaseCard from '@/components/base/BaseCard.vue'
import BasePagination from '@/components/base/BasePagination.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import HealthScoreBar from '@/components/shared/HealthScoreBar.vue'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import CustomerPicker from '@/components/shared/CustomerPicker.vue'
import UserPicker from '@/components/shared/UserPicker.vue'

const { t } = useI18n()
const { can } = usePermission()
const { asyncJob, polling, startAsync } = useAsyncReport()
const { asyncJob: exportJob, polling: exportPolling, startAsync: startExportAsync } = useAsyncReport()

const loading = ref(true)
const rows = ref<CustomerReportRow[]>([])
const summary = ref<CustomerReportSummary | null>(null)
const exportDownloadJobId = ref<number | null>(null)
const downloadingExport = ref(false)
const { dateFrom, dateTo } = useDefaultDateRange()
const customerId = ref<number | null>(null)
const status = ref('')
const customerType = ref('')
const accountManagerId = ref<number | null>(null)
const healthBelow = ref<number | undefined>()
const exportFormat = ref<ExportFormat>('xlsx')
const page = ref(1)
const perPage = ref(50)
const meta = ref<ReportPageMeta | null>(null)

function buildParams(): CustomerReportFilters {
  const params: CustomerReportFilters = {}
  if (dateFrom.value) params.date_from = dateFrom.value
  if (dateTo.value) params.date_to = dateTo.value
  if (status.value) params.status = status.value
  if (customerType.value) params.type = customerType.value
  if (customerId.value != null) params.customer_id = customerId.value
  if (accountManagerId.value != null) params.account_manager_id = accountManagerId.value
  if (healthBelow.value !== undefined) params.health_below = healthBelow.value
  params.page = page.value
  params.per_page = perPage.value
  return params
}

function buildFullParams(): CustomerReportFilters {
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
    const res = await reportService.customer(buildParams())
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
    await reportService.exportCustomer(buildFullParams(), exportFormat.value)
  } finally {
    exporting.value = false
  }
}

function generateAsync() {
  startAsync(
    () => reportService.customerAsync(buildFullParams()),
    (job) => {
      if (job.result && typeof job.result === 'object') {
        const result = job.result as { data: CustomerReportRow[]; summary: CustomerReportSummary }
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
    () => reportService.exportCustomerAsync(buildFullParams(), exportFormat.value),
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

function formatActivityTypes(types: Record<string, number>): string {
  return Object.entries(types).map(([type, count]) => `${t(`customers.activityTypes.${type}`)}: ${count}`).join(', ') || '—'
}

onMounted(fetchReport)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('reports.customer') }}</h2>
        <p class="text-muted small mb-0">{{ t('reports.customerDesc') }}</p>
      </div>
    </div>

    <!-- Filter & Actions Card -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-3">
        <div class="row g-3 align-items-end">
          <!-- Filters -->
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('common.status') }}</label>
            <select v-model="status" class="form-select" style="min-width:160px;">
              <option value="">{{ t('common.all') }}</option>
              <option value="active">{{ t('customers.statuses.active') }}</option>
              <option value="at_risk">{{ t('customers.statuses.at_risk') }}</option>
              <option value="churned">{{ t('customers.statuses.churned') }}</option>
              <option value="paused">{{ t('customers.statuses.paused') }}</option>
              <option value="renewed">{{ t('customers.statuses.renewed') }}</option>
            </select>
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('customers.type') }}</label>
            <select v-model="customerType" class="form-select" style="min-width:160px;">
              <option value="">{{ t('common.all') }}</option>
              <option value="individual">{{ t('customers.individual') }}</option>
              <option value="company">{{ t('customers.company') }}</option>
            </select>
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.dateFrom') }}</label>
            <input v-model="dateFrom" type="date" class="form-control">
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.dateTo') }}</label>
            <input v-model="dateTo" type="date" class="form-control">
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.customer_name') }}</label>
            <CustomerPicker v-model="customerId" :show-quick-add="false" :teleport-dropdown="true" />
          </div>
          <div class="col" style="min-width: 220px;">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.accountManagerId') }}</label>
            <UserPicker v-model="accountManagerId" :teleport-dropdown="true" />
          </div>
          <div class="col-sm-auto">
            <label class="form-label small fw-semibold text-muted mb-1">{{ t('reports.healthBelow') }}</label>
            <input v-model.number="healthBelow" type="number" min="0" max="100" class="form-control">
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
      <div class="col-md-2">
        <div class="card text-center h-100">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-primary">{{ summary.total_customers }}</div>
            <small class="text-muted">{{ t('nav.customers') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center h-100">
          <div class="card-body py-2 d-flex flex-column justify-content-center">
            <div class="fs-6 fw-bold text-success text-break lh-sm">{{ formatCurrencyValues(summary.total_won_opportunity_revenue_by_currency) }}</div>
            <small class="text-muted">{{ t('reports.totalWonOpportunityRevenue') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center h-100">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-info">{{ summary.avg_health_score }}</div>
            <small class="text-muted">{{ t('reports.avgHealthScore') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center h-100">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-danger">{{ summary.churn_rate }}%</div>
            <small class="text-muted">{{ t('reports.churnRate') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center h-100">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-success">{{ summary.retention_rate }}%</div>
            <small class="text-muted">{{ t('reports.retentionRate') }}</small>
          </div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="card text-center h-100">
          <div class="card-body py-2">
            <div class="fs-4 fw-bold text-warning">{{ summary.customers_needing_follow_up }}</div>
            <small class="text-muted">{{ t('reports.needingFollowUp') }}</small>
          </div>
        </div>
      </div>
    </div>

    <BaseCard :title="t('reports.customer')" icon="bi-person-lines-fill">
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
              <th>{{ t('reports.customer_name') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('reports.healthScore') }}</th>
              <th>{{ t('reports.accountManager') }}</th>
              <th class="text-end">{{ t('reports.totalWonOpportunityValue') }}</th>
              <th class="text-end">{{ t('reports.wonOpportunitiesCount') }}</th>
              <th class="text-end">{{ t('reports.activitiesCount') }}</th>
              <th>{{ t('reports.activitiesByType') }}</th>
              <th class="text-end">{{ t('reports.daysSinceLastContact') }}</th>
              <th>{{ t('reports.nextFollowUp') }}</th>
              <th class="text-end">{{ t('reports.daysAsCustomer') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.customer.id">
              <td>
                <router-link v-if="can('view-customers')" :to="{ name: 'customers.show', params: { id: row.customer.id } }">
                  {{ row.customer.company_name }}
                </router-link>
                <span v-else>{{ row.customer.company_name }}</span>
                <br><small class="text-muted">{{ row.customer.contact_name }}</small>
              </td>
              <td>
                <StatusBadge :status="row.customer.status" type="customer" />
              </td>
              <td style="min-width: 120px;">
                <HealthScoreBar :score="row.customer.health_score" :show-label="true" />
              </td>
              <td>{{ row.account_manager.name }}</td>
              <td class="text-end">{{ formatCurrencyValues(row.total_won_opportunity_value_by_currency) }}</td>
              <td class="text-end">{{ row.won_opportunities_count }}</td>
              <td class="text-end">{{ row.activities_count }}</td>
              <td><small>{{ formatActivityTypes(row.activities_by_type) }}</small></td>
              <td class="text-end">
                <span :class="{ 'text-danger fw-semibold': row.days_since_last_contact !== null && row.days_since_last_contact > 14 }">
                  {{ row.days_since_last_contact === null ? '—' : `${row.days_since_last_contact}d` }}
                </span>
              </td>
              <td>{{ row.next_follow_up_at ? new Date(row.next_follow_up_at).toLocaleDateString() : '—' }}</td>
              <td class="text-end">{{ row.days_as_customer === null ? '—' : `${row.days_as_customer}d` }}</td>
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
