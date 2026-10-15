import { computed, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { useCustomFieldsStore } from '@/stores/customFields'
import { customFieldDefinitionsToSchema } from '@/utils/customFieldTypes'

export type CustomFieldScope = 'entity' | 'category'

export function useCustomFields(
  entityType: MaybeRefOrGetter<string>,
  scope: MaybeRefOrGetter<CustomFieldScope> = 'entity',
  categoryId: MaybeRefOrGetter<number | null | undefined> = null,
) {
  const store = useCustomFieldsStore()
  const currentEntityType = computed(() => toValue(entityType))
  const currentScope = computed(() => toValue(scope))
  const currentCategoryId = computed(() => toValue(categoryId) ?? null)
  const cacheKey = computed(() => currentScope.value === 'category'
    ? `${currentEntityType.value}:category:${currentCategoryId.value ?? 'none'}`
    : `${currentEntityType.value}:entity`)

  const group = computed(() => store.groups[cacheKey.value] ?? null)
  const definitions = computed(() => store.definitions[cacheKey.value] ?? [])
  const schema = computed(() => customFieldDefinitionsToSchema(definitions.value))
  const loading = computed(() => store.loading[cacheKey.value] ?? false)

  async function load(force = false): Promise<void> {
    if (currentScope.value === 'category') {
      if (currentCategoryId.value === null) return

      await store.fetchForCategory(currentEntityType.value, currentCategoryId.value, force)

      return
    }

    await store.fetchForEntity(currentEntityType.value, force)
  }

  watch([currentEntityType, currentScope, currentCategoryId], () => {
    void load()
  }, { immediate: true })

  return { group, definitions, schema, loading, reload: () => load(true) }
}
