import { defineStore } from 'pinia'
import apiClient, { extractErrorMessage } from '@/api/client'

export const useOperationsStore = defineStore('operations', {
  state: () => ({
    operations: [],
    pagination: {
      total: 0,
      page: 1,
      per_page: 25,
      total_pages: 1,
      has_more: false,
      from: 0,
      to: 0,
      chunk_size: 25,
      current_chunk: 1,
      total_chunks: 1,
    },
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
    rankingPagination: {
      total: 0,
      page: 1,
      per_page: 25,
      total_pages: 1,
      has_more: false,
      from: 0,
      to: 0,
      chunk_size: 25,
      current_chunk: 1,
      total_chunks: 1,
    },
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
    async fetchOperations(options = {}, append = false) {
      this.loading = true
      try {
        const page = options.page || (append ? (this.pagination.page + 1) : this.pagination.page)
        const perPage = options.per_page || this.pagination.per_page || 25

        const params = new URLSearchParams()
        params.append('page', String(page))
        params.append('per_page', String(perPage))

        if (this.filters.startDate) params.append('startDate', this.filters.startDate)
        if (this.filters.endDate) params.append('endDate', this.filters.endDate)
        if (this.filters.operation_type && this.filters.operation_type !== 'all') {
          params.append('operation_type', this.filters.operation_type)
        }
        if (this.filters.client_id) params.append('client_id', String(this.filters.client_id))
        if (this.filters.search) params.append('search', this.filters.search)

        const res     = await apiClient.get(`/fetchOperations.php?${params.toString()}`)
        const payload = res.data
        if (payload?.success) {
          const newItems = payload.data || []
          if (append) {
            this.operations = [...this.operations, ...newItems]
          } else {
            this.operations = newItems
          }
          if (payload.pagination) {
            this.pagination = payload.pagination
          }
          this.stats      = payload.stats || this.stats
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
        const res   = await apiClient.post('/createOperation.php', payload)
        const data  = res.data
        if (data?.success) {
          await this.fetchOperations({ page: 1 }, false)
        }
        return data
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.submitting = false
      }
    },

    async fetchLoyaltyRanking(options = {}, append = false) {
      this.loading = true
      try {
        const page = options.page || (append ? (this.rankingPagination.page + 1) : this.rankingPagination.page)
        const perPage = options.per_page || this.rankingPagination.per_page || 25
        const clientId = options.client_id || null
        const search = options.search !== undefined ? options.search : ''

        const params = new URLSearchParams()
        params.append('page', String(page))
        params.append('per_page', String(perPage))
        if (clientId) params.append('client_id', String(clientId))
        if (search) params.append('search', search)

        const res = await apiClient.get(`/fetchLoyaltyRanking.php?${params.toString()}`)
        const payload = res.data
        if (payload?.success) {
          const list = payload.data || payload.leaderboard || []
          if (append) {
            this.leaderboard = [...this.leaderboard, ...list]
          } else {
            this.leaderboard = list
          }
          if (payload.pagination) {
            this.rankingPagination = payload.pagination
          }
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
          this.rewards = payload.rewards || []
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
          await this.fetchRewards()
          await this.fetchLoyaltyRanking()
        }
        return data
      } catch (err) {
        throw new Error(extractErrorMessage(err))
      } finally {
        this.submitting = false
      }
    },
  },
})
