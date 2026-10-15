import { ref, watch } from 'vue'
import type { PaginatedResponse } from '@/types'

export interface UseDataTableOptions {
  defaultSort?: string
  defaultSortDir?: 'asc' | 'desc'
  defaultPerPage?: number
  defaultFilters?: Record<string, unknown>
  immediate?: boolean
}

export function useDataTable<T>(
  fetchFn: (params: Record<string, unknown>) => Promise<PaginatedResponse<T>>,
  options: UseDataTableOptions = {},
) {
  const rows = ref<T[]>([])
  const loading = ref(false)
  const total = ref(0)
  const currentPage = ref(1)
  const perPage = ref(options.defaultPerPage ?? 15)
  const sortBy = ref(options.defaultSort ?? '')
  const sortDir = ref<'asc' | 'desc'>(options.defaultSortDir ?? 'asc')
  const searchQuery = ref('')
  const filters = ref<Record<string, unknown>>(options.defaultFilters ? { ...options.defaultFilters } : {})

  async function fetch() {
    loading.value = true
    try {
      const params: Record<string, unknown> = {
        page: currentPage.value,
        per_page: perPage.value,
        ...filters.value,
      }
      if (sortBy.value) {
        params.sort_by = sortBy.value
        params.sort_dir = sortDir.value
      }
      if (searchQuery.value) {
        params.search = searchQuery.value
      }
      const response = await fetchFn(params)
      rows.value = response.data
      total.value = response.meta?.total ?? response.total ?? 0
    } finally {
      loading.value = false
    }
  }

  function onSearch(query: string) {
    searchQuery.value = query
    currentPage.value = 1
  }

  function onFilter(key: string, value: unknown) {
    filters.value = { ...filters.value, [key]: value }
    currentPage.value = 1
  }

  function onSort(column: string) {
    if (sortBy.value === column) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = column
      sortDir.value = 'asc'
    }
  }

  function onPageChange(page: number) {
    currentPage.value = page
  }

  function onPerPageChange(size: number) {
    perPage.value = size
    currentPage.value = 1
  }

  async function refresh() {
    await fetch()
  }

  // Auto-fetch on param changes
  watch([currentPage, perPage, sortBy, sortDir, searchQuery, filters], () => fetch(), { deep: true })

  // Optionally fetch immediately
  if (options.immediate !== false) {
    fetch()
  }

  return {
    rows,
    loading,
    total,
    currentPage,
    perPage,
    sortBy,
    sortDir,
    searchQuery,
    filters,
    fetch,
    onSearch,
    onFilter,
    onSort,
    onPageChange,
    onPerPageChange,
    refresh,
  }
}
