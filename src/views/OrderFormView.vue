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
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
              <UserCheck class="h-4 w-4 text-indigo-600" />
              Información del Cliente
            </h3>
            <p class="text-xs text-slate-500">
              Busca en la base de datos con el selector inteligente o consulta en tiempo real ante RENIEC / SUNAT.
            </p>
          </div>

          <!-- Order Date Field (Right-aligned in header) -->
          <div class="flex items-center gap-2 shrink-0 text-xs">
            <label class="font-bold text-slate-600">Fecha de orden *</label>
            <input
              v-model="form.orderDate"
              type="date"
              required
              class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
        </div>

        <!-- Customer Search & Details Block (Matches exact reference screenshot) -->
        <div id="customer-box" class="space-y-1 max-w-2xl">
          <label class="block text-xs font-normal text-[#337ab7]">Cliente:</label>
          <div class="flex items-center gap-2">
            <!-- Custom Select2 Client Component (Zero NPM dependencies) -->
            <div class="flex-1">
              <CustomSelect2Client
                v-model="form.clientId"
                :client="selectedClientObj"
                placeholder="88888888 - PUBLICO GENERAL"
                @select="onClientSelect"
                @clear="onClientClear"
              />
            </div>

            <!-- Green Plus Button to Open RENIEC / SUNAT Lookup Modal (Matches Screenshot) -->
            <button
              type="button"
              class="h-[34px] w-[34px] flex items-center justify-center rounded-[4px] bg-[#00a65a] hover:bg-[#008d4c] text-white active:scale-95 transition shadow-xs shrink-0 cursor-pointer"
              title="Consultar cliente por DNI/RUC ante RENIEC / SUNAT"
              @click="openReniecModal = true"
            >
              <Plus class="h-4 w-4 stroke-[3]" />
            </button>
          </div>

          <!-- Selected Customer Details (Exact visual match from Screenshot) -->
          <ClientDetailDisplay
            :client="selectedClientObj"
            :divider="true"
          />
        </div>
      </div>

      <!-- ================= 2. PRODUCT & CUSTOM ITEMS SECTION ================= -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
              <ShoppingCart class="h-4 w-4 text-indigo-600" />
              Detalle de Productos y Servicios
            </h3>
            <p class="text-xs text-slate-500">
              Utiliza el buscador rápido para agregar ítems del catálogo o agrega ítems libres de texto personalizado.
            </p>
          </div>
        </div>

        <!-- DEDICATED PRODUCT SEARCH & QUICK ADD SECTION (SELECT2-LIKE) -->
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 space-y-2.5">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-indigo-950 flex items-center gap-2">
              <PackageSearch class="h-4 w-4 text-indigo-600" />
              Búsqueda de Productos (Catálogo)
            </label>
            <span class="text-[11px] font-medium text-slate-500">
              Al seleccionar un producto se agregará automáticamente a la tabla (máx. 5 resultados)
            </span>
          </div>

          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <div class="flex-1">
              <CustomSelect2Product
                v-model="quickProductId"
                placeholder="Escribe el nombre o código del producto para buscar y añadir a la lista..."
                clear-on-select
                @select="addProductToOrder"
              />
            </div>

            <!-- Retained Button for Adding Custom (Free-Text) Entry -->
            <button
              type="button"
              class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-800 hover:bg-amber-100 hover:border-amber-400 transition shadow-2xs shrink-0"
              @click="addCustomRow"
            >
              <FileEdit class="h-4 w-4 text-amber-600" />
              Añadir Ítem Libre / Servicio
            </button>
          </div>
        </div>

        <!-- TABLE OF LINE ITEMS -->
        <div class="space-y-3">
          <!-- Headers for Desktop -->
          <div class="hidden sm:grid grid-cols-12 gap-3 px-3 text-xs font-bold text-slate-500 uppercase tracking-wider">
            <div class="col-span-5">Producto / Concepto</div>
            <div class="col-span-2 text-right">Precio Unit. ({{ currencySymbol }})</div>
            <div class="col-span-2 text-center">Cantidad</div>
            <div class="col-span-2 text-right">Importe</div>
            <div class="col-span-1 text-center">Acción</div>
          </div>

          <!-- Empty state if no items -->
          <div
            v-if="form.items.length === 0"
            class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-xs text-slate-500 space-y-2"
          >
            <Package class="h-8 w-8 text-slate-300 mx-auto" />
            <p>No hay productos en la orden. Usa el buscador superior para agregar productos o el botón de ítem libre.</p>
          </div>

          <!-- Line Item Rows -->
          <div
            v-for="(row, idx) in form.items"
            :key="row.uid"
            class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center rounded-xl border p-3.5 transition"
            :class="row.isCustom ? 'border-amber-200 bg-amber-50/30' : 'border-slate-200 bg-slate-50/50'"
          >
            <!-- Column 1: Product / Custom Name -->
            <div class="sm:col-span-5">
              <!-- Custom Free-Text Item -->
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
                  placeholder="Ej. Servicio de delivery, Empaque especial, Mano de obra..."
                  class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                />
              </div>

              <!-- Catalog Product Item -->
              <div v-else class="space-y-1">
                <div class="flex items-center justify-between">
                  <label class="block text-xs font-bold text-slate-700">
                    Producto del Catálogo
                  </label>
                  <span
                    v-if="row.stock !== undefined"
                    class="text-[10px] font-semibold"
                    :class="row.stock > 5 ? 'text-emerald-700' : row.stock > 0 ? 'text-amber-700' : 'text-rose-600'"
                  >
                    Stock: {{ row.stock }} uds
                  </span>
                </div>
                <CustomSelect2Product
                  v-model="row.productId"
                  :initial-product-name="row.productNameFallback"
                  :initial-rate="row.rate"
                  :initial-stock="row.stock"
                  placeholder="Buscar o cambiar producto..."
                  @select="onProductSelect(row, $event)"
                  @clear="onProductClear(row)"
                />
              </div>
            </div>

            <!-- Column 2: Unit Price (Editable if custom, disabled if catalog) -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1 sm:hidden">
                Precio Unit. ({{ currencySymbol }}) *
              </label>
              <input
                v-if="row.isCustom"
                v-model.number="row.rate"
                type="number"
                step="0.01"
                min="0.01"
                required
                class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 text-right text-sm font-semibold text-slate-900 focus:border-amber-500 focus:outline-none"
              />
              <input
                v-else
                type="text"
                disabled
                :value="formatCurrency(row.rate)"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-right text-sm font-semibold text-slate-700"
              />
            </div>

            <!-- Column 3: Quantity -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1 sm:hidden">Cantidad *</label>
              <input
                v-model.number="row.quantity"
                type="number"
                min="1"
                required
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-center text-sm font-bold text-slate-900 focus:border-indigo-500 focus:outline-none"
              />
            </div>

            <!-- Column 4: Line Total -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1 sm:hidden">Importe</label>
              <input
                type="text"
                disabled
                :value="formatCurrency((row.rate || 0) * (row.quantity || 0))"
                class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-right text-sm font-bold text-indigo-700"
              />
            </div>

            <!-- Column 5: Remove Row Button -->
            <div class="sm:col-span-1 flex justify-end">
              <button
                type="button"
                class="rounded-xl border border-rose-200 bg-white p-2 text-rose-600 hover:bg-rose-50 hover:border-rose-300 transition"
                title="Quitar producto de la orden"
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

    <!-- RENIEC / SUNAT Client Lookup Modal -->
    <OrderClientLookupModal
      :open="openReniecModal"
      @close="openReniecModal = false"
      @selected="onClientFromModal"
    />

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
  User,
  UserCheck,
  ShoppingCart,
  FileEdit,
  AlertOctagon,
  Calculator,
  PackageSearch,
  Package,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useOrdersStore } from '@/stores/orders'
