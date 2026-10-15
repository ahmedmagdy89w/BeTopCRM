<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { usePermission } from '@/composables/usePermission'
import { searchService } from '@/services/searchService'
import type { SearchResultItem, SearchResults } from '@/services/searchService'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermission()

const query = ref('')
const results = ref<SearchResults | null>(null)
const loading = ref(false)
const showDropdown = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

const TYPE_ICONS: Record<string, string> = {
  lead: 'bi-funnel',
  customer: 'bi-people',
  opportunity: 'bi-kanban',
}

watch(query, (val) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (!val || val.length < 2) {
    results.value = null
    showDropdown.value = false
    return
  }
  debounceTimer = setTimeout(async () => {
    loading.value = true
    try {
      results.value = await searchService.search(val)
      showDropdown.value = true
    } finally {
      loading.value = false
    }
  }, 300)
})

function canOpenResult(item: SearchResultItem): boolean {
  if (item.type === 'lead' || item.type === 'customer') return can('view-customers')
  if (item.type === 'opportunity') return can('view-opportunities')
  return false
}

function hasVisibleItems(group: { items: SearchResultItem[] }): boolean {
  return group.items.some(canOpenResult)
}

function navigateTo(item: SearchResultItem) {
  showDropdown.value = false
  query.value = ''
  results.value = null

  if (!canOpenResult(item)) return

  const routeMap: Record<string, string> = {
    lead: 'customers.show',
    customer: 'customers.show',
    opportunity: 'opportunities.index',
  }

  const routeName = routeMap[item.type]
  if (routeName) {
    router.push({ name: routeName, params: { id: item.id } })
  }
}

function onKeydown(e: KeyboardEvent) {
  // Ctrl+K or Cmd+K to focus search
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault()
    inputRef.value?.focus()
  }
  // Escape to close
  if (e.key === 'Escape') {
    showDropdown.value = false
    inputRef.value?.blur()
  }
}

function onClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.global-search')) {
    showDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  document.addEventListener('click', onClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.removeEventListener('click', onClickOutside)
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<template>
  <div class="global-search position-relative" role="search" :aria-label="t('search.placeholder')">
    <div class="input-group input-group-sm">
      <span class="input-group-text bg-transparent border-end-0">
        <i class="bi bi-search"></i>
      </span>
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        class="form-control border-start-0"
        :aria-label="t('search.placeholder')"
        :placeholder="`${t('search.placeholder')} (Ctrl+K)`"
        @focus="query.length >= 2 && results ? showDropdown = true : null"
      >
      <span v-if="loading" class="input-group-text bg-transparent border-start-0">
        <span class="spinner-border spinner-border-sm"></span>
      </span>
    </div>

    <!-- Results Dropdown -->
    <div
      v-if="showDropdown && results"
      class="position-absolute top-100 start-0 w-100 bg-white border rounded-bottom shadow-lg mt-1"
      style="z-index: 1050; max-height: 400px; overflow-y: auto; min-width: 360px;"
    >
      <div v-if="results.total_count === 0" class="text-center text-muted p-3">
        {{ t('search.noResults') }}
      </div>
      <template v-else>
        <div v-for="(group, type) in results.results" :key="type">
          <template v-if="group.count > 0 && hasVisibleItems(group)">
            <div class="px-3 py-1 bg-light text-muted small fw-bold text-uppercase border-bottom">
              {{ type }} ({{ group.count }})
            </div>
            <button
              v-for="item in group.items"
              :key="item.id"
              v-show="canOpenResult(item)"
              class="btn btn-link text-start d-block w-100 px-3 py-2 text-decoration-none text-body border-bottom"
              @click="navigateTo(item)"
            >
              <div class="d-flex align-items-center gap-2">
                <i class="bi" :class="TYPE_ICONS[item.type] ?? 'bi-circle'"></i>
                <div class="flex-grow-1 min-w-0">
                  <div class="fw-medium text-truncate">{{ item.title }}</div>
                  <small class="text-muted text-truncate d-block">{{ item.subtitle }}</small>
                </div>
                <span v-if="item.status" class="badge text-bg-light">{{ item.status }}</span>
              </div>
            </button>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>
