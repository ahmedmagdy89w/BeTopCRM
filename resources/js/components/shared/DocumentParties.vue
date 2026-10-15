<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DocumentCompany, DocumentCustomer } from '@/types'

const props = defineProps<{
  company: DocumentCompany
  buyer: DocumentCustomer
}>()

const { t } = useI18n()

function nonEmptyLines(lines: Array<string | null | undefined>): string[] {
  return lines.filter((line): line is string => Boolean(line?.trim()))
}

const companyLocality = computed(() => nonEmptyLines([
  props.company.address.city,
  props.company.address.state,
  props.company.address.zip,
]).join(', '))

const companyAddress = computed(() => nonEmptyLines([
  props.company.address.street,
  companyLocality.value,
  props.company.address.country,
]))

const buyerLocality = computed(() => nonEmptyLines([
  props.buyer.address.city,
  props.buyer.address.state,
  props.buyer.address.postal_code,
]).join(', '))

const buyerAddress = computed(() => nonEmptyLines([
  props.buyer.address.line_1,
  props.buyer.address.line_2,
  buyerLocality.value,
  props.buyer.address.country,
]))
</script>

<template>
  <div class="row g-4 document-parties mb-4">
    <div class="col-md-6">
      <div class="small fw-semibold text-uppercase text-body-secondary mb-2">
        {{ t('opportunities.from') }}
      </div>
      <div class="d-flex align-items-start gap-3">
        <img
          v-if="company.logo_url"
          :src="company.logo_url"
          :alt="company.company_name ?? ''"
          class="document-parties__logo border rounded bg-body-tertiary p-2"
        >
        <div v-else class="document-parties__logo border rounded bg-body-tertiary d-flex align-items-center justify-content-center">
          <i class="bi bi-building text-body-secondary fs-3"></i>
        </div>
        <div class="document-parties__details">
          <div class="fw-semibold">{{ company.company_name || t('opportunities.companyNameFallback') }}</div>
          <div v-for="line in companyAddress" :key="line" class="small text-body-secondary">{{ line }}</div>
          <div v-if="company.company_email" class="small text-body-secondary text-break">{{ company.company_email }}</div>
          <div v-if="company.company_phone" class="small text-body-secondary">{{ company.company_phone }}</div>
          <div v-if="company.company_website" class="small text-body-secondary text-break">{{ company.company_website }}</div>
          <div v-if="company.company_tax_number" class="small text-body-secondary">
            {{ t('companySettings.taxNumber') }}: {{ company.company_tax_number }}
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="small fw-semibold text-uppercase text-body-secondary mb-2">
        {{ t('opportunities.billTo') }}
      </div>
      <div class="document-parties__details">
        <div class="fw-semibold">{{ buyer.name || `#${buyer.id}` }}</div>
        <div v-if="buyer.contact_name && buyer.contact_name !== buyer.name" class="small text-body-secondary">
          {{ t('customers.contactName') }}: {{ buyer.contact_name }}<span v-if="buyer.contact_title">, {{ buyer.contact_title }}</span>
        </div>
        <div v-for="line in buyerAddress" :key="line" class="small text-body-secondary">{{ line }}</div>
        <div v-if="buyer.email" class="small text-body-secondary text-break">{{ buyer.email }}</div>
        <div v-if="buyer.phone" class="small text-body-secondary">{{ buyer.phone }}</div>
        <div v-if="buyer.website" class="small text-body-secondary text-break">{{ buyer.website }}</div>
        <div v-if="buyer.tax_id" class="small text-body-secondary">
          {{ t('companySettings.taxNumber') }}: {{ buyer.tax_id }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.document-parties__logo {
  width: 72px;
  height: 72px;
  flex: 0 0 72px;
  object-fit: contain;
}

.document-parties__details {
  min-width: 0;
}
</style>
