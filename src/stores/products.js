import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useProductsStore = defineStore('products', () => {
  const items      = ref([])
  const pagination = ref({
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
  const loading = ref(false)

  async function fetchProducts(params = {}, append = false) {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchProduct.php', { params })
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

  async function createProduct(formData) {
    const { data } = await apiClient.post('/createProduct.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  }

  async function updateProduct(payload) {
    const { data } = await apiClient.post('/editProduct.php', payload)
    return data
  }

  async function updateProductImage(formData) {
    const { data } = await apiClient.post('/editProductImage.php', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  }

  async function deleteProduct(productId) {
    const { data } = await apiClient.post('/removeProduct.php', { productId })
    return data
  }

  return {
    items,
    pagination,
    loading,
    fetchProducts,
    createProduct,
    updateProduct,
    updateProductImage,
    deleteProduct,
  }
})
