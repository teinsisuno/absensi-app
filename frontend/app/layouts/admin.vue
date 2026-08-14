<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col bg-gray-900 md:flex">
      <div class="flex h-16 items-center gap-2 px-5">
        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 font-bold text-white">A</div>
        <span class="text-lg font-semibold text-white">Absensi Admin</span>
      </div>
      <nav class="flex-1 space-y-1 px-3 py-4">
        <NuxtLink
          to="/admin"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
          exact-active-class="bg-gray-800 text-white"
        >
          <span>📊</span> Dashboard
        </NuxtLink>
        <NuxtLink
          to="/admin/employees"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>👥</span> Karyawan
        </NuxtLink>
        <NuxtLink
          to="/admin/invite-codes"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>🔑</span> Kode Unik
        </NuxtLink>
        <NuxtLink
          to="/admin/groups"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>🗂️</span> Group
        </NuxtLink>
        <NuxtLink
          to="/admin/calendars"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📅</span> Kalender Kerja
        </NuxtLink>
        <NuxtLink
          to="/admin/work-patterns"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>⏱️</span> Pola Kerja
        </NuxtLink>
        <NuxtLink
          to="/admin/shifts"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>🕐</span> Shift
        </NuxtLink>
        <NuxtLink
          to="/admin/schedules"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📋</span> Jadwal Karyawan
        </NuxtLink>
        <NuxtLink
          to="/admin/attendance"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📊</span> Absensi Karyawan
        </NuxtLink>
        <NuxtLink
          to="/admin/leave-requests"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📝</span> Pengajuan Izin
        </NuxtLink>
        <NuxtLink
          to="/admin/overtime-requests"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>⏰</span> Pengajuan Lembur
        </NuxtLink>
        <NuxtLink
          to="/admin/visits"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📍</span> Kunjungan
        </NuxtLink>
        <NuxtLink
          to="/admin/tasks"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>✅</span> Tugas Luar
        </NuxtLink>
        <NuxtLink
          to="/admin/announcements"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📢</span> Pengumuman
        </NuxtLink>
        <NuxtLink
          to="/admin/reports"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📈</span> Laporan
        </NuxtLink>
        <NuxtLink
          to="/admin/settings"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>⚙️</span> Pengaturan
        </NuxtLink>
      </nav>
      <div class="border-t border-gray-800 p-4">
        <button class="btn-secondary w-full !bg-gray-800 !text-gray-200 ring-gray-700 hover:!bg-gray-700" @click="logout">
          Keluar
        </button>
      </div>
    </aside>

    <!-- Topbar mobile -->
    <header class="sticky top-0 z-30 flex h-14 items-center justify-between bg-gray-900 px-4 md:hidden">
      <span class="font-semibold text-white">Absensi Admin</span>
      <button class="btn-secondary !bg-gray-800 !py-1.5 !text-xs !text-gray-200 ring-gray-700 hover:!bg-gray-700" @click="logout">
        Keluar
      </button>
    </header>

    <main class="md:pl-60">
      <div class="mx-auto max-w-5xl p-4 md:p-8">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()

async function logout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>
