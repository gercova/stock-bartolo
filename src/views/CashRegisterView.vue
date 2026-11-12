<template>
  <div class="space-y-6">
    <!-- Top Header & Branch Vault Banner -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Control de Caja</h2>
          <span
            v-if="activeBranch"
            class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-600/20"
          >
            <Store class="h-3.5 w-3.5" />
            {{ activeBranch.branch_code }} · {{ activeBranch.branch_name }}
          </span>
        </div>
        <p class="text-sm text-slate-500">
          Apertura, movimientos operativos, arqueos de efectivo y cierre (Corte Z) ·
          <RouterLink
            to="/system-config?tab=general-cash"
            class="font-semibold text-indigo-600 hover:underline"
          >
            Caja General disponible: {{ formatCurrency(store.generalCash?.current_balance || 0) }}
          </RouterLink>
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5 shrink-0">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
          @click="openCreateRegister"
        >
          <Plus class="h-4 w-4 text-slate-500" />
          Nueva Caja
        </button>

        <button
          type="button"
          :disabled="availableClosedRegistersCount === 0"
          class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 shadow-xs transition"
          @click="openOpenModal(0)"
        >
          <Unlock class="h-4 w-4" />
          Abrir Caja
        </button>
      </div>
    </div>

    <!-- Loading Indicator -->
    <div
      v-if="store.loading && store.registers.length === 0"
      class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500"
    >
      <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
      Cargando cajas registradoras y turnos...
    </div>

    <template v-else>
      <!-- Live Branch Registers Cards -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div
          v-for="reg in store.registers"
          :key="reg.register_id"
          class="rounded-2xl border bg-white p-5 shadow-xs transition"
          :class="
            sessionByRegister(reg.register_id)
              ? 'border-emerald-300 ring-1 ring-emerald-500/20'
              : 'border-slate-200'
          "
        >
          <!-- Open Session View -->
          <template v-if="sessionByRegister(reg.register_id)">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 pb-3 border-b border-slate-100">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="rounded-md bg-emerald-50 px-2 py-0.5 font-mono text-xs font-bold text-emerald-700">
                    {{ reg.register_code }}
                  </span>
                  <StatusBadge variant="success" :label="`Abierta · ${sessionByRegister(reg.register_id).session_code}`" />
                </div>
                <h3 class="mt-1.5 text-base font-bold text-slate-900">
                  {{ reg.register_name }}
                  <span v-if="reg.location" class="text-xs font-normal text-slate-500">({{ reg.location }})</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  Abierta {{ formatDateTime(sessionByRegister(reg.register_id).opened_at) }} por
                  <strong>{{ sessionByRegister(reg.register_id).opened_by_name }}</strong>
                </p>
              </div>

              <button
                type="button"
                class="self-start inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                @click="openDetailModal(sessionByRegister(reg.register_id).session_id)"
              >
                <Eye class="h-3.5 w-3.5" />
                Corte X / Detalle
              </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 my-4">
              <div class="rounded-xl bg-slate-50 border border-slate-200 p-2.5">
                <p class="text-[11px] text-slate-500">Fondo Inicial</p>
                <p class="text-sm font-bold text-slate-900 mt-0.5">
                  {{ formatCurrency(sessionByRegister(reg.register_id).opening_amount) }}
                </p>
              </div>
              <div class="rounded-xl bg-emerald-50/70 border border-emerald-200 p-2.5">
                <p class="text-[11px] text-emerald-700">Entradas Efectivo</p>
                <p class="text-sm font-bold text-emerald-900 mt-0.5">
                  +{{ formatCurrency(sessionByRegister(reg.register_id).cash_in_total) }}
                </p>
              </div>
              <div class="rounded-xl bg-rose-50/70 border border-rose-200 p-2.5">
                <p class="text-[11px] text-rose-700">Salidas Efectivo</p>
                <p class="text-sm font-bold text-rose-900 mt-0.5">
                  -{{ formatCurrency(sessionByRegister(reg.register_id).cash_out_total) }}
                </p>
              </div>
              <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-2.5">
                <p class="text-[11px] text-indigo-700">Esperado Gaveta</p>
                <p class="text-base font-extrabold text-indigo-900 mt-0.5">
                  {{ formatCurrency(sessionByRegister(reg.register_id).expected_cash) }}
                </p>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500 mb-4">
              <span>
                Cobros no-efectivo (Tarjeta/Transf./Cheque):
                <strong class="text-slate-800">{{ formatCurrency(sessionByRegister(reg.register_id).non_cash_sales_total) }}</strong>
              </span>
              <span v-if="sessionByRegister(reg.register_id).reconciliations_count > 0">
                Último arqueo:
                <strong
                  :class="
                    sessionByRegister(reg.register_id).difference_amount < 0
                      ? 'text-rose-600'
                      : sessionByRegister(reg.register_id).difference_amount > 0
                        ? 'text-sky-700'
                        : 'text-emerald-700'
                  "
                >
                  {{ formatCurrency(sessionByRegister(reg.register_id).counted_cash) }}
                </strong>
              </span>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="flex-1 min-w-[120px] inline-flex items-center justify-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
                @click="openMovementModal(sessionByRegister(reg.register_id))"
              >
                <ArrowLeftRight class="h-3.5 w-3.5" />
                Movimiento +/-
              </button>
              <button
                type="button"
                class="flex-1 min-w-[110px] inline-flex items-center justify-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition"
                @click="openReconcileModal(sessionByRegister(reg.register_id))"
              >
                <ClipboardCheck class="h-3.5 w-3.5" />
                Arqueo
              </button>
              <button
                type="button"
                class="flex-1 min-w-[110px] inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700 shadow-xs transition"
                @click="openCloseModal(sessionByRegister(reg.register_id))"
              >
                <Lock class="h-3.5 w-3.5" />
                Cerrar Caja
              </button>
            </div>
          </template>

          <!-- Closed Register View -->
          <template v-else>
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700">
                    {{ reg.register_code }}
                  </span>
                  <StatusBadge :variant="reg.active === 1 ? 'neutral' : 'danger'" :label="reg.active === 1 ? 'Cerrada · Disponible' : 'Inactiva'" />
                </div>
                <h3 class="mt-2 text-base font-bold text-slate-900">{{ reg.register_name }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                  {{ reg.location || 'Sin ubicación especificada' }}
                </p>
              </div>

              <div class="flex items-center gap-1.5">
                <button
                  type="button"
                  class="rounded-lg border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-100"
                  title="Editar caja"
                  @click="openEditRegister(reg)"
                >
                  <Pencil class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-3.5">
              <p class="text-xs text-slate-500">
                No hay turno activo en esta caja registradora.
              </p>
              <button
                type="button"
                :disabled="reg.active !== 1"
                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-40 transition"
                @click="openOpenModal(reg.register_id)"
              >
                <Unlock class="h-3.5 w-3.5" />
                Abrir turno
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Lower Section Sub-Tabs -->
      <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xs">
        <button
          type="button"
          class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition"
          :class="
            subTab === 'sessions'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          "
          @click="subTab = 'sessions'"
        >
          <History class="h-4 w-4" />
          Turnos e Historial ({{ store.sessions.length }})
        </button>

        <button
          type="button"
          class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition"
          :class="
            subTab === 'registers'
              ? 'bg-indigo-600 text-white shadow-xs'
              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          "
          @click="subTab = 'registers'"
        >
          <Calculator class="h-4 w-4" />
          Cajas Registradoras ({{ store.registers.length }})
        </button>
      </div>

      <!-- SUB-TAB 1: SESSIONS HISTORY -->
      <div v-if="subTab === 'sessions'" class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
          <div class="flex flex-col sm:flex-row flex-wrap gap-3 flex-1">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
              <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model.trim="sessionSearch"
                type="text"
                placeholder="Buscar turno, caja o usuario..."
                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>

            <select
              v-model="sessionStatusFilter"
              class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option value="all">Todos los turnos</option>
              <option value="open">Abiertos</option>
              <option value="closed">Cerrados</option>
            </select>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
          <div v-if="filteredSessions.length === 0" class="p-10 text-center text-sm text-slate-500">
            No se han registrado turnos de caja en esta sucursal.
          </div>

          <template v-else>
            <!-- Desktop Table -->
            <div class="hidden xl:block overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
                  <tr>
                    <th class="px-4 py-3.5">Turno</th>
                    <th class="px-4 py-3.5">Caja</th>
                    <th class="px-4 py-3.5">Apertura / Cierre</th>
                    <th class="px-4 py-3.5 text-right">Inicial</th>
                    <th class="px-4 py-3.5 text-right">Esperado</th>
                    <th class="px-4 py-3.5 text-right">Contado</th>
                    <th class="px-4 py-3.5 text-right">Diferencia</th>
                    <th class="px-4 py-3.5 text-right">A C. General</th>
                    <th class="px-4 py-3.5">Estado</th>
                    <th class="px-4 py-3.5 text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="s in filteredSessions" :key="s.session_id" class="hover:bg-slate-50/70 transition">
                    <td class="px-4 py-3.5 font-mono text-xs font-bold text-indigo-700">{{ s.session_code }}</td>
                    <td class="px-4 py-3.5">
                      <p class="font-semibold text-slate-900">{{ s.register_name }}</p>
                      <p class="text-xs text-slate-500">{{ s.register_code }}</p>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-slate-600">
                      <p>Ap: {{ formatDateTime(s.opened_at) }} ({{ s.opened_by_name }})</p>
                      <p v-if="s.closed_at" class="text-slate-500">
                        Ci: {{ formatDateTime(s.closed_at) }} ({{ s.closed_by_name || 'Sistema' }})
                      </p>
                    </td>
                    <td class="px-4 py-3.5 text-right text-slate-700">{{ formatCurrency(s.opening_amount) }}</td>
                    <td class="px-4 py-3.5 text-right font-semibold text-slate-900">{{ formatCurrency(s.expected_cash) }}</td>
                    <td class="px-4 py-3.5 text-right font-semibold text-slate-900">
                      {{ s.counted_cash !== null ? formatCurrency(s.counted_cash) : '—' }}
                    </td>
                    <td
                      class="px-4 py-3.5 text-right font-bold"
                      :class="
                        s.difference_amount === null
                          ? 'text-slate-400'
                          : s.difference_amount < 0
                            ? 'text-rose-600'
                            : s.difference_amount > 0
                              ? 'text-sky-700'
                              : 'text-emerald-700'
                      "
                    >
                      {{
                        s.difference_amount === null
                          ? '—'
                          : `${s.difference_amount > 0 ? '+' : ''}${formatCurrency(s.difference_amount)}`
                      }}
                    </td>
                    <td class="px-4 py-3.5 text-right text-slate-700">
                      {{ s.status === 'closed' ? formatCurrency(s.amount_returned_general) : '—' }}
                    </td>
                    <td class="px-4 py-3.5">
                      <StatusBadge
                        :variant="s.status === 'open' ? 'success' : 'neutral'"
                        :label="s.status === 'open' ? 'Abierto' : 'Cerrado'"
                      />
                    </td>
                    <td class="px-4 py-3.5 text-right">
                      <div class="inline-flex items-center gap-1">
                        <button
                          type="button"
                          class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1"
                          @click="openDetailModal(s.session_id)"
                        >
                          <Eye class="h-3.5 w-3.5" />
                          Detalle
                        </button>
                        <button
                          v-if="s.status === 'open'"
                          type="button"
                          class="rounded-lg border border-amber-200 px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-50"
                          @click="openReconcileModal(s)"
                        >
                          Arqueo
                        </button>
                        <button
                          v-if="s.status === 'open'"
                          type="button"
                          class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                          @click="openCloseModal(s)"
                        >
                          Cerrar
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Mobile & Tablet Cards -->
            <div class="xl:hidden divide-y divide-slate-100">
              <div v-for="s in filteredSessions" :key="s.session_id" class="p-4 space-y-3">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="font-mono text-xs font-bold text-indigo-700">{{ s.session_code }}</p>
                    <p class="font-semibold text-slate-900">{{ s.register_name }}</p>
                    <p class="text-xs text-slate-500">Ap: {{ formatDateTime(s.opened_at) }} · {{ s.opened_by_name }}</p>
                  </div>
                  <StatusBadge
                    :variant="s.status === 'open' ? 'success' : 'neutral'"
                    :label="s.status === 'open' ? 'Abierto' : 'Cerrado'"
                  />
                </div>

                <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs">
                  <div>
                    <p class="text-slate-500">Inicial</p>
                    <p class="font-semibold text-slate-900">{{ formatCurrency(s.opening_amount) }}</p>
                  </div>
                  <div>
                    <p class="text-slate-500">Esperado</p>
                    <p class="font-semibold text-slate-900">{{ formatCurrency(s.expected_cash) }}</p>
                  </div>
                  <div>
                    <p class="text-slate-500">Contado</p>
                    <p class="font-semibold text-slate-900">
                      {{ s.counted_cash !== null ? formatCurrency(s.counted_cash) : '—' }}
                    </p>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 inline-flex items-center justify-center gap-1"
                    @click="openDetailModal(s.session_id)"
                  >
                    <Eye class="h-3.5 w-3.5" /> Ver Detalle
                  </button>
                  <button
                    v-if="s.status === 'open'"
                    type="button"
                    class="flex-1 rounded-lg border border-amber-200 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-50"
                    @click="openReconcileModal(s)"
                  >
                    Arqueo
                  </button>
                  <button
                    v-if="s.status === 'open'"
                    type="button"
                    class="flex-1 rounded-lg bg-rose-600 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
                    @click="openCloseModal(s)"
                  >
                    Cerrar Caja
                  </button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- SUB-TAB 2: CASH REGISTERS CRUD -->
      <div v-else class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h3 class="text-sm font-bold text-slate-900">Catálogo de Cajas Registradoras en Sucursal</h3>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
            @click="openCreateRegister"
          >
            <Plus class="h-3.5 w-3.5" />
            Nueva caja
          </button>
        </div>

        <div v-if="store.registers.length === 0" class="p-10 text-center text-sm text-slate-500">
          No hay cajas registradoras creadas en esta sucursal.
        </div>

        <template v-else>
          <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
                <tr>
                  <th class="px-5 py-3.5">Código</th>
                  <th class="px-5 py-3.5">Nombre de caja</th>
                  <th class="px-5 py-3.5">Ubicación</th>
                  <th class="px-5 py-3.5">Estado</th>
                  <th class="px-5 py-3.5">Turno</th>
                  <th class="px-5 py-3.5 text-right">Opciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="reg in store.registers" :key="reg.register_id" class="hover:bg-slate-50/70 transition">
                  <td class="px-5 py-3.5 font-mono text-xs font-bold text-slate-700">{{ reg.register_code }}</td>
                  <td class="px-5 py-3.5 font-semibold text-slate-900">{{ reg.register_name }}</td>
                  <td class="px-5 py-3.5 text-slate-600">{{ reg.location || '—' }}</td>
                  <td class="px-5 py-3.5">
                    <StatusBadge :variant="reg.active === 1 ? 'success' : 'neutral'" :label="reg.active === 1 ? 'Activa' : 'Inactiva'" />
                  </td>
                  <td class="px-5 py-3.5">
                    <StatusBadge
                      :variant="reg.open_session_id ? 'indigo' : 'neutral'"
                      :label="reg.open_session_id ? 'Turno abierto' : 'Disponible'"
                    />
                  </td>
                  <td class="px-5 py-3.5 text-right">
                    <div class="inline-flex items-center gap-1">
                      <button
                        v-if="!reg.open_session_id && reg.active === 1"
                        type="button"
                        class="rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                        @click="openOpenModal(reg.register_id)"
                      >
                        Abrir
                      </button>
                      <button
                        type="button"
                        class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                        @click="openEditRegister(reg)"
                      >
                        Editar
                      </button>
                      <button
                        type="button"
                        :disabled="Boolean(reg.open_session_id)"
                        class="rounded-lg border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50 disabled:opacity-35 disabled:cursor-not-allowed"
                        title="Eliminar caja"
                        @click="deletingRegister = reg"
                      >
                        <Trash2 class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="md:hidden divide-y divide-slate-100">
            <div v-for="reg in store.registers" :key="reg.register_id" class="p-4 space-y-2.5">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-mono text-xs font-bold text-slate-600">{{ reg.register_code }}</p>
                  <p class="font-semibold text-slate-900">{{ reg.register_name }}</p>
                  <p class="text-xs text-slate-500">{{ reg.location || 'Sin ubicación' }}</p>
                </div>
                <StatusBadge :variant="reg.open_session_id ? 'indigo' : 'neutral'" :label="reg.open_session_id ? 'Abierta' : 'Disponible'" />
              </div>
              <div class="flex gap-2">
                <button
                  v-if="!reg.open_session_id && reg.active === 1"
                  type="button"
                  class="flex-1 rounded-lg bg-emerald-600 py-1.5 text-xs font-semibold text-white"
                  @click="openOpenModal(reg.register_id)"
                >
                  Abrir
                </button>
                <button
                  type="button"
                  class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-700"
                  @click="openEditRegister(reg)"
                >
                  Editar
                </button>
                <button
                  type="button"
                  :disabled="Boolean(reg.open_session_id)"
                  class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs text-rose-600 disabled:opacity-35"
                  @click="deletingRegister = reg"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </template>

    <!-- Modals -->
    <CashRegisterModal
      :open="registerModalOpen"
      :register="editingRegister"
      :branch-name="activeBranch?.branch_name || ''"
      @close="registerModalOpen = false"
    />

    <OpenCashModal
      :open="openModalOpen"
      :registers="store.registers"
      :preselected-register-id="preselectedRegisterId"
      :general-cash-balance="Number(store.generalCash?.current_balance || 0)"
      :auto-fund-default="Boolean(store.systemConfig?.auto_fund_till_from_general_cash ?? true)"
      :branch-name="activeBranch?.branch_name || ''"
      @close="openModalOpen = false"
    />

    <CashMovementModal
      :open="movementModalOpen"
      :session="targetSession"
      @close="movementModalOpen = false"
    />

    <ReconcileCashModal
      :open="reconcileModalOpen"
      :session="targetSession"
      @close="reconcileModalOpen = false"
    />

    <CloseCashModal
      :open="closeModalOpen"
      :session="targetSession"
      :auto-return-default="Boolean(store.systemConfig?.auto_return_till_to_general_cash ?? true)"
      @close="closeModalOpen = false"
    />

    <CashSessionDetailModal
      :open="detailModalOpen"
      :detail="store.currentDetail"
      :loading="store.loadingDetail"
      @close="detailModalOpen = false"
    />

    <ConfirmModal
      :open="Boolean(deletingRegister)"
      title="Eliminar caja registradora"
      :message="`¿Deseas eliminar la caja ${deletingRegister?.register_name || ''} (${deletingRegister?.register_code || ''})?`"
      :loading="deletingRegisterLoading"
      @close="deletingRegister = null"
      @confirm="confirmDeleteRegister"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Store,
  Plus,
  Unlock,
  Lock,
  Loader2,
  Eye,
  ArrowLeftRight,
  ClipboardCheck,
  Pencil,
  Trash2,
  History,
  Calculator,
  Search,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useCashRegisterStore } from '@/stores/cashRegister'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency, formatDateTime } from '@/utils/formatters'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'
