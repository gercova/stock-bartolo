<template>
  <BaseModal
    :open="open"
    title="Apertura de Caja"
    :subtitle="branchName ? `Sucursal: ${branchName}` : 'Inicia un nuevo turno operativo de caja'"
    size="md"
    @close="$emit('close')"
  >
    <form id="openCashForm" class="space-y-4" @submit.prevent="submitForm">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Caja registradora *</label>
        <select
          v-model.number="form.registerId"
          required
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
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

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Fondo inicial de apertura ($) *</label>
        <input
          v-model.number="form.openingAmount"
          type="number"
          step="0.01"
          min="0"
          required
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 space-y-2.5">
        <label class="flex items-start gap-3 cursor-pointer">
          <input
            v-model="form.fundFromGeneralCash"
            type="checkbox"
            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
          />
          <div class="text-sm">
            <p class="font-semibold text-indigo-950">Fondear desde Caja General de la sucursal</p>
            <p class="text-xs text-indigo-700 mt-0.5">
              Disponible en Caja General: <strong>{{ formatCurrency(generalCashBalance) }}</strong>.
              Descontará este fondo inicial del capital operativo general.
            </p>
          </div>
        </label>

        <p
          v-if="form.fundFromGeneralCash && form.openingAmount > generalCashBalance"
          class="text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-3 py-1.5"
        >
          El fondo solicitado ({{ formatCurrency(form.openingAmount) }}) supera el saldo de Caja General.
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas de apertura</label>
        <textarea
          v-model.trim="form.openingNotes"
          rows="2"
          maxlength="255"
          placeholder="Observaciones iniciales o turno..."
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
        form="openCashForm"
        :disabled="saving || !form.registerId || (form.fundFromGeneralCash && form.openingAmount > generalCashBalance)"
        class="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 disabled:opacity-60 inline-flex items-center gap-2"
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
})

const emit = defineEmits(['close', 'opened'])
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

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    const candidate = availableRegisters.value.find(
      (r) => r.register_id === props.preselectedRegisterId
    )
    form.registerId = candidate
      ? candidate.register_id
      : availableRegisters.value[0]?.register_id || 0
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

