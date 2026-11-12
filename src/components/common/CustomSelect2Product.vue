<template>
  <div ref="rootRef" class="relative w-full">
    <!-- Selected State Display (when a product is already picked) -->
    <div
      v-if="selectedProduct && !isSearching"
      class="flex items-center justify-between gap-2 rounded-xl border border-indigo-200 bg-indigo-50/60 px-3 py-2 text-sm transition"
    >
      <div class="flex items-center gap-2 min-w-0">
        <Package class="h-4 w-4 shrink-0 text-indigo-600" />
        <div class="min-w-0 truncate">
          <p class="font-semibold text-slate-900 truncate text-xs sm:text-sm">
            {{ selectedProduct.product_name }}
          </p>
          <p class="text-[11px] text-slate-500 truncate">
            Precio: <span class="font-bold text-indigo-700">{{ formatCurrency(selectedProduct.rate) }}</span>
            <span class="mx-1">·</span>
            Stock disponible:
            <span
              class="font-semibold"
              :class="selectedProduct.quantity > 5 ? 'text-emerald-700' : selectedProduct.quantity > 0 ? 'text-amber-700' : 'text-rose-600'"
            >
              {{ selectedProduct.quantity }} uds
            </span>
          </p>
        </div>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <button
          type="button"
          class="rounded-lg p-1 text-slate-400 hover:bg-indigo-100 hover:text-indigo-700 transition"
          title="Cambiar producto"
          @click="startSearch"
        >
          <Search class="h-3.5 w-3.5" />
        </button>
        <button
          type="button"
          class="rounded-lg p-1 text-slate-400 hover:bg-rose-100 hover:text-rose-700 transition"
          title="Quitar selección"
          @click="clearSelection"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </div>
    </div>

    <!-- Search Input Mode (Select2 Search box) -->
    <div v-else class="relative">
      <div class="relative flex items-center">
        <Search class="absolute left-3 h-4 w-4 text-slate-400 pointer-events-none" />
        <input
          ref="inputRef"
          v-model="searchTerm"
          type="text"
          :placeholder="placeholder"
          :disabled="disabled"
          class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-9 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100"
          @focus="onFocus"
          @input="onInput"
          @keydown.down.prevent="navigateDown"
          @keydown.up.prevent="navigateUp"
          @keydown.enter.prevent="selectHighlighted"
          @keydown.esc.prevent="closeDropdown"
        />
        <div class="absolute right-2.5 flex items-center gap-1">
          <Loader2 v-if="loading" class="h-4 w-4 animate-spin text-indigo-600" />
          <button
            v-else-if="searchTerm || selectedProduct"
            type="button"
            class="text-slate-400 hover:text-slate-600 p-0.5"
            @click="cancelSearch"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      <!-- Floating Dropdown (Strictly max 5 results) -->
      <Transition name="fade">
        <div
          v-if="dropdownOpen"
          class="absolute left-0 right-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl ring-1 ring-slate-900/5 focus:outline-none"
        >
          <!-- Results Header & Cap notice -->
          <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/90 px-3 py-1.5 text-[11px] font-semibold text-slate-500">
            <span>Resultados de búsqueda</span>
            <span class="rounded-full bg-slate-200/70 px-2 py-0.2 text-[10px] text-slate-700">
              Máx. 5 mostrados
            </span>
          </div>

          <!-- Loading state -->
          <div v-if="loading" class="flex items-center justify-center gap-2 py-5 text-xs text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin text-indigo-600" />
            Buscando en catálogo...
          </div>

          <!-- Results List -->
          <ul v-else-if="results.length > 0" class="divide-y divide-slate-100 py-1">
            <li
              v-for="(p, idx) in results"
              :key="p.product_id"
              class="flex items-center justify-between gap-3 px-3.5 py-2.5 cursor-pointer text-sm transition"
              :class="idx === highlightedIndex ? 'bg-indigo-50 text-indigo-900' : 'hover:bg-slate-50 text-slate-800'"
              @mouseenter="highlightedIndex = idx"
              @mousedown.prevent="selectProduct(p)"
            >
              <div class="min-w-0 flex-1">
                <p class="font-medium truncate text-xs sm:text-sm">
                  {{ p.product_name }}
                </p>
                <p class="text-[11px] text-slate-400 truncate">
                  <span v-if="p.brand_name">{{ p.brand_name }}</span>
                  <span v-if="p.brand_name && p.categories_name"> · </span>
                  <span v-if="p.categories_name">{{ p.categories_name }}</span>
                  <span> · Código #{{ p.product_id }}</span>
                </p>
              </div>

              <div class="text-right shrink-0">
                <p class="font-bold text-slate-900 text-xs sm:text-sm">
                  {{ formatCurrency(p.rate) }}
                </p>
                <span
                  class="inline-block rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                  :class="p.quantity > 5 ? 'bg-emerald-50 text-emerald-700' : p.quantity > 0 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'"
                >
                  Stock: {{ p.quantity }}
                </span>
              </div>
            </li>
          </ul>

          <!-- Empty State -->
          <div v-else class="py-5 px-3 text-center text-xs text-slate-500">
            <PackageSearch class="h-6 w-6 text-slate-300 mx-auto mb-1" />
            No se encontraron productos coincidentes para <span class="font-semibold text-slate-700">"{{ searchTerm }}"</span>.
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Search, Package, PackageSearch, X, Loader2 } from '@lucide/vue'
import apiClient from '@/api/client'
import { formatCurrency } from '@/utils/formatters'

