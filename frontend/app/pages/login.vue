<template>
  <div class="w-full max-w-sm">
    <div class="card p-6 md:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-bold text-white">
          A
        </div>
        <h1 class="text-lg font-semibold text-gray-900">Absensi Karyawan</h1>
        <p class="mt-1 text-sm text-gray-500">Masuk pakai nama dan PIN</p>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="label" for="name">Nama</label>
          <input
            id="name"
            v-model="name"
            type="text"
            class="input"
            placeholder="Nama lengkap"
            autocomplete="username"
            required
          />
        </div>

        <div class="mb-6">
          <label class="label" for="pin">PIN</label>
          <input
            id="pin"
            v-model="pin"
            type="password"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            class="input"
            placeholder="••••••"
            autocomplete="current-password"
            required
          />
        </div>

        <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          {{ loading ? 'Memeriksa…' : 'Masuk' }}
        </button>
      </form>

      <p class="mt-5 text-center text-xs text-gray-400">
        Lupa PIN? Hubungi admin.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default', middleware: 'guard' })

const route = useRoute()
const auth = useAuthStore()

const name = ref('')
const pin = ref('')
const loading = ref(false)
const error = ref('')

onMounted(() => {
  // Admin yang mendarat di /login dengan token SSO → teruskan ke /sso
  if (route.query.token) {
    navigateTo(`/sso?token=${encodeURIComponent(String(route.query.token))}`)
  }
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.loginEmployee(name.value.trim(), pin.value)
    await navigateTo('/clock')
  } catch (e: any) {
    error.value = e?.data?.message || 'Nama atau PIN salah.'
  } finally {
    loading.value = false
  }
}
</script>
