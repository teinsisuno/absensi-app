<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-primary-800 via-primary-600 to-teal-600">
    <div class="animate-scale-in flex flex-col items-center">
      <div class="mb-6 flex h-24 w-24 items-center justify-center rounded-3xl bg-white/10 shadow-2xl backdrop-blur-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.6" class="h-12 w-12">
          <path
            d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
            stroke-linecap="round"
          />
          <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
        </svg>
      </div>
      <h1 class="text-3xl font-bold tracking-tight text-white">Absensi</h1>
      <p class="mt-2 text-sm font-medium text-primary-200/80">megakomsel.com</p>
    </div>
    <div class="absolute bottom-12">
      <div class="h-8 w-8 animate-spin rounded-full border-[3px] border-white/20 border-t-white"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()

onMounted(() => {
  auth.restore()
  // Splash 1.4 detik, lalu arahkan sesuai kondisi
  setTimeout(() => {
    if (auth.isLoggedIn) {
      navigateTo(auth.isAdmin ? '/admin/employees' : auth.isEmployee ? '/dashboard' : '/setup')
    } else {
      navigateTo(auth.hasAccount ? '/login-karyawan' : '/register')
    }
  }, 1400)
})
</script>

<style scoped>
.animate-scale-in {
  animation: scaleIn 0.5s ease-out forwards;
}
@keyframes scaleIn {
  0% {
    transform: scale(0.5);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
