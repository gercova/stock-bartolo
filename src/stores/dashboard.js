import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient, { extractErrorMessage } from '@/api/client'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref({
    branch: null,
    totalProducts: 0,
    totalOrders: 0,
    lowStockCount: 0,
    totalRevenue: 0,
    totalOrderDue: 0,
    lowStockProducts: [],
    recentOrders: [],
    generalCash: null,
    activeTillSession: null,
    loans: {
      totalLoans: 0,
      activeLoans: 0,
      overdueLoans: 0,
      totalPrincipal: 0,
      totalInterest: 0,
      totalCollected: 0,
      totalPending: 0,
      recent: [],
    },
  })

  const analytics = ref({
    selected_year: new Date().getFullYear(),
    selected_branch: 'all',
    available_years: [new Date().getFullYear()],
    branches: [],
    totals: {
      total_ops: 0,
      deposit_count: 0,
      withdrawal_count: 0,
      total_deposits_amount: 0,
      total_withdrawals_amount: 0,
      total_commissions_amount: 0,
      total_volume_processed: 0,
      net_cash_flow: 0,
      total_points_earned: 0,
      total_clients_transacted: 0,
    },
    monthly: [],
    operations_by_type: [],
    branches_activity: [],
    top_clients_by_volume: [],
    top_clients_by_frequency: [],
    top_clients_by_points: [],
  })

  const loading = ref(false)
  const analyticsLoading = ref(false)

  async function fetchDashboard() {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchDashboard.php')
      if (data?.data) {
        stats.value = data.data
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchBusinessAnalytics(year = null, branchId = 'all') {
    analyticsLoading.value = true
    try {
      const params = new URLSearchParams()
      if (year) params.append('year', year)
      if (branchId) params.append('branch_id', branchId)

      const res = await apiClient.get(`/fetchBusinessAnalytics.php?${params.toString()}`)
      const payload = res.data
      if (payload?.success) {
        analytics.value = payload
      }
      return payload
    } catch (err) {
      throw new Error(extractErrorMessage(err))
    } finally {
      analyticsLoading.value = false
    }
  }

  return {
    stats,
    analytics,
    loading,
    analyticsLoading,
    fetchDashboard,
    fetchBusinessAnalytics,
  }
})
