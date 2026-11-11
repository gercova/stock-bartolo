<template>
  <BaseModal
    :open="open"
    :title="loan ? `Registrar abono — ${loan.loan_code}` : 'Registrar abono'"
    :subtitle="loan ? `${loan.client_name} · Saldo pendiente: ${formatCurrency(loan.balance_due)}` : ''"
    size="lg"
    @close="$emit('close')"
  >
    <div v-if="loan" class="space-y-5">
      <!-- Summary Strip -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-2xl bg-slate-50 border border-slate-200 p-3.5 text-xs">
        <div>
          <p class="text-slate-500">Capital</p>
          <p class="font-bold text-slate-900 text-sm">{{ formatCurrency(loan.principal_amount) }}</p>
        </div>
        <div>
          <p class="text-slate-500">Interés</p>
          <p class="font-bold text-indigo-700 text-sm">
            {{ loan.has_interest ? formatCurrency(loan.interest_amount) : '$0.00' }}
          </p>
        </div>
        <div>
          <p class="text-slate-500">Abonado</p>
          <p class="font-bold text-emerald-700 text-sm">{{ formatCurrency(loan.paid_amount) }}</p>
        </div>
        <div>
          <p class="text-slate-500">Saldo pendiente</p>
          <p class="font-extrabold text-amber-700 text-sm">{{ formatCurrency(loan.balance_due) }}</p>
        </div>
      </div>

      <!-- Payment Form -->
      <form
        v-if="loan.balance_due > 0"
        id="loanPaymentForm"
        class="space-y-4 rounded-2xl border border-slate-200 p-4"
        @submit.prevent="submitPayment"
      >
        <div class="flex items-center justify-between">
          <h4 class="text-sm font-bold text-slate-900">Nuevo abono</h4>
          <button
            type="button"
            class="text-xs font-semibold text-indigo-600 hover:underline"
            @click="form.amount = loan.balance_due"
          >
            Liquidar saldo completo ({{ formatCurrency(loan.balance_due) }})
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Monto ($) *</label>
            <input
              v-model.number="form.amount"
              type="number"
              step="0.01"
              min="0.01"
              :max="loan.balance_due"
              required
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Fecha de abono *</label>
            <input
              v-model="form.paymentDate"
              type="date"
              required
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Método de pago *</label>
            <select
              v-model.number="form.paymentMethod"
              required
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option :value="2">Efectivo</option>
              <option :value="1">Cheque</option>
              <option :value="3">Tarjeta / Transferencia</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Nota (opcional)</label>
          <input
            v-model.trim="form.notes"
            type="text"
            maxlength="255"
            placeholder="Ej. Cuota #1, pago parcial en efectivo..."
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </form>

      <div v-else class="rounded-xl bg-emerald-50 border border-emerald-200 p-3.5 text-sm text-emerald-800 font-medium">
        Este préstamo ha sido pagado en su totalidad.
      </div>

      <!-- Payment History -->
      <div>
        <h4 class="text-sm font-bold text-slate-900 mb-2.5">Historial de abonos</h4>
        <div v-if="loadingPayments" class="py-6 text-center text-sm text-slate-500">
          <Loader2 class="h-5 w-5 animate-spin mx-auto mb-1 text-indigo-600" />
          Cargando historial...
        </div>
        <div v-else-if="payments.length === 0" class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-500">
          Aún no se han registrado abonos para este préstamo.
        </div>
        <div v-else class="overflow-x-auto border border-slate-200 rounded-xl">
          <table class="w-full text-left text-xs sm:text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-3.5 py-2.5">Fecha</th>
                <th class="px-3.5 py-2.5">Método</th>
                <th class="px-3.5 py-2.5">Registrado por</th>
                <th class="px-3.5 py-2.5">Notas</th>
                <th class="px-3.5 py-2.5 text-right">Monto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in payments" :key="p.payment_id">
                <td class="px-3.5 py-2.5 text-slate-700">{{ formatDate(p.payment_date) }}</td>
                <td class="px-3.5 py-2.5 text-slate-600">{{ paymentMethodLabel(p.payment_method) }}</td>
                <td class="px-3.5 py-2.5 text-slate-600">{{ p.recorded_by }}</td>
                <td class="px-3.5 py-2.5 text-slate-500">{{ p.notes || '—' }}</td>
                <td class="px-3.5 py-2.5 text-right font-semibold text-emerald-700">
                  {{ formatCurrency(p.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
        @click="$emit('close')"
      >
        Cerrar
      </button>
      <button
        v-if="loan && loan.balance_due > 0"
        type="submit"
        form="loanPaymentForm"
        :disabled="saving"
        class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        Registrar abono
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import { formatCurrency, formatDate, paymentMethodLabel } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'
import { useUiStore } from '@/stores/ui'
import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  loan: { type: Object, default: null },
  payments: { type: Array, default: () => [] },
  loadingPayments: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'submit'])
const ui = useUiStore()

const form = reactive({
  amount: 0,
  paymentDate: new Date().toISOString().slice(0, 10),
  paymentMethod: 2,
  notes: '',
})

watch(
  () => [props.open, props.loan],
  () => {
    if (props.open && props.loan) {
      form.amount = props.loan.balance_due
      form.paymentDate = new Date().toISOString().slice(0, 10)
      form.paymentMethod = 2
      form.notes = ''
    }
  },
  { immediate: true }
)

function submitPayment() {
  if (!props.loan) return
  const unsafe = findUnsafeField({ Nota: form.notes })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }
  emit('submit', {
    loanId: props.loan.loan_id,
    amount: form.amount,
    paymentDate: form.paymentDate,
    paymentMethod: form.paymentMethod,
    notes: form.notes,
  })
}
</script>
