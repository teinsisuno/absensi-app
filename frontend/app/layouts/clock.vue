<template>
  <div class="mx-auto flex min-h-screen max-w-md flex-col bg-gray-50">
    <header class="sticky top-0 z-30 bg-primary-600 px-4 py-3 text-white">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-xl">🕐</span>
          <span class="font-semibold">Absensi</span>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <span class="hidden sm:inline">{{ now }}</span>
          <button
            v-if="auth.isLoggedIn"
            class="rounded-lg bg-white/20 px-2 py-1 text-xs hover:bg-white/30"
            @click="logout"
          >
            Keluar
          </button>
        </div>
      </div>
    </header>
    <main class="flex-1 p-4">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const now = ref('')

onMounted(() => {
  const update = () => {
    now.value = new Date().toLocaleString('id-ID', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }
  update()
  const t = setInterval(update, 30_000)
  onBeforeUnmount(() => clearInterval(t))
})

async function logout() {
  await auth.logout()
  await navigateTo('/login')
}
</script>
