<template>
  <div class="w-full max-w-sm">
    <div class="card p-8 text-center">
      <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-7 w-7">
          <path
            d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
            stroke-linecap="round"
          />
          <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
        </svg>
      </div>

      <div v-if="loading" class="py-6">
        <div class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent"></div>
        <p class="text-sm text-gray-500">Memproses login dari Central…</p>
      </div>

      <template v-else>
        <h1 class="text-lg font-semibold text-gray-900">Login Gagal</h1>
        <p class="mt-2 text-sm text-gray-500">{{ error }}</p>
        <NuxtLink to="/login" class="btn-primary mt-6 w-full">Masuk sebagai Karyawan</NuxtLink>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default', middleware: 'guard' })

const route = useRoute()
const auth = useAuthStore()

const loading = ref(true)
const error = ref('')

onMounted(async () => {
  const token = route.query.token as string | undefined

  if (!token) {
    error.value = 'Tidak ada token SSO. Buka aplikasi ini dari Dashboard Central.'
    loading.value = false
    return
  }

  try {
    await auth.loginSso(token)
    await navigateTo(auth.isAdmin ? '/admin/employees' : '/clock')
  } catch (e: any) {
    error.value = e?.data?.message || 'Token SSO tidak valid atau sudah kedaluwarsa.'
    loading.value = false
  }
})
</script>
