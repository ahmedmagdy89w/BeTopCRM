<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermission } from '@/composables/usePermission'
import { importExportService } from '@/services/importExportService'
import { useProductLabels } from '@/composables/useProductLabels'
import type { Import } from '@/types'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseBadge from '@/components/base/BaseBadge.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import type { BadgeVariant } from '@/utils/constants'

const { t } = useI18n()
const { can } = usePermission()
const { plural: productPlural } = useProductLabels()

function entityTypeLabel(entityType: string): string {
  if (entityType === 'products') return productPlural.value
  return t(`importExport.types.${entityType}`)
}

const ENTITY_TYPES = ['leads', 'customers', 'opportunities', 'products', 'categories'] as const
const EXPORT_TYPES = ['leads', 'customers', 'opportunities', 'products', 'categories'] as const

const STATUS_COLORS: Record<string, BadgeVariant> = {
  pending: 'secondary',
  processing: 'warning',
  completed: 'success',
  failed: 'danger',
}

// Import state
const selectedEntityType = ref<string>('leads')
const importFile = ref<File | null>(null)
const importing = ref(false)
const imports = ref<Import[]>([])
const importsLoading = ref(false)
const activeImport = ref<Import | null>(null)

// Poll interval
let pollTimer: ReturnType<typeof setInterval> | null = null

async function fetchImports() {
  importsLoading.value = true
  try {
    const res = await importExportService.listImports()
    imports.value = res.data
  } finally {
    importsLoading.value = false
  }
}

function onFileChange(event: Event) {
  const input = event.target as HTMLInputElement
  importFile.value = input.files?.[0] ?? null
}

async function startImport() {
  if (!importFile.value) return
  importing.value = true
  try {
    const result = await importExportService.startImport(selectedEntityType.value, importFile.value)
    activeImport.value = result
    importFile.value = null
    // Start polling for progress
    startPolling(result.id)
    fetchImports()
  } finally {
    importing.value = false
  }
}

function startPolling(importId: number) {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const updated = await importExportService.getImport(importId)
      activeImport.value = updated
      if (updated.status === 'completed' || updated.status === 'failed') {
        stopPolling()
        fetchImports()
      }
    } catch {
      stopPolling()
    }
  }, 2000)
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function downloadTemplate(entityType: string) {
  window.open(importExportService.templateUrl(entityType), '_blank')
}

function exportData(entityType: string) {
  window.open(importExportService.exportUrl(entityType), '_blank')
}

onMounted(() => {
  if (can('import-data')) fetchImports()
})
</script>

<template>
  <div>
    <h2 class="mb-3">{{ t('importExport.title') }}</h2>

    <div class="row g-3">
      <!-- Import Section -->
      <div class="col-lg-6" v-if="can('import-data')">
        <BaseCard :title="t('importExport.import')" icon="bi-upload">
          <div class="mb-3">
            <label class="form-label">{{ t('importExport.entityType') }}</label>
            <select v-model="selectedEntityType" class="form-select">
              <option v-for="et in ENTITY_TYPES" :key="et" :value="et">{{ entityTypeLabel(et) }}</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ t('importExport.csvFile') }}</label>
            <input type="file" class="form-control" accept=".csv,.txt" @change="onFileChange">
          </div>

          <div class="d-flex gap-2 mb-3">
            <BaseButton
              variant="primary"
              icon="bi-upload"
              :loading="importing"
              :disabled="!importFile"
              @click="startImport"
            >
              {{ t('importExport.startImport') }}
            </BaseButton>
            <BaseButton
              variant="outline-secondary"
              icon="bi-download"
              @click="downloadTemplate(selectedEntityType)"
            >
              {{ t('importExport.downloadTemplate') }}
            </BaseButton>
          </div>

          <!-- Active Import Progress -->
          <div v-if="activeImport" class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong>{{ activeImport.original_filename }}</strong>
              <BaseBadge :variant="STATUS_COLORS[activeImport.status] ?? 'secondary'" pill>{{ activeImport.status }}</BaseBadge>
            </div>
            <div v-if="activeImport.total_rows" class="mb-2">
              <div class="progress" style="height: 8px;">
                <div
                  class="progress-bar"
                  :class="activeImport.status === 'failed' ? 'bg-danger' : 'bg-success'"
                  :style="{ width: `${(activeImport.processed_rows / activeImport.total_rows) * 100}%` }"
                ></div>
              </div>
              <small class="text-muted">
                {{ activeImport.processed_rows }} / {{ activeImport.total_rows }}
                ({{ activeImport.successful_rows }} {{ t('importExport.successful') }}, {{ activeImport.failed_rows }} {{ t('importExport.failed') }})
              </small>
            </div>
            <!-- Errors -->
            <div v-if="activeImport.errors?.length" class="mt-2">
              <details>
                <summary class="text-danger small">{{ activeImport.errors.length }} {{ t('importExport.errors') }}</summary>
                <table class="table table-sm mt-1 mb-0">
                  <thead><tr><th>{{ t('importExport.row') }}</th><th>{{ t('auditLogs.field') }}</th><th>{{ t('importExport.message') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="(err, idx) in activeImport.errors.slice(0, 20)" :key="idx">
                      <td>{{ err.row }}</td>
                      <td>{{ err.field }}</td>
                      <td>{{ err.message }}</td>
                    </tr>
                  </tbody>
                </table>
              </details>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- Export Section -->
      <div class="col-lg-6" v-if="can('export-data')">
        <BaseCard :title="t('importExport.export')" icon="bi-download">
          <p class="text-muted mb-3">{{ t('importExport.exportDescription') }}</p>
          <div class="list-group">
            <button
              v-for="et in EXPORT_TYPES"
              :key="et"
              class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
              @click="exportData(et)"
            >
              <span>{{ entityTypeLabel(et) }}</span>
              <i class="bi bi-download text-primary"></i>
            </button>
          </div>
        </BaseCard>
      </div>
    </div>

    <!-- Recent Imports -->
    <BaseCard v-if="can('import-data')" :title="t('importExport.recentImports')" icon="bi-clock-history" class="mt-3">
      <div v-if="importsLoading" class="text-center py-3"><BaseSpinner /></div>
      <div v-else-if="!imports.length" class="text-center text-muted py-3">{{ t('common.noData') }}</div>
      <table v-else class="table table-sm mb-0">
        <thead>
          <tr>
            <th>{{ t('importExport.filename') }}</th>
            <th>{{ t('importExport.entityType') }}</th>
            <th>{{ t('common.status') }}</th>
            <th class="text-center">{{ t('importExport.rows') }}</th>
            <th class="text-center">{{ t('importExport.successful') }}</th>
            <th class="text-center">{{ t('importExport.failed') }}</th>
            <th>{{ t('common.date') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="imp in imports" :key="imp.id">
            <td>{{ imp.original_filename }}</td>
            <td class="text-capitalize">{{ imp.entity_type }}</td>
            <td>
              <BaseBadge :variant="STATUS_COLORS[imp.status] ?? 'secondary'" pill>{{ imp.status }}</BaseBadge>
            </td>
            <td class="text-center">{{ imp.total_rows ?? '—' }}</td>
            <td class="text-center text-success">{{ imp.successful_rows }}</td>
            <td class="text-center text-danger">{{ imp.failed_rows }}</td>
            <td>{{ new Date(imp.created_at).toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </BaseCard>
  </div>
</template>
