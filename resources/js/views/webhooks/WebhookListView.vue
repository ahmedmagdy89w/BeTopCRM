<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { webhookService } from '@/services/webhookService'
import type { WebhookEndpoint } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const columns: DataTableColumn[] = [
  { key: 'url', label: t('webhooks.url'), sortable: true },
  { key: 'events', label: t('webhooks.events'), sortable: false },
  { key: 'is_active', label: t('common.status'), sortable: true },
  { key: 'failure_count', label: t('webhooks.failures'), sortable: true, align: 'center' },
  { key: 'last_triggered_at', label: t('webhooks.lastTriggered'), sortable: true, type: 'date' },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, refresh,
} = useDataTable<WebhookEndpoint>(
  (params) => webhookService.list(params),
  { defaultSort: 'created_at', defaultSortDir: 'desc', immediate: true },
)

function goToEdit(row: unknown) {
  if (!can('update-webhooks')) return
  const wh = row as WebhookEndpoint
  router.push({ name: 'webhooks.edit', params: { id: wh.id } })
}

function eventLabel(event: string): string {
  return t(`webhooks.eventNames.${event}`)
}

async function handleDelete(wh: WebhookEndpoint) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await webhookService.delete(wh.id)
  refresh()
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('webhooks.title') }}</h2>
      <BaseButton
        v-if="can('create-webhooks')"
        variant="primary"
        icon="bi-plus-lg"
        :to="{ name: 'webhooks.create' }"
      >
        {{ t('webhooks.newWebhook') }}
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
      @update:current-page="currentPage = $event"
      @update:per-page="perPage = $event"
      @update:sort-by="sortBy = $event"
      @update:sort-dir="sortDir = $event"
      @search="onSearch"
      @row-click="goToEdit"
    >
      <template #cell-url="{ value }">
        <code class="small">{{ value }}</code>
      </template>
      <template #cell-events="{ row }">
        <span v-for="ev in (row as WebhookEndpoint).events.slice(0, 3)" :key="ev" class="badge text-bg-light me-1">{{ eventLabel(ev) }}</span>
        <span v-if="(row as WebhookEndpoint).events.length > 3" class="badge text-bg-secondary">+{{ (row as WebhookEndpoint).events.length - 3 }}</span>
      </template>
      <template #cell-is_active="{ value }">
        <BaseBadge :variant="value ? 'success' : 'secondary'" pill>
          {{ value ? t('common.active') : t('common.inactive') }}
        </BaseBadge>
      </template>
      <template #cell-failure_count="{ value }">
        <span :class="Number(value) > 0 ? 'text-danger fw-bold' : 'text-muted'">{{ value }}</span>
      </template>
      <template #cell-last_triggered_at="{ value }">
        {{ value ? new Date(value as string).toLocaleString() : '—' }}
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="can('update-webhooks')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'webhooks.edit', params: { id: (row as WebhookEndpoint).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('delete-webhooks')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as WebhookEndpoint)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
