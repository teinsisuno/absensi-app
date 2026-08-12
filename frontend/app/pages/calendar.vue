<template>
  <div>
    <!-- Header -->
    <div class="sticky top-0 z-20 border-b border-gray-100 bg-white px-6 pb-4 pt-12">
      <div class="mb-4 flex items-center gap-4">
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200"
          @click="navigateTo('/dashboard')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="text-xl font-bold text-gray-800">Jadwal</h1>
      </div>

      <!-- Pilih bulan -->
      <div class="flex items-center justify-between">
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200"
          @click="shiftMonth(-1)"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <p class="font-semibold text-gray-800">{{ monthLabel }}</p>
        <button
          type="button"
          class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200"
          @click="shiftMonth(1)"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
            <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>

      <!-- Supervisor: pilih group -->
      <div v-if="isSupervisor && myGroups.length > 0" class="mt-3">
        <select
          v-model="selectedGroupId"
          class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-700 focus:border-primary-500 focus:outline-none"
          @change="loadSchedule"
        >
          <option :value="null">Jadwal saya sendiri</option>
          <option v-for="g in myGroups" :key="g.id" :value="g.id">
            {{ g.name }} ({{ g.members_count }} anggota)
          </option>
        </select>
      </div>
    </div>

    <div class="px-4 py-4">
      <!-- Legend -->
      <div class="mb-3 flex items-center gap-4 text-[10px] text-gray-500">
        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Masuk</span>
        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Telat</span>
        <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Libur/Off</span>
      </div>

      <!-- Grid kalender -->
      <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50 text-center text-[10px] font-semibold uppercase text-gray-400">
          <div v-for="d in ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']" :key="d" class="py-2">{{ d }}</div>
        </div>

        <div v-if="loading" class="p-10 text-center text-sm text-gray-400">Memuat…</div>

        <div v-else class="grid grid-cols-7">
          <div v-for="cell in cells" :key="cell.key" class="flex aspect-square flex-col items-center justify-center border-b border-r border-gray-50 p-0.5 last:border-r-0">
            <!-- Hari dari bulan lain: redup -->
            <span v-if="!cell.inMonth" class="text-[10px] text-gray-300">{{ cell.day }}</span>

            <!-- Hari ini -->
            <template v-else>
              <span class="text-[11px] font-medium" :class="cell.isToday ? 'rounded-full bg-primary-600 px-1.5 py-0.5 text-white' : 'text-gray-700'">
                {{ cell.day }}
              </span>
              <span
                v-if="cell.shift"
                class="mt-0.5 flex h-1.5 w-1.5 items-center justify-center rounded-full"
                :class="cell.color"
              ></span>
              <span v-else-if="cell.off" class="mt-0.5 h-1.5 w-1.5 rounded-full bg-red-500"></span>
              <span v-else-if="cell.isWeekend" class="mt-0.5 h-1.5 w-1.5 rounded-full bg-red-500/40"></span>
            </template>
          </div>
        </div>
      </div>

      <!-- Detail hari ini / dipilih -->
      <div class="mt-5">
        <h3 class="mb-3 font-bold text-gray-800">{{ selectedDateLabel }}</h3>

        <div v-if="dayDetails.length === 0" class="rounded-xl border border-gray-100 bg-white p-6 text-center text-sm text-gray-400 shadow-sm">
          Tidak ada jadwal untuk tanggal ini.
        </div>

        <div v-else class="space-y-3">
          <div v-for="d in dayDetails" :key="d.employee_id + d.date" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <div v-if="isSupervisor && selectedGroupId" class="mb-2 flex items-center gap-2">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-600/10 text-xs font-bold text-primary-700">
                {{ (d.employee?.name || '?').charAt(0) }}
              </div>
              <p class="text-sm font-semibold text-gray-800">{{ d.employee?.name }}</p>
            </div>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-semibold text-gray-800">{{ d.shift?.name || 'Off / Libur' }}</p>
                <p class="mt-0.5 text-xs text-gray-400">
                  <template v-if="d.shift">
                    {{ d.shift.work_hour_start || '?' }} – {{ d.shift.work_hour_end || '?' }}
                  </template>
                  <template v-else>Hari bebas</template>
                </p>
              </div>
              <span
                class="rounded-lg px-2.5 py-1 text-xs font-medium"
                :class="statusBadgeClass(d.status)"
              >
                {{ statusLabel(d) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const auth = useAuthStore()

const isSupervisor = computed(() => auth.employee?.mobile_role === 'supervisor')

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth()) // 0-based
const selectedDate = ref(now.toISOString().slice(0, 10))

const loading = ref(false)
const schedule = ref<any[]>([])
const myGroups = ref<any[]>([])
const selectedGroupId = ref<number | null>(null)

const monthLabel = computed(() =>
  new Date(year.value, month.value, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }),
)

