<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { templateService } from '@/services/templateService'
import type { EmailTemplate } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const columns: DataTableColumn[] = [
  { key: 'name', label: t('templates.name'), sortable: true },
  { key: 'subject', label: t('templates.subject'), sortable: true },
  { key: 'placeholders', label: t('templates.placeholders'), sortable: false },
  { key: 'created_at', label: t('common.createdAt'), sortable: true, type: 'date' },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, refresh,
} = useDataTable<EmailTemplate>(
  (params) => templateService.list(params as Record<string, unknown> & { page?: number }),
  { defaultSort: 'created_at', defaultSortDir: 'desc', immediate: true },
)

function goToEdit(row: unknown) {
  if (!can('update-templates')) return
  const tpl = row as EmailTemplate
  router.push({ name: 'templates.edit', params: { id: tpl.id } })
}

async function handleDelete(tpl: EmailTemplate) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await templateService.delete(tpl.id)
  refresh()
}
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('templates.title') }}</h2>
      <BaseButton
        v-if="can('create-templates')"
        variant="primary"
        icon="bi-plus-lg"
        :to="{ name: 'templates.create' }"
      >
        {{ t('templates.newTemplate') }}
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
      <template #cell-placeholders="{ row }">
        <span
          v-for="ph in (row as EmailTemplate).placeholders"
          :key="ph"
          class="badge text-bg-light me-1"
        >{{ ph }}</span>
        <span v-if="!(row as EmailTemplate).placeholders?.length" class="text-muted">—</span>
      </template>
      <template #cell-created_at="{ value }">
        {{ value ? new Date(value as string).toLocaleDateString() : '—' }}
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="can('update-templates')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'templates.edit', params: { id: (row as EmailTemplate).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('delete-templates')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as EmailTemplate)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