import CashRegisterModal from '@/components/cash/CashRegisterModal.vue'
import OpenCashModal from '@/components/cash/OpenCashModal.vue'
import CashMovementModal from '@/components/cash/CashMovementModal.vue'
import ReconcileCashModal from '@/components/cash/ReconcileCashModal.vue'
import CloseCashModal from '@/components/cash/CloseCashModal.vue'
import CashSessionDetailModal from '@/components/cash/CashSessionDetailModal.vue'

const auth = useAuthStore()
const store = useCashRegisterStore()
const ui = useUiStore()

const subTab = ref('sessions')
const sessionSearch = ref('')
const sessionStatusFilter = ref('all')

const registerModalOpen = ref(false)
const editingRegister = ref(null)
const deletingRegister = ref(null)
const deletingRegisterLoading = ref(false)

const openModalOpen = ref(false)
const preselectedRegisterId = ref(0)
const movementModalOpen = ref(false)
const reconcileModalOpen = ref(false)
const closeModalOpen = ref(false)
const detailModalOpen = ref(false)
const targetSession = ref(null)

const activeBranch = computed(() => store.activeBranch || auth.activeBranch)

const availableClosedRegistersCount = computed(
  () => (store.registers || []).filter((r) => Number(r.active) === 1 && !r.open_session_id).length
)

