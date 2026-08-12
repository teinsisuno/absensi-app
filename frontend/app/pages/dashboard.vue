<template>
  <div class="mx-auto w-full max-w-md">
    <!-- Header -->
    <div class="rounded-2xl bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
      <p class="text-sm text-primary-100">Selamat datang 👋</p>
      <h1 class="mt-1 text-xl font-bold">{{ auth.employee?.name || auth.user?.name || 'Karyawan' }}</h1>
      <p v-if="auth.employee?.position" class="text-sm text-primary-100">{{ auth.employee.position }}</p>

      <div class="mt-5 flex items-center justify-between rounded-xl bg-white/10 p-4">
        <div>
          <p class="text-xs text-primary-100">Hari ini</p>
          <p class="text-lg font-semibold">{{ todayLabel }}</p>
        </div>
        <NuxtLink to="/clock" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">
          Absen Sekarang
        </NuxtLink>
      </div>
    </div>

    <!-- Menu -->
    <p class="mt-6 mb-3 text-sm font-semibold text-gray-700">Menu Saya</p>
    <div class="grid grid-cols-2 gap-3">
      <NuxtLink
        v-for="item in menus"
        :key="item.label"
        :to="item.to"
        class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm"
      >
        <span class="text-2xl">{{ item.icon }}</span>
        <p class="mt-2 text-sm font-medium text-gray-800">{{ item.label }}</p>
        <span
          class="mt-1 inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold"
          :class="item.available ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'"
        >
          {{ item.available ? 'Buka' : 'Segera' }}
        </span>
      </NuxtLink>
    </div>

    <button class="mt-6 w-full text-center text-xs text-gray-400 hover:text-red-500" @click="logout">
      Keluar
    </button>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'clock' })

const auth = useAuthStore()

const todayLabel = new Date().toLocaleDateString('id-ID', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

const menus = [
  { icon: '🕐', label: 'Absen Masuk / Pulang', to: '/clock', available: true },
  { icon: '📋', label: 'Riwayat Absensi', to: '/attendance', available: false },
  { icon: '📅', label: 'Jadwal Kerja', to: '/schedule', available: false },
  { icon: '🏖️', label: 'Pengajuan Cuti / Izin', to: '/leave/request', available: false },
  { icon: '🌙', label: 'Pengajuan Lembur', to: '/overtime/request', available: false },
  { icon: '📍', label: 'Kunjungan', to: '/visits', available: false },
  { icon: '📢', label: 'Pengumuman', to: '/announcements', available: false },
  { icon: '✅', label: 'Tugas', to: '/tasks', available: false },
  { icon: '👤', label: 'Biodata & Dokumen', to: '/profile', available: false },
]

async function logout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>
