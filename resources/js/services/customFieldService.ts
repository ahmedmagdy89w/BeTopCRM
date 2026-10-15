import api from './api'
import type {
  CustomFieldDefinition,
  CustomFieldEntity,
  CustomFieldGroup,
  CustomFieldGroupDefinitions,
} from '@/types'

export interface CustomFieldDefinitionPayload {
  name: string
  label: string
  type: CustomFieldDefinition['type']
  options?: string[] | null
  is_required?: boolean
  is_filterable?: boolean
  sort_order?: number
}

export interface CustomFieldGroupPayload {
  entity_type: string
  scope: 'entity' | 'category'
  category_id?: number
  name: string
  description?: string | null
}

export const customFieldService = {
  entities(): Promise<CustomFieldEntity[]> {
    return api.get('/custom-fields/entities').then(response => response.data.data)
  },

  list(entityType: string): Promise<CustomFieldGroupDefinitions> {
    return api.get(`/custom-fields/${encodeURIComponent(entityType)}`).then(response => response.data.data)
  },

  listCategory(entityType: string, categoryId: number): Promise<CustomFieldGroupDefinitions> {
    return api.get(`/custom-fields/${encodeURIComponent(entityType)}/categories/${categoryId}`)
      .then(response => response.data.data)
  },

  create(entityType: string, data: CustomFieldDefinitionPayload): Promise<CustomFieldDefinition> {
    return api.post(`/custom-fields/${encodeURIComponent(entityType)}`, data).then(response => response.data.data)
  },

  createCategory(entityType: string, categoryId: number, data: CustomFieldDefinitionPayload): Promise<CustomFieldDefinition> {
    return api.post(`/custom-fields/${encodeURIComponent(entityType)}/categories/${categoryId}/definitions`, data)
      .then(response => response.data.data)
  },

  update(id: number, data: Partial<CustomFieldDefinitionPayload>): Promise<CustomFieldDefinition> {
    return api.put(`/custom-fields/definitions/${id}`, data).then(response => response.data.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/custom-fields/definitions/${id}`)
  },

  createGroup(data: CustomFieldGroupPayload): Promise<CustomFieldGroup> {
    return api.post('/custom-fields/groups', data).then(response => response.data.data)
  },

  updateGroup(id: number, data: Pick<CustomFieldGroup, 'name' | 'description'>): Promise<CustomFieldGroup> {
    return api.put(`/custom-fields/groups/${id}`, data).then(response => response.data.data)
  },

  deleteGroup(id: number): Promise<void> {
    return api.delete(`/custom-fields/groups/${id}`)
  },
}
