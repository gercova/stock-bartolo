import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export const useCategoriesStore = defineStore('categories', () => {
  const items   = ref([])
  const loading = ref(false)

  async function fetchCategories(onlyActive = false) {
    loading.value = true
    try {
      const { data } = await apiClient.get('/fetchCategories.php', {
        params: onlyActive ? { active: '1' } : undefined,
      })
      items.value = data?.data || []
      return items.value
    } finally {
      loading.value = false
    }
  }

  async function createCategory(payload) {
    const { data } = await apiClient.post('/createCategories.php', payload)
    await fetchCategories()
    return data
  }

  async function updateCategory(payload) {
    const { data } = await apiClient.post('/editCategories.php', payload)
    await fetchCategories()
    return data
  }

  async function deleteCategory(categoriesId) {
    const { data } = await apiClient.post('/removeCategories.php', { categoriesId })
    await fetchCategories()
    return data
  }

  return { items, loading, fetchCategories, createCategory, updateCategory, deleteCategory }
})
