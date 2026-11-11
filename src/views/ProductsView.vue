<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
      <div class="flex flex-col sm:flex-row gap-3 flex-1">
        <div class="relative flex-1 max-w-sm">
          <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            v-model.trim="search"
            type="text"
            placeholder="Buscar producto, fabricante o categoría..."
            class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
        >
          <option value="all">Todos los estados</option>
          <option value="1">Disponibles</option>
          <option value="2">No disponibles</option>
          <option value="low">Stock bajo (≤ 3)</option>
        </select>
      </div>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition shrink-0"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
        Agregar producto
      </button>
    </div>

    <!-- Content Card -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
      <div v-if="loading && items.length === 0" class="p-10 text-center text-sm text-slate-500">
        <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
        Cargando productos...
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center text-sm text-slate-500">
        No se encontraron productos.
      </div>

      <template v-else>
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
              <tr>
                <th class="px-5 py-3.5">Producto</th>
                <th class="px-5 py-3.5">Precio</th>
                <th class="px-5 py-3.5">Stock</th>
                <th class="px-5 py-3.5">Fabricante</th>
                <th class="px-5 py-3.5">Categoría</th>
                <th class="px-5 py-3.5">Estado</th>
                <th class="px-5 py-3.5 text-right">Opciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in filtered" :key="item.product_id" class="hover:bg-slate-50/70 transition">
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <img
                      :src="imageSrc(item.product_image)"
                      :alt="item.product_name"
                      class="h-11 w-11 rounded-xl object-cover border border-slate-200 bg-slate-100 shrink-0"
                    />
                    <span class="font-semibold text-slate-900">{{ item.product_name }}</span>
                  </div>
                </td>
                <td class="px-5 py-3.5 font-semibold text-slate-800">{{ formatCurrency(item.rate) }}</td>
                <td class="px-5 py-3.5">
                  <StatusBadge
                    :variant="item.quantity <= 3 ? 'danger' : 'neutral'"
                    :label="`${item.quantity} uds`"
                  />
                </td>
                <td class="px-5 py-3.5 text-slate-600">{{ item.brand_name }}</td>
                <td class="px-5 py-3.5 text-slate-600">{{ item.categories_name }}</td>
                <td class="px-5 py-3.5">
                  <StatusBadge
                    :variant="item.active === 1 ? 'success' : 'danger'"
                    :label="item.active === 1 ? 'Disponible' : 'No disponible'"
                  />
                </td>
                <td class="px-5 py-3.5 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1"
                      @click="openEdit(item)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                      Editar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 inline-flex items-center gap-1"
                      @click="askDelete(item)"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="item in filtered" :key="item.product_id" class="p-4 space-y-3">
            <div class="flex items-start gap-3">
              <img
                :src="imageSrc(item.product_image)"
                :alt="item.product_name"
                class="h-14 w-14 rounded-xl object-cover border border-slate-200 bg-slate-100 shrink-0"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                  <p class="font-semibold text-slate-900 truncate">{{ item.product_name }}</p>
                  <StatusBadge
                    :variant="item.active === 1 ? 'success' : 'danger'"
                    :label="item.active === 1 ? 'Disponible' : 'No disponible'"
                  />
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ item.brand_name }} · {{ item.categories_name }}
                </p>
                <div class="flex items-center gap-3 mt-1.5 text-sm">
                  <span class="font-bold text-slate-900">{{ formatCurrency(item.rate) }}</span>
                  <span class="text-xs" :class="item.quantity <= 3 ? 'text-rose-600 font-semibold' : 'text-slate-500'">
                    Stock: {{ item.quantity }} uds
                  </span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 inline-flex items-center justify-center gap-1.5"
                @click="openEdit(item)"
              >
                <Pencil class="h-3.5 w-3.5" />
                Editar
              </button>
              <button
                type="button"
                class="flex-1 rounded-lg border border-rose-200 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 inline-flex items-center justify-center gap-1.5"
                @click="askDelete(item)"
              >
                <Trash2 class="h-3.5 w-3.5" />
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Create / Edit Modal -->
    <BaseModal
      :open="modalOpen"
      :title="editingId ? 'Editar producto' : 'Agregar producto'"
      size="lg"
      @close="modalOpen = false"
    >
      <div v-if="editingId" class="flex border-b border-slate-200 mb-4 gap-4 text-sm font-medium">
        <button
          type="button"
          class="pb-2.5 border-b-2 transition"
          :class="editTab === 'info' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="editTab = 'info'"
        >
          Información del producto
        </button>
        <button
          type="button"
          class="pb-2.5 border-b-2 transition"
          :class="editTab === 'photo' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="editTab = 'photo'"
        >
          Imagen
        </button>
      </div>

      <!-- Info Form (Create or Edit Info) -->
      <form
        v-if="!editingId || editTab === 'info'"
        id="productForm"
        class="space-y-4"
        @submit.prevent="submitProductForm"
      >
        <div v-if="!editingId">
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Imagen del producto (opcional)</label>
          <div class="flex items-center gap-4">
            <img
              :src="previewUrl || '/assests/images/photo_default.png'"
              alt="Vista previa"
              class="h-16 w-16 rounded-xl object-cover border border-slate-200 bg-slate-100"
            />
            <input
              type="file"
              accept="image/*"
              class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
              @change="onFileSelect"
            />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre del producto *</label>
            <input
              v-model.trim="form.name"
              type="text"
              required
              placeholder="Ej. Camisa formal para hombre"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Stock *</label>
            <input
              v-model.number="form.quantity"
              type="number"
              min="0"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Precio ($) *</label>
            <input
              v-model.number="form.rate"
              type="number"
              step="0.01"
              min="0"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Fabricante *</label>
            <select
              v-model.number="form.brandId"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option :value="0" disabled>-- Selecciona --</option>
              <option v-for="b in activeBrands" :key="b.brand_id" :value="b.brand_id">
                {{ b.brand_name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Categoría *</label>
            <select
              v-model.number="form.categoriesId"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option :value="0" disabled>-- Selecciona --</option>
              <option v-for="c in activeCategories" :key="c.categories_id" :value="c.categories_id">
                {{ c.categories_name }}
              </option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado *</label>
            <select
              v-model.number="form.status"
              required
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option :value="1">Disponible</option>
              <option :value="2">No disponible</option>
            </select>
          </div>
        </div>
      </form>

      <!-- Edit Photo Tab -->
      <form v-else id="photoForm" class="space-y-4" @submit.prevent="submitPhotoForm">
        <div class="flex flex-col items-center gap-4">
          <img
            :src="previewUrl || imageSrc(currentImageUrl)"
            alt="Imagen actual"
            class="h-44 w-44 rounded-2xl object-cover border border-slate-200 bg-slate-100"
          />
          <input
            type="file"
            accept="image/*"
            required
            class="block w-full text-xs text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
            @change="onFileSelect"
          />
        </div>
      </form>

      <template #footer>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
          @click="modalOpen = false"
        >
          Cerrar
        </button>
        <button
          v-if="!editingId || editTab === 'info'"
          type="submit"
          form="productForm"
          :disabled="saving"
          class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
        >
          <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
          Guardar cambios
        </button>
        <button
          v-else
          type="submit"
          form="photoForm"
          :disabled="saving || !selectedFile"
          class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
        >
          <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
          Actualizar imagen
        </button>
      </template>
    </BaseModal>

    <ConfirmModal
      :open="Boolean(deletingItem)"
      title="Eliminar producto"
      :message="`¿Realmente deseas eliminar el producto '${deletingItem?.product_name || ''}'?`"
      :loading="deleting"
      @close="deletingItem = null"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { Search, Plus, Pencil, Trash2, Loader2 } from '@lucide/vue'
import { useProductsStore } from '@/stores/products'
import { useBrandsStore } from '@/stores/brands'
import { useCategoriesStore } from '@/stores/categories'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency } from '@/utils/formatters'
import { findUnsafeField } from '@/utils/security'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

const productsStore = useProductsStore()
const brandsStore = useBrandsStore()
const categoriesStore = useCategoriesStore()
const ui = useUiStore()

const { items, loading } = storeToRefs(productsStore)

const search = ref('')
const statusFilter = ref('all')
const modalOpen = ref(false)
const editingId = ref(null)
const editTab = ref('info')
const saving = ref(false)
const deletingItem = ref(null)
const deleting = ref(false)
const selectedFile = ref(null)
const previewUrl = ref('')
const currentImageUrl = ref('')

const form = reactive({
  name: '',
  quantity: 1,
  rate: 0,
  brandId: 0,
  categoriesId: 0,
  status: 1,
})

const activeBrands = computed(() =>
  brandsStore.items.filter((b) => b.brand_active === 1 || b.brand_id === form.brandId)
)
const activeCategories = computed(() =>
  categoriesStore.items.filter((c) => c.categories_active === 1 || c.categories_id === form.categoriesId)
)

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  return items.value.filter((p) => {
    const matchesSearch =
      !q ||
      p.product_name.toLowerCase().includes(q) ||
      p.brand_name.toLowerCase().includes(q) ||
      p.categories_name.toLowerCase().includes(q)

    if (!matchesSearch) return false
    if (statusFilter.value === '1') return p.active === 1
    if (statusFilter.value === '2') return p.active === 2
    if (statusFilter.value === 'low') return p.quantity <= 3
    return true
  })
})

