<template>
  <BaseModal
    :open="open"
    title="Arqueo y Conciliación de Caja"
    :subtitle="session ? `${session.session_code} · ${session.register_name} (Turno abierto)` : ''"
    size="lg"
    @close="$emit('close')"
  >
    <form v-if="session" id="reconcileCashForm" class="space-y-4" @submit.prevent="submitForm">
      <!-- Live Comparison Banner -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
          <p class="text-xs font-medium text-slate-500">Efectivo esperado</p>
          <p class="text-xl font-extrabold text-slate-900 mt-0.5">{{ formatCurrency(expectedCash) }}</p>
        </div>
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3.5">
          <p class="text-xs font-medium text-indigo-700">Efectivo contado</p>
          <p class="text-xl font-extrabold text-indigo-900 mt-0.5">{{ formatCurrency(countedTotal) }}</p>
        </div>
        <div
          class="rounded-xl border p-3.5"
          :class="diffCardClass"
        >
          <p class="text-xs font-medium">{{ diffLabel }}</p>
          <p class="text-xl font-extrabold mt-0.5">
            {{ difference > 0 ? '+' : '' }}{{ formatCurrency(difference) }}
          </p>
        </div>
      </div>

      <!-- Mode Toggle -->
      <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5">
        <span class="text-sm font-medium text-slate-700">Conteo por denominaciones (billetes y monedas)</span>
        <button
          type="button"
          class="rounded-lg border px-3 py-1 text-xs font-semibold transition"
          :class="useDenominations ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-slate-50 text-slate-700'"
          @click="useDenominations = !useDenominations"
        >
          {{ useDenominations ? 'Desglose activo' : 'Activar desglose' }}
        </button>
      </div>

      <!-- Denominations Counter -->
      <div v-if="useDenominations" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
        <div
          v-for="row in DENOMINATION_ROWS"
          :key="row.key"
          class="flex items-center justify-between gap-2 rounded-lg bg-white border border-slate-200 px-2.5 py-1.5"
        >
          <span class="text-xs font-medium text-slate-700">{{ row.label }}</span>
          <input
            v-model.number="denominations[row.key]"
            type="number"
            min="0"
            step="1"
            class="w-16 rounded-md border border-slate-300 px-2 py-1 text-right text-xs font-semibold focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </div>

      <!-- Direct Counted Cash Input -->
      <div v-else>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Efectivo físico contado ($) *</label>
        <input
          v-model.number="directCounted"
          type="number"
          step="0.01"
          min="0"
          required
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <!-- Declared Non-Cash Amounts -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Tarjeta declarada ($)</label>
          <input
            v-model.number="form.cardReported"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Transferencias declaradas ($)</label>
          <input
            v-model.number="form.transferReported"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Cheques declarados ($)</label>
          <input
            v-model.number="form.checkReported"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Observaciones del arqueo</label>
        <textarea
          v-model.trim="form.notes"
          rows="2"
          maxlength="255"
          placeholder="Justificación de diferencias o notas de verificación..."
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
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
        form="reconcileCashForm"
        :disabled="saving"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        Guardar arqueo
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import {
  DENOMINATION_ROWS,
  createEmptyDenominations,
  sumDenominations,
  useCashRegisterStore,
} from '@/stores/cashRegister'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  session: { type: Object, default: null },
})

const emit = defineEmits(['close', 'reconciled'])
const store = useCashRegisterStore()
const ui = useUiStore()

const saving = ref(false)
const useDenominations = ref(false)
const directCounted = ref(0)
const denominations = ref(createEmptyDenominations())

const form = reactive({
  cardReported: 0,
  transferReported: 0,
  checkReported: 0,
  notes: '',
})

const expectedCash = computed(() => Number(props.session?.expected_cash || 0))
const countedTotal = computed(() =>
  useDenominations.value
    ? sumDenominations(denominations.value)
    : Math.max(0, Math.round((Number(directCounted.value) || 0) * 100) / 100)
)
const difference = computed(() => Math.round((countedTotal.value - expectedCash.value) * 100) / 100)

const diffLabel = computed(() => {
  if (Math.abs(difference.value) < 0.005) return 'Estado: Cuadrado'
  return difference.value > 0 ? 'Diferencia: Sobrante' : 'Diferencia: Faltante'
})

const diffCardClass = computed(() => {
  if (Math.abs(difference.value) < 0.005) return 'border-emerald-200 bg-emerald-50 text-emerald-800'
  if (difference.value > 0) return 'border-sky-200 bg-sky-50 text-sky-800'
  return 'border-rose-200 bg-rose-50 text-rose-800'
})

watch(
  () => [props.open, props.session],
  () => {
    if (!props.open || !props.session) return
    useDenominations.value = false
    directCounted.value = Number(props.session.expected_cash || 0)
    denominations.value = createEmptyDenominations()
    form.cardReported = Number(props.session.card_reported || 0)
    form.transferReported = Number(props.session.transfer_reported || 0)
    form.checkReported = Number(props.session.check_reported || 0)
    form.notes = ''
  },
  { immediate: true }
)

async function submitForm() {
  if (!props.session) return
  const unsafe = findUnsafeField({ Observaciones: form.notes })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }

  saving.value = true
  try {
    const res = await store.reconcileSession({
      sessionId: props.session.session_id,
      countedCash: countedTotal.value,
      denominations: useDenominations.value ? denominations.value : null,
      cardReported: form.cardReported,
      transferReported: form.transferReported,
      checkReported: form.checkReported,
      notes: form.notes,
    })
    ui.success(res?.messages || 'Arqueo registrado exitosamente.')
    emit('reconciled')
    emit('close')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>

