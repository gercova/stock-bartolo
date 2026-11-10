<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Change Username Card -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
      <div class="flex items-center gap-3 mb-5">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
          <UserCog class="h-5 w-5" />
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900">Cambiar usuario</h2>
          <p class="text-xs text-slate-500">Actualiza tu nombre de usuario de acceso</p>
        </div>
      </div>

      <form class="space-y-4" @submit.prevent="submitUsername">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre de usuario *</label>
          <input
            v-model.trim="usernameForm.username"
            type="text"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <button
          type="submit"
          :disabled="savingUsername"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 transition"
        >
          <Loader2 v-if="savingUsername" class="h-4 w-4 animate-spin" />
          <CheckCircle2 v-else class="h-4 w-4" />
          Guardar cambios
        </button>
      </form>
    </div>

    <!-- Change Password Card -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs">
      <div class="flex items-center gap-3 mb-5">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
          <KeyRound class="h-5 w-5" />
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900">Cambiar contraseña</h2>
          <p class="text-xs text-slate-500">Actualiza la contraseña de tu cuenta</p>
        </div>
      </div>

      <form class="space-y-4" @submit.prevent="submitPassword">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Contraseña actual *</label>
          <input
            v-model="passwordForm.password"
            type="password"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Nueva contraseña *</label>
          <input
            v-model="passwordForm.npassword"
            type="password"
            required
            minlength="4"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirmar contraseña *</label>
          <input
            v-model="passwordForm.cpassword"
            type="password"
            required
            minlength="4"
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <button
          type="submit"
          :disabled="savingPassword"
          class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60 transition"
        >
          <Loader2 v-if="savingPassword" class="h-4 w-4 animate-spin" />
          <CheckCircle2 v-else class="h-4 w-4" />
          Actualizar contraseña
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { UserCog, KeyRound, CheckCircle2, Loader2 } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'

const auth = useAuthStore()
const ui = useUiStore()

const savingUsername = ref(false)
const savingPassword = ref(false)

const usernameForm = reactive({
  username: auth.user?.username || '',
})

const passwordForm = reactive({
  password: '',
  npassword: '',
  cpassword: '',
})

async function submitUsername() {
  if (!usernameForm.username) {
    ui.warning('El nombre de usuario es obligatorio.')
    return
  }
  savingUsername.value = true
  try {
    const res = await auth.updateUsername(usernameForm.username)
    ui.success(res?.messages || 'Usuario actualizado exitosamente.')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingUsername.value = false
  }
}

async function submitPassword() {
  if (passwordForm.npassword !== passwordForm.cpassword) {
    ui.warning('La nueva contraseña y su confirmación no coinciden.')
    return
  }
  savingPassword.value = true
  try {
    const res = await auth.changePassword(passwordForm)
    ui.success(res?.messages || 'Contraseña actualizada exitosamente.')
    passwordForm.password = ''
    passwordForm.npassword = ''
    passwordForm.cpassword = ''
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingPassword.value = false
  }
}

onMounted(() => {
  usernameForm.username = auth.user?.username || ''
})
</script>
