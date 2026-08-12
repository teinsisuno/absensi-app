<template>
  <div class="w-full max-w-sm">
    <div class="card p-6 md:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-bold text-white">
          🕐
        </div>
        <h1 class="text-lg font-semibold text-gray-900">Masuk</h1>
        <p class="mt-1 text-sm text-gray-500">Login cepat pakai PIN, atau email dan password.</p>
      </div>

      <!-- Pilih metode login -->
      <div class="mb-5 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 text-sm">
        <button
          type="button"
          class="rounded-lg py-2 font-medium transition"
          :class="mode === 'pin' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
          @click="mode = 'pin'"
        >
          PIN Cepat
        </button>
        <button
          type="button"
          class="rounded-lg py-2 font-medium transition"
          :class="mode === 'email' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
          @click="mode = 'email'"
        >
          Email & Password
        </button>
      </div>

      <!-- Login PIN cepat: email + PIN -->
      <form v-if="mode === 'pin'" @submit.prevent="submitPin">
        <div class="mb-4">
          <label class="label" for="pin-email">Email</label>
          <input
            id="pin-email"
            v-model="email"
            type="email"
            class="input"
            placeholder="email@contoh.com"
            autocomplete="email"
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
            class="input text-center text-2xl tracking-[0.5em]"
            placeholder="••••"
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

      <!-- Login email + password -->
      <form v-else @submit.prevent="submitEmail">
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

      <p class="mt-5 text-center text-xs text-gray-400">
        Belum punya akun?
        <NuxtLink to="/register" class="font-medium text-primary-600 hover:underline">Daftar sekarang</NuxtLink>
      </p>

      <!-- Akses Owner/Admin (HR) — mode kecil -->
      <div class="mt-4 border-t border-gray-100 pt-4 text-center">
        <button type="button" class="text-xs text-gray-400 underline hover:text-gray-600" @click="adminMode = !adminMode">
          {{ adminMode ? 'Tutup' : 'Masuk sebagai Owner/Admin' }}
        </button>
        <form v-if="adminMode" class="mt-3 space-y-3 text-left" @submit.prevent="submitAdmin">
          <input
            v-model="adminEmail"
            type="email"
            class="input"
            placeholder="Email admin"
            autocomplete="email"
            required
          />
          <input
            v-model="adminPassword"
            type="password"
            class="input"
            placeholder="Password"
            autocomplete="current-password"
            required
          />
          <button type="submit" class="w-full rounded-lg border border-gray-300 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" :disabled="loading">
            {{ loading ? 'Memeriksa…' : 'Masuk Admin' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route = useRoute()
const auth = useAuthStore()

const mode = ref<'pin' | 'email'>('pin')
const email = ref('')
const pin = ref('')
const password = ref('')
const adminMode = ref(false)
const adminEmail = ref('')
const adminPassword = ref('')
const loading = ref(false)
const error = ref('')

onMounted(() => {
  // User yang mendarat di /login dengan token SSO → teruskan ke /sso
  if (route.query.token) {
    navigateTo(`/sso?token=${encodeURIComponent(String(route.query.token))}`)
  }
})

/** Setelah login sukses → arahkan sesuai kondisi akun. */
function goHome() {
  if (auth.isAdmin) return navigateTo('/admin/employees')
  if (auth.isEmployee) return navigateTo('/dashboard')
  return navigateTo('/setup') // sudah punya akun tapi belum link kode unik
}

async function submitPin() {
  error.value = ''
  loading.value = true
  try {
    await auth.pinLogin(email.value.trim(), pin.value)
    await goHome()
  } catch (e: any) {
    error.value = e?.data?.message || 'Email atau PIN salah.'
  } finally {
    loading.value = false
  }
}

async function submitEmail() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    await goHome()
  } catch (e: any) {
    error.value = e?.data?.message || 'Email atau password salah.'
  } finally {
    loading.value = false
  }
}

async function submitAdmin() {
  error.value = ''
  loading.value = true
  try {
    await auth.loginAdmin(adminEmail.value.trim(), adminPassword.value)
    await navigateTo('/admin/employees')
  } catch (e: any) {
    error.value = e?.data?.message || 'Email atau password salah.'
  } finally {
    loading.value = false
  }
}
</script>
