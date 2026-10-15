import { ref } from 'vue'
import { defineStore } from 'pinia'
import { customFieldService } from '@/services/customFieldService'
import type {
  CustomFieldDefinition,
  CustomFieldEntity,
  CustomFieldGroup,
  CustomFieldGroupDefinitions,
} from '@/types'

export const useCustomFieldsStore = defineStore('customFields', () => {
  const definitions = ref<Record<string, CustomFieldDefinition[]>>({})
  const groups = ref<Record<string, CustomFieldGroup | null>>({})
  const loaded = ref<Record<string, boolean>>({})
  const loading = ref<Record<string, boolean>>({})
  const entities = ref<CustomFieldEntity[]>([])
  const entitiesLoaded = ref(false)

  async function load(key: string, fetcher: () => Promise<CustomFieldGroupDefinitions>, force: boolean): Promise<void> {
    if (!force && loaded.value[key]) return
    if (loading.value[key]) return

    loading.value[key] = true

    try {
      const result = await fetcher()
      groups.value[key] = result.group
      definitions.value[key] = result.definitions
      loaded.value[key] = true
    } finally {
      loading.value[key] = false
    }
  }

  async function fetchForEntity(entityType: string, force = false): Promise<void> {
    const key = `${entityType}:entity`

    await load(key, () => customFieldService.list(entityType), force)
  }

  async function fetchForCategory(entityType: string, categoryId: number, force = false): Promise<void> {
    const key = `${entityType}:category:${categoryId}`

    await load(key, () => customFieldService.listCategory(entityType, categoryId), force)
  }

  async function fetchEntities(force = false): Promise<CustomFieldEntity[]> {
    if (!force && entitiesLoaded.value) return entities.value

    entities.value = await customFieldService.entities()
    entitiesLoaded.value = true

    return entities.value
  }

  function getForEntity(entityType: string): CustomFieldDefinition[] {
    return definitions.value[`${entityType}:entity`] ?? []
  }

  function getForCategory(entityType: string, categoryId: number): CustomFieldDefinition[] {
    return definitions.value[`${entityType}:category:${categoryId}`] ?? []
  }

  function getGroup(entityType: string, scope: 'entity' | 'category' = 'entity', categoryId?: number): CustomFieldGroup | null {
    const key = scope === 'category' ? `${entityType}:category:${categoryId ?? 'none'}` : `${entityType}:entity`

    return groups.value[key] ?? null
  }

  function invalidateEntity(entityType: string): void {
    for (const key of Object.keys(loaded.value)) {
      if (key === `${entityType}:entity` || key.startsWith(`${entityType}:category:`)) {
        delete loaded.value[key]
        delete groups.value[key]
        delete definitions.value[key]
      }
    }
  }

  return {
    definitions,
    groups,
    loaded,
    loading,
    entities,
    entitiesLoaded,
    fetchForEntity,
    fetchForCategory,
    fetchEntities,
    getForEntity,
    getForCategory,
    getGroup,
    invalidateEntity,
  }
})
