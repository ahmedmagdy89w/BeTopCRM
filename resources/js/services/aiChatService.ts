import api from './api'
import type { AsyncJob } from '@/types'

export type AiChatScope = 'all' | 'reports' | 'pipeline' | 'customers' | 'products'

export interface AiChatAskParams {
  question: string
  scope?: AiChatScope
  user_id?: number | null
  customer_id?: number | null
  customer_type?: string
  date_from?: string
  date_to?: string
  language?: 'en' | 'ar'
}

export interface AiChatAnswer {
  answer: string
  sources: string[]
  locale: string
  generated_at: string
}

export const aiChatService = {
  askAsync(params: AiChatAskParams): Promise<AsyncJob> {
    return api.post('/ai-chat/async', params).then(r => r.data.data)
  },

  suggestedQuestions(): Promise<string[]> {
    return api.get('/ai-chat/suggested-questions').then(r => r.data.data.questions as string[])
  },
}