import { useUiStore } from '@/stores/ui'
import apiClient, { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField, sanitizeInput } from '@/utils/security'
import { checkOpenCashSession, promptOpenCashRegister } from '@/utils/cashRegisterPrompt'
import CustomSelect2Client from '@/components/common/CustomSelect2Client.vue'
import CustomSelect2Product from '@/components/common/CustomSelect2Product.vue'
import ClientDetailDisplay from '@/components/common/ClientDetailDisplay.vue'
import OrderClientLookupModal from '@/components/orders/OrderClientLookupModal.vue'
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

// Customer State & RENIEC/SUNAT Modal
const openReniecModal = ref(false)
const selectedClientObj = ref(null)

// Dedicated Product Search Bar State
const quickProductId = ref(0)

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

// Dedicated Product Search Selection -> Adds to Table
function addProductToOrder(product) {
  if (!product || !product.product_id) return

  // 1. If product is already in the table, increment its quantity
  const existing = form.items.find((i) => !i.isCustom && i.productId === product.product_id)
  if (existing) {
    existing.quantity = (Number(existing.quantity) || 0) + 1
    ui.info(`Cantidad incrementada a ${existing.quantity} para: ${product.product_name}`, 'Producto actualizado')
    return
  }

  // 2. If table only has 1 initial empty item, replace it
  if (form.items.length === 1 && !form.items[0].isCustom && form.items[0].productId === 0) {
    form.items[0].productId = product.product_id
    form.items[0].productNameFallback = product.product_name
    form.items[0].rate = Number(product.rate) || 0
    form.items[0].stock = Number(product.quantity) || 0
    form.items[0].quantity = 1
    ui.success(`Producto añadido: ${product.product_name}`, 'Producto agregado')
    return
  }

  // 3. Otherwise add new row
  form.items.push({
    uid: uidCounter++,
    isCustom: false,
    productId: product.product_id,
    customName: '',
    productNameFallback: product.product_name,
    rate: Number(product.rate) || 0,
    quantity: 1,
    stock: Number(product.quantity) || 0,
  })
  ui.success(`Producto añadido: ${product.product_name}`, 'Producto agregado')
}

