<template>
  <BaseModal
    :open="open"
    title="Cierre de Caja (Corte Z)"
    :subtitle="session ? `${session.session_code} · ${session.register_name}` : ''"
    size="lg"
    @close="$emit('close')"
  >
    <form v-if="session" id="closeCashForm" class="space-y-4" @submit.prevent="submitForm">
      <!-- Expected vs Counted KPIs -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
          <p class="text-xs font-medium text-slate-500">Esperado según sistema</p>
          <p class="text-xl font-extrabold text-slate-900 mt-0.5">{{ formatCurrency(expectedCash) }}</p>
        </div>
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3.5">
          <p class="text-xs font-medium text-indigo-700">Efectivo contado final</p>
          <p class="text-xl font-extrabold text-indigo-900 mt-0.5">{{ formatCurrency(countedTotal) }}</p>
        </div>
        <div class="rounded-xl border p-3.5" :class="diffCardClass">
          <p class="text-xs font-medium">{{ diffLabel }}</p>
          <p class="text-xl font-extrabold mt-0.5">
            {{ difference > 0 ? '+' : '' }}{{ formatCurrency(difference) }}
          </p>
        </div>
      </div>

      <!-- Mode Toggle -->
      <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5">
        <span class="text-sm font-medium text-slate-700">Conteo final por denominaciones</span>
        <button
          type="button"
          class="rounded-lg border px-3 py-1 text-xs font-semibold transition"
          :class="useDenominations ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-slate-50 text-slate-700'"
          @click="useDenominations = !useDenominations"
        >
          {{ useDenominations ? 'Desglose activo' : 'Activar desglose' }}
        </button>
      </div>

      <!-- Denominations -->
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

      <!-- Non-cash reported -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Tarjeta ($)</label>
          <input
            v-model.number="form.cardReported"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Transferencias ($)</label>
          <input
            v-model.number="form.transferReported"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Cheques ($)</label>
          <input
            v-model.number="form.checkReported"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </div>

      <!-- Return to Branch General Cash -->
      <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 space-y-3">
        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="form.transferToGeneralCash"
            type="checkbox"
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
          />
          <div class="text-sm">
            <p class="font-semibold text-emerald-950">Transferir efectivo de cierre a Caja General de la sucursal</p>
            <p class="text-xs text-emerald-700 mt-0.5">
              Acreditará el monto entregado al fondo operativo independiente de la sucursal activa.
            </p>
          </div>
        </label>

        <div v-if="form.transferToGeneralCash" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
          <div>
            <label class="block text-xs font-semibold text-emerald-900 mb-1">Monto a entregar a Caja General ($)</label>
            <input
              v-model.number="form.returnAmount"
              type="number"
              step="0.01"
              min="0"
              :max="countedTotal"
              class="w-full rounded-xl border border-emerald-300 bg-white px-3 py-1.5 text-sm font-semibold focus:border-emerald-500 focus:outline-none"
            />
          </div>
          <div class="rounded-xl bg-white/80 border border-emerald-200 px-3 py-2 flex flex-col justify-center">
            <span class="text-xs text-emerald-700">Fondo retenido en gaveta</span>
            <span class="text-sm font-bold text-slate-900">{{ formatCurrency(retainedFloat) }}</span>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas de cierre</label>
        <textarea
          v-model.trim="form.closingNotes"
          rows="2"
          maxlength="255"
          placeholder="Comentarios finales del cierre de turno..."
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
        form="closeCashForm"
        :disabled="saving || (form.transferToGeneralCash && form.returnAmount > countedTotal + 0.001)"
        class="px-4 py-2 text-sm font-semibold text-white bg-rose-600 rounded-lg hover:bg-rose-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        Confirmar cierre de caja
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
  autoReturnDefault: { type: Boolean, default: true },
})

const emit = defineEmits(['close', 'closed'])
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
  transferToGeneralCash: true,
  returnAmount: 0,
  closingNotes: '',
})

const expectedCash = computed(() => Number(props.session?.expected_cash || 0))
const countedTotal = computed(() =>
  useDenominations.value
    ? sumDenominations(denominations.value)
    : Math.max(0, Math.round((Number(directCounted.value) || 0) * 100) / 100)
)
const difference = computed(() => Math.round((countedTotal.value - expectedCash.value) * 100) / 100)

const retainedFloat = computed(() => {
  if (!form.transferToGeneralCash) return countedTotal.value
  return Math.max(0, Math.round((countedTotal.value - (Number(form.returnAmount) || 0)) * 100) / 100)
})

const diffLabel = computed(() => {
  if (Math.abs(difference.value) < 0.005) return 'Cuadre: Exacto'
  return difference.value > 0 ? 'Diferencia: Sobrante' : 'Diferencia: Faltante'
})

const diffCardClass = computed(() => {
  if (Math.abs(difference.value) < 0.005) return 'border-emerald-200 bg-emerald-50 text-emerald-800'
  if (difference.value > 0) return 'border-sky-200 bg-sky-50 text-sky-800'
  return 'border-rose-200 bg-rose-50 text-rose-800'
})

watch(countedTotal, (newVal) => {
  if (form.transferToGeneralCash) {
    form.returnAmount = newVal
  }
})

watch(
  () => [props.open, props.session],
  () => {
    if (!props.open || !props.session) return
    useDenominations.value = false
    const exp = Number(props.session.expected_cash || 0)
    directCounted.value = exp
    denominations.value = createEmptyDenominations()
    form.cardReported = Number(props.session.card_reported || 0)
    form.transferReported = Number(props.session.transfer_reported || 0)
    form.checkReported = Number(props.session.check_reported || 0)
    form.transferToGeneralCash = Boolean(props.autoReturnDefault)
    form.returnAmount = form.transferToGeneralCash ? exp : 0
    form.closingNotes = ''
  },
  { immediate: true }
)

async function submitForm() {
  if (!props.session) return
  const unsafe = findUnsafeField({ Notas: form.closingNotes })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }

  saving.value = true
  try {
    const res = await store.closeSession({
      sessionId: props.session.session_id,
      countedCash: countedTotal.value,
      denominations: useDenominations.value ? denominations.value : null,
      cardReported: form.cardReported,
      transferReported: form.transferReported,
      checkReported: form.checkReported,
      transferToGeneralCash: form.transferToGeneralCash,
      returnAmount: form.transferToGeneralCash ? form.returnAmount : 0,
      closingNotes: form.closingNotes,
    })
    ui.success(res?.messages || 'Cierre de caja completado.')
    emit('closed')
    emit('close')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>

