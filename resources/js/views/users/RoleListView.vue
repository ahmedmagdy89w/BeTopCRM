<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { roleService } from '@/services/roleService'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import { usePermissionLabel } from '@/composables/usePermissionLabel'
import type { Role } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()
const toast = useToast()
const { permissionLabel } = usePermissionLabel()

const roles = ref<Role[]>([])
const loading = ref(true)

async function fetchRoles() {
  loading.value = true
  try {
    roles.value = await roleService.list()
  } finally {
    loading.value = false
  }
}

async function handleDelete(role: Role) {
  const confirmed = await confirm(t('roles.confirmDelete', { name: role.name }))
  if (!confirmed) return
  try {
    await roleService.delete(role.id)
    toast.success(t('common.deleteSuccess'))
    await fetchRoles()
  } catch {
    toast.error(t('common.error'))
  }
}

onMounted(fetchRoles)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ t('roles.title') }}</h2>
        <p class="text-muted small mb-0">{{ t('roles.desc') }}</p>
      </div>
      <BaseButton
        v-if="can('create-users')"
        variant="primary"
        icon="bi-plus-lg"
        :to="{ name: 'roles.create' }"
      >
        {{ t('roles.newRole') }}
      </BaseButton>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <div v-else class="row g-3">
      <div
        v-for="role in roles"
        :key="role.id"
        class="col-md-6 col-xl-4"
      >
        <BaseCard class="h-100">
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="d-flex align-items-center gap-2">
              <span
                class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                :class="role.is_system ? 'bg-primary bg-opacity-10' : 'bg-secondary bg-opacity-10'"
                style="width:40px;height:40px;"
              >
                <i class="bi bi-shield-check fs-5" :class="role.is_system ? 'text-primary' : 'text-secondary'"></i>
              </span>
              <div>
                <h6 class="mb-0 fw-semibold text-capitalize">{{ role.name.replace(/_/g, ' ') }}</h6>
                <small class="text-muted">
                  {{ t('roles.usersCount', { count: role.users_count }) }}
                </small>
              </div>
            </div>
            <span
              class="badge"
              :class="role.is_system ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary'"
            >
              {{ role.is_system ? t('roles.system') : t('roles.custom') }}
            </span>
          </div>

          <!-- Permissions -->
          <div class="mb-3">
            <p class="small fw-semibold text-muted mb-2">
              {{ t('roles.permissions') }} ({{ role.permissions.length }})
            </p>
            <div class="d-flex flex-wrap gap-1" style="max-height:96px;overflow:hidden;">
              <span
                v-for="perm in role.permissions.slice(0, 10)"
                :key="perm"
                class="badge bg-light text-secondary border"
                style="font-size:.72rem;"
                :title="perm"
              >
                {{ permissionLabel(perm) }}
              </span>
              <span
                v-if="role.permissions.length > 10"
                class="badge bg-light text-muted border"
                style="font-size:.72rem;"
              >
                +{{ role.permissions.length - 10 }} {{ t('roles.more') }}
              </span>
            </div>
          </div>

          <!-- Actions -->
          <div v-if="can('update-users') || can('delete-users')" class="d-flex gap-2 pt-2 border-top">
            <BaseButton
              v-if="can('update-users')"
              variant="outline-primary"
              size="sm"
              icon="bi-pencil"
              class="flex-grow-1"
              :to="{ name: 'roles.edit', params: { id: role.id } }"
            >
              {{ t('common.edit') }}
            </BaseButton>
            <BaseButton
              v-if="!role.is_system && can('delete-users')"
              variant="outline-danger"
              size="sm"
              icon="bi-trash"
              @click="handleDelete(role)"
            />
          </div>
        </BaseCard>
      </div>
    </div>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
