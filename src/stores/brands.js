import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useBrandsStore = defineStore('brands', () => {
  const items = ref([])
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

  async function fetchBrands(params = {}, append = false) {
    loading.value = true
    try {
      let queryParams = {}
      if (typeof params === 'boolean') {
        if (params) queryParams.active = '1'
      } else if (params && typeof params === 'object') {
        queryParams = { ...params }
      }
      const { data } = await apiClient.get('/fetchBrand.php', { params: queryParams })
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

  async function createBrand(payload) {
    const { data } = await apiClient.post('/createBrand.php', payload)
    return data
  }

  async function updateBrand(payload) {
    const { data } = await apiClient.post('/editBrand.php', payload)
    return data
  }

  async function deleteBrand(brandId) {
    const { data } = await apiClient.post('/removeBrand.php', { brandId })
    return data
  }

  return { items, pagination, loading, fetchBrands, createBrand, updateBrand, deleteBrand }
})
