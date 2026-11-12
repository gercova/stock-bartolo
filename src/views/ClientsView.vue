<template>
  <div class="space-y-6">
    <!-- Top Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2.5">
          <Users class="h-7 w-7 text-indigo-600" />
          Clientes y Proveedores
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
          Padrón centralizado con consulta automatizada en RENIEC / SUNAT vía Decolecta y borrado lógico en base de datos.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          v-if="canCreate"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-xs"
          @click="openCreateModal"
        >
          <Plus class="h-4 w-4" />
          Nuevo Cliente / Proveedor
        </button>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Padrón</span>
          <span class="rounded-lg bg-indigo-50 p-2 text-indigo-600">
            <Users class="h-4 w-4" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ clientsStore.stats.total }}</p>
        <p class="text-xs text-slate-400">Registros no eliminados</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clientes</span>
          <span class="rounded-lg bg-sky-50 p-2 text-sky-600">
            <UserCheck class="h-4 w-4" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold text-sky-700">{{ clientsStore.stats.clients_count }}</p>
        <p class="text-xs text-slate-400">Compradores y prestatarios</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Proveedores</span>
          <span class="rounded-lg bg-purple-50 p-2 text-purple-600">
            <Truck class="h-4 w-4" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold text-purple-700">{{ clientsStore.stats.suppliers_count }}</p>
        <p class="text-xs text-slate-400">Abastecedores comerciales</p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Activos</span>
          <span class="rounded-lg bg-emerald-50 p-2 text-emerald-600">
            <CheckCircle2 class="h-4 w-4" />
          </span>
        </div>
        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ clientsStore.stats.active_count }}</p>
        <p class="text-xs text-slate-400">Habilitados para ventas</p>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-3">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Type Pill Selector -->
        <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-medium">
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 transition"
            :class="filters.client_type === 'all' ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
            @click="setClientTypeFilter('all')"
          >
            Todos ({{ clientsStore.stats.total }})
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 transition"
            :class="filters.client_type === 'client' ? 'bg-white text-indigo-700 shadow-2xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
            @click="setClientTypeFilter('client')"
          >
            Clientes ({{ clientsStore.stats.clients_count }})
          </button>
          <button
            type="button"
            class="rounded-lg px-3 py-1.5 transition"
            :class="filters.client_type === 'supplier' ? 'bg-white text-purple-700 shadow-2xs font-semibold' : 'text-slate-600 hover:text-slate-900'"
            @click="setClientTypeFilter('supplier')"
          >
            Proveedores ({{ clientsStore.stats.suppliers_count }})
          </button>
        </div>

        <!-- Search Bar -->
        <div class="relative flex-1 max-w-md">
          <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Buscar por documento, nombre, teléfono o correo..."
            class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20"
            @input="handleSearchInput"
          />
        </div>
      </div>

      <!-- Secondary Filters -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-100">
        <div>
          <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Tipo Documento</label>
          <select
            v-model="filters.document_type"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
            @change="loadClients"
          >
            <option value="all">Todos los documentos</option>
            <option value="DNI">DNI (RENIEC)</option>
            <option value="RUC">RUC (SUNAT)</option>
            <option value="CE">Carnet de Extranjería (CE)</option>
            <option value="PASAPORTE">Pasaporte</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Estado</label>
          <select
            v-model="filters.status"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500 focus:outline-hidden"
            @change="loadClients"
          >
            <option value="all">Todos los estados</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
          </select>
        </div>

        <div class="flex items-end">
          <button
            type="button"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition"
            @click="resetFilters"
          >
            Limpiar Filtros
          </button>
        </div>
      </div>
    </div>

    <!-- Table Container -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-2xs overflow-hidden">
      <!-- Loading State -->
      <div v-if="clientsStore.loading" class="flex flex-col items-center justify-center p-12 text-slate-400">
        <Loader2 class="h-8 w-8 animate-spin text-indigo-600 mb-3" />
        <p class="text-sm">Cargando padrón de clientes y proveedores...</p>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="clientsStore.clients.length === 0"
        class="flex flex-col items-center justify-center p-12 text-center"
      >
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
          <Users class="h-6 w-6" />
        </div>
        <h3 class="text-base font-semibold text-slate-800">No se encontraron clientes ni proveedores</h3>
        <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-sm">
          Añade nuevos registros o ajusta los criterios de búsqueda para visualizar información.
        </p>
        <button
          v-if="canCreate"
          type="button"
          class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 shadow-xs transition"
          @click="openCreateModal"
        >
          <Plus class="h-4 w-4" />
          Registrar Primer Cliente
        </button>
      </div>

      <!-- Desktop Table -->
      <div v-else class="hidden md:block overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-600 border-b border-slate-200">
            <tr>
              <th class="py-3 px-4">Documento</th>
              <th class="py-3 px-4">Nombre / Razón Social</th>
              <th class="py-3 px-3">Tipo</th>
              <th class="py-3 px-4">Contacto</th>
              <th class="py-3 px-4">Dirección y Ubigeo</th>
              <th class="py-3 px-3 text-center">Estado</th>
              <th class="py-3 px-4 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="c in clientsStore.clients"
              :key="c.client_id"
              class="hover:bg-slate-50/70 transition-colors"
            >
              <!-- Document -->
              <td class="py-3 px-4">
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider font-mono"
                  :class="c.document_type === 'DNI' ? 'bg-sky-100 text-sky-800' : (c.document_type === 'RUC' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700')"
                >
                  {{ c.document_type }}
                </span>
                <p class="font-mono text-xs font-semibold text-slate-900 mt-0.5">{{ c.document_number }}</p>
              </td>

              <!-- Name -->
              <td class="py-3 px-4">
                <p class="font-semibold text-slate-900">{{ c.name }}</p>
                <p v-if="c.first_name || c.last_name" class="text-xs text-slate-400">
                  {{ [c.first_name, c.last_name].filter(Boolean).join(' ') }}
                </p>
              </td>

              <!-- Type -->
              <td class="py-3 px-3">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="getClientTypeBadgeClass(c.client_type)"
                >
                  <component :is="getClientTypeIcon(c.client_type)" class="h-3 w-3" />
                  {{ formatClientType(c.client_type) }}
                </span>
              </td>

              <!-- Contact -->
              <td class="py-3 px-4 text-xs text-slate-600">
                <p v-if="c.phone" class="font-medium text-slate-800">{{ c.phone }}</p>
                <p v-if="c.email" class="text-slate-400 truncate max-w-[180px]">{{ c.email }}</p>
                <p v-if="!c.phone && !c.email" class="text-slate-400 italic">Sin datos</p>
              </td>

              <!-- Location -->
              <td class="py-3 px-4 text-xs text-slate-600 max-w-[200px]">
                <p v-if="c.address" class="truncate font-medium text-slate-800" :title="c.address">{{ c.address }}</p>
                <p v-if="c.district || c.department" class="text-slate-400 truncate">
                  {{ [c.district, c.province, c.department].filter(Boolean).join(', ') }}
                </p>
                <p v-if="!c.address && !c.district" class="text-slate-400 italic">—</p>
              </td>

              <!-- Status -->
              <td class="py-3 px-3 text-center">
                <StatusBadge
                  :variant="c.status === 1 ? 'success' : 'neutral'"
                  :label="c.status === 1 ? 'Activo' : 'Inactivo'"
                />
              </td>

              <!-- Actions -->
              <td class="py-3 px-4 text-right">
                <div class="inline-flex items-center gap-1.5 justify-end">
                  <button
                    v-if="canUpdate"
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 transition"
                    title="Editar cliente"
                    @click="openEditModal(c)"
                  >
                    <Edit3 class="h-4 w-4" />
                  </button>
                  <button
                    v-if="canDelete"
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition"
                    title="Eliminar (Soft Delete)"
                    @click="openDeleteModal(c)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards View -->
      <div class="md:hidden divide-y divide-slate-100">
        <div
          v-for="c in clientsStore.clients"
          :key="c.client_id"
          class="p-4 space-y-2.5"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <div class="flex items-center gap-1.5">
                <span
                  class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold font-mono"
                  :class="c.document_type === 'DNI' ? 'bg-sky-100 text-sky-800' : 'bg-indigo-100 text-indigo-800'"
                >
                  {{ c.document_type }}
                </span>
                <span class="font-mono text-xs font-bold text-slate-800">{{ c.document_number }}</span>
              </div>
              <h4 class="font-bold text-slate-900 text-sm mt-1">{{ c.name }}</h4>
            </div>

            <StatusBadge
              :variant="c.status === 1 ? 'success' : 'neutral'"
              :label="c.status === 1 ? 'Activo' : 'Inactivo'"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span
              class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-semibold text-[11px]"
              :class="getClientTypeBadgeClass(c.client_type)"
            >
              <component :is="getClientTypeIcon(c.client_type)" class="h-3 w-3" />
              {{ formatClientType(c.client_type) }}
            </span>
            <span v-if="c.phone" class="text-slate-600 font-medium">{{ c.phone }}</span>
          </div>

          <p v-if="c.address" class="text-xs text-slate-500 line-clamp-1">{{ c.address }}</p>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <button
              v-if="canUpdate"
              type="button"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              @click="openEditModal(c)"
            >
              <Edit3 class="h-3.5 w-3.5" />
              Editar
            </button>
            <button
              v-if="canDelete"
              type="button"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 text-xs font-semibold text-rose-600 hover:bg-rose-50"
              @click="openDeleteModal(c)"
            >
              <Trash2 class="h-3.5 w-3.5" />
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <ClientModal
      :open="modalOpen"
      :client="selectedClient"
      :branches="auth.branches"
      :submitting="clientsStore.saving"
      @close="modalOpen = false"
      @save="handleSaveClient"
    />

    <ConfirmModal
      :open="confirmDeleteOpen"
      title="Confirmar Borrado Lógico (Soft Delete)"
      :message="deleteMessage"
      confirm-label="Eliminar Registro"
      :loading="clientsStore.saving"
      @close="confirmDeleteOpen = false"
      @confirm="handleConfirmDelete"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import {
  Briefcase,
  CheckCircle2,
  Edit3,
  Loader2,
  Plus,
  Search,
  Trash2,
  Truck,
  User,
  UserCheck,
  Users,
} from '@lucide/vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'
import ClientModal from '@/components/clients/ClientModal.vue'
import { useClientsStore } from '@/stores/clients'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'

