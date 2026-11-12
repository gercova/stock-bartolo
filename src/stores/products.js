import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useProductsStore = defineStore('products', () => {
  const items   = ref([])
  const loading = ref(false)

  async function fetchProducts() {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchProduct.php')
      items.value = data?.data || []
      return items.value
    } finally {
      loading.value = false
    }
  }

  async function createProduct(formData) {
    const { data } = await apiClient.post('/createProduct.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await fetchProducts()
    return data
  }

  async function updateProduct(payload) {
    const { data } = await apiClient.post('/editProduct.php', payload)
    await fetchProducts()
    return data
  }

  async function updateProductImage(formData) {
    const { data } = await apiClient.post('/editProductImage.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await fetchProducts()
    return data
  }

  async function deleteProduct(productId) {
    const { data } = await apiClient.post('/removeProduct.php', { productId })
    await fetchProducts()
    return data
  }

  return {
    items,
    loading,
    fetchProducts,
    createProduct,
    updateProduct,
    updateProductImage,
    deleteProduct,
  }
})
