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
        <h1 class="text-xl font-bold text-gray-800">Riwayat Absensi</h1>
      </div>
    </div>

    <div class="px-4 py-4">
      <!-- Stat cards -->
      <div v-if="!loading" class="mb-6 flex gap-3">
        <div class="flex-1 rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
          <p class="text-2xl font-bold text-gray-800">{{ stats.hadir }}</p>
          <p class="mt-1 text-xs text-gray-400">Hadir</p>
        </div>
        <div class="flex-1 rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
          <p class="text-2xl font-bold text-amber-500">{{ stats.telat }}</p>
          <p class="mt-1 text-xs text-gray-400">Telat</p>
        </div>
        <div class="flex-1 rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
          <p class="text-2xl font-bold text-red-500">{{ stats.alpha }}</p>
          <p class="mt-1 text-xs text-gray-400">Alpha</p>
        </div>
      </div>

      <!-- Daftar hari -->
      <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400 shadow-sm">
        Memuat…
      </div>

      <div v-else-if="days.length === 0" class="rounded-xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-400 shadow-sm">
        Belum ada riwayat absensi.
      </div>

      <div v-else class="space-y-3">
        <div v-for="day in days" :key="day.date" class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
            :class="day.iconClass"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
              <path v-if="day.status === 'on_time'" d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
              <template v-else>
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
              </template>
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-800">{{ day.dateLabel }}</p>
            <p class="truncate text-xs text-gray-400">{{ day.timeRange }}</p>
          </div>
          <span class="rounded-lg px-2.5 py-1 text-xs font-medium" :class="day.badgeClass">
            {{ day.statusLabel }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const auth = useAuthStore()

const { data, pending: loading } = useApi<any[]>('/attendance/me')
const records = computed(() => data.value || [])

interface DayRow {
  date: string
  dateLabel: string
  timeRange: string
  status: 'on_time' | 'late'
  statusLabel: string
  iconClass: string
  badgeClass: string
}

/** Kelompokkan record per tanggal (dari record terbaru ke lama). */
const days = computed<DayRow[]>(() => {
  const byDate = new Map<string, any[]>()
  for (const r of records.value) {
    const d = (r.recorded_at || '').slice(0, 10)
    if (!d) continue
    if (!byDate.has(d)) byDate.set(d, [])
    byDate.get(d)!.push(r)
  }
  return [...byDate.entries()].map(([date, recs]) => {
    recs.sort((a, b) => (a.recorded_at < b.recorded_at ? -1 : 1))
    const first = recs[0]
    const last = recs[recs.length - 1]
    const clockOut = last?.type === 'clock_out' ? last : null
    const status = clockOut ? 'on_time' : 'on_time'
    return {
      date,
      dateLabel: new Date(date + 'T00:00:00').toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
      timeRange: `${timeOf(first?.recorded_at)}${clockOut ? ' - ' + timeOf(clockOut.recorded_at) : ''}${first?.work_location?.name ? ' · ' + first.work_location.name : ''}`,
      status,
      statusLabel: clockOut ? 'Tepat Waktu' : 'Berlangsung',
      iconClass: 'bg-emerald-100 text-emerald-600',
      badgeClass: 'bg-emerald-100 text-emerald-600',
    }
  })
})

const stats = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  const present = days.value.filter((d) => d.date <= today && d.status === 'on_time').length
  return { hadir: present, telat: 0, alpha: 0 }
})

function timeOf(iso: string) {
  if (!iso) return '--:--'
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>
