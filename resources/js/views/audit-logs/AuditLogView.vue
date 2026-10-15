<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDataTable } from '@/composables/useDataTable'
import { auditLogService } from '@/services/auditLogService'
import type { AuditLog } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import FilterControl from '@/components/shared/FilterControl.vue'
import type { BadgeVariant } from '@/utils/constants'

const { t } = useI18n()

const ACTION_COLORS: Record<string, BadgeVariant> = {
  created: 'success',
  updated: 'warning',
  deleted: 'danger',
}

const ENTITY_TYPES = ['lead', 'customer', 'organization', 'contact', 'opportunity', 'task', 'campaign', 'tag']
const entityTypeOptions = ENTITY_TYPES.map(entityType => ({ label: entityType, value: entityType }))
const actionOptions = ['created', 'updated', 'deleted'].map(action => ({
  label: t(`auditLogs.actions.${action}`),
  value: action,
}))

const columns: DataTableColumn[] = [
  { key: 'created_at', label: t('common.date'), sortable: true, type: 'date' },
  { key: 'user.name', label: t('auditLogs.user'), sortable: false },
  { key: 'action', label: t('auditLogs.action'), sortable: true },
  { key: 'entity_type', label: t('auditLogs.entityType'), sortable: true },
  { key: 'entity_id', label: t('auditLogs.entityId'), sortable: false, align: 'center' },
  { key: 'changes', label: t('auditLogs.changes'), sortable: false },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter,
} = useDataTable<AuditLog>(
  (params) => auditLogService.list(params as Record<string, unknown> & { page?: number }),
  { defaultSort: 'created_at', defaultSortDir: 'desc', immediate: true },
)

const expandedRow = ref<number | null>(null)

function toggleExpand(id: number) {
  expandedRow.value = expandedRow.value === id ? null : id
}

function changeKeys(changes: Record<string, { old: unknown; new: unknown }>): string[] {
  return Object.keys(changes ?? {})
}
</script>

<template>
  <div>
    <h2 class="mb-3">{{ t('auditLogs.title') }}</h2>

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
        <FilterControl :label="t('auditLogs.entityType')" :options="entityTypeOptions" @update:model-value="onFilter('entity_type', $event)" />
        <FilterControl :label="t('auditLogs.action')" :options="actionOptions" @update:model-value="onFilter('action', $event)" />
        <FilterControl :label="t('auditLogs.dateFrom')" type="date" @update:model-value="onFilter('date_from', $event)" />
        <FilterControl :label="t('auditLogs.dateTo')" type="date" @update:model-value="onFilter('date_to', $event)" />
      </template>

      <template #cell-created_at="{ value }">
        {{ value ? new Date(value as string).toLocaleString() : '—' }}
      </template>
      <template #cell-action="{ value }">
        <BaseBadge :variant="ACTION_COLORS[value as string] ?? 'secondary'" pill>
          {{ t(`auditLogs.actions.${value}`) }}
        </BaseBadge>
      </template>
      <template #cell-entity_type="{ value }">
        <span class="text-capitalize">{{ value }}</span>
      </template>
      <template #cell-changes="{ row }">
        <template v-if="(row as AuditLog).action === 'updated'">
          <button class="btn btn-link btn-sm p-0" @click.stop="toggleExpand((row as AuditLog).id)">
            <i class="bi" :class="expandedRow === (row as AuditLog).id ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
            {{ changeKeys((row as AuditLog).changes).length }} {{ t('auditLogs.fieldsChanged') }}
          </button>
          <div v-if="expandedRow === (row as AuditLog).id" class="mt-2">
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th>{{ t('auditLogs.field') }}</th>
                  <th>{{ t('auditLogs.oldValue') }}</th>
                  <th>{{ t('auditLogs.newValue') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="field in changeKeys((row as AuditLog).changes)" :key="field">
                  <td class="fw-medium">{{ field }}</td>
                  <td class="text-danger"><del>{{ (row as AuditLog).changes[field]?.old ?? '—' }}</del></td>
                  <td class="text-success">{{ (row as AuditLog).changes[field]?.new ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <span v-else class="text-muted">—</span>
      </template>
    </BaseDataTable>
  </div>
</template>
