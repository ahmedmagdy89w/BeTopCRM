<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { customerActivityService } from '@/services/customerActivityService'
import type { CustomerActivity } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import FilterControl from '@/components/shared/FilterControl.vue'
import UserAvatar from '@/components/shared/UserAvatar.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const columns: DataTableColumn[] = [
  { key: 'subject', label: t('activities.subject'), sortable: false },
  { key: 'type', label: t('common.type'), sortable: true },
  { key: 'customer', label: t('activities.customer'), sortable: false },
  { key: 'owner', label: t('activities.owner'), sortable: false },
  { key: 'due_date', label: t('customers.activityDueDate'), sortable: true },
  { key: 'status', label: t('activities.status'), sortable: false },
  { key: 'reminder', label: t('activities.reminder'), sortable: false },
]

const typeOptions = [
  'onboarding', 'support', 'check_in', 'review', 'upsell',
  'renewal', 'training', 'complaint', 'follow_up', 'whatsapp', 'general',
].map(type => ({ label: t(`customers.activityTypes.${type}`), value: type }))

const statusOptions = ['upcoming', 'due_today', 'overdue', 'completed'].map(status => ({
  label: t(`activities.statuses.${status}`),
  value: status,
}))

const dueFrom = ref('')
const dueTo = ref('')

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter, refresh,
} = useDataTable<CustomerActivity>(
  (params) => customerActivityService.listGlobal(params as Record<string, unknown> & { page?: number }),
  { defaultSort: 'due_date', defaultSortDir: 'asc', immediate: true },
)

async function handleComplete(activity: CustomerActivity) {
  if (!activity.customer) return
  await customerActivityService.complete(activity.customer.id, activity.id)
  refresh()
}

async function handleDelete(activity: CustomerActivity) {
  if (!activity.customer) return
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await customerActivityService.delete(activity.customer.id, activity.id)
  refresh()
}

function updateDueFromFilter(value: unknown) {
  dueFrom.value = typeof value === 'string' ? value : ''
  onFilter('due_from', dueFrom.value || undefined)
}

function updateDueToFilter(value: unknown) {
  dueTo.value = typeof value === 'string' ? value : ''
  onFilter('due_to', dueTo.value || undefined)
}

function openCustomer(activity: CustomerActivity) {
  if (!can('view-customers')) return
  if (activity.customer) {
    router.push({ name: 'customers.show', params: { id: activity.customer.id } })
  }
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('activities.title') }}</h2>
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
    >
      <template #filters>
        <FilterControl :label="t('common.type')" :options="typeOptions" @update:model-value="onFilter('type', $event)" />
        <FilterControl :label="t('activities.status')" :options="statusOptions" @update:model-value="onFilter('status', $event)" />
        <FilterControl :label="t('reports.dateFrom')" type="date" :model-value="dueFrom" @update:model-value="updateDueFromFilter" />
        <FilterControl :label="t('reports.dateTo')" type="date" :model-value="dueTo" @update:model-value="updateDueToFilter" />
      </template>

      <template #cell-subject="{ row }">
        <strong>{{ (row as CustomerActivity).subject }}</strong>
        <div v-if="(row as CustomerActivity).description" class="text-muted small text-truncate" style="max-width: 22rem">
          {{ (row as CustomerActivity).description }}
        </div>
      </template>
      <template #cell-type="{ row }">
        <BaseBadge variant="info" pill>{{ t(`customers.activityTypes.${(row as CustomerActivity).type}`) }}</BaseBadge>
      </template>
      <template #cell-customer="{ row }">
        <a
          v-if="(row as CustomerActivity).customer && can('view-customers')"
          href="#"
          @click.prevent="openCustomer(row as CustomerActivity)"
        >
          {{ (row as CustomerActivity).customer!.display_name }}
        </a>
        <span v-else-if="(row as CustomerActivity).customer">{{ (row as CustomerActivity).customer!.display_name }}</span>
        <span v-else class="text-muted">—</span>
      </template>
      <template #cell-owner="{ row }">
        <span class="d-inline-flex align-items-center gap-1">
          <UserAvatar :name="(row as CustomerActivity).user.name" :id="(row as CustomerActivity).user.id" :size="24" />
          <small>{{ (row as CustomerActivity).user.name }}</small>
        </span>
      </template>
      <template #cell-due_date="{ row }">
        <span
          v-if="(row as CustomerActivity).due_date"
          :class="{ 'text-danger fw-semibold': (row as CustomerActivity).is_overdue }"
        >
          {{ new Date((row as CustomerActivity).due_date!).toLocaleString() }}
          <i v-if="(row as CustomerActivity).is_overdue" class="bi bi-exclamation-triangle-fill text-danger ms-1"></i>
        </span>
        <span v-else class="text-muted">—</span>
      </template>
      <template #cell-status="{ row }">
        <BaseBadge :variant="(row as CustomerActivity).is_completed ? 'success' : (row as CustomerActivity).is_overdue ? 'danger' : 'secondary'" pill>
          {{
            (row as CustomerActivity).is_completed
              ? t('activities.statuses.completed')
              : (row as CustomerActivity).is_overdue
                ? t('activities.statuses.overdue')
                : t('tasks.statuses.pending')
          }}
        </BaseBadge>
      </template>
      <template #cell-reminder="{ row }">
        <span v-if="(row as CustomerActivity).reminder_at" class="text-muted small">
          <i class="bi bi-bell me-1"></i>{{ new Date((row as CustomerActivity).reminder_at!).toLocaleString() }}
        </span>
        <span v-else class="text-muted">—</span>
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="!(row as CustomerActivity).is_completed && can('update-activities')"
            variant="outline-success"
            size="sm"
            icon="bi-check-lg"
            @click.stop="handleComplete(row as CustomerActivity)"
          />
          <BaseButton
            v-if="can('view-customers')"
            variant="outline-primary"
            size="sm"
            icon="bi-box-arrow-up-right"
            @click.stop="openCustomer(row as CustomerActivity)"
          />
          <BaseButton
            v-if="can('delete-activities')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as CustomerActivity)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
