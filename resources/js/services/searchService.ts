import api from './api'

export interface SearchResultItem {
  id: number
  type: string
  title: string
  subtitle: string
  status?: string
  url: string
  matched_field: string
}

export interface SearchResults {
  query: string
  results: Record<string, { count: number; items: SearchResultItem[] }>
  total_count: number
}

export const searchService = {
  search(q: string, types?: string, limit?: number): Promise<SearchResults> {
    const params: Record<string, unknown> = { q }
    if (types) params.types = types
    if (limit) params.limit = limit
    return api.get('/search', { params }).then(r => r.data)
  },
}
