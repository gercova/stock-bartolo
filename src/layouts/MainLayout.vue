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
            <p class="text-[11px] text-indigo-300 truncate">
              {{ auth.activeBranch ? `${auth.activeBranch.branch_code} · ${auth.activeBranch.branch_name}` : 'Inventario & Crédito' }}
            </p>
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

        <!-- Quick Actions, Branch Switcher & User Menu -->
        <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
          <!-- Branch Tenant Switcher Dropdown -->
          <div v-if="auth.activeBranch" class="relative" ref="branchMenuRef">
            <button
              type="button"
              class="flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50/70 px-2.5 py-1.5 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 transition"
              :disabled="!canSwitchBranch || auth.switchingBranch"
              :title="canSwitchBranch ? 'Cambiar sucursal activa (Multi-Tenant)' : 'Sucursal asignada'"
              aria-label="Selector de sucursal activa"
              @click="canSwitchBranch && (branchMenuOpen = !branchMenuOpen)"
            >
              <Store class="h-3.5 w-3.5 text-indigo-600 shrink-0" />
              <span class="max-w-[68px] sm:max-w-[135px] truncate">
                <span class="sm:hidden">{{ auth.activeBranch.branch_code }}</span>
                <span class="hidden sm:inline">{{ auth.activeBranch.branch_code }} · {{ auth.activeBranch.branch_name }}</span>
              </span>
              <ChevronDown v-if="canSwitchBranch" class="h-3.5 w-3.5 text-indigo-500 shrink-0" />
            </button>

            <div
              v-if="branchMenuOpen && canSwitchBranch"
              class="absolute right-0 mt-2 w-72 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl ring-1 ring-black/5 z-50"
            >
              <div class="border-b border-slate-100 px-3.5 py-2 flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                  Sucursales ({{ auth.branches.length }})
                </span>
                <span class="text-[11px] text-indigo-600 font-semibold">Multi-Tenant</span>
              </div>

              <div class="max-h-64 overflow-y-auto divide-y divide-slate-50">
                <button
                  v-for="b in auth.branches"
                  :key="b.branch_id"
                  type="button"
                  class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left hover:bg-slate-50 transition"
                  :class="b.branch_id === auth.activeBranch?.branch_id ? 'bg-indigo-50/60' : ''"
                  @click="handleSwitchBranch(b.branch_id)"
                >
                  <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">
                      {{ b.branch_code }} · {{ b.branch_name }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                      Caja Gen: {{ formatCurrency(b.general_cash_balance || 0, b.currency_code) }}
                    </p>
                  </div>
                  <Check
                    v-if="b.branch_id === auth.activeBranch?.branch_id"
                    class="h-4 w-4 text-indigo-600 shrink-0"
                  />
                </button>
              </div>

              <div class="border-t border-slate-100 pt-1">
                <RouterLink
                  to="/system-config?tab=branches"
                  class="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                  @click="branchMenuOpen = false"
                >
                  <Building2 class="h-3.5 w-3.5" />
                  Administrar sucursales y Caja General
                </RouterLink>
              </div>
            </div>
          </div>

          <RouterLink
            to="/cash-register"
            class="hidden lg:inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition"
          >
            <Calculator class="h-4 w-4" />
            Caja
          </RouterLink>
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
              class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl ring-1 ring-black/5 z-50"
            >
              <div class="border-b border-slate-100 px-3.5 py-2">
                <p class="text-xs font-semibold text-slate-900 truncate">{{ auth.user?.username }}</p>
                <p class="text-[11px] text-slate-500">
                  {{ auth.activeBranch ? auth.activeBranch.branch_name : 'Administrador' }}
                </p>
              </div>
              <RouterLink
                to="/system-config"
                class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="userMenuOpen = false"
              >
                <SlidersHorizontal class="h-4 w-4 text-slate-400" />
                Config. del Sistema
              </RouterLink>
              <RouterLink
                to="/security"
                class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="userMenuOpen = false"
              >
                <ShieldCheck class="h-4 w-4 text-slate-400" />
                Gestión de Seguridad
              </RouterLink>
              <RouterLink
                to="/settings"
                class="flex items-center gap-2.5 px-3.5 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="userMenuOpen = false"
              >
                <Settings class="h-4 w-4 text-slate-400" />
                Cuenta y Seguridad
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
        <RouterView :key="`${route.fullPath}::branch-${auth.activeBranch?.branch_id || 1}`" />
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
  Calculator,
  BarChart3,
  SlidersHorizontal,
  Settings,
  LogOut,
  Menu,
  X,
  PanelLeftClose,
  PanelLeftOpen,
  ChevronRight,
  ChevronDown,
  Plus,
  Store,
  Building2,
  Check,
  Users,
  ShieldCheck,
  ArrowLeftRight,
  PieChart,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const userMenuOpen = ref(false)
const userMenuRef = ref(null)
const branchMenuOpen = ref(false)
const branchMenuRef = ref(null)

const canSwitchBranch = computed(
  () => Boolean(auth.user?.can_switch_branches) && auth.branches.length > 0
)

const navGroups = [
  {
    label: 'Principal',
    items: [
      { label: 'Inicio', to: '/dashboard', icon: LayoutDashboard, match: ['/dashboard'] },
      { label: 'Analítica General', to: '/analytics', icon: PieChart, match: ['/analytics'], badge: 'Gráficos' },
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
    label: 'Ventas y Finanzas',
    items: [
      { label: 'Órdenes', to: '/orders', icon: ShoppingCart, match: ['/orders'] },
      { label: 'Agregar Orden', to: '/orders/create', icon: PlusCircle, match: ['/orders/create'] },
      { label: 'Caja', to: '/cash-register', icon: Calculator, match: ['/cash-register'], badge: 'Nuevo' },
      { label: 'Operaciones', to: '/operations', icon: ArrowLeftRight, match: ['/operations'], badge: 'Puntos' },
      { label: 'Préstamos', to: '/loans', icon: HandCoins, match: ['/loans'] },
      { label: 'Clientes y Prov.', to: '/clients', icon: Users, match: ['/clients'] },
      { label: 'Reportes', to: '/reports', icon: BarChart3, match: ['/reports'] },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { label: 'Config. del Sistema', to: '/system-config', icon: SlidersHorizontal, match: ['/system-config'] },
      { label: 'Gestión de Seguridad', to: '/security', icon: ShieldCheck, match: ['/security'] },
      { label: 'Cuenta y Seguridad', to: '/settings', icon: Settings, match: ['/settings'] },
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

async function handleSwitchBranch(branchId) {
  branchMenuOpen.value = false
  if (branchId === auth.activeBranch?.branch_id) return
  try {
    const res = await auth.switchBranch(branchId)
    ui.success(res?.messages || 'Sucursal cambiada correctamente.')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

async function handleLogout() {
  userMenuOpen.value = false
  branchMenuOpen.value = false
  await auth.logout()
  ui.info('Has cerrado tu sesión correctamente.', 'Sesión cerrada')
  router.push({ name: 'login' })
}

function handleClickOutside(event) {
  if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
    userMenuOpen.value = false
  }
  if (branchMenuRef.value && !branchMenuRef.value.contains(event.target)) {
    branchMenuOpen.value = false
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
