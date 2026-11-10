import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref({
    totalProducts: 0,
    totalOrders: 0,
    lowStockCount: 0,
    totalRevenue: 0,
    totalOrderDue: 0,
    lowStockProducts: [],
    recentOrders: [],
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
  const loading = ref(false)

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

  return { stats, loading, fetchDashboard }
})
