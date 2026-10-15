import { ref } from 'vue'
import { defineStore } from 'pinia'
import { tagService } from '@/services/tagService'
import type { Tag } from '@/types'

export const useTagStore = defineStore('tags', () => {
  const tags = ref<Tag[]>([])
  const loading = ref(false)
  const loaded = ref(false)

  async function fetchTags() {
    if (loaded.value) return
    loading.value = true
    try {
      tags.value = await tagService.list()
      loaded.value = true
    } finally {
      loading.value = false
    }
  }

  async function refresh() {
    loaded.value = false
    await fetchTags()
  }

  function addTag(tag: Tag) {
    tags.value.push(tag)
  }

  function updateTag(updated: Tag) {
    const idx = tags.value.findIndex(t => t.id === updated.id)
    if (idx !== -1) tags.value[idx] = updated
  }

  function removeTag(id: number) {
    tags.value = tags.value.filter(t => t.id !== id)
  }

  return { tags, loading, loaded, fetchTags, refresh, addTag, updateTag, removeTag }
})
