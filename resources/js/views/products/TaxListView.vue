<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import { taxService, type Tax } from '@/services/taxService'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const toast = useToast()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const taxes = ref<Tax[]>([])
const loading = ref(false)
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ name: '', percent: '', is_active: true })
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

async function fetch() {
  loading.value = true
  try {
    taxes.value = await taxService.list()
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { name: '', percent: '', is_active: true }
  errors.value = {}
  showForm.value = true
}

function openEdit(tax: Tax) {
  editingId.value = tax.id
  form.value = { name: tax.name, percent: String(tax.percent), is_active: tax.is_active }
  errors.value = {}
  showForm.value = true
}

async function handleSave() {
  errors.value = {}
  saving.value = true
  try {
    if (editingId.value) {
      await taxService.update(editingId.value, { name: form.value.name, percent: Number(form.value.percent), is_active: form.value.is_active })
    } else {
      await taxService.create({ name: form.value.name, percent: Number(form.value.percent), is_active: form.value.is_active })
    }
    showForm.value = false
    toast.success(t('common.saveSuccess'))
    await fetch()
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    saving.value = false
  }
}

async function handleDelete(tax: Tax) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  try {
    await taxService.delete(tax.id)
    toast.success(t('common.deleteSuccess'))
    await fetch()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { errors?: Record<string, string[]> } } }
    toast.error(e.response?.data?.errors?.tax?.[0] || t('common.somethingWentWrong'))
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('taxes.title') }}</h2>
      <div class="d-flex gap-2">
        <BaseButton v-if="can('view-products')" variant="outline-secondary" icon="bi-arrow-left" @click="router.push({ name: 'products.index' })">
          {{ t('common.back') }}
        </BaseButton>
        <BaseButton v-if="can('create-taxes')" variant="primary" icon="bi-plus-lg" @click="openCreate">
          {{ t('common.create') }}
        </BaseButton>
      </div>
    </div>

    <BaseCard v-if="showForm" class="mb-3">
      <form @submit.prevent="handleSave">
        <div class="row">
          <div class="col-md-5 mb-2">
            <label class="form-label">{{ t('common.name') }}</label>
            <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }">
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">{{ t('taxes.percent') }}</label>
            <input v-model="form.percent" type="number" min="0" max="100" step="0.01" class="form-control" :class="{ 'is-invalid': errors.percent }">
            <div v-if="errors.percent" class="invalid-feedback">{{ errors.percent[0] }}</div>
          </div>
          <div class="col-md-3 mb-2 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
              <input id="taxActive" v-model="form.is_active" class="form-check-input" type="checkbox">
              <label class="form-check-label" for="taxActive">{{ t('common.active') }}</label>
            </div>
          </div>
        </div>
        <div class="d-flex gap-2 mt-2">
          <BaseButton type="submit" variant="primary" size="sm" :loading="saving">
            {{ t('common.save') }}
          </BaseButton>
          <BaseButton variant="outline-secondary" size="sm" @click="showForm = false">{{ t('common.cancel') }}</BaseButton>
        </div>
      </form>
    </BaseCard>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <BaseCard v-else>
      <div v-if="!taxes.length" class="text-center text-muted py-3">{{ t('common.noData') }}</div>
      <table v-else class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ t('common.name') }}</th>
            <th class="text-end">{{ t('taxes.percent') }}</th>
            <th>{{ t('common.status') }}</th>
            <th class="text-end">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tax in taxes" :key="tax.id">
            <td><strong>{{ tax.name }}</strong></td>
            <td class="text-end">{{ Number(tax.percent).toLocaleString() }}%</td>
            <td>
              <span class="badge" :class="tax.is_active ? 'bg-success' : 'bg-secondary'">
                {{ tax.is_active ? t('common.active') : t('common.inactive') }}
              </span>
            </td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <BaseButton v-if="can('update-taxes')" variant="outline-primary" size="sm" icon="bi-pencil" @click="openEdit(tax)" />
                <BaseButton v-if="can('delete-taxes')" variant="outline-danger" size="sm" icon="bi-trash" @click="handleDelete(tax)" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </BaseCard>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
