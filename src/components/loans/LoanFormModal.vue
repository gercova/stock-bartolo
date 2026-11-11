<template>
  <BaseModal
    :open="open"
    :title="loan ? `Editar préstamo ${loan.loan_code}` : 'Emitir nuevo préstamo'"
    :subtitle="loan ? 'Modifica las condiciones o datos del cliente' : 'Registra un préstamo a cliente con interés opcional'"
    size="lg"
    @close="$emit('close')"
  >
    <form id="loanForm" class="space-y-5" @submit.prevent="handleSubmit">
      <!-- Client Information -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombre del cliente *</label>
          <input
            v-model.trim="form.clientName"
            type="text"
            maxlength="200"
            required
            placeholder="Nombre completo del cliente"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono / Contacto *</label>
          <input
            v-model.trim="form.clientContact"
            type="text"
            maxlength="100"
            required
            placeholder="Ej. +503 7000-0000"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Documento / Identificación</label>
          <input
            v-model.trim="form.clientDocument"
            type="text"
            maxlength="100"
            placeholder="DUI, NIT o cédula (opcional)"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Orden asociada (opcional)</label>
          <select
            v-model="form.orderId"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option :value="null">Préstamo independiente</option>
            <option v-for="o in orders" :key="o.order_id" :value="o.order_id">
              Orden #{{ o.order_id }} — {{ o.client_name }} ({{ formatCurrency(o.grand_total) }})
            </option>
          </select>
        </div>
      </div>

      <!-- Dates & Principal -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Monto a prestar ($) *</label>
          <input
            v-model.number="form.principalAmount"
            type="number"
            step="0.01"
            min="0.01"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de emisión *</label>
          <input
            v-model="form.loanDate"
            type="date"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha de vencimiento</label>
          <input
            v-model="form.dueDate"
            type="date"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </div>

      <!-- Optional Interest Switch & Settings -->
      <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-semibold text-slate-900">¿Incluir interés en este préstamo?</p>
            <p class="text-xs text-slate-500">
              Activa esta opción para aplicar una tasa de interés al monto prestado, o déjala desactivada para préstamo sin interés (0%).
            </p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="form.hasInterest"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
            :class="form.hasInterest ? 'bg-indigo-600' : 'bg-slate-300'"
            @click="form.hasInterest = !form.hasInterest"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
              :class="form.hasInterest ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>

        <div v-if="form.hasInterest" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-200">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Tasa de interés (%) *</label>
            <input
              v-model.number="form.interestRate"
              type="number"
              step="0.01"
              min="0.01"
              max="1000"
              required
              placeholder="Ej. 10"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Modo de cálculo</label>
            <select
              v-model="form.interestMode"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option value="flat">Porcentaje único sobre capital</option>
              <option value="monthly">Mensual simple (tasa × plazo)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Plazo (meses / cuotas)</label>
            <input
              v-model.number="form.termMonths"
              type="number"
              min="1"
              max="360"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            />
          </div>
        </div>
      </div>

      <!-- Optional Initial Payment (Create Only) -->
      <div v-if="!loan" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Abono inicial ($) (opcional)</label>
          <input
            v-model.number="form.initialPayment"
            type="number"
            step="0.01"
            min="0"
            :max="preview.totalAmount"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Método de abono inicial</label>
          <select
            v-model.number="form.paymentMethod"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option :value="2">Efectivo</option>
            <option :value="1">Cheque</option>
            <option :value="3">Tarjeta / Transferencia</option>
          </select>
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Notas / Observaciones</label>
        <textarea
          v-model.trim="form.notes"
          rows="2"
          maxlength="1000"
          placeholder="Condiciones de pago, garantías o comentarios adicionales..."
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        ></textarea>
      </div>

      <!-- Live Calculation Summary -->
      <div class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold uppercase tracking-wider text-indigo-800">
            Resumen financiero del préstamo
          </span>
          <span class="text-xs font-medium text-indigo-700">
            {{ form.hasInterest ? `Con interés (${form.interestRate || 0}%)` : 'Sin interés (0%)' }}
          </span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
          <div>
            <p class="text-xs text-indigo-700/80">Capital</p>
            <p class="font-bold text-slate-900">{{ formatCurrency(preview.principal) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-700/80">Interés</p>
            <p class="font-bold text-indigo-700">{{ formatCurrency(preview.interestAmount) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-700/80">Total a pagar</p>
            <p class="font-extrabold text-slate-900">{{ formatCurrency(preview.totalAmount) }}</p>
          </div>
          <div>
            <p class="text-xs text-indigo-700/80">Cuota est. ({{ preview.term }}m)</p>
            <p class="font-bold text-slate-900">{{ formatCurrency(preview.installmentAmount) }}</p>
          </div>
        </div>
      </div>
    </form>

    <template #footer>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
        @click="$emit('close')"
      >
        Cancelar
      </button>
      <button
        type="submit"
        form="loanForm"
        :disabled="saving"
        class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        {{ loan ? 'Guardar cambios' : 'Emitir préstamo' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import { calculateLoanPreview } from '@/stores/loans'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'
import { useUiStore } from '@/stores/ui'
import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  loan: { type: Object, default: null },
  orders: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'submit'])
const ui = useUiStore()

const form = reactive({
  clientName: '',
  clientContact: '',
  clientDocument: '',
  orderId: null,
  loanDate: new Date().toISOString().slice(0, 10),
  dueDate: '',
  principalAmount: 100,
  hasInterest: false,
  interestRate: 10,
  interestMode: 'flat',
  termMonths: 1,
  initialPayment: 0,
  paymentMethod: 2,
  notes: '',
})

watch(
  () => [props.open, props.loan],
  () => {
    if (!props.open) return
    if (props.loan) {
      form.clientName = props.loan.client_name
      form.clientContact = props.loan.client_contact
      form.clientDocument = props.loan.client_document || ''
      form.orderId = props.loan.order_id ?? null
      form.loanDate = props.loan.loan_date
      form.dueDate = props.loan.due_date || ''
      form.principalAmount = props.loan.principal_amount
      form.hasInterest = Boolean(props.loan.has_interest)
      form.interestRate = props.loan.interest_rate || 10
      form.interestMode = props.loan.interest_mode || 'flat'
      form.termMonths = props.loan.term_months || 1
      form.initialPayment = 0
      form.notes = props.loan.notes || ''
    } else {
      form.clientName = ''
      form.clientContact = ''
      form.clientDocument = ''
      form.orderId = null
      form.loanDate = new Date().toISOString().slice(0, 10)
      form.dueDate = ''
      form.principalAmount = 100
      form.hasInterest = false
      form.interestRate = 10
      form.interestMode = 'flat'
      form.termMonths = 1
      form.initialPayment = 0
      form.paymentMethod = 2
      form.notes = ''
    }
  },
  { immediate: true }
)

const preview = computed(() =>
  calculateLoanPreview({
    principalAmount: form.principalAmount,
    hasInterest: form.hasInterest,
    interestRate: form.interestRate,
    interestMode: form.interestMode,
    termMonths: form.termMonths,
  })
)

function handleSubmit() {
  const unsafe = findUnsafeField({
    'Nombre del cliente': form.clientName,
    Teléfono: form.clientContact,
    Documento: form.clientDocument,
    Notas: form.notes,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }
  emit('submit', {
    ...(props.loan ? { loanId: props.loan.loan_id } : {}),
    clientName: form.clientName,
    clientContact: form.clientContact,
    clientDocument: form.clientDocument,
    orderId: form.orderId,
    loanDate: form.loanDate,
    dueDate: form.dueDate,
    principalAmount: form.principalAmount,
    hasInterest: form.hasInterest,
    interestRate: form.hasInterest ? form.interestRate : 0,
    interestMode: form.interestMode,
    termMonths: form.termMonths,
    initialPayment: form.initialPayment || 0,
    paymentMethod: form.paymentMethod,
    notes: form.notes,
  })
}
</script>
