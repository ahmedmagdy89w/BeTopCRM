<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTagStore } from '@/stores/tags'
import type { Tag } from '@/types'

const { t } = useI18n()
const tagStore = useTagStore()

const props = defineProps<{
  modelValue: number[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const search = ref('')

const availableTags = computed(() =>
  tagStore.tags.filter(tag =>
    !props.modelValue.includes(tag.id) &&
    (!search.value || tag.name.toLowerCase().includes(search.value.toLowerCase()))
  )
)

const selectedTags = computed(() =>
  tagStore.tags.filter(tag => props.modelValue.includes(tag.id))
)

function addTag(tag: Tag) {
  emit('update:modelValue', [...props.modelValue, tag.id])
  search.value = ''
}

function removeTag(tagId: number) {
  emit('update:modelValue', props.modelValue.filter(id => id !== tagId))
}

onMounted(() => tagStore.fetchTags())
</script>

<template>
  <div>
    <!-- Selected tags -->
    <div class="d-flex flex-wrap gap-1 mb-2" v-if="selectedTags.length">
      <span
        v-for="tag in selectedTags"
        :key="tag.id"
        class="badge badge--centered tag-badge rounded-pill d-inline-flex align-items-center gap-1"
        :style="{ backgroundColor: tag.color || '#6c757d', color: '#fff' }"
      >
        {{ tag.name }}
        <button
          type="button"
          class="btn-close btn-close-white"
          style="font-size: 0.5em;"
          @click="removeTag(tag.id)"
        ></button>
      </span>
    </div>

    <!-- Search + dropdown -->
    <div class="dropdown">
      <input
        v-model="search"
        type="text"
        class="form-control form-control-sm"
        :placeholder="t('common.search') + ' ' + t('tags.title').toLowerCase()"
        data-bs-toggle="dropdown"
      >
      <ul class="dropdown-menu w-100" :class="{ show: search.length > 0 && availableTags.length > 0 }">
        <li v-for="tag in availableTags" :key="tag.id">
          <button type="button" class="dropdown-item d-flex align-items-center gap-2" @click="addTag(tag)">
            <span
              class="d-inline-block rounded-circle"
              style="width: 12px; height: 12px;"
              :style="{ backgroundColor: tag.color || '#6c757d' }"
            ></span>
            {{ tag.name }}
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
