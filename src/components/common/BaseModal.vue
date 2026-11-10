<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="no-print fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs transition-opacity"
        @click="$emit('close')"
      ></div>
      <div
        class="relative z-10 w-full bg-white shadow-2xl border border-slate-200 rounded-t-2xl sm:rounded-2xl flex flex-col max-h-[92vh]"
        :class="maxWidthClass"
      >
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
          <div class="min-w-0">
            <h3 class="text-base sm:text-lg font-semibold text-slate-900 truncate">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs sm:text-sm text-slate-500 truncate">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
            @click="$emit('close')"
            aria-label="Cerrar"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
          <slot />
        </div>

        <div
          v-if="$slots.footer"
          class="border-t border-slate-200 bg-slate-50/70 px-5 py-3.5 rounded-b-2xl flex flex-wrap items-center justify-end gap-2.5"
        >
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { X } from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  size: { type: String, default: 'md' },
})

const emit = defineEmits(['close'])

const maxWidthClass = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'sm:max-w-md'
    case 'lg':
      return 'sm:max-w-2xl'
    case 'xl':
      return 'sm:max-w-4xl'
    default:
      return 'sm:max-w-lg'
  }
})

function onKeydown(e) {
  if (e.key === 'Escape' && props.open) {
    emit('close')
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>
