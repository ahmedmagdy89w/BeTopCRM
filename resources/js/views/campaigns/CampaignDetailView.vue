<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { usePermission } from '@/composables/usePermission'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { campaignService } from '@/services/campaignService'
import type { CampaignRecipient } from '@/services/campaignService'
import type { Campaign } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import type { BadgeVariant } from '@/utils/constants'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can, canAny } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const id = Number(route.params.id)
const campaign = ref<Campaign | null>(null)
const loading = ref(true)
const recipients = ref<CampaignRecipient[]>([])
const recipientsLoading = ref(false)
const recipientsPage = ref(1)
const recipientsLastPage = ref(1)

const FILTER_LABELS: Record<string, string> = {
  is_lead: 'campaigns.filters.audience',
  type: 'campaigns.filters.type',
  status: 'campaigns.filters.status',
  lead_status: 'campaigns.filters.status',
  effective_status: 'campaigns.filters.status',
  search: 'campaigns.filters.search',
  source: 'campaigns.filters.source',
  assigned_to: 'campaigns.filters.assignedTo',
  account_manager_id: 'campaigns.filters.accountManager',
  health_below: 'campaigns.filters.healthBelow',
  follow_up_due: 'campaigns.filters.followUpDue',
  contact: 'campaigns.filters.contact',
  city: 'campaigns.filters.city',
  address: 'campaigns.filters.address',
  score_min: 'campaigns.filters.scoreMin',
  score_max: 'campaigns.filters.scoreMax',
  customer_id: 'campaigns.filters.customerId',
  tag: 'campaigns.filters.tag',
}

const segmentFilters = computed(() => Object.entries(campaign.value?.segment_filters ?? {}).map(([key, value]) => ({
  key,
  label: FILTER_LABELS[key] ? t(FILTER_LABELS[key]) : key,
  value: formatFilterValue(key, value),
})))

async function fetch() {
  loading.value = true
  try {
    campaign.value = await campaignService.get(id)
    await fetchRecipients()
  } finally {
    loading.value = false
  }
}

async function fetchRecipients(page = recipientsPage.value) {
  recipientsLoading.value = true
  try {
    const response = await campaignService.recipients(id, { page, per_page: 15 })
    recipients.value = response.data
    recipientsPage.value = response.meta?.current_page ?? response.current_page ?? page
    recipientsLastPage.value = response.meta?.last_page ?? response.last_page ?? 1
  } finally {
    recipientsLoading.value = false
  }
}

async function handleSend() {
  const ok = await confirm(t('campaigns.confirmSend'))
  if (!ok) return
  await campaignService.send(id)
  fetch()
}

async function handleCancel() {
  const ok = await confirm(t('campaigns.confirmCancel'))
  if (!ok) return
  await campaignService.cancel(id)
  fetch()
}

const STATUS_COLORS: Record<string, BadgeVariant> = {
  draft: 'secondary',
  scheduled: 'info',
  sending: 'warning',
  sent: 'success',
  cancelled: 'danger',
  pending: 'secondary',
  failed: 'danger',
}

