import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export function calculateLoanPreview({
  principalAmount = 0,
  hasInterest     = false,
  interestRate    = 0,
  interestMode    = 'flat',
  termMonths      = 1,
}) {
  const principal = Math.max(0, Number(principalAmount) || 0)
  const rate      = hasInterest ? Math.max(0, Number(interestRate) || 0) : 0
  const term      = Math.max(1, parseInt(termMonths, 10) || 1)

  let interestAmount = 0
  if (hasInterest && principal > 0 && rate > 0) {
    if (interestMode === 'monthly') {
      interestAmount = principal * (rate / 100) * term
    } else {
      interestAmount = principal * (rate / 100)
    }
  }

  interestAmount          = Math.round(interestAmount * 100) / 100
  const totalAmount       = Math.round((principal + interestAmount) * 100) / 100
  const installmentAmount = Math.round((totalAmount / term) * 100) / 100

  return {
    principal,
    interestAmount,
    totalAmount,
    installmentAmount,
    term,
  }
}

export const useLoansStore = defineStore('loans', () => {
  const items = ref([])
  const summary = ref({
    totalLoans: 0,
    activeLoans: 0,
    paidLoans: 0,
    overdueLoans: 0,
    withInterestCount: 0,
    withoutInterestCount: 0,
    totalPrincipal: 0,
    totalInterest: 0,
    totalAmount: 0,
    totalCollected: 0,
    totalPending: 0,
  })
  const currentLoan = ref(null)
  const currentPayments = ref([])
  const loading = ref(false)

  async function fetchLoans() {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchLoan.php')
      items.value = data?.data || []
      if (data?.summary) {
        summary.value = data.summary
      }
      return items.value
    } finally {
      loading.value = false
    }
  }

  async function fetchLoanDetail(loanId) {
    const { data } = await apiClient.get('/fetchLoanData.php', {
      params: { loanId },
    })
    currentLoan.value = data?.loan || null
    currentPayments.value = data?.payments || []
    return data
  }

  async function createLoan(payload) {
    const { data } = await apiClient.post('/createLoan.php', payload)
    await fetchLoans()
    return data
  }

  async function updateLoan(payload) {
    const { data } = await apiClient.post('/editLoan.php', payload)
    await fetchLoans()
    return data
  }

  async function addPayment(payload) {
    const { data } = await apiClient.post('/addLoanPayment.php', payload)
    await fetchLoans()
    return data
  }

  async function deleteLoan(loanId) {
    const { data } = await apiClient.post('/removeLoan.php', { loanId })
    await fetchLoans()
    return data
  }

  return {
    items,
    summary,
    currentLoan,
    currentPayments,
    loading,
    fetchLoans,
    fetchLoanDetail,
    createLoan,
    updateLoan,
    addPayment,
    deleteLoan,
  }
})
