<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { opportunityService } from '@/services/opportunityService'
import { userService } from '@/services/userService'
import { OPPORTUNITY_STAGE_COLORS, OPEN_OPPORTUNITY_STAGES, CLOSED_OPPORTUNITY_STAGES } from '@/utils/constants'
import { daysAgoInput } from '@/utils/formatters'
import type { Opportunity } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import UserAvatar from '@/components/shared/UserAvatar.vue'
import CustomFieldFilterBar from '@/components/shared/CustomFieldFilterBar.vue'
import FilterControl from '@/components/shared/FilterControl.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const users = ref<{ id: number; name: string }[]>([])
const statusFilter = ref<string>('')
const assigneeFilter = ref<string>('')
const dateFrom = ref<string>(daysAgoInput(29))
const dateTo = ref<string>('')

const statusOptions = computed(() => [
  { label: t('common.open'), value: 'open' },
  { label: t('common.closed'), value: 'closed' },
])
const assigneeOptions = computed(() => users.value.map(user => ({ label: user.name, value: user.id })))
const stageOptions = computed(() => [...OPEN_OPPORTUNITY_STAGES, ...CLOSED_OPPORTUNITY_STAGES].map(stage => ({
  label: t(`opportunities.stages.${stage}`),
  value: stage,
})))

const columns: DataTableColumn[] = [
  { key: 'title', label: t('common.name'), sortable: false },
  { key: 'customer.display_name', label: t('opportunities.customer'), sortable: false },
  { key: 'stage', label: t('opportunities.stage'), sortable: true, type: 'badge' },
  { key: 'amount', label: t('opportunities.amount'), sortable: true, align: 'end' },
  { key: 'assigned_to.name', label: t('opportunities.assignee'), sortable: false },
  { key: 'expected_close_date', label: t('customers.expectedCloseDate'), sortable: true, type: 'date' },
  { key: 'created_at', label: t('common.createdAt'), sortable: true, type: 'date' },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter, refresh,
} = useDataTable<Opportunity>(
  (params) => opportunityService.listGlobal(params as Parameters<typeof opportunityService.listGlobal>[0]),
  { defaultSort: 'created_at', defaultSortDir: 'desc', immediate: true, defaultFilters: { date_from: dateFrom.value } },
)

function goToCustomer(row: unknown) {
  const opp = row as Opportunity
  router.push({ name: 'opportunities.show', params: { customerId: opp.customer_id, id: opp.id } })
}

function updateStatusFilter(value: unknown) {
  statusFilter.value = typeof value === 'string' ? value : ''
  onFilter('status', statusFilter.value || undefined)
}

function updateAssigneeFilter(value: unknown) {
  assigneeFilter.value = value == null ? '' : String(value)
  onFilter('assigned_to', value)
}

function updateDateFromFilter(value: unknown) {
  dateFrom.value = typeof value === 'string' ? value : ''
  onFilter('date_from', dateFrom.value || undefined)
}

function updateDateToFilter(value: unknown) {
  dateTo.value = typeof value === 'string' ? value : ''
  onFilter('date_to', dateTo.value || undefined)
}

async function handleDelete(opp: Opportunity) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await opportunityService.delete(opp.customer_id, opp.id)
  refresh()
}

onMounted(async () => {
  try {
    // Users list needs view-users; everyone else uses the assignable
    // endpoint (view-assignable-users) so the assignee filter still works.
    const res = can('view-users')
      ? await userService.list({ per_page: 100 })
      : await userService.assignable({ per_page: 100 })
    users.value = res.data ?? []
  } catch { /* ignore */ }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h2 class="mb-0">{{ t('opportunities.title') }} — {{ t('opportunities.table') }}</h2>
      <div class="d-flex gap-2">
        <BaseButton variant="outline-secondary" icon="bi-kanban" :to="{ name: 'opportunities.index' }">
          {{ t('opportunities.board') }}
        </BaseButton>
        <BaseButton
          v-if="can('create-opportunities')"
          variant="primary"
          icon="bi-plus-lg"
          :to="{ name: 'opportunities.createGlobal' }"
        >
          {{ t('opportunities.newOpportunity') }}
        </BaseButton>
      </div>
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
      @row-click="goToCustomer"
    >
      <template #filters>
        <FilterControl :label="t('common.status')" :model-value="statusFilter" :options="statusOptions" @update:model-value="updateStatusFilter" />
        <FilterControl :label="t('opportunities.assignee')" :model-value="assigneeFilter" :options="assigneeOptions" @update:model-value="updateAssigneeFilter" />
        <FilterControl :label="t('opportunities.dateFrom')" type="date" :model-value="dateFrom" @update:model-value="updateDateFromFilter" />
        <FilterControl :label="t('opportunities.dateTo')" type="date" :model-value="dateTo" @update:model-value="updateDateToFilter" />
        <FilterControl
          :label="t('opportunities.stage')"
          :options="stageOptions"
          @update:model-value="onFilter('stage', $event)"
        />
        <CustomFieldFilterBar entity-type="opportunity" @change="onFilter" />
      </template>

      <template #cell-title="{ row }">
        <strong>{{ (row as Opportunity).title }}</strong>
      </template>
      <template #cell-stage="{ value }">
        <BaseBadge :variant="OPPORTUNITY_STAGE_COLORS[value as Opportunity['stage']] ?? 'secondary'" pill>
          {{ t(`opportunities.stages.${value}`) }}
        </BaseBadge>
      </template>
      <template #cell-amount="{ value, row }">
        {{ value ? `${(row as Opportunity).currency} ${Number(value).toLocaleString()}` : '—' }}
      </template>
      <template #cell-assigned_to.name="{ row }">
        <span class="d-inline-flex align-items-center gap-1">
          <UserAvatar :name="(row as Opportunity).assigned_to?.name" :id="(row as Opportunity).assigned_to?.id" :size="22" />
          <small>{{ (row as Opportunity).assigned_to?.name ?? '—' }}</small>
        </span>
      </template>
      <template #cell-expected_close_date="{ value }">
        {{ value ? new Date(value as string).toLocaleDateString() : '—' }}
      </template>
      <template #cell-created_at="{ value }">
        {{ value ? new Date(value as string).toLocaleDateString() : '—' }}
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="can('update-opportunities')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'opportunities.edit', params: { customerId: (row as Opportunity).customer_id, id: (row as Opportunity).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('delete-opportunities')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as Opportunity)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
