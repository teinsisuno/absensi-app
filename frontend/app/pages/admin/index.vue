<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
      <p class="text-sm text-gray-500">Ringkasan operasional {{ currentYear }}</p>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <template v-else>
      <!-- Stat cards -->
      <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Karyawan Aktif</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.employees_active }}</p>
          <p class="text-xs text-gray-400">{{ stats.employees_total }} total</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Group</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.groups }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Shift Aktif</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.shifts_active }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Libur {{ currentYear }}</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ stats.holidays_year }}</p>
        </div>
      </div>

      <!-- Stat absensi hari ini -->
      <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="card border-l-4 border-primary-500 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Jam Masuk Hari Ini</p>
          <p class="mt-1 text-2xl font-bold text-primary-600">{{ stats.clock_in_today }}</p>
        </div>
        <div class="card border-l-4 border-emerald-500 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Jam Keluar Hari Ini</p>
          <p class="mt-1 text-2xl font-bold text-emerald-600">{{ stats.clock_out_today }}</p>
        </div>
        <div class="card border-l-4 border-amber-500 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Pola Kerja Aktif</p>
          <p class="mt-1 text-2xl font-bold text-amber-600">{{ stats.work_patterns }}</p>
        </div>
        <div class="card border-l-4 border-sky-500 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Jadwal Bulan Ini</p>
          <p class="mt-1 text-2xl font-bold text-sky-600">{{ stats.snapshots_month }}</p>
        </div>
      </div>

      <!-- Butuh aksi -->
      <div v-if="pendingTotal > 0" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-red-700">⚠️ Butuh Aksi</h2>
          <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ pendingTotal }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <NuxtLink to="/admin/leave-requests?status=pending" class="rounded-lg bg-white px-3 py-2 text-sm text-red-700 shadow-sm hover:bg-red-100">
            📝 {{ pendingLeave }} pengajuan izin pending
          </NuxtLink>
          <NuxtLink to="/admin/overtime-requests?status=pending" class="rounded-lg bg-white px-3 py-2 text-sm text-red-700 shadow-sm hover:bg-red-100">
            ⏰ {{ pendingOvertime }} pengajuan lembur pending
          </NuxtLink>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="mb-6">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Aksi Cepat</h2>
        <div class="flex flex-wrap gap-2">
          <NuxtLink to="/admin/employees" class="btn-secondary">+ Tambah Karyawan</NuxtLink>
          <NuxtLink to="/admin/announcements" class="btn-secondary">📢 Buat Pengumuman</NuxtLink>
          <NuxtLink to="/admin/tasks" class="btn-secondary">✅ Beri Tugas Luar</NuxtLink>
        </div>
      </div>

      <!-- Menu fasilitas -->
      <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Fasilitas</h2>
      <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        <NuxtLink
          v-for="item in menus"
          :key="item.to"
          :to="item.to"
          class="card group p-4 transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="text-2xl">{{ item.icon }}</div>
          <p class="mt-2 font-medium text-gray-900 group-hover:text-primary-600">{{ item.title }}</p>
          <p class="mt-0.5 text-xs text-gray-400">{{ item.desc }}</p>
        </NuxtLink>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

const currentYear = new Date().getFullYear()
const loading = ref(true)
const stats = ref({
  employees_active: 0,
  employees_total: 0,
  groups: 0,
  shifts_active: 0,
  work_patterns: 0,
  holidays_year: 0,
  snapshots_month: 0,
  clock_in_today: 0,
  clock_out_today: 0,
})

const menus = [
  { to: '/admin/employees', icon: '👥', title: 'Karyawan', desc: 'Data karyawan & kode unik' },
  { to: '/admin/invite-codes', icon: '🔑', title: 'Kode Unik', desc: 'Generate & pantau kode link akun' },
  { to: '/admin/groups', icon: '🗂️', title: 'Group', desc: 'Kelompok & kepala group' },
  { to: '/admin/calendars', icon: '📅', title: 'Kalender Kerja', desc: 'Kalender tahunan & libur' },
  { to: '/admin/work-patterns', icon: '⏱️', title: 'Pola Kerja', desc: 'Jam kerja & istirahat' },
  { to: '/admin/shifts', icon: '🕐', title: 'Shift', desc: 'Jadwal jam masuk-pulang' },
  { to: '/admin/schedules', icon: '📋', title: 'Jadwal Karyawan', desc: 'Snapshot jadwal harian' },
  { to: '/admin/attendance', icon: '📊', title: 'Absensi Karyawan', desc: 'Rekap clock in/out per tanggal' },
  { to: '/admin/leave-requests', icon: '📝', title: 'Pengajuan Izin', desc: 'Approve izin/cuti/sakit' },
  { to: '/admin/overtime-requests', icon: '⏰', title: 'Pengajuan Lembur', desc: 'Approve lembur' },
  { to: '/admin/visits', icon: '📍', title: 'Kunjungan', desc: 'Kunjungan lapangan karyawan' },
  { to: '/admin/tasks', icon: '✅', title: 'Tugas Luar', desc: 'Beri & pantau tugas luar' },
  { to: '/admin/announcements', icon: '📢', title: 'Pengumuman', desc: 'Buat & kelola pengumuman' },
  { to: '/admin/reports', icon: '📈', title: 'Laporan', desc: 'Rekap & grafik kehadiran' },
  { to: '/admin/settings', icon: '⚙️', title: 'Pengaturan', desc: 'Konfigurasi tenant' },
  { to: '/admin/settings?tab=lokasi', icon: '📍', title: 'Lokasi Kerja', desc: 'Titik GPS & radius' },
]

const pendingLeave = ref(0)
const pendingOvertime = ref(0)
const pendingTotal = computed(() => pendingLeave.value + pendingOvertime.value)

onMounted(async () => {
  try {
    const data = await api<{ data: typeof stats.value }>('GET', '/admin/stats')
    stats.value = data?.data ?? stats.value
  } catch {
    // biarkan 0
  }
  try {
    const leave = await api<{ data: { pending: number } }>('GET', '/leave-requests/stats')
    pendingLeave.value = leave.data?.pending ?? 0
  } catch {
    pendingLeave.value = 0
  }
  try {
    const overtime = await api<{ data: { pending: number } }>('GET', '/overtime-requests/stats')
    pendingOvertime.value = overtime.data?.pending ?? 0
  } catch {
    pendingOvertime.value = 0
  }
  loading.value = false
})
</script>
