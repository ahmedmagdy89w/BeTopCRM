import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { i18n } from '@/i18n'
import router from '@/router'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import App from './App.vue'
import '@/assets/styles/main.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'

const locale = localStorage.getItem('locale') || 'en'
document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
document.documentElement.lang = locale

const app = createApp(App)
app.use(createPinia())
app.use(i18n)
app.use(router)
app.use(Toast, { position: 'top-right', timeout: 4000 })
app.mount('#app')
