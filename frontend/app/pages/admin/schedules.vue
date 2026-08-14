<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-gray-900">Jadwal Karyawan</h1>
      <p class="text-sm text-gray-500">Set jadwal shift per karyawan per tanggal (snapshot harian)</p>
    </div>

    <!-- Form set jadwal -->
    <div class="card mb-6 p-5">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Set Jadwal</h2>
      <form @submit.prevent="submitSchedule">
        <div class="mb-4 grid gap-3 md:grid-cols-3">
          <div>
            <label class="label">Group (isi otomatis anggotanya)</label>
            <select v-model="schedule.group_id" class="input" @change="fillFromGroup">
              <option :value="null">— Semua karyawan —</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Dari Tanggal <span class="text-red-500">*</span></label>
            <input v-model="schedule.from" type="date" class="input" required />
          </div>
          <div>
            <label class="label">Sampai Tanggal <span class="text-red-500">*</span></label>
            <input v-model="schedule.to" type="date" class="input" required />
          </div>
        </div>

        <div class="mb-4 grid gap-3 md:grid-cols-2">
          <div>
            <label class="label">Shift</label>
            <select v-model="schedule.shift_id" class="input">
              <option :value="null">— Tanpa shift (libur/off) —</option>
              <option v-for="s in shifts" :key="s.id" :value="s.id">
                {{ s.name }} ({{ s.work_hour_start || '?' }}–{{ s.work_hour_end || '?' }})
              </option>
            </select>
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="schedule.status" class="input">
              <option value="scheduled">Scheduled</option>
              <option value="confirmed">Confirmed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <div class="mb-4">
          <label class="label">Karyawan ({{ selectedEmployeeIds.length }} dipilih)</label>
          <div v-if="employeeOptions.length === 0" class="rounded-lg bg-gray-50 p-3 text-center text-xs text-gray-400">
            Belum ada karyawan aktif.
          </div>
          <div v-else class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
            <label
              v-for="emp in employeeOptions"
              :key="emp.id"
              class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50"
            >
              <input v-model="selectedEmployeeIds" type="checkbox" :value="emp.id" class="rounded border-gray-300" />
              <span>{{ emp.name }}</span>
              <span v-if="emp.position" class="text-xs text-gray-400">· {{ emp.position }}</span>
            </label>
          </div>
        </div>

        <p v-if="scheduleError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ scheduleError }}</p>

        <div class="flex items-center gap-2">
          <button type="submit" class="btn-primary" :disabled="saving">
            <span v-if="saving" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            {{ saving ? 'Menyimpan…' : 'Simpan Jadwal' }}
          </button>
          <button v-if="selectedEmployeeIds.length" type="button" class="btn-secondary" @click="selectedEmployeeIds = []">
            Kosongkan pilihan
          </button>
        </div>
      </form>
    </div>

    <!-- Daftar jadwal -->
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Daftar Jadwal</h2>
      <select v-model="filter.group_id" class="input !w-48 !py-1.5 !text-sm" @change="loadSnapshots">
        <option :value="null">Semua group</option>
        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
      <input v-model="filter.from" type="date" class="input !w-40 !py-1.5 !text-sm" @change="loadSnapshots" />
      <input v-model="filter.to" type="date" class="input !w-40 !py-1.5 !text-sm" @change="loadSnapshots" />
    </div>

    <div v-if="snapshotLoading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <EmptyState v-else-if="snapshots.length === 0" icon="📋" title="Belum ada jadwal" description="Belum ada jadwal untuk filter ini. Ubah filter atau generate snapshot." />

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Tanggal</th>
            <th class="px-4 py-3 font-medium">Karyawan</th>
            <th class="px-4 py-3 font-medium">Shift</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="snap in snapshots" :key="snap.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ formatDate(snap.date) }}</td>
            <td class="px-4 py-3 text-gray-700">{{ snap.employee?.name || '—' }}</td>
            <td class="px-4 py-3 text-gray-600">
              {{ snap.shift?.name || 'Off' }}
              <span v-if="snap.shift" class="text-xs text-gray-400">({{ snap.shift.work_hour_start }}–{{ snap.shift.work_hour_end }})</span>
            </td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusClass(snap.status)"
              >
                {{ snap.status }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeSnapshot(snap)">Hapus</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface GroupOption {
  id: number
  name: string
  members?: { id: number }[]
}

interface EmployeeOption {
  id: number
  name: string
  position?: string | null
}

