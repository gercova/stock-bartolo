<template>
  <div class="space-y-6">
    <!-- Top Welcome & Date Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
      <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">
          Panel General
        </h2>
        <p class="text-sm text-slate-500 mt-0.5">
          Resumen de inventario, ventas e historial de préstamos — {{ formattedToday }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <RouterLink
          to="/loans"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
        >
          <HandCoins class="h-4 w-4 text-indigo-600" />
          Emitir Préstamo
        </RouterLink>
        <RouterLink
          to="/orders/create"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition"
        >
          <Plus class="h-4 w-4" />
          Nueva Orden
        </RouterLink>
      </div>
    </div>

    <!-- Primary KPI Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <RouterLink
        to="/products"
        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-indigo-300 hover:shadow-md transition"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500">Total de productos</span>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
            <Package class="h-5 w-5" />
          </div>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.totalProducts }}</p>
        <p class="mt-1 text-xs text-slate-500 group-hover:text-indigo-600 transition">Ver catálogo →</p>
      </RouterLink>

      <RouterLink
        to="/orders"
        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-indigo-300 hover:shadow-md transition"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500">Total órdenes</span>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
            <ShoppingCart class="h-5 w-5" />
          </div>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.totalOrders }}</p>
        <p class="mt-1 text-xs text-slate-500">
          Ingresos: <span class="font-semibold text-slate-700">{{ formatCurrency(stats.totalRevenue) }}</span>
        </p>
      </RouterLink>

      <RouterLink
        to="/products"
        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-rose-300 hover:shadow-md transition"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500">Inventario bajo</span>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
            <AlertTriangle class="h-5 w-5" />
          </div>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.lowStockCount }}</p>
        <p class="mt-1 text-xs text-slate-500">Productos con stock ≤ 3</p>
      </RouterLink>

      <RouterLink
        to="/loans"
        class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-indigo-300 hover:shadow-md transition"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-slate-500">Préstamos activos</span>
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
            <HandCoins class="h-5 w-5" />
          </div>
        </div>
        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.loans.activeLoans }}</p>
        <p class="mt-1 text-xs text-slate-500">
          Por cobrar: <span class="font-semibold text-slate-700">{{ formatCurrency(stats.loans.totalPending) }}</span>
        </p>
      </RouterLink>
    </div>

    <!-- Financial & Calendar Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Financial Summary Cards -->
      <div class="space-y-4">
        <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-700 p-6 text-white shadow-md">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Ingresos por órdenes</span>
            <DollarSign class="h-5 w-5 text-indigo-200" />
          </div>
          <p class="mt-3 text-3xl font-extrabold">{{ formatCurrency(stats.totalRevenue) }}</p>
          <p class="mt-1.5 text-xs text-indigo-100">
            Saldo pendiente en órdenes: {{ formatCurrency(stats.totalOrderDue) }}
          </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900">Resumen de Préstamos</h3>
            <RouterLink to="/loans" class="text-xs font-semibold text-indigo-600 hover:underline">
              Gestionar
            </RouterLink>
          </div>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Capital prestado</p>
              <p class="font-bold text-slate-900 mt-0.5">{{ formatCurrency(stats.loans.totalPrincipal) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Interés generado</p>
              <p class="font-bold text-emerald-700 mt-0.5">{{ formatCurrency(stats.loans.totalInterest) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Recuperado</p>
              <p class="font-bold text-slate-900 mt-0.5">{{ formatCurrency(stats.loans.totalCollected) }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Vencidos</p>
              <p class="font-bold mt-0.5" :class="stats.loans.overdueLoans > 0 ? 'text-rose-600' : 'text-slate-900'">
                {{ stats.loans.overdueLoans }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Orders & Low Stock -->
      <div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Recent Orders -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs flex flex-col">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h3 class="text-sm font-bold text-slate-900">Órdenes recientes</h3>
            <RouterLink to="/orders" class="text-xs font-semibold text-indigo-600 hover:underline">
              Ver todas
            </RouterLink>
          </div>
          <div v-if="stats.recentOrders.length === 0" class="p-6 text-center text-sm text-slate-500">
            No hay órdenes registradas todavía.
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="order in stats.recentOrders"
              :key="order.order_id"
              class="flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-slate-50/70 transition"
            >
              <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900 truncate">{{ order.client_name }}</p>
                <p class="text-xs text-slate-500">{{ formatDate(order.order_date) }} · Orden #{{ order.order_id }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="text-sm font-bold text-slate-900">{{ formatCurrency(order.grand_total) }}</p>
                <StatusBadge
                  :variant="orderPaymentStatusMeta(order.payment_status).variant"
                  :label="orderPaymentStatusMeta(order.payment_status).label"
                />
              </div>
            </li>
          </ul>
        </div>

        <!-- Recent Loans & Low Stock -->
        <div class="space-y-6">
          <div class="rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
              <h3 class="text-sm font-bold text-slate-900">Préstamos recientes</h3>
              <RouterLink to="/loans" class="text-xs font-semibold text-indigo-600 hover:underline">
                Ver todos
              </RouterLink>
            </div>
            <div v-if="stats.loans.recent.length === 0" class="p-6 text-center text-sm text-slate-500">
              Aún no se han emitido préstamos.
            </div>
            <ul v-else class="divide-y divide-slate-100">
              <li
                v-for="loan in stats.loans.recent"
                :key="loan.loan_id"
                class="flex items-center justify-between gap-3 px-5 py-3.5"
              >
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-slate-900 truncate">{{ loan.client_name }}</p>
                  <p class="text-xs text-slate-500">
                    {{ loan.loan_code }} · {{ loan.has_interest ? `${loan.interest_rate}% interés` : 'Sin interés' }}
                  </p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-sm font-bold text-slate-900">{{ formatCurrency(loan.balance_due) }}</p>
                  <StatusBadge
                    :variant="loanStatusMeta(loan.loan_status).variant"
                    :label="loanStatusMeta(loan.loan_status).label"
                  />
                </div>
              </li>
            </ul>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
              <h3 class="text-sm font-bold text-slate-900">Alertas de stock bajo</h3>
              <span class="text-xs text-slate-400">≤ 3 unidades</span>
            </div>
            <div v-if="stats.lowStockProducts.length === 0" class="p-5 text-center text-sm text-emerald-700 bg-emerald-50/40 rounded-b-2xl">
              Todo el inventario tiene existencias suficientes.
            </div>
            <ul v-else class="divide-y divide-slate-100">
              <li
                v-for="p in stats.lowStockProducts"
                :key="p.product_id"
                class="flex items-center justify-between px-5 py-3 text-sm"
              >
                <span class="font-medium text-slate-800 truncate pr-3">{{ p.product_name }}</span>
                <StatusBadge variant="danger" :label="`${p.quantity} uds`" />
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import {
  Package,
  ShoppingCart,
  AlertTriangle,
  HandCoins,
  DollarSign,
  Plus,
} from '@lucide/vue'
import { useDashboardStore } from '@/stores/dashboard'
import { formatCurrency, formatDate, orderPaymentStatusMeta, loanStatusMeta } from '@/utils/formatters'
import StatusBadge from '@/components/common/StatusBadge.vue'

const dashboardStore = useDashboardStore()
const { stats } = storeToRefs(dashboardStore)

const formattedToday = computed(() =>
  new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
)

onMounted(() => {
  dashboardStore.fetchDashboard()
})
</script>
