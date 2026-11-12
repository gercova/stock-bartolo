import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import apiClient, {
  clearCsrfToken,
  extractErrorMessage,
  setCsrfToken,
} from '@/api/client'
import { setActiveCurrency } from '@/utils/formatters'

export const useAuthStore = defineStore('auth', () => {
  const user              = ref(null)
  const activeBranch      = ref(null)
  const branches          = ref([])
  const systemConfig      = ref(null)
  const authenticated     = ref(false)
  const checked           = ref(false)
  const loading           = ref(false)
  const switchingBranch   = ref(false)

  function applyTenantPayload(data) {
    if (data?.activeBranch) {
      activeBranch.value  = data.activeBranch
      setActiveCurrency(data.activeBranch.currency_code, data.activeBranch.currency_symbol)
    }
    if (Array.isArray(data?.branches)) {
      branches.value      = data.branches
    }
    if (data?.systemConfig) {
      systemConfig.value  = data.systemConfig
    }
  }

  async function checkAuth(force = false) {
    if (checked.value && !force) {
      return authenticated.value
    }
    try {
      const { data } = await apiClient.get('/checkAuth.php', { skipErrorRedirect: true })
      if (data?.csrfToken) setCsrfToken(data.csrfToken)
      authenticated.value = Boolean(data?.authenticated)
      user.value = data?.user || null
      if (authenticated.value) {
        applyTenantPayload(data)
      } else {
        activeBranch.value  = null
        branches.value      = []
        systemConfig.value  = null
      }
    } catch {
      authenticated.value = false
      user.value          = null
      activeBranch.value  = null
      branches.value      = []
      systemConfig.value  = null
    } finally {
      checked.value       = true
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
      if (data?.csrfToken) setCsrfToken(data.csrfToken)
      authenticated.value = Boolean(data?.authenticated)
      user.value = data?.user || null
      applyTenantPayload(data)
      checked.value = true
      return { ok: true, message: data?.messages || 'Inicio de sesión exitoso' }
    } catch (err) {
      const retryAfter = Number(err.response?.data?.data?.retryAfter || err.response?.headers?.['retry-after'] || 0)
      const status = err.response?.status
      return {
        ok: false,
        status,
        retryAfter,
        message: extractErrorMessage(err, 'Credenciales inválidas.'),
      }
    } finally {
      loading.value = false
    }
  }

  async function switchBranch(branchId) {
    switchingBranch.value = true
    try {
      const { data } = await apiClient.post('/switchBranch.php', { branchId })
      applyTenantPayload(data)
      return data
    } finally {
      switchingBranch.value = false
    }
  }

  async function logout() {
    try {
      await apiClient.post('/logout.php', {}, { skipErrorRedirect: true })
    } finally {
      clearCsrfToken()
      clearSession()
    }
  }

  function clearSession() {
    authenticated.value = false
    user.value          = null
    activeBranch.value  = null
    branches.value      = []
    systemConfig.value  = null
    setActiveCurrency('USD', '$')
    checked.value       = true
  }

  const isSuperadmin = computed(() => {
    return Boolean(user.value?.permissions?.is_superadmin || user.value?.role === 'superadmin')
  })

  function can(module, action = 'access') {
    if (!user.value) return false
    const perms = user.value.permissions
    if (!perms) return true
    if (perms.is_superadmin) return true

    const act   = String(action).toLowerCase().trim()
    const prop  = act === 'create' || act === 'add' ? 'can_create'
      : act === 'update' || act === 'edit' ? 'can_update'
      : act === 'delete' || act === 'remove' ? 'can_delete'
      : 'can_access'

    return Boolean(perms.modules?.[module]?.[prop])
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
    if (data?.csrfToken) setCsrfToken(data.csrfToken)
    return data
  }

  return {
    user,
    activeBranch,
    branches,
    systemConfig,
    authenticated,
    checked,
    loading,
    switchingBranch,
    isSuperadmin,
    can,
    applyTenantPayload,
    checkAuth,
    login,
    switchBranch,
    logout,
    clearSession,
    updateUsername,
    changePassword,
  }
})
