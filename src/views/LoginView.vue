<template>
  <div class="min-h-screen flex flex-col lg:flex-row bg-slate-50">
    <!-- Left Visual Hero (visible on lg+) -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-900 text-white p-12 flex-col justify-between relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 via-slate-900 to-slate-950 pointer-events-none"></div>
      <div class="relative z-10 flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg">
          <Boxes class="h-6 w-6" />
        </div>
        <div>
          <p class="text-lg font-bold tracking-tight">Stock Bartolo</p>
          <p class="text-xs text-slate-400">Sistema de Gestión de Inventario</p>
        </div>
      </div>

      <div class="relative z-10 max-w-md space-y-6">
        <h2 class="text-3xl xl:text-4xl font-extrabold leading-tight">
          Control total de inventario, ventas y préstamos en un solo lugar.
        </h2>
        <p class="text-slate-300 text-sm leading-relaxed">
          Administra fabricantes, categorías, productos, órdenes de venta, reportes financieros y préstamos con cálculo flexible de intereses desde cualquier dispositivo.
        </p>
        <div class="grid grid-cols-2 gap-4 pt-2">
          <div class="rounded-xl border border-slate-800 bg-slate-800/50 p-4">
            <PackageCheck class="h-5 w-5 text-indigo-400 mb-2" />
            <p class="text-sm font-semibold">Stock en tiempo real</p>
            <p class="text-xs text-slate-400 mt-0.5">Descuento automático por orden</p>
          </div>
          <div class="rounded-xl border border-slate-800 bg-slate-800/50 p-4">
            <HandCoins class="h-5 w-5 text-emerald-400 mb-2" />
            <p class="text-sm font-semibold">Módulo de Préstamos</p>
            <p class="text-xs text-slate-400 mt-0.5">Interés opcional y abonos</p>
          </div>
        </div>
      </div>

      <p class="relative z-10 text-xs text-slate-500">
        Single-Page Application · Vue 3 + Pinia
      </p>
    </div>

    <!-- Right Login Form -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10">
      <div class="w-full max-w-md space-y-6">
        <!-- Mobile Header -->
        <div class="flex items-center gap-3 lg:hidden justify-center">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white">
            <Boxes class="h-5 w-5" />
          </div>
          <div>
            <p class="text-base font-bold text-slate-900">Stock Bartolo</p>
            <p class="text-xs text-slate-500">Gestión de Inventario</p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
          <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Iniciar sesión</h1>
            <p class="mt-1 text-sm text-slate-500">Ingresa tus credenciales para acceder al panel.</p>
          </div>

          <div
            v-if="errorMsg"
            class="mb-5 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-700"
            role="alert"
          >
            <AlertCircle class="h-5 w-5 shrink-0 mt-0.5" />
            <span>{{ errorMsg }}</span>
          </div>

          <form class="space-y-4" @submit.prevent="submitLogin">
            <div>
              <label for="username" class="block text-sm font-medium text-slate-700 mb-1.5">
                Usuario
              </label>
              <div class="relative">
                <User class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  id="username"
                  v-model.trim="form.username"
                  type="text"
                  autocomplete="username"
                  required
                  placeholder="Nombre de usuario"
                  class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
              </div>
            </div>

            <div>
              <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">
                Contraseña
              </label>
              <div class="relative">
                <Lock class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  id="password"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  placeholder="Contraseña"
                  class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-10 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                />
                <button
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  @click="showPassword = !showPassword"
                  aria-label="Mostrar u ocultar contraseña"
                >
                  <EyeOff v-if="showPassword" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <button
              type="submit"
              :disabled="auth.loading"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 transition shadow-sm"
            >
              <Loader2 v-if="auth.loading" class="h-4 w-4 animate-spin" />
              <LogIn v-else class="h-4 w-4" />
              {{ auth.loading ? 'Validando...' : 'Ingresar' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Boxes,
  PackageCheck,
  HandCoins,
  User,
  Lock,
  Eye,
  EyeOff,
  LogIn,
  Loader2,
  AlertCircle,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { findUnsafeField } from '@/utils/security'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const form = reactive({
  username: '',
  password: '',
})
const showPassword = ref(false)
const errorMsg = ref('')

async function submitLogin() {
  errorMsg.value = ''
  if (!form.username || !form.password) {
    errorMsg.value = 'Por favor ingresa tu usuario y contraseña.'
    return
  }
  const unsafe = findUnsafeField({ Usuario: form.username })
  if (unsafe) {
    errorMsg.value = `El campo "${unsafe}" contiene caracteres o código no permitido.`
    return
  }

  const res = await auth.login(form.username, form.password)
  if (res.ok) {
    ui.success(`Bienvenido, ${auth.user?.username || form.username}`, 'Sesión iniciada')
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    router.replace(redirect)
  } else {
    errorMsg.value = res.message
  }
}
</script>