const clientsStore = useClientsStore()
const auth = useAuthStore()
const ui = useUiStore()

const modalOpen = ref(false)
const selectedClient = ref(null)
const confirmDeleteOpen = ref(false)
const clientToDelete = ref(null)

const canCreate = computed(() => auth.can('clients', 'create'))
const canUpdate = computed(() => auth.can('clients', 'update'))
const canDelete = computed(() => auth.can('clients', 'delete'))

const filters = reactive({
  search: '',
  client_type: 'all',
  document_type: 'all',
  status: 'all',
})

let searchTimeout = null
function handleSearchInput() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    loadClients()
  }, 300)
}

function setClientTypeFilter(type) {
  filters.client_type = type
  loadClients()
}

function resetFilters() {
  filters.search = ''
  filters.client_type = 'all'
  filters.document_type = 'all'
  filters.status = 'all'
  loadClients()
}

async function loadClients() {
  try {
    await clientsStore.fetchClients({
      search: filters.search,
      client_type: filters.client_type,
      document_type: filters.document_type,
      status: filters.status,
    })
  } catch (err) {
    ui.error(extractErrorMessage(err, 'No fue posible cargar el padrón de clientes.'))
  }
}

function openCreateModal() {
  selectedClient.value = null
  modalOpen.value = true
}

