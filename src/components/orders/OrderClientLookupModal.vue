<template>
  <BaseModal
    :open="open"
    title="Consultar Cliente (RENIEC / SUNAT)"
    max-width="max-w-xl"
    @close="$emit('close')"
  >
    <div class="space-y-4">
      <!-- Search Mode Selector: DNI vs RUC -->
      <div class="flex items-center gap-2 p-1 bg-slate-100 rounded-xl">
        <button
          type="button"
          class="flex-1 py-2 text-xs font-bold rounded-lg transition"
          :class="
            docType === 'DNI'
              ? 'bg-white text-indigo-700 shadow-xs'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="setDocType('DNI')"
        >
          DNI (8 dígitos - RENIEC)
        </button>
        <button
          type="button"
          class="flex-1 py-2 text-xs font-bold rounded-lg transition"
          :class="
            docType === 'RUC'
              ? 'bg-white text-indigo-700 shadow-xs'
              : 'text-slate-600 hover:text-slate-900'
          "
          @click="setDocType('RUC')"
        >
          RUC (11 dígitos - SUNAT)
        </button>
      </div>

      <!-- Search Input & Query Action -->
      <form class="flex gap-2" @submit.prevent="executeLookup">
        <div class="relative flex-1">
          <input
            ref="docInputRef"
            v-model="docNumber"
            type="text"
            :maxlength="docType === 'DNI' ? 8 : 11"
            :placeholder="docType === 'DNI' ? 'Ingresa los 8 dígitos del DNI...' : 'Ingresa los 11 dígitos del RUC...'"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono tracking-wider text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            @input="filterDigits"
          />
          <button
            v-if="docNumber"
            type="button"
            class="absolute right-3 top-3 text-slate-400 hover:text-slate-600"
            @click="docNumber = ''"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <button
          type="submit"
          :disabled="loading || !isValidLength"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs shrink-0"
        >
          <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
          <Search v-else class="h-4 w-4" />
          Consultar {{ docType }}
        </button>
      </form>

      <!-- Feedback / Loading / Results Section -->
      <div v-if="loading" class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-6 text-center space-y-2">
        <Loader2 class="h-6 w-6 animate-spin mx-auto text-indigo-600" />
        <p class="text-xs font-semibold text-indigo-900">
          Consultando servicio oficial de {{ docType === 'DNI' ? 'RENIEC' : 'SUNAT' }} vía Decolecta...
        </p>
      </div>

      <!-- Error State -->
      <div
        v-else-if="errorMessage"
        class="rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-xs text-rose-800 flex items-start gap-2.5"
      >
        <AlertCircle class="h-4 w-4 shrink-0 mt-0.5 text-rose-600" />
        <div class="flex-1">
          <p class="font-bold">Error en la consulta</p>
          <p class="mt-0.5 text-rose-700">{{ errorMessage }}</p>
        </div>
      </div>

      <!-- Result Card (Found customer details) -->
      <div v-else-if="resultData" class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 space-y-3.5">
        <div class="flex items-center justify-between gap-2 border-b border-emerald-100 pb-2.5">
          <div class="flex items-center gap-2">
            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
            <span class="text-xs font-bold text-emerald-900">
              {{ resultData.status_text || 'Identidad validada con éxito' }}
            </span>
          </div>
          <span
            v-if="resultData.already_registered"
            class="rounded-full bg-blue-100 px-2.5 py-0.5 text-[10px] font-bold text-blue-800"
          >
            Ya registrado en BD
          </span>
          <span
            v-else
            class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800"
          >
            Nuevo Cliente
          </span>
        </div>

        <!-- Editable Details Grid -->
        <div class="space-y-2.5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">
              {{ docType === 'DNI' ? 'Nombre completo' : 'Razón Social' }} *
            </label>
            <input
              v-model.trim="resultData.name"
              type="text"
              required
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:outline-none"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Teléfono / Celular</label>
              <input
                v-model.trim="resultData.phone"
                type="text"
                placeholder="Ej. 987654321"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none"
              />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Correo electrónico</label>
              <input
                v-model.trim="resultData.email"
                type="email"
                placeholder="correo@ejemplo.com"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none"
              />
            </div>
          </div>

          <div v-if="docType === 'DNI'" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Fecha de Nacimiento</label>
              <input
                v-model="resultData.birth_date"
                type="date"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none"
              />
            </div>
            <div>
              <label class="block font-semibold text-slate-700 mb-1">Sexo</label>
              <select
                v-model="resultData.gender"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none"
              >
                <option value="">No especificado</option>
                <option value="MASCULINO">Masculino</option>
                <option value="FEMENINO">Femenino</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-semibold text-slate-700 mb-1">Dirección / Ubicación</label>
            <input
              v-model.trim="resultData.address"
              type="text"
              placeholder="Dirección fiscal o domiciliaria"
              class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Footer Actions -->
    <template #footer>
      <div class="flex flex-col sm:flex-row items-center justify-between gap-2 w-full">
        <button
          type="button"
          class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition"
          @click="$emit('close')"
        >
          Cancelar
        </button>

        <div v-if="resultData" class="flex flex-col sm:flex-row items-center gap-2 w-full sm:w-auto">
          <!-- Option A: Use only for this order without DB persistence -->
          <button
            type="button"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition"
            @click="useForOrderOnly"
          >
            Usar solo en esta orden
          </button>

          <!-- Option B: Save to DB & Select (Default recommended) -->
          <button
            type="button"
            :disabled="savingClient"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 rounded-xl transition shadow-xs"
            @click="saveAndSelectClient"
          >
            <Loader2 v-if="savingClient" class="h-3.5 w-3.5 animate-spin" />
            <UserPlus v-else class="h-3.5 w-3.5" />
            {{ resultData.already_registered ? 'Seleccionar Cliente' : 'Guardar en BD y Seleccionar' }}
          </button>
        </div>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { Search, Loader2, X, AlertCircle, CheckCircle2, UserPlus } from '@lucide/vue'