// Client Selection Handlers
function onClientSelect(client) {
  if (!client) {
    onClientClear()
    return
  }
  selectedClientObj.value = client
  form.clientId = client.client_id || null
  form.clientName = client.name || ''
  form.clientContact = client.phone || (client.document_number ? `${client.document_type || 'DOC'}: ${client.document_number}` : '')
}

function onClientClear() {
  selectedClientObj.value = null
  form.clientId = null
  form.clientName = ''
  form.clientContact = ''
}

function onClientFromModal(client) {
  onClientSelect(client)
  ui.success(`Cliente "${client.name}" cargado en la orden.`, 'Cliente Seleccionado')
}

function calculateAge(birthDateStr) {
  if (!birthDateStr || birthDateStr === '0000-00-00') return 0
  const bDate = new Date(birthDateStr)
  if (isNaN(bDate.getTime())) return 0
  const today = new Date()
  let age = today.getFullYear() - bDate.getFullYear()
  const m = today.getMonth() - bDate.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < bDate.getDate())) {
    age--
  }
  return Math.max(0, age)
}

async function loadDefaultClient() {
  try {
    const { data } = await apiClient.get('/fetchClients.php', {
      params: { search: '88888888' },
    })
    const def = (data?.data || []).find((c) => c.document_number === '88888888' || c.name === 'PUBLICO GENERAL') || data?.data?.[0]
    if (def) {
      onClientSelect(def)
    }
  } catch {
    // ignore
  }
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
  selectedClientObj.value = null
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
  loadDefaultClient()
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

  if (!form.clientName || form.clientName.trim() === '') {
    ui.warning('Debes seleccionar o ingresar un cliente para la orden.')
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

  // Enforce open cash register for all commercial operations / sales
  if (!hasOpenCashSession.value) {
    await promptOpenCashRegister(router, auth.activeBranch?.branch_name, 'ventas y emisión de órdenes')
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

async function verifyCashSession(shouldPrompt = false) {
  checkingCashSession.value = true
  try {
    const res = await checkOpenCashSession()
    openCashSession.value = res.session
    if (!res.isOpen && shouldPrompt) {
      await promptOpenCashRegister(router, auth.activeBranch?.branch_name || res.branchName, 'ventas y emisión de órdenes')
    }
  } catch {
    openCashSession.value = null
  } finally {
    checkingCashSession.value = false
  }
}

onMounted(async () => {
  loadingInitial.value = true
  try {
    await verifyCashSession(true)
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

        // Fetch client details if client_id present
        if (data.order.client_id) {
          try {
            const { data: cData } = await apiClient.get('/fetchClients.php', {
              params: { search: '' },
            })
            const cFound = (cData?.data || []).find((c) => c.client_id === Number(data.order.client_id))
            if (cFound) {
              selectedClientObj.value = cFound
            } else {
              selectedClientObj.value = {
                client_id: data.order.client_id,
                name: data.order.client_name,
                phone: data.order.client_contact,
                document_type: 'OTROS',
                document_number: '-',
              }
            }
          } catch {
            // ignore
          }
        } else {
          selectedClientObj.value = {
            client_id: null,
            name: data.order.client_name,
            phone: data.order.client_contact,
            document_type: 'OTROS',
            document_number: '-',
          }
        }
      }
    } else {
      // New Order: Load default client (88888888 - PUBLICO GENERAL)
      await loadDefaultClient()
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
