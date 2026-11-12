<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar Usuario' : 'Nuevo Usuario'"
    :subtitle="isEdit ? `Modificar información de @${form.username}` : 'Registrar un nuevo usuario con credenciales y rol'"
    size="lg"
    @close="$emit('close')"
  >
    <form @submit.prevent="handleSubmit" id="userForm" class="space-y-4">
      <div v-if="errorMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
        {{ errorMessage }}
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Username -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Nombre de Usuario <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.trim="form.username"
            type="text"
            required
            minlength="3"
            maxlength="50"
            pattern="[a-zA-Z0-9._-]+"
            placeholder="ej. carlos.mendez"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
          <p class="mt-1 text-[11px] text-slate-400">Letras, números, puntos, guiones y guiones bajos.</p>
        </div>

        <!-- Full Name -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Nombre Completo <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.trim="form.full_name"
            type="text"
            required
            maxlength="100"
            placeholder="ej. Carlos Méndez"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <!-- Email -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Correo Electrónico
          </label>
          <input
            v-model.trim="form.email"
            type="email"
            maxlength="100"
            placeholder="ej. carlos@empresa.com"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <!-- Phone -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Teléfono
          </label>
          <input
            v-model.trim="form.phone"
            type="tel"
            maxlength="30"
            placeholder="ej. +503 7000-0000"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>

        <!-- Role -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Rol de Seguridad <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="form.role_id"
            required
            :disabled="isCurrentUser"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100 disabled:text-slate-500"
          >
            <option value="" disabled>Seleccione un rol...</option>
            <option v-for="r in roles" :key="r.role_id" :value="r.role_id">
              {{ r.role_name }} ({{ r.role_slug }})
            </option>
          </select>
          <p v-if="isCurrentUser" class="mt-1 text-[11px] text-amber-600 font-medium">
            No puedes alterar tu propio rol para evitar bloqueos de sesión.
          </p>
        </div>

        <!-- Branch -->
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
            Sucursal Asignada
          </label>
          <select
            v-model="form.branch_id"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          >
            <option :value="null">Todas las sucursales (Acceso Global)</option>
            <option v-for="b in branches" :key="b.branch_id" :value="b.branch_id">
              [{{ b.branch_code }}] {{ b.branch_name }}
            </option>
          </select>
          <p class="mt-1 text-[11px] text-slate-400">Dejar en "Acceso Global" si el usuario rota o supervisa varias sedes.</p>
        </div>
      </div>

      <!-- Password section -->
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 space-y-2">
        <div class="flex items-center justify-between">
          <label class="text-xs font-semibold uppercase tracking-wider text-slate-700">
            Contraseña <span v-if="!isEdit" class="text-rose-500">*</span>
          </label>
          <span v-if="isEdit" class="text-[11px] text-slate-500">
            Opcional (dejar vacío para mantener la actual)
          </span>
        </div>
        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            :required="!isEdit"
            minlength="6"
            maxlength="128"
            :placeholder="isEdit ? 'Nueva contraseña (opcional)...' : 'Mínimo 6 caracteres...'"
            class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 pr-10 text-sm text-slate-800 focus:border-indigo-500 focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
          />
          <button
            type="button"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600"
            @click="showPassword = !showPassword"
          >
            <component :is="showPassword ? EyeOff : Eye" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <!-- Active Status Toggle -->
      <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3.5">
        <div>
          <p class="text-sm font-semibold text-slate-900">Estado de la Cuenta</p>
          <p class="text-xs text-slate-500">
            Los usuarios inactivos no pueden iniciar sesión y sus tokens activos son revocados inmediatamente.
          </p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input
            type="checkbox"
            v-model="form.active"
            :disabled="isCurrentUser"
            class="sr-only peer"
          />
          <div
            class="w-11 h-6 bg-slate-300 peer-focus:outline-hidden peer-focus:ring-2 peer-focus:ring-indigo-500/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600 peer-disabled:opacity-50"
          ></div>
        </label>
      </div>
      <p v-if="isCurrentUser" class="text-[11px] text-amber-600 font-medium">
        No puedes desactivar tu propia cuenta activa de usuario.
      </p>
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
        form="userForm"
        :disabled="submitting"
        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 transition shadow-xs"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        <span>{{ isEdit ? 'Actualizar Usuario' : 'Crear Usuario' }}</span>
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { Eye, EyeOff, Loader2 } from '@lucide/vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  user: { type: Object, default: null },
  roles: { type: Array, default: () => [] },
  branches: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'save'])

const showPassword = ref(false)
const errorMessage = ref('')

const isEdit = computed(() => Boolean(props.user && props.user.user_id))
const isCurrentUser = computed(() => Boolean(props.user?.is_current_user))

const form = ref({
  user_id: null,
  username: '',
  full_name: '',
  email: '',
  phone: '',
  role_id: '',
  branch_id: null,
  password: '',
  active: true,
})

watch(
  () => props.open,
  (val) => {
    if (val) {
      errorMessage.value = ''
      showPassword.value = false
      if (props.user) {
        form.value = {
          user_id: props.user.user_id,
          username: props.user.username || '',
          full_name: props.user.full_name || '',
          email: props.user.email || '',
          phone: props.user.phone || '',
          role_id: props.user.role_id || '',
          branch_id: props.user.branch_id || null,
          password: '',
          active: Number(props.user.active) === 1,
        }
      } else {
        const defaultRole = props.roles.find((r) => r.role_slug === 'cashier') || props.roles[0]
        form.value = {
          user_id: null,
          username: '',
          full_name: '',
          email: '',
          phone: '',
          role_id: defaultRole ? defaultRole.role_id : '',
          branch_id: null,
          password: '',
          active: true,
        }
      }
    }
  },
  { immediate: true }
)

function handleSubmit() {
  errorMessage.value = ''
  if (!form.value.username || form.value.username.length < 3) {
    errorMessage.value = 'El nombre de usuario debe tener al menos 3 caracteres.'
    return
  }
  if (!form.value.full_name) {
    errorMessage.value = 'El nombre completo es obligatorio.'
    return
  }
  if (!form.value.role_id) {
    errorMessage.value = 'Selecciona un rol de seguridad.'
    return
  }
  if (!isEdit.value && (!form.value.password || form.value.password.length < 6)) {
    errorMessage.value = 'La contraseña debe tener al menos 6 caracteres.'
    return
  }

  emit('save', {
    user_id: form.value.user_id,
    username: form.value.username,
    full_name: form.value.full_name,
    email: form.value.email,
    phone: form.value.phone,
    role_id: Number(form.value.role_id),
    branch_id: form.value.branch_id ? Number(form.value.branch_id) : null,
    password: form.value.password || undefined,
    active: form.value.active ? 1 : 0,
  })
}
</script>

