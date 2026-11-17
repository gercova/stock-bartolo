<template>
  <BaseModal
    :open="open"
    title="Ingreso de Capital Bancario"
    size="2xl"
    @close="handleClose"
  >
    <form id="bankCapitalDepositForm" class="space-y-4" @submit.prevent="handleSubmit">
      <!-- Info Header Banner -->
      <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3.5 flex items-start gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-white shrink-0 mt-0.5">
          <Landmark class="h-5 w-5" />
        </div>
        <div class="text-xs text-indigo-950 space-y-1">
          <p class="font-bold text-sm">Fondeo de Instituciones Bancarias (Agente Multibanco)</p>
          <p class="text-indigo-800 leading-relaxed">
            Registra cada entrada de capital a una entidad bancaria específica, indicando la agencia o sede del banco donde se efectuó el depósito para asegurar la trazabilidad y cuadre de operaciones de depósitos y retiros.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- 1. Banking Entity -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
            Entidad Bancaria *
          </label>
          <select
            v-model="form.bank_name"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:outline-hidden transition"
            required
            @change="onBankChange"
          >
            <option value="" disabled>-- Selecciona entidad bancaria --</option>
            <option v-for="b in bankList" :key="b.name" :value="b.name">
              {{ b.name }} — {{ b.fullName }}
            </option>
          </select>
          <div v-if="selectedBankMeta" class="mt-1 flex items-center justify-between text-[11px]">
            <span
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-bold border"
              :class="selectedBankMeta.badgeClass"
            >
              {{ selectedBankMeta.name }}
            </span>
            <span class="text-slate-600 font-medium">
              Saldo actual: <strong class="text-slate-900 font-mono">S/ {{ currentBankBalanceFormatted }}</strong>
            </span>
          </div>
        </div>

        <!-- 2. Business Branch -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
            Sucursal de la Empresa *
          </label>
          <select
            v-model="form.branch_id"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:outline-hidden transition"
            required
            :disabled="!canSwitchBranches"
          >
            <option v-for="br in branches" :key="br.branch_id" :value="br.branch_id">
              {{ br.branch_code }} · {{ br.branch_name }}
            </option>
          </select>
          <p class="text-[10px] text-slate-400 mt-1">Ubicación operativa a la que se adjudica este capital</p>
        </div>
      </div>

      <!-- 3. Specific Bank Branch / Agency where deposited (CORE REQUIREMENT) -->
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5 flex items-center gap-1.5">
          <Building2 class="h-3.5 w-3.5 text-indigo-600" />
          <span>Sucursal / Agencia Bancaria donde se depositó el capital *</span>
        </label>
        <div class="relative">
          <input
            v-model="form.bank_branch"
            type="text"
            required
            placeholder="Ej: Agencia BCP San Isidro - Av. Rivera Navarrete 501 / Ventanilla BN Centro"
            class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-hidden transition"
          />
          <MapPin class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>
        <p class="text-[11px] text-slate-500 mt-1">
          Especifique la sede, oficina, cajero o agencia de la institución financiera donde se realizó el abono para facilitar la auditoría de retiros y depósitos.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- 4. Amount to Deposit -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
            Monto a Ingresar / Depositar (S/) *
          </label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-xs text-slate-500">S/</span>
            <input
              v-model.number="form.amount"
              type="number"
              step="0.01"
              min="0.01"
              required
              placeholder="0.00"
              class="w-full rounded-xl border border-slate-300 bg-white pl-8 pr-3 py-2 text-sm font-bold font-mono text-emerald-700 focus:border-indigo-500 focus:outline-hidden transition"
            />
          </div>
          <!-- Projection info -->
          <div v-if="form.amount > 0" class="mt-1.5 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-[11px] text-emerald-900 flex items-center justify-between font-mono">
            <span>Saldo resultante:</span>
            <strong>S/ {{ projectedBalanceFormatted }}</strong>
          </div>
        </div>

        <!-- 5. Operation / Voucher Number -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5 flex items-center gap-1.5">
            <Receipt class="h-3.5 w-3.5 text-slate-500" />
            <span>N° Operación / Voucher / Referencia</span>
          </label>
          <input
            v-model="form.voucher_number"
            type="text"
            placeholder="Ej: OP-982341, VCH-44512, TRF-00123"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-mono font-semibold text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-hidden transition"
          />
          <p class="text-[10px] text-slate-400 mt-1">Identificador del comprobante emitido por el banco</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- 6. Bank Account Number -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
            N° Cuenta Bancaria Destino
          </label>
          <input
            v-model="form.account_number"
            type="text"
            placeholder="Ej: 191-98765432-0-12"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-hidden transition"
          />
        </div>

        <!-- 7. Funding Source / Method -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
            Origen / Método de Fondos *
          </label>
          <select
            v-model="form.funding_source"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:outline-hidden transition"
            required
          >
            <option value="Efectivo en Ventanilla">Efectivo en Ventanilla Bancaria</option>
            <option value="Transferencia Interbancaria">Transferencia Interbancaria</option>
            <option value="Aporte de Capital / Socios">Aporte de Capital / Socios</option>
            <option value="Transferencia desde Caja General">Transferencia desde Caja General</option>
            <option value="Depósito en Agente">Depósito en Agente Corresponsal</option>
            <option value="Cheque de Gerencia">Cheque de Gerencia</option>
          </select>
        </div>
      </div>

      <!-- 8. Date of Deposit -->
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5 flex items-center gap-1.5">
          <Calendar class="h-3.5 w-3.5 text-slate-500" />
          <span>Fecha y Hora del Depósito Bancario</span>
        </label>
        <input
          v-model="form.deposit_date"
          type="datetime-local"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:outline-hidden transition"
        />
      </div>

      <!-- 9. Optional Deduction from General Cash -->
      <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2">
        <label class="flex items-start gap-2.5 cursor-pointer">
          <input
            v-model="form.deduct_from_general_cash"
            type="checkbox"
            class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
          />
          <div class="text-xs">
            <span class="font-bold text-slate-900">Descontar este monto de la Caja General de la sucursal</span>
            <p class="text-slate-500 text-[11px] mt-0.5">
              Si el efectivo salió físicamente de la bóveda de la sucursal para llevarlo al banco, marcar esta opción ajustará automáticamente el saldo de Caja General.
            </p>
          </div>
        </label>

        <div v-if="form.deduct_from_general_cash" class="pt-2 border-t border-slate-200 flex items-center justify-between text-xs">
          <span class="text-slate-600">Disponible en Caja General:</span>
          <span
            class="font-mono font-bold"
            :class="isGeneralCashSufficient ? 'text-slate-900' : 'text-rose-600'"
          >
            S/ {{ generalCashBalanceFormatted }}
          </span>
        </div>

        <div v-if="form.deduct_from_general_cash && !isGeneralCashSufficient" class="p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-[11px] flex items-center gap-2">
          <AlertTriangle class="h-4 w-4 shrink-0 text-rose-600" />
          <span>Saldo insuficiente en Caja General para fondear este monto.</span>
        </div>
      </div>

      <!-- 10. Notes -->
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
          Observaciones / Justificación de Auditoría
        </label>
        <textarea
          v-model="form.notes"
          rows="2"
          placeholder="Notas adicionales, motivo de la inyección de capital, responsable del traslado..."
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-hidden transition"
        ></textarea>
      </div>
    </form>

    <template #footer>
      <div class="flex items-center justify-end gap-3">
        <button
          type="button"
          class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
          :disabled="submitting"
          @click="handleClose"
        >
          Cancelar
        </button>
        <button
          type="submit"
          form="bankCapitalDepositForm"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs cursor-pointer"
          :disabled="submitting || (form.deduct_from_general_cash && !isGeneralCashSufficient)"
        >
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <Landmark v-else class="h-4 w-4" />
          <span>Registrar Depósito de Capital</span>
        </button>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import {
  Landmark,
  Building2,
  MapPin,
  Receipt,
  Calendar,
  AlertTriangle,
  Loader2,
} from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { BANK_ENTITIES, getBankEntity } from '@/utils/bankEntities'
import { formatCurrency } from '@/utils/formatters'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  initialBank: {
    type: String,
    default: 'BCP',
  },
  branches: {
    type: Array,
    default: () => [],
  },
  activeBranchId: {
    type: Number,
    default: 1,
  },
  canSwitchBranches: {
    type: Boolean,
    default: true,
  },
  accounts: {
    type: Array,
    default: () => [],
  },
  generalCashBalance: {
    type: Number,
    default: 0,
  },
  submitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'submit'])

