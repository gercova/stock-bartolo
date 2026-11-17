<template>
  <BaseModal
    :open="open"
    title="Configurar Cuenta Bancaria"
    size="md"
    @close="handleClose"
  >
    <form id="bankCapitalAccountForm" class="space-y-4" @submit.prevent="handleSubmit">
      <div v-if="account" class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold border"
            :class="bankMeta?.badgeClass"
          >
            {{ account.bank_name }}
          </span>
          <span class="text-xs text-slate-500 font-mono">{{ account.bank_code }}</span>
        </div>
        <div class="text-right">
          <span class="text-[10px] text-slate-400 block uppercase font-bold">Saldo Actual</span>
          <span class="text-xs font-mono font-bold text-slate-900">
            S/ {{ Number(account.current_balance || 0).toFixed(2) }}
          </span>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
          Número de Cuenta Bancaria
        </label>
        <input
          v-model="form.account_number"
          type="text"
          placeholder="Ej: 191-98765432-0-12"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-mono text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-hidden transition"
        />
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
          Tipo de Cuenta
        </label>
        <select
          v-model="form.account_type"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:outline-hidden transition"
        >
          <option value="corriente">Cuenta Corriente</option>
          <option value="ahorros">Cuenta de Ahorros</option>
          <option value="recaudadora">Cuenta Recaudadora</option>
          <option value="billetera">Billetera Digital / Recaudación</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
          Estado
        </label>
        <select
          v-model.number="form.status"
          class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-900 focus:border-indigo-500 focus:outline-hidden transition"
        >
          <option :value="1">Activa (Disponible para operaciones y fondeo)</option>
          <option :value="0">Inactiva (No disponible)</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
          Notas / Descripción de la Cuenta
        </label>
        <textarea
          v-model="form.notes"
          rows="2"
          placeholder="Notas internas, titular, CCI, etc."
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
          form="bankCapitalAccountForm"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs cursor-pointer"
          :disabled="submitting"
        >
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <Save v-else class="h-4 w-4" />
          <span>Guardar Cambios</span>
        </button>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { Save, Loader2 } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { getBankEntity } from '@/utils/bankEntities'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  account: {
    type: Object,
    default: null,
  },
  submitting: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
  account_id: 0,
  account_number: '',
  account_type: 'corriente',
  status: 1,
  notes: '',
})

const bankMeta = computed(() => {
  return props.account ? getBankEntity(props.account.bank_name) : null
})

watch(
  () => props.account,
  (acc) => {
    if (acc) {
      form.account_id = acc.account_id
      form.account_number = acc.account_number || ''
      form.account_type = acc.account_type || 'corriente'
      form.status = acc.status ?? 1
      form.notes = acc.notes || ''
    }
  },
  { immediate: true }
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
