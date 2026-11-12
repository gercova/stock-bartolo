import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useClientsStore = defineStore('clients', () => {
  const clients = ref([])
  const stats = ref({
    total: 0,
    clients_count: 0,
    suppliers_count: 0,
    active_count: 0,
    inactive_count: 0,
  })

  const loading   = ref(false)
  const saving    = ref(false)
  const lookingUp = ref(false)

  async function fetchClients(filters = {}) {
    loading.value = true
    try {
      const params = {}
      if (filters.search) params.search = filters.search
      if (filters.client_type && filters.client_type !== 'all') params.client_type = filters.client_type
      if (filters.document_type && filters.document_type !== 'all') params.document_type = filters.document_type
      if (filters.status !== undefined && filters.status !== '' && filters.status !== 'all') params.status = filters.status
      if (filters.branch_id) params.branch_id = filters.branch_id

      const { data }  = await apiClient.get('/fetchClients.php', { params })
      clients.value   = data?.data || []
      if (data?.stats) {
        stats.value = data.stats
      }
      return data
    } finally {
      loading.value = false
    }
  }

  async function createClient(payload) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/createClient.php', payload)
      await fetchClients()
      return data
    } finally {
      saving.value = false
    }
  }

  async function updateClient(payload) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/editClient.php', payload)
      await fetchClients()
      return data
    } finally {
      saving.value = false
    }
  }

  async function removeClient(clientId) {
    saving.value = true
    try {
      const { data } = await apiClient.post('/removeClient.php', { client_id: clientId })
      await fetchClients()
      return data
    } finally {
      saving.value = false
    }
  }

  async function lookupDocument(type, number) {
    lookingUp.value = true
    try {
      const { data } = await apiClient.get('/lookupDocument.php', {
        params: { type, number },
      })
      return data
    } finally {
      lookingUp.value = false
    }
  }

  return {
    clients,
    stats,
    loading,
    saving,
    lookingUp,
    fetchClients,
    createClient,
    updateClient,
    removeClient,
    lookupDocument,
  }
})