const selectedDateLabel = computed(() =>
  new Date(selectedDate.value + 'T00:00:00').toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  }),
)

/** Semua sel kalender bulan aktif (termasuk hari dari bulan tetangga). */
const cells = computed(() => {
  const first = new Date(year.value, month.value, 1)
  const startIdx = first.getDay() // 0 = Minggu
  const daysInMonth = new Date(year.value, month.value + 1, 0).getDate()

  const map = new Map<string, any>()
  for (const s of schedule.value) map.set(s.date, s)

  const out: any[] = []
  const start = new Date(year.value, month.value, 1 - startIdx)
  for (let i = 0; i < 42; i++) {
    const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i)
    const iso = d.toISOString().slice(0, 10)
    const snap = map.get(iso)
    const isWeekend = d.getDay() === 0 || d.getDay() === 6
    out.push({
      key: iso,
      day: d.getDate(),
      inMonth: d.getMonth() === month.value,
      isToday: iso === new Date().toISOString().slice(0, 10),
      shift: snap?.shift_id ? snap : null,
      off: snap && !snap.shift_id,
      isWeekend,
      color: snap?.shift_id ? cellColor(snap) : '',
    })
  }
  return out
})

function cellColor(snap: any) {
  if (snap.is_leave || snap.is_permit || snap.status === 'cancelled') return 'bg-red-500'
  if (snap.status === 'confirmed') return 'bg-amber-500'
  return 'bg-emerald-500'
}

const dayDetails = computed(() => {
  const map = new Map<string, any>()
  for (const s of schedule.value) {
    // Group view → tampilkan semua member; self view → cukup satu (key sama)
    map.set(s.employee_id + '|' + s.date, s)
  }
  return [...map.values()].filter((s) => s.date === selectedDate.value)
})

function statusLabel(d: any) {
  if (d.is_leave || d.is_permit) return 'Cuti/Izin'
  if (d.status === 'cancelled') return 'Dibatalkan'
  if (!d.shift_id) return 'Off'
  return d.status === 'confirmed' ? 'Konfirmasi' : 'Terjadwal'
}

function statusBadgeClass(d: any) {
  if (d.is_leave || d.is_permit) return 'bg-red-100 text-red-600'
  if (d.status === 'cancelled') return 'bg-red-100 text-red-500'
  if (!d.shift_id) return 'bg-gray-100 text-gray-500'
  return d.status === 'confirmed' ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'
}

function shiftMonth(delta: number) {
  const m = month.value + delta
  year.value = new Date(year.value, m, 1).getFullYear()
  month.value = new Date(year.value, m, 1).getMonth()
  loadSchedule()
}

async function loadGroups() {
  try {
    const data = await api<{ data: any[] }>('GET', '/groups/mine')
    myGroups.value = data.data
  } catch {
    myGroups.value = []
  }
}

async function loadSchedule() {
  loading.value = true
  try {
    const from = new Date(year.value, month.value, 1).toISOString().slice(0, 10)
    const to = new Date(year.value, month.value + 1, 0).toISOString().slice(0, 10)
    const params = new URLSearchParams({ from, to })
    if (selectedGroupId.value) params.set('group_id', String(selectedGroupId.value))
    const data = await api<{ data: any[] }>('GET', `/schedule-snapshots/me?${params.toString()}`)
    schedule.value = data.data
  } catch {
    schedule.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (isSupervisor.value) await loadGroups()
  await loadSchedule()
})
</script>
