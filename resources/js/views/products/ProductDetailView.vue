<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCustomFields } from '@/composables/useCustomFields'
import { usePermission } from '@/composables/usePermission'
import { productService } from '@/services/productService'
import type { Opportunity, Product } from '@/types'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import CustomFieldValue from '@/components/shared/CustomFieldValue.vue'

type ProductTab = 'details' | 'opportunities'

const { t } = useI18n()
const route = useRoute()
const { can } = usePermission()

const productId = Number(route.params.id)
const product = ref<Product | null>(null)
const opportunities = ref<Opportunity[]>([])
const loading = ref(true)
const opportunitiesLoading = ref(false)
const activeTab = ref<ProductTab>('details')
const opportunitiesLoaded = ref(false)

const categoryId = computed(() => product.value?.category_id ?? product.value?.category?.id ?? null)
const modelFields = useCustomFields('product')
const categoryFields = useCustomFields('product', 'category', categoryId)

async function loadProduct(): Promise<void> {
  loading.value = true

  try {
    product.value = await productService.get(productId)
  } finally {
    loading.value = false
  }
}

async function loadOpportunities(): Promise<void> {
  if (opportunitiesLoaded.value || opportunitiesLoading.value) return

  opportunitiesLoading.value = true

  try {
    opportunities.value = await productService.opportunities(productId)
    opportunitiesLoaded.value = true
  } finally {
    opportunitiesLoading.value = false
  }
}

watch(activeTab, (tab) => {
  if (tab === 'opportunities') void loadOpportunities()
})

onMounted(loadProduct)
</script>

<template>
  <div>
    <div v-if="loading" class="text-center py-5"><BaseSpinner /></div>

    <template v-else-if="product">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 class="mb-1">{{ product.name }}</h2>
          <span class="text-muted">{{ product.sku || '—' }}</span>
        </div>
        <div class="d-flex gap-2">
          <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'products.index' }">
            {{ t('common.back') }}
          </BaseButton>
          <BaseButton
            v-if="can('update-products')"
            variant="primary"
            icon="bi-pencil"
            :to="{ name: 'products.edit', params: { id: product.id } }"
          >
            {{ t('common.edit') }}
          </BaseButton>
        </div>
      </div>

      <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'details' }" @click="activeTab = 'details'">
            <i class="bi bi-info-circle me-1"></i>{{ t('products.detailsTab') }}
          </button>
        </li>
        <li v-if="can('view-opportunities')" class="nav-item">
          <button class="nav-link" :class="{ active: activeTab === 'opportunities' }" @click="activeTab = 'opportunities'">
            <i class="bi bi-kanban me-1"></i>{{ t('products.relatedOpportunities') }}
            <span v-if="opportunitiesLoaded" class="badge bg-secondary ms-1">{{ opportunities.length }}</span>
          </button>
        </li>
      </ul>

      <div v-show="activeTab === 'details'">
        <BaseCard :title="t('common.details')">
          <div class="row g-3">
            <div class="col-md-6">
              <small class="text-muted d-block">{{ t('products.sku') }}</small>
              <span>{{ product.sku || '—' }}</span>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">{{ t('products.unitPrice') }}</small>
              <span>{{ product.currency }} {{ Number(product.unit_price).toLocaleString() }}</span>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">{{ t('products.category') }}</small>
              <span>{{ product.category?.name ?? '—' }}</span>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">{{ t('products.tax') }}</small>
              <span>{{ product.tax ? `${product.tax.name} (${product.tax.percent}%)` : '—' }}</span>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">{{ t('common.status') }}</small>
              <BaseBadge :variant="product.is_active ? 'success' : 'secondary'" pill>
                {{ product.is_active ? t('common.active') : t('common.inactive') }}
              </BaseBadge>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">{{ t('products.createdBy') }}</small>
              <span>{{ product.created_by?.name ?? '—' }}</span>
            </div>
            <div v-if="product.description" class="col-12">
              <small class="text-muted d-block">{{ t('common.description') }}</small>
              <span>{{ product.description }}</span>
            </div>

            <div v-for="definition in modelFields.definitions.value" :key="`model-${definition.id}`" class="col-md-6">
              <small class="text-muted d-block">{{ definition.label }}</small>
              <CustomFieldValue :definition="definition" :value="product.custom_fields?.[definition.name]" />
            </div>
            <div v-for="definition in categoryFields.definitions.value" :key="`category-${definition.id}`" class="col-md-6">
              <small class="text-muted d-block">{{ definition.label }}</small>
              <CustomFieldValue :definition="definition" :value="product.category_custom_fields?.[definition.name]" />
            </div>
          </div>
        </BaseCard>
      </div>

      <div v-show="activeTab === 'opportunities'">
        <BaseCard :title="t('products.relatedOpportunities')">
          <div v-if="opportunitiesLoading" class="text-center py-4"><BaseSpinner size="sm" /></div>
          <div v-else-if="!opportunities.length" class="text-center text-muted py-4">{{ t('products.noRelatedOpportunities') }}</div>
          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>{{ t('opportunities.title') }}</th>
                  <th>{{ t('customers.title') }}</th>
                  <th>{{ t('opportunities.stage') }}</th>
                  <th class="text-end">{{ t('opportunities.amount') }}</th>
                  <th class="text-end">{{ t('products.quantity') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="opportunity in opportunities" :key="opportunity.id">
                  <td>{{ opportunity.title }}</td>
                  <td>{{ opportunity.customer?.display_name ?? '—' }}</td>
                  <td>{{ t(`opportunities.stages.${opportunity.stage}`) }}</td>
                  <td class="text-end">{{ opportunity.currency }} {{ opportunity.amount ?? '—' }}</td>
                  <td class="text-end">{{ opportunity.product_pivot?.quantity ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BaseCard>
      </div>
    </template>
  </div>
</template>
