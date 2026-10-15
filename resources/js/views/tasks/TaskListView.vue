<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { taskService } from '@/services/taskService'
import { TASK_PRIORITY_COLORS, TASK_STATUS_COLORS } from '@/utils/constants'
import type { Task } from '@/types'
import type { TaskPriority, TaskStatus } from '@/types/enums'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import FilterControl from '@/components/shared/FilterControl.vue'

const { t } = useI18n()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const columns: DataTableColumn[] = [
  { key: 'title', label: t('tasks.taskTitle'), sortable: true },
  { key: 'priority', label: t('tasks.priority'), sortable: true },
  { key: 'status', label: t('tasks.status'), sortable: true },
  { key: 'assignee.name', label: t('tasks.assignee'), sortable: false },
  { key: 'entity', label: t('tasks.relatedTo'), sortable: false },
  { key: 'due_date', label: t('tasks.dueDate'), sortable: true },
]

const statusOptions = Object.keys(TASK_STATUS_COLORS).map(status => ({
  label: t(`tasks.statuses.${status}`),
  value: status,
}))
const priorityOptions = Object.keys(TASK_PRIORITY_COLORS).map(priority => ({
  label: t(`tasks.priorities.${priority}`),
  value: priority,
}))

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter, refresh,
} = useDataTable<Task>(
  (params) => taskService.list(params as Record<string, unknown> & { page?: number }),
  { defaultSort: 'due_date', defaultSortDir: 'asc', immediate: true },
)

async function handleComplete(task: Task) {
  await taskService.complete(task.id)
  refresh()
}

async function handleDelete(task: Task) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await taskService.delete(task.id)
  refresh()
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('tasks.title') }}</h2>
      <BaseButton v-if="can('create-tasks')" variant="primary" icon="bi-plus-lg" :to="{ name: 'tasks.create' }">
        {{ t('tasks.newTask') }}
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
    >
      <template #filters>
        <FilterControl :label="t('tasks.status')" :options="statusOptions" @update:model-value="onFilter('status', $event)" />
        <FilterControl :label="t('tasks.priority')" :options="priorityOptions" @update:model-value="onFilter('priority', $event)" />
      </template>

      <template #cell-title="{ row }">
        <strong :class="{ 'text-decoration-line-through text-muted': (row as Task).status === 'completed' }">
          {{ (row as Task).title }}
        </strong>
      </template>
      <template #cell-priority="{ value }">
        <BaseBadge :variant="TASK_PRIORITY_COLORS[value as TaskPriority] ?? 'secondary'" pill>
          {{ t(`tasks.priorities.${value}`) }}
        </BaseBadge>
      </template>
      <template #cell-status="{ value }">
        <BaseBadge :variant="TASK_STATUS_COLORS[value as TaskStatus] ?? 'secondary'" pill>
          {{ t(`tasks.statuses.${value}`) }}
        </BaseBadge>
      </template>
      <template #cell-entity="{ row }">
        <span v-if="(row as Task).entity">
          <small class="text-muted">{{ (row as Task).entity!.type }}:</small>
          {{ (row as Task).entity!.name }}
        </span>
        <span v-else class="text-muted">—</span>
      </template>
      <template #cell-due_date="{ row }">
        <span v-if="(row as Task).due_date" :class="{ 'text-danger fw-semibold': (row as Task).is_overdue }">
          {{ new Date((row as Task).due_date!).toLocaleDateString() }}
          <i v-if="(row as Task).is_overdue" class="bi bi-exclamation-triangle-fill text-danger ms-1"></i>
        </span>
        <span v-else class="text-muted">—</span>
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="(row as Task).status !== 'completed' && can('complete-tasks')"
            variant="outline-success"
            size="sm"
            icon="bi-check-lg"
            @click.stop="handleComplete(row as Task)"
          />
          <BaseButton
            v-if="can('update-tasks')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'tasks.edit', params: { id: (row as Task).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('delete-tasks')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as Task)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
