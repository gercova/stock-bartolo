import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useSecurityStore = defineStore('security', () => {
  const users = ref([])
  const userStats = ref({
    total: 0,
    active: 0,
    inactive: 0,
    superadmins: 0,
  })

  const roles = ref([])
  const availableModules = ref([])

  const auditLogs = ref([])
  const auditTotal = ref(0)
  const auditStats = ref({
    total_all: 0,
    today_logs: 0,
    today_users: 0,
    auth_events_24h: 0,
    security_alerts: 0,
  })

  const loadingUsers = ref(false)
  const loadingRoles = ref(false)
  const loadingAudit = ref(false)
  const saving = ref(false)

  async function fetchUsers(filters = {}) {
    loadingUsers.value = true
    try {
      const params = {}
      if (filters.search) params.search = filters.search
      if (filters.role_id) params.role_id = filters.role_id
      if (filters.branch_id) params.branch_id = filters.branch_id
      if (filters.active !== undefined && filters.active !== '') params.active = filters.active

      const { data } = await apiClient.get('/fetchUsers.php', { params })
      users.value = data?.data || []
      if (data?.stats) {
        userStats.value = data.stats
      }
      return data
    } finally {
      loadingUsers.value = false
    }
  }

  async function createUser(payload) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/createUser.php', payload)
      await fetchUsers()
      return data
    } finally {
      saving.value = false
    }
  }

  async function updateUser(payload) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/editUser.php', payload)
      await fetchUsers()
      return data
    } finally {
      saving.value = false
    }
  }

  async function removeUser(userId) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/removeUser.php', { user_id: userId })
      await fetchUsers()
      return data
    } finally {
      saving.value = false
    }
  }

  async function fetchRoles() {
    loadingRoles.value = true
    try {
      const { data } = await apiClient.get('/fetchRoles.php')
      roles.value = data?.data || []
      availableModules.value = data?.available_modules || []
      return data
    } finally {
      loadingRoles.value = false
    }
  }

  async function createRole(payload) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/createRole.php', payload)
      await fetchRoles()
      return data
    } finally {
      saving.value = false
    }
  }

  async function updateRole(payload) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/editRole.php', payload)
      await fetchRoles()
      return data
    } finally {
      saving.value = false
    }
  }

  async function removeRole(roleId) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/removeRole.php', { role_id: roleId })
      await fetchRoles()
      return data
    } finally {
      saving.value = false
    }
  }

  async function fetchAuditLogs(filters = {}) {
    loadingAudit.value = true
    try {
      const params = {}
      if (filters.startDate) params.startDate = filters.startDate
      if (filters.endDate) params.endDate = filters.endDate
      if (filters.module && filters.module !== 'all') params.module = filters.module
      if (filters.action && filters.action !== 'all') params.action = filters.action
      if (filters.userId) params.userId = filters.userId
      if (filters.branchId) params.branchId = filters.branchId
      if (filters.search) params.search = filters.search
      if (filters.limit) params.limit = filters.limit
      if (filters.offset) params.offset = filters.offset

      const { data } = await apiClient.get('/fetchAuditLogs.php', { params })
      auditLogs.value = data?.data || []
      auditTotal.value = data?.total || 0
      if (data?.stats) {
        auditStats.value = data.stats
      }
      return data
    } finally {
      loadingAudit.value = false
    }
  }

  return {
    users,
    userStats,
    roles,
    availableModules,
    auditLogs,
    auditTotal,
    auditStats,
    loadingUsers,
    loadingRoles,
    loadingAudit,
    saving,
    fetchUsers,
    createUser,
    updateUser,
    removeUser,
    fetchRoles,
    createRole,
    updateRole,
    removeRole,
    fetchAuditLogs,
  }
})

