<template>
  <div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg sm:text-xl font-bold text-slate-900">
            {{ isEdit ? `Editar Orden #${orderId}` : 'Punto de Venta · Nueva Orden' }}
          </h2>
          <span
            v-if="hasOpenCashSession"
            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20"
          >
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Caja Abierta: {{ openSessionCode }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20"
          >
            <AlertOctagon class="h-3.5 w-3.5 text-rose-600" />
            Caja Cerrada
          </span>
        </div>
        <p class="text-sm text-slate-500 mt-0.5">
          Selecciona productos o servicios personalizados, busca clientes por DNI/RUC y emite comprobantes.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <RouterLink
          to="/orders"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
        >
          <ArrowLeft class="h-4 w-4" />
          Listado de Órdenes
        </RouterLink>
      </div>
    </div>

    <!-- Mandatory Open Cash Register Warning Banner (if cash register is closed) -->
    <div
      v-if="!checkingCashSession && !hasOpenCashSession"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-rose-300 bg-rose-50 p-4 sm:p-5 text-rose-900 shadow-xs"
    >
      <div class="flex items-start gap-3 min-w-0">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
          <AlertOctagon class="h-6 w-6" />
        </div>
        <div>
          <h4 class="font-bold text-sm sm:text-base text-rose-950">
            Caja Registradora Cerrada en esta Sucursal
          </h4>
          <p class="text-xs sm:text-sm text-rose-700 mt-0.5 leading-relaxed">
            Para realizar cualquier actividad comercial u orden de venta, la caja registradora de la sucursal debe estar abierta.
            Por favor, realiza la apertura de turno antes de guardar la orden.
          </p>
        </div>
      </div>

      <RouterLink
        to="/cash-register"
        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs sm:text-sm font-bold text-white hover:bg-rose-700 shadow-sm transition shrink-0"
      >
        <Calculator class="h-4 w-4" />
        Abrir Turno de Caja →
      </RouterLink>
    </div>

    <div v-if="loadingInitial" class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500">
      <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
      Cargando datos de la orden...
    </div>

    <form v-else class="space-y-6" @submit.prevent="submitOrder">
      <!-- ================= 1. CUSTOMER INFORMATION SECTION ================= -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
              <UserCheck class="h-4 w-4 text-indigo-600" />
              Información del Cliente
            </h3>
            <p class="text-xs text-slate-500">
              Busca en el padrón local o consulta automáticamente en tiempo real ante RENIEC / SUNAT vía Decolecta.
            </p>
          </div>

          <!-- Quick status indicator if customer is picked from DB or API -->
          <div v-if="selectedClientBadge" class="flex items-center gap-1.5 text-xs">
            <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-bold text-indigo-700 border border-indigo-200">
              {{ selectedClientBadge }}
            </span>
            <button
              type="button"
              class="text-slate-400 hover:text-slate-600 text-xs font-semibold underline ml-1"
              @click="clearCustomerSelection"
            >
              Cambiar
            </button>
          </div>
        </div>

        <!-- Integrated Customer Search Bar (Select2-like for Clients) -->
        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3.5 space-y-2">
          <label class="block text-xs font-bold text-slate-700">
            Búsqueda Rápida de Cliente (DNI, RUC o Nombre):
          </label>
          <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
              <Search class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
              <input
                v-model="clientSearchQuery"
                type="text"
                placeholder="Ingresa 8 dígitos para DNI, 11 para RUC, o escribe el nombre..."
                class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-9 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                @input="onClientSearchInput"
                @keydown.enter.prevent="executeDecolectaLookup"
              />
              <button
                v-if="clientSearchQuery"
                type="button"
                class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 p-0.5"
                @click="clearClientSearch"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <!-- RENIEC / SUNAT Query Button (visible if query looks like document) -->
            <button
              v-if="isDocumentCandidate"
              type="button"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition shrink-0"
              :disabled="lookingUpDoc"
              @click="executeDecolectaLookup"
            >
              <Loader2 v-if="lookingUpDoc" class="h-3.5 w-3.5 animate-spin" />
              <Search v-else class="h-3.5 w-3.5" />
              Consultar {{ docTypeLabel }} (Decolecta)
            </button>
          </div>

          <!-- Dropdown Search Results from Local Client Directory -->
          <div
            v-if="clientSearchResults.length > 0 && showClientDropdown"
            class="mt-1 rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden divide-y divide-slate-100 z-20"
          >
            <div class="bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-500 flex justify-between">
              <span>Clientes encontrados en base de datos local:</span>
              <button type="button" class="text-slate-400 hover:text-slate-600" @click="showClientDropdown = false">Cerrar</button>
            </div>
            <div
              v-for="c in clientSearchResults"
              :key="c.client_id"
              class="flex items-center justify-between gap-3 px-3.5 py-2 cursor-pointer hover:bg-indigo-50 transition text-xs"
              @click="selectLocalClient(c)"
            >
              <div class="min-w-0">
                <p class="font-bold text-slate-900 truncate">{{ c.name }}</p>
                <p class="text-[11px] text-slate-500">
                  <span v-if="c.document_number">{{ c.document_type || 'DOC' }}: {{ c.document_number }} · </span>
                  <span v-if="c.phone">Tel: {{ c.phone }}</span>
                </p>
              </div>
              <div class="text-right shrink-0">
                <span class="rounded-md bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700">
                  {{ c.points_balance || 0 }} pts
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer Form Fields (Auto-filled or manual) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fecha de orden *</label>
            <input
              v-model="form.orderDate"
              type="date"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Nombre / Razón Social del cliente *
            </label>
            <input
              v-model.trim="form.clientName"
              type="text"
              required
              placeholder="Nombre completo o razón social"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Teléfono / Contacto *
            </label>
            <input
              v-model.trim="form.clientContact"
              type="text"
              required
              placeholder="Ej. +51 987654321"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
        </div>
      </div>

      <!-- ================= 2. PRODUCT & CUSTOM ITEMS TABLE ================= -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
              <ShoppingCart class="h-4 w-4 text-indigo-600" />
              Detalle de Productos y Servicios
            </h3>
            <p class="text-xs text-slate-500">
              Busca en el catálogo con el selector rápido (máx. 5 resultados) o agrega ítems libres de texto personalizado.
            </p>
          </div>

          <!-- Row Adding Buttons -->
          <div class="flex flex-wrap items-center gap-2">
            <!-- Button 1: Add Catalog Product -->
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition shadow-2xs"
              @click="addProductRow"
            >
              <Plus class="h-3.5 w-3.5" />
              Añadir Producto (Catálogo)
            </button>

            <!-- Button 2: Add Custom Free-Text Item (Requirement) -->
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition shadow-2xs"
              @click="addCustomRow"
            >
              <FileEdit class="h-3.5 w-3.5 text-amber-600" />
              Añadir Ítem Libre / Servicio
            </button>
          </div>
        </div>

        <!-- Table Line Items -->
        <div class="space-y-3">
          <div
            v-for="(row, idx) in form.items"
            :key="row.uid"
            class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end rounded-xl border p-3.5 transition"
            :class="row.isCustom ? 'border-amber-200 bg-amber-50/30' : 'border-slate-200 bg-slate-50/50'"
          >
            <!-- Column 1: Product Selector (Custom Select2 Component) OR Free-Text Input -->
            <div class="sm:col-span-5">
              <!-- Case A: Free-Text Custom Item -->
              <div v-if="row.isCustom" class="space-y-1">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-bold text-amber-800">
                    Concepto / Ítem Personalizado *
                  </label>
                  <span class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">
                    Libre / Servicio
                  </span>
                </div>
                <input
                  v-model.trim="row.customName"
                  type="text"
                  required
                  placeholder="Ej. Servicio de delivery, Mano de obra, Empaque especial..."
                  class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                />
              </div>

              <!-- Case B: Standard Catalog Product (Custom Select2 Component, Max 5 results) -->
              <div v-else class="space-y-1">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-bold text-slate-700">
                    Producto (Catálogo) *
                  </label>
                  <span class="text-[10px] text-slate-400">Selector inteligente</span>
                </div>
                <CustomSelect2Product
                  v-model="row.productId"
                  :initial-product-name="row.productNameFallback"
                  :initial-rate="row.rate"
                  :initial-stock="row.stock"
                  placeholder="Buscar producto (máx. 5 resultados)..."
                  @select="onProductSelect(row, $event)"
                  @clear="onProductClear(row)"
                />
              </div>
            </div>

            <!-- Column 2: Unit Price (Editable for Custom Item, Fixed for Catalog) -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">
                Precio Unit. ({{ currencySymbol }}) *
              </label>
              <!-- If custom item, allow editing rate -->
              <input
                v-if="row.isCustom"
                v-model.number="row.rate"
                type="number"
                step="0.01"
                min="0.01"
                required
                class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 focus:border-amber-500 focus:outline-none"
              />
              <!-- Otherwise read-only product catalog price -->
              <input
                v-else
                type="text"
                disabled
                :value="formatCurrency(row.rate)"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-700"
              />
            </div>

            <!-- Column 3: Quantity -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Cantidad *</label>
              <input
                v-model.number="row.quantity"
                type="number"
                min="1"
                required
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              />
            </div>

            <!-- Column 4: Line Total -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Importe</label>
              <input
                type="text"
                disabled
                :value="formatCurrency((row.rate || 0) * (row.quantity || 0))"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm font-bold text-slate-900"
              />
            </div>

            <!-- Column 5: Remove Row Button -->
            <div class="sm:col-span-1 flex justify-end">
              <button
                type="button"
                class="rounded-xl border border-rose-200 bg-white p-2 text-rose-600 hover:bg-rose-50 disabled:opacity-40 transition"
                :disabled="form.items.length <= 1"
                title="Quitar fila"
                @click="removeRow(idx)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= 3. TOTALS & PAYMENT CARD ================= -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Totals Breakdown -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-3.5">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Resumen de importes</h3>

          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Sub total</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(subTotal) }}</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">IVA / IGV ({{ taxRate }}%)</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(vat) }}</span>
          </div>
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Total neto</span>
            <span class="font-semibold text-slate-900">{{ formatCurrency(totalAmount) }}</span>
          </div>
          <div class="flex items-center justify-between gap-4 text-sm pt-1">
            <label for="discount" class="text-slate-600">Descuento ({{ currencySymbol }})</label>
            <input
              id="discount"
              v-model.number="form.discount"
              type="number"
              step="0.01"
              min="0"
              class="w-36 rounded-xl border border-slate-300 px-3 py-1.5 text-right text-sm focus:border-indigo-500 focus:outline-none"
            />
          </div>
          <div class="border-t border-slate-200 pt-3 flex items-center justify-between">
            <span class="text-base font-bold text-slate-900">Total a pagar</span>
            <span class="text-xl font-extrabold text-indigo-700">{{ formatCurrency(grandTotal) }}</span>
          </div>
        </div>

        <!-- Payment Details & Submit -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Detalles de pago</h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Monto pagado ({{ currencySymbol }}) *</label>
              <input
                v-model.number="form.paid"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Saldo pendiente</label>
              <input
                type="text"
                disabled
                :value="formatCurrency(dueAmount)"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3.5 py-2 text-sm font-bold"
                :class="dueAmount > 0 ? 'text-amber-700' : 'text-emerald-700'"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Método de pago *</label>
              <select
                v-model.number="form.paymentType"
                required
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              >
                <option :value="2">Efectivo (Gaveta de Caja)</option>
                <option :value="3">Tarjeta de crédito / débito</option>
                <option :value="1">Cheque / Transferencia</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado del pago *</label>
              <select
                v-model.number="form.paymentStatus"
                required
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
              >
                <option :value="1">Pago completo</option>
                <option :value="2">Pago por adelantado</option>
                <option :value="3">No pagado</option>
              </select>
            </div>
          </div>

          <!-- Bottom Action Buttons -->
          <div class="pt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100">
            <button
              v-if="!isEdit"
              type="button"
              class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
              @click="resetForm"
            >
              Reiniciar Formulario
            </button>
            <div class="flex items-center gap-3 ml-auto">
              <button
                type="submit"
                :disabled="saving || !hasOpenCashSession"
                class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 shadow-sm transition"
              >
                <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
                <CheckCircle2 v-else class="h-4 w-4" />
                {{ isEdit ? 'Actualizar Orden' : 'Guardar y Emitir Comprobante' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </form>

    <!-- Multi-Format Receipt Print Modal (Ticket, A5, A4) -->
    <OrderReceiptModal
      :open="showReceiptModal"
      :order-data="completedPrintData"
      @close="onReceiptModalClose"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Plus,
  Trash2,
  Loader2,
  CheckCircle2,
  Search,
  X,
  UserCheck,
  ShoppingCart,
  FileEdit,
  AlertOctagon,
  Calculator,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useOrdersStore } from '@/stores/orders'
import { useUiStore } from '@/stores/ui'
import apiClient, { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField, sanitizeInput } from '@/utils/security'
import CustomSelect2Product from '@/components/common/CustomSelect2Product.vue'
import OrderReceiptModal from '@/components/orders/OrderReceiptModal.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useOrdersStore()
const ui = useUiStore()

const taxRate = computed(() => Number(auth.activeBranch?.tax_rate ?? 13))
const currencySymbol = computed(() => auth.activeBranch?.currency_symbol || '$')

const orderId = computed(() => (route.params.id ? Number(route.params.id) : null))
const isEdit = computed(() => Boolean(orderId.value))

const loadingInitial = ref(false)
const saving = ref(false)
let uidCounter = 1

// Cash Register Enforcement State
const openCashSession = ref(null)
const checkingCashSession = ref(true)
const hasOpenCashSession = computed(() => Boolean(openCashSession.value))
const openSessionCode = computed(() => openCashSession.value?.session_code || '')

// Customer Search & RENIEC/SUNAT State
const clientSearchQuery = ref('')
const clientSearchResults = ref([])
const showClientDropdown = ref(false)
const lookingUpDoc = ref(false)
const selectedClientBadge = ref('')
let clientSearchTimer = null

const isDocumentCandidate = computed(() => {
  const q = clientSearchQuery.value.trim()
  return /^\d{8}$/.test(q) || /^\d{11}$/.test(q)
})

const docTypeLabel = computed(() => {
  const q = clientSearchQuery.value.trim()
  if (/^\d{8}$/.test(q)) return 'RENIEC (DNI)'
  if (/^\d{11}$/.test(q)) return 'SUNAT (RUC)'
  return 'Documento'
})

// Receipt Modal State
const completedPrintData = ref(null)
const showReceiptModal = ref(false)

const form = reactive({
  orderDate: new Date().toISOString().slice(0, 10),
  clientId: null,
  clientName: '',
  clientContact: '',
  discount: 0,
  paid: 0,
  paymentType: 2,
  paymentStatus: 1,
  items: [
    {
      uid: uidCounter++,
      isCustom: false,
      productId: 0,
      customName: '',
      productNameFallback: '',
      rate: 0,
      quantity: 1,
      stock: 0,
    },
  ],
})

const subTotal = computed(() =>
  Math.round(
    form.items.reduce((sum, item) => sum + (Number(item.rate) || 0) * (Number(item.quantity) || 0), 0) *
      100
  ) / 100
)

const vat = computed(() => Math.round(subTotal.value * (taxRate.value / 100) * 100) / 100)
const totalAmount = computed(() => Math.round((subTotal.value + vat.value) * 100) / 100)
const grandTotal = computed(() =>
  Math.round(Math.max(0, totalAmount.value - (Number(form.discount) || 0)) * 100) / 100
)
const dueAmount = computed(() =>
  Math.round(Math.max(0, grandTotal.value - (Number(form.paid) || 0)) * 100) / 100
)

// Add regular catalog product row
function addProductRow() {
  form.items.push({
    uid: uidCounter++,
    isCustom: false,
    productId: 0,
    customName: '',
    productNameFallback: '',
    rate: 0,
    quantity: 1,
    stock: 0,
  })
}

// Add free-text custom item row (Requirement)
function addCustomRow() {
  form.items.push({
    uid: uidCounter++,
    isCustom: true,
    productId: 0,
    customName: '',
    productNameFallback: '',
    rate: 0,
    quantity: 1,
    stock: 999,
  })
}

function removeRow(index) {
  if (form.items.length > 1) {
    form.items.splice(index, 1)
  }
}

function onProductSelect(row, product) {
  row.productId = product.product_id
  row.rate = product.rate
  row.productNameFallback = product.product_name
  row.stock = product.quantity
  if (!row.quantity || row.quantity < 1) {
    row.quantity = 1
  }
}

function onProductClear(row) {
  row.productId = 0
  row.rate = 0
  row.productNameFallback = ''
  row.stock = 0
}

// Customer Search & Decolecta Integration
function onClientSearchInput() {
  const query = clientSearchQuery.value.trim()
  if (!query) {
    clientSearchResults.value = []
    showClientDropdown.value = false
    return
  }

  if (clientSearchTimer) clearTimeout(clientSearchTimer)
  clientSearchTimer = setTimeout(async () => {
    try {
      const { data } = await apiClient.get('/fetchClients.php', {
        params: { search: query },
      })
      clientSearchResults.value = (data?.data || []).slice(0, 5)
      showClientDropdown.value = clientSearchResults.value.length > 0
    } catch {
      clientSearchResults.value = []
    }
  }, 220)
}

function selectLocalClient(client) {
  form.clientId = client.client_id
  form.clientName = client.name
  form.clientContact = client.phone || (client.document_number ? `${client.document_type || 'DOC'}: ${client.document_number}` : '')
  selectedClientBadge.value = `Cliente Local (${client.document_number || 'Registrado'})`
  clientSearchQuery.value = ''
  clientSearchResults.value = []
  showClientDropdown.value = false
  ui.info(`Cliente seleccionado: ${client.name}`, 'Cliente cargado')
}

async function executeDecolectaLookup() {
  const q = clientSearchQuery.value.trim().replace(/[^0-9]/g, '')
  if (q.length !== 8 && q.length !== 11) {
    ui.warning('Ingresa un número de DNI válido (8 dígitos) o RUC (11 dígitos).')
    return
  }

  lookingUpDoc.value = true
  try {
    const docType = q.length === 8 ? 'DNI' : 'RUC'
    const { data } = await apiClient.get('/lookupDocument.php', {
      params: { type: docType, number: q },
    })

    if (data?.data) {
      const c = data.data
      form.clientId = c.client_id || null
      form.clientName = c.name || ''
      form.clientContact = c.phone || `${c.document_type || docType}: ${c.document_number || q}`
      selectedClientBadge.value = data.source === 'local_database' ? `Padrón Local (${q})` : `Consulta RENIEC/SUNAT (${q})`
      clientSearchQuery.value = ''
      showClientDropdown.value = false
      ui.success(`Datos obtenidos para: ${form.clientName}`, 'Consulta Exitosa')
    }
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    lookingUpDoc.value = false
  }
}

function clearCustomerSelection() {
  form.clientId = null
  selectedClientBadge.value = ''
  clientSearchQuery.value = ''
}

function clearClientSearch() {
  clientSearchQuery.value = ''
  clientSearchResults.value = []
  showClientDropdown.value = false
}

function resetForm() {
  form.orderDate = new Date().toISOString().slice(0, 10)
  form.clientId = null
  form.clientName = ''
  form.clientContact = ''
  form.discount = 0
  form.paid = 0
  form.paymentType = 2
  form.paymentStatus = 1
  selectedClientBadge.value = ''
  form.items = [
    {
      uid: uidCounter++,
      isCustom: false,
      productId: 0,
      customName: '',
      productNameFallback: '',
      rate: 0,
      quantity: 1,
      stock: 0,
    },
  ]
}

async function submitOrder() {
  // Check Cash Register Open Status
  if (!hasOpenCashSession.value) {
    ui.error(
      'Para realizar cualquier actividad comercial u orden de venta, la caja registradora de la sucursal debe estar abierta.',
      'Caja Registradora Cerrada'
    )
    return
  }

  const unsafe = findUnsafeField({
    'Nombre del cliente': form.clientName,
    'Teléfono del cliente': form.clientContact,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }

  const validItems = form.items.filter((i) => {
    if (i.isCustom) {
      return i.customName && i.customName.trim().length > 0 && Number(i.rate) > 0 && Number(i.quantity) > 0
    }
    return i.productId > 0 && Number(i.quantity) > 0
  })

  for (const item of validItems) {
    if (item.isCustom && item.customName) {
      const unsafeItem = findUnsafeField({ 'Descripción personalizada': item.customName })
      if (unsafeItem) {
        ui.error(`El ítem personalizado "${item.customName}" contiene caracteres o código no permitidos.`)
        return
      }
    }
  }

  if (validItems.length === 0) {
    ui.warning('Debes seleccionar al menos un producto o agregar un ítem personalizado con precio válido.')
    return
  }

  saving.value = true
  try {
    const payload = {
      orderDate: form.orderDate,
      clientId: form.clientId,
      clientName: sanitizeInput(form.clientName),
      clientContact: sanitizeInput(form.clientContact),
      discount: Number(form.discount) || 0,
      paid: Number(form.paid) || 0,
      paymentType: form.paymentType,
      paymentStatus: form.paymentStatus,
      items: validItems.map((i) => ({
        isCustom: i.isCustom,
        productId: i.productId,
        customName: i.customName ? sanitizeInput(i.customName) : '',
        rate: Number(i.rate) || 0,
        quantity: i.quantity,
      })),
    }

    if (isEdit.value) {
      await store.updateOrder({ orderId: orderId.value, ...payload })
      ui.success('Orden actualizada exitosamente.')
      router.push('/orders')
    } else {
      const res = await store.createOrder(payload)
      ui.success('Orden creada exitosamente.')

      // Fetch print receipt data in various formats (Ticket, A5, A4) upon completing sale
      if (res?.order_id) {
        try {
          const printPayload = await store.fetchPrintOrder(res.order_id)
          completedPrintData.value = printPayload
          showReceiptModal.value = true
        } catch {
          router.push('/orders')
        }
      } else {
        router.push('/orders')
      }
    }
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

function onReceiptModalClose() {
  showReceiptModal.value = false
  router.push('/orders')
}

async function verifyCashSession() {
  checkingCashSession.value = true
  try {
    const { data } = await apiClient.get('/fetchCashSessions.php')
    openCashSession.value = (data?.openSessions && data.openSessions.length > 0)
      ? data.openSessions[0]
      : (data?.active_session || null)
  } catch {
    openCashSession.value = null
  } finally {
    checkingCashSession.value = false
  }
}

onMounted(async () => {
  loadingInitial.value = true
  try {
    await verifyCashSession()
    await store.fetchAvailableProducts(isEdit.value)

    if (isEdit.value) {
      const data = await store.fetchOrderById(orderId.value)
      if (data?.order) {
        form.orderDate = data.order.order_date
        form.clientId = data.order.client_id || null
        form.clientName = data.order.client_name
        form.clientContact = data.order.client_contact
        form.discount = data.order.discount
        form.paid = data.order.paid
        form.paymentType = data.order.payment_type
        form.paymentStatus = data.order.payment_status
        form.items = (data.items || []).map((i) => ({
          uid: uidCounter++,
          isCustom: i.product_id === 0,
          productId: i.product_id,
          customName: i.product_id === 0 ? i.product_name : '',
          productNameFallback: i.product_name,
          rate: i.rate,
          quantity: i.quantity,
          stock: i.available_stock || 0,
        }))
        if (form.items.length === 0) {
          addProductRow()
        }
      }
    }
  } catch (err) {
    if (err?.response?.status === 404) {
      router.replace({ name: 'error-page', params: { code: '404' } })
      return
    }
    ui.error(extractErrorMessage(err))
  } finally {
    loadingInitial.value = false
  }
})
</script>
