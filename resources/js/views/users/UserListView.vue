<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { userService } from '@/services/userService'
import { roleService } from '@/services/roleService'
import type { User, Role } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import FilterControl from '@/components/shared/FilterControl.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const columns: DataTableColumn[] = [
  { key: 'name', label: t('common.name'), sortable: true },
  { key: 'email', label: t('common.email'), sortable: true },
  { key: 'role', label: t('users.role'), sortable: true },
  { key: 'is_active', label: t('common.status'), sortable: true },
  { key: 'created_at', label: t('common.createdAt'), sortable: true, type: 'date' },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter, refresh,
} = useDataTable<User>(
  (params) => userService.list(params as Record<string, unknown> & { page?: number }),
  { defaultSort: 'name', defaultSortDir: 'asc', immediate: true },
)

const availableRoles = ref<Role[]>([])
const roleOptions = computed(() => availableRoles.value.map(role => ({
  label: role.name.replace(/_/g, ' '),
  value: role.name,
})))
const statusOptions = computed(() => [
  { label: t('common.active'), value: '1' },
  { label: t('common.inactive'), value: '0' },
])

async function fetchRoles() {
  availableRoles.value = await roleService.list()
}

function goToEdit(row: unknown) {
  if (!can('update-users')) return
  const u = row as User
  router.push({ name: 'users.edit', params: { id: u.id } })
}

async function handleDeactivate(user: User) {
  const confirmed = await confirm(t('users.confirmDeactivate'))
  if (!confirmed) return
  await userService.delete(user.id)
  refresh()
}

async function handleActivate(user: User) {
  await userService.activate(user.id)
  refresh()
}

onMounted(fetchRoles)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('users.title') }}</h2>
      <BaseButton
        v-if="can('create-users')"
        variant="primary"
        icon="bi-plus-lg"
        :to="{ name: 'users.create' }"
      >
        {{ t('users.newUser') }}
      </BaseButton>
    </div>

    <BaseDataTable
      :columns="columns"
      :rows="(rows as unknown[])"
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
      <template #filters>
        <FilterControl :label="t('users.role')" :options="roleOptions" @update:model-value="onFilter('role', $event)" />
        <FilterControl :label="t('common.status')" :options="statusOptions" @update:model-value="onFilter('is_active', $event)" />
      </template>

      <template #cell-role="{ value }">
        <span class="badge bg-secondary bg-opacity-10 text-secondary border text-capitalize">
          {{ (value as string).replace(/_/g, ' ') }}
        </span>
      </template>
      <template #cell-is_active="{ value }">
        <BaseBadge :variant="value ? 'success' : 'secondary'" pill>
          {{ value ? t('common.active') : t('common.inactive') }}
        </BaseBadge>
      </template>
      <template #cell-created_at="{ value }">
        {{ value ? new Date(value as string).toLocaleDateString() : '—' }}
      </template>
      <template #row-actions="{ row }">
        <div class="d-flex gap-1 justify-content-end">
          <BaseButton
            v-if="can('update-users')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'users.edit', params: { id: (row as User).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('update-users') && (row as User).is_active"
            variant="outline-danger"
            size="sm"
            icon="bi-person-x"
            @click.stop="handleDeactivate(row as User)"
          />
          <BaseButton
            v-if="can('update-users') && !(row as User).is_active"
            variant="outline-success"
            size="sm"
            icon="bi-person-check"
            @click.stop="handleActivate(row as User)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
