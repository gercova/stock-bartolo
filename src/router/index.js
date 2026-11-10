import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useErrorStore } from '@/stores/error'
import { RELEVANT_ERROR_CODES } from '@/constants/httpErrors'

import MainLayout from '@/layouts/MainLayout.vue'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import BrandsView from '@/views/BrandsView.vue'
import CategoriesView from '@/views/CategoriesView.vue'
import ProductsView from '@/views/ProductsView.vue'
import OrdersListView from '@/views/OrdersListView.vue'
import OrderFormView from '@/views/OrderFormView.vue'
import LoansView from '@/views/LoansView.vue'
import ReportsView from '@/views/ReportsView.vue'
import SettingsView from '@/views/SettingsView.vue'
import ErrorView from '@/views/ErrorView.vue'

const errorAliasRoutes = RELEVANT_ERROR_CODES.map((code) => ({
  path: `/${code}`,
  name: `http-error-${code}`,
  component: ErrorView,
  props: { code: String(code) },
  meta: {
    title: `Error ${code}`,
    public: true,
  },
}))

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { title: 'Iniciar sesión', guestOnly: true },
  },
  {
    path: '/',
    component: MainLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: DashboardView,
        meta: { title: 'Inicio', breadcrumb: ['Inicio'] },
      },
      {
        path: 'brands',
        name: 'brands',
        component: BrandsView,
        meta: { title: 'Fabricantes', breadcrumb: ['Inicio', 'Fabricantes'] },
      },
      {
        path: 'categories',
        name: 'categories',
        component: CategoriesView,
        meta: { title: 'Categorías', breadcrumb: ['Inicio', 'Categorías'] },
      },
      {
        path: 'products',
        name: 'products',
        component: ProductsView,
        meta: { title: 'Productos', breadcrumb: ['Inicio', 'Productos'] },
      },
      {
        path: 'orders',
        name: 'orders',
        component: OrdersListView,
        meta: { title: 'Órdenes', breadcrumb: ['Inicio', 'Órdenes'] },
      },
      {
        path: 'orders/create',
        name: 'order-create',
        component: OrderFormView,
        meta: { title: 'Nueva Orden', breadcrumb: ['Inicio', 'Órdenes', 'Agregar Orden'] },
      },
      {
        path: 'orders/:id/edit',
        name: 'order-edit',
        component: OrderFormView,
        props: true,
        meta: { title: 'Editar Orden', breadcrumb: ['Inicio', 'Órdenes', 'Editar Orden'] },
      },
      {
        path: 'loans',
        name: 'loans',
        component: LoansView,
        meta: { title: 'Préstamos', breadcrumb: ['Inicio', 'Préstamos'] },
      },
      {
        path: 'reports',
        name: 'reports',
        component: ReportsView,
        meta: { title: 'Reportes', breadcrumb: ['Inicio', 'Reportes'] },
      },
      {
        path: 'settings',
        name: 'settings',
        component: SettingsView,
        meta: { title: 'Configuración', breadcrumb: ['Inicio', 'Configuración'] },
      },
    ],
  },
  {
    path: '/error/:code(\\d+)',
    name: 'error-page',
    component: ErrorView,
    props: true,
    meta: { title: 'Error del sistema', public: true },
  },
  ...errorAliasRoutes,
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: ErrorView,
    props: { code: '404' },
    meta: { title: '404 — Página no encontrada', public: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const ui = useUiStore()
  ui.closeMobileMenu()

  if (to.meta.public || to.name === 'error-page' || to.name === 'not-found') {
    if (to.name === 'not-found') {
      const errorStore = useErrorStore()
      errorStore.setError({
        code: 404,
        message: `La ruta "${to.fullPath}" no existe en la aplicación.`,
        path: to.fullPath,
      })
    }
    return true
  }

  const auth = useAuthStore()
  if (!auth.checked) {
    await auth.checkAuth()
  }

  if (to.meta.requiresAuth && !auth.authenticated) {
    return {
      name: 'login',
      query: to.fullPath && to.fullPath !== '/dashboard' ? { redirect: to.fullPath } : undefined,
    }
  }

  if (to.meta.guestOnly && auth.authenticated) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  const baseTitle = 'Stock Bartolo'
  document.title = to.meta?.title ? `${to.meta.title} — ${baseTitle}` : baseTitle
})

export default router