interface ShiftOption {
  id: number
  name: string
  work_hour_start?: string | null
  work_hour_end?: string | null
}

interface SnapshotItem {
  id: number
  date: string
  status: string
  employee?: { id: number; name: string } | null
  shift?: { id: number; name: string; work_hour_start?: string | null; work_hour_end?: string | null } | null
}

const saving = ref(false)
const snapshotLoading = ref(true)
const scheduleError = ref('')

const groups = ref<GroupOption[]>([])
const shifts = ref<ShiftOption[]>([])
const employeeOptions = ref<EmployeeOption[]>([])
const selectedEmployeeIds = ref<number[]>([])
const snapshots = ref<SnapshotItem[]>([])

const schedule = reactive({
  group_id: null as number | null,
  from: '',
  to: '',
  shift_id: null as number | null,
  status: 'scheduled',
})

const filter = reactive({
  group_id: null as number | null,
  from: '',
  to: '',
})

function formatDate(d: string) {
  const [y, m, day] = d.split('-')
  return `${day}-${m}-${y}`
}

function statusClass(s: string) {
  if (s === 'confirmed') return 'bg-green-100 text-green-700'
  if (s === 'cancelled') return 'bg-red-100 text-red-600'
  return 'bg-gray-100 text-gray-600'
}

async function loadGroups() {
  try {
    const data = await api<{ data: GroupOption[] }>('GET', '/groups')
    groups.value = data.data
  } catch {
    groups.value = []
  }
}

async function loadShifts() {
  try {
    const data = await api<{ data: ShiftOption[] }>('GET', '/shifts')
    shifts.value = data.data
  } catch {
    shifts.value = []
  }
}

async function loadEmployees() {
  try {
    const data = await api<{ data: EmployeeOption[] }>('GET', '/groups/available-employees')
    employeeOptions.value = data.data
  } catch {
    employeeOptions.value = []
  }
}

/** Kalau pilih group → isi checkbox karyawan dengan anggota group tsb. */
async function fillFromGroup() {
  if (!schedule.group_id) return
  try {
    const data = await api<{ data: GroupOption }>('GET', `/groups/${schedule.group_id}`)
    selectedEmployeeIds.value = (data.data.members || []).map((m) => m.id)
  } catch {
    selectedEmployeeIds.value = []
  }
}

async function submitSchedule() {
  scheduleError.value = ''
  if (selectedEmployeeIds.value.length === 0) {
    scheduleError.value = 'Pilih minimal satu karyawan.'
    return
  }
  if (!schedule.from || !schedule.to) {
    scheduleError.value = 'Isi rentang tanggal.'
    return
  }
  saving.value = true
  try {
    await api('POST', '/schedule-snapshots', {
      employee_ids: selectedEmployeeIds.value,
      from: schedule.from,
      to: schedule.to,
      shift_id: schedule.shift_id,
      status: schedule.status,
      source: 'manual',
    })
    await loadSnapshots()
  } catch (e: any) {
    scheduleError.value = errorMessage(e, 'Gagal menyimpan jadwal.')
  } finally {
    saving.value = false
  }
}

async function loadSnapshots() {
  snapshotLoading.value = true
  try {
    const params = new URLSearchParams()
    if (filter.group_id) params.set('group_id', String(filter.group_id))
    if (filter.from) params.set('from', filter.from)
    if (filter.to) params.set('to', filter.to)
    const qs = params.toString()
    const data = await api<{ data: SnapshotItem[] }>('GET', `/schedule-snapshots${qs ? `?${qs}` : ''}`)
    snapshots.value = data.data
  } catch {
    snapshots.value = []
  } finally {
    snapshotLoading.value = false
  }
}

async function removeSnapshot(snap: SnapshotItem) {
  if (!confirm(`Hapus jadwal ${snap.employee?.name || ''} tanggal ${formatDate(snap.date)}?`)) return
  try {
    await api('DELETE', `/schedule-snapshots/${snap.id}`)
    await loadSnapshots()
  } catch (e: any) {
    alert(errorMessage(e, 'Gagal menghapus jadwal.'))
  }
}

onMounted(async () => {
  // Default: bulan ini
  const now = new Date()
  const first = new Date(now.getFullYear(), now.getMonth(), 1)
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  schedule.from = first.toISOString().slice(0, 10)
  schedule.to = last.toISOString().slice(0, 10)
  filter.from = schedule.from
  filter.to = schedule.to

  await Promise.all([loadGroups(), loadShifts(), loadEmployees()])
  await loadSnapshots()
})
</script>
