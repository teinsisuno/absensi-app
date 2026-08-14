<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Tugas Luar</h1>
        <p class="text-sm text-gray-500">Berikan tugas luar ke karyawan dan pantau statusnya</p>
      </div>
    </div>

    <!-- Tab -->
    <div class="mb-4 flex rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
      <button
        type="button"
        class="flex-1 rounded-lg py-2 text-sm font-medium transition"
        :class="tab === 'list' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
        @click="tab = 'list'"
      >
        Daftar Tugas Luar
      </button>
      <button
        type="button"
        class="flex-1 rounded-lg py-2 text-sm font-medium transition"
        :class="tab === 'create' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
        @click="tab = 'create'"
      >
        Buat Tugas Luar
      </button>
    </div>

    <!-- Tab list -->
    <template v-if="tab === 'list'">
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <select v-model="listFilter.assignee_id" class="input !w-auto" @change="load">
          <option value="">Semua Karyawan</option>
          <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
        </select>
        <select v-model="listFilter.status" class="input !w-auto" @change="load">
          <option value="">Semua Status</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="done">Done</option>
        </select>
        <button
          v-if="listFilter.assignee_id || listFilter.status"
          type="button"
          class="rounded-lg px-3 py-2 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700"
          @click="resetFilter"
        >
          ✕ Reset filter
        </button>
        <span class="ml-auto text-xs text-gray-400">{{ tasks.length }} tugas luar</span>
      </div>

      <SkeletonLoader v-if="loading" />

      <EmptyState v-else-if="tasks.length === 0" icon="✅" title="Belum ada tugas luar" description="Buat tugas luar pertama lewat tab Buat Tugas Luar." />

      <div v-else class="card overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
          <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">Karyawan</th>
              <th class="px-4 py-3 font-medium">Judul</th>
              <th class="px-4 py-3 font-medium">Deadline</th>
              <th class="px-4 py-3 font-medium">Dibuat oleh</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 text-right font-medium">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="t in tasks" :key="t.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ t.assignee?.name || '—' }}</td>
              <td class="max-w-[260px] px-4 py-3">
                <p class="truncate font-medium text-gray-800">{{ t.title }}</p>
                <p v-if="t.description" class="line-clamp-1 text-xs text-gray-400">{{ t.description }}</p>
              </td>
              <td class="px-4 py-3">
                <span v-if="isOverdue(t)" class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600">
                  ⏰ {{ formatDate(t.due_date) }}
                </span>
                <span v-else class="text-gray-600">{{ formatDate(t.due_date) }}</span>
              </td>
              <td class="px-4 py-3 text-gray-600">{{ t.creator?.name || '—' }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(t.status)">{{ statusLabel(t.status) }}</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-1">
                  <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(t)">Edit</button>
                  <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(t)">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Tab create -->
    <div v-else class="card max-w-2xl overflow-hidden">
      <!-- Header form -->
      <div class="border-b border-primary-800 bg-gradient-to-r from-primary-700 to-primary-600 px-6 py-5">
        <div class="flex items-center gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 text-2xl">📋</div>
          <div>
            <h2 class="font-bold text-white">Buat Tugas Luar</h2>
            <p class="text-xs text-primary-100">Berikan tugas luar ke karyawan, lengkap dengan deadline</p>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="p-6">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <div>
            <label class="label">Karyawan <span class="text-red-500">*</span></label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400">👤</span>
              <select v-model="form.assignee_id" class="input !pl-10" required>
                <option :value="null" disabled>— Pilih karyawan —</option>
                <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
              </select>
            </div>
            <p class="mt-1 text-xs text-gray-400">Karyawan yang menerima tugas luar.</p>
          </div>
          <div>
            <label class="label">Deadline</label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400">📅</span>
              <input v-model="form.due_date" type="date" class="input !pl-10" />
            </div>
            <p class="mt-1 text-xs text-gray-400">Tanggal batas penyelesaian.</p>
          </div>
          <div class="sm:col-span-2">
            <label class="label">Judul <span class="text-red-500">*</span></label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-gray-400">📝</span>
              <input v-model="form.title" type="text" class="input !pl-10" placeholder="mis. Ambil dokumen ke kantor pajak" required />
            </div>
          </div>
          <div class="sm:col-span-2">
            <label class="label">Deskripsi</label>
            <textarea v-model="form.description" rows="4" class="input" placeholder="Detail pekerjaan, alamat tujuan, instruksi tambahan…"></textarea>
          </div>
        </div>

        <p v-if="formError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>

        <div class="mt-6 flex items-center justify-between gap-2 border-t border-gray-100 pt-4">
          <p class="text-xs text-gray-400"><span class="text-red-500">*</span> Wajib diisi</p>
          <div class="flex gap-2">
            <button type="button" class="btn-secondary" @click="resetForm">Reset</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan Tugas Luar' }}</button>
          </div>
        </div>
      </form>
    </div>

    <!-- Modal edit -->
    <AppModal v-if="editModal.open" title="Edit Tugas Luar" @close="editModal.open = false">
      <form @submit.prevent="submitEdit">
        <div class="mb-4">
          <label class="label">Karyawan</label>
          <select v-model="editModal.form.assignee_id" class="input">
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="label">Judul</label>
          <input v-model="editModal.form.title" type="text" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Deskripsi</label>
          <textarea v-model="editModal.form.description" rows="3" class="input"></textarea>
        </div>
        <div class="mb-4">
          <label class="label">Deadline</label>
          <input v-model="editModal.form.due_date" type="date" class="input" />
        </div>
        <div class="mb-4">
          <label class="label">Status</label>
          <select v-model="editModal.form.status" class="input">
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="done">Done</option>
          </select>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="editModal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="editModal.saving">{{ editModal.saving ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface Task {
  id: number
  assignee_id: number
  title: string
  description?: string | null
  due_date?: string | null
  status: string
  assignee?: { name?: string } | null
  creator?: { name?: string } | null
}

