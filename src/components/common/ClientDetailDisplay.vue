<template>
  <div v-if="client" class="w-full max-w-2xl text-xs text-slate-800">
    <!-- Top Horizontal Divider Line (Matches exact reference screenshot) -->
    <div v-if="divider" class="border-t border-[#e5e5e5] my-2"></div>

    <div class="space-y-1">
      <!-- Row 1: Document Type & Number -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-bold text-[#337ab7] pr-5 shrink-0">
          {{ formattedDocType }}:
        </span>
        <span class="font-bold text-slate-900">
          {{ client.document_number || '88888888' }}
        </span>
      </div>

      <!-- Row 2: Customer Name -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          Cliente:
        </span>
        <span class="font-normal text-slate-800">
          {{ client.name || 'PUBLICO GENERAL' }}
        </span>
      </div>

      <!-- Row 3: Birthdate and Age -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          F. Nac.:
        </span>
        <div class="flex items-baseline gap-12 sm:gap-16">
          <span class="text-slate-800">
            {{ client.birth_date && client.birth_date !== '0000-00-00' ? client.birth_date : '0000-00-00' }}
          </span>
          <div class="flex items-baseline gap-2">
            <span class="font-normal text-[#337ab7]">Edad:</span>
            <span class="text-slate-800">{{ age }}</span>
          </div>
        </div>
      </div>

      <!-- Row 4: Gender -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          Sexo:
        </span>
        <span class="text-slate-800">
          {{ client.gender || '' }}
        </span>
      </div>

      <!-- Row 5: Email -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          Correo:
        </span>
        <span class="text-slate-800">
          {{ client.email || '' }}
        </span>
      </div>

      <!-- Row 6: Address -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          Dirección:
        </span>
        <span class="text-slate-800">
          {{ client.address || '' }}
        </span>
      </div>

      <!-- Row 7: Phone / Mobile -->
      <div class="flex items-baseline">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          Teléfono/Cel:
        </span>
        <span class="text-slate-800">
          {{ client.phone || '/' }}
        </span>
      </div>

      <!-- Optional Row 8: Loyalty Points (for operations & rewards) -->
      <div v-if="showPoints" class="flex items-baseline pt-1">
        <span class="w-[110px] text-right font-normal text-[#337ab7] pr-5 shrink-0">
          Puntos:
        </span>
        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">
          <Award class="h-3.5 w-3.5" />
          {{ client.points_balance || 0 }} pts disponibles
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Award } from '@lucide/vue'

const props = defineProps({
  client: {
    type: Object,
    default: null,
  },
  showPoints: {
    type: Boolean,
    default: false,
  },
  divider: {
    type: Boolean,
    default: true,
  },
})

const formattedDocType = computed(() => {
  if (!props.client) return 'OTROS'
  const t = (props.client.document_type || 'OTRO').toUpperCase()
  if (t === 'OTRO' || t === 'OTROS') return 'OTROS'
  return t
})

const age = computed(() => {
  if (!props.client?.birth_date || props.client.birth_date === '0000-00-00') return 0
  const birth = new Date(props.client.birth_date)
  if (isNaN(birth.getTime())) return 0
  const today = new Date()
  let calculated = today.getFullYear() - birth.getFullYear()
  const m = today.getMonth() - birth.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
    calculated--
  }
  return Math.max(0, calculated)
})
</script>
