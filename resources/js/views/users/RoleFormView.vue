<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { roleService } from '@/services/roleService'
import { useToast } from '@/composables/useToast'
import { usePermissionLabel } from '@/composables/usePermissionLabel'
import { useCompanySettingsStore } from '@/stores/companySettings'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { permissionLabel, moduleLabel } = usePermissionLabel()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = computed(() => !!id)

interface PermissionGroup {
  label: string
  icon: string
  module: string
  permissions: string[]
}

const MODULE_ICONS: Record<string, string> = {
  users: 'bi-people',
  organizations: 'bi-building',
  leads: 'bi-funnel',
  contacts: 'bi-person-lines-fill',
  activities: 'bi-lightning-charge',
  customers: 'bi-person-check',
  tasks: 'bi-check2-square',
  tags: 'bi-tag',
  campaigns: 'bi-megaphone',
  templates: 'bi-file-earmark-text',
  products: 'bi-box-seam',
  attachments: 'bi-paperclip',
  'custom-fields': 'bi-sliders',
  webhooks: 'bi-link-45deg',
  quotations: 'bi-file-earmark-text',
  invoices: 'bi-receipt',
  'audit-log': 'bi-shield-check',
  reports: 'bi-bar-chart-line',
  data: 'bi-arrow-left-right',
  'company-settings': 'bi-building-gear',
}

const allPermissions = ref<string[]>([])

const permissionGroups = computed<PermissionGroup[]>(() => {
  const groups = new Map<string, string[]>()
  for (const perm of allPermissions.value) {
    const dashIdx = perm.indexOf('-')
    const module = dashIdx !== -1 ? perm.slice(dashIdx + 1) : perm
    if (!groups.has(module)) groups.set(module, [])
    groups.get(module)!.push(perm)
  }
  return Array.from(groups.entries()).map(([module, permissions]) => ({
    module,
    label: moduleLabel(module),
    icon: MODULE_ICONS[module] ?? 'bi-gear',
    permissions,
  }))
})

const form = ref({ name: '', permissions: [] as string[] })
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const saving = ref(false)
const isSystem = ref(false)

function isGroupChecked(group: PermissionGroup): boolean {
  return group.permissions.every(p => form.value.permissions.includes(p))
}

function isGroupIndeterminate(group: PermissionGroup): boolean {
  const count = group.permissions.filter(p => form.value.permissions.includes(p)).length
  return count > 0 && count < group.permissions.length
}

function toggleGroup(group: PermissionGroup) {
  if (isGroupChecked(group)) {
    form.value.permissions = form.value.permissions.filter(p => !group.permissions.includes(p))
  } else {
    const toAdd = group.permissions.filter(p => !form.value.permissions.includes(p))
    form.value.permissions = [...form.value.permissions, ...toAdd]
  }
}

function togglePermission(perm: string) {
  const idx = form.value.permissions.indexOf(perm)
  if (idx === -1) {
    form.value.permissions.push(perm)
  } else {
    form.value.permissions.splice(idx, 1)
  }
}

function selectAll() {
  form.value.permissions = [...allPermissions.value]
}

function clearAll() {
  form.value.permissions = []
}

async function fetchRole() {
  if (!id) return
  const role = await roleService.get(id)
  form.value = { name: role.name, permissions: [...role.permissions] }
  isSystem.value = role.is_system
}

async function handleSubmit() {
  errors.value = {}
  saving.value = true
  try {
    if (isEdit.value && id) {
      await roleService.update(id, form.value)
    } else {
      await roleService.create(form.value)
    }
    toast.success(t('common.saveSuccess'))
    router.push({ name: 'roles.index' })
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.error'))
    }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([
      roleService.listPermissions().then(p => { allPermissions.value = p }),
      fetchRole(),
      useCompanySettingsStore().fetchSettings(),
    ])
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ t('roles.role') }}</h2>
        <p class="text-muted small mb-0">{{ t('roles.formDesc') }}</p>
      </div>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.back()">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <form v-else @submit.prevent="handleSubmit">
      <!-- Role Name -->
      <BaseCard class="mb-3">
        <div class="row align-items-end g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">{{ t('roles.name') }}</label>
            <input
              v-model="form.name"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': errors.name }"
              :disabled="isSystem"
              :placeholder="t('roles.namePlaceholder')"
            >
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
            <div v-if="isSystem" class="form-text text-warning">
              <i class="bi bi-shield-exclamation me-1"></i>{{ t('roles.systemRoleNote') }}
            </div>
          </div>
          <div class="col-md-6">
            <p class="small text-muted mb-0">
              <i class="bi bi-info-circle me-1"></i>{{ t('roles.nameHint') }}
            </p>
          </div>
        </div>
      </BaseCard>

      <!-- Permissions -->
      <BaseCard class="mb-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h6 class="mb-0 fw-semibold">{{ t('roles.permissions') }}</h6>
            <small class="text-muted">
              {{ form.permissions.length }} {{ t('roles.selected') }}
            </small>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" @click="selectAll">
              <i class="bi bi-check2-all me-1"></i>{{ t('roles.selectAll') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearAll">
              <i class="bi bi-x-lg me-1"></i>{{ t('roles.clearAll') }}
            </button>
          </div>
        </div>

        <div v-if="errors.permissions" class="alert alert-danger py-2 small mb-3">
          {{ errors.permissions[0] }}
        </div>

        <div class="row g-3">
          <div
            v-for="group in permissionGroups"
            :key="group.module"
            class="col-md-6 col-xl-4"
          >
            <div class="border rounded p-3 h-100">
              <!-- Group header -->
              <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                <input
                  type="checkbox"
                  class="form-check-input mt-0 flex-shrink-0"
                  :checked="isGroupChecked(group)"
                  :indeterminate="isGroupIndeterminate(group)"
                  @change="toggleGroup(group)"
                >
                <i :class="`bi ${group.icon} text-primary`"></i>
                <span class="fw-semibold small">{{ group.label }}</span>
              </div>
              <!-- Individual permissions -->
              <div class="d-flex flex-column gap-1">
                <label
                  v-for="perm in group.permissions"
                  :key="perm"
                  class="d-flex align-items-center gap-2 small"
                  style="cursor:pointer;"
                >
                  <input
                    type="checkbox"
                    class="form-check-input mt-0 flex-shrink-0"
                    :checked="form.permissions.includes(perm)"
                    @change="togglePermission(perm)"
                  >
                  <code class="text-body-secondary" style="font-size:.78rem;" :title="perm">{{ permissionLabel(perm) }}</code>
                </label>
              </div>
            </div>
          </div>
        </div>
      </BaseCard>

      <div class="d-flex gap-2">
        <BaseButton type="submit" variant="primary" icon="bi-check-lg" :loading="saving">
          {{ t('common.save') }}
        </BaseButton>
        <BaseButton variant="outline-secondary" @click="router.back()">
          {{ t('common.cancel') }}
        </BaseButton>
      </div>
    </form>
  </div>
</template>
