<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
          <ShieldCheck class="h-7 w-7 text-indigo-600" />
          Gestión de Seguridad
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Administración de usuarios, roles del sistema, matriz de permisos granulares y registro inmutable de auditoría.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="activeTab === 'users' && canCreateUser"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-xs"
          @click="openCreateUserModal"
        >
          <UserPlus class="h-4 w-4" />
          Nuevo Usuario
        </button>

        <button
          v-if="activeTab === 'roles' && canCreateRole"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-xs"
          @click="openCreateRoleModal"
        >
          <Plus class="h-4 w-4" />
          Nuevo Rol
        </button>

        <button
          v-if="activeTab === 'audit'"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
          @click="loadAuditLogs"
        >
          <RotateCcw class="h-4 w-4 text-slate-400" />
          Actualizar
        </button>
      </div>
    </div>

    <!-- Tab Navigation Pills -->
    <div class="flex border-b border-slate-200">
      <nav class="-mb-px flex space-x-6">
        <button
          type="button"
          class="flex items-center gap-2 py-3 px-1 border-b-2 font-medium text-sm transition"
          :class="activeTab === 'users' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
          @click="activeTab = 'users'"
        >
          <Users class="h-4 w-4" />
          <span>Usuarios</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
            {{ secStore.userStats.total }}
          </span>
        </button>

        <button
          type="button"
          class="flex items-center gap-2 py-3 px-1 border-b-2 font-medium text-sm transition"
          :class="activeTab === 'roles' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
          @click="activeTab = 'roles'"
        >
          <KeyRound class="h-4 w-4" />
          <span>Roles y Permisos</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
            {{ secStore.roles.length }}
          </span>
        </button>

        <button
          type="button"
          class="flex items-center gap-2 py-3 px-1 border-b-2 font-medium text-sm transition"
          :class="activeTab === 'audit' ? 'border-indigo-600 text-indigo-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'"
          @click="activeTab = 'audit'"
        >
          <History class="h-4 w-4" />
          <span>Registro de Auditoría</span>
          <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
            {{ secStore.auditStats.total_all }}
          </span>
        </button>
      </nav>
    </div>

    <!-- TAB 1: USUARIOS -->
    <div v-if="activeTab === 'users'" class="space-y-6">
      <!-- Stats Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Usuarios</span>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ secStore.userStats.total }}</p>
          <p class="text-xs text-slate-400">Cuentas registradas</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Activos</span>
          <p class="mt-2 text-2xl font-bold text-emerald-700">{{ secStore.userStats.active }}</p>
          <p class="text-xs text-slate-400">Sesiones habilitadas</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Inactivos</span>
          <p class="mt-2 text-2xl font-bold text-rose-700">{{ secStore.userStats.inactive }}</p>
          <p class="text-xs text-slate-400">Acceso revocado</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Superadmins</span>
          <p class="mt-2 text-2xl font-bold text-indigo-700">{{ secStore.userStats.superadmins }}</p>
          <p class="text-xs text-slate-400">Control total</p>
        </div>
      </div>

      <!-- Filters Toolbar -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
          <div class="sm:col-span-6 relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              v-model="userFilters.search"
              type="text"
              placeholder="Buscar por usuario, nombre, email o teléfono..."
              class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
              @input="handleUserSearchInput"
            />
          </div>

          <div class="sm:col-span-3">
            <select
              v-model="userFilters.role_id"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
              @change="loadUsers"
            >
              <option value="">Todos los roles</option>
              <option v-for="r in secStore.roles" :key="r.role_id" :value="r.role_id">
                {{ r.role_name }}
              </option>
            </select>
          </div>

          <div class="sm:col-span-3">
            <select
              v-model="userFilters.active"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
              @change="loadUsers"
            >
              <option value="">Todos los estados</option>
              <option value="1">Solo Activos</option>
              <option value="0">Solo Inactivos</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-2xs overflow-hidden">
        <div v-if="secStore.loadingUsers" class="flex flex-col items-center justify-center p-12 text-slate-400">
          <Loader2 class="h-8 w-8 animate-spin text-indigo-600 mb-3" />
          <p class="text-sm">Cargando directorio de usuarios...</p>
        </div>

        <div v-else-if="secStore.users.length === 0" class="p-12 text-center text-slate-500">
          <p class="text-base font-semibold">No se encontraron usuarios coincidentes</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Usuario</th>
                <th class="py-3 px-4">Rol Asignado</th>
                <th class="py-3 px-4">Sucursal</th>
                <th class="py-3 px-4 text-center">Estado</th>
                <th class="py-3 px-4">Último Ingreso</th>
                <th class="py-3 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="u in secStore.users" :key="u.user_id" class="hover:bg-slate-50/70 transition-colors">
                <!-- User -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs uppercase">
                      {{ u.username.slice(0, 2) }}
                    </div>
                    <div>
                      <div class="flex items-center gap-1.5">
                        <span class="font-bold text-slate-900">{{ u.full_name }}</span>
                        <span v-if="u.is_current_user" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">Tú</span>
                      </div>
                      <p class="text-xs text-slate-500 font-mono">@{{ u.username }}</p>
                    </div>
                  </div>
                </td>

                <!-- Role -->
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                    :class="u.is_superadmin ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-800'"
                  >
                    {{ u.role_name }}
                  </span>
                </td>

                <!-- Branch -->
                <td class="py-3 px-4 text-xs">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold"
                    :class="u.branch_id ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700'"
                  >
                    {{ u.branch_name }}
                  </span>
                </td>

                <!-- Status -->
                <td class="py-3 px-4 text-center">
                  <StatusBadge
                    :variant="u.active === 1 ? 'success' : 'danger'"
                    :label="u.active === 1 ? 'Activo' : 'Inactivo'"
                  />
                </td>

                <!-- Last Login -->
                <td class="py-3 px-4 text-xs text-slate-500">
                  {{ formatDateTime(u.last_login) }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right">
                  <div class="inline-flex items-center gap-1.5 justify-end">
                    <button
                      v-if="canUpdateUser"
                      type="button"
                      class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 transition"
                      title="Editar usuario"
                      @click="openEditUserModal(u)"
                    >
                      <Edit3 class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canDeleteUser && !u.is_current_user"
                      type="button"
                      class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                      title="Eliminar usuario"
                      @click="openDeleteUserModal(u)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: ROLES Y PERMISOS -->
    <div v-if="activeTab === 'roles'" class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h3 class="text-base font-bold text-slate-900">Roles del Sistema y Matriz de Privilegios</h3>
          <p class="text-xs text-slate-500 mt-0.5">
            Cada rol define permisos específicos de lectura, creación, modificación y borrado para los 15 módulos del sistema.
          </p>
        </div>
      </div>

      <!-- Roles Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="r in secStore.roles"
          :key="r.role_id"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs flex flex-col justify-between hover:border-indigo-200 transition"
        >
          <div>
            <div class="flex items-start justify-between gap-2">
              <div>
                <div class="flex items-center gap-2">
                  <h4 class="font-bold text-slate-900 text-base">{{ r.role_name }}</h4>
                  <span
                    v-if="r.is_system"
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                  >
                    Sistema
                  </span>
                  <span
                    v-else
                    class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700"
                  >
                    Personalizado
                  </span>
                </div>
                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ r.role_slug }}</p>
              </div>

              <span class="rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600 border border-slate-100">
                {{ r.user_count }} {{ r.user_count === 1 ? 'usuario' : 'usuarios' }}
              </span>
            </div>

            <p class="text-xs text-slate-600 mt-3 line-clamp-2">
              {{ r.description || 'Sin descripción detallada para este perfil.' }}
            </p>

            <!-- Permissions overview -->
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
              <span class="flex items-center gap-1.5 font-medium">
                <Lock class="h-3.5 w-3.5 text-indigo-600" />
                {{ r.role_slug === 'superadmin' ? 'Acceso Total (15/15)' : `${countGrantedModules(r)} de 15 módulos activos` }}
              </span>
            </div>
          </div>

          <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
            <button
              v-if="canUpdateRole"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
              @click="openEditRoleModal(r)"
            >
              <Sliders class="h-3.5 w-3.5 text-slate-500" />
              <span>Configurar Permisos</span>
            </button>
            <button
              v-if="canDeleteRole && !r.is_system && r.user_count === 0"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition"
              @click="openDeleteRoleModal(r)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: REGISTRO DE AUDITORÍA -->
    <div v-if="activeTab === 'audit'" class="space-y-6">
      <!-- Audit Stats -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Histórico</span>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ secStore.auditStats.total_all }}</p>
          <p class="text-xs text-slate-400">Eventos registrados</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Operaciones Hoy</span>
          <p class="mt-2 text-2xl font-bold text-indigo-700">{{ secStore.auditStats.today_logs }}</p>
          <p class="text-xs text-slate-400">{{ secStore.auditStats.today_users }} usuarios distintos</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Logins (24h)</span>
          <p class="mt-2 text-2xl font-bold text-sky-700">{{ secStore.auditStats.auth_events_24h }}</p>
          <p class="text-xs text-slate-400">Autenticaciones</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Alertas de Seguridad</span>
          <p class="mt-2 text-2xl font-bold" :class="secStore.auditStats.security_alerts > 0 ? 'text-rose-600' : 'text-emerald-700'">
            {{ secStore.auditStats.security_alerts }}
          </p>
          <p class="text-xs text-slate-400">Accesos denegados</p>
        </div>
      </div>

      <!-- Audit Filters -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
          <div class="sm:col-span-4 relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <input
              v-model="auditFilters.search"
              type="text"
              placeholder="Buscar por usuario, IP, módulo o descripción..."
              class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
              @input="handleAuditSearchInput"
            />
          </div>

          <div class="sm:col-span-3">
            <select
              v-model="auditFilters.module"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
              @change="loadAuditLogs"
            >
              <option value="all">Todos los módulos</option>
              <option v-for="m in secStore.availableModules" :key="m.key" :value="m.key">
                {{ m.name }}
              </option>
            </select>
          </div>

          <div class="sm:col-span-3">
            <select
              v-model="auditFilters.action"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
              @change="loadAuditLogs"
            >
              <option value="all">Todas las acciones</option>
              <option value="LOGIN">LOGIN</option>
              <option value="LOGOUT">LOGOUT</option>
              <option value="CREATE">CREATE</option>
              <option value="UPDATE">UPDATE</option>
              <option value="DELETE">DELETE</option>
              <option value="ACCESS_DENIED">ACCESS_DENIED</option>
              <option value="PASSWORD_CHANGE">PASSWORD_CHANGE</option>
            </select>
          </div>

          <div class="sm:col-span-2">
            <button
              type="button"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
              @click="resetAuditFilters"
            >
              Limpiar
            </button>
          </div>
        </div>
      </div>

      <!-- Audit Logs Table -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-2xs overflow-hidden">
        <div v-if="secStore.loadingAudit" class="flex flex-col items-center justify-center p-12 text-slate-400">
          <Loader2 class="h-8 w-8 animate-spin text-indigo-600 mb-3" />
          <p class="text-sm">Consultando bitácora de auditoría...</p>
        </div>

        <div v-else-if="secStore.auditLogs.length === 0" class="p-12 text-center text-slate-500">
          <p class="text-base font-semibold">No se encontraron eventos registrados con los filtros aplicados</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Fecha y Hora</th>
                <th class="py-3 px-4">Usuario</th>
                <th class="py-3 px-3">Módulo</th>
                <th class="py-3 px-3 text-center">Acción</th>
                <th class="py-3 px-4">Descripción del Evento</th>
                <th class="py-3 px-3">IP</th>
                <th class="py-3 px-4 text-right">Detalle</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="log in secStore.auditLogs" :key="log.log_id" class="hover:bg-slate-50/70 transition-colors">
                <td class="py-3 px-4 text-xs font-mono text-slate-600 whitespace-nowrap">
                  {{ formatDateTime(log.created_at) }}
                </td>
                <td class="py-3 px-4 text-xs font-semibold text-slate-900">
                  <p>{{ log.full_name || log.username }}</p>
                  <p class="text-slate-400 font-mono">@{{ log.username }}</p>
                </td>
                <td class="py-3 px-3 text-xs font-medium text-slate-700 capitalize">
                  {{ log.module }}
                </td>
                <td class="py-3 px-3 text-center">
                  <span
                    class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase font-mono"
                    :class="getActionBadgeClass(log.action)"
                  >
                    {{ log.action }}
                  </span>
                </td>
                <td class="py-3 px-4 text-xs text-slate-700 max-w-[280px] truncate" :title="log.description">
                  {{ log.description }}
                </td>
                <td class="py-3 px-3 text-xs font-mono text-slate-500">
                  {{ log.ip_address }}
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    type="button"
                    class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 transition"
                    title="Ver detalle del evento"
                    @click="openAuditDetail(log)"
                  >
                    <Eye class="h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <UserModal
      :open="userModalOpen"
      :user="selectedUser"
      :roles="secStore.roles"
      :branches="auth.branches"
      :submitting="secStore.saving"
      @close="userModalOpen = false"
      @save="handleSaveUser"
    />

    <RoleModal
      :open="roleModalOpen"
      :role="selectedRole"
      :available-modules="secStore.availableModules"
      :submitting="secStore.saving"
      @close="roleModalOpen = false"
      @save="handleSaveRole"
    />

    <AuditDetailModal
      :open="auditDetailOpen"
      :log="selectedAuditLog"
      @close="auditDetailOpen = false"
    />

    <ConfirmModal
      :open="confirmDeleteOpen"
      :title="deleteTitle"
      :message="deleteMessage"
      :loading="secStore.saving"
      @close="confirmDeleteOpen = false"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import {
  Edit3,
  Eye,
  History,
  KeyRound,
  Loader2,
  Lock,
  Plus,
  RotateCcw,
  Search,
  ShieldCheck,
  Sliders,
  Trash2,
  UserPlus,
  Users,
} from '@lucide/vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'
import UserModal from '@/components/security/UserModal.vue'
import RoleModal from '@/components/security/RoleModal.vue'
import AuditDetailModal from '@/components/security/AuditDetailModal.vue'
import { useSecurityStore } from '@/stores/security'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'

