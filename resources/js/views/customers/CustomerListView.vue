<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { customerService } from '@/services/customerService'
import { useTagStore } from '@/stores/tags'
import { LEAD_STATUS_COLORS, CUSTOMER_STATUS_COLORS, CUSTOMER_TYPE_COLORS, type BadgeVariant } from '@/utils/constants'
import type { Customer } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import TagList from '@/components/shared/TagList.vue'
import CustomFieldFilterBar from '@/components/shared/CustomFieldFilterBar.vue'
import FilterControl from '@/components/shared/FilterControl.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()
const tagStore = useTagStore()

type MainTab = 'leads' | 'customers'
const activeTab = ref<MainTab>('leads')
const typeFilter = ref<string>('')
const statusFilter = ref<string>('')
const contactQuery = ref<string>('')
const cityQuery = ref<string>('')

const columns: DataTableColumn[] = [
  { key: 'display_name', label: t('common.name'), sortable: true },
  { key: 'type', label: t('customers.type'), sortable: true, type: 'badge' },
  { key: 'effective_status', label: t('customers.status'), sortable: false, type: 'badge' },
  { key: 'email', label: t('common.email'), sortable: false },
  { key: 'phone', label: t('common.phone'), sortable: false },
  { key: 'score', label: t('customers.score'), sortable: true, align: 'center' },
  { key: 'assigned_to.name', label: t('customers.assignedTo'), sortable: false },
  { key: 'tags', label: t('tags.title'), sortable: false },
  { key: 'created_at', label: t('common.createdAt'), sortable: true, type: 'date' },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter, refresh,
} = useDataTable<Customer>(
  (params) => customerService.list({
    ...params as Record<string, unknown>,
    is_lead: activeTab.value === 'leads',
  } as Parameters<typeof customerService.list>[0]),
  { defaultSort: 'created_at', defaultSortDir: 'desc', immediate: true },
)

watch(activeTab, () => {
  statusFilter.value = ''
  onFilter('lead_status', undefined)
  onFilter('status', undefined)
  refresh()
})

const statusOptions = computed(() => activeTab.value === 'leads'
  ? Object.keys(LEAD_STATUS_COLORS)
  : Object.keys(CUSTOMER_STATUS_COLORS))

function displayNameOf(row: Customer): string {
  return row.display_name ?? row.company_name ?? (`${row.first_name ?? ''} ${row.last_name ?? ''}`.trim() || '—')
}

function statusVariant(row: Customer): BadgeVariant {
  if (row.is_lead) return (LEAD_STATUS_COLORS[row.lead_status as keyof typeof LEAD_STATUS_COLORS] ?? 'secondary') as BadgeVariant
  return (CUSTOMER_STATUS_COLORS[row.status as keyof typeof CUSTOMER_STATUS_COLORS] ?? 'secondary') as BadgeVariant
}

function statusLabel(row: Customer): string {
  return row.effective_status ?? (row.is_lead ? row.lead_status ?? '' : row.status)
}

function goToDetail(row: unknown) {
  const customer = row as Customer
  router.push({ name: 'customers.show', params: { id: customer.id } })
}

async function handleDelete(customer: Customer) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await customerService.delete(customer.id)
  refresh()
}

let contactDebounce: ReturnType<typeof setTimeout> | null = null
function onContactInput(value: unknown) {
  contactQuery.value = typeof value === 'string' ? value : ''
  const v = contactQuery.value.trim() || undefined
  if (contactDebounce) clearTimeout(contactDebounce)
  contactDebounce = setTimeout(() => onFilter('contact', v), 400)
}

let cityDebounce: ReturnType<typeof setTimeout> | null = null
function onCityInput(value: unknown) {
  cityQuery.value = typeof value === 'string' ? value : ''
  const v = cityQuery.value.trim() || undefined
  if (cityDebounce) clearTimeout(cityDebounce)
  cityDebounce = setTimeout(() => onFilter('city', v), 400)
}

function updateTypeFilter(value: unknown) {
  typeFilter.value = typeof value === 'string' ? value : ''
  onFilter('type', typeFilter.value || undefined)
}

function updateStatusFilter(value: unknown) {
  statusFilter.value = typeof value === 'string' ? value : ''
  onFilter(activeTab.value === 'leads' ? 'lead_status' : 'status', statusFilter.value || undefined)
}

const typeOptions = computed(() => [
  { label: t('customers.individual'), value: 'individual' },
  { label: t('customers.company'), value: 'company' },
])

const tagOptions = computed(() => tagStore.tags.map(tag => ({ label: tag.name, value: tag.id })))

onMounted(() => tagStore.fetchTags())
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('customers.title') }}</h2>
      <BaseButton
        v-if="can('create-customers')"
        variant="primary"
        icon="bi-plus-lg"
        :to="{ name: 'customers.create' }"
      >
        {{ t('customers.newCustomer') }}
      </BaseButton>
    </div>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'leads' }" @click="activeTab = 'leads'">
          <i class="bi bi-funnel me-1"></i>{{ t('customers.leadsTab') }}
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'customers' }" @click="activeTab = 'customers'">
          <i class="bi bi-people me-1"></i>{{ t('customers.customersTab') }}
        </button>
      </li>
    </ul>

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
      @row-click="goToDetail"
    >
      <template #filters>
        <FilterControl :label="t('customers.type')" :model-value="typeFilter" :options="typeOptions" @update:model-value="updateTypeFilter" />
        <FilterControl
          :label="t('customers.status')"
          :model-value="statusFilter"
          :options="statusOptions.map(status => ({ label: status, value: status }))"
          @update:model-value="updateStatusFilter"
        />
        <FilterControl :label="t('customers.contacts')" type="text" :model-value="contactQuery" @update:model-value="onContactInput" />
        <FilterControl :label="t('customers.addresses')" type="text" :model-value="cityQuery" @update:model-value="onCityInput" />
        <FilterControl :label="t('tags.title')" :options="tagOptions" @update:model-value="onFilter('tag', $event)" />
        <CustomFieldFilterBar entity-type="customer" @change="onFilter" />
      </template>

      <template #cell-display_name="{ row }">
        <strong>{{ displayNameOf(row as Customer) }}</strong>
      </template>
      <template #cell-type="{ value }">
        <BaseBadge :variant="CUSTOMER_TYPE_COLORS[value as string] ?? 'secondary'" pill>
          {{ value }}
        </BaseBadge>
      </template>
      <template #cell-effective_status="{ row }">
        <BaseBadge :variant="statusVariant(row as Customer)" pill>
          {{ statusLabel(row as Customer) }}
        </BaseBadge>
      </template>
      <template #cell-score="{ value, row }">
        <span v-if="(row as Customer).is_lead && value != null" class="badge rounded-pill" :class="Number(value) >= 70 ? 'bg-success' : Number(value) >= 40 ? 'bg-warning text-dark' : 'bg-secondary'">
          {{ value }}
        </span>
        <span v-else class="text-muted">—</span>
      </template>
      <template #cell-tags="{ row }">
        <TagList :tags="(row as Customer).tags ?? []" />
      </template>
      <template #cell-created_at="{ value }">
        {{ value ? new Date(value as string).toLocaleDateString() : '—' }}
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="can('update-customers')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'customers.edit', params: { id: (row as Customer).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('delete-customers')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as Customer)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
