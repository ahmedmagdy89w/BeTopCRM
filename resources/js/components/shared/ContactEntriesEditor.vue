<script setup lang="ts">
import type { ContactEntry } from '@/types'
import BaseButton from '@/components/base/BaseButton.vue'

const props = defineProps<{ modelValue: ContactEntry[] }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: ContactEntry[]): void }>()

function add() {
  emit('update:modelValue', [...(props.modelValue ?? []), { first_name: '', last_name: '', email: '', phone: '', title: '', is_primary: (props.modelValue ?? []).length === 0 }])
}

function remove(i: number) {
  const next = [...(props.modelValue ?? [])]
  next.splice(i, 1)
  emit('update:modelValue', next)
}

function set(i: number, key: keyof ContactEntry, v: string | boolean) {
  const next: ContactEntry[] = [...(props.modelValue ?? [])]
  const cur: ContactEntry = next[i] ?? { first_name: '', last_name: '' }
  next[i] = { ...cur, [key]: v } as ContactEntry
  if (key === 'is_primary' && v === true) {
    next.forEach((c, j) => { if (j !== i) c.is_primary = false })
  }
  emit('update:modelValue', next)
}
</script>

<template>
  <div>
    <div v-for="(c, i) in (modelValue ?? [])" :key="i" class="border rounded p-2 mb-2">
      <div class="row g-2">
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="First name *" :value="c.first_name" @input="set(i, 'first_name', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Last name *" :value="c.last_name" @input="set(i, 'last_name', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Email" :value="c.email ?? ''" @input="set(i, 'email', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Phone" :value="c.phone ?? ''" @input="set(i, 'phone', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-6"><input class="form-control form-control-sm" placeholder="Title" :value="c.title ?? ''" @input="set(i, 'title', ($event.target as HTMLInputElement).value)" /></div>
        <div class="col-md-4 d-flex align-items-center gap-2">
          <div class="form-check">
            <input class="form-check-input" type="radio" :checked="!!c.is_primary" :name="`contact-primary-${i}`" @change="set(i, 'is_primary', true)" />
            <label class="form-check-label small">Primary</label>
          </div>
        </div>
        <div class="col-md-2 text-end">
          <BaseButton variant="outline-danger" size="sm" icon="bi-trash" @click="remove(i)" />
        </div>
      </div>
    </div>
    <BaseButton variant="outline-primary" size="sm" icon="bi-plus-lg" @click="add">Add contact</BaseButton>
  </div>
</template>
