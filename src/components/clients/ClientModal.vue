<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar Registro' : 'Nuevo Cliente / Proveedor'"
    :subtitle="isEdit ? `Modificando a ${form.name}` : 'Registra un cliente o proveedor con validación en línea RENIEC/SUNAT'"
    size="xl"
    @close="$emit('close')"
  >
    <form @submit.prevent="handleSubmit" id="clientForm" class="space-y-4">
      <div v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
        {{ errorMessage }}
      </div>

      <!-- Type of entity pills -->
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5">
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
          Tipo de Registro <span class="text-rose-500">*</span>
        </label>
        <div class="grid grid-cols-3 gap-2">
          <button
            type="button"
            class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold border transition"
            :class="form.client_type === 'client' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
            @click="form.client_type = 'client'"
          >
            <User class="h-3.5 w-3.5" />
            <span>Cliente</span>
          </button>
          <button
            type="button"
            class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold border transition"
            :class="form.client_type === 'supplier' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
            @click="form.client_type = 'supplier'"
          >
            <Truck class="h-3.5 w-3.5" />
            <span>Proveedor</span>
          </button>
          <button
            type="button"
            class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-semibold border transition"
            :class="form.client_type === 'both' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
            @click="form.client_type = 'both'"
          >
            <Briefcase class="h-3.5 w-3.5" />
            <span>Cliente y Proveedor</span>
          </button>
        </div>
      </div>

      <!-- Document & Online Decolecta Lookup Section -->
      <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 space-y-3">
        <div class="flex items-center justify-between">
          <label class="text-xs font-bold uppercase tracking-wider text-indigo-950 flex items-center gap-1.5">
            <Sparkles class="h-4 w-4 text-indigo-600" />
            Identificación Oficial (Consulta RENIEC / SUNAT)
          </label>
          <span class="text-[11px] text-indigo-700 font-medium">Decolecta API</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
          <!-- Document Type -->
          <div class="sm:col-span-4">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo de Documento</label>
            <select
              v-model="form.document_type"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
              @change="handleDocTypeChange"
            >
              <option value="DNI">DNI (8 dígitos - RENIEC)</option>
              <option value="RUC">RUC (11 dígitos - SUNAT)</option>
              <option value="CE">Carnet de Extranjería (CE)</option>
              <option value="PASAPORTE">Pasaporte</option>
              <option value="OTRO">Otro documento</option>
            </select>
          </div>

          <!-- Document Number -->
          <div class="sm:col-span-5">
            <label class="block text-xs font-semibold text-slate-600 mb-1">
              Número de Documento <span class="text-rose-500">*</span>
            </label>
            <input
              v-model.trim="form.document_number"
              type="text"
              required
              :maxlength="form.document_type === 'DNI' ? 8 : (form.document_type === 'RUC' ? 11 : 20)"
              :placeholder="form.document_type === 'DNI' ? 'ej. 45874213 (8 dígitos)' : (form.document_type === 'RUC' ? 'ej. 20131312955 (11 dígitos)' : 'Número de documento')"
              class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
              @keydown.enter.prevent="lookupOnline"
            />
          </div>

          <!-- Lookup Button -->
          <div class="sm:col-span-3">
            <button
              type="button"
              :disabled="lookingUp || !canLookup"
              class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs"
              @click="lookupOnline"
            >
              <Loader2 v-if="lookingUp" class="h-4 w-4 animate-spin" />
              <Search v-else class="h-4 w-4" />
              <span>{{ lookingUp ? 'Consultando...' : 'Consultar' }}</span>
            </button>
          </div>
        </div>

        <!-- Lookup Result Banner -->
        <div
          v-if="lookupBadge"
          class="flex items-center gap-2 rounded-lg p-2.5 text-xs font-medium border"
          :class="lookupBadge.isWarning ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'"
        >
          <CheckCircle2 v-if="!lookupBadge.isWarning" class="h-4 w-4 shrink-0 text-emerald-600" />
          <AlertCircle v-else class="h-4 w-4 shrink-0 text-amber-600" />
          <span>{{ lookupBadge.message }}</span>
        </div>
      </div>

      <!-- Main Name / Business Name -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
          Nombre Completo o Razón Social <span class="text-rose-500">*</span>
        </label>
        <input
          v-model.trim="form.name"
          type="text"
          required
          maxlength="255"
          placeholder="ej. Distribuidora Bartolo S.A.C. o Carlos Méndez"
          class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <!-- Natural person split fields (optional) -->
      <div v-if="form.document_type === 'DNI'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Nombres
          </label>
          <input
            v-model.trim="form.first_name"
            type="text"
            maxlength="100"
            placeholder="ej. Carlos"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Apellidos
          </label>
          <input
            v-model.trim="form.last_name"
            type="text"
            maxlength="100"
            placeholder="ej. Méndez López"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <!-- Contact details -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Teléfono / Celular
          </label>
          <input
            v-model.trim="form.phone"
            type="tel"
            maxlength="50"
            placeholder="ej. 987 654 321"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Correo Electrónico
          </label>
          <input
            v-model.trim="form.email"
            type="email"
            maxlength="150"
            placeholder="ej. contacto@empresa.com"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <!-- Address & Location -->
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
          Dirección Fiscal o Residencia
        </label>
        <input
          v-model.trim="form.address"
          type="text"
          maxlength="255"
          placeholder="ej. Av. Los Próceres 1234, Urb. Las Flores"
          class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Departamento</label>
          <input
            v-model.trim="form.department"
            type="text"
            placeholder="ej. Lima"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Provincia</label>
          <input
            v-model.trim="form.province"
            type="text"
            placeholder="ej. Lima"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
          />
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Distrito</label>
          <input
            v-model.trim="form.district"
            type="text"
            placeholder="ej. San Isidro"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
          />
        </div>
      </div>

      <!-- Branch & Notes -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Sucursal
          </label>
          <select
            v-model="form.branch_id"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          >
            <option :value="null">Acceso Global (Todas las sucursales)</option>
            <option v-for="b in branches" :key="b.branch_id" :value="b.branch_id">
              [{{ b.branch_code }}] {{ b.branch_name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Estado Operativo
          </label>
          <select
            v-model="form.status"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          >
            <option :value="1">Activo</option>
            <option :value="0">Inactivo</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
          Notas u Observaciones
        </label>
        <textarea
          v-model.trim="form.notes"
          rows="2"
          placeholder="Condiciones comerciales, referencias o comentarios..."
          class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
        ></textarea>
      </div>
    </form>

    <template #footer>
      <button
        type="button"
        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
        @click="$emit('close')"
      >
        Cancelar
      </button>
      <button
        type="submit"
        form="clientForm"
        :disabled="submitting"
        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        <span>{{ isEdit ? 'Guardar Cambios' : 'Registrar Cliente' }}</span>
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import {
  AlertCircle,
  Briefcase,
  CheckCircle2,
  Loader2,
  Search,
  Sparkles,
  Truck,
  User,
} from '@lucide/vue'
import { useClientsStore } from '@/stores/clients'
import { extractErrorMessage } from '@/api/client'
import { findUnsafeField, sanitizeObject } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
  client: { type: Object, default: null },
  branches: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save'])

const clientsStore = useClientsStore()
const errorMessage = ref('')
const lookingUp = ref(false)
const lookupBadge = ref(null)

const isEdit = computed(() => Boolean(props.client && props.client.client_id))

const form = ref({
  client_id: null,
  branch_id: null,
  document_type: 'DNI',
  document_number: '',
  name: '',
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  address: '',
  department: '',
  province: '',
  district: '',
  ubigeo: '',
  client_type: 'client',
  status: 1,
  notes: '',
})

const canLookup = computed(() => {
  const num = form.value.document_number.replace(/\D/g, '')
  if (form.value.document_type === 'DNI') return num.length === 8
  if (form.value.document_type === 'RUC') return num.length === 11
  return false
})

function handleDocTypeChange() {
  lookupBadge.value = null
  if (form.value.document_type === 'RUC' && form.value.client_type === 'client') {
    // suggest supplier or both
    form.value.client_type = 'both'
  }
}

watch(
  () => props.open,
  (val) => {
    if (val) {
      errorMessage.value = ''
      lookupBadge.value = null
      if (props.client) {
        form.value = {
          client_id: props.client.client_id,
          branch_id: props.client.branch_id || null,
          document_type: props.client.document_type || 'DNI',
          document_number: props.client.document_number || '',
          name: props.client.name || '',
          first_name: props.client.first_name || '',
          last_name: props.client.last_name || '',
          email: props.client.email || '',
          phone: props.client.phone || '',
          address: props.client.address || '',
          department: props.client.department || '',
          province: props.client.province || '',
          district: props.client.district || '',
          ubigeo: props.client.ubigeo || '',
          client_type: props.client.client_type || 'client',
          status: Number(props.client.status) === 0 ? 0 : 1,
          notes: props.client.notes || '',
        }
      } else {
        form.value = {
          client_id: null,
          branch_id: null,
          document_type: 'DNI',
          document_number: '',
          name: '',
          first_name: '',
          last_name: '',
          email: '',
          phone: '',
          address: '',
          department: '',
          province: '',
          district: '',
          ubigeo: '',
          client_type: 'client',
          status: 1,
          notes: '',
        }
      }
    }
  },
  { immediate: true }
)

async function lookupOnline() {
  if (!canLookup.value) {
    errorMessage.value = form.value.document_type === 'DNI'
      ? 'El DNI debe tener 8 dígitos para consultar RENIEC.'
      : 'El RUC debe tener 11 dígitos para consultar SUNAT.'
    return
  }

  errorMessage.value = ''
  lookupBadge.value = null
  lookingUp.value = true

  try {
    const res = await clientsStore.lookupDocument(form.value.document_type, form.value.document_number)
    if (res?.data) {
      const d = res.data
      form.value.name = d.name || form.value.name
      if (d.first_name) form.value.first_name = d.first_name
      if (d.last_name) form.value.last_name = d.last_name
      if (d.address) form.value.address = d.address
      if (d.department) form.value.department = d.department
      if (d.province) form.value.province = d.province
      if (d.district) form.value.district = d.district
      if (d.ubigeo) form.value.ubigeo = d.ubigeo
      if (d.email && !form.value.email) form.value.email = d.email
      if (d.phone && !form.value.phone) form.value.phone = d.phone

      if (res.already_registered && (!isEdit.value || form.value.client_id !== d.client_id)) {
        lookupBadge.value = {
          isWarning: true,
          message: `Atención: este documento ya se encuentra registrado previamente en el sistema (#${d.client_id}).`,
        }
      } else {
        lookupBadge.value = {
          isWarning: false,
          message: d.status_text || 'Información oficial recuperada y completada con éxito.',
        }
      }
    }
  } catch (err) {
    errorMessage.value = extractErrorMessage(err, 'No fue posible consultar el documento en RENIEC/SUNAT.')
  } finally {
    lookingUp.value = false
  }
}

function handleSubmit() {
  errorMessage.value = ''
  if (!form.value.document_number) {
    errorMessage.value = 'El número de documento es obligatorio.'
    return
  }
  if (!form.value.name || form.value.name.length < 2) {
    errorMessage.value = 'El nombre o razón social es obligatorio.'
    return
  }

  const unsafe = findUnsafeField({
    'Documento': form.value.document_number,
    'Nombre / Razón Social': form.value.name,
    'Nombres': form.value.first_name,
    'Apellidos': form.value.last_name,
    'Correo electrónico': form.value.email,
    'Teléfono': form.value.phone,
    'Dirección': form.value.address,
    'Notas': form.value.notes,
  })

  if (unsafe) {
    errorMessage.value = `El campo "${unsafe}" contiene código o caracteres no permitidos por seguridad.`
    return
  }

  emit('save', sanitizeObject({
    ...form.value,
  }))
}
</script>