const secStore = useSecurityStore()
const auth = useAuthStore()
const ui = useUiStore()

const activeTab = ref('users')

const userModalOpen = ref(false)
const selectedUser = ref(null)

const roleModalOpen = ref(false)
const selectedRole = ref(null)

const auditDetailOpen = ref(false)
const selectedAuditLog = ref(null)

const confirmDeleteOpen = ref(false)
const itemToDelete = ref(null)
const deleteType = ref('user')

const canCreateUser = computed(() => auth.can('users', 'create'))
const canUpdateUser = computed(() => auth.can('users', 'update'))
const canDeleteUser = computed(() => auth.can('users', 'delete'))

const canCreateRole = computed(() => auth.can('roles', 'create'))
const canUpdateRole = computed(() => auth.can('roles', 'update'))
const canDeleteRole = computed(() => auth.can('roles', 'delete'))

const userFilters = reactive({
  search: '',
  role_id: '',
  active: '',
})

const auditFilters = reactive({
  search: '',
  module: 'all',
  action: 'all',
})

let userSearchTimeout = null
function handleUserSearchInput() {
  clearTimeout(userSearchTimeout)
  userSearchTimeout = setTimeout(() => {
    loadUsers()
  }, 300)
}

let auditSearchTimeout = null
function handleAuditSearchInput() {
  clearTimeout(auditSearchTimeout)
  auditSearchTimeout = setTimeout(() => {
    loadAuditLogs()
  }, 300)
}

