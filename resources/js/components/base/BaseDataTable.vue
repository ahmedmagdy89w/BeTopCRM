<script setup lang="ts">
import { ref, computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DataTableColumn } from '@/types/forms'
import BasePagination from '@/components/base/BasePagination.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'

const { t } = useI18n()
const searchInputId = useId()

const props = withDefaults(defineProps<{
  columns: DataTableColumn[]
  rows: unknown[]
  loading?: boolean
  total?: number
  perPage?: number
  currentPage?: number
  sortBy?: string
  sortDir?: 'asc' | 'desc'
  selectable?: boolean
  searchable?: boolean
}>(), {
  loading: false,
  total: 0,
  perPage: 15,
  currentPage: 1,
  sortBy: '',
  sortDir: 'asc',
  selectable: false,
  searchable: true,
})

const emit = defineEmits<{
  'update:currentPage': [page: number]
  'update:perPage': [size: number]
  'update:sortBy': [key: string]
  'update:sortDir': [dir: 'asc' | 'desc']
  search: [query: string]
  filter: [key: string, value: unknown]
  'row-click': [row: unknown]
  'selection-change': [selected: unknown[]]
}>()

const searchInput = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    emit('search', searchInput.value)
  }, 300)
}

const selectedSet = ref<Set<number>>(new Set())

const visibleColumns = computed(() =>
  props.columns.filter(c => c.visible !== false),
)

const allSelected = computed(
  () => props.rows.length > 0 && selectedSet.value.size === props.rows.length,
)

function toggleAll() {
  if (allSelected.value) {
    selectedSet.value.clear()
  } else {
    selectedSet.value = new Set(props.rows.map((_, i) => i))
  }
  emitSelection()
}

function toggleRow(index: number) {
  if (selectedSet.value.has(index)) {
    selectedSet.value.delete(index)
  } else {
    selectedSet.value.add(index)
  }
  emitSelection()
}

function emitSelection() {
  emit(
    'selection-change',
    [...selectedSet.value].map(i => props.rows[i]),
  )
}

function handleSort(col: DataTableColumn) {
  if (!col.sortable) return
  if (props.sortBy === col.key) {
    emit('update:sortDir', props.sortDir === 'asc' ? 'desc' : 'asc')
  } else {
    emit('update:sortBy', col.key)
    emit('update:sortDir', 'asc')
  }
}

function getCellValue(row: unknown, key: string): unknown {
  const obj = row as Record<string, unknown>
  return key.split('.').reduce<unknown>((v, k) => (v as Record<string, unknown>)?.[k], obj)
}
</script>

<template>
  <div class="base-data-table">
    <!-- Toolbar -->
    <div v-if="$slots['bulk-actions'] || $slots['header-actions']" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div class="d-flex align-items-center gap-2">
        <slot name="bulk-actions" />
        <slot name="header-actions" />
      </div>
    </div>

    <div v-if="$slots.filters || searchable" class="bg-body-tertiary border rounded p-3 mb-3">
      <div class="d-flex flex-wrap align-items-end gap-3">
        <div v-if="searchable" class="table-search">
          <label class="form-label table-search__label" :for="searchInputId">{{ t('common.search') }}</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input
              :id="searchInputId"
              v-model="searchInput"
              type="text"
              class="form-control"
              :placeholder="t('common.search')"
              @input="onSearchInput"
            >
          </div>
        </div>
        <slot name="filters" />
      </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th v-if="selectable" style="width: 40px;">
              <input
                class="form-check-input"
                type="checkbox"
                :checked="allSelected"
                @change="toggleAll"
              >
            </th>
            <th
              v-for="col in visibleColumns"
              :key="col.key"
              :style="col.width ? { width: col.width } : undefined"
              :class="[
                col.align ? `text-${col.align}` : '',
                { 'sortable-header': col.sortable },
              ]"
              @click="handleSort(col)"
            >
              <span class="d-inline-flex align-items-center gap-1">
                {{ col.label }}
                <template v-if="col.sortable">
                  <i
                    v-if="sortBy === col.key"
                    :class="['bi', sortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down']"
                  ></i>
                  <i v-else class="bi bi-arrow-down-up text-muted" style="font-size: 0.75em;"></i>
                </template>
              </span>
            </th>
            <th v-if="$slots['row-actions']" class="text-end" style="width: 100px;">
              {{ t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading -->
          <tr v-if="loading">
            <td :colspan="visibleColumns.length + (selectable ? 1 : 0) + ($slots['row-actions'] ? 1 : 0)" class="text-center py-5">
              <BaseSpinner />
            </td>
          </tr>
          <!-- Empty -->
          <tr v-else-if="rows.length === 0">
            <td :colspan="visibleColumns.length + (selectable ? 1 : 0) + ($slots['row-actions'] ? 1 : 0)" class="text-center py-5 text-muted">
              <slot name="empty">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                {{ t('common.noData') }}
              </slot>
            </td>
          </tr>
          <!-- Rows -->
          <tr
            v-for="(row, idx) in rows"
            v-else
            :key="idx"
            :class="{ 'table-active': selectedSet.has(idx) }"
            style="cursor: pointer;"
            @click="emit('row-click', row)"
          >
            <td v-if="selectable" @click.stop>
              <input
                class="form-check-input"
                type="checkbox"
                :checked="selectedSet.has(idx)"
                @change="toggleRow(idx)"
              >
            </td>
            <td
              v-for="col in visibleColumns"
              :key="col.key"
              :class="col.align ? `text-${col.align}` : ''"
            >
              <slot :name="`cell-${col.key}`" :row="row" :value="getCellValue(row, col.key)">
                {{ getCellValue(row, col.key) }}
              </slot>
            </td>
            <td v-if="$slots['row-actions']" class="text-end" @click.stop>
              <slot name="row-actions" :row="row" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="total > 0" class="mt-3">
      <BasePagination
        :total="total"
        :per-page="perPage"
        :current-page="currentPage"
        @update:current-page="emit('update:currentPage', $event)"
        @update:per-page="emit('update:perPage', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.sortable-header {
  cursor: pointer;
  user-select: none;
}

.sortable-header:hover {
  background-color: var(--bs-tertiary-bg);
}

.table-search {
  width: min(100%, 20rem);
}

.table-search__label {
  display: block;
  margin-bottom: 0.35rem;
  color: var(--bs-secondary-color);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}
</style>
