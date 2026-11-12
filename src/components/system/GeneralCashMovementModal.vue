<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar detalle del movimiento' : 'Registrar movimiento en Caja General'"
    :subtitle="branch ? `${branch.branch_code} · ${branch.branch_name} — Saldo actual: ${formatCurrency(currentBalance)}` : ''"
    size="md"
    @close="$emit('close')"
  >
    <form id="generalCashMovForm" class="space-y-4" @submit.prevent="submitForm">
      <template v-if="!isEdit">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Tipo de operación *</label>
          <select
            v-model="form.movementType"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option value="deposit">Ingreso / Aporte de capital operativo (+)</option>
            <option value="initial_capital">Dotación / Incremento de capital inicial (+)</option>
            <option value="withdrawal">Retiro de capital de Caja General (-)</option>
            <option value="adjustment_in">Ajuste positivo (+)</option>
            <option value="adjustment_out">Ajuste negativo (-)</option>
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
          <span class="text-slate-600">Saldo proyectado tras operación:</span>
          <span
            class="font-bold"
            :class="projectedBalance < 0 ? 'text-rose-600' : 'text-emerald-700'"
          >
            {{ formatCurrency(projectedBalance) }}
          </span>
        </div>
      </template>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Referencia / Comprobante</label>
        <input
          v-model.trim="form.reference"
          type="text"
          maxlength="100"
          placeholder="Ej. DEP-2026-01, Acta #04..."
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Concepto / Descripción *</label>
        <textarea
          v-model.trim="form.description"
          rows="2"
          required
          maxlength="255"
          placeholder="Describe el motivo del ingreso o retiro de efectivo..."
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
        form="generalCashMovForm"
        :disabled="saving || (!isEdit && projectedBalance < 0)"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        {{ isEdit ? 'Guardar cambios' : 'Registrar movimiento' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { useSystemConfigStore } from '@/stores/systemConfig'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField, sanitizeObject } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  branch: { type: Object, default: null },
  currentBalance: { type: Number, default: 0 },
  movement: { type: Object, default: null },
  defaultType: { type: String, default: 'deposit' },
})

const emit = defineEmits(['close', 'saved'])
const store = useSystemConfigStore()
const ui = useUiStore()

const isEdit = computed(() => Boolean(props.movement?.movement_id))
const saving = ref(false)

const form = reactive({
  movementType: 'deposit',
  amount: 0,
  reference: '',
  description: '',
})

const isOutflow = computed(() => ['withdrawal', 'adjustment_out'].includes(form.movementType))

const projectedBalance = computed(() => {
  const amt = Math.max(0, Number(form.amount) || 0)
  return Math.round((isOutflow.value ? props.currentBalance - amt : props.currentBalance + amt) * 100) / 100
})

watch(
  () => [props.open, props.movement, props.defaultType],
  () => {
    if (!props.open) return
    if (props.movement) {
      form.movementType = props.movement.movement_type
      form.amount = Number(props.movement.amount || 0)
      form.reference = props.movement.reference || ''
      form.description = props.movement.description || ''
    } else {
      form.movementType = props.defaultType || 'deposit'
      form.amount = 0
      form.reference = ''
      form.description = ''
    }
  },
  { immediate: true }
)

async function submitForm() {
  const unsafe = findUnsafeField({
    Referencia: form.reference,
    Descripción: form.description,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene caracteres o código no permitidos.`)
    return
  }

  saving.value = true
  try {
    const cleanPayload = sanitizeObject({
      reference: form.reference,
      description: form.description,
    })

    if (isEdit.value) {
      const res = await store.updateGeneralCashMovement({
        branchId: props.branch?.branch_id,
        movementId: props.movement.movement_id,
        ...cleanPayload,
      })
      ui.success(res?.messages || 'Movimiento actualizado.')
    } else {
      const res = await store.addGeneralCashMovement({
        branchId: props.branch?.branch_id,
        movementType: form.movementType,
        amount: form.amount,
        ...cleanPayload,
      })
      ui.success(res?.messages || 'Movimiento registrado.')
    }
    emit('saved')
    emit('close')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>

