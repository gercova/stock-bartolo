<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar sucursal' : 'Nueva sucursal'"
    :subtitle="isEdit ? `Modifica parámetros de ${branch?.branch_name || ''}` : 'Crea un nuevo tenant con su propia Caja General y Caja Registradora'"
    size="lg"
    @close="$emit('close')"
  >
    <form id="branchForm" class="space-y-4" @submit.prevent="submitForm">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Código *</label>
          <input
            v-model.trim="form.branchCode"
            type="text"
            maxlength="32"
            :placeholder="isEdit ? '' : 'Ej. SUC-002 (opcional)'"
            :required="isEdit"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de la sucursal *</label>
          <input
            v-model.trim="form.branchName"
            type="text"
            required
            maxlength="150"
            placeholder="Ej. Sucursal Norte"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Responsable / Gerente</label>
          <input
            v-model.trim="form.managerName"
            type="text"
            maxlength="120"
            placeholder="Nombre del encargado"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
          <input
            v-model.trim="form.phone"
            type="text"
            maxlength="60"
            placeholder="+503 0000-0000"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Correo electrónico</label>
          <input
            v-model.trim="form.email"
            type="email"
            maxlength="120"
            placeholder="sucursal@empresa.com"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
          <input
            v-model.trim="form.address"
            type="text"
            maxlength="255"
            placeholder="Dirección física de la sucursal"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 rounded-xl bg-slate-50 p-4 border border-slate-200">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">IVA (%) *</label>
          <input
            v-model.number="form.taxRate"
            type="number"
            step="0.01"
            min="0"
            max="100"
            required
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Moneda *</label>
          <input
            v-model.trim="form.currencyCode"
            type="text"
            maxlength="10"
            required
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm uppercase focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Símbolo *</label>
          <input
            v-model.trim="form.currencySymbol"
            type="text"
            maxlength="8"
            required
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Estado *</label>
          <select
            v-model.number="form.active"
            :disabled="Boolean(branch?.is_main)"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
          >
            <option :value="1">Activa</option>
            <option :value="2">Inactiva</option>
          </select>
        </div>
      </div>

      <div v-if="!isEdit" class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 space-y-2">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-sm font-semibold text-indigo-950">Capital inicial de Caja General (opcional)</p>
            <p class="text-xs text-indigo-700">
              Se asignará de forma independiente al fondo operativo de esta nueva sucursal.
            </p>
          </div>
          <div class="w-40">
            <input
              v-model.number="form.initialCapital"
              type="number"
              step="0.01"
              min="0"
              class="w-full rounded-xl border border-indigo-300 bg-white px-3 py-2 text-right text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:outline-none"
            />
          </div>
        </div>
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
        form="branchForm"
        :disabled="saving"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        {{ isEdit ? 'Actualizar sucursal' : 'Crear sucursal' }}
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
import { findUnsafeField } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  branch: { type: Object, default: null },
  defaultTaxRate: { type: Number, default: 13 },
  defaultCurrencyCode: { type: String, default: 'USD' },
  defaultCurrencySymbol: { type: String, default: '$' },
})

const emit = defineEmits(['close', 'saved'])
const store = useSystemConfigStore()
const ui = useUiStore()

const isEdit = computed(() => Boolean(props.branch?.branch_id))
const saving = ref(false)

const form = reactive({
  branchCode: '',
  branchName: '',
  managerName: '',
  phone: '',
  email: '',
  address: '',
  taxRate: 13,
  currencyCode: 'USD',
  currencySymbol: '$',
  active: 1,
  initialCapital: 0,
})

watch(
  () => [props.open, props.branch],
  () => {
    if (!props.open) return
    if (props.branch) {
      form.branchCode = props.branch.branch_code || ''
      form.branchName = props.branch.branch_name || ''
      form.managerName = props.branch.manager_name || ''
      form.phone = props.branch.phone || ''
      form.email = props.branch.email || ''
      form.address = props.branch.address || ''
      form.taxRate = Number(props.branch.tax_rate ?? 13)
      form.currencyCode = props.branch.currency_code || 'USD'
      form.currencySymbol = props.branch.currency_symbol || '$'
      form.active = Number(props.branch.active || 1)
      form.initialCapital = 0
    } else {
      form.branchCode = ''
      form.branchName = ''
      form.managerName = ''
      form.phone = ''
      form.email = ''
      form.address = ''
      form.taxRate = Number(props.defaultTaxRate ?? 13)
      form.currencyCode = props.defaultCurrencyCode || 'USD'
      form.currencySymbol = props.defaultCurrencySymbol || '$'
      form.active = 1
      form.initialCapital = 0
    }
  },
  { immediate: true }
)

async function submitForm() {
  const unsafe = findUnsafeField({
    Código: form.branchCode,
    Nombre: form.branchName,
    Responsable: form.managerName,
    Teléfono: form.phone,
    Correo: form.email,
    Dirección: form.address,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene caracteres o código no permitidos.`)
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      const res = await store.updateBranch({
        branchId: props.branch.branch_id,
        branchCode: form.branchCode,
        branchName: form.branchName,
        managerName: form.managerName,
        phone: form.phone,
        email: form.email,
        address: form.address,
        taxRate: form.taxRate,
        currencyCode: form.currencyCode,
        currencySymbol: form.currencySymbol,
        active: form.active,
      })
      ui.success(res?.messages || 'Sucursal actualizada.')
    } else {
      const res = await store.createBranch({
        branchCode: form.branchCode,
        branchName: form.branchName,
        managerName: form.managerName,
        phone: form.phone,
        email: form.email,
        address: form.address,
        taxRate: form.taxRate,
        currencyCode: form.currencyCode,
        currencySymbol: form.currencySymbol,
        active: form.active,
        initialCapital: form.initialCapital,
      })
      ui.success(res?.messages || 'Sucursal creada exitosamente.')
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

