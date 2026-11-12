<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar Rol y Permisos' : 'Nuevo Rol Personalizado'"
    :subtitle="isEdit ? `Configurar permisos para el rol '${form.role_name}'` : 'Definir un nuevo rol con asignación granular de privilegios'"
    size="2xl"
    @close="$emit('close')"
  >
    <form @submit.prevent="handleSubmit" id="roleForm" class="space-y-5">
      <div v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
        {{ errorMessage }}
      </div>

      <!-- Superadmin banner -->
      <div
        v-if="isSuperadmin"
        class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 flex items-start gap-3 text-indigo-900"
      >
        <ShieldAlert class="h-5 w-5 text-indigo-600 shrink-0 mt-0.5" />
        <div class="text-xs sm:text-sm">
          <p class="font-semibold">Rol Superadministrador Protegido</p>
          <p class="text-indigo-700 mt-0.5">
            Por arquitectura de seguridad, el rol Superadministrador posee acceso total e irrestricto a todos los módulos y acciones del sistema. La matriz de permisos no puede ser degradada.
          </p>
        </div>
      </div>

      <!-- Role Info -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Nombre del Rol <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.trim="form.role_name"
            type="text"
            required
            maxlength="50"
            placeholder="ej. Auditor Nocturno"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Descripción Funcional
          </label>
          <input
            v-model.trim="form.description"
            type="text"
            maxlength="255"
            placeholder="ej. Consulta de reportes y arqueos sin acceso a edición"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
      </div>

      <!-- Permissions Matrix Header & Bulk Actions -->
      <div class="border-t border-slate-200 pt-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
          <div>
            <h4 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Lock class="h-4 w-4 text-indigo-600" />
              Matriz de Permisos Granulares ({{ availableModules.length }} Módulos)
            </h4>
            <p class="text-xs text-slate-500">
              Configura de manera independiente los derechos de lectura, creación, modificación y borrado.
            </p>
          </div>

          <!-- Quick Matrix Controls (disabled for superadmin) -->
          <div v-if="!isSuperadmin" class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 shadow-2xs transition"
              @click="toggleAll(true)"
            >
              Marcar Todo
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 shadow-2xs transition"
              @click="toggleAll(false)"
            >
              Desmarcar Todo
            </button>
          </div>
        </div>

        <!-- Matrix Table Container -->
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-2xs">
          <table class="w-full text-left text-xs sm:text-sm">
            <thead class="bg-slate-50 text-slate-700 uppercase tracking-wider text-[11px] font-semibold border-b border-slate-200">
              <tr>
                <th class="py-3 px-4 min-w-[200px]">Módulo del Sistema</th>
                <th class="py-3 px-3 text-center w-24">
                  <div class="flex flex-col items-center gap-1">
                    <span>Acceso</span>
                    <button
                      v-if="!isSuperadmin"
                      type="button"
                      class="text-[10px] text-indigo-600 hover:underline capitalize"
                      @click="toggleColumn('can_access')"
                    >
                      (columna)
                    </button>
                  </div>
                </th>
                <th class="py-3 px-3 text-center w-24">
                  <div class="flex flex-col items-center gap-1">
                    <span>Crear</span>
                    <button
                      v-if="!isSuperadmin"
                      type="button"
                      class="text-[10px] text-indigo-600 hover:underline capitalize"
                      @click="toggleColumn('can_create')"
                    >
                      (columna)
                    </button>
                  </div>
                </th>
                <th class="py-3 px-3 text-center w-24">
                  <div class="flex flex-col items-center gap-1">
                    <span>Editar</span>
                    <button
                      v-if="!isSuperadmin"
                      type="button"
                      class="text-[10px] text-indigo-600 hover:underline capitalize"
                      @click="toggleColumn('can_update')"
                    >
                      (columna)
                    </button>
                  </div>
                </th>
                <th class="py-3 px-3 text-center w-24">
                  <div class="flex flex-col items-center gap-1">
                    <span>Eliminar</span>
                    <button
                      v-if="!isSuperadmin"
                      type="button"
                      class="text-[10px] text-indigo-600 hover:underline capitalize"
                      @click="toggleColumn('can_delete')"
                    >
                      (columna)
                    </button>
                  </div>
                </th>
                <th v-if="!isSuperadmin" class="py-3 px-3 text-center w-20">Fila</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <!-- Group by Category -->
              <template v-for="(group, catName) in groupedModules" :key="catName">
                <tr class="bg-slate-100/70">
                  <td colspan="6" class="py-2 px-4 font-bold text-slate-800 text-xs uppercase tracking-wider">
                    {{ catName }}
                  </td>
                </tr>
                <tr
                  v-for="mod in group"
                  :key="mod.key"
                  class="hover:bg-slate-50/70 transition-colors"
                >
                  <td class="py-2.5 px-4">
                    <p class="font-semibold text-slate-900">{{ mod.name }}</p>
                    <p class="text-[11px] text-slate-500 line-clamp-1">{{ mod.description }}</p>
                  </td>
                  <!-- can_access -->
                  <td class="py-2.5 px-3 text-center">
                    <input
                      type="checkbox"
                      v-model="permissionsMap[mod.key].can_access"
                      :disabled="isSuperadmin"
                      class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-60 cursor-pointer"
                    />
                  </td>
                  <!-- can_create -->
                  <td class="py-2.5 px-3 text-center">
                    <input
                      type="checkbox"
                      v-model="permissionsMap[mod.key].can_create"
                      :disabled="isSuperadmin"
                      class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-60 cursor-pointer"
                    />
                  </td>
                  <!-- can_update -->
                  <td class="py-2.5 px-3 text-center">
                    <input
                      type="checkbox"
                      v-model="permissionsMap[mod.key].can_update"
                      :disabled="isSuperadmin"
                      class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-60 cursor-pointer"
                    />
                  </td>
                  <!-- can_delete -->
                  <td class="py-2.5 px-3 text-center">
                    <input
                      type="checkbox"
                      v-model="permissionsMap[mod.key].can_delete"
                      :disabled="isSuperadmin"
                      class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-60 cursor-pointer"
                    />
                  </td>
                  <!-- Toggle Row -->
                  <td v-if="!isSuperadmin" class="py-2.5 px-3 text-center">
                    <button
                      type="button"
                      class="text-[11px] text-slate-400 hover:text-indigo-600"
                      title="Alternar toda la fila"
                      @click="toggleRow(mod.key)"
                    >
                      <CheckSquare class="h-4 w-4 mx-auto" />
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </form>

    <template #footer>
      <button
        type="button"
        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
        @click="$emit('close')"
      >
        Cancelar
      </button>
      <button
        type="submit"
        form="roleForm"
        :disabled="submitting"
        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        <span>{{ isEdit ? 'Guardar Cambios' : 'Crear Rol' }}</span>
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { CheckSquare, Loader2, Lock, ShieldAlert } from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  role: { type: Object, default: null },
  availableModules: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save'])

