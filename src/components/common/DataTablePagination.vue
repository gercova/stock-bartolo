<template>
  <div
    v-if="pagination && (pagination.total > 0 || loading)"
    class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-t border-slate-200 bg-white px-4 py-3 sm:px-6 text-xs text-slate-700 select-none"
  >
    <!-- Left Section: Status & Page Size Selector -->
    <div class="flex flex-wrap items-center gap-3">
      <!-- Mode Toggle Pill -->
      <div v-if="showChunkToggle" class="inline-flex rounded-lg bg-slate-100 p-0.5 border border-slate-200">
        <button
          type="button"
          class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition inline-flex items-center gap-1"
          :class="!internalChunkMode ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
          title="Navegación tradicional página por página"
          @click="setMode(false)"
        >
          <FileText class="h-3 w-3" />
          Páginas
        </button>
        <button
          type="button"
          class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition inline-flex items-center gap-1"
          :class="internalChunkMode ? 'bg-indigo-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
          title="Carga progresiva por bloques (acumula registros en la tabla para grandes volúmenes)"
          @click="setMode(true)"
        >
          <Layers class="h-3 w-3" />
          Por Bloques (Chunks)
        </button>
      </div>

      <!-- Info Count -->
      <div class="text-slate-600 font-medium">
        <template v-if="!internalChunkMode">
          Mostrando
          <span class="font-bold text-slate-900">{{ pagination.from }}</span>
          al
          <span class="font-bold text-slate-900">{{ pagination.to }}</span>
          de
          <span class="font-bold text-slate-900">{{ formattedTotal }}</span>
          {{ entityName }}
        </template>
        <template v-else>
          Cargados
          <span class="font-bold text-indigo-700">{{ currentLoadedCount }}</span>
          de
          <span class="font-bold text-slate-900">{{ formattedTotal }}</span>
          {{ entityName }}
          <span class="text-slate-400 text-[11px]">
            (Bloque {{ pagination.current_chunk || pagination.page }} de {{ pagination.total_chunks || pagination.total_pages }})
          </span>
        </template>
      </div>

      <!-- Rows Per Page / Chunk Size Selector -->
      <div class="flex items-center gap-1.5 pl-2 border-l border-slate-200">
        <span class="text-slate-500 text-[11px]">Por {{ internalChunkMode ? 'bloque' : 'página' }}:</span>
        <select
          :value="pagination.per_page || pagination.chunk_size"
          class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-hidden"
          :disabled="loading"
          @change="onPerPageChange($event.target.value)"
        >
          <option v-for="size in perPageOptions" :key="size" :value="size">
            {{ size }}
          </option>
        </select>
      </div>
    </div>

    <!-- Right Section: Navigation Controls -->
    <div class="flex items-center justify-end gap-2">
      <!-- 1. CHUNK LOADING CONTROLS -->
      <div v-if="internalChunkMode" class="flex items-center gap-2 w-full sm:w-auto">
        <!-- Progress Bar Indicator -->
        <div class="hidden lg:flex items-center gap-2 mr-2 min-w-[130px]">
          <div class="w-24 h-2 rounded-full bg-slate-100 overflow-hidden border border-slate-200">
            <div
              class="h-full bg-indigo-600 transition-all duration-300"
              :style="{ width: `${chunkProgressPercent}%` }"
            ></div>
          </div>
          <span class="text-[10px] font-bold text-slate-500 font-mono">{{ chunkProgressPercent }}%</span>
        </div>

        <!-- Load Next Chunk Button -->
        <button
          v-if="pagination.has_more"
          type="button"
          :disabled="loading"
          class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-2xs active:scale-95"
          @click="$emit('load-next-chunk')"
        >
          <Loader2 v-if="loading" class="h-3.5 w-3.5 animate-spin" />
          <Download v-else class="h-3.5 w-3.5" />
          Cargar siguiente bloque (+{{ pagination.chunk_size || pagination.per_page }})
        </button>

        <span
          v-else
          class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 border border-emerald-200"
        >
          <CheckCircle2 class="h-3.5 w-3.5" />
          Todos los bloques cargados
        </span>

        <!-- Reset to Chunk 1 Button -->
        <button
          v-if="currentLoadedCount > (pagination.per_page || pagination.chunk_size)"
          type="button"
          :disabled="loading"
          class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
          title="Reiniciar al primer bloque"
          @click="$emit('change-page', 1)"
        >
          Reiniciar
        </button>
      </div>

      <!-- 2. CLASSIC PAGE-BY-PAGE CONTROLS -->
      <div v-else class="inline-flex items-center gap-1">
        <!-- First Page -->
        <button
          type="button"
          :disabled="loading || pagination.page <= 1"
          class="rounded-lg border border-slate-200 bg-white p-1 text-slate-500 hover:bg-slate-50 hover:text-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
          title="Primera página"
          @click="$emit('change-page', 1)"
        >
          <ChevronsLeft class="h-4 w-4" />
        </button>

        <!-- Previous Page -->
        <button
          type="button"
          :disabled="loading || pagination.page <= 1"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition"
          title="Página anterior"
          @click="$emit('change-page', pagination.page - 1)"
        >
          <ChevronLeft class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">Anterior</span>
        </button>

        <!-- Page Numbers List with Ellipsis -->
        <div class="hidden sm:inline-flex items-center gap-1">
          <template v-for="(p, idx) in visiblePages" :key="idx">
            <span
              v-if="p === '...'"
              class="px-1.5 py-1 text-slate-400 font-semibold select-none"
            >
              …
            </span>
            <button
              v-else
              type="button"
              :disabled="loading"
              class="min-w-[28px] h-7 rounded-lg text-xs font-bold transition flex items-center justify-center"
              :class="p === pagination.page
                ? 'bg-indigo-600 text-white shadow-2xs'
                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
              @click="$emit('change-page', p)"
            >
              {{ p }}
            </button>
          </template>
        </div>

        <!-- Next Page -->
        <button
          type="button"
          :disabled="loading || !pagination.has_more || pagination.page >= pagination.total_pages"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-slate-600 hover:bg-slate-50 hover:text-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition"
          title="Página siguiente"
          @click="$emit('change-page', pagination.page + 1)"
        >
          <span class="hidden sm:inline">Siguiente</span>
          <ChevronRight class="h-3.5 w-3.5" />
        </button>

        <!-- Last Page -->
        <button
          type="button"
          :disabled="loading || pagination.page >= pagination.total_pages"
          class="rounded-lg border border-slate-200 bg-white p-1 text-slate-500 hover:bg-slate-50 hover:text-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition"
          title="Última página"
          @click="$emit('change-page', pagination.total_pages)"
        >
          <ChevronsRight class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Loader2,
  Download,
  Layers,
  FileText,
  CheckCircle2,
} from '@lucide/vue'

