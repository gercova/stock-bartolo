<template>
  <BaseModal
    :open="open"
    title="Apertura de Caja"
    :subtitle="branchSubtitle"
    size="md"
    @close="$emit('close')"
  >
    <form id="openCashForm" class="space-y-4" @submit.prevent="submitForm">
      <!-- Selección de Caja Registradora -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Caja registradora *</label>
        <select
          v-model.number="form.registerId"
          required
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        >
          <option :value="0" disabled>-- Selecciona caja disponible --</option>
          <option
            v-for="r in availableRegisters"
            :key="r.register_id"
            :value="r.register_id"
          >
            {{ r.register_code }} — {{ r.register_name }}{{ r.location ? ` (${r.location})` : '' }}
          </option>
        </select>
        <p v-if="availableRegisters.length === 0" class="text-xs text-rose-600 mt-1">
          No hay cajas disponibles en esta sucursal (todas están abiertas o inactivas).
        </p>
      </div>

      <!-- Tarjeta informativa de Moneda Oficial de Apertura -->
      <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/90 px-3.5 py-2.5">
        <div class="flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 font-bold text-sm shadow-xs">
            {{ currencySymbol }}
          </div>
          <div>
            <p class="text-xs font-bold text-slate-800">Moneda del Sistema</p>
            <p class="text-[11px] text-slate-500 font-medium">
              Apertura en {{ currencyName }} ({{ currencyCode }})
            </p>
          </div>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 border border-indigo-200">
          <span>{{ currencySymbol }}</span>
          <span>{{ currencyCode }}</span>
        </span>
      </div>

      <!-- Monto de Fondo Inicial de Apertura en la Moneda Correspondiente -->
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="block text-sm font-semibold text-slate-700">
            Fondo inicial de apertura ({{ currencySymbol }}) *
          </label>
          <span class="text-xs text-slate-500 font-medium">
            Solo {{ currencyName }} ({{ currencyCode }})
          </span>
        </div>
        <div class="relative flex rounded-xl shadow-xs">
          <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-slate-300 bg-slate-100 text-slate-700 font-bold text-sm select-none">
            {{ currencySymbol }}
          </span>
          <input
            v-model.number="form.openingAmount"
            type="number"
            step="0.01"
            min="0"
            required
            placeholder="0.00"
            class="w-full rounded-none rounded-r-xl border border-slate-300 px-3.5 py-2 text-base font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <!-- Preajustes rápidos de monto en la moneda correspondiente -->
        <div class="mt-2 flex items-center gap-1.5 flex-wrap">
          <span class="text-[11px] text-slate-500 font-medium mr-1">Montos rápidos:</span>
          <button
            v-for="preset in quickPresets"
            :key="preset"
            type="button"
            @click="form.openingAmount = preset"
            class="text-xs px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700 font-semibold text-slate-700 transition shadow-xs cursor-pointer"
          >
            {{ currencySymbol }} {{ preset.toFixed(2) }}
          </button>
        </div>
      </div>

      <!-- Fondeo desde Caja General de la Sucursal -->
      <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 space-y-2.5">
        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="form.fundFromGeneralCash"
            type="checkbox"
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
          />
          <div class="text-sm">
            <p class="font-semibold text-indigo-950">Fondear desde Caja General de la sucursal</p>
            <p class="text-xs text-indigo-700 mt-0.5">
              Disponible en Caja General: <strong>{{ formatCurrency(generalCashBalance, currencyCode) }}</strong>.
              Descontará este fondo inicial del capital operativo general.
            </p>
          </div>
        </label>

        <p
          v-if="form.fundFromGeneralCash && form.openingAmount > generalCashBalance"
          class="text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-1.5"
        >
          El fondo solicitado ({{ formatCurrency(form.openingAmount, currencyCode) }}) supera el saldo disponible en Caja General.
        </p>
      </div>

      <!-- Notas u observaciones de apertura -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas de apertura</label>
        <textarea
          v-model.trim="form.openingNotes"
          rows="2"
          maxlength="255"
          placeholder="Observaciones iniciales del turno operativo..."
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        ></textarea>
      </div>
    </form>

    <template #footer>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition cursor-pointer"
        :disabled="saving"
        @click="$emit('close')"
      >
        Cancelar
      </button>
      <button
        type="submit"
        form="openCashForm"
        :disabled="saving || !form.registerId || (form.fundFromGeneralCash && form.openingAmount > generalCashBalance)"
        class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-60 inline-flex items-center gap-2 transition cursor-pointer"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        Abrir caja
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { useAuthStore } from '@/stores/auth'
import { useCashRegisterStore } from '@/stores/cashRegister'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  registers: { type: Array, default: () => [] },
  preselectedRegisterId: { type: Number, default: 0 },
  generalCashBalance: { type: Number, default: 0 },
  autoFundDefault: { type: Boolean, default: true },
  branchName: { type: String, default: '' },
  currencyCode: { type: String, default: '' },
  currencySymbol: { type: String, default: '' },
})

const emit = defineEmits(['close', 'opened'])
const auth = useAuthStore()
const store = useCashRegisterStore()
const ui = useUiStore()

const saving = ref(false)
const form = reactive({
  registerId: 0,
  openingAmount: 0,
  fundFromGeneralCash: true,
  openingNotes: '',
})

const availableRegisters = computed(() =>
  (props.registers || []).filter((r) => Number(r.active) === 1 && !r.open_session_id)
)

const currencyCode = computed(() => {
  return (
    props.currencyCode ||
    auth.activeBranch?.currency_code ||
    store.systemConfig?.default_currency_code ||
    'PEN'
  ).toUpperCase()
})

const currencySymbol = computed(() => {
  return (
    props.currencySymbol ||
    auth.activeBranch?.currency_symbol ||
    store.systemConfig?.default_currency_symbol ||
    (currencyCode.value === 'USD' ? '$' : 'S/')
  )
})

const currencyName = computed(() => {
  if (currencyCode.value === 'PEN') return 'Soles'
  if (currencyCode.value === 'USD') return 'Dólares'
  return currencyCode.value
})

const branchSubtitle = computed(() => {
  const parts = []
  if (props.branchName) parts.push(`Sucursal: ${props.branchName}`)
  parts.push(`Moneda: ${currencyName.value} (${currencySymbol.value})`)
  return parts.join(' · ')
})

const quickPresets = computed(() => {
  if (currencyCode.value === 'PEN') {
    return [0, 50, 100, 200, 500]
  }
  return [0, 20, 50, 100, 200]
})

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    const candidate = availableRegisters.value.find(
      (r) => r.register_id === props.preselectedRegisterId
    )
    const chosen = candidate || availableRegisters.value[0] || null
    form.registerId = chosen?.register_id || 0
    form.openingAmount = 0
    form.fundFromGeneralCash = Boolean(props.autoFundDefault) && props.generalCashBalance > 0
    form.openingNotes = ''
  }
)

async function submitForm() {
  const unsafe = findUnsafeField({ Notas: form.openingNotes })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }

  saving.value = true
  try {
    const res = await store.openSession({
      registerId: form.registerId,
      openingAmount: form.openingAmount,
      fundFromGeneralCash: form.fundFromGeneralCash,
      openingNotes: form.openingNotes,
    })
    ui.success(res?.messages || 'Caja abierta exitosamente.')
    emit('opened')
    emit('close')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>
