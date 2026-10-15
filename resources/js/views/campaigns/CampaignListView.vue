<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useDataTable } from '@/composables/useDataTable'
import { usePermission } from '@/composables/usePermission'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { campaignService } from '@/services/campaignService'
import type { Campaign } from '@/types'
import type { DataTableColumn } from '@/types'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import type { BadgeVariant } from '@/utils/constants'

const { t } = useI18n()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const columns: DataTableColumn[] = [
  { key: 'name', label: t('campaigns.name'), sortable: true },
  { key: 'template', label: t('campaigns.template') },
  { key: 'status', label: t('campaigns.status'), sortable: true },
  { key: 'stats', label: t('campaigns.recipients') },
  { key: 'scheduled_at', label: t('campaigns.scheduledAt'), sortable: true },
  { key: 'actions', label: t('common.actions') },
]

const {
  rows,
  loading,
  total,
  currentPage,
  perPage,
  sortBy,
  sortDir,
  onSearch,
  onSort,
  onPageChange,
  onPerPageChange,
  refresh,
} = useDataTable<Campaign>((params) => campaignService.list(params), {
  defaultSort: 'created_at',
  defaultSortDir: 'desc',
})

async function handleSend(campaign: Campaign) {
  const ok = await confirm(t('campaigns.confirmSend'))
  if (!ok) return
  await campaignService.send(campaign.id)
  refresh()
}

async function handleCancel(campaign: Campaign) {
  const ok = await confirm(t('campaigns.confirmCancel'))
  if (!ok) return
  await campaignService.cancel(campaign.id)
  refresh()
}

async function handleDelete(campaign: Campaign) {
  const ok = await confirm(t('common.confirmDelete'))
  if (!ok) return
  await campaignService.delete(campaign.id)
  refresh()
}

const CAMPAIGN_STATUS_COLORS: Record<string, BadgeVariant> = {
  draft: 'secondary',
  scheduled: 'info',
  sending: 'warning',
  sent: 'success',
  cancelled: 'danger',
}

function getStatusColor(status: string): BadgeVariant {
  return CAMPAIGN_STATUS_COLORS[status] ?? 'secondary'
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('campaigns.title') }}</h2>
      <BaseButton
        v-if="can('create-campaigns')"
        variant="primary"
        icon="bi-plus-lg"
        :to="{ name: 'campaigns.create' }"
      >
        {{ t('campaigns.newCampaign') }}
      </BaseButton>
    </div>

    <BaseDataTable
      :columns="columns"
      :rows="rows"
      :loading="loading"
      :total="total"
      :current-page="currentPage"
      :per-page="perPage"
      :sort-by="sortBy"
      :sort-dir="sortDir"
      @search="onSearch"
      @sort="onSort"
      @page-change="onPageChange"
      @per-page-change="onPerPageChange"
    >
      <template #cell-name="{ row }">
        <router-link :to="{ name: 'campaigns.show', params: { id: (row as Campaign).id } }">
          {{ (row as Campaign).name }}
        </router-link>
      </template>

      <template #cell-template="{ row }">
        {{ (row as Campaign).template?.name ?? '—' }}
      </template>

      <template #cell-status="{ row }">
        <BaseBadge :variant="getStatusColor((row as Campaign).status)" pill>
          {{ t(`campaigns.statuses.${(row as Campaign).status}`) }}
        </BaseBadge>
      </template>

      <template #cell-stats="{ row }">
        <span class="text-nowrap">
          {{ (row as Campaign).stats?.sent ?? 0 }}/{{ (row as Campaign).stats?.total_recipients ?? 0 }}
        </span>
        <span v-if="(row as Campaign).stats?.failed" class="text-danger ms-2 small">
          {{ t('campaigns.failedCount', { count: (row as Campaign).stats?.failed }) }}
        </span>
      </template>

      <template #cell-scheduled_at="{ row }">
        {{ (row as Campaign).scheduled_at ? new Date((row as Campaign).scheduled_at as string).toLocaleString() : '—' }}
      </template>

      <template #cell-actions="{ row }">
        <div class="d-flex gap-1">
          <BaseButton
            :to="{ name: 'campaigns.show', params: { id: (row as Campaign).id } }"
            variant="outline-primary"
            size="sm"
            icon="bi-eye"
            :title="t('common.view')"
            :aria-label="t('common.view')"
          >
          </BaseButton>
          <BaseButton
            v-if="can('update-campaigns')"
            :to="{ name: 'campaigns.edit', params: { id: (row as Campaign).id } }"
            variant="outline-secondary"
            size="sm"
            icon="bi-pencil"
            :title="t('common.edit')"
            :aria-label="t('common.edit')"
          >
          </BaseButton>
          <BaseButton
            v-if="(row as Campaign).status === 'draft' && can('send-campaigns')"
            variant="outline-success"
            size="sm"
            icon="bi-send"
            :title="t('campaigns.send')"
            :aria-label="t('campaigns.send')"
            @click="handleSend(row as Campaign)"
          />
          <BaseButton
            v-if="((row as Campaign).status === 'draft' || (row as Campaign).status === 'scheduled') && can('update-campaigns')"
            variant="outline-warning"
            size="sm"
            icon="bi-x-circle"
            :title="t('campaigns.cancelCampaign')"
            :aria-label="t('campaigns.cancelCampaign')"
            @click="handleCancel(row as Campaign)"
          />
          <BaseButton
            v-if="(row as Campaign).status === 'draft' && can('delete-campaigns')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            :title="t('common.delete')"
            :aria-label="t('common.delete')"
            @click="handleDelete(row as Campaign)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
