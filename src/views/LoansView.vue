<template>
  <div class="space-y-6">
    <!-- Summary KPI Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-500">Total capital prestado</span>
          <HandCoins class="h-4 w-4 text-indigo-600" />
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(summary.totalPrincipal) }}</p>
        <p class="mt-1 text-xs text-slate-500">
          {{ summary.totalLoans }} préstamos ({{ summary.withInterestCount }} con interés · {{ summary.withoutInterestCount }} sin interés)
        </p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-500">Intereses generados</span>
          <Percent class="h-4 w-4 text-emerald-600" />
        </div>
        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ formatCurrency(summary.totalInterest) }}</p>
        <p class="mt-1 text-xs text-slate-500">Total esperado: {{ formatCurrency(summary.totalAmount) }}</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-500">Saldo por recuperar</span>
          <Clock class="h-4 w-4 text-amber-600" />
        </div>
        <p class="mt-2 text-2xl font-bold text-amber-700">{{ formatCurrency(summary.totalPending) }}</p>
        <p class="mt-1 text-xs text-slate-500">Recuperado: {{ formatCurrency(summary.totalCollected) }}</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-500">Estado de cartera</span>
          <AlertCircle class="h-4 w-4 text-rose-600" />
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ summary.activeLoans }} activos</p>
        <p class="mt-1 text-xs" :class="summary.overdueLoans > 0 ? 'text-rose-600 font-semibold' : 'text-slate-500'">
          {{ summary.overdueLoans }} vencidos · {{ summary.paidLoans }} liquidados
        </p>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
      <div class="flex flex-col sm:flex-row flex-wrap gap-3 flex-1">
        <div class="relative flex-1 min-w-[200px] max-w-sm">
          <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            v-model.trim="search"
            type="text"
            placeholder="Buscar cliente, código o teléfono..."
            class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        >
          <option value="all">Todos los estados</option>
          <option value="1">Activos</option>
          <option value="2">En abonos</option>
          <option value="3">Pagados</option>
          <option value="4">Vencidos</option>
        </select>
        <select
          v-model="interestFilter"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        >
          <option value="all">Interés: Todos</option>
          <option value="with">Con interés</option>
          <option value="without">Sin interés</option>
        </select>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition shrink-0"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
        Emitir préstamo
      </button>
    </div>

    <!-- Loans Table & Mobile Cards -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
      <div v-if="loading && items.length === 0" class="p-10 text-center text-sm text-slate-500">
        <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
        Cargando préstamos...
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center space-y-2">
        <p class="text-sm text-slate-500">No hay préstamos que coincidan con el filtro actual.</p>
        <button
          type="button"
          class="text-xs font-semibold text-indigo-600 hover:underline"
          @click="openCreate"
        >
          + Emitir el primer préstamo
        </button>
      </div>

      <template v-else>
        <!-- Desktop Table -->
        <div class="hidden xl:block overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3.5">Código</th>
                <th class="px-4 py-3.5">Cliente</th>
                <th class="px-4 py-3.5">Emisión / Vence</th>
                <th class="px-4 py-3.5">Capital</th>
                <th class="px-4 py-3.5">Interés</th>
                <th class="px-4 py-3.5">Total</th>
                <th class="px-4 py-3.5">Saldo</th>
                <th class="px-4 py-3.5">Estado</th>
                <th class="px-4 py-3.5 text-right">Opciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="loan in filtered" :key="loan.loan_id" class="hover:bg-slate-50/70 transition">
                <td class="px-4 py-3.5">
                  <p class="font-mono text-xs font-bold text-slate-700">{{ loan.loan_code }}</p>
                  <p class="text-[11px] text-slate-400">Por: {{ loan.issued_by }}</p>
                </td>
                <td class="px-4 py-3.5">
                  <p class="font-semibold text-slate-900">{{ loan.client_name }}</p>
                  <p class="text-xs text-slate-500">{{ loan.client_contact }}</p>
                </td>
                <td class="px-4 py-3.5 text-xs text-slate-600">
                  <p>{{ formatDate(loan.loan_date) }}</p>
                  <p class="text-slate-400">Vence: {{ loan.due_date ? formatDate(loan.due_date) : '—' }}</p>
                </td>
                <td class="px-4 py-3.5 font-medium text-slate-900">{{ formatCurrency(loan.principal_amount) }}</td>
                <td class="px-4 py-3.5">
                  <StatusBadge
                    v-if="loan.has_interest"
                    variant="indigo"
                    :label="`${loan.interest_rate}% (${formatCurrency(loan.interest_amount)})`"
                  />
                  <StatusBadge v-else variant="neutral" label="Sin interés" />
                </td>
                <td class="px-4 py-3.5 font-semibold text-slate-900">{{ formatCurrency(loan.total_amount) }}</td>
                <td class="px-4 py-3.5 font-bold" :class="loan.balance_due > 0 ? 'text-amber-700' : 'text-emerald-700'">
                  {{ formatCurrency(loan.balance_due) }}
                </td>
                <td class="px-4 py-3.5">
                  <StatusBadge
                    :variant="loanStatusMeta(loan.loan_status).variant"
                    :label="loanStatusMeta(loan.loan_status).label"
                  />
                </td>
                <td class="px-4 py-3.5 text-right">
                  <div class="inline-flex items-center gap-1">
                    <button
                      type="button"
                      class="rounded-lg border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-100"
                      title="Ver detalle / imprimir"
                      @click="openDetail(loan)"
                    >
                      <Eye class="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 inline-flex items-center gap-1"
                      @click="openPayment(loan)"
                    >
                      <Wallet class="h-3.5 w-3.5" />
                      Abonar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-100"
                      title="Editar"
                      @click="openEdit(loan)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50"
                      title="Eliminar"
                      @click="deletingLoan = loan"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile & Tablet Cards -->
        <div class="xl:hidden divide-y divide-slate-100">
          <div v-for="loan in filtered" :key="loan.loan_id" class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-indigo-700">{{ loan.loan_code }}</span>
                  <StatusBadge
                    :variant="loanStatusMeta(loan.loan_status).variant"
                    :label="loanStatusMeta(loan.loan_status).label"
                  />
                </div>
                <p class="font-semibold text-slate-900 mt-1">{{ loan.client_name }}</p>
                <p class="text-xs text-slate-500">
                  {{ loan.client_contact }} · Emitido por {{ loan.issued_by }} ({{ formatDate(loan.loan_date) }})
                </p>
              </div>
              <StatusBadge
                v-if="loan.has_interest"
                variant="indigo"
                :label="`${loan.interest_rate}% int.`"
              />
              <StatusBadge v-else variant="neutral" label="0% int." />
            </div>

            <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs">
              <div>
                <p class="text-slate-500">Capital</p>
                <p class="font-semibold text-slate-900">{{ formatCurrency(loan.principal_amount) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Total</p>
                <p class="font-semibold text-slate-900">{{ formatCurrency(loan.total_amount) }}</p>
              </div>
              <div>
                <p class="text-slate-500">Saldo</p>
                <p class="font-bold" :class="loan.balance_due > 0 ? 'text-amber-700' : 'text-emerald-700'">
                  {{ formatCurrency(loan.balance_due) }}
                </p>
              </div>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="flex-1 rounded-lg border border-emerald-200 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 inline-flex items-center justify-center gap-1"
                @click="openPayment(loan)"
              >
                <Wallet class="h-3.5 w-3.5" /> Abonos
              </button>
              <button
                type="button"
                class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 inline-flex items-center justify-center gap-1"
                @click="openDetail(loan)"
              >
                <Eye class="h-3.5 w-3.5" /> Detalle
              </button>
              <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                @click="openEdit(loan)"
              >
                <Pencil class="h-3.5 w-3.5" />
              </button>
              <button
                type="button"
                class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-50"
                @click="deletingLoan = loan"
              >
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Form Modal -->
    <LoanFormModal
      :open="formOpen"
      :loan="editingLoan"
      :orders="ordersStore.items"
      :saving="savingForm"
      @close="formOpen = false"
      @submit="handleLoanSubmit"
    />

    <!-- Payment Modal -->
    <LoanPaymentModal
      :open="paymentOpen"
      :loan="selectedLoan"
      :payments="currentPayments"
      :loading-payments="loadingDetail"
      :saving="savingPayment"
      @close="paymentOpen = false"
      @submit="handlePaymentSubmit"
    />

    <!-- Detail / Print Modal -->
    <LoanDetailModal
      :open="detailOpen"
      :loan="selectedLoan"
      :payments="currentPayments"
      @close="detailOpen = false"
    />

    <!-- Delete Confirmation -->
    <ConfirmModal
      :open="Boolean(deletingLoan)"
      title="Eliminar préstamo"
      :message="`¿Realmente deseas eliminar el préstamo ${deletingLoan?.loan_code || ''} de ${deletingLoan?.client_name || ''}?`"
      :loading="deleting"
      @close="deletingLoan = null"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import {
  HandCoins,
  Percent,
  Clock,
  AlertCircle,
  Search,
  Plus,
  Eye,
  Wallet,
  Pencil,
  Trash2,
  Loader2,
} from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useLoansStore } from '@/stores/loans'
import { useOrdersStore } from '@/stores/orders'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { checkOpenCashSession, promptOpenCashRegister } from '@/utils/cashRegisterPrompt'
import { formatCurrency, formatDate, loanStatusMeta } from '@/utils/formatters'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'
import LoanFormModal from '@/components/loans/LoanFormModal.vue'
import LoanPaymentModal from '@/components/loans/LoanPaymentModal.vue'
import LoanDetailModal from '@/components/loans/LoanDetailModal.vue'

const router = useRouter()
const auth = useAuthStore()
const store = useLoansStore()
const ordersStore = useOrdersStore()
const ui = useUiStore()
const { items, summary, currentLoan, currentPayments, loading } = storeToRefs(store)

const search = ref('')
const statusFilter = ref('all')
const interestFilter = ref('all')

const formOpen = ref(false)
const editingLoan = ref(null)
const savingForm = ref(false)

const paymentOpen = ref(false)
const detailOpen = ref(false)
const selectedLoan = ref(null)
const loadingDetail = ref(false)
const savingPayment = ref(false)

const deletingLoan = ref(null)
const deleting = ref(false)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return items.value.filter((l) => {
    const matchesSearch =
      !q ||
      l.client_name.toLowerCase().includes(q) ||
      l.loan_code.toLowerCase().includes(q) ||
      l.client_contact.toLowerCase().includes(q)
    if (!matchesSearch) return false
    if (statusFilter.value !== 'all' && String(l.loan_status) !== statusFilter.value) return false
    if (interestFilter.value === 'with' && !l.has_interest) return false
    if (interestFilter.value === 'without' && l.has_interest) return false
    return true
  })
})

