<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useCustomFields } from '@/composables/useCustomFields'
import { useProductLabels } from '@/composables/useProductLabels'
import { productService } from '@/services/productService'
import { productCategoryService } from '@/services/productCategoryService'
import { taxService, type Tax } from '@/services/taxService'
import type { Product, ProductCategory } from '@/types'
import type { FormFieldSchema } from '@/types/forms'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import DynamicForm from '@/components/form/DynamicForm.vue'

interface ProductFormData {
  name: string
  sku: string
  description: string
  unit_price: string
  category_id: number | null
  tax_id: number | null
  is_active: boolean
  custom_fields: Record<string, unknown>
  category_custom_fields: Record<string, unknown>
}

const { t } = useI18n()
const { singular: productSingular } = useProductLabels()
const route = useRoute()
const router = useRouter()

const id = route.params.id ? Number(route.params.id) : null
const isEdit = computed(() => !!id)
const categories = ref<ProductCategory[]>([])
const taxes = ref<Tax[]>([])
const errors = ref<Record<string, string[]>>({})
const loading = ref(false)
const saving = ref(false)
const form = ref<ProductFormData>({
  name: '',
  sku: '',
  description: '',
  unit_price: '',
  category_id: null,
  tax_id: null,
  is_active: true,
  custom_fields: {},
  category_custom_fields: {},
})

const selectedCategoryId = computed(() => form.value.category_id)
const entityFields = useCustomFields('product')
const categoryFields = useCustomFields('product', 'category', selectedCategoryId)
const customFields = computed(() => [...entityFields.definitions.value, ...categoryFields.definitions.value])

const schema = computed<FormFieldSchema[]>(() => [
  { key: 'name', label: t('products.name'), type: 'text', required: true },
  { key: 'sku', label: t('products.sku'), type: 'text' },
  { key: 'unit_price', label: t('products.unitPrice'), type: 'number', required: true, step: 0.01 },
  {
    key: 'category_id',
    label: t('products.category'),
    type: 'select',
    options: categories.value.map(category => ({ label: category.name, value: category.id })),
  },
  {
    key: 'tax_id',
    label: t('products.tax'),
    type: 'select',
    options: taxes.value.filter(tax => tax.is_active).map(tax => ({ label: `${tax.name} (${tax.percent}%)`, value: tax.id })),
  },
  { key: 'description', label: t('common.description'), type: 'textarea', colSpan: 2 },
  { key: 'is_active', label: t('common.active'), type: 'boolean' },
])

async function fetch(): Promise<void> {
  loading.value = true

  try {
    const [categoryList, taxList, product] = await Promise.all([
      productCategoryService.list(),
      taxService.list(true).catch(() => [] as Tax[]),
      id ? productService.get(id) : Promise.resolve(null),
    ])

    categories.value = categoryList
    taxes.value = taxList

    if (product) {
      if (product.tax && !taxes.value.some(tax => tax.id === product.tax!.id)) {
        taxes.value = [...taxes.value, { id: product.tax.id, name: product.tax.name, percent: product.tax.percent, is_active: false, created_at: '' }]
      }

      form.value = {
        name: product.name,
        sku: product.sku ?? '',
        description: product.description ?? '',
        unit_price: product.unit_price,
        category_id: product.category?.id ?? product.category_id ?? null,
        tax_id: product.tax?.id ?? product.tax_id ?? null,
        is_active: product.is_active,
        custom_fields: product.custom_fields ?? {},
        category_custom_fields: product.category_custom_fields ?? {},
      }
    }
  } finally {
    loading.value = false
  }
}

function updateForm(value: Record<string, unknown>): void {
  form.value = {
    ...value,
    unit_price: value.unit_price === null || value.unit_price === undefined ? '' : String(value.unit_price),
  } as unknown as ProductFormData
}

watch([categoryFields.loading, categoryFields.definitions], ([isLoading, definitions]) => {
  if (isLoading) return

  const allowed = new Set(definitions.map(definition => definition.name))
  const values = form.value.category_custom_fields ?? {}
  const reconciled = Object.fromEntries(Object.entries(values).filter(([name]) => allowed.has(name)))

  form.value = {
    ...form.value,
    category_custom_fields: reconciled,
  }
}, { deep: true })

async function handleSubmit(): Promise<void> {
  errors.value = {}
  saving.value = true

  try {
    if (isEdit.value && id) {
      await productService.update(id, form.value as Partial<Product>)
    } else {
      await productService.create(form.value as Partial<Product>)
    }

    await router.push({ name: 'products.index' })
  } catch (error: unknown) {
    const response = error as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }

    if (response.response?.status === 422 && response.response.data?.errors) {
      errors.value = response.response.data.errors
    }
  } finally {
    saving.value = false
  }
}

onMounted(fetch)
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ productSingular }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" @click="router.back()">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <BaseCard v-else>
      <DynamicForm
        :schema="schema"
        :custom-fields="customFields"
        :model-value="(form as Record<string, unknown>)"
        :errors="errors"
        :loading="saving || categoryFields.loading.value"
        :columns="2"
        @update:model-value="updateForm"
        @submit="handleSubmit"
      />

      <div class="d-flex gap-2 justify-content-end mt-3">
        <BaseButton variant="secondary" @click="router.back()">{{ t('common.cancel') }}</BaseButton>
        <BaseButton
          type="submit"
          variant="primary"
          :loading="saving"
          :disabled="entityFields.loading.value || categoryFields.loading.value"
          @click="handleSubmit"
        >
          {{ t('common.save') }}
        </BaseButton>
      </div>
    </BaseCard>
  </div>
</template>
