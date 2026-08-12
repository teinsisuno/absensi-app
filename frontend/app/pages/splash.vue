<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-primary-600 to-primary-800">
    <div class="relative flex h-24 w-24 items-center justify-center">
      <!-- Loading circle mengelilingi logo -->
      <div class="absolute inset-0 animate-spin rounded-full border-4 border-white/20 border-t-white"></div>
      <span class="text-5xl">🕐</span>
    </div>
    <p class="mt-8 text-lg font-semibold text-white">Absensi</p>
    <p class="mt-1 text-sm text-primary-100">megakomsel.com</p>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()

onMounted(() => {
  auth.restore()
  // Splash 1-1.5 detik, lalu arahkan sesuai kondisi
  setTimeout(() => {
    if (auth.isLoggedIn) {
      navigateTo(auth.isAdmin ? '/admin/employees' : auth.isEmployee ? '/dashboard' : '/setup')
    } else {
      navigateTo(auth.hasAccount ? '/login' : '/register')
    }
  }, 1400)
})
</script>