const props = defineProps({
  modelValue: {
    type: Number,
    default: 0,
  },
  initialProductName: {
    type: String,
    default: '',
  },
  initialStock: {
    type: Number,
    default: 0,
  },
  initialRate: {
    type: Number,
    default: 0,
  },
  placeholder: {
    type: String,
    default: 'Buscar producto por nombre o código (máx. 5)...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'select', 'clear'])

const rootRef = ref(null)
const inputRef = ref(null)

const searchTerm = ref('')
const results = ref([])
const loading = ref(false)
const dropdownOpen = ref(false)
const highlightedIndex = ref(-1)
const isSearching = ref(false)

const selectedProduct = ref(null)
let debounceTimer = null

// Initialize selected product if passed
watch(
  () => props.modelValue,
  (val) => {
    if (val && (!selectedProduct.value || selectedProduct.value.product_id !== val)) {
      selectedProduct.value = {
        product_id: val,
        product_name: props.initialProductName || `Producto #${val}`,
        rate: props.initialRate || 0,
        quantity: props.initialStock || 0,
      }
    } else if (!val) {
      selectedProduct.value = null
    }
  },
  { immediate: true }
)

function startSearch() {
  isSearching.value = true
  dropdownOpen.value = true
  searchTerm.value = ''
  highlightedIndex.value = -1
  nextTick(() => {
    inputRef.value?.focus()
    searchProducts('')
  })
}

function cancelSearch() {
  searchTerm.value = ''
  dropdownOpen.value = false
  if (selectedProduct.value) {
    isSearching.value = false
  }
}

function onFocus() {
  dropdownOpen.value = true
  if (results.value.length === 0) {
    searchProducts(searchTerm.value)
  }
}

function onInput() {
  dropdownOpen.value = true
  highlightedIndex.value = -1
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    searchProducts(searchTerm.value)
  }, 220)
}

async function searchProducts(query) {
  loading.value = true
  try {
    const { data } = await apiClient.get('/searchProducts.php', {
      params: { query: query.trim() },
    })
    // Ensure hard cap of maximum 5 items
    results.value = (data?.data || []).slice(0, 5)
  } catch (err) {
    results.value = []
  } finally {
    loading.value = false
  }
}

function selectProduct(product) {
  selectedProduct.value = product
  isSearching.value = false
  dropdownOpen.value = false
  searchTerm.value = ''
  emit('update:modelValue', product.product_id)
  emit('select', product)
}

function selectHighlighted() {
  if (highlightedIndex.value >= 0 && highlightedIndex.value < results.value.length) {
    selectProduct(results.value[highlightedIndex.value])
  } else if (results.value.length === 1) {
    selectProduct(results.value[0])
  }
}

function clearSelection() {
  selectedProduct.value = null
  searchTerm.value = ''
  isSearching.value = false
  dropdownOpen.value = false
  emit('update:modelValue', 0)
  emit('clear')
}

function navigateDown() {
  if (!dropdownOpen.value) {
    dropdownOpen.value = true
    return
  }
  if (results.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value + 1) % results.value.length
}

function navigateUp() {
  if (!dropdownOpen.value) {
    dropdownOpen.value = true
    return
  }
  if (results.value.length === 0) return
  highlightedIndex.value =
    (highlightedIndex.value - 1 + results.value.length) % results.value.length
}

function closeDropdown() {
  dropdownOpen.value = false
  if (selectedProduct.value) {
    isSearching.value = false
  }
}

function handleOutsideClick(event) {
  if (rootRef.value && !rootRef.value.contains(event.target)) {
    closeDropdown()
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

