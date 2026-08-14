<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Absensi Karyawan</h1>
        <p class="text-sm text-gray-500">Rekap kehadiran per karyawan per tanggal — klik sel untuk detail clock in/out + foto</p>
      </div>
      <button type="button" class="btn-primary" :disabled="!canExport" @click="downloadExport">
        ⬇ Export CSV
      </button>
    </div>

    <!-- Filter: bulan / range tanggal / group -->
    <div class="card mb-6 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="label">Bulan</label>
          <input v-model="filter.month" type="month" class="input" @change="applyMonth" />
        </div>
        <div>
          <label class="label">Dari Tanggal</label>
          <input v-model="filter.from" type="date" class="input" @change="onRangeChange" />
        </div>
        <div>
          <label class="label">Sampai Tanggal</label>
          <input v-model="filter.to" type="date" class="input" @change="onRangeChange" />
        </div>
        <div>
          <label class="label">Group</label>
          <select v-model="filter.group_id" class="input" @change="loadRoster">
            <option :value="null">Semua group</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
        <button type="button" class="btn-secondary" @click="resetFilter">Reset</button>
      </div>
      <p v-if="rosterError" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ rosterError }}</p>
    </div>

    <!-- Roster matrix -->
    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <EmptyState v-else-if="employees.length === 0" icon="👥" title="Tidak ada karyawan" description="Tidak ada karyawan untuk filter ini. Ubah filter atau periode." />

    <div v-else class="card overflow-x-auto">
      <table class="border-separate border-spacing-0 text-sm">
        <thead>
          <tr>
            <th
              class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
            >
              Karyawan
            </th>
            <th
              v-for="d in dates"
              :key="d"
              class="border-b border-r border-gray-200 px-1 py-2 text-center"
              :class="isWeekend(d) ? 'bg-red-50' : 'bg-gray-50'"
            >
              <div class="text-[10px] font-medium uppercase" :class="isWeekend(d) ? 'text-red-400' : 'text-gray-400'">
                {{ dayLabel(d) }}
              </div>
              <div class="text-xs font-semibold" :class="isWeekend(d) ? 'text-red-500' : 'text-gray-700'">
                {{ dayNum(d) }}
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in employees" :key="emp.id" class="group/row">
            <td
              class="sticky left-0 z-10 border-b border-r border-gray-200 bg-white px-3 py-2 group-hover/row:bg-gray-50"
            >
              <div class="whitespace-nowrap font-medium text-gray-900">{{ emp.name }}</div>
              <div v-if="emp.position" class="whitespace-nowrap text-xs text-gray-400">{{ emp.position }}</div>
            </td>
            <td v-for="(day, i) in emp.days" :key="i" class="border-b border-r border-gray-100 p-0">
              <button
                type="button"
                class="block h-12 w-16 cursor-pointer text-center text-xs leading-tight transition hover:ring-2 hover:ring-primary-400"
                :class="cellClass(day, dates[i])"
                :title="`${emp.name} — ${formatDate(dates[i])}`"
                @click="openDetail(emp, dates[i])"
              >
                <span v-if="day.clock_in" class="block font-semibold">▲ {{ day.clock_in }}</span>
                <span v-else class="block text-gray-300">—</span>
                <span v-if="day.clock_out" class="block font-semibold">▼ {{ day.clock_out }}</span>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="!loading && employees.length" class="mt-3 text-xs text-gray-400">
      ▲ Clock in &nbsp;·&nbsp; ▼ Clock out &nbsp;·&nbsp; Klik sel untuk detail + foto selfie
    </p>

    <!-- Modal detail harian -->
    <AppModal v-if="detailOpen" :title="detailTitle" wide @close="detailOpen = false">
      <div v-if="detailLoading" class="p-8 text-center text-sm text-gray-400">Memuat detail…</div>

      <template v-else-if="detail">
        <p v-if="detail.records.length === 0" class="rounded-lg bg-gray-50 p-6 text-center text-sm text-gray-400">
          Tidak ada absen pada tanggal ini.
        </p>

        <template v-else>
          <!-- Masuk & Pulang -->
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Clock In</p>
              <p v-if="clockIn" class="mt-1 text-2xl font-bold text-gray-900">{{ clockIn.time }}</p>
              <p v-else class="mt-1 text-sm text-gray-400">Tidak ada</p>
              <SelfieThumb v-if="clockIn" :photo="clockIn.selfie_photo" />
              <dl v-if="clockIn" class="mt-3 space-y-1 text-xs text-gray-600">
                <div v-if="clockIn.work_location" class="flex justify-between gap-2"><dt>Lokasi</dt><dd class="text-right font-medium">{{ clockIn.work_location }}</dd></div>
                <div v-if="clockIn.distance_meter != null" class="flex justify-between gap-2"><dt>Jarak</dt><dd class="text-right font-medium">{{ Math.round(Number(clockIn.distance_meter)) }} m</dd></div>
                <div class="flex justify-between gap-2"><dt>Status</dt><dd class="text-right"><StatusBadge :status="clockIn.status" /></dd></div>
              </dl>
            </div>

            <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Clock Out</p>
              <p v-if="clockOut" class="mt-1 text-2xl font-bold text-gray-900">{{ clockOut.time }}</p>
              <p v-else class="mt-1 text-sm text-gray-400">Belum clock out</p>
              <SelfieThumb v-if="clockOut" :photo="clockOut.selfie_photo" />
              <dl v-if="clockOut" class="mt-3 space-y-1 text-xs text-gray-600">
                <div v-if="clockOut.work_location" class="flex justify-between gap-2"><dt>Lokasi</dt><dd class="text-right font-medium">{{ clockOut.work_location }}</dd></div>
                <div v-if="clockOut.distance_meter != null" class="flex justify-between gap-2"><dt>Jarak</dt><dd class="text-right font-medium">{{ Math.round(Number(clockOut.distance_meter)) }} m</dd></div>
                <div class="flex justify-between gap-2"><dt>Status</dt><dd class="text-right"><StatusBadge :status="clockOut.status" /></dd></div>
              </dl>
            </div>
          </div>

          <!-- Semua catatan (kalau lebih dari 2) -->
          <div v-if="detail.records.length > 2" class="mt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Semua Catatan ({{ detail.records.length }})</p>
            <div class="max-h-44 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
              <div
                v-for="r in detail.records"
                :key="r.id"
                class="flex items-center justify-between rounded px-2 py-1 text-xs"
                :class="r.type === 'clock_in' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700'"
              >
                <span class="font-medium">{{ r.type === 'clock_in' ? 'Clock In' : 'Clock Out' }}</span>
                <span>{{ r.time }}</span>
              </div>
            </div>
          </div>
        </template>
      </template>

      <p v-else class="rounded-lg bg-red-50 p-4 text-sm text-red-600">{{ detailError }}</p>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface GroupOption {
  id: number
  name: string
}

