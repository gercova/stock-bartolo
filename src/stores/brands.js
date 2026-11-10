import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useBrandsStore = defineStore('brands', () => {
  const items = ref([])
  const loading = ref(false)

  async function fetchBrands(onlyActive = false) {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchBrand.php', {
        params: onlyActive ? { active: '1' } : undefined,
      })
      items.value = data?.data || []
      return items.value
    } finally {
      loading.value = false
    }
  }

  async function createBrand(payload) {
    const { data } = await apiClient.post('/createBrand.php', payload)
    await fetchBrands()
    return data
  }

  async function updateBrand(payload) {
    const { data } = await apiClient.post('/editBrand.php', payload)
    await fetchBrands()
    return data
  }

  async function deleteBrand(brandId) {
    const { data } = await apiClient.post('/removeBrand.php', { brandId })
    await fetchBrands()
    return data
  }

  return { items, loading, fetchBrands, createBrand, updateBrand, deleteBrand }
})
