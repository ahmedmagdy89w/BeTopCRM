<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useCrudForm } from '@/composables/useCrudForm'
import { useToast } from '@/composables/useToast'
import { useCustomFields } from '@/composables/useCustomFields'
import { opportunityService } from '@/services/opportunityService'
import { customerService } from '@/services/customerService'
import type { Opportunity, Customer } from '@/types'
import type { FormFieldSchema } from '@/types/forms'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import DynamicForm from '@/components/form/DynamicForm.vue'
import CustomerPicker from '@/components/shared/CustomerPicker.vue'
import QuickAddModal from '@/components/form/QuickAddModal.vue'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const customFields = useCustomFields('opportunity')

const nestedCustomerId = computed<number | null>(() =>
  route.params.customerId ? Number(route.params.customerId) : null,
)

const showQuickAdd = ref(false)
const customerError = ref('')

const isCompanyQuickAdd = (v: Record<string, unknown>) => v.type === 'company'

const quickAddSchema = computed<FormFieldSchema[]>(() => [
  {
    key: 'kind', label: t('customers.leadOrCustomer'), type: 'radio', required: true,
    options: [
      { label: t('customers.leadsTab'), value: 'lead' },
      { label: t('customers.customersTab'), value: 'customer' },
    ],
  },
  {
    key: 'type', label: t('customers.type'), type: 'radio', required: true,
    options: [
      { label: t('customers.individual'), value: 'individual' },
      { label: t('customers.company'), value: 'company' },
    ],
  },
  { key: 'company_name', label: t('customers.companyName'), type: 'text', required: true, visible: isCompanyQuickAdd },
  { key: 'first_name', label: t('common.firstName'), type: 'text', required: true, visible: (v) => !isCompanyQuickAdd(v) },
  { key: 'last_name', label: t('common.lastName'), type: 'text', required: true, visible: (v) => !isCompanyQuickAdd(v) },
  { key: 'email', label: t('common.email'), type: 'email' },
  { key: 'phone', label: t('common.phone'), type: 'tel' },
])

async function quickAddSubmit(data: Record<string, unknown>): Promise<unknown> {
  const { kind, ...rest } = data
  const created = await customerService.create({
    ...(rest as Partial<Customer>),
    is_lead: kind !== 'customer',
  })
  return created
}

function onQuickAddCreated(entity: unknown) {
  const customer = entity as Customer
  updateFormData({ ...(formData.value as Partial<Opportunity>), customer_id: customer.id })
  customerError.value = ''
  toast.success(t('common.saveSuccess'))
}

const {
  formData, errors, loading, saving, isEdit,
  loadItem, submit, updateFormData,
} = useCrudForm<Opportunity>({
  getService: (id: number) => opportunityService.get(Number(route.params.customerId), id),
  createService: (data: Partial<Opportunity>) => opportunityService.create(
    nestedCustomerId.value ?? (data.customer_id as number),
    data,
  ),
  updateService: (id: number, data: Partial<Opportunity>) => opportunityService.update(
    nestedCustomerId.value ?? (data.customer_id as number), id, data,
  ),
  redirectTo: (item) => ({ name: 'customers.show', params: { id: item.customer_id } }),
  defaultValues: () => ({ probability: 10 } as Partial<Opportunity>),
  // The picker works with a raw id while the API type is an object —
  // the cast is contained to this form boundary (cf. ProductFormView).
  transform: (item) => ({
    ...item,
    assigned_to: (typeof item.assigned_to === 'object' && item.assigned_to !== null
      ? item.assigned_to.id
      : (item.assigned_to ?? null)) as unknown as { id: number; name: string },
  }),
})

const pickerValue = computed(() => formData.value.customer_id as number | null | undefined)

function onPickerChange(id: number | null) {
  updateFormData({ ...(formData.value as Partial<Opportunity>), customer_id: id ?? undefined })
  if (id != null) customerError.value = ''
}

async function submitWithCustomerCheck() {
  if (!nestedCustomerId.value && !isEdit.value && (formData.value.customer_id as number | undefined) == null) {
    customerError.value = t('opportunities.selectCustomer')
    return
  }
  await submit()
}

const schema = computed<FormFieldSchema[]>(() => [
  { key: 'title', label: t('common.name'), type: 'text', required: true, colSpan: 2 },
  {
    key: 'type', label: t('common.type'), type: 'select', required: true,
    options: ['initial', 'new', 'upsell', 'cross_sell', 'renewal', 'downgrade'].map((value) => ({
      label: t(`opportunities.types.${value}`), value,
    })),
  },
  { key: 'amount', label: t('opportunities.amount'), type: 'number' },
  { key: 'probability', label: 'Probability %', type: 'number' },
  { key: 'expected_close_date', label: t('customers.expectedCloseDate'), type: 'date' },
  { key: 'start_date', label: t('customers.contractStart'), type: 'date' },
  { key: 'end_date', label: t('customers.contractEnd'), type: 'date' },
  { key: 'assigned_to', label: t('opportunities.assignee'), type: 'user' },
  { key: 'notes', label: t('common.notes'), type: 'textarea', colSpan: 2 },
])

onMounted(() => {
  if (isEdit.value) loadItem()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ t('opportunities.title') }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'opportunities.index' }">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <BaseCard :loading="loading">
      <div v-if="!nestedCustomerId && !isEdit" class="mb-3">
        <label class="form-label">{{ t('opportunities.customer') }} *</label>
        <CustomerPicker :model-value="pickerValue ?? null" @update:model-value="onPickerChange" @quick-add="showQuickAdd = true" />
        <div v-if="customerError" class="invalid-feedback d-block">{{ customerError }}</div>
      </div>

      <DynamicForm
        :schema="schema"
        :custom-fields="customFields.definitions.value"
        :model-value="(formData as Record<string, unknown>)"
        :errors="errors"
        :loading="saving"
        :columns="2"
        @update:model-value="updateFormData($event as Partial<Opportunity>)"
        @submit="submitWithCustomerCheck"
      />
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BaseButton variant="secondary" :to="{ name: 'opportunities.index' }">
          {{ t('common.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" :loading="saving" :disabled="customFields.loading.value" @click="submitWithCustomerCheck">
          {{ t('common.save') }}
        </BaseButton>
      </div>
    </BaseCard>

    <QuickAddModal
      v-model="showQuickAdd"
      :title="t('opportunities.newLeadCustomer')"
      :schema="quickAddSchema"
      :submit-fn="quickAddSubmit"
      @created="onQuickAddCreated"
    />
  </div>
</template>
