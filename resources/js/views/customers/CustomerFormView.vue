<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrudForm } from '@/composables/useCrudForm'
import { useCustomFields } from '@/composables/useCustomFields'
import { customerService } from '@/services/customerService'
import type { Customer, ContactEntry, AddressEntry } from '@/types'
import type { FormFieldSchema } from '@/types/forms'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import DynamicForm from '@/components/form/DynamicForm.vue'
import ContactEntriesEditor from '@/components/shared/ContactEntriesEditor.vue'
import AddressEntriesEditor from '@/components/shared/AddressEntriesEditor.vue'

const { t } = useI18n()
const customFields = useCustomFields('customer')

const {
  formData, errors, loading, saving, isEdit,
  loadItem, submit, updateFormData,
} = useCrudForm<Customer>({
  getService: (id) => customerService.get(id),
  createService: (data) => customerService.create(data),
  updateService: (id, data) => customerService.update(id, data),
  redirectTo: (item) => ({ name: 'customers.show', params: { id: item.id } }),
  defaultValues: () => ({ type: 'company', is_lead: true } as Partial<Customer>),
  transform: (item) => ({
    ...item,
    assigned_to: typeof item.assigned_to === 'object' && item.assigned_to !== null
      ? item.assigned_to.id
      : (item.assigned_to ?? null),
    tag_ids: item.tags?.map(t => t.id) ?? [],
  }),
  preparePayload: (data) => {
    const { tag_ids: _, ...rest } = data as Record<string, unknown>
    return rest as Partial<Customer>
  },
  afterSave: async (item, data) => {
    if (data.tag_ids?.length) await customerService.syncTags(item.id, data.tag_ids ?? [])
  },
})

const isCompany = computed(() => (formData.value.type ?? 'company') === 'company')
const isLead = computed(() => formData.value.is_lead !== false)
const contactsValue = computed(() => (formData.value.contacts ?? []) as ContactEntry[])
const addressesValue = computed(() => (formData.value.addresses ?? []) as AddressEntry[])

const baseSchema = computed<FormFieldSchema[]>(() => [
  {
    key: 'type', label: t('customers.type'), type: 'select', required: true,
    options: [
      { label: t('customers.company'), value: 'company' },
      { label: t('customers.individual'), value: 'individual' },
    ],
  },
  { key: 'is_lead', label: t('customers.isLead'), type: 'boolean' },
])

const individualSchema = computed<FormFieldSchema[]>(() => [
  { key: 'first_name', label: t('common.firstName'), type: 'text', required: !isCompany.value, visible: !isCompany.value },
  { key: 'last_name', label: t('common.lastName'), type: 'text', required: !isCompany.value, visible: !isCompany.value },
  { key: 'email', label: t('common.email'), type: 'email', visible: !isCompany.value },
  { key: 'phone', label: t('common.phone'), type: 'tel', visible: !isCompany.value },
  { key: 'job_title', label: t('customers.jobTitle'), type: 'text', visible: !isCompany.value },
])

const companySchema = computed<FormFieldSchema[]>(() => [
  { key: 'company_name', label: t('customers.companyName'), type: 'text', required: isCompany.value, visible: isCompany.value },
  { key: 'email', label: t('common.email'), type: 'email', visible: isCompany.value },
  { key: 'phone', label: t('common.phone'), type: 'tel', visible: isCompany.value },
  { key: 'industry', label: t('customers.industry'), type: 'text', visible: isCompany.value },
  { key: 'website', label: t('customers.website'), type: 'url', visible: isCompany.value },
  { key: 'tax_id', label: t('customers.taxId'), type: 'text', visible: isCompany.value },
])

const pipelineSchema = computed<FormFieldSchema[]>(() => [
  { key: 'source', label: t('customers.source'), type: 'select', options: [
    { label: 'Web', value: 'web' }, { label: 'Referral', value: 'referral' },
    { label: 'Cold call', value: 'cold_call' }, { label: 'Advertisement', value: 'advertisement' },
    { label: 'Social media', value: 'social_media' }, { label: 'Trade show', value: 'trade_show' },
    { label: 'Other', value: 'other' },
  ]},
  { key: 'estimated_value', label: t('customers.estimatedValue'), type: 'number' },
  { key: 'expected_close_date', label: t('customers.expectedCloseDate'), type: 'date' },
  { key: 'assigned_to', label: t('customers.assignedTo'), type: 'user' },
  {
    key: 'lead_status', label: t('customers.leadStatus'), type: 'select', visible: isLead.value && isEdit.value,
    options: [
      { label: 'new', value: 'new' }, { label: 'contacted', value: 'contacted' },
      { label: 'qualified', value: 'qualified' }, { label: 'proposal', value: 'proposal' },
      { label: 'negotiation', value: 'negotiation' }, { label: 'lost', value: 'lost' },
    ],
  },
  {
    key: 'status', label: t('customers.status'), type: 'select', visible: !isLead.value,
    options: [
      { label: 'active', value: 'active' }, { label: 'at_risk', value: 'at_risk' },
      { label: 'churned', value: 'churned' }, { label: 'paused', value: 'paused' },
      { label: 'renewed', value: 'renewed' },
    ],
  },
  { key: 'description', label: t('common.description'), type: 'textarea', colSpan: 2 },
  { key: 'notes', label: t('common.notes'), type: 'textarea', colSpan: 2 },
  { key: 'tag_ids', label: t('tags.title'), type: 'tag', colSpan: 2 },
])

const schema = computed(() => [...baseSchema.value, ...companySchema.value, ...individualSchema.value, ...pipelineSchema.value])

function setContacts(v: ContactEntry[]) {
  updateFormData({ ...(formData.value as Partial<Customer>), contacts: v })
}

function setAddresses(v: AddressEntry[]) {
  updateFormData({ ...(formData.value as Partial<Customer>), addresses: v })
}

onMounted(() => {
  if (isEdit.value) loadItem()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ isEdit ? t('common.edit') : t('common.create') }} {{ t('customers.title') }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'customers.index' }">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <BaseCard :loading="loading">
      <DynamicForm
        :schema="schema"
        :custom-fields="customFields.definitions.value"
        :model-value="(formData as Record<string, unknown>)"
        :errors="errors"
        :loading="saving"
        :columns="2"
        @update:model-value="updateFormData($event as Partial<Customer>)"
        @submit="submit"
      />

      <h5 class="mt-4">{{ t('customers.contacts') }}</h5>
      <ContactEntriesEditor :model-value="contactsValue" @update:model-value="setContacts" />

      <h5 class="mt-4">{{ t('customers.addresses') }}</h5>
      <AddressEntriesEditor :model-value="addressesValue" @update:model-value="setAddresses" />

      <div class="d-flex justify-content-end gap-2 mt-4">
        <BaseButton variant="secondary" :to="{ name: 'customers.index' }">
          {{ t('common.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" :loading="saving" :disabled="customFields.loading.value" @click="submit">
          {{ t('common.save') }}
        </BaseButton>
      </div>
    </BaseCard>
  </div>
</template>
