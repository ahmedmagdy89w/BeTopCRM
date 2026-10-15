<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrudForm } from '@/composables/useCrudForm'
import { campaignService } from '@/services/campaignService'
import { templateService } from '@/services/templateService'
import { useTagStore } from '@/stores/tags'
import { usePermission } from '@/composables/usePermission'
import type { Campaign, EmailTemplate } from '@/types'
import type { FormFieldSchema } from '@/types'
import { LEAD_STATUS_COLORS, CUSTOMER_STATUS_COLORS } from '@/utils/constants'
import DynamicForm from '@/components/form/DynamicForm.vue'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import CustomFieldFilterBar from '@/components/shared/CustomFieldFilterBar.vue'

const { t } = useI18n()
const tagStore = useTagStore()
const { can } = usePermission()

const templates = ref<EmailTemplate[]>([])
const leadStatuses = Object.keys(LEAD_STATUS_COLORS)
const customerStatuses = Object.keys(CUSTOMER_STATUS_COLORS)
const sources = ['web', 'website', 'referral', 'cold_call', 'advertisement', 'social_media', 'trade_show', 'other']

const {
  formData,
  errors,
  saving,
  loading,
  isEdit,
  loadItem,
  submit,
  updateFormData,
} = useCrudForm<Campaign>({
  getService: (id) => campaignService.get(id),
  createService: (data) => campaignService.create(data as Record<string, unknown>),
  updateService: (id, data) => campaignService.update(id, data as Record<string, unknown>),
  redirectTo: { name: 'campaigns.index' },
  defaultValues: () => ({ name: '', status: 'draft', scheduled_at: '', segment_audience: 'all', segment_filters: {} } as Partial<Campaign>),
  afterSave: async (campaign, data) => {
    if (data.scheduled_at && ['draft', 'scheduled'].includes(campaign.status)) {
      await campaignService.schedule(campaign.id, { scheduled_at: data.scheduled_at })
    }
  },
  transform: (item) => {
    const filters = (item.segment_filters ?? {}) as Record<string, unknown>
    return {
      name: item.name,
      status: item.status,
      scheduled_at: item.scheduled_at ? toLocalDateTime(item.scheduled_at) : '',
      email_template_id: item.template?.id,
      segment_audience: filters.is_lead === true ? 'leads' : filters.is_lead === false ? 'customers' : 'all',
      segment_type: filters.type as string | undefined,
      segment_status: (filters.effective_status ?? filters.lead_status ?? filters.status) as string | undefined,
      segment_source: filters.source as string | undefined,
      segment_assigned_to: filters.assigned_to as number | undefined,
      segment_account_manager_id: filters.account_manager_id as number | undefined,
      segment_health_below: filters.health_below as number | undefined,
      segment_follow_up_due: filters.follow_up_due ? '1' : '',
      segment_contact: filters.contact as string | undefined,
      segment_city: filters.city as string | undefined,
      segment_score_min: filters.score_min as number | undefined,
      segment_score_max: filters.score_max as number | undefined,
      segment_customer_id: filters.customer_id as number | undefined,
      segment_tag: filters.tag as string | number | undefined,
      segment_custom_filters: Object.fromEntries(
        Object.entries(filters).filter(([key]) => key.startsWith('cf_') || key.startsWith('ccf_')),
      ),
    } as unknown as Partial<Campaign>
  },
  preparePayload: (data) => {
    const d = data as Record<string, unknown>
    const segment_filters: Record<string, unknown> = {}
    const filterFields: Record<string, string> = {
      type: 'segment_type',
      source: 'segment_source',
      assigned_to: 'segment_assigned_to',
      account_manager_id: 'segment_account_manager_id',
      health_below: 'segment_health_below',
      contact: 'segment_contact',
      city: 'segment_city',
      score_min: 'segment_score_min',
      score_max: 'segment_score_max',
      customer_id: 'segment_customer_id',
      tag: 'segment_tag',
    }

    if (d.segment_audience === 'leads') segment_filters.is_lead = true
    if (d.segment_audience === 'customers') segment_filters.is_lead = false
    if (d.segment_status) {
      const statusFilter = d.segment_audience === 'leads'
        ? 'lead_status'
        : d.segment_audience === 'customers' ? 'status' : 'effective_status'
      segment_filters[statusFilter] = d.segment_status
    }
    if (d.segment_follow_up_due === '1') segment_filters.follow_up_due = true

    for (const [filter, field] of Object.entries(filterFields)) {
      const value = d[field]
      if (value !== undefined && value !== null && value !== '') segment_filters[filter] = value
    }

    const customFilters = d.segment_custom_filters as Record<string, unknown> | undefined
    for (const [key, value] of Object.entries(customFilters ?? {})) {
      if (value !== undefined && value !== null && value !== '') segment_filters[key] = value
    }

    return {
      name: d.name,
      email_template_id: d.email_template_id,
      segment_filters,
    } as Partial<Campaign>
  },
})

const formValues = computed(() => formData.value as Record<string, unknown>)
const customFilterValues = computed(() => formValues.value.segment_custom_filters as Record<string, unknown> | undefined)

function toLocalDateTime(value: string): string {
  const date = new Date(value)
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
}

