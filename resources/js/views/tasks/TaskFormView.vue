<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrudForm } from '@/composables/useCrudForm'
import { taskService } from '@/services/taskService'
import type { Task } from '@/types'
import type { FormFieldSchema } from '@/types/forms'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import DynamicForm from '@/components/form/DynamicForm.vue'

const { t } = useI18n()

const {
  formData, errors, loading, saving, isEdit,
  loadItem, submit, updateFormData,
} = useCrudForm<Task>({
  getService: (id) => taskService.get(id),
  createService: (data) => taskService.create(data as Partial<Task> & { entity_type?: string; entity_id?: number }),
  updateService: (id, data) => taskService.update(id, data),
  redirectTo: { name: 'tasks.index' },
  defaultValues: () => ({ priority: 'medium', status: 'pending' } as Partial<Task>),
  transform: (item) => {
    const raw = item as unknown as Record<string, unknown>
    const entity = raw.entity as { type?: string; id?: number } | undefined
    const assignee = raw.assignee as { id?: number } | undefined
    return {
      ...item,
      user_id: assignee?.id ?? (raw.user_id as number | undefined),
      entity_type: entity?.type ? entity.type.toLowerCase() : (raw.entity_type as string | undefined),
      entity_id: entity?.id ?? (raw.entity_id as number | undefined),
    } as unknown as Partial<Task>
  },
  preparePayload: (data) => {
    const { title, description, priority, status, due_date,
      user_id, entity_type, entity_id } = data as Record<string, unknown>
    return Object.fromEntries(
      Object.entries({ title, description, priority, status, due_date,
        user_id, entity_type, entity_id })
        .filter(([, v]) => v !== undefined),
    ) as Partial<Task>
  },
})

const schema = computed<FormFieldSchema[]>(() => [
  { key: 'title', label: t('tasks.taskTitle'), type: 'text', required: true },
  {
    key: 'priority',
    label: t('tasks.priority'),
    type: 'select',
    options: [
      { label: t('tasks.priorities.low'), value: 'low' },
      { label: t('tasks.priorities.medium'), value: 'medium' },
      { label: t('tasks.priorities.high'), value: 'high' },
      { label: t('tasks.priorities.urgent'), value: 'urgent' },
    ],
  },
  {
    key: 'status',
    label: t('tasks.status'),
    type: 'select',
    visible: isEdit.value,
    options: [
      { label: t('tasks.statuses.pending'), value: 'pending' },
      { label: t('tasks.statuses.in_progress'), value: 'in_progress' },
      { label: t('tasks.statuses.completed'), value: 'completed' },
      { label: t('tasks.statuses.cancelled'), value: 'cancelled' },
    ],
  },
  {
    key: 'user_id',
    label: t('tasks.assignee'),
    type: 'select',
    optionsUrl: '/users?per_page=100',
    optionsLabelKey: 'name',
    optionsValueKey: 'id',
  },
  { key: 'due_date', label: t('tasks.dueDate'), type: 'date' },
  {
    key: 'entity_type',
    label: t('tasks.relatedTo'),
    type: 'select',
    options: [
      { label: t('nav.customers'), value: 'customer' },
      { label: t('nav.opportunities'), value: 'opportunity' },
    ],
  },
  { key: 'entity_id', label: 'Entity ID', type: 'number', visible: !!(formData.value as Record<string, unknown>).entity_type },
  { key: 'description', label: t('tasks.description'), type: 'textarea', colSpan: 2 },
])

const pageTitle = computed(() =>
  isEdit.value ? t('common.edit') + ' ' + t('tasks.title') : t('tasks.newTask'),
)

onMounted(() => {
  if (isEdit.value) loadItem()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ pageTitle }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'tasks.index' }">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <BaseCard :loading="loading">
      <DynamicForm
        :schema="schema"
        :model-value="(formData as Record<string, unknown>)"
        :errors="errors"
        :loading="saving"
        :columns="2"
        @update:model-value="updateFormData($event as Partial<Task>)"
        @submit="submit"
      />
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BaseButton variant="secondary" :to="{ name: 'tasks.index' }">
          {{ t('common.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" :loading="saving" @click="submit">
          {{ t('common.save') }}
        </BaseButton>
      </div>
    </BaseCard>
  </div>
</template>
