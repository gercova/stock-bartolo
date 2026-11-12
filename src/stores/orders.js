import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useOrdersStore = defineStore('orders', () => {
  const items             = ref([])
  const pagination        = ref({
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
  })
  const availableProducts = ref([])
  const currentOrder      = ref(null)
  const currentItems      = ref([])
  const loading           = ref(false)

  async function fetchOrders(params = {}, append = false) {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchOrder.php', { params })
      if (append) {
        items.value = [...items.value, ...(data?.data || [])]
      } else {
        items.value = data?.data || []
      }
      if (data?.pagination) {
        pagination.value = data.pagination
      }
      return data
    } finally {
      loading.value = false
    }
  }

  async function fetchAvailableProducts(includeAll = false) {
    const { data } = await apiClient.get('/fetchProductData.php', {
      params: includeAll ? { all: '1' } : undefined,
    })
    availableProducts.value = data?.data || []
    return availableProducts.value
  }

  async function fetchOrderById(orderId) {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchOrderData.php', {
        params: { orderId },
      })
      currentOrder.value = data?.order || null
      currentItems.value = data?.items || []
      return data
    } finally {
      loading.value = false
    }
  }

  async function createOrder(payload) {
    const { data } = await apiClient.post('/createOrder.php', payload)
    return data
  }

  async function updateOrder(payload) {
    const { data } = await apiClient.post('/editOrder.php', payload)
    return data
  }

  async function addPayment(payload) {
    const { data } = await apiClient.post('/editPayment.php', payload)
    return data
  }

  async function deleteOrder(orderId) {
    const { data } = await apiClient.post('/removeOrder.php', { orderId })
    return data
  }

  async function fetchPrintOrder(orderId) {
    const { data } = await apiClient.get('/printOrder.php', {
      params: { orderId },
    })
    return data
  }

  return {
    items,
    pagination,
    availableProducts,
    currentOrder,
    currentItems,
    loading,
    fetchOrders,
    fetchAvailableProducts,
    fetchOrderById,
    createOrder,
    updateOrder,
    addPayment,
    deleteOrder,
    fetchPrintOrder,
  }
})