const toast = useToast()
const confirmDialog = useConfirm()

const tab = ref<'list' | 'create'>('list')
const loading = ref(true)
const tasks = ref<Task[]>([])
const employees = ref<{ id: number; name: string }[]>([])
const listFilter = reactive({ assignee_id: '' as string | number, status: '' })

const form = reactive({ assignee_id: null as number | null, title: '', description: '', due_date: '' })
const formError = ref('')
const saving = ref(false)

const editModal = reactive({
  open: false,
  id: null as number | null,
  saving: false,
  form: { assignee_id: null as number | null, title: '', description: '', due_date: '', status: 'pending' },
})

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (listFilter.assignee_id) params.set('assignee_id', String(listFilter.assignee_id))
    if (listFilter.status) params.set('status', listFilter.status)
    const qs = params.toString() ? `?${params}` : ''
    const data = await api<{ data: Task[] }>('GET', `/tasks${qs}`)
    tasks.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat tugas.'))
  } finally {
    loading.value = false
  }
}

async function loadEmployees() {
  try {
    const data = await api<{ data: { id: number; name: string }[] }>('GET', '/employees?status=active')
    employees.value = data.data
  } catch {
    employees.value = []
  }
}

function resetForm() {
  form.assignee_id = null
  form.title = ''
  form.description = ''
  form.due_date = ''
  formError.value = ''
}

async function submitForm() {
  formError.value = ''
  if (!form.title.trim()) {
    formError.value = 'Judul wajib diisi.'
    return
  }
  saving.value = true
  try {
    await api('POST', '/tasks', {
      assignee_id: form.assignee_id,
      title: form.title.trim(),
      description: form.description || null,
      due_date: form.due_date || null,
    })
    resetForm()
    toast.success('Tugas luar diberikan.')
    tab.value = 'list'
    await load()
  } catch (e: any) {
    formError.value = errorMessage(e, 'Gagal menyimpan tugas.')
  } finally {
    saving.value = false
  }
}

function openEdit(t: Task) {
  editModal.id = t.id
  editModal.form = {
    assignee_id: t.assignee_id,
    title: t.title,
    description: t.description || '',
    due_date: t.due_date || '',
    status: t.status,
  }
  editModal.open = true
}

async function submitEdit() {
  editModal.saving = true
  try {
    await api('PUT', `/tasks/${editModal.id}`, {
      assignee_id: editModal.form.assignee_id,
      title: editModal.form.title,
      description: editModal.form.description || null,
      due_date: editModal.form.due_date || null,
      status: editModal.form.status,
    })
    editModal.open = false
    toast.success('Tugas luar diperbarui.')
    await load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal mengupdate tugas.'))
  } finally {
    editModal.saving = false
  }
}

async function remove(t: Task) {
  const ok = await confirmDialog.confirm({
    title: 'Hapus tugas?',
    message: `"${t.title}" akan dihapus permanen.`,
    confirmText: 'Hapus',
    danger: true,
  })
  if (!ok) return
  try {
    await api('DELETE', `/tasks/${t.id}`)
    toast.success('Tugas luar dihapus.')
    await load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menghapus tugas.'))
  }
}

function resetFilter() {
  listFilter.assignee_id = ''
  listFilter.status = ''
  load()
}

function isOverdue(t: Task) {
  if (!t.due_date || t.status === 'done') return false
  return new Date(t.due_date + 'T00:00:00') < new Date(new Date().toDateString())
}

function formatDate(d: string | null) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function statusLabel(s: string) {
  return { pending: 'Pending', in_progress: 'In Progress', done: 'Done' }[s] || s
}

function statusClass(s: string) {
  if (s === 'done') return 'bg-emerald-100 text-emerald-700'
  if (s === 'in_progress') return 'bg-sky-100 text-sky-700'
  return 'bg-amber-100 text-amber-700'
}

onMounted(() => {
  load()
  loadEmployees()
})
</script>