const errorMessage = ref('')
const isEdit = computed(() => Boolean(props.role && props.role.role_id))
const isSuperadmin = computed(() => props.role?.role_slug === 'superadmin')

const form = ref({
  role_id: null,
  role_name: '',
  description: '',
})

const permissionsMap = reactive({})

const groupedModules = computed(() => {
  const groups = {}
  for (const m of props.availableModules) {
    const cat = m.category || 'Otros'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(m)
  }
  return groups
})

watch(
  () => props.open,
  (val) => {
    if (val) {
      errorMessage.value = ''
      if (props.role) {
        form.value = {
          role_id: props.role.role_id,
          role_name: props.role.role_name || '',
          description: props.role.description || '',
        }
        // Populate permissions
        const existingMap = {}
        if (Array.isArray(props.role.permissions)) {
          for (const p of props.role.permissions) {
            existingMap[p.module] = p
          }
        }
        for (const mod of props.availableModules) {
          const ex = existingMap[mod.key]
          permissionsMap[mod.key] = {
            can_access: isSuperadmin.value ? true : Boolean(ex?.can_access),
            can_create: isSuperadmin.value ? true : Boolean(ex?.can_create),
            can_update: isSuperadmin.value ? true : Boolean(ex?.can_update),
            can_delete: isSuperadmin.value ? true : Boolean(ex?.can_delete),
          }
        }
      } else {
        form.value = {
          role_id: null,
          role_name: '',
          description: '',
        }
        for (const mod of props.availableModules) {
          permissionsMap[mod.key] = {
            can_access: false,
            can_create: false,
            can_update: false,
            can_delete: false,
          }
        }
      }
    }
  },
  { immediate: true }
)

function toggleAll(status) {
  for (const mod of props.availableModules) {
    permissionsMap[mod.key] = {
      can_access: status,
      can_create: status,
      can_update: status,
      can_delete: status,
    }
  }
}

function toggleColumn(col) {
  const allCurrentTrue = props.availableModules.every((m) => Boolean(permissionsMap[m.key]?.[col]))
  const targetVal = !allCurrentTrue
  for (const mod of props.availableModules) {
    if (permissionsMap[mod.key]) {
      permissionsMap[mod.key][col] = targetVal
    }
  }
}

function toggleRow(modKey) {
  const row = permissionsMap[modKey]
  if (!row) return
  const allTrue = row.can_access && row.can_create && row.can_update && row.can_delete
  const target = !allTrue
  row.can_access = target
  row.can_create = target
  row.can_update = target
  row.can_delete = target
}

function handleSubmit() {
  errorMessage.value = ''
  if (!form.value.role_name || form.value.role_name.length < 2) {
    errorMessage.value = 'El nombre del rol debe tener al menos 2 caracteres.'
    return
  }

  const permissionsList = props.availableModules.map((m) => ({
    module: m.key,
    can_access: isSuperadmin.value ? true : Boolean(permissionsMap[m.key]?.can_access),
    can_create: isSuperadmin.value ? true : Boolean(permissionsMap[m.key]?.can_create),
    can_update: isSuperadmin.value ? true : Boolean(permissionsMap[m.key]?.can_update),
    can_delete: isSuperadmin.value ? true : Boolean(permissionsMap[m.key]?.can_delete),
  }))

  emit('save', {
    role_id: form.value.role_id,
    role_name: form.value.role_name,
    description: form.value.description,
    permissions: permissionsList,
  })
}
</script>

