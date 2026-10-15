<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = withDefaults(defineProps<{
  total: number
  perPage: number
  currentPage: number
  perPageOptions?: number[]
}>(), {
  perPageOptions: () => [15, 25, 50, 100],
})

const emit = defineEmits<{
  'update:currentPage': [page: number]
  'update:perPage': [size: number]
}>()

const lastPage = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))

const from = computed(() => (props.total === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1))
const to = computed(() => Math.min(props.currentPage * props.perPage, props.total))

const pages = computed(() => {
  const total = lastPage.value
  const current = props.currentPage
  const delta = 2
  const range: number[] = []

  for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
    range.push(i)
  }

  const result: (number | null)[] = []
  const first = range[0] ?? 1
  const last = range[range.length - 1] ?? 1
  if (first > 1) {
    result.push(1)
    if (first > 2) result.push(null) // ellipsis
  }
  result.push(...range)
  if (last < total) {
    if (last < total - 1) result.push(null) // ellipsis
    result.push(total)
  }
  return result
})

function changePage(page: number) {
  if (page >= 1 && page <= lastPage.value && page !== props.currentPage) {
    emit('update:currentPage', page)
  }
}

function changePerPage(event: Event) {
  const value = Number((event.target as HTMLSelectElement).value)
  emit('update:perPage', value)
  emit('update:currentPage', 1)
}
</script>

<template>
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <!-- Info -->
    <small class="text-muted">
      {{ t('common.showing', { from, to, total }) }}
    </small>

    <div class="d-flex align-items-center gap-3">
      <!-- Per page -->
      <div class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 small text-muted text-nowrap">{{ t('common.perPage') }}</label>
        <select class="form-select form-select-sm" style="width: auto;" :value="perPage" @change="changePerPage">
          <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
        </select>
      </div>

      <!-- Pages -->
      <nav v-if="lastPage > 1" aria-label="pagination">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item" :class="{ disabled: currentPage === 1 }">
            <button class="page-link" @click="changePage(currentPage - 1)">&laquo;</button>
          </li>
          <li
            v-for="(page, idx) in pages"
            :key="idx"
            class="page-item"
            :class="{ active: page === currentPage, disabled: page === null }"
          >
            <span v-if="page === null" class="page-link">&hellip;</span>
            <button v-else class="page-link" @click="changePage(page)">{{ page }}</button>
          </li>
          <li class="page-item" :class="{ disabled: currentPage === lastPage }">
            <button class="page-link" @click="changePage(currentPage + 1)">&raquo;</button>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</template>
