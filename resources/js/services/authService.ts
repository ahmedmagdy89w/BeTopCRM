import axios from 'axios'
import api from '@/services/api'
import type { User } from '@/types'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface AuthResponse {
  message: string
  token: string
  user: User
}

export const authService = {
  getCsrfCookie(): Promise<void> {
    return axios.get('/sanctum/csrf-cookie').then(() => undefined)
  },

  login(payload: LoginPayload): Promise<AuthResponse> {
    return api.post('/auth/login', payload).then(r => r.data)
  },

  requestPasswordResetOtp(email: string): Promise<{ message: string }> {
    return api.post('/auth/password/otp/request', { email }).then(r => r.data)
  },

  resetPasswordWithOtp(payload: {
    email: string
    otp: string
    password: string
    password_confirmation: string
  }): Promise<{ message: string }> {
    return api.post('/auth/password/otp/reset', payload).then(r => r.data)
  },

  logout(): Promise<void> {
    return api.post('/auth/logout')
  },

  me(): Promise<User> {
    return api.get('/auth/me').then(r => r.data.data ?? r.data)
  },
}
