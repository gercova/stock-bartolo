import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getHttpErrorInfo } from '@/constants/httpErrors'

export const useErrorStore = defineStore('error', () => {
  const lastError = ref(null)

  function setError({ code = 500, message = '', path = '', timestamp = null }) {
    const info = getHttpErrorInfo(code)
    lastError.value = {
      ...info,
      customMessage: message || info.description,
      path: path || (typeof window !== 'undefined' ? window.location.pathname : ''),
      timestamp: timestamp || new Date().toLocaleString('es-ES'),
    }
    return lastError.value
  }

  function clearError() {
    lastError.value = null
  }

  return {
    lastError,
    setError,
    clearError,
  }
})