function openEditModal(c) {
  selectedClient.value = { ...c }
  modalOpen.value = true
}

async function handleSaveClient(payload) {
  try {
    if (payload.client_id) {
      const res = await clientsStore.updateClient(payload)
      ui.success(res?.messages || 'Registro actualizado correctamente.')
    } else {
      const res = await clientsStore.createClient(payload)
      ui.success(res?.messages || 'Cliente registrado correctamente.')
    }
    modalOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al guardar el cliente.'))
  }
}

function openDeleteModal(c) {
  clientToDelete.value = c
  confirmDeleteOpen.value = true
}

const deleteMessage = computed(() => {
  if (!clientToDelete.value) return ''
  return `¿Estás seguro de eliminar a "${clientToDelete.value.name}" (${clientToDelete.value.document_type} ${clientToDelete.value.document_number})? Se aplicará un borrado lógico (soft delete) para conservar intacto el historial transaccional de ventas y préstamos.`
})

async function handleConfirmDelete() {
  if (!clientToDelete.value) return
  try {
    const res = await clientsStore.removeClient(clientToDelete.value.client_id)
    ui.success(res?.messages || 'Registro eliminado mediante borrado lógico.')
    confirmDeleteOpen.value = false
    clientToDelete.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err, 'Error al eliminar el registro.'))
  }
}

function formatClientType(type) {
  switch (type) {
    case 'supplier':
      return 'Proveedor'
    case 'both':
      return 'Cliente y Prov.'
    default:
      return 'Cliente'
  }
}

function getClientTypeIcon(type) {
  switch (type) {
    case 'supplier':
      return Truck
    case 'both':
      return Briefcase
    default:
      return User
  }
}

function getClientTypeBadgeClass(type) {
  switch (type) {
    case 'supplier':
      return 'bg-purple-50 text-purple-700 ring-1 ring-purple-600/20'
    case 'both':
      return 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/20'
    default:
      return 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20'
  }
}

onMounted(() => {
  loadClients()
})
</script>

