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
          to="/admin/employees"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>👥</span> Karyawan
        </NuxtLink>
        <NuxtLink
          to="/admin/locations"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white"
          active-class="bg-gray-800 text-white"
        >
          <span>📍</span> Lokasi Kerja
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
