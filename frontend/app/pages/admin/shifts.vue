<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Shift</h1>
        <p class="text-sm text-gray-500">Jam masuk-pulang & window check-in/out</p>
      </div>
      <button class="btn-primary" @click="openCreate">+ Tambah Shift</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <div v-else-if="shifts.length === 0" class="card p-10 text-center text-sm text-gray-400">
      Belum ada shift.
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Kode</th>
            <th class="px-4 py-3 font-medium">Nama</th>
            <th class="px-4 py-3 font-medium">Pola Kerja</th>
            <th class="px-4 py-3 font-medium">Jam Kerja</th>
            <th class="px-4 py-3 font-medium">Check-in</th>
            <th class="px-4 py-3 font-medium">Toleransi</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="s in shifts" :key="s.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs font-medium text-gray-900">{{ s.code || '—' }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ s.name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ s.work_pattern?.name || '—' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ s.work_hour_start || '?' }} – {{ s.work_hour_end || '?' }}</td>
            <td class="px-4 py-3 text-gray-600">
              {{ s.check_in_start || '?' }} – {{ s.check_in_end || '?' }}
              <span v-if="s.is_overnight" class="ml-1 text-xs text-purple-600">🌙</span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ s.tolerance_minutes }} mnt</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="s.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(s)">Edit</button>
                <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(s)">Hapus</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal form -->
    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Shift' : 'Edit Shift'" @close="modal.open = false">
      <form @submit.prevent="submitForm">
        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Nama <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" class="input" placeholder="mis. Pagi" required />
          </div>
          <div>
            <label class="label">Kode</label>
            <input v-model="form.code" type="text" class="input uppercase" placeholder="mis. PGI" />
          </div>
        </div>

        <div class="mb-4">
          <label class="label">Pola Kerja</label>
          <select v-model="form.work_pattern_id" class="input">
            <option :value="null">— Tanpa pola —</option>
            <option v-for="p in patterns" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</option>
          </select>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Jam Mulai Kerja</label>
            <input v-model="form.work_hour_start" type="time" class="input" />
          </div>
          <div>
            <label class="label">Jam Selesai Kerja</label>
            <input v-model="form.work_hour_end" type="time" class="input" />
          </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Check-in mulai</label>
            <input v-model="form.check_in_start" type="time" class="input" />
          </div>
          <div>
            <label class="label">Check-in sampai</label>
            <input v-model="form.check_in_end" type="time" class="input" />
          </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Check-out mulai</label>
            <input v-model="form.check_out_start" type="time" class="input" />
          </div>
          <div>
            <label class="label">Check-out sampai</label>
            <input v-model="form.check_out_end" type="time" class="input" />
          </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Toleransi (menit)</label>
            <input v-model.number="form.tolerance_minutes" type="number" min="0" max="240" class="input" />
          </div>
          <div>
            <label class="label">Minimal Jam Kerja</label>
            <input v-model.number="form.min_work_hours" type="number" min="1" max="24" class="input" placeholder="opsional" />
          </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-4">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.is_overnight" type="checkbox" class="rounded border-gray-300" />
            Shift lintas malam (overnight)
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.has_overtime" type="checkbox" class="rounded border-gray-300" />
            Ada lembur
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
            Aktif
          </label>
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

interface PatternOption {
  id: number
  code: string
  name: string
}

interface ShiftItem {
  id: number
  name: string
  code?: string | null
  work_pattern_id?: number | null
  work_pattern?: { id: number; code: string; name: string } | null
  work_hour_start?: string | null
  work_hour_end?: string | null
  check_in_start?: string | null
  check_in_end?: string | null
  check_out_start?: string | null
  check_out_end?: string | null
  is_overnight: boolean
  has_overtime: boolean
  tolerance_minutes: number
  min_work_hours?: number | null
  is_active: boolean
}

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const shifts = ref<ShiftItem[]>([])
const patterns = ref<PatternOption[]>([])

const modal = reactive({ open: false, mode: 'create' as 'create' | 'edit', id: null as number | null })
const form = reactive({
  name: '',
  code: '',
  work_pattern_id: null as number | null,
  work_hour_start: '',
  work_hour_end: '',
  check_in_start: '',
  check_in_end: '',
  check_out_start: '',
  check_out_end: '',
  is_overnight: false,
  has_overtime: false,
  tolerance_minutes: 15,
  min_work_hours: null as number | null,
  is_active: true,
})

async function load() {
  loading.value = true
  try {
    const [shiftRes, patternRes] = await Promise.all([
      api<{ data: ShiftItem[] }>('GET', '/shifts'),
      api<{ data: PatternOption[] }>('GET', '/work-patterns'),
    ])
    shifts.value = shiftRes.data
    patterns.value = patternRes.data
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal memuat shift.')
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.name = ''
  form.code = ''
  form.work_pattern_id = null
  form.work_hour_start = ''
  form.work_hour_end = ''
  form.check_in_start = ''
  form.check_in_end = ''
  form.check_out_start = ''
  form.check_out_end = ''
  form.is_overnight = false
  form.has_overtime = false
  form.tolerance_minutes = 15
  form.min_work_hours = null
  form.is_active = true
  error.value = ''
}

function openCreate() {
  resetForm()
  modal.mode = 'create'
  modal.id = null
  modal.open = true
}

function openEdit(s: ShiftItem) {
  resetForm()
  modal.mode = 'edit'
  modal.id = s.id
  Object.assign(form, {
    name: s.name,
    code: s.code || '',
    work_pattern_id: s.work_pattern_id ?? null,
    work_hour_start: s.work_hour_start || '',
    work_hour_end: s.work_hour_end || '',
    check_in_start: s.check_in_start || '',
    check_in_end: s.check_in_end || '',
    check_out_start: s.check_out_start || '',
    check_out_end: s.check_out_end || '',
    is_overnight: s.is_overnight,
    has_overtime: s.has_overtime,
    tolerance_minutes: s.tolerance_minutes,
    min_work_hours: s.min_work_hours ?? null,
    is_active: s.is_active,
  })
  modal.open = true
}

async function submitForm() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      name: form.name,
      code: form.code || null,
      work_pattern_id: form.work_pattern_id,
      work_hour_start: form.work_hour_start || null,
      work_hour_end: form.work_hour_end || null,
      check_in_start: form.check_in_start || null,
      check_in_end: form.check_in_end || null,
      check_out_start: form.check_out_start || null,
      check_out_end: form.check_out_end || null,
      is_overnight: form.is_overnight,
      has_overtime: form.has_overtime,
      tolerance_minutes: form.tolerance_minutes,
      min_work_hours: form.min_work_hours,
      is_active: form.is_active,
    }
    if (modal.mode === 'create') {
      await api('POST', '/shifts', payload)
    } else {
      await api('PUT', `/shifts/${modal.id}`, payload)
    }
    modal.open = false
    await load()
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal menyimpan shift.')
  } finally {
    saving.value = false
  }
}

async function remove(s: ShiftItem) {
  if (!confirm(`Hapus shift "${s.name}"?`)) return
  try {
    await api('DELETE', `/shifts/${s.id}`)
    await load()
  } catch (e: any) {
    alert(errorMessage(e, 'Gagal menghapus shift.'))
  }
}

onMounted(load)
</script>
