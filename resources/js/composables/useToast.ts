import { useToast as useVueToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'

export function useToast() {
  const toast = useVueToast()
  const { t, te } = useI18n()

  function resolve(msg: string): string {
    return te(msg) ? t(msg) : msg
  }

  return {
    success: (msg: string) => toast.success(resolve(msg)),
    error: (msg: string) => toast.error(resolve(msg)),
    warning: (msg: string) => toast.warning(resolve(msg)),
    info: (msg: string) => toast.info(resolve(msg)),
  }
}
