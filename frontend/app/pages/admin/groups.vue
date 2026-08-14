<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Group Karyawan</h1>
        <p class="text-sm text-gray-500">Kelompok karyawan — nanti dipakai supervisor buat atur jadwal</p>
      </div>
      <button class="btn-primary" @click="openCreate">+ Tambah Group</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <EmptyState v-else-if="groups.length === 0" icon="🗂️" title="Belum ada group" description="Buat group pertama untuk mengelompokkan karyawan dan atur jadwalnya." />

    <div v-else class="grid gap-3 md:grid-cols-2">
      <div v-for="group in groups" :key="group.id" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="font-medium text-gray-900">{{ group.name }}</p>
            <p class="text-xs text-gray-400">
              {{ group.members_count }} anggota
              <span v-if="group.supervisor"> · Kepala: {{ group.supervisor.name }}</span>
            </p>
          </div>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="group.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
          >
            {{ group.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </div>
        <p v-if="group.description" class="mt-2 text-sm text-gray-500">{{ group.description }}</p>
        <div class="mt-3 flex justify-end gap-1">
          <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(group)">Edit</button>
          <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(group)">Hapus</button>
        </div>
      </div>
    </div>

    <!-- Modal form -->
    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Group' : 'Edit Group'" @close="modal.open = false">
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label class="label">Nama Group <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" class="input" placeholder="mis. Group Toko A" required />
        </div>

        <div class="mb-4">
          <label class="label">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="input" placeholder="Opsional"></textarea>
        </div>

        <div class="mb-4">
          <label class="label">Kepala Group (Supervisor)</label>
          <select v-model="form.supervisor_id" class="input">
            <option :value="null">— Tanpa kepala —</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.position || '—' }})</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="label">Status</label>
          <select v-model="form.is_active" class="input">
            <option :value="true">Aktif</option>
            <option :value="false">Nonaktif</option>
          </select>
        </div>

        <!-- Pilihan anggota -->
        <div class="mb-4">
          <label class="label">Anggota ({{ selectedMembers.length }} dipilih)</label>
          <div v-if="employees.length === 0" class="rounded-lg bg-gray-50 p-3 text-center text-xs text-gray-400">
            Belum ada karyawan aktif.
          </div>
          <div v-else class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
            <label
              v-for="emp in employees"
              :key="emp.id"
              class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50"
            >
              <input v-model="selectedMembers" type="checkbox" :value="emp.id" class="rounded border-gray-300" />
              <span>{{ emp.name }}</span>
              <span v-if="emp.position" class="text-xs text-gray-400">· {{ emp.position }}</span>
            </label>
          </div>
        </div>

        <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="modal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            <span v-if="saving" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            {{ saving ? 'Menyimpan…' : 'Simpan' }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface EmployeeOption {
  id: number
  name: string
  position?: string | null
}

interface GroupItem {
  id: number
  name: string
  description?: string | null
  supervisor_id?: number | null
  supervisor?: { id: number; name: string } | null
  members_count: number
  is_active: boolean
}

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const groups = ref<GroupItem[]>([])
const employees = ref<EmployeeOption[]>([])
const selectedMembers = ref<number[]>([])

const modal = reactive({ open: false, mode: 'create' as 'create' | 'edit', id: null as number | null })
const form = reactive({ name: '', description: '', supervisor_id: null as number | null, is_active: true })

async function loadGroups() {
  loading.value = true
  try {
    const data = await api<{ data: GroupItem[] }>('GET', '/groups')
    groups.value = data.data
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal memuat group.')
  } finally {
    loading.value = false
  }
}

async function loadEmployees() {
  try {
    const data = await api<{ data: EmployeeOption[] }>('GET', '/groups/available-employees')
    employees.value = data.data
  } catch {
    employees.value = []
  }
}

function resetForm() {
  form.name = ''
  form.description = ''
  form.supervisor_id = null
  form.is_active = true
  selectedMembers.value = []
  error.value = ''
}

async function openCreate() {
  resetForm()
  modal.mode = 'create'
  modal.id = null
  modal.open = true
}

async function openEdit(group: GroupItem) {
  resetForm()
  modal.mode = 'edit'
  modal.id = group.id
  form.name = group.name
  form.description = group.description || ''
  form.supervisor_id = group.supervisor_id ?? null
  form.is_active = group.is_active
  modal.open = true

  try {
    const data = await api<{ data: GroupItem & { members: EmployeeOption[] } }>('GET', `/groups/${group.id}`)
    selectedMembers.value = (data.data.members || []).map((m) => m.id)
  } catch {
    // anggota tetap kosong
  }
}

async function submitForm() {
  saving.value = true
  error.value = ''
  try {
    if (modal.mode === 'create') {
      await api('POST', '/groups', {
        name: form.name,
        description: form.description || null,
        supervisor_id: form.supervisor_id,
        is_active: form.is_active,
        member_ids: selectedMembers.value,
      })
    } else {
      await api('PUT', `/groups/${modal.id}`, {
        name: form.name,
        description: form.description || null,
        supervisor_id: form.supervisor_id,
        is_active: form.is_active,
        member_ids: selectedMembers.value,
      })
    }
    modal.open = false
    await loadGroups()
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal menyimpan group.')
  } finally {
    saving.value = false
  }
}

async function remove(group: GroupItem) {
  if (!confirm(`Hapus group "${group.name}"?`)) return
  try {
    await api('DELETE', `/groups/${group.id}`)
    await loadGroups()
  } catch (e: any) {
    alert(errorMessage(e, 'Gagal menghapus group.'))
  }
}

onMounted(() => {
  loadGroups()
  loadEmployees()
})
</script>
