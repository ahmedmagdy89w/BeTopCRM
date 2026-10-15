<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { customFieldService } from '@/services/customFieldService'
import { useCustomFieldsStore } from '@/stores/customFields'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermission'
import type { CustomFieldDefinitionInput, CustomFieldEntity, CustomFieldGroup } from '@/types'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseCard from '@/components/base/BaseCard.vue'
import BaseSpinner from '@/components/base/BaseSpinner.vue'
import CustomFieldDefinitionEditor from '@/components/shared/CustomFieldDefinitionEditor.vue'

const { t } = useI18n()
const store = useCustomFieldsStore()
const toast = useToast()
const { can } = usePermission()

const entities = ref<CustomFieldEntity[]>([])
const selectedEntityType = ref('')
const group = ref<CustomFieldGroup | null>(null)
const definitions = ref<CustomFieldDefinitionInput[]>([])
const originalDefinitions = ref<CustomFieldDefinitionInput[]>([])
const groupName = ref('')
const groupDescription = ref('')
const loading = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const selectedEntity = computed(() => entities.value.find(entity => entity.entity_type === selectedEntityType.value) ?? null)
const canEdit = computed(() => can('create-custom-fields') && can('update-custom-fields') && can('delete-custom-fields'))
const canSaveGroup = computed(() => group.value ? can('update-custom-fields') : can('create-custom-fields'))

async function loadEntity(): Promise<void> {
  if (!selectedEntityType.value) return

  loading.value = true

  try {
    await store.fetchForEntity(selectedEntityType.value, true)
    const key = `${selectedEntityType.value}:entity`
    group.value = store.groups[key] ?? null
    const loadedDefinitions = store.definitions[key] ?? []

    definitions.value = loadedDefinitions.map(definition => ({
      id: definition.id,
      name: definition.name,
      label: definition.label,
      type: definition.type,
      options: definition.options ?? null,
      is_required: definition.is_required,
      is_filterable: definition.is_filterable,
      sort_order: definition.sort_order,
    }))
    originalDefinitions.value = definitions.value.map(definition => ({ ...definition }))
    groupName.value = group.value?.name ?? t('customFields.defaultGroupName', {
      entity: selectedEntity.value ? t(selectedEntity.value.label) : selectedEntityType.value,
    })
    groupDescription.value = group.value?.description ?? ''
    errors.value = {}
  } finally {
    loading.value = false
  }
}

async function loadEntities(): Promise<void> {
  entities.value = await store.fetchEntities()

  if (!selectedEntityType.value && entities.value.length) {
    selectedEntityType.value = entities.value[0]!.entity_type
  }
}

async function save(): Promise<void> {
  if (!selectedEntityType.value) return

  saving.value = true
  errors.value = {}

  try {
    let targetGroup = group.value

    if (!targetGroup) {
      targetGroup = await customFieldService.createGroup({
        entity_type: selectedEntityType.value,
        scope: 'entity',
        name: groupName.value,
        description: groupDescription.value || null,
      })
    } else {
      targetGroup = await customFieldService.updateGroup(targetGroup.id, {
        name: groupName.value,
        description: groupDescription.value || null,
      })
    }

    const nextIds = new Set(definitions.value.flatMap(definition => definition.id ? [definition.id] : []))

    for (const definition of originalDefinitions.value) {
      if (definition.id && !nextIds.has(definition.id)) {
        await customFieldService.delete(definition.id)
      }
    }

    for (const [index, definition] of definitions.value.entries()) {
      const payload = {
        name: definition.name,
        label: definition.label,
        type: definition.type,
        options: definition.options ?? null,
        is_required: definition.is_required,
        is_filterable: definition.is_filterable,
        sort_order: index,
      }

      if (definition.id) {
        await customFieldService.update(definition.id, payload)
      } else {
        await customFieldService.create(selectedEntityType.value, payload)
      }
    }

    group.value = targetGroup
    store.invalidateEntity(selectedEntityType.value)
    await loadEntity()
    toast.success(t('customFields.saved'))
  } catch (error: unknown) {
    const response = error as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } }

    if (response.response?.status === 422 && response.response.data?.errors) {
      errors.value = response.response.data.errors
    } else {
      toast.error(t('common.somethingWentWrong'))
    }
  } finally {
    saving.value = false
  }
}

watch(selectedEntityType, () => {
  void loadEntity()
})

onMounted(loadEntities)
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h2 class="mb-1">{{ t('customFields.title') }}</h2>
        <p class="text-muted mb-0">{{ t('customFields.subtitle') }}</p>
      </div>
      <BaseButton
        v-if="canSaveGroup"
        variant="primary"
        icon="bi-check-lg"
        :loading="saving"
        @click="save"
      >
        {{ t('common.save') }}
      </BaseButton>
    </div>

    <BaseCard :loading="loading">
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="custom-field-entity" class="form-label">{{ t('customFields.entityType') }}</label>
          <select id="custom-field-entity" v-model="selectedEntityType" class="form-select">
            <option v-for="entity in entities" :key="entity.entity_type" :value="entity.entity_type">
              {{ t(entity.label) }}
            </option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="custom-field-group-name" class="form-label">{{ t('customFields.groupName') }}</label>
          <input id="custom-field-group-name" v-model="groupName" class="form-control" :disabled="!canEdit">
        </div>
        <div class="col-md-4">
          <label for="custom-field-group-description" class="form-label">{{ t('customFields.groupDescription') }}</label>
          <input id="custom-field-group-description" v-model="groupDescription" class="form-control" :disabled="!canEdit">
        </div>
      </div>

      <CustomFieldDefinitionEditor
        v-model="definitions"
        :errors="errors"
        :disabled="!canEdit || saving"
      />

      <div v-if="selectedEntity" class="text-muted small mt-3">
        {{ t('customFields.entityType') }}: {{ t(selectedEntity.label) }}
      </div>
    </BaseCard>

    <div v-if="loading" class="text-center mt-3"><BaseSpinner size="sm" /></div>
  </div>
</template>
