<template>
  <BaseModal :open="open" :title="title" size="sm" @close="$emit('close')">
    <div class="flex items-start gap-3.5">
      <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
        <Trash2 class="h-5 w-5" />
      </div>
      <div class="text-sm text-slate-600 leading-relaxed pt-0.5">
        <p>{{ message }}</p>
      </div>
    </div>
    <template #footer>
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition"
        :disabled="loading"
        @click="$emit('close')"
      >
        Cancelar
      </button>
      <button
        type="button"
        class="px-4 py-2 text-sm font-semibold text-white bg-rose-600 rounded-lg hover:bg-rose-700 disabled:opacity-50 transition inline-flex items-center gap-2"
        :disabled="loading"
        @click="$emit('confirm')"
      >
        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
        {{ confirmLabel }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { Trash2, Loader2 } from '@lucide/vue'
import BaseModal from './BaseModal.vue'

defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: 'Confirmar eliminación' },
  message: { type: String, default: '¿Realmente deseas eliminar este registro?' },
  confirmLabel: { type: String, default: 'Eliminar' },
  loading: { type: Boolean, default: false },
})

defineEmits(['close', 'confirm'])
</script>
