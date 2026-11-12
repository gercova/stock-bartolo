import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { setupApiInterceptors } from './api/client'
import { useAuthStore } from './stores/auth'
import './style.css'
import 'sweetalert2/dist/sweetalert2.min.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

const authStore = useAuthStore(pinia)
setupApiInterceptors(router, () => authStore.clearSession())

app.mount('#app')
