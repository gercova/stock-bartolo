<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
    @click.self="close"
  >
    <div class="relative w-full max-w-xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden my-8">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/70">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl font-bold shadow-xs transition"
            :class="form.operation_type === 'deposit' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
          >
            <ArrowDownLeft v-if="form.operation_type === 'deposit'" class="h-5 w-5" />
            <ArrowUpRight v-else class="h-5 w-5" />
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">
              Nueva Operación: {{ form.operation_type === 'deposit' ? 'Depósito' : 'Retiro' }}
            </h3>
            <p class="text-xs text-slate-500">
              Sucursal: <span class="font-semibold text-slate-700">{{ activeBranchName }}</span>
            </p>
          </div>
        </div>
        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
          @click="close"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <!-- Form Body -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-5">
        <!-- Central Cash Drawer Balance Banner -->
        <div
          class="flex items-center justify-between gap-3 rounded-xl p-3.5 border transition"
          :class="isInsufficientBalance ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-indigo-50/80 border-indigo-100 text-indigo-950'"
        >
          <div class="flex items-center gap-2.5">
            <Landmark class="h-5 w-5 shrink-0" :class="isInsufficientBalance ? 'text-rose-600' : 'text-indigo-600'" />
            <div class="text-xs">
              <p class="font-semibold text-slate-700">Saldo Disponible en Caja General:</p>
              <p class="text-base font-extrabold" :class="isInsufficientBalance ? 'text-rose-700' : 'text-indigo-700'">
                S/ {{ Number(centralCashBalance).toFixed(2) }}
              </p>
            </div>
          </div>
          <div v-if="isInsufficientBalance" class="text-right">
            <span class="inline-flex items-center gap-1 rounded-md bg-rose-200/70 px-2 py-0.5 text-[11px] font-bold text-rose-800">
              <AlertTriangle class="h-3.5 w-3.5" />
              Saldo Insuficiente
            </span>
            <p class="text-[11px] text-rose-600 mt-0.5">Se requiere S/ {{ Number(cashRequired).toFixed(2) }}</p>
          </div>
        </div>

        <!-- Operation Type Toggle -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
            Tipo de Operación *
          </label>
          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-bold border transition cursor-pointer"
              :class="
                form.operation_type === 'deposit'
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                  : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
              "
              @click="form.operation_type = 'deposit'"
            >
              <ArrowDownLeft class="h-4 w-4" />
              Depósito (Ingreso)
            </button>
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-bold border transition cursor-pointer"
              :class="
                form.operation_type === 'withdrawal'
                  ? 'bg-rose-600 text-white border-rose-600 shadow-sm'
                  : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
              "
              @click="form.operation_type = 'withdrawal'"
            >
              <ArrowUpRight class="h-4 w-4" />
              Retiro (Egreso)
            </button>
          </div>
        </div>

        <!-- Client Selector -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
              Cliente *
            </label>
            <span v-if="selectedClient" class="text-xs text-amber-600 font-semibold flex items-center gap-1">
              <Award class="h-3.5 w-3.5" />
              {{ selectedClient.points_balance || 0 }} pts disponibles
            </span>
          </div>

          <div class="relative">
            <input
              v-model="clientSearch"
              type="text"
              placeholder="Buscar por DNI, RUC, nombre o teléfono..."
              class="w-full rounded-xl border border-slate-300 pl-9 pr-8 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              @focus="showClientDropdown = true"
            />
            <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <button
              v-if="form.client_id"
              type="button"
              class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600"
              @click="clearClient"
            >
              <X class="h-4 w-4" />
            </button>

            <!-- Dropdown List -->
            <div
              v-if="showClientDropdown && filteredClients.length > 0"
              class="absolute left-0 right-0 z-30 mt-1 max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
            >
              <button
                v-for="c in filteredClients"
                :key="c.client_id"
                type="button"
                class="w-full px-3.5 py-2 text-left text-xs hover:bg-slate-50 flex items-center justify-between transition"
                @click="selectClient(c)"
              >
                <div>
                  <p class="font-bold text-slate-900">{{ c.name }}</p>
                  <p class="text-slate-500">{{ c.document_type }}: {{ c.document_number }} {{ c.phone ? '· ' + c.phone : '' }}</p>
                </div>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                  {{ c.points_balance || 0 }} pts
                </span>
              </button>
            </div>
          </div>
          <p v-if="selectedClient" class="mt-1.5 text-xs text-emerald-700 font-medium flex items-center gap-1">
            <Check class="h-3.5 w-3.5" />
            Cliente seleccionado: {{ selectedClient.name }} ({{ selectedClient.document_type }} {{ selectedClient.document_number }})
          </p>
        </div>

        <!-- Amount and Quick Chips -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">
            Monto de la Operación (S/) *
          </label>
          <div class="relative">
            <span class="absolute left-3.5 top-2.5 text-slate-400 font-bold text-sm">S/</span>
            <input
              v-model.number="form.amount"
              type="number"
              step="0.01"
              min="1"
              required
              placeholder="0.00"
              class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2.5 text-base font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <!-- Quick chips -->
          <div class="flex flex-wrap gap-1.5 mt-2">
            <button
              v-for="chip in [50, 100, 200, 500, 1000]"
              :key="chip"
              type="button"
              class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition"
              @click="form.amount = chip"
            >
              +S/ {{ chip }}
            </button>
          </div>
        </div>

        <!-- Checkbox: Commission Included -->
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="form.commission_included"
              type="checkbox"
              class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <div class="text-xs">
              <span class="font-bold text-slate-800">
                El monto ingresado ya incluye la comisión (1 sol por cada 100 soles)
              </span>
              <p class="text-slate-500 mt-0.5 leading-relaxed">
                <span v-if="form.commission_included">
                  Activo: La comisión se descontará del monto ingresado (monto neto = monto - comisión).
                </span>
                <span v-else>
                  Inactivo: La comisión se cobra de manera adicional al monto base.
                </span>
              </p>
            </div>
          </label>
        </div>

        <!-- Real-Time Calculation Card -->
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-2.5 text-xs">
          <div class="flex justify-between text-slate-600">
            <span>Monto Base Ingresado:</span>
            <span class="font-bold text-slate-800">S/ {{ Number(form.amount || 0).toFixed(2) }}</span>
          </div>

          <div class="flex justify-between text-slate-600">
            <span class="flex items-center gap-1">
              Comisión (S/ 1.00 por cada S/ 100.00):
            </span>
            <span class="font-bold text-amber-700">+S/ {{ Number(commissionAmount).toFixed(2) }}</span>
          </div>

          <div class="flex justify-between text-slate-600">
            <span>Monto Neto (Acreditar / Entregar):</span>
            <span class="font-bold text-slate-900">S/ {{ Number(netAmount).toFixed(2) }}</span>
          </div>

          <div class="pt-2 border-t border-indigo-100/80 flex justify-between items-center text-sm">
            <span class="font-bold text-slate-900">
              {{ form.operation_type === 'deposit' ? 'Total a Cobrar al Cliente:' : 'Total Efectivo a Entregar:' }}
            </span>
            <span class="text-base font-extrabold text-indigo-700">
              S/ {{ Number(totalCustomerAmount).toFixed(2) }}
            </span>
          </div>

          <!-- Points preview -->
          <div class="pt-2 border-t border-indigo-100/80 flex items-center justify-between text-xs">
            <span class="flex items-center gap-1.5 font-semibold text-amber-800">
              <Gift class="h-4 w-4 text-amber-600" />
              Puntos a otorgar al cliente:
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 font-bold text-amber-800">
              +{{ pointsEarned }} puntos
            </span>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">
            Notas u Observaciones (Opcional)
          </label>
          <input
            v-model.trim="form.notes"
            type="text"
            maxlength="255"
            placeholder="Ej. Transferencia BCP, retiro de emergencia, etc."
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
            @click="close"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="submitting || isInsufficientBalance || !form.client_id || form.amount <= 0"
            class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
            :class="form.operation_type === 'deposit' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'"
          >
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <span v-else>
              {{ form.operation_type === 'deposit' ? 'Registrar Depósito' : 'Registrar Retiro' }}
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import {
  X,
  ArrowDownLeft,
  ArrowUpRight,
  Landmark,
  Search,
  Check,
  Award,
  AlertTriangle,
  Gift,
  Loader2,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useClientsStore } from '@/stores/clients'
