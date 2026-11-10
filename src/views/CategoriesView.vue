<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
      <div class="relative flex-1 max-w-sm">
        <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model.trim="search"
          type="text"
          placeholder="Buscar categoría..."
          class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
        />
      </div>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 shadow-xs transition"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
        Agregar categoría
      </button>
    </div>

    <!-- Content Card -->
    <div class="rounded-2xl border border-slate-200 bg-white shadow-xs overflow-hidden">
      <div v-if="loading && items.length === 0" class="p-10 text-center text-sm text-slate-500">
        <Loader2 class="h-6 w-6 animate-spin mx-auto mb-2 text-indigo-600" />
        Cargando categorías...
      </div>

      <div v-else-if="filtered.length === 0" class="p-10 text-center text-sm text-slate-500">
        No se encontraron categorías.
      </div>

      <template v-else>
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase text-slate-500">
              <tr>
                <th class="px-5 py-3.5">Nombre</th>
                <th class="px-5 py-3.5">Estado</th>
                <th class="px-5 py-3.5 text-right">Opciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in filtered" :key="item.categories_id" class="hover:bg-slate-50/70 transition">
                <td class="px-5 py-3.5 font-semibold text-slate-900">{{ item.categories_name }}</td>
                <td class="px-5 py-3.5">
                  <StatusBadge
                    :variant="item.categories_active === 1 ? 'success' : 'danger'"
                    :label="item.categories_active === 1 ? 'Disponible' : 'No disponible'"
                  />
                </td>
                <td class="px-5 py-3.5 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 inline-flex items-center gap-1"
                      @click="openEdit(item)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                      Editar
                    </button>
                    <button
                      type="button"
                      class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 inline-flex items-center gap-1"
                      @click="askDelete(item)"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                      Eliminar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card List -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-for="item in filtered" :key="item.categories_id" class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <p class="font-semibold text-slate-900">{{ item.categories_name }}</p>
              <StatusBadge
                :variant="item.categories_active === 1 ? 'success' : 'danger'"
                :label="item.categories_active === 1 ? 'Disponible' : 'No disponible'"
              />
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 inline-flex items-center justify-center gap-1.5"
                @click="openEdit(item)"
              >
                <Pencil class="h-3.5 w-3.5" />
                Editar
              </button>
              <button
                type="button"
                class="flex-1 rounded-lg border border-rose-200 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 inline-flex items-center justify-center gap-1.5"
                @click="askDelete(item)"
              >
                <Trash2 class="h-3.5 w-3.5" />
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Create / Edit Modal -->
    <BaseModal
      :open="modalOpen"
      :title="editingId ? 'Editar categoría' : 'Agregar categoría'"
      @close="modalOpen = false"
    >
      <form id="categoryForm" class="space-y-4" @submit.prevent="submitForm">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Nombre de categoría *</label>
          <input
            v-model.trim="form.name"
            type="text"
            required
            placeholder="Ej. Camisas, Calzado, Electrónica..."
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1.5">Estado *</label>
          <select
            v-model.number="form.status"
            required
            class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
          >
            <option :value="1">Disponible</option>
            <option :value="2">No disponible</option>
          </select>
        </div>
      </form>
      <template #footer>
        <button
          type="button"
          class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
          @click="modalOpen = false"
        >
          Cerrar
        </button>
        <button
          type="submit"
          form="categoryForm"
          :disabled="saving"
          class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-60 inline-flex items-center gap-2"
        >
          <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
          Guardar cambios
        </button>
      </template>
    </BaseModal>

    <ConfirmModal
      :open="Boolean(deletingItem)"
      title="Eliminar categoría"
      :message="`¿Realmente deseas eliminar la categoría '${deletingItem?.categories_name || ''}'?`"
      :loading="deleting"
      @close="deletingItem = null"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { Search, Plus, Pencil, Trash2, Loader2 } from '@lucide/vue'
import { useCategoriesStore } from '@/stores/categories'
import { useUiStore } from '@/stores/ui'
import { extractErrorMessage } from '@/api/client'
import StatusBadge from '@/components/common/StatusBadge.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import ConfirmModal from '@/components/common/ConfirmModal.vue'

const store = useCategoriesStore()
const ui = useUiStore()
const { items, loading } = storeToRefs(store)

const search = ref('')
const modalOpen = ref(false)
const editingId = ref(null)
const saving = ref(false)
const deletingItem = ref(null)
const deleting = ref(false)

const form = reactive({
  name: '',
  status: 1,
})

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return items.value
  return items.value.filter((c) => c.categories_name.toLowerCase().includes(q))
})

function openCreate() {
  editingId.value = null
  form.name = ''
  form.status = 1
  modalOpen.value = true
}

function openEdit(item) {
  editingId.value = item.categories_id
  form.name = item.categories_name
  form.status = item.categories_active
  modalOpen.value = true
}

async function submitForm() {
  if (!form.name) {
    ui.warning('El nombre de la categoría es obligatorio.')
    return
  }
  saving.value = true
  try {
    if (editingId.value) {
      await store.updateCategory({
        editCategoriesId: editingId.value,
        editCategoriesName: form.name,
        editCategoriesStatus: form.status,
      })
      ui.success('Categoría actualizada exitosamente.')
    } else {
      await store.createCategory({
        categoriesName: form.name,
        categoriesStatus: form.status,
      })
      ui.success('Categoría creada exitosamente.')
    }
    modalOpen.value = false
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    saving.value = false
  }
}

function askDelete(item) {
  deletingItem.value = item
}

async function confirmDelete() {
  if (!deletingItem.value) return
  deleting.value = true
  try {
    await store.deleteCategory(deletingItem.value.categories_id)
    ui.success('Categoría eliminada exitosamente.')
    deletingItem.value = null
  } catch (err) {
    ui.error(extractErrorMessage(err))
  } finally {
    deleting.value = false
  }
}

onMounted(() => store.fetchCategories())
</script>
