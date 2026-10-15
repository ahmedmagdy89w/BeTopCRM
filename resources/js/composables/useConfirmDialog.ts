import { ref } from 'vue'

export function useConfirmDialog() {
  const show = ref(false)
  const message = ref('')
  let resolveFn: ((value: boolean) => void) | null = null

  function confirm(msg: string): Promise<boolean> {
    message.value = msg
    show.value = true
    return new Promise<boolean>((resolve) => {
      resolveFn = resolve
    })
  }

  function accept() {
    show.value = false
    resolveFn?.(true)
    resolveFn = null
  }

  function cancel() {
    show.value = false
    resolveFn?.(false)
    resolveFn = null
  }

  return {
    show,
    message,
    confirm,
    accept,
    cancel,
  }
}
