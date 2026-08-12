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
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Clock-in Hari Ini</p>
          <p class="mt-1 text-2xl font-bold text-primary-600">{{ stats.clock_in_today }}</p>
        </div>
        <div class="card border-l-4 border-emerald-500 p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Clock-out Hari Ini</p>
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
  { to: '/admin/locations', icon: '📍', title: 'Lokasi Kerja', desc: 'Titik GPS & radius' },
]

onMounted(async () => {
  try {
    const data = await api<{ data: typeof stats.value }>('GET', '/admin/stats')
    stats.value = data?.data ?? stats.value
  } catch {
    // biarkan 0
  } finally {
    loading.value = false
  }
})
</script>
