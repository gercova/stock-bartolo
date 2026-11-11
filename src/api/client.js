import axios from 'axios'
import { useErrorStore } from '@/stores/error'
import { getCookie } from '@/utils/security'

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
let csrfTokenMem = ''
let csrfFetchPromise = null

export function setCsrfToken(token) {
  if (typeof token === 'string' && token.trim() !== '') {
    csrfTokenMem = token.trim()
  }
}

export function clearCsrfToken() {
  csrfTokenMem = ''
}

export function getCsrfToken() {
  return csrfTokenMem || getCookie('XSRF-TOKEN') || ''
}

export async function ensureCsrfToken() {
  const existing = getCsrfToken()
  if (existing) {
    csrfTokenMem = existing
    return existing
  }
  if (!csrfFetchPromise) {
    csrfFetchPromise = axios
      .get('/php_action/csrfToken.php', {
        withCredentials: true,
        timeout: 10000,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
      .then((res) => {
        const token = res?.data?.csrfToken || getCookie('XSRF-TOKEN') || ''
        if (token) setCsrfToken(token)
        return token
      })
      .finally(() => {
        csrfFetchPromise = null
      })
  }
  return csrfFetchPromise
}

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

const MUTATING_METHODS = new Set(['post', 'put', 'patch', 'delete'])
const REDIRECT_STATUS_CODES = new Set([403, 429, 500, 501, 502, 503, 504])

apiClient.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()
  if (MUTATING_METHODS.has(method)) {
    const token = await ensureCsrfToken()
    if (token) {
      config.headers = config.headers || {}
      config.headers['X-CSRF-Token'] = token
    }
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => {
    if (response?.data?.csrfToken) {
      setCsrfToken(response.data.csrfToken)
    }
    return response
  },
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

    if (status === 403 && error?.response?.data?.details?.reason === 'csrf_invalid') {
      clearCsrfToken()
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
