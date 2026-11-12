<template>
  <div
    v-if="modelValue"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto"
    @click.self="close"
  >
    <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden my-6 max-h-[92vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 bg-slate-50/70 shrink-0">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl font-bold shadow-xs transition"
            :class="form.operation_type === 'deposit' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
          >
            <ArrowDownLeft v-if="form.operation_type === 'deposit'" class="h-5 w-5" />
            <ArrowUpRight v-else class="h-5 w-5" />
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">
              Nueva Operación: {{ form.operation_type === 'deposit' ? 'Depósito' : 'Retiro' }}
            </h3>
            <p class="text-xs text-slate-500">
              Sucursal: <span class="font-semibold text-slate-700">{{ activeBranchName }}</span>
            </p>
          </div>
        </div>
        <button
          type="button"
          class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition"
          @click="close"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <!-- Form Body (Scrollable) -->
      <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto p-6 space-y-5">
        <!-- Central Cash Drawer Balance Banner -->
        <div
          class="flex items-center justify-between gap-3 rounded-xl p-3.5 border transition"
          :class="isInsufficientBalance ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-indigo-50/80 border-indigo-100 text-indigo-950'"
        >
          <div class="flex items-center gap-2.5">
            <Landmark class="h-5 w-5 shrink-0" :class="isInsufficientBalance ? 'text-rose-600' : 'text-indigo-600'" />
            <div class="text-xs">
              <p class="font-semibold text-slate-700">Saldo Disponible en Caja General:</p>
              <p class="text-base font-extrabold" :class="isInsufficientBalance ? 'text-rose-700' : 'text-indigo-700'">
                S/ {{ Number(centralCashBalance).toFixed(2) }}
              </p>
            </div>
          </div>
          <div v-if="isInsufficientBalance" class="text-right">
            <span class="inline-flex items-center gap-1 rounded-md bg-rose-200/70 px-2 py-0.5 text-[11px] font-bold text-rose-800">
              <AlertTriangle class="h-3.5 w-3.5" />
              Saldo Insuficiente
            </span>
            <p class="text-[11px] text-rose-600 mt-0.5">Se requiere S/ {{ Number(cashRequired).toFixed(2) }}</p>
          </div>
        </div>

        <!-- Operation Type Toggle -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
            Tipo de Operación *
          </label>
          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-bold border transition cursor-pointer"
              :class="
                form.operation_type === 'deposit'
                  ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                  : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
              "
              @click="form.operation_type = 'deposit'"
            >
              <ArrowDownLeft class="h-4 w-4" />
              Depósito (Ingreso)
            </button>
            <button
              type="button"
              class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-bold border transition cursor-pointer"
              :class="
                form.operation_type === 'withdrawal'
                  ? 'bg-rose-600 text-white border-rose-600 shadow-sm'
                  : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
              "
              @click="form.operation_type = 'withdrawal'"
            >
              <ArrowUpRight class="h-4 w-4" />
              Retiro (Egreso)
            </button>
          </div>
        </div>

        <!-- Client Selector Row: Select2 Input + Green Plus Button (RENIEC / SUNAT) -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
              Cliente *
            </label>
            <span v-if="activeClient" class="text-xs text-amber-600 font-semibold flex items-center gap-1">
              <Award class="h-3.5 w-3.5" />
              {{ activeClient.points_balance || 0 }} pts disponibles
            </span>
          </div>

          <div class="flex items-center gap-2">
            <!-- Custom Select2 Client Component (Queries local DB first) -->
            <div class="flex-1">
              <CustomSelect2Client
                v-model="form.client_id"
                :client="selectedClientObj"
                placeholder="Buscar cliente en BD por DNI, RUC o Nombre..."
                @select="selectClient"
                @clear="clearClient"
              />
            </div>

            <!-- Green Plus Button to Open / Toggle RENIEC Lookup Panel -->
            <button
              type="button"
              class="h-9 w-9 flex items-center justify-center rounded-lg bg-[#00a65a] hover:bg-[#008d4c] text-white active:scale-95 transition shadow-xs shrink-0 cursor-pointer"
              title="Consultar cliente por DNI/RUC ante RENIEC / SUNAT"
              @click="toggleReniecLookup"
            >
              <Plus class="h-4 w-4 stroke-[3]" />
            </button>
          </div>

          <!-- Integrated RENIEC / SUNAT Lookup & Register Panel (Inside same modal) -->
          <div
            v-if="showReniecLookup"
            class="rounded-xl border border-emerald-300 bg-emerald-50/50 p-4 space-y-3 transition-all shadow-xs"
          >
            <div class="flex items-center justify-between border-b border-emerald-200/70 pb-2">
              <div class="flex items-center gap-2">
                <span class="flex h-5 w-5 items-center justify-center rounded-md bg-emerald-600 text-[10px] font-bold text-white">
                  RE
                </span>
                <span class="text-xs font-bold text-emerald-950">
                  Consulta RENIEC / SUNAT — Registrar Nuevo Cliente
                </span>
              </div>
              <button
                type="button"
                class="rounded-md p-1 text-slate-400 hover:text-slate-600 hover:bg-emerald-100 transition"
                title="Cerrar panel de consulta"
                @click="showReniecLookup = false"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <!-- DNI vs RUC Tabs -->
            <div class="flex items-center gap-1.5 p-1 bg-white rounded-lg border border-emerald-200">
              <button
                type="button"
                class="flex-1 py-1.5 text-xs font-bold rounded transition cursor-pointer"
                :class="
                  reniecDocType === 'DNI'
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
                @click="setReniecDocType('DNI')"
              >
                DNI (8 dígitos - RENIEC)
              </button>
              <button
                type="button"
                class="flex-1 py-1.5 text-xs font-bold rounded transition cursor-pointer"
                :class="
                  reniecDocType === 'RUC'
                    ? 'bg-emerald-600 text-white shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                "
                @click="setReniecDocType('RUC')"
              >
                RUC (11 dígitos - SUNAT)
              </button>
            </div>

            <!-- Lookup Input & Query Button -->
            <div class="flex gap-2">
              <div class="relative flex-1">
                <input
                  v-model="reniecDocNumber"
                  type="text"
                  :maxlength="reniecDocType === 'DNI' ? 8 : 11"
                  :placeholder="reniecDocType === 'DNI' ? 'Ingresa los 8 dígitos del DNI...' : 'Ingresa los 11 dígitos del RUC...'"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-mono tracking-wider text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                  @input="filterReniecDigits"
                  @keydown.enter.prevent="executeReniecLookup"
                />
                <button
                  v-if="reniecDocNumber"
                  type="button"
                  class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600"
                  @click="reniecDocNumber = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>

              <button
                type="button"
                :disabled="reniecLoading || !isReniecDocValid"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition shadow-xs shrink-0 cursor-pointer"
                @click="executeReniecLookup"
              >
                <Loader2 v-if="reniecLoading" class="h-3.5 w-3.5 animate-spin" />
                <Search v-else class="h-3.5 w-3.5" />
                Consultar
              </button>
            </div>

            <!-- Loading Spinner -->
            <div v-if="reniecLoading" class="rounded-lg bg-white/70 p-4 text-center space-y-1 border border-emerald-100">
              <Loader2 class="h-5 w-5 animate-spin mx-auto text-emerald-600" />
              <p class="text-xs font-medium text-emerald-900">
                Consultando {{ reniecDocType === 'DNI' ? 'RENIEC' : 'SUNAT' }} vía Decolecta...
              </p>
            </div>

            <!-- Error Banner -->
            <div
              v-else-if="reniecError"
              class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800 flex items-start gap-2"
            >
              <AlertCircle class="h-4 w-4 shrink-0 text-rose-600 mt-0.5" />
              <div class="flex-1">
                <p class="font-bold">Error en la consulta</p>
                <p class="mt-0.5 text-rose-700">{{ reniecError }}</p>
              </div>
            </div>

            <!-- Result Box -->
            <div v-else-if="reniecResult" class="rounded-xl border border-emerald-200 bg-white p-3.5 space-y-3">
              <div class="flex items-center justify-between border-b border-emerald-100 pb-2">
                <div class="flex items-center gap-1.5">
                  <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                  <span class="text-xs font-bold text-emerald-900">
                    {{ reniecResult.status_text || 'Identidad validada con éxito' }}
                  </span>
                </div>
                <span
                  v-if="reniecResult.already_registered"
                  class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-200"
                >
                  Ya registrado en BD
                </span>
                <span
                  v-else
                  class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-200"
                >
                  Nuevo Cliente
                </span>
              </div>

              <!-- Editable Inputs -->
              <div class="space-y-2 text-xs">
                <div>
                  <label class="block font-bold text-slate-700 mb-0.5">
                    {{ reniecDocType === 'DNI' ? 'Nombre completo' : 'Razón Social' }} *
                  </label>
                  <input
                    v-model.trim="reniecResult.name"
                    type="text"
                    required
                    class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:outline-none"
                  />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div>
                    <label class="block font-semibold text-slate-700 mb-0.5">Teléfono / Celular</label>
                    <input
                      v-model.trim="reniecResult.phone"
                      type="text"
                      placeholder="Ej. 987654321"
                      class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-slate-700 mb-0.5">Correo electrónico</label>
                    <input
                      v-model.trim="reniecResult.email"
                      type="email"
                      placeholder="correo@ejemplo.com"
                      class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                </div>

                <div v-if="reniecDocType === 'DNI'" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div>
                    <label class="block font-semibold text-slate-700 mb-0.5">Fecha de Nacimiento</label>
                    <input
                      v-model="reniecResult.birth_date"
                      type="date"
                      class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"
                    />
                  </div>
                  <div>
                    <label class="block font-semibold text-slate-700 mb-0.5">Sexo</label>
                    <select
                      v-model="reniecResult.gender"
                      class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"
                    >
                      <option value="">No especificado</option>
                      <option value="MASCULINO">Masculino</option>
                      <option value="FEMENINO">Femenino</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="block font-semibold text-slate-700 mb-0.5">Dirección / Ubicación</label>
                  <input
                    v-model.trim="reniecResult.address"
                    type="text"
                    placeholder="Dirección fiscal o domiciliaria"
                    class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-900 focus:border-emerald-500 focus:outline-none"
                  />
                </div>
              </div>

              <!-- Action to Save and Select -->
              <div class="pt-2 border-t border-slate-100 flex items-center justify-end gap-2">
                <button
                  type="button"
                  class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer"
                  @click="reniecResult = null"
                >
                  Descartar
                </button>
                <button
                  type="button"
                  :disabled="reniecSaving"
                  class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-3.5 py-1.5 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                  @click="saveAndSelectReniecClient"
                >
                  <Loader2 v-if="reniecSaving" class="h-3.5 w-3.5 animate-spin" />
                  <UserPlus v-else class="h-3.5 w-3.5" />
                  {{ reniecResult.already_registered ? 'Seleccionar Cliente' : 'Guardar en BD y Seleccionar' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Selected Customer Details Card (Matching Screenshot / OrderFormView Style) -->
          <div
            v-if="activeClient"
            class="pt-1 text-xs text-slate-800 space-y-1 rounded-xl bg-slate-50/80 p-3 border border-slate-200/80"
          >
            <div class="flex items-baseline">
              <span class="w-24 text-right font-medium text-[#1f6497] pr-2.5 shrink-0">
                {{ (activeClient.document_type === 'OTRO' ? 'OTROS' : (activeClient.document_type || 'DNI')).toUpperCase() }}:
              </span>
              <span class="font-extrabold text-slate-900">
                {{ activeClient.document_number || '---' }}
              </span>
            </div>

            <div class="flex items-baseline">
              <span class="w-24 text-right font-medium text-[#1f6497] pr-2.5 shrink-0">
                Cliente:
              </span>
              <span class="font-bold text-slate-800">
                {{ activeClient.name }}
              </span>
            </div>

            <div class="flex items-baseline">
              <span class="w-24 text-right font-medium text-[#1f6497] pr-2.5 shrink-0">
                Puntos:
              </span>
              <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 font-bold text-amber-700 border border-amber-200">
                <Award class="h-3.5 w-3.5" />
                {{ activeClient.points_balance || 0 }} pts disponibles
              </span>
            </div>

            <div v-if="activeClient.phone" class="flex items-baseline">
              <span class="w-24 text-right font-medium text-[#1f6497] pr-2.5 shrink-0">
                Teléfono:
              </span>
              <span class="text-slate-700">
                {{ activeClient.phone }}
              </span>
            </div>

            <div v-if="activeClient.address" class="flex items-baseline">
              <span class="w-24 text-right font-medium text-[#1f6497] pr-2.5 shrink-0">
                Dirección:
              </span>
              <span class="text-slate-700">
                {{ activeClient.address }}
              </span>
            </div>
          </div>
        </div>

        <!-- Amount and Quick Chips -->
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">
            Monto de la Operación (S/) *
          </label>
          <div class="relative">
            <span class="absolute left-3.5 top-2.5 text-slate-400 font-bold text-sm">S/</span>
            <input
              v-model.number="form.amount"
              type="number"
              step="0.01"
              min="1"
              required
              placeholder="0.00"
              class="w-full rounded-xl border border-slate-300 pl-10 pr-4 py-2.5 text-base font-bold text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <!-- Quick chips -->
          <div class="flex flex-wrap gap-1.5 mt-2">
            <button
              v-for="chip in [50, 100, 200, 500, 1000]"
              :key="chip"
              type="button"
              class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 transition"
              @click="form.amount = chip"
            >
              +S/ {{ chip }}
            </button>
          </div>
        </div>

        <!-- Checkbox: Commission Included -->
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5">
          <label class="flex items-start gap-3 cursor-pointer">
            <input
              v-model="form.commission_included"
              type="checkbox"
              class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
            />
            <div class="text-xs">
              <span class="font-bold text-slate-800">
                El monto ingresado ya incluye la comisión (1 sol por cada 100 soles)
              </span>
              <p class="text-slate-500 mt-0.5 leading-relaxed">
                <span v-if="form.commission_included">
                  Activo: La comisión se descontará del monto ingresado (monto neto = monto - comisión).
                </span>
                <span v-else>
                  Inactivo: La comisión se cobra de manera adicional al monto base.
                </span>
              </p>
            </div>
          </label>
        </div>

        <!-- Real-Time Calculation Card -->
        <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-2.5 text-xs">
          <div class="flex justify-between text-slate-600">
            <span>Monto Base Ingresado:</span>
            <span class="font-bold text-slate-800">S/ {{ Number(form.amount || 0).toFixed(2) }}</span>
          </div>

          <div class="flex justify-between text-slate-600">
            <span class="flex items-center gap-1">
              Comisión (S/ 1.00 por cada S/ 100.00):
            </span>
            <span class="font-bold text-amber-700">+S/ {{ Number(commissionAmount).toFixed(2) }}</span>
          </div>

          <div class="flex justify-between text-slate-600">
            <span>Monto Neto (Acreditar / Entregar):</span>
            <span class="font-bold text-slate-900">S/ {{ Number(netAmount).toFixed(2) }}</span>
          </div>

          <div class="pt-2 border-t border-indigo-100/80 flex justify-between items-center text-sm">
            <span class="font-bold text-slate-900">
              {{ form.operation_type === 'deposit' ? 'Total a Cobrar al Cliente:' : 'Total Efectivo a Entregar:' }}
            </span>
            <span class="text-base font-extrabold text-indigo-700">
              S/ {{ Number(totalCustomerAmount).toFixed(2) }}
            </span>
          </div>

          <!-- Points preview -->
          <div class="pt-2 border-t border-indigo-100/80 flex items-center justify-between text-xs">
            <span class="flex items-center gap-1.5 font-semibold text-amber-800">
              <Gift class="h-4 w-4 text-amber-600" />
              Puntos a otorgar al cliente:
            </span>
            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 font-bold text-amber-800">
              +{{ pointsEarned }} puntos
            </span>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">
            Notas u Observaciones (Opcional)
          </label>
          <input
            v-model.trim="form.notes"
            type="text"
            maxlength="255"
            placeholder="Ej. Transferencia BCP, retiro de emergencia, etc."
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs focus:border-indigo-500 focus:outline-none"
          />
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            type="button"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
            @click="close"
          >
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="submitting || isInsufficientBalance || !form.client_id || form.amount <= 0"
            class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold text-white shadow-xs transition disabled:opacity-50 disabled:cursor-not-allowed"
            :class="form.operation_type === 'deposit' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'"
          >
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <span v-else>
              {{ form.operation_type === 'deposit' ? 'Registrar Depósito' : 'Registrar Retiro' }}
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import {
  X,
  ArrowDownLeft,
  ArrowUpRight,
  Landmark,
  Search,
  Award,
  AlertTriangle,
  Gift,
  Loader2,
  Plus,
  UserPlus,
  CheckCircle2,
  AlertCircle,
} from '@lucide/vue'
import CustomSelect2Client from '@/components/common/CustomSelect2Client.vue'
import apiClient, { extractErrorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useClientsStore } from '@/stores/clients'
import { useOperationsStore } from '@/stores/operations'
import { findUnsafeField, sanitizeInput, isValidEmail, sanitizeObject } from '@/utils/security'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'created'])

const auth = useAuthStore()
const clientsStore = useClientsStore()
const operationsStore = useOperationsStore()

const submitting = ref(false)
const selectedClientObj = ref(null)

// RENIEC / SUNAT Lookup State
const showReniecLookup = ref(false)
const reniecDocType = ref('DNI')
const reniecDocNumber = ref('')
const reniecLoading = ref(false)
const reniecSaving = ref(false)
const reniecError = ref('')
const reniecResult = ref(null)

const isReniecDocValid = computed(() => {
  const len = reniecDocNumber.value.trim().length
  return reniecDocType.value === 'DNI' ? len === 8 : len === 11
})

const form = reactive({
  client_id: '',
  operation_type: 'deposit',
  amount: '',
  commission_included: false,
  notes: '',
})

const activeBranchName = computed(() => auth.activeBranch?.branch_name || 'Sucursal')
const centralCashBalance = computed(() => operationsStore.centralCashBalance || 0)

const commissionRatePer100 = computed(() => {
  return Number(operationsStore.settings?.commission_rate_per_100 || 1.0)
})

const commissionAmount = computed(() => {
  const amt = Number(form.amount || 0)
  if (amt <= 0) return 0
  return Math.round(((amt / 100.0) * commissionRatePer100.value) * 100) / 100
})

const netAmount = computed(() => {
  const amt = Number(form.amount || 0)
  if (amt <= 0) return 0
  if (form.commission_included) {
    return Math.max(0, Math.round((amt - commissionAmount.value) * 100) / 100)
  }
  return amt
})

const totalCustomerAmount = computed(() => {
  const amt = Number(form.amount || 0)
  if (amt <= 0) return 0
  if (form.operation_type === 'deposit') {
    return form.commission_included ? amt : Math.round((amt + commissionAmount.value) * 100) / 100
  } else {
    // Withdrawal
    return form.commission_included ? netAmount.value : amt
  }
})

const cashRequired = computed(() => {
  if (form.operation_type !== 'withdrawal') return 0
  return form.commission_included ? netAmount.value : Number(form.amount || 0)
})

const isInsufficientBalance = computed(() => {
  if (form.operation_type !== 'withdrawal') return false
  return cashRequired.value > Number(centralCashBalance.value || 0)
})

const pointsEarned = computed(() => {
  const amt = Number(form.amount || 0)
  const ratioSoles = Number(operationsStore.settings?.points_ratio_soles || 10)
  const ratioPoints = Number(operationsStore.settings?.points_ratio_points || 1)
  if (amt <= 0 || ratioSoles <= 0) return 0
  return Math.floor(amt / ratioSoles) * ratioPoints
})

const activeClient = computed(() => {
  if (selectedClientObj.value && selectedClientObj.value.client_id === form.client_id) {
    return selectedClientObj.value
  }
  if (!form.client_id) return null
  return clientsStore.clients.find((c) => c.client_id === form.client_id) || null
})

function selectClient(client) {
  if (!client) return
  form.client_id = client.client_id
  selectedClientObj.value = client
}

function clearClient() {
  form.client_id = ''
  selectedClientObj.value = null
}

function setReniecDocType(type) {
  reniecDocType.value = type
  reniecDocNumber.value = ''
  reniecError.value = ''
  reniecResult.value = null
}

function filterReniecDigits() {
  reniecDocNumber.value = reniecDocNumber.value.replace(/[^0-9]/g, '')
  reniecError.value = ''
}

function toggleReniecLookup() {
  showReniecLookup.value = !showReniecLookup.value
  if (showReniecLookup.value) {
    reniecError.value = ''
    reniecResult.value = null
  }
}

async function executeReniecLookup() {
  if (!isReniecDocValid.value) {
    reniecError.value =
      reniecDocType.value === 'DNI'
        ? 'El DNI debe tener exactamente 8 dígitos numéricos.'
        : 'El RUC debe tener exactamente 11 dígitos numéricos.'
    return
  }

  reniecLoading.value = true
  reniecError.value = ''
  reniecResult.value = null

  try {
    const { data } = await apiClient.get('/lookupDocument.php', {
      params: {
        type: reniecDocType.value,
        number: reniecDocNumber.value.trim(),
      },
    })

    if (data?.data) {
      reniecResult.value = {
        ...data.data,
        birth_date: data.data.birth_date || '',
        gender: data.data.gender || '',
      }
    } else {
      reniecError.value = 'No se recibieron datos del documento ingresado.'
    }
  } catch (err) {
    reniecError.value = extractErrorMessage(err)
  } finally {
    reniecLoading.value = false
  }
}

async function saveAndSelectReniecClient() {
  if (!reniecResult.value) return

  // If already registered in local DB, select it directly
  if (reniecResult.value.already_registered && reniecResult.value.client_id) {
    selectClient(reniecResult.value)
    showReniecLookup.value = false
    reniecResult.value = null
    return
  }

  const unsafe = findUnsafeField({
    Nombre: reniecResult.value.name,
    Dirección: reniecResult.value.address,
    Teléfono: reniecResult.value.phone,
    Correo: reniecResult.value.email,
  })
  if (unsafe) {
    reniecError.value = `El campo "${unsafe}" contiene caracteres no permitidos.`
    return
  }

  if (reniecResult.value.email && !isValidEmail(reniecResult.value.email)) {
    reniecError.value = 'El correo electrónico no tiene un formato válido.'
    return
  }

  reniecSaving.value = true
  reniecError.value = ''

  try {
    const payload = {
      document_type: reniecDocType.value,
      document_number: reniecDocNumber.value.trim(),
      name: sanitizeInput(reniecResult.value.name),
      first_name: sanitizeInput(reniecResult.value.first_name || ''),
      last_name: sanitizeInput(reniecResult.value.last_name || ''),
      birth_date: reniecResult.value.birth_date || null,
      gender: reniecResult.value.gender || null,
      phone: sanitizeInput(reniecResult.value.phone || ''),
      email: sanitizeInput(reniecResult.value.email || ''),
      address: sanitizeInput(reniecResult.value.address || ''),
      department: reniecResult.value.department || '',
      province: reniecResult.value.province || '',
      district: reniecResult.value.district || '',
      ubigeo: reniecResult.value.ubigeo || '',
      client_type: 'client',
      status: 1,
    }

    const { data } = await apiClient.post('/createClient.php', payload)
    const newId = Number(data?.clientId) || null

    const createdClient = {
      ...reniecResult.value,
      client_id: newId,
      points_balance: 0,
      lifetime_points: 0,
    }

    if (!clientsStore.clients.some((c) => c.client_id === newId)) {
      clientsStore.clients.unshift(createdClient)
    }

    selectClient(createdClient)
    showReniecLookup.value = false
    reniecResult.value = null
    reniecDocNumber.value = ''
  } catch (err) {
    if (err.response?.status === 409) {
      selectClient(reniecResult.value)
      showReniecLookup.value = false
      reniecResult.value = null
    } else {
      reniecError.value = extractErrorMessage(err)
    }
  } finally {
    reniecSaving.value = false
  }
}

function close() {
  emit('update:modelValue', false)
  resetForm()
}

function resetForm() {
  form.client_id = ''
  form.operation_type = 'deposit'
  form.amount = ''
  form.commission_included = false
  form.notes = ''
  selectedClientObj.value = null
  showReniecLookup.value = false
  reniecDocNumber.value = ''
  reniecError.value = ''
  reniecResult.value = null
}

async function handleSubmit() {
  if (isInsufficientBalance.value) return
  if (!form.client_id || Number(form.amount) <= 0) return

  const unsafe = findUnsafeField({
    'Notas / Observaciones': form.notes,
  })
  if (unsafe) {
    alert(`El campo "${unsafe}" contiene caracteres o código no permitidos por seguridad.`)
    return
  }

  submitting.value = true
  try {
    const res = await operationsStore.createOperation(sanitizeObject({
      client_id: form.client_id,
      operation_type: form.operation_type,
      amount: form.amount,
      commission_included: form.commission_included,
      notes: form.notes,
    }))
    emit('created', res.operation)
    close()
  } catch (err) {
    // Parent or store handles error
  } finally {
    submitting.value = false
  }
}

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      if (clientsStore.clients.length === 0) {
        clientsStore.fetchClients()
      }
    }
  }
)
</script>

