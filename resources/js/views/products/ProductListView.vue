<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useDataTable } from '@/composables/useDataTable'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { usePermission } from '@/composables/usePermission'
import { useProductLabels } from '@/composables/useProductLabels'
import { productService } from '@/services/productService'
import { productCategoryService } from '@/services/productCategoryService'
import type { Product, ProductCategory } from '@/types'
import type { DataTableColumn } from '@/types/forms'
import BaseDataTable from '@/components/base/BaseDataTable.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import ConfirmDialog from '@/components/base/ConfirmDialog.vue'
import CustomFieldFilterBar from '@/components/shared/CustomFieldFilterBar.vue'
import FilterControl from '@/components/shared/FilterControl.vue'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()
const { show: confirmShow, message: confirmMsg, confirm, accept, cancel } = useConfirmDialog()
const { plural: productPlural, singular: productSingular, hasCustom: hasCustomProductLabel } = useProductLabels()
const newProductLabel = computed(() => hasCustomProductLabel.value
  ? `${t('common.create')} ${productSingular.value}`
  : t('products.newProduct'))
const categories = ref<ProductCategory[]>([])
const categoryFilter = ref<number | undefined>()
const statusFilter = ref<string | undefined>()

const categoryOptions = computed(() => categories.value.map(category => ({ label: category.name, value: category.id })))
const statusOptions = computed(() => [
  { label: t('common.active'), value: '1' },
  { label: t('common.inactive'), value: '0' },
])

const columns: DataTableColumn[] = [
  { key: 'name', label: t('products.name'), sortable: true },
  { key: 'sku', label: t('products.sku'), sortable: true },
  { key: 'unit_price', label: t('products.unitPrice'), sortable: true, align: 'end' },
  { key: 'tax', label: t('products.tax'), sortable: false },
  { key: 'category.name', label: t('products.category'), sortable: false },
  { key: 'is_active', label: t('common.status'), sortable: true },
  { key: 'created_at', label: t('common.createdAt'), sortable: true, type: 'date' },
]

const {
  rows, loading, total, currentPage, perPage,
  sortBy, sortDir, onSearch, onFilter, refresh,
} = useDataTable<Product>(
  (params) => productService.list(params as Record<string, unknown> & { page?: number }),
  { defaultSort: 'name', defaultSortDir: 'asc', immediate: true },
)

function goToDetail(row: unknown) {
  const p = row as Product
  router.push({ name: 'products.show', params: { id: p.id } })
}

function updateCategoryFilter(value: unknown) {
  categoryFilter.value = typeof value === 'number' ? value : undefined
  onFilter('category_id', categoryFilter.value)
}

function updateStatusFilter(value: unknown) {
  statusFilter.value = typeof value === 'string' ? value : undefined
  onFilter('is_active', statusFilter.value)
}

async function handleDelete(product: Product) {
  const confirmed = await confirm(t('common.confirmDelete'))
  if (!confirmed) return
  await productService.delete(product.id)
  refresh()
}

onMounted(async () => {
  try { categories.value = await productCategoryService.list() } catch { /* optional */ }
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ productPlural }}</h2>
      <div class="d-flex gap-2">
        <BaseButton
          v-if="can('view-products')"
          variant="outline-secondary"
          icon="bi-folder"
          :to="{ name: 'productCategories.index' }"
        >
          {{ t('products.categories') }}
        </BaseButton>
        <BaseButton
          v-if="can('create-products')"
          variant="primary"
          icon="bi-plus-lg"
          :to="{ name: 'products.create' }"
        >
          {{ newProductLabel }}
        </BaseButton>
      </div>
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
      @row-click="goToDetail"
    >
      <template #filters>
        <FilterControl
          :label="t('products.category')"
          :model-value="categoryFilter"
          :options="categoryOptions"
          @update:model-value="updateCategoryFilter"
        />
        <FilterControl
          :label="t('common.status')"
          :model-value="statusFilter"
          :options="statusOptions"
          @update:model-value="updateStatusFilter"
        />
        <CustomFieldFilterBar
          entity-type="product"
          :category-ids="categories.map(category => category.id)"
          @change="onFilter"
        />
      </template>

      <template #cell-unit_price="{ row }">
        {{ (row as Product).currency }} {{ (row as Product).unit_price }}
      </template>
      <template #cell-tax="{ row }">
        {{ (row as Product).tax ? `${(row as Product).tax!.name} (${(row as Product).tax!.percent}%)` : '—' }}
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
            v-if="can('update-products')"
            variant="outline-primary"
            size="sm"
            icon="bi-pencil"
            :to="{ name: 'products.edit', params: { id: (row as Product).id } }"
            @click.stop
          />
          <BaseButton
            v-if="can('delete-products')"
            variant="outline-danger"
            size="sm"
            icon="bi-trash"
            @click.stop="handleDelete(row as Product)"
          />
        </div>
      </template>
    </BaseDataTable>

    <ConfirmDialog :show="confirmShow" :message="confirmMsg" @accept="accept" @cancel="cancel" />
  </div>
</template>
