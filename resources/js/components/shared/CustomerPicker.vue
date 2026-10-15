<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermission } from '@/composables/usePermission'
import { customerService } from '@/services/customerService'
import type { Customer } from '@/types'

const props = withDefaults(defineProps<{
  modelValue: number | null | undefined
  placeholder?: string
  disabled?: boolean
  showQuickAdd?: boolean
  teleportDropdown?: boolean
}>(), {
  disabled: false,
  showQuickAdd: true,
  teleportDropdown: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
  'quick-add': []
}>()

const { t } = useI18n()
const { can } = usePermission()

const query = ref('')
const results = ref<Customer[]>([])
const selected = ref<Customer | null>(null)
const open = ref(false)
const loading = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

let debounce: ReturnType<typeof setTimeout> | null = null
let reqId = 0

async function search(q: string) {
  // Customer list needs view-customers; skip silently instead of
  // triggering a 403 (which redirects to the Forbidden page).
  if (!can('view-customers')) {
    results.value = []
    open.value = true
    return
  }
  const myReq = ++reqId
  loading.value = true
  try {
    const res = await customerService.list({ search: q, per_page: 10 })
    if (myReq !== reqId) return
    const items = res.data ?? []
    results.value = props.modelValue != null ? items.filter(c => c.id !== props.modelValue) : items
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
  if (!can('view-customers')) {
    selected.value = null
    return
  }
  try {
    selected.value = await customerService.get(id)
  } catch {
    selected.value = null
  }
}

function pick(c: Customer) {
  emit('update:modelValue', c.id)
  selected.value = c
  query.value = ''
  results.value = []
  open.value = false
}

function clear() {
  emit('update:modelValue', null)
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
  const width = Math.max(inputBounds.width, 320)

  if (document.documentElement.dir === 'rtl') {
    dropdownStyle.value = {
      top: `${shouldOpenAbove ? inputBounds.top - dropdownHeight - 4 : inputBounds.bottom + 4}px`,
      right: `${window.innerWidth - inputBounds.right}px`,
      width: `${width}px`,
      maxHeight: `${dropdownHeight}px`,
    }

    return
  }

  dropdownStyle.value = {
    top: `${shouldOpenAbove ? inputBounds.top - dropdownHeight - 4 : inputBounds.bottom + 4}px`,
    left: `${inputBounds.left}px`,
    width: `${width}px`,
    maxHeight: `${dropdownHeight}px`,
  }
}

function displayName(c: Customer): string {
  return c.display_name ?? c.company_name ?? (`${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || `#${c.id}`)
}

function typeLabel(c: Customer): string {
  return t(`customers.${c.type}`)
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
  <div ref="rootRef" class="customer-picker position-relative">
    <!-- Selected chip -->
    <div v-if="selected" class="d-flex align-items-center gap-2 border rounded px-2 py-1">
      <span class="customer-picker__text fw-semibold flex-grow-1 text-truncate">{{ displayName(selected) }}</span>
      <span v-if="selected.type" class="badge text-bg-light flex-shrink-0">{{ typeLabel(selected) }}</span>
      <span v-if="selected.is_lead" class="badge text-bg-info flex-shrink-0">{{ t('customers.isLead') }}</span>
      <button type="button" class="btn btn-sm btn-link text-danger p-0" :disabled="disabled" @click="clear" aria-label="Clear">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <!-- Search input -->
    <div v-else class="d-flex gap-1">
      <div class="position-relative flex-grow-1">
        <input
          ref="inputRef"
          v-model="query"
          class="form-control"
          :placeholder="placeholder ?? t('common.search')"
          :disabled="disabled"
          autocomplete="off"
          @input="onInput"
          @focus="query.trim() ? search(query.trim()) : search('')"
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
            style="z-index: 2000; max-height: 260px; overflow-y: auto; overflow-x: hidden; padding: 0; margin: 0;"
          >
            <li
              v-for="c in results"
              :key="c.id"
              class="list-group-item list-group-item-action"
              role="button"
              @click="pick(c)"
            >
              <div class="d-flex align-items-center gap-2">
                <div class="customer-picker__text flex-grow-1">
                  <div class="fw-semibold text-truncate">{{ displayName(c) }}</div>
                  <small class="text-muted text-truncate d-block">{{ c.email ?? c.contact_email ?? c.phone ?? '' }}</small>
                </div>
                <span v-if="c.type" class="badge text-bg-light flex-shrink-0">{{ typeLabel(c) }}</span>
                <span v-if="c.is_lead" class="badge text-bg-info flex-shrink-0">{{ t('customers.isLead') }}</span>
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
      <button
        v-if="showQuickAdd && can('create-customers')"
        type="button"
        class="btn btn-outline-primary flex-shrink-0"
        :disabled="disabled"
        :title="t('common.quickAdd')"
        @click="emit('quick-add')"
      >
        <i class="bi bi-plus-lg"></i>
      </button>
    </div>
  </div>
</template>

<style scoped>
.customer-picker__text {
  min-width: 0;
  text-align: start;
}
</style>
