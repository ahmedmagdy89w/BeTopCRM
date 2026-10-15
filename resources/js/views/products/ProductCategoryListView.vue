<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { useToast } from '@/composables/useToast'
import { productCategoryService } from '@/services/productCategoryService'
import { customFieldService } from '@/services/customFieldService'
import type { CustomFieldDefinitionInput, CustomFieldGroup, ProductCategory } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import CustomFieldDefinitionEditor from '@/components/shared/CustomFieldDefinitionEditor.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const toast = useToast()
const canManageFieldDefinitions = computed(() => can('create-custom-fields') && can('update-custom-fields') && can('delete-custom-fields'))
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()

const tree = ref<ProductCategory[]>([])
const expandedCategoryIds = ref<Set<number>>(new Set())
const loading = ref(false)

// Inline form state
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = ref({ name: '', description: '', parent_id: null as number | null })
const flatCategories = ref<ProductCategory[]>([])
const errors = ref<Record<string, string[]>>({})
const saving = ref(false)
const productFields = ref<CustomFieldDefinitionInput[]>([])
const originalProductFields = ref<CustomFieldDefinitionInput[]>([])
const categoryFieldGroup = ref<CustomFieldGroup | null>(null)
const categoryFieldsLoading = ref(false)

async function fetch() {
  loading.value = true
  try {
    const [treeData, flatData] = await Promise.all([
      productCategoryService.tree(),
      productCategoryService.list(),
    ])
    tree.value = treeData
    flatCategories.value = flatData
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  form.value = { name: '', description: '', parent_id: null }
  errors.value = {}
  productFields.value = []
  originalProductFields.value = []
  categoryFieldGroup.value = null
  categoryFieldsLoading.value = false
  showForm.value = true
}

function toggleChildren(categoryId: number) {
  const expanded = new Set(expandedCategoryIds.value)

  if (expanded.has(categoryId)) {
    expanded.delete(categoryId)
  } else {
    expanded.add(categoryId)
  }

  expandedCategoryIds.value = expanded
}

async function openEdit(cat: ProductCategory) {
  editingId.value = cat.id
  form.value = { name: cat.name, description: cat.description ?? '', parent_id: cat.parent_id }
  errors.value = {}
  productFields.value = []
  originalProductFields.value = []
  categoryFieldGroup.value = null
  categoryFieldsLoading.value = true
  showForm.value = true

  try {
    const fieldGroup = await customFieldService.listCategory('product', cat.id)
    categoryFieldGroup.value = fieldGroup.group
    productFields.value = fieldGroup.definitions.map(definition => ({
      id: definition.id,
      name: definition.name,
      label: definition.label,
      type: definition.type,
      options: definition.options ?? null,
      is_required: definition.is_required,
      is_filterable: definition.is_filterable,
      sort_order: definition.sort_order,
    }))
    originalProductFields.value = productFields.value.map(definition => ({ ...definition }))
  } catch {
    productFields.value = []
    originalProductFields.value = []
  } finally {
    categoryFieldsLoading.value = false
  }
}

async function handleSave() {
  if (categoryFieldsLoading.value) return

  errors.value = {}
  saving.value = true
  try {
    let savedCategory: ProductCategory

    if (editingId.value) {
      savedCategory = await productCategoryService.update(editingId.value, form.value)
    } else {
      savedCategory = await productCategoryService.create(form.value)
    }

    await saveCategoryFields(savedCategory.id)
    showForm.value = false
    fetch()
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

async function saveCategoryFields(categoryId: number): Promise<void> {
  if (!canManageFieldDefinitions.value) return

  let group = categoryFieldGroup.value

  if (!group && productFields.value.length > 0) {
    group = await customFieldService.createGroup({
      entity_type: 'product',
      scope: 'category',
      category_id: categoryId,
      name: t('customFields.category'),
    })
  }

  const nextIds = new Set(productFields.value.flatMap(field => field.id ? [field.id] : []))

  for (const field of originalProductFields.value) {
    if (field.id && !nextIds.has(field.id)) {
      await customFieldService.delete(field.id)
    }
  }

  for (const [index, field] of productFields.value.entries()) {
    const payload = {
      name: field.name,
      label: field.label,
      type: field.type,
      options: field.options ?? null,
      is_required: field.is_required,
      is_filterable: field.is_filterable,
      sort_order: index,
    }

    if (field.id) {
      await customFieldService.update(field.id, payload)
    } else {
      await customFieldService.createCategory('product', categoryId, payload)
    }
  }

  if (group && productFields.value.length === 0) {
    await customFieldService.deleteGroup(group.id)
  }
}

async function handleDelete(cat: ProductCategory) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return

  try {
    await productCategoryService.delete(cat.id)
    toast.success(t('common.deleteSuccess'))
    fetch()
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }
    const backendMessage = e.response?.data?.errors?.category?.[0] || e.response?.data?.message
    toast.error(backendMessage || t('common.error'))
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ t('products.categories') }}</h2>
      <div class="d-flex gap-2">
        <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.push({ name: 'products.index' })">
          {{ t('common.back') }}
        </BaseButton>
        <BaseButton v-if="can('create-products')" variant="primary" icon="bi-plus-lg" @click="openCreate">
          {{ t('common.create') }}
        </BaseButton>
      </div>
    </div>

    <!-- Inline Form -->
    <BaseCard v-if="showForm" class="mb-3">
      <form @submit.prevent="handleSave">
        <div class="row">
          <div class="col-md-4 mb-2">
            <label class="form-label">{{ t('common.name') }}</label>
            <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': errors.name }">
            <div v-if="errors.name" class="invalid-feedback">{{ errors.name[0] }}</div>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">{{ t('products.parentCategory') }}</label>
            <select v-model="form.parent_id" class="form-select">
              <option :value="null">— {{ t('products.noParent') }} —</option>
              <option v-for="cat in flatCategories.filter(c => c.id !== editingId)" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
          <div class="col-md-4 mb-2">
            <label class="form-label">{{ t('common.description') }}</label>
            <input v-model="form.description" type="text" class="form-control">
          </div>
        </div>
        <div v-if="canManageFieldDefinitions" class="mt-4">
          <h6>{{ t('products.productFields') }}</h6>
          <p class="text-muted small">{{ t('products.productFieldsHelp') }}</p>
          <CustomFieldDefinitionEditor
            v-model="productFields"
            :errors="errors"
            :disabled="saving"
          />
        </div>
        <div class="d-flex gap-2 mt-2">
          <BaseButton type="submit" variant="primary" size="sm" :loading="saving" :disabled="categoryFieldsLoading">
            {{ t('common.save') }}
          </BaseButton>
          <BaseButton variant="outline-secondary" size="sm" @click="showForm = false">{{ t('common.cancel') }}</BaseButton>
        </div>
      </form>
    </BaseCard>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <BaseCard v-else>
      <div v-if="!tree.length" class="text-center text-muted py-3">{{ t('common.noData') }}</div>

      <!-- Recursive tree rendering via flat iteration with indentation -->
      <table v-else class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ t('common.name') }}</th>
            <th>{{ t('common.description') }}</th>
            <th class="text-end">{{ t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="cat in tree" :key="cat.id">
            <tr>
              <td>
                <button
                  v-if="cat.children?.length"
                  type="button"
                  class="btn btn-link btn-sm p-0 me-1"
                  :aria-label="expandedCategoryIds.has(cat.id) ? t('products.collapseChildren') : t('products.expandChildren')"
                  @click="toggleChildren(cat.id)"
                >
                  <i :class="expandedCategoryIds.has(cat.id) ? 'bi bi-chevron-down' : 'bi bi-chevron-right'"></i>
                </button>
                <i class="bi bi-folder me-1"></i><strong>{{ cat.name }}</strong>
              </td>
              <td>{{ cat.description ?? '—' }}</td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <BaseButton v-if="can('update-products')" variant="outline-primary" size="sm" icon="bi-pencil" @click="openEdit(cat)" />
                  <BaseButton v-if="can('delete-products')" variant="outline-danger" size="sm" icon="bi-trash" @click="handleDelete(cat)" />
                </div>
              </td>
            </tr>
            <tr v-for="child in cat.children" v-show="expandedCategoryIds.has(cat.id)" :key="child.id">
              <td class="ps-4"><i class="bi bi-file-earmark me-1"></i>{{ child.name }}</td>
              <td>{{ child.description ?? '—' }}</td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <BaseButton v-if="can('update-products')" variant="outline-primary" size="sm" icon="bi-pencil" @click="openEdit(child)" />
                  <BaseButton v-if="can('delete-products')" variant="outline-danger" size="sm" icon="bi-trash" @click="handleDelete(child)" />
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </BaseCard>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
