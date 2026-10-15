import { ref } from 'vue'

function toDateInputValue(date: Date): string {
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 10)
}

export function useDefaultDateRange(days = 30) {
  const today = new Date()
  const from = new Date()
  from.setDate(today.getDate() - days)

  const dateFrom = ref(toDateInputValue(from))
  const dateTo = ref(toDateInputValue(today))

  return { dateFrom, dateTo }
}
