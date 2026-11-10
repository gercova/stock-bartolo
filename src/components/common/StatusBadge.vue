<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
    :class="variantClasses"
  >
    <span class="h-1.5 w-1.5 rounded-full" :class="dotClass"></span>
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
  },
  label: {
    type: String,
    default: '',
  },
})

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
    case 'danger':
      return 'bg-rose-50 text-rose-700 ring-rose-600/20'
    case 'warning':
      return 'bg-amber-50 text-amber-800 ring-amber-600/20'
    case 'info':
      return 'bg-sky-50 text-sky-700 ring-sky-600/20'
    case 'indigo':
      return 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'
    default:
      return 'bg-slate-100 text-slate-700 ring-slate-500/20'
  }
})

const dotClass = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-emerald-500'
    case 'danger':
      return 'bg-rose-500'
    case 'warning':
      return 'bg-amber-500'
    case 'info':
      return 'bg-sky-500'
    case 'indigo':
      return 'bg-indigo-500'
    default:
      return 'bg-slate-400'
  }
})
</script>
