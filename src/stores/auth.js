import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient, { extractErrorMessage } from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const authenticated = ref(false)
  const checked = ref(false)
  const loading = ref(false)

  async function checkAuth(force = false) {
    if (checked.value && !force) {
      return authenticated.value
    }
    try {
      const { data } = await apiClient.get('/checkAuth.php', { skipErrorRedirect: true })
      authenticated.value = Boolean(data?.authenticated)
      user.value = data?.user || null
    } catch {
      authenticated.value = false
      user.value = null
    } finally {
      checked.value = true
    }
    return authenticated.value
  }

  async function login(username, password) {
    loading.value = true
    try {
      const { data } = await apiClient.post(
        '/login.php',
        { username, password },
        { skipErrorRedirect: true }
      )
      authenticated.value = Boolean(data?.authenticated)
      user.value = data?.user || null
      checked.value = true
      return { ok: true, message: data?.messages || 'Inicio de sesión exitoso' }
    } catch (err) {
      return { ok: false, message: extractErrorMessage(err, 'Credenciales inválidas.') }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await apiClient.post('/logout.php', {}, { skipErrorRedirect: true })
    } finally {
      clearSession()
    }
  }

  function clearSession() {
    authenticated.value = false
    user.value = null
    checked.value = true
  }

  async function updateUsername(username) {
    const { data } = await apiClient.post('/changeUsername.php', {
      username,
      user_id: user.value?.user_id,
    })
    if (data?.success && user.value) {
      user.value = { ...user.value, username: data.username || username }
    }
    return data
  }

  async function changePassword(payload) {
    const { data } = await apiClient.post('/changePassword.php', {
      ...payload,
      user_id: user.value?.user_id,
    })
    return data
  }

  return {
    user,
    authenticated,
    checked,
    loading,
    checkAuth,
    login,
    logout,
    clearSession,
    updateUsername,
    changePassword,
  }
})
