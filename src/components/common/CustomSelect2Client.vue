<template>
  <div ref="rootRef" class="relative w-full">
    <!-- Trigger Button / Box (Displays current selected client like standard Select2) -->
    <div
      v-if="!dropdownOpen"
      tabindex="0"
      class="flex items-center justify-between gap-2 w-full rounded-lg border border-sky-400 bg-white px-3 py-1.5 text-sm text-slate-900 cursor-pointer hover:border-sky-500 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400/30 transition shadow-2xs"
      :class="{ 'opacity-60 cursor-not-allowed bg-slate-50': disabled }"
      @click="toggleDropdown"
      @keydown.down.prevent="openDropdown"
      @keydown.enter.prevent="openDropdown"
      @keydown.space.prevent="openDropdown"
    >
      <div class="flex items-center gap-1.5 min-w-0 truncate">
        <span v-if="displayLabel" class="text-slate-900 truncate font-normal">
          {{ displayLabel }}
        </span>
        <span v-else class="text-slate-400 truncate">
          {{ placeholder }}
        </span>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <button
          v-if="displayLabel && !disabled"
          type="button"
          class="rounded p-0.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
          title="Quitar cliente seleccionado"
          @click.stop="clearSelection"
        >
          <X class="h-3.5 w-3.5" />
        </button>
        <span class="text-slate-500 text-xs select-none">▼</span>
      </div>
    </div>

    <!-- Active Search Input Box (when dropdown is opened) -->
    <div v-else class="relative">
      <div class="relative flex items-center">
        <Search class="absolute left-3 h-4 w-4 text-indigo-500 pointer-events-none" />
        <input
          ref="searchInputRef"
          v-model="searchTerm"
          type="text"
          :placeholder="placeholder"
          class="w-full rounded-xl border-2 border-indigo-500 bg-white pl-9 pr-9 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none shadow-md"
          @input="onSearchInput"
          @keydown.down.prevent="navigateDown"
          @keydown.up.prevent="navigateUp"
          @keydown.enter.prevent="selectHighlighted"
          @keydown.esc.prevent="closeDropdown"
        />
        <div class="absolute right-2.5 flex items-center gap-1">
          <Loader2 v-if="loading" class="h-4 w-4 animate-spin text-indigo-600" />
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600 p-0.5"
            title="Cerrar selector"
            @click="closeDropdown"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>

      <!-- Dropdown Results Box -->
      <Transition name="fade">
        <div
          class="absolute left-0 right-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/5 focus:outline-none divide-y divide-slate-100"
        >
          <div class="flex items-center justify-between bg-slate-50 px-3.5 py-1.5 text-[11px] font-semibold text-slate-500">
            <span>Resultados de clientes (Base de Datos)</span>
            <span class="text-[10px] text-slate-400">Usa flechas ↑↓ y Enter</span>
          </div>

          <!-- Loading state -->
          <div v-if="loading" class="flex items-center justify-center gap-2 py-6 text-xs text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin text-indigo-600" />
            Buscando clientes...
          </div>

          <!-- Client Results List -->
          <ul v-else-if="results.length > 0" class="divide-y divide-slate-100 py-1">
            <li
              v-for="(c, idx) in results"
              :key="c.client_id"
              class="flex items-center justify-between gap-3 px-3.5 py-2.5 cursor-pointer text-sm transition"
              :class="idx === highlightedIndex ? 'bg-indigo-50 text-indigo-950 font-medium' : 'hover:bg-slate-50 text-slate-800'"
              @mouseenter="highlightedIndex = idx"
              @mousedown.prevent="selectClient(c)"
            >
              <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900 truncate text-xs sm:text-sm">
                  {{ c.document_number ? `${c.document_number} - ` : '' }}{{ c.name }}
                </p>
                <p class="text-[11px] text-slate-500 truncate flex items-center gap-1.5 mt-0.5">
                  <span class="rounded bg-slate-100 px-1 py-0.2 font-mono text-[10px] text-slate-700">
                    {{ c.document_type || 'OTRO' }}
                  </span>
                  <span v-if="c.phone">· Tel: {{ c.phone }}</span>
                  <span v-if="c.email" class="truncate">· {{ c.email }}</span>
                  <span v-if="c.district || c.department" class="truncate">· {{ [c.district, c.department].filter(Boolean).join(', ') }}</span>
                </p>
              </div>

              <div class="text-right shrink-0">
                <span
                  v-if="c.points_balance"
                  class="rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 border border-purple-200"
                >
                  {{ c.points_balance }} pts
                </span>
              </div>
            </li>
          </ul>

          <!-- Empty state -->
          <div v-else class="py-6 px-4 text-center text-xs text-slate-500">
            <UserX class="h-6 w-6 text-slate-300 mx-auto mb-1" />
            No se encontraron clientes coincidentes para <span class="font-semibold text-slate-700">"{{ searchTerm }}"</span>.
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Search, User, UserX, X, ChevronDown, Loader2 } from '@lucide/vue'
import apiClient from '@/api/client'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null,
  },
  client: {
    type: Object,
    default: null,
  },
  placeholder: {
    type: String,
    default: 'Buscar o seleccionar cliente (DNI, RUC, Nombre)...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'select', 'clear'])

const rootRef = ref(null)
const searchInputRef = ref(null)

const dropdownOpen = ref(false)
const searchTerm = ref('')
const results = ref([])
const loading = ref(false)
const highlightedIndex = ref(-1)
const currentClient = ref(null)
let debounceTimer = null

const displayLabel = computed(() => {
  if (currentClient.value) {
    const doc = currentClient.value.document_number
    const name = currentClient.value.name
    return doc ? `${doc} - ${name}` : name
  }
  return ''
})

watch(
  () => [props.modelValue, props.client],
  ([val, cObj]) => {
    if (cObj && cObj.client_id) {
      currentClient.value = cObj
    } else if (val) {
      if (!currentClient.value || currentClient.value.client_id !== val) {
        fetchClientById(val)
      }
    } else if (!val && !cObj) {
      currentClient.value = null
    }
  },
  { immediate: true, deep: true }
)

function toggleDropdown() {
  if (props.disabled) return
  if (dropdownOpen.value) {
    closeDropdown()
  } else {
    openDropdown()
  }
}

function openDropdown() {
  if (props.disabled) return
  dropdownOpen.value = true
  searchTerm.value = ''
  highlightedIndex.value = -1
  nextTick(() => {
    searchInputRef.value?.focus()
    searchClients('')
  })
}

function closeDropdown() {
  dropdownOpen.value = false
  searchTerm.value = ''
  highlightedIndex.value = -1
}

function onSearchInput() {
  highlightedIndex.value = -1
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    searchClients(searchTerm.value)
  }, 220)
}

