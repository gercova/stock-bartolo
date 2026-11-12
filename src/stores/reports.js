import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useReportsStore = defineStore('reports', () => {
  const orders  = ref([])
  const loans   = ref([])
  const summary = ref({
    count: 0,
    totalAmount: 0,
    totalPaid: 0,
    totalDue: 0,
  })
  const loanSummary = ref({
    count: 0,
    totalPrincipal: 0,
    totalInterest: 0,
    totalAmount: 0,
    totalPaid: 0,
    totalBalance: 0,
  })
  const range         = ref({ startDate: '', endDate: '' })
  const loading       = ref(false)
  const hasGenerated  = ref(false)

  async function fetchReport(startDate, endDate) {
    loading.value = true
    try {
      const { data }      = await apiClient.post('/getOrderReport.php', { startDate, endDate })
      orders.value        = data?.orders || []
      loans.value         = data?.loans || []
      summary.value       = data?.summary || summary.value
      loanSummary.value   = data?.loanSummary || loanSummary.value
      range.value         = data?.range || { startDate, endDate }
      hasGenerated.value  = true
      return data
    } finally {
      loading.value = false
    }
  }

  return {
    orders,
    loans,
    summary,
    loanSummary,
    range,
    loading,
    hasGenerated,
    fetchReport,
  }
})
