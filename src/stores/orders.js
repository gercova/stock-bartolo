import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useOrdersStore = defineStore('orders', () => {
  const items = ref([])
  const availableProducts = ref([])
  const currentOrder = ref(null)
  const currentItems = ref([])
  const loading = ref(false)

  async function fetchOrders() {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchOrder.php')
      items.value = data?.data || []
      return items.value
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
    await fetchOrders()
    return data
  }

  async function deleteOrder(orderId) {
    const { data } = await apiClient.post('/removeOrder.php', { orderId })
    await fetchOrders()
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
