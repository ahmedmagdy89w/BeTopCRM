<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermission } from '@/composables/usePermission'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { useTagStore } from '@/stores/tags'
import { tagService } from '@/services/tagService'
import type { Tag } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'

const { t } = useI18n()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()
const tagStore = useTagStore()

const showForm = ref(false)
const editingTag = ref<Tag | null>(null)
const form = ref({ name: '', color: '#3498DB' })
const formErrors = ref<Record<string, string[]>>({})
const saving = ref(false)

function openCreate() {
  editingTag.value = null
  form.value = { name: '', color: '#3498DB' }
  formErrors.value = {}
  showForm.value = true
}

function openEdit(tag: Tag) {
  editingTag.value = tag
  form.value = { name: tag.name, color: tag.color ?? '#3498DB' }
  formErrors.value = {}
  showForm.value = true
}

async function saveTag() {
  formErrors.value = {}
  saving.value = true
  try {
    if (editingTag.value) {
      const updated = await tagService.update(editingTag.value.id, form.value)
      tagStore.updateTag(updated)
    } else {
      const created = await tagService.create(form.value)
      tagStore.addTag(created)
    }
    showForm.value = false
  } catch (err: unknown) {
    const axiosErr = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (axiosErr.response?.status === 422) {
      formErrors.value = axiosErr.response.data?.errors ?? {}
    }
  } finally {
    saving.value = false
  }
}

async function handleDelete(tag: Tag) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await tagService.delete(tag.id)
  tagStore.removeTag(tag.id)
}

onMounted(() => tagStore.refresh())
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('tags.title') }}</h2>
      <BaseButton v-if="can('create-tags')" variant="primary" icon="bi-plus-lg" @click="openCreate">
        {{ t('common.create') }}
      </BaseButton>
    </div>

    <BaseCard>
      <div v-if="tagStore.loading" class="text-center py-4">
        <BaseSpinner />
      </div>

      <template v-else>
        <!-- Inline form -->
        <div v-if="showForm" class="border rounded p-3 mb-3 bg-body-tertiary">
          <form @submit.prevent="saveTag" class="row g-2 align-items-end">
            <div class="col-md-5">
              <label class="form-label">{{ t('tags.name') }}</label>
              <input
                v-model="form.name"
                class="form-control form-control-sm"
                :class="{ 'is-invalid': formErrors.name }"
                required
              >
              <div v-if="formErrors.name" class="invalid-feedback">{{ formErrors.name[0] }}</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ t('tags.color') }}</label>
              <div class="d-flex align-items-center gap-2">
                <input v-model="form.color" type="color" class="form-control form-control-color form-control-sm">
                <code>{{ form.color }}</code>
              </div>
            </div>
            <div class="col-md-4 d-flex gap-1">
              <button type="submit" class="btn btn-primary btn-sm" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm"></span>
                {{ t('common.save') }}
              </button>
              <button type="button" class="btn btn-secondary btn-sm" @click="showForm = false">
                {{ t('common.cancel') }}
              </button>
            </div>
          </form>
        </div>

        <!-- Tags table -->
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 40px;">{{ t('tags.color') }}</th>
                <th>{{ t('tags.name') }}</th>
                <th>{{ t('common.createdAt') }}</th>
                <th v-if="can('update-tags') || can('delete-tags')" class="text-end">{{ t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="tagStore.tags.length === 0">
                <td :colspan="can('update-tags') || can('delete-tags') ? 4 : 3" class="text-center text-muted py-4">
                  {{ t('common.noData') }}
                </td>
              </tr>
              <tr v-for="tag in tagStore.tags" :key="tag.id">
                <td>
                  <span
                    class="d-inline-block rounded-circle"
                    style="width: 20px; height: 20px;"
                    :style="{ backgroundColor: tag.color || '#6c757d' }"
                  ></span>
                </td>
                <td>
                  <span
                    class="badge rounded-pill"
                    :style="{ backgroundColor: tag.color || '#6c757d', color: '#fff' }"
                  >
                    {{ tag.name }}
                  </span>
                </td>
                <td>{{ new Date(tag.created_at).toLocaleDateString() }}</td>
                <td v-if="can('update-tags') || can('delete-tags')" class="text-end">
                  <div class="d-flex gap-1 justify-content-end">
                    <BaseButton v-if="can('update-tags')" variant="outline-primary" size="sm" icon="bi-pencil" @click="openEdit(tag)" />
                    <BaseButton v-if="can('delete-tags')" variant="outline-danger" size="sm" icon="bi-trash" @click="handleDelete(tag)" />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </BaseCard>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