const filteredSessions = computed(() => {
  const q = sessionSearch.value.toLowerCase()
  return (store.sessions || []).filter((s) => {
    if (sessionStatusFilter.value !== 'all' && s.status !== sessionStatusFilter.value) {
      return false
    }
    if (!q) return true
    return (
      (s.session_code || '').toLowerCase().includes(q) ||
      (s.register_name || '').toLowerCase().includes(q) ||
      (s.register_code || '').toLowerCase().includes(q) ||
      (s.opened_by_name || '').toLowerCase().includes(q) ||
      (s.closed_by_name || '').toLowerCase().includes(q)
    )
  })
})

function sessionByRegister(registerId) {
  return (store.openSessions || []).find((s) => s.register_id === registerId) || null
}

function openCreateRegister() {
  editingRegister.value = null
  registerModalOpen.value = true
}

function openEditRegister(reg) {
  editingRegister.value = reg
  registerModalOpen.value = true
}

function openOpenModal(registerId = 0) {
  preselectedRegisterId.value = registerId
  openModalOpen.value = true
}

function openMovementModal(session) {
  targetSession.value = session
  movementModalOpen.value = true
}

function openReconcileModal(session) {
  targetSession.value = session
  reconcileModalOpen.value = true
}

function openCloseModal(session) {
  targetSession.value = session
  closeModalOpen.value = true
}

async function openDetailModal(sessionId) {
  detailModalOpen.value = true
  try {
    await store.fetchSessionDetail(sessionId)
  } catch (err) {
    ui.error(extractErrorMessage(err))
    detailModalOpen.value = false
  }
}

async function confirmDeleteRegister() {
  if (!deletingRegister.value) return
  deletingRegisterLoading.value = true
  try {
    const res = await store.deleteRegister(deletingRegister.value.register_id)
    ui.success(res?.messages || 'Caja eliminada.')
    deletingRegister.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    deletingRegisterLoading.value = false
  }
}

onMounted(async () => {
  try {
    await store.fetchCashDashboard()
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
})
</script>