async function loadUsers() {
  try {
    await secStore.fetchUsers(userFilters)
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al cargar usuarios.'))
  }
}

async function loadRoles() {
  try {
    await secStore.fetchRoles()
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al cargar roles.'))
  }
}

async function loadAuditLogs() {
  try {
    await secStore.fetchAuditLogs(auditFilters)
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al consultar logs de auditoría.'))
  }
}

function resetAuditFilters() {
  auditFilters.search = ''
  auditFilters.module = 'all'
  auditFilters.action = 'all'
  loadAuditLogs()
}

function openCreateUserModal() {
  selectedUser.value = null
  userModalOpen.value = true
}

function openEditUserModal(u) {
  selectedUser.value = { ...u }
  userModalOpen.value = true
}

async function handleSaveUser(payload) {
  try {
    if (payload.user_id) {
      const res = await secStore.updateUser(payload)
      ui.success(res?.messages || 'Usuario actualizado correctamente.')
    } else {
      const res = await secStore.createUser(payload)
      ui.success(res?.messages || 'Usuario creado correctamente.')
    }
    userModalOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al guardar usuario.'))
  }
}

function openDeleteUserModal(u) {
  deleteType.value = 'user'
  itemToDelete.value = u
  confirmDeleteOpen.value = true
}

function openCreateRoleModal() {
  selectedRole.value = null
  roleModalOpen.value = true
}

