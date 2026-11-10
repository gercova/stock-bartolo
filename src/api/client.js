import axios from 'axios'
import { useErrorStore } from '@/stores/error'

const apiClient = axios.create({
  baseURL: '/php_action',
  withCredentials: true,
  timeout: 15000,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

let routerRef = null
let onUnauthorizedRef = null

export function setupApiInterceptors(router, onUnauthorized) {
  routerRef = router
  onUnauthorizedRef = onUnauthorized
}

export function extractErrorMessage(err, fallback = 'Ocurrió un error al procesar la solicitud.') {
  if (err?.response?.data) {
    const d = err.response.data
    if (typeof d === 'string' && d.trim()) return d
    if (d.messages && typeof d.messages === 'string') return d.messages
    if (d.message && typeof d.message === 'string') return d.message
  }
  if (err?.message) return err.message
  return fallback
}

const REDIRECT_STATUS_CODES = new Set([403, 500, 501, 502, 503, 504])

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    const config = error?.config || {}
    const url = config.url || ''

    if (error.code === 'ECONNABORTED') {
      const errorStore = useErrorStore()
      errorStore.setError({
        code: 408,
        message: 'La petición excedió el tiempo máximo de espera.',
        path: url,
      })
      if (routerRef && !config.skipErrorRedirect) {
        routerRef.push({ name: 'error-page', params: { code: '408' } })
      }
      return Promise.reject(error)
    }

    if (!error.response) {
      // Network failure / backend down -> 503 Service Unavailable
      const errorStore = useErrorStore()
      errorStore.setError({
        code: 503,
        message: 'No se pudo conectar con el servidor de aplicaciones.',
        path: url,
      })
      if (routerRef && !config.skipErrorRedirect) {
        routerRef.push({ name: 'error-page', params: { code: '503' } })
      }
      return Promise.reject(error)
    }

    if (status === 401 && !url.includes('login.php') && !url.includes('checkAuth.php')) {
      if (onUnauthorizedRef) {
        onUnauthorizedRef()
      }
      if (routerRef) {
        const currentPath = routerRef.currentRoute.value.fullPath
        routerRef.push({
          name: 'login',
          query: currentPath && currentPath !== '/login' ? { redirect: currentPath } : undefined,
        })
      }
      return Promise.reject(error)
    }

    if (REDIRECT_STATUS_CODES.has(status) && !config.skipErrorRedirect) {
      const errorStore = useErrorStore()
      errorStore.setError({
        code: status,
        message: extractErrorMessage(error),
        path: url,
      })
      if (routerRef) {
        routerRef.push({ name: 'error-page', params: { code: String(status) } })
      }
    }

    return Promise.reject(error)
  }
)

export default apiClient
