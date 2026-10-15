import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import type { FormErrors } from '@/types/forms'

export interface UseCrudFormOptions<T> {
  getService: (id: number) => Promise<T>
  createService: (data: Partial<T>) => Promise<T>
  updateService: (id: number, data: Partial<T>) => Promise<T>
  redirectTo: RouteLocationRaw | ((item: T) => RouteLocationRaw)
  defaultValues?: () => Partial<T>
  transform?: (item: T) => Partial<T>
  preparePayload?: (data: Partial<T>) => Partial<T>
  afterSave?: (item: T, formData: Partial<T>) => Promise<void>
}

export function useCrudForm<T>(options: UseCrudFormOptions<T>) {
  const route = useRoute()
  const router = useRouter()

  const formData = ref<Partial<T>>(options.defaultValues?.() ?? {} as Partial<T>) as { value: Partial<T> }
  const errors = ref<FormErrors>({})
  const loading = ref(false)
  const saving = ref(false)

  const isEdit = computed(() => !!route.params.id)

  async function loadItem(id?: number) {
    const itemId = id ?? Number(route.params.id)
    if (!itemId) return
    loading.value = true
    try {
      const item = await options.getService(itemId)
      formData.value = options.transform ? options.transform(item) : { ...item } as Partial<T>
    } finally {
      loading.value = false
    }
  }

  async function submit() {
    errors.value = {}
    saving.value = true
    try {
      let result: T
      const payload = options.preparePayload
        ? options.preparePayload(formData.value)
        : formData.value
      if (isEdit.value) {
        result = await options.updateService(Number(route.params.id), payload)
      } else {
        result = await options.createService(payload)
      }
      const target = typeof options.redirectTo === 'function'
        ? options.redirectTo(result)
        : options.redirectTo
      if (options.afterSave) await options.afterSave(result, formData.value)
      router.push(target)
      return result
    } catch (err: unknown) {
      const axiosErr = err as { response?: { status?: number; data?: { errors?: FormErrors } } }
      if (axiosErr.response?.status === 422) {
        errors.value = axiosErr.response.data?.errors ?? {}
        return undefined
      }
      throw err
    } finally {
      saving.value = false
    }
  }

  function setField(key: string, value: unknown) {
    formData.value = { ...formData.value, [key]: value }
  }

  function updateFormData(data: Partial<T>) {
    formData.value = data
  }

  function resetForm() {
    formData.value = options.defaultValues?.() ?? {} as Partial<T>
    errors.value = {}
  }

  return {
    formData,
    errors,
    loading,
    saving,
    isEdit,
    loadItem,
    submit,
    setField,
    updateFormData,
    resetForm,
  }
}
