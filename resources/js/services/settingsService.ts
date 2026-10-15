import api from './api'

export interface UserSettings {
  id: number
  name: string
  email: string
  phone?: string
  timezone: string
  locale: string
  date_format: string
  notification_preferences: Record<string, boolean>
  avatar_url?: string
  role: string
  created_at: string
}

export const settingsService = {
  get(): Promise<UserSettings> {
    return api.get('/settings').then(r => r.data.data)
  },
  update(data: Partial<UserSettings>): Promise<UserSettings> {
    return api.put('/settings', data).then(r => r.data.data)
  },
  uploadAvatar(file: File): Promise<{ avatar_url: string }> {
    const formData = new FormData()
    formData.append('avatar', file)
    return api.post('/settings/avatar', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data.data)
  },
  deleteAvatar(): Promise<void> {
    return api.delete('/settings/avatar')
  },
  changePassword(data: { current_password: string; password: string; password_confirmation: string }): Promise<void> {
    return api.put('/settings/password', data).then(r => r.data)
  },
}
