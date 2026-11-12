<template>
  <div class="space-y-6">
    <!-- Filter Form -->
    <div class="no-print rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs">
      <h2 class="text-base font-bold text-slate-900 mb-4">Generar reporte por fechas</h2>
      <form class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end" @submit.prevent="generateReport">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha inicial *</label>
          <input
            v-model="startDate"
            type="date"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha final *</label>
          <input
            v-model="endDate"
            type="date"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div class="flex gap-2">
          <button
            type="submit"
            :disabled="loading"
            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 transition"
          >
            <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
            <BarChart3 v-else class="h-4 w-4" />
            Generar reporte
          </button>
          <button
            v-if="hasGenerated"
            type="button"
            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            @click="printReport"
          >
            <Printer class="h-4 w-4" />
            Imprimir
          </button>
        </div>
      </form>
    </div>
    <!-- Results -->
    <template v-if="hasGenerated">
      <!-- Summary KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
          <p class="text-xs font-medium text-slate-500">Órdenes en período</p>
          <p class="text-2xl font-bold text-slate-900 mt-1">{{ summary.count }}</p>
          <p class="text-xs text-slate-500 mt-1">Total: {{ formatCurrency(summary.totalAmount) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
          <p class="text-xs font-medium text-slate-500">Ingresos por órdenes</p>
          <p class="text-2xl font-bold text-emerald-700 mt-1">{{ formatCurrency(summary.totalPaid) }}</p>
          <p class="text-xs text-slate-500 mt-1">Pendiente: {{ formatCurrency(summary.totalDue) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
          <p class="text-xs font-medium text-slate-500">Préstamos emitidos</p>
          <p class="text-2xl font-bold text-slate-900 mt-1">{{ loanSummary.count }}</p>
          <p class="text-xs text-slate-500 mt-1">Capital: {{ formatCurrency(loanSummary.totalPrincipal) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
          <p class="text-xs font-medium text-slate-500">Interés / Saldo préstamos</p>
          <p class="text-2xl font-bold text-indigo-700 mt-1">{{ formatCurrency(loanSummary.totalInterest) }}</p>
          <p class="text-xs text-slate-500 mt-1">Por cobrar: {{ formatCurrency(loanSummary.totalBalance) }}</p>
        </div>
      </div>

      <!-- Orders Report Table -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
          <h3 class="text-sm font-bold text-slate-900">
            Órdenes ({{ formatDate(range.startDate) }} — {{ formatDate(range.endDate) }})
          </h3>
          <span class="text-xs font-semibold text-slate-600">Total: {{ formatCurrency(summary.totalAmount) }}</span>
        </div>

        <div v-if="orders.length === 0" class="p-8 text-center text-sm text-slate-500">
          No hay órdenes en el rango seleccionado.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-5 py-3">Fecha</th>
                <th class="px-5 py-3">Cliente</th>
                <th class="px-5 py-3">Teléfono</th>
                <th class="px-5 py-3 text-right">Total</th>
                <th class="px-5 py-3 text-right">Pagado</th>
                <th class="px-5 py-3 text-right">Saldo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="o in orders" :key="o.order_id">
                <td class="px-5 py-3 text-slate-600">{{ formatDate(o.order_date) }}</td>
                <td class="px-5 py-3 font-medium text-slate-900">{{ o.client_name }}</td>
                <td class="px-5 py-3 text-slate-600">{{ o.client_contact }}</td>
                <td class="px-5 py-3 text-right font-semibold">{{ formatCurrency(o.grand_total) }}</td>
                <td class="px-5 py-3 text-right text-emerald-700">{{ formatCurrency(o.paid) }}</td>
                <td class="px-5 py-3 text-right text-amber-700">{{ formatCurrency(o.due) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Loans Report Table -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
          <h3 class="text-sm font-bold text-slate-900">
            Préstamos emitidos en el período
          </h3>
          <span class="text-xs font-semibold text-slate-600">Total c/ interés: {{ formatCurrency(loanSummary.totalAmount) }}</span>
        </div>

        <div v-if="loans.length === 0" class="p-8 text-center text-sm text-slate-500">
          No hay préstamos emitidos en el rango seleccionado.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-5 py-3">Código</th>
                <th class="px-5 py-3">Fecha</th>
                <th class="px-5 py-3">Cliente</th>
                <th class="px-5 py-3 text-right">Capital</th>
                <th class="px-5 py-3 text-right">Interés</th>
                <th class="px-5 py-3 text-right">Total</th>
                <th class="px-5 py-3 text-right">Pendiente</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="l in loans" :key="l.loan_id">
                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ l.loan_code }}</td>
                <td class="px-5 py-3 text-slate-600">{{ formatDate(l.loan_date) }}</td>
                <td class="px-5 py-3 font-medium text-slate-900">{{ l.client_name }}</td>
                <td class="px-5 py-3 text-right">{{ formatCurrency(l.principal_amount) }}</td>
                <td class="px-5 py-3 text-right text-indigo-700">
                  {{ l.has_interest ? `${formatCurrency(l.interest_amount)} (${l.interest_rate}%)` : 'Sin interés' }}
                </td>
                <td class="px-5 py-3 text-right font-semibold">{{ formatCurrency(l.total_amount) }}</td>
                <td class="px-5 py-3 text-right font-medium text-amber-700">{{ formatCurrency(l.balance_due) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import { BarChart3, Printer, Loader2 } from '@lucide/vue'
import { useReportsStore } from '@/stores/reports'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency, formatDate } from '@/utils/formatters'

const store = useReportsStore()
const ui    = useUiStore()
const { orders, loans, summary, loanSummary, range, loading, hasGenerated } = storeToRefs(store)

const today     = new Date()
const firstDay  = new Date(today.getFullYear(), today.getMonth(), 1)
const startDate = ref(firstDay.toISOString().slice(0, 10))
const endDate   = ref(today.toISOString().slice(0, 10))

async function generateReport() {
  if (!startDate.value || !endDate.value) {
    ui.warning('Selecciona ambas fechas para generar el reporte.')
    return
  }
  try {
    await store.fetchReport(startDate.value, endDate.value)
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

function printReport() {
  window.print()
}
</script>
