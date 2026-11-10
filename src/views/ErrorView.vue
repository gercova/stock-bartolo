<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 via-white to-indigo-50/50 p-4 sm:p-6">
    <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 sm:p-10 shadow-xl ring-1 ring-slate-900/5">
      <!-- Top Status Row -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <div class="flex h-12 w-12 items-center justify-center rounded-2xl" :class="toneBadgeBg">
            <component :is="statusIcon" class="h-6 w-6" :class="toneIconColor" />
          </div>
          <div>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold tracking-wide uppercase"
              :class="tonePillClass"
            >
              HTTP {{ errorInfo.code }} · {{ errorInfo.short }}
            </span>
            <p class="text-xs text-slate-400 mt-1">
              {{ errorInfo.category === 'server' ? 'Error de servidor' : 'Error de cliente / solicitud' }}
            </p>
          </div>
        </div>

        <RouterLink
          to="/dashboard"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
        >
          <Boxes class="h-4 w-4" />
          Stock Bartolo
        </RouterLink>
      </div>

      <!-- Main Error Body -->
      <div class="py-8 text-center sm:text-left">
        <div class="flex flex-col sm:flex-row sm:items-baseline gap-2 sm:gap-4">
          <span class="text-6xl sm:text-7xl font-black tracking-tight text-slate-900">
            {{ errorInfo.code }}
          </span>
          <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
            {{ errorInfo.title }}
          </h1>
        </div>

        <p class="mt-4 text-base text-slate-600 leading-relaxed">
          {{ displayMessage }}
        </p>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 flex items-start gap-3 text-left">
          <Info class="h-5 w-5 text-indigo-600 shrink-0 mt-0.5" />
          <div class="text-sm text-slate-700 space-y-1">
            <p class="font-semibold text-slate-900">Sugerencia</p>
            <p>{{ errorInfo.hint }}</p>
            <p v-if="errorStore.lastError?.path" class="text-xs text-slate-500 font-mono pt-1 break-all">
              Ruta: {{ errorStore.lastError.path }} · {{ errorStore.lastError.timestamp }}
            </p>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 pt-2">
        <RouterLink
          :to="primaryDestination"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition"
        >
          <Home class="h-4 w-4" />
          {{ auth.authenticated ? 'Volver al panel' : 'Ir al inicio de sesión' }}
        </RouterLink>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Volver atrás
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
          @click="reloadPage"
        >
          <RotateCw class="h-4 w-4" />
          Reintentar
        </button>
      </div>

      <!-- Quick Reference for Supported Error Pages -->
      <div class="mt-8 pt-5 border-t border-slate-100">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2.5">
          Páginas de estado HTTP disponibles
        </p>
        <div class="flex flex-wrap gap-1.5">
          <RouterLink
            v-for="c in RELEVANT_ERROR_CODES"
            :key="c"
            :to="`/error/${c}`"
            class="rounded-lg px-2.5 py-1 text-xs font-mono font-medium transition"
            :class="
              Number(errorInfo.code) === c
                ? 'bg-slate-900 text-white'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            "
          >
            {{ c }}
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  AlertTriangle,
  ShieldAlert,
  FileQuestion,
  ServerCrash,
  Wrench,
  Clock,
  Ban,
  Info,
  Home,
  ArrowLeft,
  RotateCw,
  Boxes,
} from '@lucide/vue'
import { getHttpErrorInfo, RELEVANT_ERROR_CODES } from '@/constants/httpErrors'
import { useErrorStore } from '@/stores/error'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  code: {
    type: [String, Number],
    default: null,
  },
})

const route = useRoute()
const router = useRouter()
const errorStore = useErrorStore()
const auth = useAuthStore()

const resolvedCode = computed(() => {
  return Number(props.code || route.params.code || errorStore.lastError?.code || 404)
})

const errorInfo = computed(() => getHttpErrorInfo(resolvedCode.value))

const displayMessage = computed(() => {
  if (errorStore.lastError && Number(errorStore.lastError.code) === resolvedCode.value) {
    return errorStore.lastError.customMessage
  }
  return errorInfo.value.description
})

const primaryDestination = computed(() => (auth.authenticated ? '/dashboard' : '/login'))

const statusIcon = computed(() => {
  switch (resolvedCode.value) {
    case 401:
    case 403:
      return ShieldAlert
    case 404:
      return FileQuestion
    case 405:
      return Ban
    case 408:
    case 504:
      return Clock
    case 501:
    case 503:
      return Wrench
    case 500:
    case 502:
      return ServerCrash
    default:
      return AlertTriangle
  }
})

const toneBadgeBg = computed(() => {
  switch (errorInfo.value.tone) {
    case 'red':
    case 'rose':
      return 'bg-rose-100'
    case 'amber':
      return 'bg-amber-100'
    case 'purple':
      return 'bg-purple-100'
    default:
      return 'bg-indigo-100'
  }
})

const toneIconColor = computed(() => {
  switch (errorInfo.value.tone) {
    case 'red':
    case 'rose':
      return 'text-rose-600'
    case 'amber':
      return 'text-amber-600'
    case 'purple':
      return 'text-purple-600'
    default:
      return 'text-indigo-600'
  }
})

const tonePillClass = computed(() => {
  switch (errorInfo.value.tone) {
    case 'red':
    case 'rose':
      return 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'
    case 'amber':
      return 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20'
    case 'purple':
      return 'bg-purple-50 text-purple-700 ring-1 ring-inset ring-purple-600/20'
    default:
      return 'bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/20'
  }
})

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push(primaryDestination.value)
  }
}

function reloadPage() {
  window.location.reload()
}
</script>
