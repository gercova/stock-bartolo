import { defineStore } from 'pinia'
import { ref } from 'vue'

let nextToastId = 1

export const useUiStore = defineStore('ui', () => {
  const sidebarCollapsed = ref(false)
  const mobileMenuOpen = ref(false)
  const toasts = ref([])

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function openMobileMenu() {
    mobileMenuOpen.value = true
  }

  function closeMobileMenu() {
    mobileMenuOpen.value = false
  }

  function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value
  }

  function removeToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function notify({ type = 'info', title = '', message = '', duration = 4000 }) {
    const id = nextToastId++
    toasts.value.push({ id, type, title, message })
    if (duration > 0) {
      setTimeout(() => removeToast(id), duration)
    }
    return id
  }

  function success(message, title = 'Operación exitosa') {
    return notify({ type: 'success', title, message })
  }

  function error(message, title = 'Error') {
    return notify({ type: 'error', title, message, duration: 5500 })
  }

  function warning(message, title = 'Atención') {
    return notify({ type: 'warning', title, message })
  }

  function info(message, title = 'Información') {
    return notify({ type: 'info', title, message })
  }

  return {
    sidebarCollapsed,
    mobileMenuOpen,
    toasts,
    toggleSidebar,
    openMobileMenu,
    closeMobileMenu,
    toggleMobileMenu,
    removeToast,
    notify,
    success,
    error,
    warning,
    info,
  }
})
