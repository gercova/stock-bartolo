<template>
  <BaseModal
    :open="open"
    title="Registrar movimiento de caja"
    :subtitle="session ? `${session.session_code} · ${session.register_name} — Esperado: ${formatCurrency(session.expected_cash)}` : ''"
    size="md"
    @close="$emit('close')"
  >
    <form id="cashMovForm" class="space-y-4" @submit.prevent="submitForm">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Tipo de movimiento *</label>
        <select
          v-model="form.movementType"
          required
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option value="manual_in">Ingreso manual de efectivo (+)</option>
          <option value="expense">Pago de gasto / compra menor (-)</option>
          <option value="manual_out">Retiro manual de efectivo (-)</option>
          <option value="sweep_to_general">Retiro parcial hacia Caja General (-)</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Monto ($) *</label>
        <input
          v-model.number="form.amount"
          type="number"
          step="0.01"
          min="0.01"
          required
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 flex items-center justify-between text-sm">
        <span class="text-slate-600">Efectivo esperado tras movimiento:</span>
        <span class="font-bold" :class="projectedExpected < 0 ? 'text-rose-600' : 'text-slate-900'">
          {{ formatCurrency(projectedExpected) }}
        </span>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Concepto / Descripción *</label>
        <textarea
          v-model.trim="form.description"
          rows="2"
          required
          maxlength="255"
          placeholder="Ej. Pago de mensajería, sencillo adicional, depósito parcial..."
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        ></textarea>
      </div>
    </form>

    <template #footer>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
        :disabled="saving"
        @click="$emit('close')"
      >
        Cancelar
      </button>
      <button
        type="submit"
        form="cashMovForm"
        :disabled="saving || projectedExpected < 0"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        Registrar movimiento
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { useCashRegisterStore } from '@/stores/cashRegister'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  session: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])
const store = useCashRegisterStore()
const ui = useUiStore()

const saving = ref(false)
const form = reactive({
  movementType: 'manual_in',
  amount: 0,
  description: '',
})

const isOutflow = computed(() => form.movementType !== 'manual_in')
const projectedExpected = computed(() => {
  const base = Number(props.session?.expected_cash || 0)
  const amt = Math.max(0, Number(form.amount) || 0)
  return Math.round((isOutflow.value ? base - amt : base + amt) * 100) / 100
})

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    form.movementType = 'manual_in'
    form.amount = 0
    form.description = ''
  }
)

async function submitForm() {
  if (!props.session) return
  const unsafe = findUnsafeField({ Descripción: form.description })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }

  saving.value = true
  try {
    const res = await store.addMovement({
      sessionId: props.session.session_id,
      movementType: form.movementType,
      amount: form.amount,
      description: form.description,
    })
    ui.success(res?.messages || 'Movimiento registrado.')
    emit('saved')
    emit('close')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>

