<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePermission } from '@/composables/usePermission'
import { dashboardService, type DashboardFilters } from '@/services/dashboardService'
import { useDefaultDateRange } from '@/composables/useDefaultDateRange'
import type { DashboardData, ActivityFeedItem } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import SkeletonCard from '@/components/base/SkeletonCard.vue'
import PipelineChart from '@/components/charts/PipelineChart.vue'
import RevenueChart from '@/components/charts/RevenueChart.vue'

const { t } = useI18n()
const router = useRouter()
const { can, canAny } = usePermission()

const loading = ref(true)
const data = ref<DashboardData | null>(null)
const feedItems = ref<ActivityFeedItem[]>([])
const feedLoading = ref(false)
const feedPage = ref(1)
const feedHasMore = ref(true)
const { dateFrom, dateTo } = useDefaultDateRange()

const showManagerSections = canAny('view-reports')

async function fetchDashboard() {
  loading.value = true
  try {
    const params: DashboardFilters = {}
    if (dateFrom.value) params.date_from = dateFrom.value
    if (dateTo.value) params.date_to = dateTo.value
    data.value = await dashboardService.index(params)
  } finally {
    loading.value = false
  }
}

async function fetchFeed(reset = false) {
  if (reset) {
    feedPage.value = 1
    feedItems.value = []
    feedHasMore.value = true
  }
  feedLoading.value = true
  try {
    const res = await dashboardService.activityFeed({ page: feedPage.value, per_page: 10 })
    feedItems.value.push(...res.data)
    const currentPage = res.meta?.current_page ?? res.current_page ?? 1
    const lastPage = res.meta?.last_page ?? res.last_page ?? 1
    feedHasMore.value = currentPage < lastPage
  } finally {
    feedLoading.value = false
  }
}

function loadMoreFeed() {
  feedPage.value++
  fetchFeed()
}

function applyDateFilter() {
  fetchDashboard()
}

function formatCurrency(val: string): string {
  return `$${parseFloat(val).toLocaleString()}`
}

function formatPercent(val: string): string {
  return `${val}%`
}

function canNavigateToEntity(item: ActivityFeedItem): boolean {
  if (!item.entity_type) return false
  if (item.entity_type === 'task') return can('view-tasks')
  if (!item.entity_id) return false
  if (item.entity_type === 'customer') return can('view-customers')
  if (item.entity_type === 'campaign') return can('view-campaigns')
  if (item.entity_type === 'product') return can('view-products')
  return false
}

function navigateToEntity(item: ActivityFeedItem) {
  if (item.entity_type === 'task') {
    router.push({ name: 'tasks.index' })
  } else if (item.entity_type && item.entity_id && ['customer', 'campaign', 'product'].includes(item.entity_type)) {
    router.push({ name: `${item.entity_type}s.show`, params: { id: item.entity_id } })
  }
}

const FEED_ICONS: Record<string, string> = {
  customer_created: 'bi-people text-success',
  customer_converted: 'bi-arrow-right-circle text-success',
  customer_assigned: 'bi-person-check text-success',
  customer_lead_status_changed: 'bi-arrow-left-right text-info',
  opportunity_created: 'bi-kanban text-primary',
  opportunity_stage_changed: 'bi-arrow-left-right text-info',
  customer_activity_logged: 'bi-activity text-warning',
  task_completed: 'bi-check2-square text-success',
  campaign_sent: 'bi-envelope text-primary',
}

