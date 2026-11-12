<template>
  <div class="space-y-6">
    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Operaciones y Fidelización</h2>
          <span
            v-if="auth.activeBranch"
            class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-600/20"
          >
            <Store class="h-3.5 w-3.5" />
            {{ auth.activeBranch.branch_code }} · {{ auth.activeBranch.branch_name }}
          </span>
        </div>
        <p class="text-sm text-slate-500">
          Depósitos, retiros con comisión de 1 sol por cada 100 soles, gestión de saldo central y programa de recompensas por puntos.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5 shrink-0">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
          :disabled="store.loading"
          @click="refreshActiveTab"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': store.loading }" />
          Actualizar
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 shadow-xs transition cursor-pointer"
          @click="openNewOperationModal"
        >
          <Plus class="h-4 w-4" />
          Nueva Operación
        </button>
      </div>
    </div>

    <!-- Mandatory Open Cash Register Warning Banner (if cash register is closed) -->
    <div
      v-if="!checkingCashSession && !hasOpenCashSession"
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-rose-300 bg-rose-50 p-4 sm:p-5 text-rose-900 shadow-xs"
    >
      <div class="flex items-start gap-3 min-w-0">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
          <AlertOctagon class="h-6 w-6" />
        </div>
        <div>
          <h4 class="font-bold text-sm sm:text-base text-rose-950">
            Caja Registradora Cerrada en esta Sucursal
          </h4>
          <p class="text-xs sm:text-sm text-rose-700 mt-0.5 leading-relaxed">
            Para realizar operaciones comerciales de depósitos o retiros, la caja registradora de la sucursal debe estar abierta.
            Por favor, abre un turno de caja para esta ubicación antes de registrar transacciones.
          </p>
        </div>
      </div>

      <RouterLink
        to="/cash-register"
        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs sm:text-sm font-bold text-white hover:bg-rose-700 shadow-sm transition shrink-0"
      >
        <Calculator class="h-4 w-4" />
        Abrir Turno de Caja →
      </RouterLink>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xs">
      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer"
        :class="
          activeTab === 'operations'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="switchTab('operations')"
      >
        <ArrowLeftRight class="h-4 w-4" />
        Operaciones (Depósitos y Retiros)
        <span
          class="rounded-full px-2 py-0.5 text-[10px] font-bold"
          :class="activeTab === 'operations' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
        >
          {{ store.operations.length }}
        </span>
      </button>

      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer"
        :class="
          activeTab === 'ranking'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="switchTab('ranking')"
      >
        <Award class="h-4 w-4" />
        Ranking de Fidelización
        <span
          class="rounded-full px-2 py-0.5 text-[10px] font-bold"
          :class="activeTab === 'ranking' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'"
        >
          Top Clientes
        </span>
      </button>

      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer"
        :class="
          activeTab === 'rewards'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="switchTab('rewards')"
      >
        <Gift class="h-4 w-4" />
        Catálogo de Premios
        <span
          class="rounded-full px-2 py-0.5 text-[10px] font-bold"
          :class="activeTab === 'rewards' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
        >
          {{ store.rewards.length }}
        </span>
      </button>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 1: OPERACIONES (DEPÓSITOS Y RETIROS)                  -->
    <!-- ========================================================= -->
    <div v-if="activeTab === 'operations'" class="space-y-6">
      <!-- Metrics Banner Strip -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Central Cash Drawer Balance -->
        <div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-4 shadow-xs">
          <div class="flex items-center justify-between text-indigo-700">
            <span class="text-xs font-bold">Caja General (Sucursal)</span>
            <Landmark class="h-4 w-4" />
          </div>
          <p class="text-xl font-extrabold text-indigo-950 mt-1.5">
            S/ {{ Number(store.centralCashBalance || 0).toFixed(2) }}
          </p>
          <p class="text-[11px] text-indigo-600 mt-1 flex items-center gap-1">
            <ShieldCheck class="h-3.5 w-3.5" />
            Disponible para retiros
          </p>
        </div>

        <!-- Total Deposits -->
        <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-xs">
          <div class="flex items-center justify-between text-emerald-700">
            <span class="text-xs font-bold">Total Depósitos</span>
            <ArrowDownLeft class="h-4 w-4" />
          </div>
          <p class="text-xl font-extrabold text-emerald-950 mt-1.5">
            S/ {{ Number(store.stats?.total_deposits || 0).toFixed(2) }}
          </p>
          <p class="text-[11px] text-emerald-600 mt-1">Efectivo ingresado a bóveda</p>
        </div>

        <!-- Total Withdrawals -->
        <div class="rounded-2xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-4 shadow-xs">
          <div class="flex items-center justify-between text-rose-700">
            <span class="text-xs font-bold">Total Retiros</span>
            <ArrowUpRight class="h-4 w-4" />
          </div>
          <p class="text-xl font-extrabold text-rose-950 mt-1.5">
            S/ {{ Number(store.stats?.total_withdrawals || 0).toFixed(2) }}
          </p>
          <p class="text-[11px] text-rose-600 mt-1">Dispensado desde Caja General</p>
        </div>

        <!-- Total Commissions Earned -->
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-xs">
          <div class="flex items-center justify-between text-amber-700">
            <span class="text-xs font-bold">Comisiones Ganadas</span>
            <Coins class="h-4 w-4" />
          </div>
          <p class="text-xl font-extrabold text-amber-950 mt-1.5">
            S/ {{ Number(store.stats?.total_commissions || 0).toFixed(2) }}
          </p>
          <p class="text-[11px] text-amber-700 mt-1">Tarifa: S/ 1 por cada S/ 100</p>
        </div>

        <!-- Loyalty Points Distributed -->
        <div class="rounded-2xl border border-purple-200 bg-gradient-to-br from-purple-50 to-white p-4 shadow-xs">
          <div class="flex items-center justify-between text-purple-700">
            <span class="text-xs font-bold">Puntos Otorgados</span>
            <Award class="h-4 w-4" />
          </div>
          <p class="text-xl font-extrabold text-purple-950 mt-1.5">
            {{ Number(store.stats?.total_points_earned || 0).toLocaleString() }} pts
          </p>
          <p class="text-[11px] text-purple-600 mt-1">
            Cada S/ {{ store.settings?.points_ratio_soles || 10 }} = {{ store.settings?.points_ratio_points || 1 }} pto
          </p>
        </div>
      </div>

      <!-- Filters Strip -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
          <!-- Search -->
          <div>
            <label class="block font-semibold text-slate-600 mb-1">Buscar</label>
            <div class="relative">
              <input
                v-model="store.filters.search"
                type="text"
                placeholder="Código, cliente, documento..."
                class="w-full rounded-xl border border-slate-300 pl-8 pr-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
                @input="debounceFetchOperations"
              />
              <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-slate-400" />
            </div>
          </div>

          <!-- Type filter -->
          <div>
            <label class="block font-semibold text-slate-600 mb-1">Tipo de Operación</label>
            <select
              v-model="store.filters.operation_type"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
              @change="store.fetchOperations"
            >
              <option value="all">Todos los tipos</option>
              <option value="deposit">Solo Depósitos (Ingreso)</option>
              <option value="withdrawal">Solo Retiros (Egreso)</option>
            </select>
          </div>

          <!-- Start Date -->
          <div>
            <label class="block font-semibold text-slate-600 mb-1">Fecha Desde</label>
            <input
              v-model="store.filters.startDate"
              type="date"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
              @change="store.fetchOperations"
            />
          </div>

          <!-- End Date -->
          <div>
            <label class="block font-semibold text-slate-600 mb-1">Fecha Hasta</label>
            <input
              v-model="store.filters.endDate"
              type="date"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-indigo-500 focus:outline-none"
              @change="store.fetchOperations"
            />
          </div>
        </div>

        <div v-if="hasActiveFilters" class="flex justify-end pt-1">
          <button
            type="button"
            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition"
            @click="resetFilters"
          >
            Limpiar filtros
          </button>
        </div>
      </div>

      <!-- Operations Table Card -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-bold text-slate-900">Historial de Operaciones de Sucursal</h3>
          <span class="text-xs text-slate-500">{{ store.operations.length }} operaciones encontradas</span>
        </div>

        <div v-if="store.loading" class="py-16 text-center">
          <Loader2 class="h-8 w-8 animate-spin text-indigo-600 mx-auto" />
          <p class="text-xs text-slate-500 mt-2">Cargando operaciones...</p>
        </div>

        <div v-else-if="store.operations.length === 0" class="py-16 text-center">
          <ArrowLeftRight class="h-10 w-10 text-slate-300 mx-auto" />
          <p class="text-sm font-bold text-slate-700 mt-2">No se encontraron operaciones</p>
          <p class="text-xs text-slate-500 mt-1">Registra tu primer depósito o retiro haciendo clic en "Nueva Operación".</p>
          <button
            type="button"
            class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition"
            @click="openNewOperationModal"
          >
            <Plus class="h-4 w-4" />
            Registrar Operación
          </button>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Código / Fecha</th>
                <th class="py-3 px-4">Cliente</th>
                <th class="py-3 px-4 text-center">Tipo</th>
                <th class="py-3 px-4 text-right">Monto Base</th>
                <th class="py-3 px-4 text-right">Comisión (1/100)</th>
                <th class="py-3 px-4 text-right">Total Transacción</th>
                <th class="py-3 px-4 text-center">Puntos Ganados</th>
                <th class="py-3 px-4">Cajero</th>
                <th class="py-3 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="op in store.operations"
                :key="op.operation_id"
                class="hover:bg-slate-50/70 transition"
              >
                <!-- Code & Date -->
                <td class="py-3 px-4">
                  <span class="font-mono font-bold text-slate-900 block">{{ op.operation_code }}</span>
                  <span class="text-[11px] text-slate-400">{{ op.created_at }}</span>
                </td>

                <!-- Client -->
                <td class="py-3 px-4">
                  <p class="font-bold text-slate-900">{{ op.client_name }}</p>
                  <p class="text-[11px] text-slate-500">{{ op.document_type }}: {{ op.document_number }}</p>
                </td>

                <!-- Type -->
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                    :class="op.operation_type === 'deposit' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  >
                    <ArrowDownLeft v-if="op.operation_type === 'deposit'" class="h-3 w-3" />
                    <ArrowUpRight v-else class="h-3 w-3" />
                    {{ op.operation_type === 'deposit' ? 'Depósito' : 'Retiro' }}
                  </span>
                </td>

                <!-- Amount -->
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-800">
                  S/ {{ Number(op.amount).toFixed(2) }}
                </td>

                <!-- Commission -->
                <td class="py-3 px-4 text-right">
                  <span class="font-mono font-bold text-amber-700 block">
                    S/ {{ Number(op.commission_amount).toFixed(2) }}
                  </span>
                  <span
                    class="inline-block rounded px-1.5 py-0.2 text-[9px] font-semibold"
                    :class="op.commission_included ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'"
                  >
                    {{ op.commission_included ? 'Incluida' : 'Adicional' }}
                  </span>
                </td>

                <!-- Total -->
                <td class="py-3 px-4 text-right font-mono font-extrabold text-slate-900">
                  S/ {{ Number(op.total_customer_amount).toFixed(2) }}
                </td>

                <!-- Points -->
                <td class="py-3 px-4 text-center">
                  <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                    <Award class="h-3 w-3" />
                    +{{ op.points_earned }} pts
                  </span>
                </td>

                <!-- Cashier -->
                <td class="py-3 px-4 text-slate-600">
                  {{ op.cashier_name }}
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition shadow-xs"
                    title="Imprimir o Ver Ticket"
                    @click="viewTicket(op)"
                  >
                    <Printer class="h-3.5 w-3.5" />
                    Ticket
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 2: RANKING DE FIDELIZACIÓN (CLIENTES FRECUENTES)      -->
    <!-- ========================================================= -->
    <div v-else-if="activeTab === 'ranking'" class="space-y-6">
      <!-- Info Banner -->
      <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4 sm:p-5 flex items-start gap-3">
        <Award class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
        <div class="text-xs space-y-1">
          <h3 class="font-bold text-amber-900 text-sm">Ranking Histórico de Clientes Frecuentes</h3>
          <p class="text-amber-800 leading-relaxed">
            La posición de cada cliente en este ranking se determina estrictamente por los <strong>Puntos Acumulados de por Vida (Lifetime Points)</strong>.
            Cuando un cliente canjea premios por puntos, su saldo canjeable se reinicia o descuenta, pero su puntuación acumulada histórica y su posición en el ranking
            permanecen intactas en la base de datos.
          </p>
        </div>
      </div>

      <!-- Top 3 Podium Cards -->
      <div v-if="topThreeClients.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- 2nd Place (Silver) -->
        <div
          v-if="topThreeClients[1]"
          class="rounded-2xl border border-slate-300 bg-gradient-to-b from-slate-100 to-white p-5 shadow-xs flex flex-col justify-between order-2 md:order-1"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="rounded-full bg-slate-200 px-3 py-1 font-bold text-xs text-slate-700 flex items-center gap-1">
                🥈 Puesto #2
              </span>
              <span class="text-xs font-semibold text-slate-500">{{ topThreeClients[1].total_ops }} operaciones</span>
            </div>
            <h4 class="text-base font-bold text-slate-900 truncate">{{ topThreeClients[1].name }}</h4>
            <p class="text-xs text-slate-500">{{ topThreeClients[1].document_type }} {{ topThreeClients[1].document_number }}</p>
          </div>
          <div class="pt-4 border-t border-slate-200 mt-4 flex items-center justify-between">
            <div>
              <p class="text-[10px] text-slate-400 uppercase font-semibold">Puntos Históricos</p>
              <p class="text-lg font-extrabold text-slate-800">{{ topThreeClients[1].lifetime_points }} pts</p>
            </div>
            <button
              type="button"
              class="rounded-lg bg-slate-200/80 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-300 transition"
              @click="openHistoryModal(topThreeClients[1])"
            >
              Historial
            </button>
          </div>
        </div>

        <!-- 1st Place (Gold Champion) -->
        <div
          v-if="topThreeClients[0]"
          class="rounded-2xl border-2 border-amber-400 bg-gradient-to-b from-amber-50 to-white p-5 shadow-md flex flex-col justify-between order-1 md:order-2 ring-2 ring-amber-400/20"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="rounded-full bg-amber-400 text-amber-950 px-3.5 py-1 font-extrabold text-xs shadow-xs flex items-center gap-1">
                👑 Puesto #1 · Campeón
              </span>
              <span class="text-xs font-semibold text-amber-800">{{ topThreeClients[0].total_ops }} operaciones</span>
            </div>
            <h4 class="text-lg font-extrabold text-slate-900 truncate">{{ topThreeClients[0].name }}</h4>
            <p class="text-xs text-slate-600">{{ topThreeClients[0].document_type }} {{ topThreeClients[0].document_number }}</p>
          </div>
          <div class="pt-4 border-t border-amber-200 mt-4 flex items-center justify-between">
            <div>
              <p class="text-[10px] text-amber-700 uppercase font-bold">Puntos Históricos</p>
              <p class="text-2xl font-black text-amber-800">{{ topThreeClients[0].lifetime_points }} pts</p>
            </div>
            <button
              type="button"
              class="rounded-lg bg-amber-500 text-white px-3 py-1 text-xs font-bold hover:bg-amber-600 transition shadow-xs"
              @click="openHistoryModal(topThreeClients[0])"
            >
              Ver Historial
            </button>
          </div>
        </div>

        <!-- 3rd Place (Bronze) -->
        <div
          v-if="topThreeClients[2]"
          class="rounded-2xl border border-amber-700/30 bg-gradient-to-b from-amber-100/40 to-white p-5 shadow-xs flex flex-col justify-between order-3"
        >
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="rounded-full bg-amber-700/20 px-3 py-1 font-bold text-xs text-amber-900 flex items-center gap-1">
                🥉 Puesto #3
              </span>
              <span class="text-xs font-semibold text-amber-800">{{ topThreeClients[2].total_ops }} operaciones</span>
            </div>
            <h4 class="text-base font-bold text-slate-900 truncate">{{ topThreeClients[2].name }}</h4>
            <p class="text-xs text-slate-500">{{ topThreeClients[2].document_type }} {{ topThreeClients[2].document_number }}</p>
          </div>
          <div class="pt-4 border-t border-amber-200 mt-4 flex items-center justify-between">
            <div>
              <p class="text-[10px] text-amber-800 uppercase font-semibold">Puntos Históricos</p>
              <p class="text-lg font-extrabold text-amber-900">{{ topThreeClients[2].lifetime_points }} pts</p>
            </div>
            <button
              type="button"
              class="rounded-lg bg-amber-200/80 px-2.5 py-1 text-xs font-semibold text-amber-900 hover:bg-amber-300 transition"
              @click="openHistoryModal(topThreeClients[2])"
            >
              Historial
            </button>
          </div>
        </div>
      </div>

      <!-- Ranking Search & Table -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <h3 class="text-sm font-bold text-slate-900">Tabla de Clasificación General de Clientes</h3>
          <div class="relative w-full sm:w-72">
            <input
              v-model="rankingSearch"
              type="text"
              placeholder="Buscar cliente en el ranking..."
              class="w-full rounded-xl border border-slate-300 pl-8 pr-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
              @input="debounceFetchRanking"
            />
            <Search class="absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" />
          </div>
        </div>

        <div v-if="store.loading" class="py-16 text-center">
          <Loader2 class="h-8 w-8 animate-spin text-indigo-600 mx-auto" />
          <p class="text-xs text-slate-500 mt-2">Cargando clasificación de clientes...</p>
        </div>

        <div v-else-if="store.leaderboard.length === 0" class="py-16 text-center text-xs text-slate-400">
          No hay clientes registrados en el ranking de fidelización todavía.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
              <tr>
                <th class="py-3 px-4 text-center">Posición</th>
                <th class="py-3 px-4">Cliente</th>
                <th class="py-3 px-4 text-right">Puntos Históricos (Ranking)</th>
                <th class="py-3 px-4 text-right">Saldo Canjeable Actual</th>
                <th class="py-3 px-4 text-center">Operaciones</th>
                <th class="py-3 px-4 text-right">Volumen Operado</th>
                <th class="py-3 px-4">Última Operación</th>
                <th class="py-3 px-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="c in store.leaderboard"
                :key="c.client_id"
                class="hover:bg-slate-50/70 transition"
                :class="c.rank <= 3 ? 'bg-amber-50/20' : ''"
              >
                <!-- Rank -->
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-black"
                    :class="
                      c.rank === 1
                        ? 'bg-amber-400 text-amber-950 shadow-xs'
                        : c.rank === 2
                        ? 'bg-slate-300 text-slate-800'
                        : c.rank === 3
                        ? 'bg-amber-700/30 text-amber-900'
                        : 'bg-slate-100 text-slate-600'
                    "
                  >
                    {{ c.rank }}
                  </span>
                </td>

                <!-- Client Info -->
                <td class="py-3 px-4">
                  <p class="font-bold text-slate-900">{{ c.name }}</p>
                  <p class="text-[11px] text-slate-500">{{ c.document_type }}: {{ c.document_number }} {{ c.phone ? '· ' + c.phone : '' }}</p>
                </td>

                <!-- Lifetime Points (RANKING DEFINER) -->
                <td class="py-3 px-4 text-right font-mono font-black text-indigo-700 text-sm">
                  {{ c.lifetime_points }} pts
                </td>

                <!-- Current Spendable Points -->
                <td class="py-3 px-4 text-right font-mono font-bold text-amber-700">
                  {{ c.points_balance }} pts
                </td>

                <!-- Ops count -->
                <td class="py-3 px-4 text-center font-bold text-slate-700">
                  {{ c.total_ops }}
                </td>

                <!-- Volume -->
                <td class="py-3 px-4 text-right font-mono text-slate-600">
                  S/ {{ Number(c.total_volume || 0).toFixed(2) }}
                </td>

                <!-- Last Operation Date -->
                <td class="py-3 px-4 text-slate-500 text-[11px]">
                  {{ c.last_operation_at || 'Sin operaciones' }}
                </td>

                <!-- Action Button -->
                <td class="py-3 px-4 text-right">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-200 transition"
                    @click="openHistoryModal(c)"
                  >
                    <Clock class="h-3.5 w-3.5" />
                    Historial
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 3: CATÁLOGO DE PREMIOS Y RECOMPENSAS                   -->
    <!-- ========================================================= -->
    <div v-else-if="activeTab === 'rewards'" class="space-y-6">
      <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 sm:p-5 flex items-start gap-3">
        <Gift class="h-5 w-5 text-indigo-600 shrink-0 mt-0.5" />
        <div class="text-xs space-y-1">
          <h3 class="font-bold text-indigo-950 text-sm">Catálogo de Premios y Recompensas por Puntos</h3>
          <p class="text-indigo-800 leading-relaxed">
            Los clientes frecuentes pueden canjear sus puntos acumulados por cualquiera de los productos o paquetes disponibles a continuación.
            Cada premio cuenta con un valor nominal en Soles y su respectivo costo en puntos.
          </p>
        </div>
      </div>

      <!-- Rewards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div
          v-for="item in store.rewards"
          :key="item.reward_id"
          class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between transition hover:shadow-md hover:border-amber-300"
        >
          <div class="space-y-3">
            <!-- Image / Placeholder -->
            <div class="h-36 w-full rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden">
              <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="item.name"
                class="h-full w-full object-cover"
              />
              <Package v-else class="h-12 w-12 text-slate-300" />
            </div>

            <!-- Category and Stock -->
            <div class="flex items-center justify-between">
              <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 uppercase">
                {{ item.category || 'General' }}
              </span>
              <span
                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                :class="item.stock > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
              >
                Stock: {{ item.stock }}
              </span>
            </div>

            <h4 class="text-sm font-bold text-slate-900 line-clamp-1">{{ item.name }}</h4>
            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed">
              {{ item.description || 'Premio exclusivo para clientes frecuentes del sistema.' }}
            </p>
          </div>

          <div class="pt-4 border-t border-slate-100 mt-4 space-y-3">
            <div class="flex items-baseline justify-between text-xs">
              <div>
                <span class="text-[10px] text-slate-400 block font-semibold">Costo en Puntos</span>
                <span class="font-extrabold text-base text-amber-700 flex items-center gap-1">
                  <Award class="h-4 w-4" />
                  {{ item.points_cost }} pts
                </span>
              </div>
              <div class="text-right">
                <span class="text-[10px] text-slate-400 block font-semibold">Valor en Soles</span>
                <span class="font-bold text-sm text-slate-800">
                  S/ {{ Number(item.price_soles).toFixed(2) }}
                </span>
              </div>
            </div>

            <button
              type="button"
              :disabled="item.stock <= 0"
              class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl py-2 px-3 text-xs font-bold transition shadow-xs cursor-pointer"
              :class="
                item.stock > 0
                  ? 'bg-amber-600 text-white hover:bg-amber-700'
                  : 'bg-slate-200 text-slate-400 cursor-not-allowed'
              "
              @click="openRedeemModal(item)"
            >
              <Gift class="h-3.5 w-3.5" />
              {{ item.stock > 0 ? 'Canjear Premio' : 'Agotado' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Recent Redemptions Table -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden mt-6">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-sm font-bold text-slate-900">Historial de Canjes de Premios Recientes</h3>
          <span class="text-xs text-slate-500">{{ store.recentRedemptions.length }} canjes registrados</span>
        </div>

        <div v-if="store.recentRedemptions.length === 0" class="py-12 text-center text-xs text-slate-400">
          No hay canjes de premios registrados recientemente.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Fecha / Hora</th>
                <th class="py-3 px-4">Cliente</th>
                <th class="py-3 px-4">Premio Canjeado</th>
                <th class="py-3 px-4 text-right">Puntos Utilizados</th>
                <th class="py-3 px-4 text-right">Valor en Soles</th>
                <th class="py-3 px-4 text-center">Estado</th>
                <th class="py-3 px-4">Cajero</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="red in store.recentRedemptions" :key="red.redemption_id" class="hover:bg-slate-50/70 transition">
                <td class="py-3 px-4 text-slate-500">{{ red.redeemed_at }}</td>
                <td class="py-3 px-4">
                  <p class="font-bold text-slate-900">{{ red.client_name }}</p>
                  <p class="text-[11px] text-slate-500">{{ red.document_type }}: {{ red.document_number }}</p>
                </td>
                <td class="py-3 px-4 font-bold text-slate-900">{{ red.reward_name }}</td>
                <td class="py-3 px-4 text-right font-mono font-bold text-amber-700">-{{ red.points_spent }} pts</td>
                <td class="py-3 px-4 text-right font-mono text-slate-800">S/ {{ Number(red.price_soles_value).toFixed(2) }}</td>
                <td class="py-3 px-4 text-center">
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                    <Check class="h-3 w-3" />
                    Entregado
                  </span>
                </td>
                <td class="py-3 px-4 text-slate-600">{{ red.cashier_name }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <OperationModal
      v-model="showOperationModal"
      @created="handleOperationCreated"
    />

    <ReceiptTicketModal
      v-model="showReceiptModal"
      :receipt-data="selectedReceipt"
    />

    <RedeemRewardModal
      v-model="showRedeemModal"
      :reward="selectedReward"
      @redeemed="handleRewardRedeemed"
    />

    <PointsHistoryModal
      v-model="showHistoryModal"
      :client="selectedClientForHistory"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Swal from 'sweetalert2'
import {
  ArrowLeftRight,
  Plus,
  RefreshCw,
  Landmark,
  ShieldCheck,
  ArrowDownLeft,
  ArrowUpRight,
  Coins,
  Award,
  Gift,
  Search,
  Loader2,
  Printer,
  Clock,
  Package,
  Check,
  Store,
  AlertOctagon,
  Calculator,
} from '@lucide/vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useOperationsStore } from '@/stores/operations'
import { useClientsStore } from '@/stores/clients'
import { checkOpenCashSession, promptOpenCashRegister } from '@/utils/cashRegisterPrompt'

import OperationModal from '@/components/operations/OperationModal.vue'
import ReceiptTicketModal from '@/components/operations/ReceiptTicketModal.vue'
import RedeemRewardModal from '@/components/operations/RedeemRewardModal.vue'
import PointsHistoryModal from '@/components/operations/PointsHistoryModal.vue'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const store = useOperationsStore()
const clientsStore = useClientsStore()

// Cash Session State
const hasOpenCashSession = ref(true)
const checkingCashSession = ref(true)

async function verifyCashSession() {
  checkingCashSession.value = true
  try {
    const status = await checkOpenCashSession()
    hasOpenCashSession.value = status.isOpen
  } catch {
    hasOpenCashSession.value = false
  } finally {
    checkingCashSession.value = false
  }
}

const activeTab = ref('operations')
const rankingSearch = ref('')

// Modals visibility & data
const showOperationModal = ref(false)
const showReceiptModal = ref(false)
const selectedReceipt = ref(null)

const showRedeemModal = ref(false)
const selectedReward = ref(null)

const showHistoryModal = ref(false)
const selectedClientForHistory = ref(null)

let searchDebounceTimer = null
let rankingDebounceTimer = null

const hasActiveFilters = computed(() => {
  return (
    store.filters.startDate !== '' ||
    store.filters.endDate !== '' ||
    store.filters.operation_type !== 'all' ||
    store.filters.search !== ''
  )
})

const topThreeClients = computed(() => {
  return store.leaderboard.slice(0, 3)
})

function switchTab(tab) {
  activeTab.value = tab
  if (tab === 'operations') {
    store.fetchOperations()
  } else if (tab === 'ranking') {
    store.fetchLoyaltyRanking(null, rankingSearch.value)
  } else if (tab === 'rewards') {
    store.fetchRewards()
  }
}

function refreshActiveTab() {
  switchTab(activeTab.value)
}

function debounceFetchOperations() {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    store.fetchOperations()
  }, 350)
}

function debounceFetchRanking() {
  clearTimeout(rankingDebounceTimer)
  rankingDebounceTimer = setTimeout(() => {
    store.fetchLoyaltyRanking(null, rankingSearch.value)
  }, 350)
}

function resetFilters() {
  store.filters.startDate = ''
  store.filters.endDate = ''
  store.filters.operation_type = 'all'
  store.filters.search = ''
  store.fetchOperations()
}

async function openNewOperationModal() {
  const cashStatus = await checkOpenCashSession()
  hasOpenCashSession.value = cashStatus.isOpen

  if (!cashStatus.isOpen) {
    await promptOpenCashRegister(router, auth.activeBranch?.branch_name || cashStatus.branchName, 'depósitos y retiros')
    return
  }

  showOperationModal.value = true
}

async function handleOperationCreated(operationData) {
  // Trigger SweetAlert modal offering to print ticket
  const result = await Swal.fire({
    title: '¡Operación Registrada con Éxito!',
    html: `
      <div style="text-align: left; font-size: 13px; line-height: 1.6; padding: 4px 10px;">
        <p><strong>Código:</strong> <span style="font-family: monospace; font-weight: bold; color: #4338ca;">${operationData.operation_code}</span></p>
        <p><strong>Tipo:</strong> ${operationData.operation_type_label}</p>
        <p><strong>Cliente:</strong> ${operationData.client?.name}</p>
        <p><strong>Monto Base:</strong> S/ ${Number(operationData.amount).toFixed(2)}</p>
        <p><strong>Comisión (1/100):</strong> S/ ${Number(operationData.commission_amount).toFixed(2)}</p>
        <p><strong>Total Operación:</strong> S/ ${Number(operationData.total_customer_amount).toFixed(2)}</p>
        <p style="color: #b45309; font-weight: bold; margin-top: 6px;">
          ★ Puntos Ganados: +${operationData.points_earned} pts (Saldo total: ${operationData.client_new_points} pts)
        </p>
      </div>
    `,
    icon: 'success',
    showCancelButton: true,
    confirmButtonColor: '#4f46e5',
    cancelButtonColor: '#64748b',
    confirmButtonText: '🖨️ Imprimir Ticket',
    cancelButtonText: 'Cerrar',
    allowOutsideClick: false,
  })

  if (result.isConfirmed) {
    selectedReceipt.value = operationData
    showReceiptModal.value = true
  }
}

function viewTicket(operation) {
  selectedReceipt.value = {
    operation_id: operation.operation_id,
    operation_code: operation.operation_code,
    operation_type: operation.operation_type,
    operation_type_label: operation.operation_type === 'deposit' ? 'DEPÓSITO' : 'RETIRO',
    amount: operation.amount,
    commission_amount: operation.commission_amount,
    commission_included: operation.commission_included,
    net_amount: operation.net_amount,
    total_customer_amount: operation.total_customer_amount,
    points_earned: operation.points_earned,
    client_new_points: operation.client_current_points,
    client_lifetime_points: 0,
    created_at: operation.created_at,
    cashier: operation.cashier_name,
    client: {
      name: operation.client_name,
      document_type: operation.document_type,
      document_number: operation.document_number,
      phone: operation.client_phone,
    },
    branch: {
      branch_name: auth.activeBranch?.branch_name || 'Sucursal Principal',
      branch_code: auth.activeBranch?.branch_code || 'SUC-001',
      address: auth.activeBranch?.address || '',
      phone: auth.activeBranch?.phone || '',
    },
    company: {
      name: 'Stock Bartolo',
      tax_id: '',
      receipt_footer: 'Gracias por su preferencia.',
    },
    print_double_ticket: Boolean(store.settings?.print_double_ticket),
  }
  showReceiptModal.value = true
}

function openRedeemModal(reward) {
  selectedReward.value = reward
  showRedeemModal.value = true
}

function handleRewardRedeemed(res) {
  ui.success(res?.messages || '¡Premio canjeado exitosamente!')
  store.fetchRewards()
  store.fetchLoyaltyRanking()
}

function openHistoryModal(client) {
  selectedClientForHistory.value = client
  showHistoryModal.value = true
}

onMounted(() => {
  verifyCashSession()
  store.fetchOperations()
  clientsStore.fetchClients()
})
</script>