function imageSrc(path) {
  if (!path) return '/assests/images/photo_default.png'
  return path.startsWith('/') ? path : `/${path}`
}

function revokePreviewUrl() {
  if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(previewUrl.value)
  }
  previewUrl.value = ''
}

function onFileSelect(e) {
  const file = e.target.files?.[0] || null
  selectedFile.value = file
  revokePreviewUrl()
  if (file) {
    previewUrl.value = URL.createObjectURL(file)
  }
}

function openCreate() {
  editingId.value = null
  editTab.value = 'info'
  selectedFile.value = null
  revokePreviewUrl()
  form.name = ''
  form.quantity = 1
  form.rate = 0
  form.brandId = activeBrands.value[0]?.brand_id || 0
  form.categoriesId = activeCategories.value[0]?.categories_id || 0
  form.status = 1
  modalOpen.value = true
}

function openEdit(item) {
  editingId.value = item.product_id
  editTab.value = 'info'
  selectedFile.value = null
  revokePreviewUrl()
  currentImageUrl.value = item.product_image
  form.name = item.product_name
  form.quantity = item.quantity
  form.rate = item.rate
  form.brandId = item.brand_id
  form.categoriesId = item.categories_id
  form.status = item.active
  modalOpen.value = true
}

async function submitProductForm() {
  if (!form.name || !form.brandId || !form.categoriesId) {
    ui.warning('Completa todos los campos obligatorios.')
    return
  }
  const unsafe = findUnsafeField({ 'Nombre del producto': form.name })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene código o caracteres no permitidos.`)
    return
  }
  saving.value = true
  try {
    if (editingId.value) {
      await productsStore.updateProduct({
        productId: editingId.value,
        editProductName: form.name,
        editQuantity: form.quantity,
        editRate: form.rate,
        editBrandName: form.brandId,
        editCategoryName: form.categoriesId,
        editProductStatus: form.status,
      })
      ui.success('Producto actualizado exitosamente.')
    } else {
      const fd = new FormData()
      fd.append('productName', form.name)
      fd.append('quantity', String(form.quantity))
      fd.append('rate', String(form.rate))
      fd.append('brandName', String(form.brandId))
      fd.append('categoryName', String(form.categoriesId))
      fd.append('productStatus', String(form.status))
      if (selectedFile.value) {
        fd.append('productImage', selectedFile.value)
      }
      await productsStore.createProduct(fd)
      ui.success('Producto creado exitosamente.')
    }
    modalOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

async function submitPhotoForm() {
  if (!editingId.value || !selectedFile.value) return
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('productId', String(editingId.value))
    fd.append('editProductImage', selectedFile.value)
    const res = await productsStore.updateProductImage(fd)
    if (res?.imageUrl) {
      currentImageUrl.value = res.imageUrl
    }
    selectedFile.value = null
    revokePreviewUrl()
    ui.success('Imagen actualizada exitosamente.')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

function askDelete(item) {
  deletingItem.value = item
}

async function confirmDelete() {
  if (!deletingItem.value) return
  deleting.value = true
  try {
    await productsStore.deleteProduct(deletingItem.value.product_id)
    ui.success('Producto eliminado exitosamente.')
    deletingItem.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  productsStore.fetchProducts()
  brandsStore.fetchBrands()
  categoriesStore.fetchCategories()
})

onUnmounted(() => {
  revokePreviewUrl()
})
</script>
