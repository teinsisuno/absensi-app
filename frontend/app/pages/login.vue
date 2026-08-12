<template>
  <div class="w-full max-w-sm">
    <div class="card p-6 md:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-7 w-7">
            <path
              d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
              stroke-linecap="round"
            />
            <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
          </svg>
        </div>
        <h1 class="text-lg font-semibold text-gray-900">Masuk Admin</h1>
        <p class="mt-1 text-sm text-gray-500">Login dengan akun portal untuk kelola absensi.</p>
      </div>

      <!-- Login email + password akun Central -->
      <form @submit.prevent="submitAdmin">
        <div class="mb-4">
          <label class="label" for="email">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            class="input"
            placeholder="email@contoh.com"
            autocomplete="email"
            required
          />
        </div>

        <div class="mb-6">
          <label class="label" for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            class="input"
            placeholder="••••••••"
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

      <!-- Login karyawan (PWA) — paling bawah -->
      <div class="mt-4 border-t border-gray-100 pt-4 text-center">
        <button type="button" class="text-xs text-gray-400 underline hover:text-gray-600" @click="navigateTo('/login-karyawan')">
          Login Karyawan
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

onMounted(() => {
  // User yang mendarat di /login dengan token SSO → teruskan ke /sso
  if (route.query.token) {
    navigateTo(`/sso?token=${encodeURIComponent(String(route.query.token))}`)
  }
})

async function submitAdmin() {
  error.value = ''
  loading.value = true
  try {
    await auth.loginAdmin(email.value.trim(), password.value)
    await navigateTo('/admin/employees')
  } catch (e: any) {
    error.value = e?.data?.message || 'Email atau password salah.'
  } finally {
    loading.value = false
  }
}
</script>