interface DayCell {
  clock_in: string | null
  clock_out: string | null
  count_in: number
  count_out: number
  has_selfie: boolean
}

interface RosterEmployee {
  id: number
  name: string
  position?: string | null
  days: DayCell[]
}

interface DetailRecord {
  id: number
  type: string
  time: string
  recorded_at: string
  selfie_photo: string | null
  latitude: string | number | null
  longitude: string | number | null
  distance_meter: string | number | null
  status: string
  notes: string | null
  work_location: string | null
}

const DAY_LABELS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

const loading = ref(true)
const rosterError = ref('')
const groups = ref<GroupOption[]>([])
const dates = ref<string[]>([])
const employees = ref<RosterEmployee[]>([])

const filter = reactive({
  month: '',
  from: '',
  to: '',
  group_id: null as number | null,
})

const canExport = computed(() => !!(filter.from && filter.to))

const toast = useToast()

async function downloadExport() {
  if (!canExport.value) return
  try {
    let url = `/attendance/export?from=${filter.from}&to=${filter.to}`
    if (filter.group_id) url += `&group_id=${filter.group_id}`
    const blob = await $fetch(url, {
      baseURL: apiBase(),
      method: 'GET',
      responseType: 'blob',
      headers: { Authorization: `Bearer ${useAuthStore().token}` },
    })
    const objectUrl = URL.createObjectURL(blob as Blob)
    const a = document.createElement('a')
    a.href = objectUrl
    a.download = `absensi-${filter.from}_${filter.to}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(objectUrl)
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal export CSV.'))
  }
}

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailError = ref('')
const detail = ref<{ employee: { name: string; position?: string | null }; date: string; records: DetailRecord[] } | null>(null)
const detailTarget = ref<{ emp: RosterEmployee; date: string } | null>(null)

const detailTitle = computed(() => {
  if (!detailTarget.value) return 'Detail Absensi'
  return `${detailTarget.value.emp.name} — ${formatDate(detailTarget.value.date)}`
})

const clockIn = computed(() => detail.value?.records.find((r) => r.type === 'clock_in') ?? null)
const clockOut = computed(() => [...(detail.value?.records ?? [])].reverse().find((r) => r.type === 'clock_out') ?? null)

function formatDate(d: string) {
  const [y, m, day] = d.split('-')
  return `${day}-${m}-${y}`
}

function dayLabel(d: string) {
  return DAY_LABELS[new Date(`${d}T00:00:00`).getDay()]
}

function dayNum(d: string) {
  return d.slice(8)
}

function isWeekend(d: string) {
  const g = new Date(`${d}T00:00:00`).getDay()
  return g === 0 || g === 6
}

function cellClass(day: DayCell, date: string) {
  if (isWeekend(date)) return 'bg-gray-50 text-gray-300'
  if (day.clock_in && day.clock_out) return 'bg-green-50 text-green-700 hover:bg-green-100'
  if (day.clock_in) return 'bg-amber-50 text-amber-700 hover:bg-amber-100'
  return 'bg-gray-50 text-gray-300 hover:bg-gray-100'
}

/** Pilih bulan → set dari/sampai ke awal & akhir bulan, lalu muat roster. */
function applyMonth() {
  if (!filter.month) return
  const [y, m] = filter.month.split('-')
  filter.from = `${y}-${m}-01`
  filter.to = new Date(Number(y), Number(m), 0).toISOString().slice(0, 10)
  loadRoster()
}

/** Ubah range tanggal manual → bulan dikosongkan. */
function onRangeChange() {
  filter.month = ''
  loadRoster()
}

function resetFilter() {
  const now = new Date()
  filter.month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  applyMonth()
}

async function loadGroups() {
  try {
    const data = await api<{ data: GroupOption[] }>('GET', '/groups')
    groups.value = data.data
  } catch {
    groups.value = []
  }
}

async function loadRoster() {
  if (!filter.from || !filter.to) {
    rosterError.value = 'Pilih bulan atau isi rentang tanggal.'
    return
  }
  loading.value = true
  rosterError.value = ''
  try {
    const params = new URLSearchParams()
    params.set('from', filter.from)
    params.set('to', filter.to)
    if (filter.group_id) params.set('group_id', String(filter.group_id))
    const data = await api<{ data: { dates: string[]; employees: RosterEmployee[] } }>(
      'GET',
      `/attendance/roster?${params.toString()}`,
    )
    dates.value = data.data.dates
    employees.value = data.data.employees
  } catch (e: any) {
    employees.value = []
    dates.value = []
    rosterError.value = errorMessage(e, 'Gagal memuat rekap absensi.')
  } finally {
    loading.value = false
  }
}

async function openDetail(emp: RosterEmployee, date: string) {
  detailTarget.value = { emp, date }
  detailOpen.value = true
  detail.value = null
  detailError.value = ''
  detailLoading.value = true
  try {
    const data = await api<{ data: { employee: { name: string; position?: string | null }; date: string; records: DetailRecord[] } }>(
      'GET',
      `/attendance/roster/${emp.id}?date=${date}`,
    )
    detail.value = data.data
  } catch (e: any) {
    detailError.value = errorMessage(e, 'Gagal memuat detail absensi.')
  } finally {
    detailLoading.value = false
  }
}

onMounted(() => {
  resetFilter()
  loadGroups()
})
</script>
