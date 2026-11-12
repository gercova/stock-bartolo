<template>
  <div class="min-h-screen flex flex-col lg:flex-row bg-slate-50">
    <!-- Left Visual Hero (visible on lg+) -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-900 text-white p-12 flex-col justify-between relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 via-slate-900 to-slate-950 pointer-events-none"></div>
      <div class="relative z-10 flex items-center gap-3.5">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 backdrop-blur-xs p-1 shadow-lg overflow-hidden border border-white/20">
          <img
            v-if="companyInfo.logo"
            :src="resolveLogoUrl(companyInfo.logo)"
            :alt="companyInfo.name"
            class="h-full w-full object-contain"
          />
          <Boxes v-else class="h-6 w-6 text-indigo-400" />
        </div>
        <div>
          <p class="text-xl font-extrabold tracking-tight text-white">{{ companyInfo.name || 'Stock Bartolo' }}</p>
          <p class="text-xs text-slate-300 font-medium">{{ companyInfo.legal_name || 'Sistema de Gestión de Inventario' }}</p>
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
        {{ companyInfo.name || 'Stock Bartolo' }} · Sistema Protegido con Rate Limiting
      </p>
    </div>

    <!-- Right Login Form -->
    <div class="flex-1 flex items-center justify-center p-6 sm:p-10">
      <div class="w-full max-w-md space-y-6">
        <!-- Mobile Header -->
        <div class="flex items-center gap-3 lg:hidden justify-center">
          <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white border border-slate-200 p-1 shadow-xs overflow-hidden">
            <img
              v-if="companyInfo.logo"
              :src="resolveLogoUrl(companyInfo.logo)"
              :alt="companyInfo.name"
              class="h-full w-full object-contain"
            />
            <Boxes v-else class="h-6 w-6 text-indigo-600" />
          </div>
          <div class="text-left">
            <p class="text-base font-bold text-slate-900">{{ companyInfo.name || 'Stock Bartolo' }}</p>
            <p class="text-xs text-slate-500">{{ companyInfo.legal_name || 'Gestión de Inventario' }}</p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
          <div class="mb-6 flex items-center gap-3.5">
            <div
              v-if="companyInfo.logo"
              class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-200 bg-white p-1.5 shadow-xs overflow-hidden shrink-0"
            >
              <img
                :src="resolveLogoUrl(companyInfo.logo)"
                :alt="companyInfo.name"
                class="h-full w-full object-contain"
              />
            </div>
            <div>
              <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ companyInfo.name || 'Iniciar sesión' }}</h1>
              <p class="mt-0.5 text-xs sm:text-sm text-slate-500">
                {{ companyInfo.legal_name ? `${companyInfo.legal_name} · ` : '' }}Ingresa tus credenciales para acceder.
              </p>
            </div>
          </div>

          <!-- Rate Limiter Lockout Banner -->
          <div
            v-if="lockoutSeconds > 0"
            class="mb-5 rounded-2xl border border-amber-300 bg-gradient-to-br from-amber-50 to-orange-50 p-4 text-xs text-amber-950 shadow-xs space-y-2.5"
            role="alert"
          >
            <div class="flex items-center gap-2 font-bold text-amber-900 text-sm">
              <ShieldAlert class="h-5 w-5 text-amber-600 shrink-0" />
              Protección de Acceso Activada (Rate Limiting)
            </div>
            <p class="text-slate-700 leading-relaxed">
              Demasiados intentos fallidos consecutivos. Por seguridad del sistema, el acceso ha sido bloqueado temporalmente contra ataques de fuerza bruta.
            </p>
            <div class="flex items-center gap-2 font-mono font-bold text-amber-900 bg-white/90 border border-amber-200 px-3 py-1.5 rounded-lg w-fit shadow-2xs">
              <Timer class="h-4 w-4 text-amber-600 animate-pulse" />
              Podrás reintentar en: {{ formatLockoutTime(lockoutSeconds) }}
            </div>
          </div>

          <!-- Standard Error Banner (with remaining attempts) -->
          <div
            v-else-if="errorMsg"
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
                  :disabled="lockoutSeconds > 0"
                  placeholder="Nombre de usuario"
                  class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100 disabled:cursor-not-allowed"
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
                  :disabled="lockoutSeconds > 0"
                  placeholder="Contraseña"
                  class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-10 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100 disabled:cursor-not-allowed"
                />
                <button
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 disabled:opacity-50"
                  :disabled="lockoutSeconds > 0"
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
              :disabled="auth.loading || lockoutSeconds > 0"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed transition shadow-sm"
            >
              <Loader2 v-if="auth.loading" class="h-4 w-4 animate-spin" />
              <Timer v-else-if="lockoutSeconds > 0" class="h-4 w-4" />
              <LogIn v-else class="h-4 w-4" />
              <span v-if="lockoutSeconds > 0">
                Bloqueado ({{ formatLockoutTime(lockoutSeconds) }})
              </span>
              <span v-else>
                {{ auth.loading ? 'Validando...' : 'Ingresar' }}
              </span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue'
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
  ShieldAlert,
  Timer,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import apiClient from '@/api/client'
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

const lockoutSeconds = ref(0)
let timerId = null

const companyInfo = ref({
  name: 'Stock Bartolo',
  legal_name: '',
  logo: '',
})

function resolveLogoUrl(path) {
  if (!path) return ''
  if (path.startsWith('data:') || path.startsWith('http://') || path.startsWith('https://')) return path
  return path.startsWith('/') ? path : `/${path}`
}

async function fetchPublicCompanyInfo() {
  try {
    const { data } = await apiClient.get('/fetchPublicCompanyInfo.php')
    if (data?.success) {
      companyInfo.value.name = data.company_name || 'Stock Bartolo'
      companyInfo.value.legal_name = data.company_legal_name || ''
      companyInfo.value.logo = data.company_logo || ''
    }
  } catch {
    // fallback defaults
  }
}

function formatLockoutTime(sec) {
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

function startLockoutTimer(sec) {
  lockoutSeconds.value = Math.max(1, sec)
  if (timerId) clearInterval(timerId)
  timerId = setInterval(() => {
    if (lockoutSeconds.value > 0) {
      lockoutSeconds.value--
    } else {
      clearInterval(timerId)
      timerId = null
      errorMsg.value = ''
    }
  }, 1000)
}

onMounted(async () => {
  await fetchPublicCompanyInfo()
})

onUnmounted(() => {
  if (timerId) clearInterval(timerId)
})

async function submitLogin() {
  if (lockoutSeconds.value > 0) return
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
    if (timerId) clearInterval(timerId)
    ui.success(`Bienvenido, ${auth.user?.username || form.username}`, 'Sesión iniciada')
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard'
    router.replace(redirect)
  } else {
    if (res.status === 429 || res.retryAfter > 0) {
      startLockoutTimer(res.retryAfter || 900)
    }
    errorMsg.value = res.message
  }
}
</script>