import { useOperationsStore } from '@/stores/operations'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'created'])

const auth = useAuthStore()
const clientsStore = useClientsStore()
const operationsStore = useOperationsStore()

const submitting = ref(false)
const clientSearch = ref('')
const showClientDropdown = ref(false)

const form = reactive({
  client_id: '',
  operation_type: 'deposit',
  amount: '',
  commission_included: false,
  notes: '',
})

const activeBranchName = computed(() => auth.activeBranch?.branch_name || 'Sucursal')
const centralCashBalance = computed(() => operationsStore.centralCashBalance || 0)

const commissionRatePer100 = computed(() => {
  return Number(operationsStore.settings?.commission_rate_per_100 || 1.0)
})

const commissionAmount = computed(() => {
  const amt = Number(form.amount || 0)
  if (amt <= 0) return 0
  return Math.round(((amt / 100.0) * commissionRatePer100.value) * 100) / 100
})

const netAmount = computed(() => {
  const amt = Number(form.amount || 0)
  if (amt <= 0) return 0
  if (form.commission_included) {
    return Math.max(0, Math.round((amt - commissionAmount.value) * 100) / 100)
  }
  return amt
})

const totalCustomerAmount = computed(() => {
  const amt = Number(form.amount || 0)
  if (amt <= 0) return 0
  if (form.operation_type === 'deposit') {
    return form.commission_included ? amt : Math.round((amt + commissionAmount.value) * 100) / 100
  } else {
    // Withdrawal
    return form.commission_included ? netAmount.value : amt
  }
})

