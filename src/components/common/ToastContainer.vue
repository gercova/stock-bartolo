<template>
  <div
    class="no-print fixed bottom-4 right-4 left-4 sm:left-auto z-50 flex flex-col gap-2.5 sm:w-96 pointer-events-none"
    aria-live="polite"
  >
    <TransitionGroup name="toast">
      <div
        v-for="toast in ui.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-3.5 shadow-lg ring-1 ring-black/5"
        :class="borderClass(toast.type)"
      >
        <component :is="iconFor(toast.type)" class="h-5 w-5 shrink-0 mt-0.5" :class="iconColor(toast.type)" />
        <div class="flex-1 min-w-0">
          <p v-if="toast.title" class="text-sm font-semibold text-slate-900">{{ toast.title }}</p>
          <p class="text-sm text-slate-600 break-words">{{ toast.message }}</p>
        </div>
        <button
          type="button"
          class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
          @click="ui.removeToast(toast.id)"
          aria-label="Cerrar notificación"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from '@lucide/vue'
import { useUiStore } from '@/stores/ui'

const ui = useUiStore()

function iconFor(type) {
  switch (type) {
    case 'success':
      return CheckCircle2
    case 'error':
      return AlertCircle
    case 'warning':
      return AlertTriangle
    default:
      return Info
  }
}

function iconColor(type) {
  switch (type) {
    case 'success':
      return 'text-emerald-600'
    case 'error':
      return 'text-rose-600'
    case 'warning':
      return 'text-amber-600'
    default:
      return 'text-indigo-600'
  }
}

function borderClass(type) {
  switch (type) {
    case 'success':
      return 'border-emerald-200'
    case 'error':
      return 'border-rose-200'
    case 'warning':
      return 'border-amber-200'
    default:
      return 'border-indigo-200'
  }
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.22s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(10px) scale(0.97);
}
</style>
