<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar caja registradora' : 'Nueva caja registradora'"
    :subtitle="branchName ? `Sucursal: ${branchName}` : ''"
    size="md"
    @close="$emit('close')"
  >
    <form id="cashRegisterForm" class="space-y-4" @submit.prevent="submitForm">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Código</label>
          <input
            v-model.trim="form.registerCode"
            type="text"
            maxlength="32"
            :required="isEdit"
            placeholder="CAJA-02"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm uppercase focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombre de caja *</label>
          <input
            v-model.trim="form.registerName"
            type="text"
            required
            maxlength="120"
            placeholder="Ej. Caja Express / Punto 2"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Ubicación / Área</label>
          <input
            v-model.trim="form.location"
            type="text"
            maxlength="150"
            placeholder="Ej. Mostrador Principal"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Estado *</label>
          <select
            v-model.number="form.active"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option :value="1">Activa</option>
            <option :value="2">Inactiva</option>
          </select>
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
        form="cashRegisterForm"
        :disabled="saving"
        class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
      >
        <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
        {{ isEdit ? 'Guardar cambios' : 'Crear caja' }}
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
import { findUnsafeField } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  register: { type: Object, default: null },
  branchName: { type: String, default: '' },
})

const emit = defineEmits(['close', 'saved'])
const store = useCashRegisterStore()
const ui = useUiStore()

const isEdit = computed(() => Boolean(props.register?.register_id))
const saving = ref(false)

const form = reactive({
  registerCode: '',
  registerName: '',
  location: '',
  active: 1,
})

watch(
  () => [props.open, props.register],
  () => {
    if (!props.open) return
    if (props.register) {
      form.registerCode = props.register.register_code || ''
      form.registerName = props.register.register_name || ''
      form.location = props.register.location || ''
      form.active = Number(props.register.active || 1)
    } else {
      form.registerCode = ''
      form.registerName = ''
      form.location = ''
      form.active = 1
    }
  },
  { immediate: true }
)

async function submitForm() {
  const unsafe = findUnsafeField({
    Código: form.registerCode,
    Nombre: form.registerName,
    Ubicación: form.location,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene caracteres o código no permitidos.`)
    return
  }

  saving.value = true
  try {
    if (isEdit.value) {
      const res = await store.updateRegister({
        registerId: props.register.register_id,
        registerCode: form.registerCode,
        registerName: form.registerName,
        location: form.location,
        active: form.active,
      })
      ui.success(res?.messages || 'Caja actualizada.')
    } else {
      const res = await store.createRegister({
        registerCode: form.registerCode,
        registerName: form.registerName,
        location: form.location,
        active: form.active,
      })
      ui.success(res?.messages || 'Caja creada exitosamente.')
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