const cashRequired = computed(() => {
  if (form.operation_type !== 'withdrawal') return 0
  return form.commission_included ? netAmount.value : Number(form.amount || 0)
})

const isInsufficientBalance = computed(() => {
  if (form.operation_type !== 'withdrawal') return false
  return cashRequired.value > Number(centralCashBalance.value || 0)
})

const pointsEarned = computed(() => {
  const amt = Number(form.amount || 0)
  const ratioSoles = Number(operationsStore.settings?.points_ratio_soles || 10)
  const ratioPoints = Number(operationsStore.settings?.points_ratio_points || 1)
  if (amt <= 0 || ratioSoles <= 0) return 0
  return Math.floor(amt / ratioSoles) * ratioPoints
})

const selectedClient = computed(() => {
  if (!form.client_id) return null
  return clientsStore.clients.find((c) => c.client_id === form.client_id) || null
})

const filteredClients = computed(() => {
  const q = clientSearch.value.toLowerCase().trim()
  if (!q) return clientsStore.clients.slice(0, 10)
  return clientsStore.clients
    .filter(
      (c) =>
        c.name.toLowerCase().includes(q) ||
        (c.document_number && c.document_number.includes(q)) ||
        (c.phone && c.phone.includes(q))
    )
    .slice(0, 10)
})

function selectClient(client) {
  form.client_id = client.client_id
  clientSearch.value = `${client.name} (${client.document_type} ${client.document_number})`
  showClientDropdown.value = false
}

function clearClient() {
  form.client_id = ''
  clientSearch.value = ''
}

function close() {
  emit('update:modelValue', false)
  resetForm()
}

function resetForm() {
  form.client_id = ''
  form.operation_type = 'deposit'
  form.amount = ''
  form.commission_included = false
  form.notes = ''
  clientSearch.value = ''
  showClientDropdown.value = false
}

async function handleSubmit() {
  if (isInsufficientBalance.value) return
  if (!form.client_id || Number(form.amount) <= 0) return

  submitting.value = true
  try {
    const res = await operationsStore.createOperation({
      client_id: form.client_id,
      operation_type: form.operation_type,
      amount: form.amount,
      commission_included: form.commission_included,
      notes: form.notes,
    })
    emit('created', res.operation)
    close()
  } catch (err) {
    // Parent or store handles error
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      if (clientsStore.clients.length === 0) {
        clientsStore.fetchClients()
      }
    }
  }
)
</script>

