<template>
  <div>
    <!-- Header teal -->
    <div class="rounded-b-[2rem] bg-primary-800 px-6 pb-6 pt-12 shadow-lg">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-primary-200/70">{{ greeting }}</p>
          <h2 class="text-xl font-bold text-white">{{ auth.employee?.name || auth.user?.name || 'Karyawan' }}</h2>
        </div>
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
          @click="navigateTo('/profile')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
          </svg>
        </button>
      </div>

      <!-- Status card -->
      <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs text-white/60">Status Hari Ini</p>
            <p class="mt-0.5 text-lg font-bold text-white">{{ statusLabel }}</p>
          </div>
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/10">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6 text-primary-300">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
        </div>
        <div class="mt-3 flex gap-4 border-t border-white/10 pt-3">
          <div class="flex-1">
            <p class="text-[10px] uppercase tracking-wider text-white/50">Clock In</p>
            <p class="font-semibold text-white">{{ todayClockIn || '--:--' }}</p>
          </div>
          <div class="flex-1">
            <p class="text-[10px] uppercase tracking-wider text-white/50">Clock Out</p>
            <p class="font-semibold text-white">{{ todayClockOut || '--:--' }}</p>
          </div>
          <div class="flex-1">
            <p class="text-[10px] uppercase tracking-wider text-white/50">Lokasi</p>
            <p class="truncate text-xs font-semibold text-white">{{ todayLocation || '—' }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="px-4 py-6">
      <!-- Menu grid -->
      <div class="mb-6 grid grid-cols-2 gap-3">
        <MenuCard
          icon="clock"
          color="primary"
          label="Absensi"
          sub="Clock in/out"
          @click="openAbsenModal"
        />
        <MenuCard
          icon="file"
          color="warning"
          label="Pengajuan"
          sub="Izin/cuti/sakit"
          to="/leave-request"
        />
        <MenuCard
          icon="map"
          color="success"
          label="Kunjungan"
          sub="Lapangan"
          :disabled="true"
        />
        <MenuCard
          icon="tasks"
          color="purple"
          label="Tugas"
          sub="Segera hadir"
          :disabled="true"
        />
      </div>

      <!-- Riwayat absensi -->
      <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-bold text-gray-800">Riwayat Absensi</h3>
          <button type="button" class="text-xs font-medium text-primary-600" @click="navigateTo('/attendance')">
            Lihat Semua
          </button>
        </div>

        <div v-if="historyLoading" class="py-4 text-center text-sm text-gray-400">Memuat…</div>
        <div v-else-if="recentHistory.length === 0" class="py-4 text-center text-sm text-gray-400">
          Belum ada catatan absensi.
        </div>
        <div v-else class="space-y-3">
          <div v-for="row in recentHistory" :key="row.key" class="flex items-center gap-3 rounded-xl bg-gray-50 p-3">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
              :class="row.status === 'on_time' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'"
            >
              <svg v-if="row.status === 'on_time'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
                <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-800">{{ row.dateLabel }}</p>
              <p class="truncate text-xs text-gray-400">{{ row.timeRange }}</p>
            </div>
            <span
              class="rounded-lg px-2 py-1 text-xs font-medium"
              :class="row.status === 'on_time' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'"
            >
              {{ row.statusLabel }}
            </span>
          </div>
        </div>
      </div>

      <!-- Pengumuman -->
      <div class="rounded-2xl bg-gradient-to-r from-primary-600 to-primary-800 p-5 text-white shadow-lg shadow-primary-600/20">
        <div class="flex items-start gap-3">
          <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
              <path d="M3 11l18-7-7 18-2.5-7.5L3 11z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <div>
            <p class="mb-1 text-[10px] font-medium uppercase tracking-wider text-primary-200">Pengumuman</p>
            <h4 class="text-sm font-bold leading-snug">Belum ada pengumuman</h4>
            <p class="mt-1 text-xs text-white/70">Pengumuman dari HR akan tampil di sini.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal pilih aksi absensi -->
    <AbsenModal v-if="absenModal.open" @close="absenModal.open = false" />
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const auth = useAuthStore()

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 11) return 'Selamat Pagi,'
  if (h < 15) return 'Selamat Siang,'
  if (h < 19) return 'Selamat Sore,'
  return 'Selamat Malam,'
})

const dateStr = computed(() => new Date().toISOString().slice(0, 10))
const { data, pending: historyLoading } = useApi<{ data: any[] }>(() => `/attendance/me?date=${dateStr.value}`)
const todayRecords = computed(() => data.value?.data || [])

const todayClockIn = computed(() => {
  const r = todayRecords.value.find((x) => x.type === 'clock_in')
  return r ? timeOf(r.recorded_at) : ''
})
const todayClockOut = computed(() => {
  const r = todayRecords.value.find((x) => x.type === 'clock_out')
  return r ? timeOf(r.recorded_at) : ''
})
const todayLocation = computed(() => {
  const r = todayRecords.value.find((x) => x.work_location?.name)
  return r?.work_location?.name || ''
})
const isWorking = computed(() => todayRecords.value[0]?.type === 'clock_in')
const statusLabel = computed(() => {
  if (isWorking.value) return 'Sedang Bekerja'
  if (todayRecords.value.length > 0) return 'Selesai Hari Ini'
  return 'Belum Absen'
})

// --- Modal pilih aksi absensi (komponen AbsenModal) ---
const absenModal = reactive({ open: false })
function openAbsenModal() {
  absenModal.open = true
}

/** Riwayat hari ini → tampilkan sebagai satu baris (kalau sudah ada absen). */
const recentHistory = computed(() => {
  const rec = todayRecords.value
  if (rec.length === 0) return []
  const first = rec[rec.length - 1] // clock_in (paling lama)
  const last = rec[0] // clock_out (terbaru) kalau ada
  return [
    {
      key: 'today',
      dateLabel: new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }),
      timeRange: `${timeOf(first.recorded_at)}${last?.type === 'clock_out' ? ' - ' + timeOf(last.recorded_at) : ''} · ${first.work_location?.name || ''}`,
      status: last?.type === 'clock_out' ? 'on_time' : 'on_time',
      statusLabel: last?.type === 'clock_out' ? 'Selesai' : 'Berlangsung',
    },
  ]
})

function timeOf(iso: string) {
  if (!iso) return '--:--'
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>