function formatFilterValue(key: string, value: unknown): string {
  if (key === 'is_lead') return value ? t('customers.leadsTab') : t('customers.customersTab')
  if (key === 'type' && (value === 'individual' || value === 'company')) return t(`customers.${value}`)
  if (key === 'source' && typeof value === 'string') return t(`customers.sources.${value}`)
  if (key === 'follow_up_due') return value ? t('common.yes') : t('common.no')

  return Array.isArray(value) ? value.join(', ') : String(value ?? '—')
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0 text-break">{{ campaign?.name ?? t('campaigns.title') }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.back()">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <div v-if="loading" class="text-center py-5">
      <BaseSpinner />
    </div>

    <template v-else-if="campaign">
      <div v-if="canAny('update-campaigns', 'send-campaigns')" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <BaseButton
          v-if="can('update-campaigns')"
          variant="outline-primary"
          icon="bi-pencil"
          :to="{ name: 'campaigns.edit', params: { id: campaign.id } }"
        >
          {{ t('common.edit') }}
        </BaseButton>
        <BaseButton
          v-if="campaign.status === 'draft' && can('send-campaigns')"
          variant="success"
          icon="bi-send"
          @click="handleSend"
        >
          {{ t('campaigns.send') }}
        </BaseButton>
        <BaseButton
          v-if="(campaign.status === 'draft' || campaign.status === 'scheduled') && can('update-campaigns')"
          variant="outline-warning"
          icon="bi-x-circle"
          @click="handleCancel"
        >
          {{ t('campaigns.cancelCampaign') }}
        </BaseButton>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-6">
          <BaseCard :title="t('common.details')" icon="bi-info-circle">
            <dl class="row mb-0">
              <dt class="col-sm-4">{{ t('campaigns.name') }}</dt>
              <dd class="col-sm-8">{{ campaign.name }}</dd>

              <dt class="col-sm-4">{{ t('campaigns.template') }}</dt>
              <dd class="col-sm-8">{{ campaign.template?.name ?? '—' }}</dd>

              <dt class="col-sm-4">{{ t('campaigns.status') }}</dt>
              <dd class="col-sm-8">
                <BaseBadge :variant="STATUS_COLORS[campaign.status] ?? 'secondary'" pill>
                  {{ t(`campaigns.statuses.${campaign.status}`) }}
                </BaseBadge>
              </dd>

              <dt class="col-sm-4">{{ t('campaigns.scheduledAt') }}</dt>
              <dd class="col-sm-8">{{ campaign.scheduled_at ? new Date(campaign.scheduled_at).toLocaleString() : '—' }}</dd>

              <dt class="col-sm-4">{{ t('common.createdAt') }}</dt>
              <dd class="col-sm-8">{{ new Date(campaign.created_at).toLocaleString() }}</dd>
            </dl>
          </BaseCard>
        </div>

        <div class="col-lg-6">
          <BaseCard :title="t('campaigns.recipients')" icon="bi-people">
            <div v-if="campaign.stats" class="row text-center">
              <div class="col">
                <div class="fs-3 fw-bold text-primary">{{ campaign.stats.total_recipients }}</div>
                <small class="text-muted">{{ t('campaigns.totalRecipients') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-success">{{ campaign.stats.sent }}</div>
                <small class="text-muted">{{ t('campaigns.sent') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-danger">{{ campaign.stats.failed }}</div>
                <small class="text-muted">{{ t('campaigns.failed') }}</small>
              </div>
              <div class="col">
                <div class="fs-3 fw-bold text-warning">{{ campaign.stats.pending }}</div>
                <small class="text-muted">{{ t('campaigns.pending') }}</small>
              </div>
            </div>

            <!-- Progress bar -->
            <div v-if="campaign.stats && campaign.stats.total_recipients > 0" class="mt-3">
              <div class="progress" style="height: 8px;">
                <div
                  class="progress-bar bg-success"
                  :style="{ width: `${(campaign.stats.sent / campaign.stats.total_recipients) * 100}%` }"
                ></div>
                <div
                  class="progress-bar bg-danger"
                  :style="{ width: `${(campaign.stats.failed / campaign.stats.total_recipients) * 100}%` }"
                ></div>
              </div>
            </div>
          </BaseCard>
        </div>
      </div>

      <BaseCard v-if="segmentFilters.length" :title="t('campaigns.segmentFilters')" icon="bi-funnel">
        <dl class="row mb-0">
          <template v-for="filter in segmentFilters" :key="filter.key">
            <dt class="col-sm-4 col-lg-3 text-muted">{{ filter.label }}</dt>
            <dd class="col-sm-8 col-lg-9 text-break mb-2">
              <BaseBadge variant="light">{{ filter.value }}</BaseBadge>
            </dd>
          </template>
        </dl>
      </BaseCard>

      <BaseCard :title="t('campaigns.recipients')" icon="bi-people" class="mt-3">
        <div v-if="recipientsLoading" class="d-flex justify-content-center py-4">
          <BaseSpinner size="sm" />
        </div>
        <div v-else-if="!recipients.length" class="text-center text-muted py-4">
          {{ t('common.noData') }}
        </div>
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>{{ t('common.name') }}</th>
                <th>{{ t('common.email') }}</th>
                <th>{{ t('campaigns.status') }}</th>
                <th>{{ t('campaigns.sentAt') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="recipient in recipients" :key="recipient.id">
                <td>{{ recipient.customer?.name || '—' }}</td>
                <td class="text-break">{{ recipient.email }}</td>
                <td>
                  <BaseBadge :variant="STATUS_COLORS[recipient.status]" pill>
                    {{ t(`campaigns.statuses.${recipient.status}`) }}
                  </BaseBadge>
                  <small v-if="recipient.failed_reason" class="d-block text-danger mt-1">
                    {{ recipient.failed_reason }}
                  </small>
                </td>
                <td>{{ recipient.sent_at ? new Date(recipient.sent_at).toLocaleString() : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="recipientsLastPage > 1" class="d-flex justify-content-between align-items-center mt-3">
          <BaseButton
            variant="outline-secondary"
            size="sm"
            icon="bi-chevron-left"
            :disabled="recipientsPage <= 1 || recipientsLoading"
            @click="fetchRecipients(recipientsPage - 1)"
          >
            {{ t('campaigns.previousPage') }}
          </BaseButton>
          <small class="text-muted">{{ t('campaigns.pageInfo', { current: recipientsPage, total: recipientsLastPage }) }}</small>
          <BaseButton
            variant="outline-secondary"
            size="sm"
            icon="bi-chevron-right"
            icon-position="end"
            :disabled="recipientsPage >= recipientsLastPage || recipientsLoading"
            @click="fetchRecipients(recipientsPage + 1)"
          >
            {{ t('campaigns.nextPage') }}
          </BaseButton>
        </div>
      </BaseCard>
    </template>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