onMounted(() => {
  fetchDashboard()
  fetchFeed()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>{{ t('nav.dashboard') }}</h2>
      <div class="d-flex gap-2 align-items-center">
        <input v-model="dateFrom" type="date" class="form-control form-control-sm" :placeholder="t('reports.dateFrom')">
        <input v-model="dateTo" type="date" class="form-control form-control-sm" :placeholder="t('reports.dateTo')">
        <button class="btn btn-sm btn-primary" @click="applyDateFilter">
          <i class="bi bi-funnel"></i>
        </button>
      </div>
    </div>

    <div v-if="loading" class="row g-3">
      <div class="col-lg-6">
        <SkeletonCard :lines="4" />
      </div>
      <div class="col-lg-6">
        <SkeletonCard :lines="4" />
      </div>
      <div class="col-lg-4">
        <SkeletonCard :lines="3" />
      </div>
      <div class="col-lg-4">
        <SkeletonCard :lines="3" />
      </div>
      <div class="col-lg-4">
        <SkeletonCard :lines="3" />
      </div>
    </div>

    <template v-else-if="data">
      <!-- Row 1: Pipeline + Revenue -->
      <div class="row g-3 mb-3">
        <div class="col-lg-6">
          <BaseCard :title="t('dashboard.pipeline')" icon="bi-funnel">
            <div class="row text-center mb-3">
              <div class="col-3">
                <div class="fs-6 fw-bold text-primary text-truncate" :title="String(data.pipeline.total_leads)">{{ data.pipeline.total_leads }}</div>
                <small class="text-muted">{{ t('dashboard.totalLeads') }}</small>
              </div>
              <div class="col-3">
                <div class="fs-6 fw-bold text-success text-truncate" :title="formatCurrency(data.pipeline.won_value)">{{ formatCurrency(data.pipeline.won_value) }}</div>
                <small class="text-muted">{{ t('dashboard.wonValue') }}</small>
              </div>
              <div class="col-3">
                <div class="fs-6 fw-bold text-warning text-truncate" :title="formatCurrency(data.pipeline.pipeline_value)">{{ formatCurrency(data.pipeline.pipeline_value) }}</div>
                <small class="text-muted">{{ t('dashboard.pipelineValue') }}</small>
              </div>
              <div class="col-3">
                <div class="fs-6 fw-bold text-info text-truncate" :title="formatPercent(data.pipeline.conversion_rate)">{{ formatPercent(data.pipeline.conversion_rate) }}</div>
                <small class="text-muted">{{ t('dashboard.conversionRate') }}</small>
              </div>
            </div>
            <PipelineChart :by-status="data.pipeline.by_status" />
          </BaseCard>
        </div>
        <div class="col-lg-6">
          <BaseCard :title="t('dashboard.revenue')" icon="bi-graph-up">
            <div class="row text-center mb-3">
              <div class="col-4">
                <div class="fs-6 fw-bold text-primary text-truncate" :title="formatCurrency(data.revenue.this_period)">{{ formatCurrency(data.revenue.this_period) }}</div>
                <small class="text-muted">{{ t('dashboard.thisPeriod') }}</small>
              </div>
              <div class="col-4">
                <div class="fs-6 fw-bold text-secondary text-truncate" :title="formatCurrency(data.revenue.previous_period)">{{ formatCurrency(data.revenue.previous_period) }}</div>
                <small class="text-muted">{{ t('dashboard.previousPeriod') }}</small>
              </div>
              <div class="col-4">
                <div :class="['fs-6 fw-bold text-truncate', parseFloat(data.revenue.change_percent) >= 0 ? 'text-success' : 'text-danger']" :title="formatPercent(data.revenue.change_percent)">
                  {{ parseFloat(data.revenue.change_percent) >= 0 ? '+' : '' }}{{ formatPercent(data.revenue.change_percent) }}
                </div>
                <small class="text-muted">{{ t('dashboard.change') }}</small>
              </div>
            </div>
            <RevenueChart :months="data.revenue.by_month" />
          </BaseCard>
        </div>
      </div>

      <!-- Row 2: Tasks + Customers -->
      <div class="row g-3 mb-3">
        <div class="col-lg-6">
          <BaseCard :title="t('dashboard.tasks')" icon="bi-check2-square">
            <div class="row text-center">
              <div class="col">
                <div class="fs-3 fw-bold text-danger">{{ data.tasks.overdue }}</div>
                <small class="text-muted">{{ t('dashboard.overdue') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-warning">{{ data.tasks.due_today }}</div>
                <small class="text-muted">{{ t('dashboard.dueToday') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-info">{{ data.tasks.upcoming_7_days }}</div>
                <small class="text-muted">{{ t('dashboard.upcoming7Days') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-primary">{{ data.tasks.in_progress }}</div>
                <small class="text-muted">{{ t('dashboard.inProgress') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-success">{{ data.tasks.completed_this_period }}</div>
                <small class="text-muted">{{ t('dashboard.completedThisPeriod') }}</small>
              </div>
            </div>
            <template v-if="can('view-tasks')" #footer>
              <router-link :to="{ name: 'tasks.index' }" class="text-decoration-none">
                <small>{{ t('common.view') }} {{ t('nav.tasks') }} <i class="bi bi-arrow-right"></i></small>
              </router-link>
            </template>
          </BaseCard>
        </div>
        <div class="col-lg-6">
          <BaseCard :title="t('dashboard.customers')" icon="bi-people">
            <div class="row text-center">
              <div class="col">
                <div class="fs-3 fw-bold text-success">{{ data.customers.by_status?.active ?? 0 }}</div>
                <small class="text-muted">{{ t('dashboard.activeCustomers') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-warning">{{ data.customers.by_status?.at_risk ?? 0 }}</div>
                <small class="text-muted">{{ t('dashboard.atRiskCustomers') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-info">{{ data.customers.avg_health_score }}</div>
                <small class="text-muted">{{ t('dashboard.avgHealthScore') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-danger">{{ data.customers.needing_follow_up }}</div>
                <small class="text-muted">{{ t('dashboard.needingFollowUp') }}</small>
              </div>
            </div>
            <div class="text-center mt-3">
              <span class="text-muted">{{ t('dashboard.totalWonOpportunityRevenue') }}:</span>
              <span class="fw-bold ms-1">{{ formatCurrency(data.customers.total_won_opportunity_revenue) }}</span>
            </div>
            <template v-if="can('view-customers')" #footer>
              <router-link :to="{ name: 'customers.index' }" class="text-decoration-none">
                <small>{{ t('common.view') }} {{ t('nav.customers') }} <i class="bi bi-arrow-right"></i></small>
              </router-link>
            </template>
          </BaseCard>
        </div>
      </div>

      <!-- Row 3: Top Performers + Campaigns (admin/manager only) -->
      <div v-if="showManagerSections" class="row g-3 mb-3">
        <div class="col-lg-6" v-if="data.top_performers">
          <BaseCard :title="t('dashboard.topPerformers')" icon="bi-trophy">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>{{ t('common.name') }}</th>
                    <th class="text-end">{{ t('dashboard.leadsWon') }}</th>
                    <th class="text-end">{{ t('dashboard.wonValue') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(performer, idx) in data.top_performers" :key="performer.user?.id ?? idx">
                    <td>
                      <span v-if="idx === 0" class="text-warning"><i class="bi bi-trophy-fill"></i></span>
                      <span v-else>{{ idx + 1 }}</span>
                    </td>
                    <td>{{ performer.user?.name ?? '—' }}</td>
                    <td class="text-end">{{ performer.leads_won }}</td>
                    <td class="text-end">{{ formatCurrency(performer.won_value) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BaseCard>
        </div>
        <div class="col-lg-6" v-if="data.campaigns">
          <BaseCard :title="t('dashboard.campaigns')" icon="bi-envelope">
            <div class="row text-center">
              <div class="col-4">
                <div class="fs-3 fw-bold text-primary">{{ data.campaigns.active }}</div>
                <small class="text-muted">{{ t('dashboard.activeCampaigns') }}</small>
              </div>
              <div class="col-4">
                <div class="fs-3 fw-bold text-success">{{ data.campaigns.total_sent_this_period }}</div>
                <small class="text-muted">{{ t('dashboard.totalSent') }}</small>
              </div>
              <div class="col-4">
                <div class="fs-3 fw-bold text-info">{{ data.campaigns.total_recipients }}</div>
                <small class="text-muted">{{ t('dashboard.totalRecipients') }}</small>
              </div>
            </div>
            <template v-if="can('view-campaigns')" #footer>
              <router-link :to="{ name: 'campaigns.index' }" class="text-decoration-none">
                <small>{{ t('common.view') }} {{ t('nav.campaigns') }} <i class="bi bi-arrow-right"></i></small>
              </router-link>
            </template>
          </BaseCard>
        </div>
      </div>

      <!-- Row 4: Activity Feed -->
      <BaseCard :title="t('dashboard.activityFeed')" icon="bi-activity">
        <div v-if="feedItems.length === 0 && !feedLoading" class="text-center text-muted py-3">
          {{ t('common.noData') }}
        </div>
        <div v-else class="list-group list-group-flush">
          <div v-for="item in feedItems" :key="item.id" class="list-group-item d-flex align-items-start gap-3 px-0">
            <div class="flex-shrink-0">
              <i :class="FEED_ICONS[item.type] || 'bi-circle text-secondary'" class="fs-5"></i>
            </div>
            <div class="flex-grow-1">
              <div class="mb-1">{{ item.description }}</div>
              <small class="text-muted">
                <i class="bi bi-person me-1"></i>{{ item.user?.name ?? t('nav.system') }}
                <span class="mx-2">&middot;</span>
                {{ new Date(item.created_at).toLocaleString() }}
              </small>
            </div>
            <button
              v-if="canNavigateToEntity(item)"
              class="btn btn-sm btn-outline-secondary flex-shrink-0"
              @click="navigateToEntity(item)"
            >
              <i class="bi bi-box-arrow-up-right"></i>
            </button>
          </div>
        </div>
        <div v-if="feedLoading" class="text-center py-3">
          <BaseSpinner />
        </div>
        <template #footer v-if="feedHasMore && !feedLoading">
          <button class="btn btn-sm btn-outline-primary w-100" @click="loadMoreFeed">
            {{ t('common.view') }} {{ t('dashboard.activityFeed') }}
          </button>
        </template>
      </BaseCard>
    </template>
  </div>
</template>
