import { defineStore } from 'pinia'
import apiClient, { extractErrorMessage } from '@/api/client'

export const useOperationsStore = defineStore('operations', {
  state: () => ({
    operations: [],
    stats: {
      total_ops: 0,
      total_deposits: 0,
      total_withdrawals: 0,
      total_commissions: 0,
      total_points_earned: 0,
    },
    centralCashBalance: 0,
    settings: {
      points_ratio_soles: 10,
      points_ratio_points: 1,
      print_double_ticket: true,
      commission_rate_per_100: 1.0,
    },
    leaderboard: [],
    clientHistory: [],
    rewards: [],
    recentRedemptions: [],
    loading: false,
    submitting: false,
    filters: {
      startDate: '',
      endDate: '',
      operation_type: 'all',
      client_id: '',
      search: '',
    },
  }),

  getters: {
    formattedCentralCashBalance: (state) => {
      return Number(state.centralCashBalance || 0).toLocaleString('es-PE', {
        style: 'currency',
        currency: 'PEN',
      })
    },
  },

  actions: {
    async fetchOperations() {
      this.loading = true
      try {
        const params = new URLSearchParams()
        if (this.filters.startDate) params.append('startDate', this.filters.startDate)
        if (this.filters.endDate) params.append('endDate', this.filters.endDate)
        if (this.filters.operation_type && this.filters.operation_type !== 'all') {
          params.append('operation_type', this.filters.operation_type)
        }
        if (this.filters.client_id) params.append('client_id', this.filters.client_id)
        if (this.filters.search) params.append('search', this.filters.search)

        const res = await apiClient.get(`/fetchOperations.php?${params.toString()}`)
        const payload = res.data
        if (payload?.success) {
          this.operations = payload.data || []
          this.stats = payload.stats || this.stats
          this.centralCashBalance = payload.central_cash_balance ?? 0
          if (payload.settings) {
            this.settings = { ...this.settings, ...payload.settings }
          }
        }
        return payload
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.loading = false
      }
    },

    async createOperation(payload) {
      this.submitting = true
      try {
        const res = await apiClient.post('/createOperation.php', payload)
        const data = res.data
        if (data?.success) {
          await this.fetchOperations()
        }
        return data
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.submitting = false
      }
    },

    async fetchLoyaltyRanking(clientId = null, search = '') {
      this.loading = true
      try {
        const params = new URLSearchParams()
        if (clientId) params.append('client_id', clientId)
        if (search) params.append('search', search)

        const res = await apiClient.get(`/fetchLoyaltyRanking.php?${params.toString()}`)
        const payload = res.data
        if (payload?.success) {
          this.leaderboard = payload.leaderboard || []
          if (clientId) {
            this.clientHistory = payload.client_history || []
          }
        }
        return payload
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.loading = false
      }
    },

    async fetchRewards(category = 'all') {
      this.loading = true
      try {
        const params = new URLSearchParams()
        if (category && category !== 'all') params.append('category', category)

        const res = await apiClient.get(`/fetchRewards.php?${params.toString()}`)
        const payload = res.data
        if (payload?.success) {
          this.rewards = payload.data || []
          this.recentRedemptions = payload.recent_redemptions || []
        }
        return payload
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.loading = false
      }
    },

    async redeemReward(payload) {
      this.submitting = true
      try {
        const res = await apiClient.post('/redeemReward.php', payload)
        const data = res.data
        if (data?.success) {
          await Promise.all([this.fetchRewards(), this.fetchLoyaltyRanking()])
        }
        return data
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.submitting = false
      }
    },

    async fetchReceiptData(operationId) {
      try {
        const res = await apiClient.get(`/printOperationReceipt.php?operation_id=${operationId}`)
        return res.data?.data || null
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      }
    },
  },
})
