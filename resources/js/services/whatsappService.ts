import api from './api'

export type WhatsAppEntityType = 'customer' | 'quotation' | 'invoice'

export interface WhatsAppPreparePayload {
  entity_type: WhatsAppEntityType
  customer_id: number
  opportunity_id?: number
  quotation_id?: number
  invoice_id?: number
  custom_message?: string
}

export interface PreparedWhatsAppMessage {
  entity_type: WhatsAppEntityType
  entity_id: number
  customer_id: number
  opportunity_id: number | null
  phone: string
  recipient_name: string
  document_number: string | null
  total: number | null
  currency: string | null
  pdf_url: string | null
  message: string
  click_to_chat_url: string
  cloud_api_available: boolean
}

export interface SendWhatsAppPayload {
  customer_id: number
  opportunity_id?: number
  phone: string
  message: string
  subject?: string
  via_cloud_api?: boolean
}

export const whatsappService = {
  prepare(payload: WhatsAppPreparePayload): Promise<PreparedWhatsAppMessage> {
    const url = payload.entity_type === 'quotation'
      ? `/customers/${payload.customer_id}/opportunities/${payload.opportunity_id}/quotations/${payload.quotation_id}/whatsapp/prepare`
      : payload.entity_type === 'invoice'
        ? `/customers/${payload.customer_id}/opportunities/${payload.opportunity_id}/invoice/${payload.invoice_id}/whatsapp/prepare`
        : `/customers/${payload.customer_id}/whatsapp/prepare`

    return api.post(url, { custom_message: payload.custom_message }).then(response => response.data.data)
  },

  send(payload: SendWhatsAppPayload): Promise<void> {
    const url = payload.opportunity_id
      ? `/customers/${payload.customer_id}/opportunities/${payload.opportunity_id}/whatsapp/send`
      : `/customers/${payload.customer_id}/whatsapp/send`
    const body = {
      phone: payload.phone,
      message: payload.message,
      subject: payload.subject,
      via_cloud_api: payload.via_cloud_api,
    }

    return api.post(url, body).then(() => undefined)
  },
}