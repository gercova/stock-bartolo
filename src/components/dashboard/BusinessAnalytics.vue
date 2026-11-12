<template>
  <div class="space-y-6">
    <!-- Filters & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs">
      <div>
        <h3 class="text-base sm:text-lg font-bold text-slate-900 flex items-center gap-2">
          <BarChart3 class="h-5 w-5 text-indigo-600" />
          Reporte Ejecutivo y Actividades del Negocio
        </h3>
        <p class="text-xs text-slate-500 mt-0.5">
          Visualización analítica de depósitos, retiros, comisiones, clientes destacados y actividad de sucursales.
        </p>
      </div>

      <!-- Controls: Year Selector & Branch Selector -->
      <div class="flex flex-wrap items-center gap-3">
        <!-- Branch Selector -->
        <div class="flex items-center gap-1.5">
          <label class="text-xs font-semibold text-slate-600 shrink-0">Sucursal:</label>
          <select
            v-model="selectedBranch"
            class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-none"
            @change="loadAnalytics"
          >
            <option value="all">Todas las sucursales</option>
            <option
              v-for="b in dashboardStore.analytics.branches"
              :key="b.branch_id"
              :value="String(b.branch_id)"
            >
              {{ b.branch_code }} · {{ b.branch_name }}
            </option>
          </select>
        </div>

        <!-- Year Selector -->
        <div class="flex items-center gap-1.5">
          <label class="text-xs font-semibold text-slate-600 shrink-0">Año:</label>
          <select
            v-model="selectedYear"
            class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-none"
            @change="loadAnalytics"
          >
            <option
              v-for="yr in dashboardStore.analytics.available_years"
              :key="yr"
              :value="yr"
            >
              {{ yr }}
            </option>
          </select>
        </div>

        <!-- Refresh Button -->
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
          :disabled="dashboardStore.analyticsLoading"
          @click="loadAnalytics"
        >
          <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': dashboardStore.analyticsLoading }" />
          Actualizar
        </button>
      </div>
    </div>

    <!-- Executive KPI Metrics Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
      <!-- Total Volume Processed -->
      <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50/80 to-white p-4 shadow-xs">
        <div class="flex items-center justify-between text-indigo-700">
          <span class="text-xs font-bold uppercase tracking-wider">Volumen Total</span>
          <Coins class="h-4 w-4" />
        </div>
        <p class="text-xl font-extrabold text-indigo-950 mt-1.5">
          S/ {{ Number(totals.total_volume_processed || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
        <p class="text-[11px] text-indigo-600 mt-1">
          {{ totals.total_ops || 0 }} operaciones procesadas
        </p>
      </div>

      <!-- Deposits Volume -->
      <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/80 to-white p-4 shadow-xs">
        <div class="flex items-center justify-between text-emerald-700">
          <span class="text-xs font-bold uppercase tracking-wider">Total Depósitos</span>
          <ArrowDownLeft class="h-4 w-4" />
        </div>
        <p class="text-xl font-extrabold text-emerald-950 mt-1.5">
          S/ {{ Number(totals.total_deposits_amount || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
        <p class="text-[11px] text-emerald-600 mt-1">
          {{ totals.deposit_count || 0 }} ingresos a bóveda
        </p>
      </div>

      <!-- Withdrawals Volume -->
      <div class="rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50/80 to-white p-4 shadow-xs">
        <div class="flex items-center justify-between text-rose-700">
          <span class="text-xs font-bold uppercase tracking-wider">Total Retiros</span>
          <ArrowUpRight class="h-4 w-4" />
        </div>
        <p class="text-xl font-extrabold text-rose-950 mt-1.5">
          S/ {{ Number(totals.total_withdrawals_amount || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
        <p class="text-[11px] text-rose-600 mt-1">
          {{ totals.withdrawal_count || 0 }} retiros dispensados
        </p>
      </div>

      <!-- Net Cash Flow -->
      <div class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50/80 to-white p-4 shadow-xs">
        <div class="flex items-center justify-between text-blue-700">
          <span class="text-xs font-bold uppercase tracking-wider">Flujo Neto</span>
          <TrendingUp class="h-4 w-4" />
        </div>
        <p
          class="text-xl font-extrabold mt-1.5"
          :class="totals.net_cash_flow >= 0 ? 'text-blue-950' : 'text-rose-700'"
        >
          {{ totals.net_cash_flow >= 0 ? '+' : '' }}S/ {{ Number(totals.net_cash_flow || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
        <p class="text-[11px] text-blue-600 mt-1">
          Depósitos vs Retiros
        </p>
      </div>

      <!-- Total Commissions -->
      <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/80 to-white p-4 shadow-xs">
        <div class="flex items-center justify-between text-amber-700">
          <span class="text-xs font-bold uppercase tracking-wider">Comisiones</span>
          <DollarSign class="h-4 w-4" />
        </div>
        <p class="text-xl font-extrabold text-amber-950 mt-1.5">
          S/ {{ Number(totals.total_commissions_amount || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
        </p>
        <p class="text-[11px] text-amber-700 mt-1">
          Ingreso por tarifa 1%
        </p>
      </div>

      <!-- Loyalty Points -->
      <div class="rounded-2xl border border-purple-200 bg-gradient-to-br from-purple-50/80 to-white p-4 shadow-xs">
        <div class="flex items-center justify-between text-purple-700">
          <span class="text-xs font-bold uppercase tracking-wider">Puntos Otorgados</span>
          <Award class="h-4 w-4" />
        </div>
        <p class="text-xl font-extrabold text-purple-950 mt-1.5">
          {{ Number(totals.total_points_earned || 0).toLocaleString() }} pts
        </p>
        <p class="text-[11px] text-purple-600 mt-1">
          {{ totals.total_clients_transacted || 0 }} clientes participantes
        </p>
      </div>
    </div>

    <!-- MAIN CHART ROW 1: Monthly Deposit and Withdrawal Amounts (Bar Chart) -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
        <div>
          <h4 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
            <BarChart2 class="h-4 w-4 text-indigo-600" />
            Montos de Depósitos y Retiros por Mes (Año {{ selectedYear }})
          </h4>
          <p class="text-xs text-slate-500">
            Comparativa visual de los montos totales (S/) ingresados por depósitos vs dispensados por retiros en cada mes.
          </p>
        </div>
        <div class="flex items-center gap-4 text-xs font-semibold">
          <span class="inline-flex items-center gap-1.5 text-emerald-700">
            <span class="h-3 w-3 rounded-sm bg-emerald-500"></span> Depósitos
          </span>
          <span class="inline-flex items-center gap-1.5 text-rose-700">
            <span class="h-3 w-3 rounded-sm bg-rose-500"></span> Retiros
          </span>
        </div>
      </div>

      <!-- Chart Canvas Wrapper -->
      <div class="relative h-72 sm:h-80 w-full">
        <canvas ref="monthlyChartRef"></canvas>
      </div>
    </div>

    <!-- MAIN CHART ROW 2: Pie & Donut Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Chart 2: Total Monetary Value Processed for Each Type of Operation (Pie Chart) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between space-y-4">
        <div class="border-b border-slate-100 pb-3">
          <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
            <PieChart class="h-4 w-4 text-indigo-600" />
            Valor Monetario Procesado por Tipo de Operación
          </h4>
          <p class="text-xs text-slate-500">
            Proporción porcentual y monto total procesado entre Depósitos y Retiros.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 items-center gap-4">
          <div class="relative h-56 w-full flex items-center justify-center">
            <canvas ref="operationTypeChartRef"></canvas>
          </div>
          <div class="space-y-3 text-xs">
            <div
              v-for="op in dashboardStore.analytics.operations_by_type"
              :key="op.type"
              class="rounded-xl border p-3 flex items-center justify-between"
              :class="op.type === 'deposit' ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40'"
            >
              <div class="space-y-0.5">
                <p class="font-bold text-slate-900 flex items-center gap-1.5">
                  <span
                    class="h-2.5 w-2.5 rounded-full"
                    :style="{ backgroundColor: op.color }"
                  ></span>
                  {{ op.label }}
                </p>
                <p class="text-slate-500 text-[11px]">{{ op.count }} transacciones</p>
              </div>
              <div class="text-right">
                <p class="font-extrabold text-sm" :class="op.type === 'deposit' ? 'text-emerald-700' : 'text-rose-700'">
                  S/ {{ Number(op.amount).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
                </p>
                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold shadow-xs">
                  {{ op.percentage }}%
                </span>
              </div>
            </div>

            <!-- Commission Info Pill -->
            <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 flex items-center justify-between">
              <div>
                <p class="font-bold text-amber-900">Comisiones Totales</p>
                <p class="text-[11px] text-amber-700">1 sol por cada 100 soles</p>
              </div>
              <p class="font-extrabold text-amber-800 text-sm">
                S/ {{ Number(totals.total_commissions_amount || 0).toFixed(2) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart 3: Branches with Highest Activity (Donut / Bar Chart) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
          <div>
            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Building2 class="h-4 w-4 text-indigo-600" />
              Sucursales con Mayor Actividad
            </h4>
            <p class="text-xs text-slate-500">
              Distribución de volumen transaccionado y nivel operativo por sucursal.
            </p>
          </div>
          <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
            {{ dashboardStore.analytics.branches_activity.length }} Sucursales
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 items-center gap-4">
          <div class="relative h-56 w-full flex items-center justify-center">
            <canvas ref="branchesChartRef"></canvas>
          </div>
          <div class="space-y-2.5 text-xs max-h-56 overflow-y-auto">
            <div
              v-for="b in dashboardStore.analytics.branches_activity"
              :key="b.branch_id"
              class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 flex items-center justify-between"
            >
              <div class="min-w-0 pr-2">
                <span class="font-mono font-bold text-[10px] text-indigo-700 block">{{ b.branch_code }}</span>
                <p class="font-bold text-slate-900 truncate">{{ b.branch_name }}</p>
                <p class="text-[10px] text-slate-500">{{ b.total_ops }} operaciones</p>
              </div>
              <div class="text-right shrink-0">
                <p class="font-bold text-slate-900">
                  S/ {{ Number(b.total_volume).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
                </p>
                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800">
                  {{ b.percentage }}%
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MAIN CHART ROW 3: Loyalty & Frequency Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Chart 4: Clients with Highest Point Totals (Donut Chart) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between space-y-4">
        <div class="border-b border-slate-100 pb-3">
          <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
            <Award class="h-4 w-4 text-amber-600" />
            Clientes con Mayor Puntuación (Puntos de Fidelización)
          </h4>
          <p class="text-xs text-slate-500">
            Top clientes con más puntos acumulados de por vida y su participación en el total.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 items-center gap-4">
          <div class="relative h-56 w-full flex items-center justify-center">
            <canvas ref="topPointsChartRef"></canvas>
          </div>
          <div class="space-y-2 text-xs max-h-56 overflow-y-auto">
            <div
              v-for="(c, idx) in dashboardStore.analytics.top_clients_by_points.slice(0, 5)"
              :key="c.client_id"
              class="rounded-xl border border-amber-100 bg-amber-50/40 p-2.5 flex items-center justify-between"
            >
              <div class="min-w-0 pr-2">
                <div class="flex items-center gap-1.5">
                  <span
                    class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-bold"
                    :class="idx === 0 ? 'bg-amber-400 text-amber-950' : 'bg-slate-200 text-slate-700'"
                  >
                    {{ idx + 1 }}
                  </span>
                  <p class="font-bold text-slate-900 truncate">{{ c.name }}</p>
                </div>
                <p class="text-[10px] text-slate-500 pl-5.5">{{ c.document_type }}: {{ c.document_number }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="font-extrabold text-amber-800">{{ c.lifetime_points }} pts</p>
                <p class="text-[10px] text-slate-500">Saldo: {{ c.points_balance }} pts</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart 5: Clients with Most Frequent Transactions (Horizontal Bar Chart) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between space-y-4">
        <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
          <div>
            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <ArrowLeftRight class="h-4 w-4 text-indigo-600" />
              Clientes con Transacciones Más Frecuentes
            </h4>
            <p class="text-xs text-slate-500">
              Número de operaciones ejecutadas por cliente (Depósitos vs Retiros).
            </p>
          </div>
          <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
            Frecuencia (# Ops)
          </span>
        </div>

        <div class="relative h-60 w-full">
          <canvas ref="topFrequencyChartRef"></canvas>
        </div>
      </div>
    </div>

    <!-- Leaderboard Interactive Table of Top Clients -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
      <!-- Table Header & Filter Tabs -->
      <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h4 class="text-sm font-bold text-slate-900">
            Tabla Clasificatoria de Clientes Destacados
          </h4>
          <p class="text-xs text-slate-500">
            Información consolidada de los clientes con mayor impacto en el negocio.
          </p>
        </div>

        <!-- Table View Tabs -->
        <div class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 text-xs">
          <button
            type="button"
            class="rounded-lg px-3 py-1 font-semibold transition"
            :class="clientTableTab === 'volume' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="clientTableTab = 'volume'"
          >
            Mayor Volumen (S/)
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1 font-semibold transition"
            :class="clientTableTab === 'frequency' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="clientTableTab = 'frequency'"
          >
            Más Frecuentes
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1 font-semibold transition"
            :class="clientTableTab === 'points' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
            @click="clientTableTab = 'points'"
          >
            Mayor Puntuación
          </button>
        </div>
      </div>

      <!-- Table Content -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
            <tr>
              <th class="py-3 px-4 text-center">Posición</th>
              <th class="py-3 px-4">Cliente</th>
              <th class="py-3 px-4 text-center">Total Operaciones</th>
              <th class="py-3 px-4 text-right">Depósitos (S/)</th>
              <th class="py-3 px-4 text-right">Retiros (S/)</th>
              <th class="py-3 px-4 text-right">Volumen Total</th>
              <th class="py-3 px-4 text-center">Puntos Históricos</th>
              <th class="py-3 px-4 text-center">Saldo Canjeable</th>
              <th class="py-3 px-4">Última Operación</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="(c, idx) in activeClientsList"
              :key="c.client_id"
              class="hover:bg-slate-50/70 transition"
              :class="idx < 3 ? 'bg-amber-50/20' : ''"
            >
              <td class="py-3 px-4 text-center">
                <span
                  class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                  :class="
                    idx === 0
                      ? 'bg-amber-400 text-amber-950 font-black shadow-xs'
                      : idx === 1
                      ? 'bg-slate-300 text-slate-800 font-bold'
                      : idx === 2
                      ? 'bg-amber-700/30 text-amber-900 font-bold'
                      : 'bg-slate-100 text-slate-600'
                  "
                >
                  {{ idx + 1 }}
                </span>
              </td>
              <td class="py-3 px-4">
                <p class="font-bold text-slate-900">{{ c.name }}</p>
                <p class="text-[11px] text-slate-500">{{ c.document_type }}: {{ c.document_number }} {{ c.phone ? '· ' + c.phone : '' }}</p>
              </td>
              <td class="py-3 px-4 text-center">
                <span class="inline-flex items-center gap-1 font-bold text-slate-900">
                  {{ c.total_ops }}
                </span>
                <span class="block text-[10px] text-slate-400">
                  {{ c.deposit_ops }} dep / {{ c.withdrawal_ops }} ret
                </span>
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700">
                S/ {{ Number(c.total_deposits || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-rose-700">
                S/ {{ Number(c.total_withdrawals || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right font-mono font-extrabold text-slate-900">
                S/ {{ Number(c.total_volume || 0).toLocaleString('es-PE', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-center font-mono font-black text-indigo-700">
                {{ c.lifetime_points }} pts
              </td>
              <td class="py-3 px-4 text-center font-mono font-bold text-amber-700">
                {{ c.points_balance }} pts
              </td>
              <td class="py-3 px-4 text-slate-500 text-[11px]">
                {{ c.last_operation_at || 'Sin operaciones' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
import {
  BarChart3,
  BarChart2,
  PieChart,
  Coins,
  ArrowDownLeft,
  ArrowUpRight,
  TrendingUp,
  DollarSign,
  Award,
  Building2,
  ArrowLeftRight,
  RefreshCw,
} from '@lucide/vue'
import { useDashboardStore } from '@/stores/dashboard'

const dashboardStore = useDashboardStore()

const selectedYear = ref(new Date().getFullYear())
const selectedBranch = ref('all')
const clientTableTab = ref('volume')

// Canvas Refs
const monthlyChartRef = ref(null)
const operationTypeChartRef = ref(null)
const branchesChartRef = ref(null)
const topPointsChartRef = ref(null)
const topFrequencyChartRef = ref(null)

// Chart Instances
let monthlyChart = null
let operationTypeChart = null
let branchesChart = null
let topPointsChart = null
let topFrequencyChart = null

const totals = computed(() => dashboardStore.analytics.totals || {})

const activeClientsList = computed(() => {
  if (clientTableTab.value === 'frequency') {
    return dashboardStore.analytics.top_clients_by_frequency || []
  }
  if (clientTableTab.value === 'points') {
    return dashboardStore.analytics.top_clients_by_points || []
  }
  return dashboardStore.analytics.top_clients_by_volume || []
})

async function loadAnalytics() {
  await dashboardStore.fetchBusinessAnalytics(selectedYear.value, selectedBranch.value)
  await nextTick()
  renderAllCharts()
}

function destroyAllCharts() {
  if (monthlyChart) { monthlyChart.destroy(); monthlyChart = null; }
  if (operationTypeChart) { operationTypeChart.destroy(); operationTypeChart = null; }
  if (branchesChart) { branchesChart.destroy(); branchesChart = null; }
  if (topPointsChart) { topPointsChart.destroy(); topPointsChart = null; }
  if (topFrequencyChart) { topFrequencyChart.destroy(); topFrequencyChart = null; }
}

function renderAllCharts() {
  destroyAllCharts()
  renderMonthlyChart()
  renderOperationTypeChart()
  renderBranchesChart()
  renderTopPointsChart()
  renderTopFrequencyChart()
}

// 1. Bar Chart: Monthly Deposits vs Withdrawals
function renderMonthlyChart() {
  if (!monthlyChartRef.value) return
  const monthly = dashboardStore.analytics.monthly || []
  const labels = monthly.map((m) => m.month_name)
  const depositData = monthly.map((m) => m.deposit_amount)
  const withdrawalData = monthly.map((m) => m.withdrawal_amount)

  monthlyChart = new Chart(monthlyChartRef.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Depósitos (S/)',
          data: depositData,
          backgroundColor: '#10b981',
          hoverBackgroundColor: '#059669',
          borderRadius: 6,
          barPercentage: 0.7,
        },
        {
          label: 'Retiros (S/)',
          data: withdrawalData,
          backgroundColor: '#f43f5e',
          hoverBackgroundColor: '#e11d48',
          borderRadius: 6,
          barPercentage: 0.7,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label(ctx) {
              const val = ctx.raw || 0
              return ` ${ctx.dataset.label}: S/ ${Number(val).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback(val) {
              return `S/ ${val}`
            },
          },
          grid: {
            color: '#f1f5f9',
          },
        },
        x: {
          grid: {
            display: false,
          },
        },
      },
    },
  })
}

// 2. Pie Chart: Total Monetary Value by Operation Type
function renderOperationTypeChart() {
  if (!operationTypeChartRef.value) return
  const ops = dashboardStore.analytics.operations_by_type || []
  const labels = ops.map((o) => o.label)
  const data = ops.map((o) => o.amount)
  const colors = ops.map((o) => o.color)

  operationTypeChart = new Chart(operationTypeChartRef.value, {
    type: 'pie',
    data: {
      labels,
      datasets: [
        {
          data,
          backgroundColor: colors,
          borderWidth: 2,
          borderColor: '#ffffff',
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 12,
            font: { size: 11 },
          },
        },
        tooltip: {
          callbacks: {
            label(ctx) {
              const val = ctx.raw || 0
              return ` ${ctx.label}: S/ ${Number(val).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`
            },
          },
        },
      },
    },
  })
}

// 3. Donut Chart: Branches with Highest Activity
function renderBranchesChart() {
  if (!branchesChartRef.value) return
  const branches = dashboardStore.analytics.branches_activity || []
  const labels = branches.map((b) => b.branch_name)
  const data = branches.map((b) => b.total_volume)
  const colors = [
    '#4f46e5', // indigo-600
    '#0ea5e9', // sky-500
    '#10b981', // emerald-500
    '#f59e0b', // amber-500
    '#ec4899', // pink-500
    '#8b5cf6', // violet-500
  ]

  branchesChart = new Chart(branchesChartRef.value, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [
        {
          data,
          backgroundColor: colors.slice(0, Math.max(labels.length, 1)),
          borderWidth: 2,
          borderColor: '#ffffff',
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            boxWidth: 10,
            font: { size: 11 },
          },
        },
        tooltip: {
          callbacks: {
            label(ctx) {
              const val = ctx.raw || 0
              return ` ${ctx.label}: S/ ${Number(val).toLocaleString('es-PE', { minimumFractionDigits: 2 })}`
            },
          },
        },
      },
    },
  })
}

// 4. Donut Chart: Clients with Highest Point Totals
function renderTopPointsChart() {
  if (!topPointsChartRef.value) return
  const clients = (dashboardStore.analytics.top_clients_by_points || []).slice(0, 5)
  const labels = clients.map((c) => c.name)
  const data = clients.map((c) => c.lifetime_points)
  const colors = ['#f59e0b', '#4f46e5', '#10b981', '#06b6d4', '#8b5cf6']

  topPointsChart = new Chart(topPointsChartRef.value, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [
        {
          data,
          backgroundColor: colors.slice(0, Math.max(labels.length, 1)),
          borderWidth: 2,
          borderColor: '#ffffff',
          hoverOffset: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '65%',
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label(ctx) {
              const val = ctx.raw || 0
              return ` ${ctx.label}: ${Number(val).toLocaleString()} pts acumulados`
            },
          },
        },
      },
    },
  })
}

// 5. Horizontal Bar Chart: Clients with Most Frequent Transactions
function renderTopFrequencyChart() {
  if (!topFrequencyChartRef.value) return
  const clients = (dashboardStore.analytics.top_clients_by_frequency || []).slice(0, 6)
  const labels = clients.map((c) => {
    const parts = c.name.split(' ')
    return parts.length > 2 ? `${parts[0]} ${parts[1]}` : c.name
  })
  const depositData = clients.map((c) => c.deposit_ops)
  const withdrawalData = clients.map((c) => c.withdrawal_ops)

  topFrequencyChart = new Chart(topFrequencyChartRef.value, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Depósitos',
          data: depositData,
          backgroundColor: '#10b981',
          borderRadius: 4,
        },
        {
          label: 'Retiros',
          data: withdrawalData,
          backgroundColor: '#f43f5e',
          borderRadius: 4,
        },
      ],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: {
            boxWidth: 10,
            font: { size: 11 },
          },
        },
        tooltip: {
          callbacks: {
            label(ctx) {
              return ` ${ctx.dataset.label}: ${ctx.raw} operaciones`
            },
          },
        },
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            stepSize: 1,
            precision: 0,
          },
          grid: {
            color: '#f1f5f9',
          },
        },
        y: {
          grid: {
            display: false,
          },
        },
      },
    },
  })
}

onMounted(() => {
  loadAnalytics()
})

onBeforeUnmount(() => {
  destroyAllCharts()
})
</script>

