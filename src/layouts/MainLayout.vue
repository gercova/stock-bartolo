<template>
  <div class="min-h-screen flex bg-slate-50 text-slate-900">
    <!-- Mobile Drawer Backdrop -->
    <Transition name="fade">
      <div
        v-if="ui.mobileMenuOpen"
        class="no-print fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden"
        @click="ui.closeMobileMenu()"
      ></div>
    </Transition>

    <!-- Sidebar (Mobile Drawer + Desktop Fixed Rail) -->
    <aside
      class="no-print fixed inset-y-0 left-0 z-50 flex flex-col bg-slate-900 text-slate-100 transition-all duration-200 ease-out lg:static lg:translate-x-0"
      :class="[
        ui.mobileMenuOpen ? 'translate-x-0 visible' : '-translate-x-full invisible lg:visible',
        ui.sidebarCollapsed ? 'lg:w-20' : 'lg:w-64',
        'w-72'
      ]"
    >
      <!-- Brand Header -->
      <div class="flex h-16 items-center justify-between gap-3 border-b border-slate-800 px-4">
        <RouterLink to="/dashboard" class="flex items-center gap-3 min-w-0">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
            <Boxes class="h-5 w-5" />
          </div>
          <div v-if="!ui.sidebarCollapsed" class="min-w-0">
            <p class="text-sm font-bold tracking-tight text-white truncate">Stock Bartolo</p>
            <p class="text-[11px] text-slate-400 truncate">Inventario &amp; Crédito</p>
          </div>
        </RouterLink>

        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white lg:hidden"
          @click="ui.closeMobileMenu()"
          aria-label="Cerrar menú"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <!-- Navigation Groups -->
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <div v-for="group in navGroups" :key="group.label">
          <p
            v-if="!ui.sidebarCollapsed"
            class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400"
          >
            {{ group.label }}
          </p>
          <div class="space-y-1">
            <RouterLink
              v-for="item in group.items"
              :key="item.to"
              :to="item.to"
              class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
              :class="[
                isActive(item)
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-slate-300 hover:bg-slate-800/80 hover:text-white',
                ui.sidebarCollapsed ? 'lg:justify-center lg:px-2' : ''
              ]"
              :title="ui.sidebarCollapsed ? item.label : undefined"
            >
              <component :is="item.icon" class="h-5 w-5 shrink-0" />
              <span v-if="!ui.sidebarCollapsed" class="truncate">{{ item.label }}</span>
              <span
                v-if="!ui.sidebarCollapsed && item.badge"
                class="ml-auto rounded-full bg-indigo-500/20 px-2 py-0.5 text-[10px] font-semibold text-indigo-200"
              >
                {{ item.badge }}
              </span>
            </RouterLink>
          </div>
        </div>
      </nav>

      <!-- Sidebar Footer User Pill -->
      <div class="border-t border-slate-800 p-3">
        <div
          class="flex items-center gap-3 rounded-xl bg-slate-800/70 p-2.5"
          :class="ui.sidebarCollapsed ? 'lg:justify-center' : ''"
        >
          <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500/20 text-indigo-300 font-bold text-xs uppercase">
            {{ userInitials }}
          </div>
          <div v-if="!ui.sidebarCollapsed" class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-white truncate">{{ auth.user?.username || 'Usuario' }}</p>
            <p class="text-[11px] text-slate-400 truncate">Sesión activa</p>
          </div>
          <button
            v-if="!ui.sidebarCollapsed"
            type="button"
            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-700 hover:text-rose-300 transition"
            title="Cerrar sesión"
            @click="handleLogout"
          >
            <LogOut class="h-4 w-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Main Content Column -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Bar -->
      <header class="no-print sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white/90 backdrop-blur-md px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 min-w-0">
          <!-- Mobile Menu Toggle -->
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
            @click="ui.openMobileMenu()"
            aria-label="Abrir menú"
          >
            <Menu class="h-5 w-5" />
          </button>

          <!-- Desktop Collapse Toggle -->
          <button
            type="button"
            class="hidden lg:inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
            @click="ui.toggleSidebar()"
            :title="ui.sidebarCollapsed ? 'Expandir barra lateral' : 'Contraer barra lateral'"
          >
            <PanelLeftOpen v-if="ui.sidebarCollapsed" class="h-5 w-5" />
            <PanelLeftClose v-else class="h-5 w-5" />
          </button>

          <!-- Page Title & Breadcrumb -->
          <div class="min-w-0">
            <div class="hidden sm:flex items-center gap-1.5 text-xs text-slate-400">
              <span v-for="(crumb, idx) in breadcrumbs" :key="crumb" class="flex items-center gap-1.5">
                <span :class="idx === breadcrumbs.length - 1 ? 'text-slate-600 font-medium' : ''">
                  {{ crumb }}
                </span>
                <ChevronRight v-if="idx < breadcrumbs.length - 1" class="h-3 w-3" />
              </span>
            </div>
            <h1 class="text-base sm:text-lg font-bold text-slate-900 truncate">{{ pageTitle }}</h1>
          </div>
        </div>

        <!-- Quick Actions & User Menu -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
          <RouterLink
            to="/loans"
            class="hidden md:inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
          >
            <HandCoins class="h-4 w-4" />
            Préstamos
          </RouterLink>
          <RouterLink
            to="/orders/create"
            class="hidden sm:inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 shadow-xs transition"
          >
            <Plus class="h-4 w-4" />
            Nueva Orden
          </RouterLink>

          <!-- User Dropdown -->
          <div class="relative" ref="userMenuRef">
            <button
              type="button"
              class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
              @click="userMenuOpen = !userMenuOpen"
            >
              <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 uppercase">
                {{ userInitials }}
              </span>
              <span class="hidden sm:inline max-w-[110px] truncate">{{ auth.user?.username || 'Usuario' }}</span>
              <ChevronDown class="h-4 w-4 text-slate-400" />
            </button>

            <div
              v-if="userMenuOpen"
              class="absolute right-0 mt-2 w-52 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl ring-1 ring-black/5 z-50"
            >
              <div class="border-b border-slate-100 px-3.5 py-2">
                <p class="text-xs font-semibold text-slate-900 truncate">{{ auth.user?.username }}</p>
                <p class="text-[11px] text-slate-500">Administrador / Usuario</p>
              </div>
              <RouterLink
                to="/settings"
                class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="userMenuOpen = false"
              >
                <Settings class="h-4 w-4 text-slate-400" />
                Configuración
              </RouterLink>
              <button
                type="button"
                class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-sm text-rose-600 hover:bg-rose-50"
                @click="handleLogout"
              >
                <LogOut class="h-4 w-4" />
                Cerrar sesión
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Main Workspace Area -->
      <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <RouterView />
      </main>

      <!-- Footer -->
      <footer class="no-print border-t border-slate-200 bg-white px-4 sm:px-6 lg:px-8 py-3.5 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2">
        <p>Stock Bartolo — Sistema de Gestión de Inventario, Ventas y Préstamos</p>
        <p class="text-slate-400">SPA Vue 3 · Pinia</p>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import {
  Boxes,
  LayoutDashboard,
  Tag,
  FolderTree,
  Package,
  ShoppingCart,
  PlusCircle,
  HandCoins,
  BarChart3,
  Settings,
  LogOut,
  Menu,
  X,
  PanelLeftClose,
  PanelLeftOpen,
  ChevronRight,
  ChevronDown,
  Plus,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const userMenuOpen = ref(false)
const userMenuRef = ref(null)

const navGroups = [
  {
    label: 'Principal',
    items: [
      { label: 'Inicio', to: '/dashboard', icon: LayoutDashboard, match: ['/dashboard'] },
    ],
  },
  {
    label: 'Inventario',
    items: [
      { label: 'Fabricantes', to: '/brands', icon: Tag, match: ['/brands'] },
      { label: 'Categorías', to: '/categories', icon: FolderTree, match: ['/categories'] },
      { label: 'Productos', to: '/products', icon: Package, match: ['/products'] },
    ],
  },
  {
    label: 'Ventas y Préstamos',
    items: [
      { label: 'Órdenes', to: '/orders', icon: ShoppingCart, match: ['/orders'] },
      { label: 'Agregar Orden', to: '/orders/create', icon: PlusCircle, match: ['/orders/create'] },
      { label: 'Préstamos', to: '/loans', icon: HandCoins, match: ['/loans'], badge: 'Nuevo' },
      { label: 'Reportes', to: '/reports', icon: BarChart3, match: ['/reports'] },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { label: 'Configuración', to: '/settings', icon: Settings, match: ['/settings'] },
    ],
  },
]

const pageTitle = computed(() => route.meta?.title || 'Panel')
const breadcrumbs = computed(() => route.meta?.breadcrumb || ['Inicio'])

const userInitials = computed(() => {
  const name = auth.user?.username || 'U'
  return name.slice(0, 2).toUpperCase()
})

function isActive(item) {
  if (item.to === '/orders' && route.path.startsWith('/orders/') && route.path !== '/orders/create') {
    return true
  }
  return item.match?.includes(route.path) || route.path === item.to
}

async function handleLogout() {
  userMenuOpen.value = false
  await auth.logout()
  ui.info('Has cerrado tu sesión correctamente.', 'Sesión cerrada')
  router.push({ name: 'login' })
}

function handleClickOutside(event) {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    userMenuOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
