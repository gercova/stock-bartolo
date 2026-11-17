<template>
  <div class="space-y-6">
    <!-- Top Header & Active Tenant Banner -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Configuración del Sistema</h2>
          <span
            v-if="auth.activeBranch"
            class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-600/20"
          >
            <Store class="h-3.5 w-3.5" />
            {{ auth.activeBranch.branch_code }} · {{ auth.activeBranch.branch_name }}
          </span>
        </div>
        <p class="text-sm text-slate-500">
          Administra la Caja General independiente de cada sucursal, la arquitectura multi-sucursal (multi-tenant) y las políticas operativas.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2.5 shrink-0">
        <button
          v-if="store.canSwitchBranches"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition"
          @click="openCreateBranch"
        >
          <Plus class="h-4 w-4" />
          Nueva Sucursal
        </button>
        <RouterLink
          to="/cash-register"
          class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800 shadow-xs transition"
        >
          <Calculator class="h-4 w-4" />
          Ir a Caja
        </RouterLink>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xs">
      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
        :class="
          activeTab === 'general-cash'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="setTab('general-cash')"
      >
        <Wallet class="h-4 w-4" />
        Caja General
      </button>

      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition cursor-pointer"
        :class="
          activeTab === 'bank-capital'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="setTab('bank-capital')"
      >
        <Landmark class="h-4 w-4" />
        Capital Bancario
        <span
          class="rounded-full px-2 py-0.5 text-[11px] font-bold"
          :class="activeTab === 'bank-capital' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
        >
          {{ store.bankAccounts.length }}
        </span>
      </button>

      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
        :class="
          activeTab === 'branches'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="setTab('branches')"
      >
        <Building2 class="h-4 w-4" />
        Sucursales
        <span
          class="rounded-full px-2 py-0.5 text-[11px] font-bold"
          :class="activeTab === 'branches' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'"
        >
          {{ store.branches.length }}
        </span>
      </button>

      <button
        type="button"
        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
        :class="
          activeTab === 'settings'
            ? 'bg-indigo-600 text-white shadow-xs'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
        "
        @click="setTab('settings')"
      >
        <SlidersHorizontal class="h-4 w-4" />
        Parámetros del Sistema
      </button>
    </div>

    <!-- ========================================================= -->
    <!-- TAB 1: CAJA GENERAL MULTI-SUCURSAL                        -->
    <!-- ========================================================= -->
    <section v-if="activeTab === 'general-cash'" class="space-y-5">
      <!-- Multi-Branch Independent Vaults Strip -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 class="text-sm font-bold text-slate-900">Bóvedas Independientes de Caja General por Sucursal</h3>
            <p class="text-xs text-slate-500">
              Cada sucursal mantiene su propio capital operativo aislado. Haz clic en una sucursal para gestionar su Caja General.
            </p>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 self-start rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
            :disabled="store.loadingGeneralCash"
            @click="refreshGeneralCash"
          >
            <RefreshCw class="h-3.5 w-3.5" :class="store.loadingGeneralCash ? 'animate-spin' : ''" />
            Actualizar
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div
            v-for="v in store.branchVaults"
            :key="v.branch_id"
            role="button"
            tabindex="0"
            class="rounded-xl border p-4 text-left transition cursor-pointer"
            :class="
              inspectedBranchId === v.branch_id
                ? 'border-indigo-500 bg-indigo-50/50 ring-2 ring-indigo-500/20'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60'
            "
            @click="selectVaultBranch(v.branch_id)"
            @keydown.enter.prevent="selectVaultBranch(v.branch_id)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-xs font-mono font-semibold text-indigo-700">{{ v.branch_code }}</p>
                <p class="text-sm font-bold text-slate-900 truncate">{{ v.branch_name }}</p>
              </div>
              <div class="flex flex-wrap justify-end gap-1 shrink-0">
                <StatusBadge v-if="v.is_active_tenant" variant="indigo" label="Sesión activa" />
                <StatusBadge v-if="v.status === 2" variant="danger" label="Bloqueada" />
                <StatusBadge v-else-if="v.is_below_min" variant="warning" label="Saldo bajo" />
              </div>
            </div>

            <div class="mt-3 flex items-end justify-between gap-2">
              <div>
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Saldo Disponible</p>
                <p class="text-xl font-extrabold text-slate-900">
                  {{ formatCurrency(v.current_balance, v.currency_code) }}
                </p>
              </div>
              <div class="text-right text-[11px] text-slate-500">
                <p>Cajas abiertas: <strong class="text-slate-700">{{ v.open_sessions_count }}</strong></p>
                <p>Mínimo: {{ formatCurrency(v.min_balance_alert, v.currency_code) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div
        v-if="store.loadingGeneralCash && !store.generalCashAccount"
        class="rounded-2xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-500"
      >
        <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
        Cargando Caja General de la sucursal...
      </div>

      <template v-else-if="store.generalCashAccount && store.generalCashBranch">
        <!-- Alerts -->
        <div
          v-if="store.generalCashAccount.status === 2"
          class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"
        >
          <AlertCircle class="h-5 w-5 shrink-0 text-rose-600 mt-0.5" />
          <div>
            <p class="font-bold">Caja General bloqueada (solo lectura)</p>
            <p class="text-xs mt-0.5">
              Esta bóveda está inactiva temporalmente. No podrá recibir depósitos, retiros ni fondear aperturas de caja hasta habilitarla desde "Configurar Bóveda".
            </p>
          </div>
        </div>

        <div
          v-else-if="store.generalCashSummary.isBelowMinAlert"
          class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
          <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 mt-0.5" />
          <div>
            <p class="font-bold">Alerta de liquidez operativa</p>
            <p class="text-xs mt-0.5">
              El saldo actual ({{ formatCurrency(store.generalCashSummary.currentBalance, vaultCurrency) }}) se encuentra igual o por debajo del mínimo configurado ({{ formatCurrency(store.generalCashAccount.min_balance_alert, vaultCurrency) }}). Considera registrar un aporte de capital.
            </p>
          </div>
        </div>

        <!-- KPIs for Selected Branch Vault -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-700 p-5 text-white shadow-sm">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Saldo Actual Caja General</span>
              <Wallet class="h-5 w-5 text-indigo-200" />
            </div>
            <p class="mt-2.5 text-3xl font-extrabold">
              {{ formatCurrency(store.generalCashSummary.currentBalance, vaultCurrency) }}
            </p>
            <p class="mt-1.5 text-xs text-indigo-100 truncate">
              {{ store.generalCashAccount.account_name }}
            </p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-500">Capital Inicial &amp; Aportes</span>
              <TrendingUp class="h-4 w-4 text-emerald-600" />
            </div>
            <p class="mt-2 text-2xl font-bold text-slate-900">
              {{ formatCurrency(store.generalCashSummary.initialCapital, vaultCurrency) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              Total ingresos: <span class="font-semibold text-emerald-700">{{ formatCurrency(store.generalCashSummary.totalDeposits, vaultCurrency) }}</span>
            </p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-500">Fondeado a Cajas</span>
              <ArrowUpRight class="h-4 w-4 text-amber-600" />
            </div>
            <p class="mt-2 text-2xl font-bold text-amber-700">
              {{ formatCurrency(store.generalCashSummary.totalAllocatedToTills, vaultCurrency) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              Retiros manuales: <span class="font-semibold text-rose-600">{{ formatCurrency(store.generalCashSummary.totalWithdrawals, vaultCurrency) }}</span>
            </p>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-500">Retorno de Cierres</span>
              <ArrowDownLeft class="h-4 w-4 text-sky-600" />
            </div>
            <p class="mt-2 text-2xl font-bold text-emerald-700">
              {{ formatCurrency(store.generalCashSummary.totalReturnedFromTills, vaultCurrency) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
              {{ store.generalCashSummary.movementsCount }} movimientos registrados
            </p>
          </div>
        </div>

        <!-- Toolbar & Quick Actions for Selected Vault -->
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
          <div class="flex flex-col sm:flex-row flex-wrap gap-3 flex-1">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
              <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model.trim="gcSearch"
                type="text"
                placeholder="Buscar concepto, referencia o usuario..."
                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              />
            </div>

            <select
              v-model="gcTypeFilter"
              class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
            >
              <option value="all">Todos los movimientos</option>
              <option value="initial_capital">Capital inicial</option>
              <option value="deposit">Ingresos / Aportes</option>
              <option value="withdrawal">Retiros</option>
              <option value="till_open_allocation">Fondeo a cajas</option>
              <option value="till_close_return">Retorno de cajas</option>
              <option value="adjustment_in">Ajustes (+)</option>
              <option value="adjustment_out">Ajustes (-)</option>
            </select>
          </div>

          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button
              v-if="store.canSwitchBranches && store.generalCashBranch.branch_id !== auth.activeBranch?.branch_id && store.generalCashBranch.active === 1"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
              @click="switchToBranch(store.generalCashBranch.branch_id)"
            >
              <ArrowLeftRight class="h-3.5 w-3.5" />
              Activar esta sucursal
            </button>

            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
              @click="cfgModalOpen = true"
            >
              <Pencil class="h-4 w-4 text-slate-500" />
              Configurar Bóveda
            </button>

            <button
              type="button"
              :disabled="store.generalCashAccount.status !== 1"
              class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-50 transition"
              @click="openNewGcMovement('withdrawal')"
            >
              <TrendingDown class="h-4 w-4" />
              Retiro
            </button>

            <button
              type="button"
              :disabled="store.generalCashAccount.status !== 1"
              class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50 shadow-xs transition"
              @click="openNewGcMovement('deposit')"
            >
              <Plus class="h-4 w-4" />
              Aporte / Capital
            </button>
          </div>
        </div>

        <!-- Ledger Table & Mobile Cards -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
          <div v-if="filteredGcMovements.length === 0" class="p-10 text-center text-sm text-slate-500">
            No se encontraron movimientos en Caja General para esta sucursal.
          </div>

          <template v-else>
            <!-- Desktop Table -->
            <div class="hidden lg:block overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
                  <tr>
                    <th class="px-5 py-3.5">Fecha</th>
                    <th class="px-5 py-3.5">Tipo</th>
                    <th class="px-5 py-3.5">Concepto y Referencia</th>
                    <th class="px-5 py-3.5">Usuario</th>
                    <th class="px-5 py-3.5 text-right">Saldo Ant.</th>
                    <th class="px-5 py-3.5 text-right">Monto</th>
                    <th class="px-5 py-3.5 text-right">Saldo Nuevo</th>
                    <th class="px-5 py-3.5 text-right">Acciones</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="m in filteredGcMovements" :key="m.movement_id" class="hover:bg-slate-50/70 transition">
                    <td class="px-5 py-3.5 text-xs text-slate-500 whitespace-nowrap">
                      {{ formatDateTime(m.created_at) }}
                    </td>
                    <td class="px-5 py-3.5">
                      <StatusBadge
                        :variant="generalCashMovementMeta(m.movement_type).variant"
                        :label="generalCashMovementMeta(m.movement_type).label"
                      />
                    </td>
                    <td class="px-5 py-3.5">
                      <p class="font-medium text-slate-900">{{ m.description }}</p>
                      <p v-if="m.reference || m.session_code" class="text-xs text-slate-500 mt-0.5">
                        <span v-if="m.reference">Ref: {{ m.reference }}</span>
                        <span v-if="m.reference && m.session_code"> · </span>
                        <span v-if="m.session_code" class="font-mono text-indigo-700">{{ m.session_code }}</span>
                      </p>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-600">{{ m.username }}</td>
                    <td class="px-5 py-3.5 text-right text-slate-500">
                      {{ formatCurrency(m.balance_before, vaultCurrency) }}
                    </td>
                    <td
                      class="px-5 py-3.5 text-right font-bold whitespace-nowrap"
                      :class="m.direction === 'in' ? 'text-emerald-700' : 'text-rose-600'"
                    >
                      {{ m.direction === 'in' ? '+' : '-' }}{{ formatCurrency(m.amount, vaultCurrency) }}
                    </td>
                    <td class="px-5 py-3.5 text-right font-semibold text-slate-900">
                      {{ formatCurrency(m.balance_after, vaultCurrency) }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                      <div v-if="m.can_modify" class="inline-flex items-center gap-1">
                        <button
                          type="button"
                          class="rounded-lg border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-100"
                          title="Editar referencia / concepto"
                          @click="openEditGcMovement(m)"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded-lg border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50"
                          title="Anular y recalcular saldo"
                          @click="voidTarget = m"
                        >
                          <Trash2 class="h-3.5 w-3.5" />
                        </button>
                      </div>
                      <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-500"
                        title="Movimiento auditado generado por sesión de caja"
                      >
                        <Lock class="h-3 w-3" />
                        Automático
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Mobile Cards -->
            <div class="lg:hidden divide-y divide-slate-100">
              <div v-for="m in filteredGcMovements" :key="m.movement_id" class="p-4 space-y-2.5">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <StatusBadge
                      :variant="generalCashMovementMeta(m.movement_type).variant"
                      :label="generalCashMovementMeta(m.movement_type).label"
                    />
                    <p class="text-sm font-semibold text-slate-900 mt-1.5">{{ m.description }}</p>
                    <p class="text-xs text-slate-500">
                      {{ formatDateTime(m.created_at) }} · {{ m.username }}
                      <span v-if="m.reference"> · Ref: {{ m.reference }}</span>
                    </p>
                  </div>
                  <p
                    class="text-base font-extrabold shrink-0"
                    :class="m.direction === 'in' ? 'text-emerald-700' : 'text-rose-600'"
                  >
                    {{ m.direction === 'in' ? '+' : '-' }}{{ formatCurrency(m.amount, vaultCurrency) }}
                  </p>
                </div>

                <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs">
                  <span class="text-slate-500">
                    Saldo: {{ formatCurrency(m.balance_before, vaultCurrency) }} →
                    <strong class="text-slate-900">{{ formatCurrency(m.balance_after, vaultCurrency) }}</strong>
                  </span>
                  <div v-if="m.can_modify" class="flex items-center gap-1.5">
                    <button
                      type="button"
                      class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700"
                      @click="openEditGcMovement(m)"
                    >
                      Editar
                    </button>
                    <button
                      type="button"
                      class="rounded-md border border-rose-200 bg-white px-2 py-1 text-xs font-medium text-rose-600"
                      @click="voidTarget = m"
                    >
                      Anular
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </section>

    <!-- ========================================================= -->
    <!-- TAB 2: CAPITAL BANCARIO & AUDITORÍA DE DEPÓSITOS           -->
    <!-- ========================================================= -->
    <section v-else-if="activeTab === 'bank-capital'" class="space-y-6">
      <!-- 1. Header Metrics Banner -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Capital Bancario -->
        <div class="rounded-2xl border border-indigo-100 bg-linear-to-br from-indigo-900 via-indigo-800 to-indigo-950 p-5 text-white shadow-sm flex flex-col justify-between relative overflow-hidden">
          <div class="absolute -right-4 -bottom-4 w-28 h-28 rounded-full bg-white/5 pointer-events-none"></div>
          <div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-bold uppercase tracking-wider text-indigo-200">Capital Total en Bancos</span>
              <div class="rounded-xl bg-white/10 p-2 text-indigo-200">
                <Landmark class="h-4 w-4" />
              </div>
            </div>
            <p class="mt-3 text-2xl lg:text-3xl font-extrabold font-mono tracking-tight">
              S/ {{ Number(store.bankSummary.total_capital || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </p>
          </div>
          <p class="mt-3 text-[11px] text-indigo-200">
            Fondos disponibles en {{ store.bankAccounts.filter(a => a.status === 1).length }} entidades activas
          </p>
        </div>

        <!-- Card 2: Total Depósitos Acumulados -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Capital Inyectado</span>
              <div class="rounded-xl bg-emerald-50 p-2 text-emerald-600">
                <TrendingUp class="h-4 w-4" />
              </div>
            </div>
            <p class="mt-3 text-2xl lg:text-3xl font-extrabold font-mono text-emerald-700 tracking-tight">
              S/ {{ Number(store.bankSummary.total_deposited || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </p>
          </div>
          <p class="mt-3 text-[11px] text-slate-500">
            En {{ store.bankSummary.total_deposits_count || 0 }} depósitos de auditoría registrados
          </p>
        </div>

        <!-- Card 3: Caja General de la Sucursal -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Caja General (Bóveda)</span>
              <div class="rounded-xl bg-amber-50 p-2 text-amber-600">
                <Wallet class="h-4 w-4" />
              </div>
            </div>
            <p class="mt-3 text-2xl lg:text-3xl font-extrabold font-mono text-slate-900 tracking-tight">
              S/ {{ Number(store.bankSummary.general_cash_balance || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
            </p>
          </div>
          <p class="mt-3 text-[11px] text-slate-500">
            Fondo físico disponible para traslados y fondeo
          </p>
        </div>

        <!-- Card 4: Quick Action -->
        <div class="rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/40 p-5 flex flex-col justify-between items-center text-center">
          <div class="space-y-1">
            <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900">Inyección de Fondos</h4>
            <p class="text-[11px] text-indigo-700">Registrar nuevo aporte o depósito bancario a una institución</p>
          </div>
          <button
            type="button"
            class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 active:scale-95 transition shadow-xs cursor-pointer"
            @click="openNewBankDeposit('BCP')"
          >
            <Plus class="h-4 w-4" />
            <span>Ingresar Capital Bancario</span>
          </button>
        </div>
      </div>

      <!-- 2. Banking Institutions Catalog Grid (Accounts Overview) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
              <CreditCard class="h-4 w-4 text-indigo-600" />
              <span>Instituciones Bancarias y Fondos Operativos</span>
            </h3>
            <p class="text-xs text-slate-500">
              Saldos asignados por banco para operaciones de depósitos y retiros en ventanilla y agentes
            </p>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer"
              :disabled="store.loadingBankCapital"
              @click="refreshBankCapital"
            >
              <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': store.loadingBankCapital }" />
              <span>Actualizar</span>
            </button>
          </div>
        </div>

        <!-- Bank Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5">
          <div
            v-for="acc in store.bankAccounts"
            :key="acc.account_id"
            class="rounded-xl border p-4 transition flex flex-col justify-between hover:shadow-xs"
            :class="acc.status === 1 ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50/70 opacity-60'"
          >
            <div class="space-y-2.5">
              <div class="flex items-center justify-between gap-2">
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold border"
                  :class="getBankEntity(acc.bank_name)?.badgeClass || 'bg-slate-100 text-slate-700'"
                >
                  {{ acc.bank_name }}
                </span>
                <span class="text-[10px] font-mono text-slate-400">Cod: {{ acc.bank_code }}</span>
              </div>

              <div>
                <p class="text-[10px] uppercase font-bold text-slate-400">Saldo Disponible</p>
                <p class="text-lg font-extrabold font-mono text-slate-900">
                  S/ {{ Number(acc.current_balance || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                </p>
              </div>

              <div class="text-[11px] text-slate-500 space-y-0.5 pt-1 border-t border-slate-100">
                <p v-if="acc.account_number" class="font-mono text-slate-700 truncate" :title="acc.account_number">
                  <span class="text-slate-400 font-sans">N°:</span> {{ acc.account_number }}
                </p>
                <p v-else class="text-slate-400 italic">Sin N° de cuenta registrado</p>
                <p class="text-[10px] text-slate-400">
                  Total depositado: S/ {{ Number(acc.total_deposited || 0).toFixed(2) }}
                </p>
              </div>
            </div>

            <div class="pt-3 mt-2 border-t border-slate-100 flex items-center justify-between gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-600 hover:text-indigo-600 transition cursor-pointer"
                @click="openEditBankAccount(acc)"
              >
                <Pencil class="h-3 w-3" />
                <span>Editar</span>
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700 hover:bg-indigo-100 transition cursor-pointer"
                @click="openNewBankDeposit(acc.bank_name)"
              >
                <Plus class="h-3 w-3" />
                <span>+ Fondeo</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. DETAILED TABLE TO LOG (AUDIT) EVERY CAPITAL DEPOSIT (CORE REQUIREMENT) -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-200 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Receipt class="h-4 w-4 text-indigo-600" />
                <span>Registro de Auditoría de Depósitos de Capital Bancario</span>
              </h3>
              <p class="text-xs text-slate-500">
                Historial inmutable y trazabilidad de ingresos de capital por institución y agencia bancaria para control de retiros y depósitos
              </p>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-xs cursor-pointer self-start sm:self-auto"
              @click="openNewBankDeposit()"
            >
              <Plus class="h-3.5 w-3.5" />
              <span>Nuevo Ingreso de Capital</span>
            </button>
          </div>

          <!-- Filters Toolbar -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2">
            <!-- Search Text -->
            <div class="relative lg:col-span-2">
              <Search class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
              <input
                v-model.trim="bankSearch"
                type="text"
                placeholder="Buscar por voucher, agencia bancaria, cuenta o código..."
                class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-hidden transition"
                @keydown.enter="applyBankFilters"
              />
            </div>

            <!-- Filter by Bank Entity -->
            <div>
              <select
                v-model="bankEntityFilter"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-hidden transition"
                @change="applyBankFilters"
              >
                <option value="all">Todas las Entidades Bancarias</option>
                <option v-for="b in bankList" :key="b.name" :value="b.name">
                  {{ b.name }}
                </option>
              </select>
            </div>

            <!-- Date Range: Start -->
            <div>
              <input
                v-model="bankStartDate"
                type="date"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-hidden transition"
                title="Fecha desde"
                @change="applyBankFilters"
              />
            </div>

            <!-- Date Range: End -->
            <div class="flex items-center gap-2">
              <input
                v-model="bankEndDate"
                type="date"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:outline-hidden transition"
                title="Fecha hasta"
                @change="applyBankFilters"
              />
              <button
                type="button"
                class="rounded-xl border border-slate-200 p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition cursor-pointer"
                title="Restablecer filtros"
                @click="resetBankFilters"
              >
                <RotateCcw class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>

        <!-- Audit Table -->
        <div class="overflow-x-auto">
          <div v-if="store.loadingBankCapital && store.bankDeposits.length === 0" class="py-12 text-center">
            <Loader2 class="h-8 w-8 text-indigo-600 animate-spin mx-auto" />
            <p class="text-xs text-slate-500 mt-2">Cargando registros de auditoría de capital bancario...</p>
          </div>

          <div v-else-if="store.bankDeposits.length === 0" class="py-12 text-center">
            <Landmark class="h-10 w-10 text-slate-300 mx-auto" />
            <p class="text-sm font-bold text-slate-700 mt-2">No se encontraron registros de capital</p>
            <p class="text-xs text-slate-400 mt-1">Registra tu primer ingreso de capital haciendo clic en "Nuevo Ingreso de Capital".</p>
          </div>

          <table v-else class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Código / Fecha</th>
                <th class="py-3 px-4">Entidad Bancaria</th>
                <th class="py-3 px-4">Sucursal / Agencia Bancaria (Depósito)</th>
                <th class="py-3 px-4">N° Operación / Voucher</th>
                <th class="py-3 px-4">Cuenta Destino</th>
                <th class="py-3 px-4 text-right">Monto Depositado</th>
                <th class="py-3 px-4 text-center">Trazabilidad Saldo</th>
                <th class="py-3 px-4">Origen Fondos</th>
                <th class="py-3 px-4">Sucursal / Auditor</th>
                <th class="py-3 px-4">Observaciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="d in store.bankDeposits"
                :key="d.deposit_id"
                class="hover:bg-slate-50/70 transition"
              >
                <!-- Code & Date -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <span class="font-mono font-bold text-slate-900 block">{{ d.deposit_code }}</span>
                  <span class="text-[11px] text-slate-400">{{ formatDateTime(d.deposit_date) }}</span>
                </td>

                <!-- Bank Entity Badge -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <span
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold border"
                    :class="getBankEntity(d.bank_name)?.badgeClass || 'bg-slate-100 text-slate-700'"
                  >
                    {{ d.bank_name }}
                  </span>
                </td>

                <!-- Specific Bank Branch / Agency where deposited (CORE REQUIREMENT) -->
                <td class="py-3 px-4">
                  <div class="flex items-start gap-1.5 max-w-xs">
                    <MapPin class="h-3.5 w-3.5 text-indigo-600 shrink-0 mt-0.5" />
                    <div>
                      <p class="font-bold text-slate-900 text-xs leading-snug">{{ d.bank_branch }}</p>
                      <span class="text-[10px] text-slate-400">Sede receptora de fondos</span>
                    </div>
                  </div>
                </td>

                <!-- Voucher / Operation Number -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <div v-if="d.voucher_number" class="inline-flex items-center gap-1 font-mono font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-md text-[11px]">
                    <Receipt class="h-3 w-3 text-slate-400" />
                    {{ d.voucher_number }}
                  </div>
                  <span v-else class="text-slate-400 italic">—</span>
                </td>

                <!-- Account Number -->
                <td class="py-3 px-4 whitespace-nowrap font-mono text-[11px] text-slate-600">
                  {{ d.account_number || '—' }}
                </td>

                <!-- Amount Deposited -->
                <td class="py-3 px-4 text-right whitespace-nowrap">
                  <span class="font-mono font-extrabold text-emerald-700 text-xs">
                    + S/ {{ Number(d.amount).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </span>
                </td>

                <!-- Balance Before -> After Trace -->
                <td class="py-3 px-4 text-center whitespace-nowrap font-mono text-[11px]">
                  <span class="text-slate-500">S/ {{ Number(d.balance_before).toFixed(2) }}</span>
                  <span class="text-slate-400 mx-1">→</span>
                  <strong class="text-indigo-900 font-bold">S/ {{ Number(d.balance_after).toFixed(2) }}</strong>
                </td>

                <!-- Funding Source -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <span
                    class="inline-block rounded-md px-2 py-0.5 text-[10px] font-semibold"
                    :class="d.funding_source === 'Transferencia desde Caja General' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'"
                  >
                    {{ d.funding_source }}
                  </span>
                </td>

                <!-- Business Branch & Username -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <p class="font-semibold text-slate-800">{{ d.branch_name }}</p>
                  <p class="text-[10px] text-slate-400">Por: {{ d.username }}</p>
                </td>

                <!-- Notes -->
                <td class="py-3 px-4 max-w-xs truncate text-[11px] text-slate-500" :title="d.notes">
                  {{ d.notes || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination & Chunk Loading Footer -->
        <DataTablePagination
          :pagination="store.bankDepositsPagination"
          :loading="store.loadingBankCapital"
          :loaded-count="store.bankDeposits.length"
          :chunk-mode="bankChunkMode"
          entity-name="depósitos de capital"
          @change-page="onBankPageChange"
          @change-per-page="onBankPerPageChange"
          @load-next-chunk="onBankLoadNextChunk"
          @update:chunk-mode="bankChunkMode = $event"
        />
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- TAB 3: SUCURSALES (MULTI-TENANT CRUD)                     -->
    <!-- ========================================================= -->
    <section v-else-if="activeTab === 'branches'" class="space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
        <div class="flex flex-col sm:flex-row gap-3 flex-1">
          <div class="relative flex-1 max-w-sm">
            <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              v-model.trim="branchSearch"
              type="text"
              placeholder="Buscar sucursal, código, responsable..."
              class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
          <select
            v-model="branchStatusFilter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          >
            <option value="all">Todas las sucursales</option>
            <option value="1">Activas</option>
            <option value="2">Inactivas</option>
          </select>
        </div>

        <button
          v-if="store.canSwitchBranches"
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition shrink-0"
          @click="openCreateBranch"
        >
          <Plus class="h-4 w-4" />
          Nueva sucursal
        </button>
      </div>

      <div v-if="store.loadingBranches && store.branches.length === 0" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
        <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
        Cargando sucursales...
      </div>

      <div v-else-if="filteredBranches.length === 0" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
        No se encontraron sucursales que coincidan con el filtro.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="b in filteredBranches"
          :key="b.branch_id"
          class="flex flex-col justify-between rounded-2xl border bg-white p-5 shadow-xs transition"
          :class="b.is_active_tenant ? 'border-indigo-500 ring-2 ring-indigo-500/15' : 'border-slate-200'"
        >
          <div class="space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-700">
                    {{ b.branch_code }}
                  </span>
                  <StatusBadge v-if="b.is_main" variant="indigo" label="Principal" />
                  <StatusBadge v-if="b.is_active_tenant" variant="success" label="En uso" />
                </div>
                <h3 class="mt-1.5 text-base font-bold text-slate-900 truncate">{{ b.branch_name }}</h3>
              </div>

              <StatusBadge
                :variant="b.active === 1 ? 'success' : 'neutral'"
                :label="b.active === 1 ? 'Activa' : 'Inactiva'"
              />
            </div>

            <div class="space-y-1 text-xs text-slate-600">
              <p v-if="b.manager_name">
                <span class="font-semibold text-slate-700">Responsable:</span> {{ b.manager_name }}
              </p>
              <p v-if="b.address">
                <span class="font-semibold text-slate-700">Dirección:</span> {{ b.address }}
              </p>
              <p v-if="b.phone || b.email">
                <span class="font-semibold text-slate-700">Contacto:</span>
                {{ [b.phone, b.email].filter(Boolean).join(' · ') }}
              </p>
              <p>
                <span class="font-semibold text-slate-700">Fiscal / Moneda:</span>
                IVA {{ Number(b.tax_rate).toFixed(2) }}% · {{ b.currency_code }} ({{ b.currency_symbol }})
              </p>
            </div>

            <!-- Independent Vault & Registers Box -->
            <div class="grid grid-cols-2 gap-2.5 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
              <div>
                <p class="text-slate-500">Caja General (Indep.)</p>
                <p class="text-sm font-extrabold text-indigo-700 mt-0.5">
                  {{ formatCurrency(b.general_cash?.current_balance || 0, b.currency_code) }}
                </p>
              </div>
              <div>
                <p class="text-slate-500">Cajas Registradoras</p>
                <p class="text-sm font-bold text-slate-900 mt-0.5">
                  {{ b.registers_count }} ({{ b.open_sessions_count }} abiertas)
                </p>
              </div>
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
              <span>{{ b.products_count }} productos</span>
              <span>{{ b.orders_count }} órdenes</span>
              <span>{{ b.loans_count }} préstamos</span>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
            <button
              v-if="store.canSwitchBranches && !b.is_active_tenant && b.active === 1"
              type="button"
              :disabled="auth.switchingBranch"
              class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 transition"
              @click="switchToBranch(b.branch_id)"
            >
              <ArrowLeftRight class="h-3.5 w-3.5" />
              Usar sucursal
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              @click="inspectBranchGeneralCash(b.branch_id)"
            >
              <Landmark class="h-3.5 w-3.5 text-indigo-600" />
              Caja General
            </button>
            <button
              v-if="store.canSwitchBranches"
              type="button"
              class="rounded-xl border border-slate-200 p-1.5 text-slate-600 hover:bg-slate-100"
              title="Editar sucursal"
              @click="openEditBranch(b)"
            >
              <Pencil class="h-3.5 w-3.5" />
            </button>
            <button
              v-if="store.canSwitchBranches"
              type="button"
              :disabled="Boolean(b.is_main) || b.branch_id === 1"
              class="rounded-xl border border-rose-200 p-1.5 text-rose-600 hover:bg-rose-50 disabled:opacity-35 disabled:cursor-not-allowed"
              :title="b.is_main ? 'La sucursal principal no puede eliminarse' : 'Eliminar sucursal'"
              @click="deletingBranch = b"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ========================================================= -->
    <!-- TAB 3: PARÁMETROS GLOBALES DEL SISTEMA                    -->
    <!-- ========================================================= -->
    <section v-else class="space-y-5">
      <form class="grid grid-cols-1 lg:grid-cols-2 gap-6" @submit.prevent="submitSystemSettings">
        <!-- Company & Fiscal Defaults -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
          <div class="flex items-center gap-3 pb-2 border-b border-slate-100">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
              <Store class="h-5 w-5" />
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Identidad Corporativa y Predeterminados</h3>
              <p class="text-xs text-slate-500">Información base para comprobantes y nuevas sucursales</p>
            </div>
          </div>

          <!-- Logo Corporativo -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Logo de la empresa
            </label>
            <div class="flex items-center gap-4 p-4 rounded-xl border border-slate-200 bg-slate-50">
              <div
                class="flex h-16 w-16 items-center justify-center rounded-xl border border-slate-300 bg-white shadow-xs overflow-hidden shrink-0"
              >
                <img
                  v-if="settingsForm.company_logo"
                  :src="resolveLogoUrl(settingsForm.company_logo)"
                  alt="Logo"
                  class="h-full w-full object-contain p-1"
                />
                <Store v-else class="h-8 w-8 text-slate-400" />
              </div>

              <div class="flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <input
                    ref="logoFileInput"
                    type="file"
                    accept="image/png, image/jpeg, image/jpg, image/webp, image/svg+xml, image/gif"
                    class="hidden"
                    @change="onLogoFileSelected"
                  />
                  <button
                    type="button"
                    :disabled="uploadingLogo || !store.canSwitchBranches"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white shadow-xs transition disabled:opacity-50 cursor-pointer"
                    @click="logoFileInput?.click()"
                  >
                    <Upload v-if="!uploadingLogo" class="h-3.5 w-3.5" />
                    <Loader2 v-else class="h-3.5 w-3.5 animate-spin" />
                    {{ uploadingLogo ? 'Subiendo...' : 'Subir nuevo logo' }}
                  </button>
                  <button
                    v-if="settingsForm.company_logo"
                    type="button"
                    :disabled="uploadingLogo || !store.canSwitchBranches"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white hover:bg-rose-50 hover:text-rose-700 px-3 py-1.5 text-xs font-semibold text-slate-700 transition cursor-pointer"
                    @click="removeLogo"
                  >
                    <Trash2 class="h-3.5 w-3.5 text-rose-600" />
                    Quitar logo
                  </button>
                </div>
                <p class="text-[11px] text-slate-500 leading-tight">
                  Formatos soportados: <strong>PNG, JPEG, JPG, WebP, SVG, GIF</strong> (máx. 5 MB). Se mostrará en el login y en todos los comprobantes.
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Nombre Comercial (Trade Name) *</label>
              <input
                v-model.trim="settingsForm.company_name"
                type="text"
                required
                maxlength="180"
                placeholder="Ej. Stock Bartolo"
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Razón Social (Legal Business Name)</label>
              <input
                v-model.trim="settingsForm.company_legal_name"
                type="text"
                maxlength="200"
                placeholder="Ej. Bartolo Inversiones & Servicios S.A.C."
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 disabled:bg-slate-100"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">RUC / Identificación Fiscal (Tax ID)</label>
              <input
                v-model.trim="settingsForm.company_tax_id"
                type="text"
                maxlength="60"
                placeholder="Ej. 20601234567"
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono corporativo</label>
              <input
                v-model.trim="settingsForm.company_phone"
                type="text"
                maxlength="60"
                placeholder="Ej. (01) 456-7890"
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Dirección principal</label>
            <input
              v-model.trim="settingsForm.company_address"
              type="text"
              maxlength="255"
              :disabled="!store.canSwitchBranches"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Pie de comprobante / Ticket</label>
            <input
              v-model.trim="settingsForm.receipt_footer"
              type="text"
              maxlength="255"
              :disabled="!store.canSwitchBranches"
              class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
            />
          </div>

          <div class="grid grid-cols-3 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">IVA defecto (%) *</label>
              <input
                v-model.number="settingsForm.default_tax_rate"
                type="number"
                step="0.01"
                min="0"
                max="100"
                required
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Moneda *</label>
              <input
                v-model.trim="settingsForm.default_currency_code"
                type="text"
                maxlength="10"
                required
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm uppercase focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">Símbolo *</label>
              <input
                v-model.trim="settingsForm.default_currency_symbol"
                type="text"
                maxlength="8"
                required
                :disabled="!store.canSwitchBranches"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-100"
              />
            </div>
          </div>
        </div>

        <!-- Operational Cash & Multi-Tenant Policies -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs flex flex-col justify-between space-y-5">
          <div class="space-y-4">
            <div class="flex items-center gap-3 pb-2 border-b border-slate-100">
              <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <ShieldCheck class="h-5 w-5" />
              </div>
              <div>
                <h3 class="text-base font-bold text-slate-900">Políticas de Caja y Multi-Sucursal</h3>
                <p class="text-xs text-slate-500">Reglas operativas que regulan la relación Caja General ↔ Caja</p>
              </div>
            </div>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50/70 cursor-pointer">
              <input
                v-model="settingsForm.enforce_open_till_for_cash"
                type="checkbox"
                :disabled="!store.canSwitchBranches"
                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <div>
                <p class="text-sm font-semibold text-slate-900">Exigir turno de caja abierto para operaciones en efectivo</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  Si se activa, el sistema bloqueará órdenes o préstamos con pago en efectivo cuando no exista una caja registradora abierta en la sucursal activa.
                </p>
              </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50/70 cursor-pointer">
              <input
                v-model="settingsForm.auto_fund_till_from_general_cash"
                type="checkbox"
                :disabled="!store.canSwitchBranches"
                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <div>
                <p class="text-sm font-semibold text-slate-900">Fondeo automático desde Caja General en apertura</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  Marca por defecto la deducción del fondo inicial desde la Caja General independiente de la sucursal al abrir turno.
                </p>
              </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50/70 cursor-pointer">
              <input
                v-model="settingsForm.auto_return_till_to_general_cash"
                type="checkbox"
                :disabled="!store.canSwitchBranches"
                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <div>
                <p class="text-sm font-semibold text-slate-900">Entrega automática a Caja General al cerrar caja</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  Sugiere depositar el efectivo contado al finalizar el Corte Z dentro de la Caja General de la sucursal.
                </p>
              </div>
            </label>

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50/70 cursor-pointer">
              <input
                v-model="settingsForm.allow_branch_switching"
                type="checkbox"
                :disabled="!store.canSwitchBranches"
                class="mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <div>
                <p class="text-sm font-semibold text-slate-900">Habilitar selector rápido de sucursal en barra superior</p>
                <p class="text-xs text-slate-500 mt-0.5">
                  Permite a administradores autorizados alternar entre tenants activos desde cualquier pantalla.
                </p>
              </div>
            </label>
          </div>

          <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
            <button
              v-if="store.canSwitchBranches"
              type="submit"
              :disabled="savingSettings"
              class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60 shadow-xs transition"
            >
              <Loader2 v-if="savingSettings" class="h-4 w-4 animate-spin" />
              <CheckCircle2 v-else class="h-4 w-4" />
              Guardar parámetros del sistema
            </button>
            <p v-else class="text-xs font-medium text-amber-700">
              Solo un administrador global puede modificar los parámetros generales.
            </p>
          </div>
        </div>
      </form>
    </section>

    <!-- Modals -->
    <BranchFormModal
      :open="branchModalOpen"
      :branch="editingBranch"
      :default-tax-rate="Number(store.config.default_tax_rate || 13)"
      :default-currency-code="store.config.default_currency_code || 'USD'"
      :default-currency-symbol="store.config.default_currency_symbol || '$'"
      @close="branchModalOpen = false"
      @saved="onBranchSaved"
    />

    <GeneralCashMovementModal
      :open="gcMovModalOpen"
      :branch="store.generalCashBranch"
      :current-balance="Number(store.generalCashSummary.currentBalance || 0)"
      :movement="editingGcMovement"
      :default-type="gcDefaultMovType"
      @close="gcMovModalOpen = false"
    />

    <GeneralCashConfigModal
      :open="cfgModalOpen"
      :branch="store.generalCashBranch"
      :account="store.generalCashAccount"
      @close="cfgModalOpen = false"
    />

    <ConfirmModal
      :open="Boolean(deletingBranch)"
      title="Eliminar sucursal"
      :message="`¿Realmente deseas eliminar la sucursal ${deletingBranch?.branch_name || ''} (${deletingBranch?.branch_code || ''})?`"
      :loading="deletingBranchLoading"
      @close="deletingBranch = null"
      @confirm="confirmDeleteBranch"
    />

    <ConfirmModal
      :open="Boolean(voidTarget)"
      title="Anular movimiento de Caja General"
      :message="`¿Deseas anular el movimiento '${voidTarget?.description || ''}' por ${formatCurrency(voidTarget?.amount || 0, vaultCurrency)}? El saldo de Caja General se recalculará automáticamente.`"
      confirm-label="Anular movimiento"
      :loading="voidLoading"
      @close="voidTarget = null"
      @confirm="confirmVoidMovement"
    />

    <BankCapitalDepositModal
      :open="bankDepositModalOpen"
      :initial-bank="bankInitialEntity"
      :branches="store.branches"
      :active-branch-id="inspectedBranchId"
      :can-switch-branches="store.canSwitchBranches"
      :accounts="store.bankAccounts"
      :general-cash-balance="Number(store.bankSummary.general_cash_balance || 0)"
      :submitting="store.submittingBankDeposit"
      @close="bankDepositModalOpen = false"
      @submit="handleSaveBankDeposit"
    />

    <BankCapitalAccountModal
      :open="bankAccountModalOpen"
      :account="editingBankAccount"
      :submitting="savingBankAccount"
      @close="bankAccountModalOpen = false"
      @submit="handleSaveBankAccount"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Building2,
  Landmark,
  SlidersHorizontal,
  Plus,
  Calculator,
  Store,
  RefreshCw,
  Loader2,
  AlertCircle,
  AlertTriangle,
  Wallet,
  TrendingUp,
  TrendingDown,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowLeftRight,
  Search,
  Pencil,
  Trash2,
  Lock,
  ShieldCheck,
  CheckCircle2,
  Upload,
  MapPin,
  Receipt,
  Calendar,
  Filter,
  RotateCcw,
  CreditCard,
  Download,
  Layers,
} from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useSystemConfigStore } from '@/stores/systemConfig'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import { formatCurrency, formatDateTime, generalCashMovementMeta } from '@/utils/formatters'
import { findUnsafeField, sanitizeObject } from '@/utils/security'
import { BANK_ENTITIES, getBankEntity, getBankBadgeClass } from '@/utils/bankEntities'
import StatusBadge from '@/components/common/StatusBadge.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'
import BranchFormModal from '@/components/system/BranchFormModal.vue'
import GeneralCashMovementModal from '@/components/system/GeneralCashMovementModal.vue'
import GeneralCashConfigModal from '@/components/system/GeneralCashConfigModal.vue'
import BankCapitalDepositModal from '@/components/system/BankCapitalDepositModal.vue'
import BankCapitalAccountModal from '@/components/system/BankCapitalAccountModal.vue'
import DataTablePagination from '@/components/common/DataTablePagination.vue'

const VALID_TABS = ['general-cash', 'bank-capital', 'branches', 'settings']

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useSystemConfigStore()
const ui = useUiStore()

const activeTab = ref(VALID_TABS.includes(route.query.tab) ? route.query.tab : 'general-cash')
const inspectedBranchId = ref(Number(auth.activeBranch?.branch_id || 1))

// General Cash filters & modals
const gcSearch = ref('')
const gcTypeFilter = ref('all')
const gcMovModalOpen = ref(false)
const gcDefaultMovType = ref('deposit')
const editingGcMovement = ref(null)
const cfgModalOpen = ref(false)
const voidTarget = ref(null)
const voidLoading = ref(false)

// Branch CRUD filters & modals
const branchSearch = ref('')
const branchStatusFilter = ref('all')
const branchModalOpen = ref(false)
const editingBranch = ref(null)
const deletingBranch = ref(null)
const deletingBranchLoading = ref(false)

// Global Settings form
const savingSettings = ref(false)
const logoFileInput = ref(null)
const uploadingLogo = ref(false)

// Bank Capital & Audit Deposits State
const bankList = BANK_ENTITIES
const bankSearch = ref('')
const bankEntityFilter = ref('all')
const bankStartDate = ref('')
const bankEndDate = ref('')
const bankChunkMode = ref(false)
const bankPage = ref(1)
const bankPerPage = ref(25)
const bankDepositModalOpen = ref(false)
const bankInitialEntity = ref('BCP')
const bankAccountModalOpen = ref(false)
const editingBankAccount = ref(null)
const savingBankAccount = ref(false)

function resolveLogoUrl(path) {
  if (!path) return ''
  if (path.startsWith('data:') || path.startsWith('http://') || path.startsWith('https://')) return path
  return path.startsWith('/') ? path : `/${path}`
}

async function onLogoFileSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingLogo.value = true
  try {
    const res = await store.uploadLogo(file)
    settingsForm.company_logo = res.logo_url
    ui.success('Logo de la empresa actualizado exitosamente.')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    uploadingLogo.value = false
    if (logoFileInput.value) logoFileInput.value.value = ''
  }
}

function removeLogo() {
  settingsForm.company_logo = ''
}

const settingsForm = reactive({
  company_name: 'Stock Bartolo',
  company_legal_name: '',
  company_logo: '',
  company_tax_id: '',
  company_phone: '',
  company_address: '',
  receipt_footer: 'Gracias por su preferencia.',
  default_tax_rate: 13.0,
  default_currency_code: 'USD',
  default_currency_symbol: '$',
  enforce_open_till_for_cash: false,
  auto_fund_till_from_general_cash: true,
  auto_return_till_to_general_cash: true,
  allow_branch_switching: true,
  points_ratio_soles: 10.0,
  points_ratio_points: 1,
  print_double_ticket: true,
  commission_rate_per_100: 1.0,
})

const vaultCurrency = computed(() => store.generalCashBranch?.currency_code || 'USD')

const filteredGcMovements = computed(() => {
  const q = gcSearch.value.toLowerCase()
  return (store.generalCashMovements || []).filter((m) => {
    if (gcTypeFilter.value !== 'all' && m.movement_type !== gcTypeFilter.value) {
      return false
    }
    if (!q) return true
    return (
      (m.description || '').toLowerCase().includes(q) ||
      (m.reference || '').toLowerCase().includes(q) ||
      (m.username || '').toLowerCase().includes(q) ||
      (m.session_code || '').toLowerCase().includes(q)
    )
  })
})

const filteredBranches = computed(() => {
  const q = branchSearch.value.toLowerCase()
  return (store.branches || []).filter((b) => {
    if (branchStatusFilter.value !== 'all' && String(b.active) !== branchStatusFilter.value) {
      return false
    }
    if (!q) return true
    return (
      (b.branch_code || '').toLowerCase().includes(q) ||
      (b.branch_name || '').toLowerCase().includes(q) ||
      (b.manager_name || '').toLowerCase().includes(q) ||
      (b.address || '').toLowerCase().includes(q) ||
      (b.phone || '').toLowerCase().includes(q) ||
      (b.email || '').toLowerCase().includes(q)
    )
  })
})

function syncSettingsForm(cfg) {
  if (!cfg) return
  settingsForm.company_name = cfg.company_name || 'Stock Bartolo'
  settingsForm.company_legal_name = cfg.company_legal_name || ''
  settingsForm.company_logo = cfg.company_logo || ''
  settingsForm.company_tax_id = cfg.company_tax_id || ''
  settingsForm.company_phone = cfg.company_phone || ''
  settingsForm.company_address = cfg.company_address || ''
  settingsForm.receipt_footer = cfg.receipt_footer || ''
  settingsForm.default_tax_rate = Number(cfg.default_tax_rate ?? 13.0)
  settingsForm.default_currency_code = cfg.default_currency_code || 'USD'
  settingsForm.default_currency_symbol = cfg.default_currency_symbol || '$'
  settingsForm.enforce_open_till_for_cash = Boolean(cfg.enforce_open_till_for_cash)
  settingsForm.auto_fund_till_from_general_cash = Boolean(cfg.auto_fund_till_from_general_cash)
  settingsForm.auto_return_till_to_general_cash = Boolean(cfg.auto_return_till_to_general_cash)
  settingsForm.allow_branch_switching = Boolean(cfg.allow_branch_switching)
  settingsForm.points_ratio_soles = Number(cfg.points_ratio_soles ?? 10.0)
  settingsForm.points_ratio_points = Number(cfg.points_ratio_points ?? 1)
  settingsForm.print_double_ticket = Boolean(cfg.print_double_ticket ?? true)
  settingsForm.commission_rate_per_100 = Number(cfg.commission_rate_per_100 ?? 1.0)
}

function setTab(tab) {
  activeTab.value = VALID_TABS.includes(tab) ? tab : 'general-cash'
  if (route.query.tab !== activeTab.value) {
    router.replace({ query: { ...route.query, tab: activeTab.value } })
  }
}

watch(
  () => route.query.tab,
  (tab) => {
    if (tab && VALID_TABS.includes(tab) && tab !== activeTab.value) {
      activeTab.value = tab
    }
  }
)

watch(
  () => store.config,
  (cfg) => syncSettingsForm(cfg),
  { immediate: true, deep: true }
)

async function selectVaultBranch(branchId) {
  inspectedBranchId.value = branchId
  try {
    await store.fetchGeneralCash(branchId)
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

async function refreshGeneralCash() {
  try {
    await store.fetchGeneralCash(inspectedBranchId.value)
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

function inspectBranchGeneralCash(branchId) {
  selectVaultBranch(branchId)
  setTab('general-cash')
}

async function switchToBranch(branchId) {
  try {
    const res = await auth.switchBranch(branchId)
    inspectedBranchId.value = branchId
    ui.success(res?.messages || 'Sucursal cambiada correctamente.')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

function openNewGcMovement(defaultType = 'deposit') {
  editingGcMovement.value = null
  gcDefaultMovType.value = defaultType
  gcMovModalOpen.value = true
}

function openEditGcMovement(mov) {
  editingGcMovement.value = mov
  gcMovModalOpen.value = true
}

async function confirmVoidMovement() {
  if (!voidTarget.value) return
  voidLoading.value = true
  try {
    const res = await store.voidGeneralCashMovement(
      voidTarget.value.movement_id,
      store.generalCashBranch?.branch_id || inspectedBranchId.value
    )
    ui.success(res?.messages || 'Movimiento anulado.')
    voidTarget.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    voidLoading.value = false
  }
}

function openCreateBranch() {
  editingBranch.value = null
  branchModalOpen.value = true
}

function openEditBranch(branch) {
  editingBranch.value = branch
  branchModalOpen.value = true
}

async function onBranchSaved() {
  await store.fetchGeneralCash(inspectedBranchId.value)
}

async function confirmDeleteBranch() {
  if (!deletingBranch.value) return
  deletingBranchLoading.value = true
  try {
    const res = await store.deleteBranch(deletingBranch.value.branch_id)
    if (inspectedBranchId.value === deletingBranch.value.branch_id) {
      inspectedBranchId.value = auth.activeBranch?.branch_id || 1
      await store.fetchGeneralCash(inspectedBranchId.value)
    }
    ui.success(res?.messages || 'Sucursal eliminada.')
    deletingBranch.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    deletingBranchLoading.value = false
  }
}

async function submitSystemSettings() {
  const unsafe = findUnsafeField({
    Empresa: settingsForm.company_name,
    'Razón Social': settingsForm.company_legal_name,
    NIT: settingsForm.company_tax_id,
    Teléfono: settingsForm.company_phone,
    Dirección: settingsForm.company_address,
    Comprobante: settingsForm.receipt_footer,
    Moneda: settingsForm.default_currency_code,
    Símbolo: settingsForm.default_currency_symbol,
  })
  if (unsafe) {
    ui.error(`El campo "${unsafe}" contiene caracteres o código no permitidos.`)
    return
  }

  savingSettings.value = true
  try {
    const cleanSettings = sanitizeObject({ ...settingsForm })
    const res = await store.saveSystemConfig(cleanSettings)
    ui.success(res?.messages || 'Parámetros guardados exitosamente.')
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingSettings.value = false
  }
}

async function loadBankCapital(page = 1, appendChunk = false) {
  try {
    await store.fetchBankCapital({
      branchId: inspectedBranchId.value,
      bankName: bankEntityFilter.value,
      search: bankSearch.value,
      startDate: bankStartDate.value,
      endDate: bankEndDate.value,
      page,
      limit: bankPerPage.value,
    }, appendChunk)
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

async function refreshBankCapital() {
  bankPage.value = 1
  await loadBankCapital(1, false)
}

function applyBankFilters() {
  bankPage.value = 1
  loadBankCapital(1, false)
}

function resetBankFilters() {
  bankSearch.value = ''
  bankEntityFilter.value = 'all'
  bankStartDate.value = ''
  bankEndDate.value = ''
  bankPage.value = 1
  loadBankCapital(1, false)
}

function onBankPageChange(p) {
  bankPage.value = p
  loadBankCapital(p, false)
}

function onBankPerPageChange(size) {
  bankPerPage.value = size
  bankPage.value = 1
  loadBankCapital(1, false)
}

function onBankLoadNextChunk() {
  const current = store.bankDepositsPagination.current_chunk || bankPage.value
  const next = current + 1
  bankPage.value = next
  loadBankCapital(next, true)
}

function openNewBankDeposit(bankName = 'BCP') {
  bankInitialEntity.value = bankName
  bankDepositModalOpen.value = true
}

function openEditBankAccount(acc) {
  editingBankAccount.value = acc
  bankAccountModalOpen.value = true
}

async function handleSaveBankDeposit(payload) {
  try {
    const res = await store.createBankCapitalDeposit(payload)
    ui.success(res?.messages || 'Depósito de capital registrado exitosamente.')
    bankDepositModalOpen.value = false
    await loadBankCapital(1, false)
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
}

async function handleSaveBankAccount(payload) {
  savingBankAccount.value = true
  try {
    const res = await store.updateBankCapitalAccount(payload)
    ui.success(res?.messages || 'Cuenta bancaria actualizada correctamente.')
    bankAccountModalOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    savingBankAccount.value = false
  }
}

watch(
  () => activeTab.value,
  async (tab) => {
    if (tab === 'bank-capital') {
      await loadBankCapital(1, false)
    }
  }
)

onMounted(async () => {
  inspectedBranchId.value = Number(auth.activeBranch?.branch_id || 1)
  try {
    await Promise.all([
      store.fetchSystemConfig(),
      store.fetchBranches(),
      store.fetchGeneralCash(inspectedBranchId.value),
      store.fetchBankCapital({ branchId: inspectedBranchId.value }),
    ])
  } catch (err) {
    ui.error(extractErrorMessage(err))
  }
})
</script>