const bankList = BANK_ENTITIES

function getNowIsoLocal() {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60000
  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
}

const form = reactive({
  bank_name: 'BCP',
  bank_branch: '',
  branch_id: 1,
  amount: '',
  voucher_number: '',
  account_number: '',
  funding_source: 'Efectivo en Ventanilla',
  deposit_date: getNowIsoLocal(),
  deduct_from_general_cash: false,
  notes: '',
})

const selectedBankMeta = computed(() => {
  return getBankEntity(form.bank_name)
})

const selectedAccount = computed(() => {
  return props.accounts.find((a) => a.bank_name === form.bank_name) || null
})

const currentBankBalance = computed(() => {
  return Number(selectedAccount.value?.current_balance || 0)
})

const currentBankBalanceFormatted = computed(() => {
  return currentBankBalance.value.toLocaleString('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
})

const projectedBalance = computed(() => {
  const amt = Number(form.amount || 0)
  return currentBankBalance.value + amt
})

const projectedBalanceFormatted = computed(() => {
  return projectedBalance.value.toLocaleString('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
})

const generalCashBalanceFormatted = computed(() => {
  return Number(props.generalCashBalance || 0).toLocaleString('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
})

const isGeneralCashSufficient = computed(() => {
  if (!form.deduct_from_general_cash) return true
  const amt = Number(form.amount || 0)
  return amt <= Number(props.generalCashBalance || 0) + 0.0001
})

function onBankChange() {
  if (selectedAccount.value?.account_number && !form.account_number) {
    form.account_number = selectedAccount.value.account_number
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      form.bank_name = props.initialBank || 'BCP'
      form.branch_id = props.activeBranchId || 1
      form.amount = ''
      form.voucher_number = ''
      form.bank_branch = ''
      form.funding_source = 'Efectivo en Ventanilla'
      form.deposit_date = getNowIsoLocal()
      form.deduct_from_general_cash = false
      form.notes = ''
      if (selectedAccount.value?.account_number) {
        form.account_number = selectedAccount.value.account_number
      } else {
        form.account_number = ''
      }
    }
  }
)

watch(
  () => form.funding_source,
  (src) => {
    if (src === 'Transferencia desde Caja General') {
      form.deduct_from_general_cash = true
    }
  }
)

function handleClose() {
  if (!props.submitting) {
    emit('close')
  }
}

function handleSubmit() {
  emit('submit', { ...form })
}
</script>