async function openCreate() {
  const cashStatus = await checkOpenCashSession()
  if (!cashStatus.isOpen) {
    await promptOpenCashRegister(router, auth.activeBranch?.branch_name || cashStatus.branchName, 'emisión de préstamos')
    return
  }
  editingLoan.value = null
  formOpen.value = true
}

function openEdit(loan) {
  editingLoan.value = loan
  formOpen.value = true
}

async function openPayment(loan) {
  const cashStatus = await checkOpenCashSession()
  if (!cashStatus.isOpen) {
    await promptOpenCashRegister(router, auth.activeBranch?.branch_name || cashStatus.branchName, 'abonos y cobros de préstamos')
    return
  }
  selectedLoan.value = loan
  paymentOpen.value = true
  loadingDetail.value = true
  try {
    await store.fetchLoanDetail(loan.loan_id)
    if (currentLoan.value) {
      selectedLoan.value = currentLoan.value
    }
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    loadingDetail.value = false
  }
}

async function openDetail(loan) {
  selectedLoan.value = loan
  detailOpen.value = true
  try {
    await store.fetchLoanDetail(loan.loan_id)
    if (currentLoan.value) {
      selectedLoan.value = currentLoan.value
    }
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

async function handleLoanSubmit(payload) {
  savingForm.value = true
  try {
    if (payload.loanId) {
      await store.updateLoan(payload)
      ui.success('Préstamo actualizado exitosamente.')
    } else {
      const res = await store.createLoan(payload)
      ui.success(`Préstamo ${res?.loan_code || ''} emitido exitosamente.`)
    }
    formOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingForm.value = false
  }
}

async function handlePaymentSubmit(payload) {
  savingPayment.value = true
  try {
    const res = await store.addPayment(payload)
    ui.success(res?.messages || 'Abono registrado exitosamente.')
    await store.fetchLoanDetail(payload.loanId)
    if (currentLoan.value) {
      selectedLoan.value = currentLoan.value
    }
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingPayment.value = false
  }
}

async function confirmDelete() {
  if (!deletingLoan.value) return
  deleting.value = true
  try {
    await store.deleteLoan(deletingLoan.value.loan_id)
    ui.success('Préstamo eliminado exitosamente.')
    deletingLoan.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  store.fetchLoans()
  ordersStore.fetchOrders()
})
</script>