const props = defineProps({
  pagination: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  loadedCount: {
    type: Number,
    default: null,
  },
  chunkMode: {
    type: Boolean,
    default: false,
  },
  showChunkToggle: {
    type: Boolean,
    default: true,
  },
  perPageOptions: {
    type: Array,
    default: () => [5, 10, 25, 50, 100, 250],
  },
  entityName: {
    type: String,
    default: 'registros',
  },
})

const emit = defineEmits([
  'change-page',
  'change-per-page',
  'load-next-chunk',
  'update:chunkMode',
])

const internalChunkMode = ref(props.chunkMode)

watch(
  () => props.chunkMode,
  (val) => {
    internalChunkMode.value = val
  }
)

function setMode(val) {
  internalChunkMode.value = val
  emit('update:chunkMode', val)
}

function onPerPageChange(val) {
  const num = parseInt(val, 10) || 25
  emit('change-per-page', num)
}

const formattedTotal = computed(() => {
  return Number(props.pagination?.total || 0).toLocaleString('es-PE')
})

const currentLoadedCount = computed(() => {
  if (props.loadedCount !== null && props.loadedCount !== undefined) {
    return props.loadedCount
  }
  return props.pagination?.to || 0
})

const chunkProgressPercent = computed(() => {
  const total = Number(props.pagination?.total || 0)
  if (total <= 0) return 100
  const loaded = currentLoadedCount.value
  return Math.min(100, Math.round((loaded / total) * 100))
})

// Smart page window generator (e.g. 1 ... 4 [5] 6 ... 50)
const visiblePages = computed(() => {
  const total = props.pagination?.total_pages || 1
  const current = props.pagination?.page || 1

  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  const pages = []
  pages.push(1)

  const left = Math.max(2, current - 1)
  const right = Math.min(total - 1, current + 1)

  if (left > 2) {
    pages.push('...')
  }

  for (let i = left; i <= right; i++) {
    pages.push(i)
  }

  if (right < total - 1) {
    pages.push('...')
  }

  pages.push(total)
  return pages
})
</script>