const schema = computed<FormFieldSchema[]>(() => [
  { key: 'name', label: t('campaigns.name'), type: 'text', required: true },
  {
    key: 'scheduled_at',
    label: t('campaigns.scheduledAt'),
    type: 'datetime',
    required: formValues.value.status === 'scheduled',
    visible: values => values.status === 'draft' || values.status === 'scheduled',
  },
  {
    key: 'email_template_id',
    label: t('campaigns.template'),
    type: 'select',
    required: true,
    options: templates.value.map(tmpl => ({ label: tmpl.name, value: tmpl.id })),
  },
  {
    key: 'segment_audience',
    label: t('campaigns.audience'),
    type: 'select',
    options: [
      { label: t('campaigns.allLeadsAndCustomers'), value: 'all' },
      { label: t('customers.leadsTab'), value: 'leads' },
      { label: t('customers.customersTab'), value: 'customers' },
    ],
  },
  {
    key: 'segment_type',
    label: t('customers.type'),
    type: 'select',
    options: [
      { label: t('common.all'), value: '' },
      { label: t('customers.individual'), value: 'individual' },
      { label: t('customers.company'), value: 'company' },
    ],
  },
  {
    key: 'segment_status',
    label: t('customers.status'),
    type: 'select',
    options: [
      { label: t('common.all'), value: '' },
      ...(formValues.value.segment_audience === 'leads'
        ? leadStatuses.map(status => ({ label: t(`customers.leadStatuses.${status}`), value: status }))
        : formValues.value.segment_audience === 'customers'
          ? customerStatuses.map(status => ({ label: t(`customers.statuses.${status}`), value: status }))
          : [
              ...leadStatuses.map(status => ({ label: t(`customers.leadStatuses.${status}`), value: status })),
              ...customerStatuses.map(status => ({ label: t(`customers.statuses.${status}`), value: status })),
            ]),
    ],
  },
  {
    key: 'segment_source',
    label: t('customers.source'),
    type: 'select',
    options: [
      { label: t('common.all'), value: '' },
      ...sources.map(source => ({ label: t(`customers.sources.${source}`), value: source })),
    ],
  },
  { key: 'segment_health_below', label: t('campaigns.healthBelow'), type: 'number', min: '0', max: '100' },
  { key: 'segment_score_min', label: t('campaigns.scoreMinimum'), type: 'number', min: '0', max: '100' },
  { key: 'segment_score_max', label: t('campaigns.scoreMaximum'), type: 'number', min: '0', max: '100' },
  {
    key: 'segment_follow_up_due',
    label: t('campaigns.followUpDue'),
    type: 'select',
    options: [
      { label: t('common.all'), value: '' },
      { label: t('campaigns.onlyFollowUpDue'), value: '1' },
    ],
  },
  { key: 'segment_contact', label: t('campaigns.contactFilter'), type: 'text' },
  { key: 'segment_city', label: t('campaigns.cityFilter'), type: 'text' },
  { key: 'segment_assigned_to', label: t('campaigns.assignedUserId'), type: 'number', min: '1', helpText: t('campaigns.userIdHelp') },
  { key: 'segment_account_manager_id', label: t('campaigns.accountManagerUserId'), type: 'number', min: '1', helpText: t('campaigns.userIdHelp') },
  { key: 'segment_customer_id', label: t('campaigns.customerId'), type: 'number', min: '1' },
  {
    key: 'segment_tag',
    label: t('campaigns.segmentTag'),
    type: 'select',
    options: [
      { label: t('common.all'), value: '' },
      ...tagStore.tags.map(tag => ({ label: tag.name, value: tag.id })),
    ],
  },
])

function updateCustomFilter(key: string, value: unknown) {
  const current = customFilterValues.value ?? {}
  updateFormData({
    ...formValues.value,
    segment_custom_filters: { ...current, [key]: value },
  } as Partial<Campaign>)
}

const pageTitle = computed(() =>
  isEdit.value ? t('common.edit') + ' ' + t('campaigns.title') : t('campaigns.newCampaign'),
)

onMounted(async () => {
  const res = await templateService.list({ per_page: 100 })
  templates.value = res.data
  await tagStore.fetchTags()
  if (isEdit.value) loadItem()
})
</script>

<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">{{ pageTitle }}</h2>
      <BaseButton variant="outline-secondary" icon="bi-arrow-left" :to="{ name: 'campaigns.index' }">
        {{ t('common.back') }}
      </BaseButton>
    </div>

    <BaseCard :loading="loading">
      <DynamicForm
        :schema="schema"
        :columns="2"
        :model-value="(formData as Record<string, unknown>)"
        :errors="errors"
        :loading="saving"
        @update:model-value="updateFormData($event as Partial<Campaign>)"
        @submit="submit"
      />
      <div v-if="can('create-custom-fields')" class="mt-3">
        <CustomFieldFilterBar
          entity-type="customer"
          :model-value="customFilterValues"
          @change="updateCustomFilter"
        />
      </div>
      <div class="d-flex justify-content-end gap-2 mt-3">
        <BaseButton variant="secondary" :to="{ name: 'campaigns.index' }">
          {{ t('common.cancel') }}
        </BaseButton>
        <BaseButton variant="primary" :loading="saving" @click="submit">
          {{ t('common.save') }}
        </BaseButton>
      </div>
    </BaseCard>
  </div>
</template>
