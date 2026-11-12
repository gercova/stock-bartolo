import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const DENOMINATION_ROWS = [
  { key: '100', label: 'Billetes de $100', value: 100 },
  { key: '50', label: 'Billetes de $50', value: 50 },
  { key: '20', label: 'Billetes de $20', value: 20 },
  { key: '10', label: 'Billetes de $10', value: 10 },
  { key: '5', label: 'Billetes de $5', value: 5 },
  { key: '2', label: 'Billetes de $2', value: 2 },
  { key: '1', label: 'Billetes / Monedas de $1', value: 1 },
  { key: '0.50', label: 'Monedas de $0.50', value: 0.5 },
  { key: '0.25', label: 'Monedas de $0.25', value: 0.25 },
  { key: '0.10', label: 'Monedas de $0.10', value: 0.1 },
  { key: '0.05', label: 'Monedas de $0.05', value: 0.05 },
  { key: '0.01', label: 'Monedas de $0.01', value: 0.01 },
]

export function createEmptyDenominations() {
  const map = {}
  for (const row of DENOMINATION_ROWS) {
    map[row.key] = 0
  }
  return map
}

export function sumDenominations(denomMap) {
  let cents = 0
  for (const row of DENOMINATION_ROWS) {
    const qty = Math.max(0, parseInt(denomMap?.[row.key], 10) || 0)
    cents += qty * Math.round(row.value * 100)
  }
  return Math.round(cents) / 100
}

export const useCashRegisterStore = defineStore('cashRegister', () => {
  const activeBranch = ref(null)
  const systemConfig = ref(null)
  const generalCash = ref(null)
  const registers = ref([])
  const openSessions = ref([])
  const sessions = ref([])

  const currentDetail = ref(null)
  const loading = ref(false)
  const loadingDetail = ref(false)

  async function fetchCashDashboard() {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchCashSessions.php')
      activeBranch.value = data?.activeBranch || null
      systemConfig.value = data?.systemConfig || null
      generalCash.value = data?.generalCash || null
      registers.value = data?.registers || []
      openSessions.value = data?.openSessions || []
      sessions.value = data?.sessions || []
      return data
    } finally {
      loading.value = false
    }
  }

  async function fetchRegisters() {
    const { data } = await apiClient.get('/fetchCashRegisters.php')
    registers.value = data?.data || []
    return registers.value
  }

  async function createRegister(payload) {
    const { data } = await apiClient.post('/createCashRegister.php', payload)
    await fetchCashDashboard()
    return data
  }

  async function updateRegister(payload) {
    const { data } = await apiClient.post('/editCashRegister.php', payload)
    await fetchCashDashboard()
    return data
  }

  async function deleteRegister(registerId) {
    const { data } = await apiClient.post('/removeCashRegister.php', { registerId })
    await fetchCashDashboard()
    return data
  }

  async function openSession(payload) {
    const { data } = await apiClient.post('/openCashSession.php', payload)
    await fetchCashDashboard()
    return data
  }

  async function fetchSessionDetail(sessionId) {
    loadingDetail.value = true
    try {
      const { data } = await apiClient.get('/fetchCashSessionDetail.php', {
        params: { sessionId },
      })
      currentDetail.value = data
      return data
    } finally {
      loadingDetail.value = false
    }
  }

  async function addMovement(payload) {
    const { data } = await apiClient.post('/addCashMovement.php', payload)
    await fetchCashDashboard()
    if (currentDetail.value?.session?.session_id === payload.sessionId) {
      await fetchSessionDetail(payload.sessionId)
    }
    return data
  }

  async function reconcileSession(payload) {
    const { data } = await apiClient.post('/reconcileCashSession.php', payload)
    await fetchCashDashboard()
    if (currentDetail.value?.session?.session_id === payload.sessionId) {
      await fetchSessionDetail(payload.sessionId)
    }
    return data
  }

  async function closeSession(payload) {
    const { data } = await apiClient.post('/closeCashSession.php', payload)
    await fetchCashDashboard()
    if (currentDetail.value?.session?.session_id === payload.sessionId) {
      await fetchSessionDetail(payload.sessionId)
    }
    return data
  }

  return {
    activeBranch,
    systemConfig,
    generalCash,
    registers,
    openSessions,
    sessions,
    currentDetail,
    loading,
    loadingDetail,
    fetchCashDashboard,
    fetchRegisters,
    createRegister,
    updateRegister,
    deleteRegister,
    openSession,
    fetchSessionDetail,
    addMovement,
    reconcileSession,
    closeSession,
  }
})

