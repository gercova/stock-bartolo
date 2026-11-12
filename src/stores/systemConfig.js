import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'
import { useAuthStore } from './auth'

export const useSystemConfigStore = defineStore('systemConfig', () => {
  const branches = ref([])
  const activeBranchId = ref(1)
  const canSwitchBranches = ref(true)
  const config = ref({
    company_name: 'Stock Bartolo',
    company_legal_name: '',
    company_logo: '',
    company_tax_id: '',
    company_phone: '',
    company_address: '',
    receipt_footer: 'Gracias por su preferencia.',
    default_tax_rate: 13.0,
    default_currency_code: 'USD',
    default_currency_symbol: '$',
    enforce_open_till_for_cash: false,
    auto_fund_till_from_general_cash: true,
    auto_return_till_to_general_cash: true,
    allow_branch_switching: true,
  })

  const generalCashBranch = ref(null)
  const generalCashAccount = ref(null)
  const generalCashSummary = ref({
    currentBalance: 0,
    initialCapital: 0,
    totalDeposits: 0,
    totalWithdrawals: 0,
    totalAllocatedToTills: 0,
    totalReturnedFromTills: 0,
    isBelowMinAlert: false,
    movementsCount: 0,
  })
  const generalCashMovements = ref([])
  const branchVaults = ref([])

  const loadingBranches = ref(false)
  const loadingConfig = ref(false)
  const loadingGeneralCash = ref(false)

  async function fetchBranches() {
    loadingBranches.value = true
    try {
      const { data } = await apiClient.get('/fetchBranches.php')
      branches.value = data?.data || []
      activeBranchId.value = data?.activeBranchId || 1
      canSwitchBranches.value = Boolean(data?.canSwitch)

      const auth = useAuthStore()
      const activeOnly = branches.value
        .filter((b) => Number(b.active) === 1)
        .map((b) => ({
          ...b,
          general_cash_balance: b.general_cash?.current_balance ?? b.general_cash_balance ?? 0,
        }))
      auth.applyTenantPayload({
        activeBranch: data?.activeBranch || auth.activeBranch,
        branches: activeOnly,
      })
      return branches.value
    } finally {
      loadingBranches.value = false
    }
  }

  async function createBranch(payload) {
    const { data } = await apiClient.post('/createBranch.php', payload)
    const auth = useAuthStore()
    if (data?.branches) {
      auth.applyTenantPayload({ branches: data.branches })
    }
    await fetchBranches()
    await fetchGeneralCash(generalCashBranch.value?.branch_id || activeBranchId.value)
    return data
  }

  async function updateBranch(payload) {
    const { data } = await apiClient.post('/editBranch.php', payload)
    const auth = useAuthStore()
    auth.applyTenantPayload({
      activeBranch: data?.activeBranch,
      branches: data?.branches,
    })
    await fetchBranches()
    await fetchGeneralCash(generalCashBranch.value?.branch_id || activeBranchId.value)
    return data
  }

  async function deleteBranch(branchId) {
    const { data } = await apiClient.post('/removeBranch.php', { branchId })
    const auth = useAuthStore()
    auth.applyTenantPayload({
      activeBranch: data?.activeBranch,
      branches: data?.branches,
    })
    await fetchBranches()
    await fetchGeneralCash(data?.activeBranch?.branch_id || activeBranchId.value)
    return data
  }

  async function fetchSystemConfig() {
    loadingConfig.value = true
    try {
      const { data } = await apiClient.get('/fetchSystemConfig.php')
      if (data?.config) {
        config.value = data.config
        const auth = useAuthStore()
        auth.applyTenantPayload({
          systemConfig: data.config,
          activeBranch: data?.activeBranch,
        })
      }
      return config.value
    } finally {
      loadingConfig.value = false
    }
  }

  async function saveSystemConfig(payload) {
    const { data } = await apiClient.post('/saveSystemConfig.php', payload)
    if (data?.config) {
      config.value = data.config
      const auth = useAuthStore()
      auth.applyTenantPayload({ systemConfig: data.config })
    }
    return data
  }

  async function uploadLogo(file) {
    const formData = new FormData()
    formData.append('logo', file)
    const { data } = await apiClient.post('/uploadCompanyLogo.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (data?.config) {
      config.value = data.config
      const auth = useAuthStore()
      auth.applyTenantPayload({ systemConfig: data.config })
    }
    return data
  }

  async function fetchGeneralCash(branchId = null) {
    loadingGeneralCash.value = true
    try {
      const { data } = await apiClient.get('/fetchGeneralCash.php', {
        params: branchId ? { branchId } : undefined,
      })
      generalCashBranch.value = data?.branch || null
      generalCashAccount.value = data?.account || null
      if (data?.summary) {
        generalCashSummary.value = data.summary
      }
      generalCashMovements.value = data?.movements || []
      branchVaults.value = data?.branchVaults || []
      return data
    } finally {
      loadingGeneralCash.value = false
    }
  }

  async function updateGeneralCashAccount(payload) {
    const { data } = await apiClient.post('/manageGeneralCash.php', {
      action: 'update_account',
      ...payload,
    })
    await Promise.all([
      fetchGeneralCash(payload.branchId || generalCashBranch.value?.branch_id),
      fetchBranches(),
    ])
    return data
  }

  async function addGeneralCashMovement(payload) {
    const { data } = await apiClient.post('/manageGeneralCash.php', {
      action: 'add_movement',
      ...payload,
    })
    await Promise.all([
      fetchGeneralCash(payload.branchId || generalCashBranch.value?.branch_id),
      fetchBranches(),
    ])
    return data
  }

  async function updateGeneralCashMovement(payload) {
    const { data } = await apiClient.post('/manageGeneralCash.php', {
      action: 'update_movement',
      ...payload,
    })
    await fetchGeneralCash(payload.branchId || generalCashBranch.value?.branch_id)
    return data
  }

  async function voidGeneralCashMovement(movementId, branchId = null) {
    const targetBranchId = branchId || generalCashBranch.value?.branch_id
    const { data } = await apiClient.post('/manageGeneralCash.php', {
      action: 'void_movement',
      movementId,
      branchId: targetBranchId,
    })
    await Promise.all([fetchGeneralCash(targetBranchId), fetchBranches()])
    return data
  }

  return {
    branches,
    activeBranchId,
    canSwitchBranches,
    config,
    generalCashBranch,
    generalCashAccount,
    generalCashSummary,
    generalCashMovements,
    branchVaults,
    loadingBranches,
    loadingConfig,
    loadingGeneralCash,
    fetchBranches,
    createBranch,
    updateBranch,
    deleteBranch,
    fetchSystemConfig,
    saveSystemConfig,
    uploadLogo,
    fetchGeneralCash,
    updateGeneralCashAccount,
    addGeneralCashMovement,
    updateGeneralCashMovement,
    voidGeneralCashMovement,
  }
})