import BaseModal from '@/components/common/BaseModal.vue'
import apiClient, { extractErrorMessage } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { findUnsafeField, sanitizeInput, isValidEmail } from '@/utils/security'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'selected'])

const ui = useUiStore()
const docInputRef = ref(null)

const docType = ref('DNI')
const docNumber = ref('')
const loading = ref(false)
const savingClient = ref(false)
const errorMessage = ref('')
const resultData = ref(null)

const isValidLength = computed(() => {
  const len = docNumber.value.trim().length
  return docType.value === 'DNI' ? len === 8 : len === 11
})

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      errorMessage.value = ''
      resultData.value = null
      nextTick(() => {
        docInputRef.value?.focus()
      })
    }
  }
)

function setDocType(type) {
  docType.value = type
  docNumber.value = ''
  errorMessage.value = ''
  resultData.value = null
  nextTick(() => {
    docInputRef.value?.focus()
  })
}

function filterDigits() {
  docNumber.value = docNumber.value.replace(/[^0-9]/g, '')
  errorMessage.value = ''
}

async function executeLookup() {
  if (!isValidLength.value) {
    ui.warning(
      docType.value === 'DNI'
        ? 'El DNI debe tener exactamente 8 dígitos numéricos.'
        : 'El RUC debe tener exactamente 11 dígitos numéricos.'
    )
    return
  }

  loading.value = true
  errorMessage.value = ''
  resultData.value = null

  try {
    const { data } = await apiClient.get('/lookupDocument.php', {
      params: {
        type: docType.value,
        number: docNumber.value.trim(),
      },
    })

    if (data?.data) {
      resultData.value = {
        ...data.data,
        birth_date: data.data.birth_date || '',
        gender: data.data.gender || '',
      }
      ui.success(data.messages || 'Datos obtenidos correctamente.', 'Consulta Exitosa')
    } else {
      errorMessage.value = 'No se recibieron datos del documento ingresado.'
    }
  } catch (err) {
    errorMessage.value = extractErrorMessage(err)
  } finally {
    loading.value = false
  }
}

function useForOrderOnly() {
  if (!resultData.value) return
  emit('selected', { ...resultData.value })
  emit('close')
}

async function saveAndSelectClient() {
  if (!resultData.value) return

  // If already registered in local DB, select it directly
  if (resultData.value.already_registered && resultData.value.client_id) {
    emit('selected', { ...resultData.value })
    emit('close')
    return
  }

  // Validate fields before saving
  const unsafe = findUnsafeField({
    Nombre: resultData.value.name,
    Dirección: resultData.value.address,
    Teléfono: resultData.value.phone,
    Correo: resultData.value.email,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene caracteres no permitidos.`)
    return
  }

  if (resultData.value.email && !isValidEmail(resultData.value.email)) {
    ui.warning('El correo electrónico no tiene un formato válido.')
    return
  }

  savingClient.value = true
  try {
    const payload = {
      document_type: docType.value,
      document_number: docNumber.value.trim(),
      name: sanitizeInput(resultData.value.name),
      first_name: sanitizeInput(resultData.value.first_name || ''),
      last_name: sanitizeInput(resultData.value.last_name || ''),
      birth_date: resultData.value.birth_date || null,
      gender: resultData.value.gender || null,
      phone: sanitizeInput(resultData.value.phone || ''),
      email: sanitizeInput(resultData.value.email || ''),
      address: sanitizeInput(resultData.value.address || ''),
      department: resultData.value.department || '',
      province: resultData.value.province || '',
      district: resultData.value.district || '',
      ubigeo: resultData.value.ubigeo || '',
      client_type: 'client',
      status: 1,
    }

    const { data } = await apiClient.post('/createClient.php', payload)
    const newId = data?.clientId || null
    ui.success('Cliente registrado exitosamente en el padrón local.')

    emit('selected', {
      ...resultData.value,
      client_id: newId,
    })
    emit('close')
  } catch (err) {
    // If conflict 409 (already exists), re-fetch and select
    if (err.response?.status === 409) {
      ui.info('El cliente ya se encontraba registrado. Seleccionando...')
      emit('selected', { ...resultData.value })
      emit('close')
    } else {
      ui.error(extractErrorMessage(err))
    }
  } finally {
    savingClient.value = false
  }
}
</script>