async function searchClients(query) {
  loading.value = true
  try {
    const { data } = await apiClient.get('/fetchClients.php', {
      params: { search: query.trim() },
    })
    results.value = (data?.data || []).slice(0, 10)
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
}

async function fetchClientById(clientId) {
  try {
    const { data } = await apiClient.get('/fetchClients.php', {
      params: { search: '' },
    })
    const found = (data?.data || []).find((c) => c.client_id === Number(clientId))
    if (found) {
      currentClient.value = found
    }
  } catch {
    // ignore
  }
}

function selectClient(client) {
  currentClient.value = client
  closeDropdown()
  emit('update:modelValue', client.client_id)
  emit('select', client)
}

function selectHighlighted() {
  if (highlightedIndex.value >= 0 && highlightedIndex.value < results.value.length) {
    selectClient(results.value[highlightedIndex.value])
  } else if (results.value.length === 1) {
    selectClient(results.value[0])
  }
}

function clearSelection() {
  currentClient.value = null
  searchTerm.value = ''
  closeDropdown()
  emit('update:modelValue', null)
  emit('clear')
}

function navigateDown() {
  if (results.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value + 1) % results.value.length
}

function navigateUp() {
  if (results.value.length === 0) return
  highlightedIndex.value = (highlightedIndex.value - 1 + results.value.length) % results.value.length
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
