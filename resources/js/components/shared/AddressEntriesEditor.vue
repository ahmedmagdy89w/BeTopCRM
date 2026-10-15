<script setup lang="ts">
import type { AddressEntry } from '@/types'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps<{ modelValue: AddressEntry[] }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: AddressEntry[]): void }>()

function add() {
  emit('update:modelValue', [...(props.modelValue ?? []), { label: '', type: 'office', line_1: '', city: '', country: '', is_primary: (props.modelValue ?? []).length === 0 }])
}

function remove(i: number) {
  const next = [...(props.modelValue ?? [])]
  next.splice(i, 1)
  emit('update:modelValue', next)
}

function set(i: number, key: keyof AddressEntry, v: string | boolean) {
  const next: AddressEntry[] = [...(props.modelValue ?? [])]
  const cur: AddressEntry = next[i] ?? {}
  next[i] = { ...cur, [key]: v } as AddressEntry
  if (key === 'is_primary' && v === true) {
    next.forEach((a, j) => { if (j !== i) a.is_primary = false })
  }
  emit('update:modelValue', next)
}
</script>

<template>
  <div>
    <div v-for="(a, i) in (modelValue ?? [])" :key="i" class="border rounded p-2 mb-2">
      <div class="row g-2">
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Label (HQ)" :value="a.label ?? ''" @input="set(i, 'label', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-3">
          <select class="form-select form-select-sm" :value="a.type ?? 'office'" @change="set(i, 'type', ($event.target as HTMLSelectElement).value)">
            <option value="office">Office</option>
            <option value="billing">Billing</option>
            <option value="shipping">Shipping</option>
            <option value="home">Home</option>
          </select>
        </div>
        <div class="col-md-6"><input class="form-control form-control-sm" placeholder="Street" :value="a.line_1 ?? ''" @input="set(i, 'line_1', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="City" :value="a.city ?? ''" @input="set(i, 'city', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="State" :value="a.state ?? ''" @input="set(i, 'state', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-2"><input class="form-control form-control-sm" placeholder="ZIP" :value="a.postal_code ?? ''" @input="set(i, 'postal_code', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-2"><input class="form-control form-control-sm" placeholder="Country" :value="a.country ?? ''" @input="set(i, 'country', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-1 d-flex align-items-center">
          <div class="form-check">
            <input class="form-check-input" type="radio" :checked="!!a.is_primary" @change="set(i, 'is_primary', true)" />
          </div>
        </div>
        <div class="col-md-1 text-end">
          <BaseButton variant="outline-danger" size="sm" icon="bi-trash" @click="remove(i)" />
        </div>
      </div>
    </div>
    <BaseButton variant="outline-primary" size="sm" icon="bi-plus-lg" @click="add">Add address</BaseButton>
  </div>
</template>