function openEditRoleModal(r) {
  selectedRole.value = { ...r }
  roleModalOpen.value = true
}

async function handleSaveRole(payload) {
  try {
    if (payload.role_id) {
      const res = await secStore.updateRole(payload)
      ui.success(res?.messages || 'Rol actualizado correctamente.')
    } else {
      const res = await secStore.createRole(payload)
      ui.success(res?.messages || 'Rol creado correctamente.')
    }
    roleModalOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al guardar rol.'))
  }
}

function openDeleteRoleModal(r) {
  deleteType.value = 'role'
  itemToDelete.value = r
  confirmDeleteOpen.value = true
}

function openAuditDetail(log) {
  selectedAuditLog.value = log
  auditDetailOpen.value = true
}

const deleteTitle = computed(() => {
  return deleteType.value === 'user' ? 'Confirmar eliminación de usuario' : 'Confirmar eliminación de rol'
})

const deleteMessage = computed(() => {
  if (deleteType.value === 'user') {
    return `¿Realmente deseas eliminar al usuario "${itemToDelete.value?.username}"? Si tiene registros asociados de ventas o caja, la cuenta será desactivada permanentemente.`
  }
  return `¿Deseas eliminar el rol personalizado "${itemToDelete.value?.role_name}"?`
})

async function handleConfirmDelete() {
  if (!itemToDelete.value) return
  try {
    if (deleteType.value === 'user') {
      const res = await secStore.removeUser(itemToDelete.value.user_id)
      ui.success(res?.messages || 'Usuario eliminado o desactivado correctamente.')
    } else {
      const res = await secStore.removeRole(itemToDelete.value.role_id)
      ui.success(res?.messages || 'Rol eliminado correctamente.')
    }
    confirmDeleteOpen.value = false
    itemToDelete.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error en la eliminación.'))
  }
}

function countGrantedModules(role) {
  if (!Array.isArray(role?.permissions)) return 0
  return role.permissions.filter((p) => p.can_access).length
}

function formatDateTime(str) {
  if (!str) return '—'
  const d = new Date(str.replace(' ', 'T'))
  if (isNaN(d.getTime())) return str
  return d.toLocaleString('es-SV', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function getActionBadgeClass(action) {
  switch (action) {
    case 'CREATE':
      return 'bg-emerald-100 text-emerald-800'
    case 'UPDATE':
      return 'bg-amber-100 text-amber-800'
    case 'DELETE':
    case 'DEACTIVATE':
      return 'bg-rose-100 text-rose-800'
    case 'LOGIN':
      return 'bg-blue-100 text-blue-800'
    case 'LOGOUT':
      return 'bg-slate-100 text-slate-800'
    case 'ACCESS_DENIED':
    case 'LOGIN_FAILED':
      return 'bg-red-100 text-red-800'
    case 'PASSWORD_CHANGE':
      return 'bg-purple-100 text-purple-800'
    default:
      return 'bg-slate-100 text-slate-800'
  }
}

onMounted(() => {
  loadUsers()
  loadRoles()
  loadAuditLogs()
})
</script>

