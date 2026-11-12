<template>
  <BaseModal
    :open="open"
    title="Configurar Caja General de Sucursal"
    :subtitle="branch ? `${branch.branch_code} · ${branch.branch_name}` : ''"
    size="md"
    @close="$emit('close')"
  >
    <form id="generalCashCfgForm" class="space-y-4" @submit.prevent="submitForm">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre de bóveda / fondo *</label>
        <input
          v-model.trim="form.accountName"
          type="text"
          required
          maxlength="150"
          class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Alerta de saldo mínimo ($) *</label>
          <input
            v-model.number="form.minBalanceAlert"
            type="number"
            step="0.01"
            min="0"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado operativo *</label>
          <select
            v-model.number="form.status"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option :value="1">Activa (operativa)</option>
            <option :value="2">Bloqueada (solo lectura)</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1.5">Notas / Política interna</label>
        <textarea
          v-model.trim="form.notes"
          rows="2"
          maxlength="255"
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
        form="generalCashCfgForm"
        :disabled="saving"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        Guardar configuración
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { Loader2 } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { useSystemConfigStore } from '@/stores/systemConfig'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { findUnsafeField } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  branch: { type: Object, default: null },
  account: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])
const store = useSystemConfigStore()
const ui = useUiStore()

const saving = ref(false)
const form = reactive({
  accountName: '',
  minBalanceAlert: 100,
  status: 1,
  notes: '',
})

watch(
  () => [props.open, props.account],
  () => {
    if (!props.open || !props.account) return
    form.accountName = props.account.account_name || ''
    form.minBalanceAlert = Number(props.account.min_balance_alert ?? 0)
    form.status = Number(props.account.status ?? 1)
    form.notes = props.account.notes || ''
  },
  { immediate: true }
)

async function submitForm() {
  const unsafe = findUnsafeField({
    Nombre: form.accountName,
    Notas: form.notes,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene caracteres o código no permitidos.`)
    return
  }

  saving.value = true
  try {
    const res = await store.updateGeneralCashAccount({
      branchId: props.branch?.branch_id,
      accountName: form.accountName,
      minBalanceAlert: form.minBalanceAlert,
      status: form.status,
      notes: form.notes,
    })
    ui.success(res?.messages || 'Configuración de Caja General actualizada.')
    emit('saved')
    emit('close')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}
</script>

