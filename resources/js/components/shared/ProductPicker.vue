<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { productService } from '@/services/productService'
import type { Product } from '@/types'

const props = withDefaults(defineProps<{
  modelValue: number | null | undefined
  placeholder?: string
  disabled?: boolean
  activeOnly?: boolean
  showPrice?: boolean
  excludeIds?: number[]
  teleportDropdown?: boolean
}>(), {
  disabled: false,
  activeOnly: true,
  showPrice: true,
  teleportDropdown: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  select: [product: Product | null]
}>()

const { t } = useI18n()

const query = ref('')
const results = ref<Product[]>([])
const selected = ref<Product | null>(null)
const open = ref(false)
const loading = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

let debounce: ReturnType<typeof setTimeout> | null = null
let reqId = 0

async function search(q: string) {
  const myReq = ++reqId
  loading.value = true
  try {
    const res = await productService.list({
      search: q || undefined,
      per_page: 10,
      ...(props.activeOnly ? { is_active: true } : {}),
    })
    if (myReq !== reqId) return
    const items = res.data ?? []
    results.value = items.filter(p => p.id !== props.modelValue && !props.excludeIds?.includes(p.id))
    open.value = true
  } catch {
    if (myReq !== reqId) return
    results.value = []
    open.value = true
  } finally {
    if (myReq === reqId) loading.value = false
  }

  if (open.value) {
    await nextTick()
    updateDropdownPosition()
  }
}

function onInput() {
  if (debounce) clearTimeout(debounce)
  debounce = setTimeout(() => search(query.value.trim()), 300)
}

async function loadSelected(id: number) {
  try {
    selected.value = await productService.get(id)
    emit('select', selected.value)
  } catch {
    selected.value = null
    emit('select', null)
  }
}

function pick(p: Product) {
  emit('update:modelValue', p.id)
  emit('select', p)
  selected.value = p
  query.value = ''
  results.value = []
  open.value = false
}

function clear() {
  emit('update:modelValue', null)
  emit('select', null)
  selected.value = null
  query.value = ''
  search('')
}

function onClickOutside(e: MouseEvent) {
  const target = e.target as Node
  if (rootRef.value?.contains(target) || dropdownRef.value?.contains(target)) return

  open.value = false
}

function updateDropdownPosition() {
  if (!props.teleportDropdown || !inputRef.value) return

  const inputBounds = inputRef.value.getBoundingClientRect()
  const dropdownHeight = Math.min(260, window.innerHeight - 24)
  const shouldOpenAbove = inputBounds.bottom + dropdownHeight + 4 > window.innerHeight && inputBounds.top > dropdownHeight

  dropdownStyle.value = {
    top: `${shouldOpenAbove ? inputBounds.top - dropdownHeight - 4 : inputBounds.bottom + 4}px`,
    left: `${inputBounds.left}px`,
    width: `${inputBounds.width}px`,
    maxHeight: `${dropdownHeight}px`,
  }
}

watch(() => props.modelValue, (id) => {
  if (id == null) selected.value = null
  else if (selected.value?.id !== id) loadSelected(id)
})

onMounted(() => {
  document.addEventListener('click', onClickOutside)
  if (props.teleportDropdown) {
    window.addEventListener('scroll', updateDropdownPosition, true)
    window.addEventListener('resize', updateDropdownPosition)
  }
  if (props.modelValue != null) loadSelected(props.modelValue)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
  window.removeEventListener('scroll', updateDropdownPosition, true)
  window.removeEventListener('resize', updateDropdownPosition)
  if (debounce) clearTimeout(debounce)
})
</script>

<template>
  <div ref="rootRef" class="product-picker position-relative">
    <!-- Selected chip -->
    <div v-if="selected" class="d-flex align-items-center gap-2 border rounded px-2 py-1">
      <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold text-truncate small">{{ selected.name }}</div>
        <small v-if="showPrice" class="text-muted">{{ selected.unit_price }}</small>
      </div>
      <button type="button" class="btn btn-sm btn-link text-danger p-0" :disabled="disabled" @click="clear" aria-label="Clear">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <!-- Search input -->
    <div v-else class="position-relative">
      <input
        ref="inputRef"
        v-model="query"
        class="form-control form-control-sm"
        :placeholder="placeholder ?? t('common.search')"
        :disabled="disabled"
        autocomplete="off"
        @input="onInput"
        @focus="search(query.trim())"
      />
      <span v-if="loading" class="position-absolute top-50 end-0 translate-middle-y me-2">
        <span class="spinner-border spinner-border-sm"></span>
      </span>
      <Teleport to="body" :disabled="!teleportDropdown">
        <ul
          v-if="open && results.length"
          ref="dropdownRef"
          class="list-group shadow"
          :class="teleportDropdown ? 'position-fixed' : 'position-absolute w-100 mt-1'"
          :style="teleportDropdown ? dropdownStyle : undefined"
          style="z-index: 2000; max-height: 260px; overflow-y: auto; padding: 0; margin: 0;"
        >
          <li
            v-for="p in results"
            :key="p.id"
            class="list-group-item list-group-item-action"
            role="button"
            @click="pick(p)"
          >
            <div class="d-flex align-items-center gap-2">
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate small">{{ p.name }}</div>
                <small class="text-muted text-truncate d-block">{{ p.sku ?? '' }}{{ p.category ? ` · ${p.category.name}` : '' }}</small>
              </div>
              <small v-if="showPrice" class="fw-semibold text-nowrap">{{ p.unit_price }}</small>
            </div>
          </li>
        </ul>
        <div
          v-else-if="open && !loading"
          ref="dropdownRef"
          class="shadow mt-1 border rounded bg-body px-3 py-2 text-muted small"
          :class="teleportDropdown ? 'position-fixed' : 'position-absolute w-100'"
          :style="teleportDropdown ? dropdownStyle : undefined"
          style="z-index: 2000;"
        >
          {{ t('common.noResults') }}
        </div>
      </Teleport>
    </div>
  </div>
</template>
