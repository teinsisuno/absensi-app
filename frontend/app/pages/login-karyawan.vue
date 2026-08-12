<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-800 via-primary-600 to-teal-600">
    <div class="flex min-h-screen flex-col">
      <div class="flex flex-1 flex-col items-center justify-center px-6 pb-6">
        <div class="mb-8 flex h-20 w-20 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.6" class="h-10 w-10">
            <path
              d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
              stroke-linecap="round"
            />
            <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
          </svg>
        </div>
        <h2 class="mb-1 text-2xl font-bold text-white">Selamat Datang</h2>
        <p class="mb-8 text-sm text-primary-200/80">Masuk untuk mencatat kehadiran</p>

        <!-- Tab PIN / Email -->
        <div class="mb-6 flex w-full max-w-xs rounded-xl bg-white/10 p-1 backdrop-blur-sm">
          <button
            type="button"
            class="flex-1 rounded-lg py-2.5 text-sm font-medium transition"
            :class="mode === 'pin' ? 'bg-white/20 text-white shadow-sm' : 'text-white/60'"
            @click="mode = 'pin'"
          >
            PIN
          </button>
          <button
            type="button"
            class="flex-1 rounded-lg py-2.5 text-sm font-medium transition"
            :class="mode === 'email' ? 'bg-white/20 text-white shadow-sm' : 'text-white/60'"
            @click="mode = 'email'"
          >
            Email
          </button>
        </div>

        <!-- Panel PIN -->
        <div v-if="mode === 'pin'" class="w-full max-w-xs">
          <p class="mb-3 text-center text-sm text-white/70">Masukkan email & PIN 6 digit Anda</p>
          <input
            v-model="email"
            type="email"
            inputmode="email"
            placeholder="nama@perusahaan.com"
            class="mb-4 w-full rounded-xl border border-white/10 bg-white/10 px-4 py-2.5 text-sm text-white placeholder-white/40 backdrop-blur-sm transition focus:border-white/30 focus:bg-white/15 focus:outline-none"
            autocomplete="email"
          />
          <div class="mb-6 flex justify-center gap-4">
            <div
              v-for="i in 6"
              :key="i"
              class="h-3.5 w-3.5 rounded-full border-2 transition-all"
              :class="i <= pin.length ? 'border-primary-300 bg-primary-300' : 'border-white/40'"
            ></div>
          </div>

          <div class="mx-auto grid max-w-xs grid-cols-3 gap-3">
            <button
              v-for="n in [1, 2, 3, 4, 5, 6, 7, 8, 9]"
              :key="n"
              type="button"
              class="h-16 rounded-2xl bg-white/10 text-xl font-semibold text-white transition hover:bg-white/20 active:scale-95"
              @click="enterPin(String(n))"
            >
              {{ n }}
            </button>
            <button
              type="button"
              class="flex h-16 items-center justify-center rounded-2xl bg-white/5 text-sm font-medium text-white/60 transition hover:bg-white/10 active:scale-95"
              @click="clearPin"
            >
              ✕
            </button>
            <button
              type="button"
              class="h-16 rounded-2xl bg-white/10 text-xl font-semibold text-white transition hover:bg-white/20 active:scale-95"
              @click="enterPin('0')"
            >
              0
            </button>
            <button
              type="button"
              class="flex h-16 items-center justify-center rounded-2xl bg-white/5 text-lg text-white/60 transition hover:bg-white/10 active:scale-95"
              @click="backspacePin"
            >
              ⌫
            </button>
          </div>

          <p v-if="error" class="mt-4 rounded-xl bg-red-500/20 px-3 py-2 text-center text-sm text-red-100">
            {{ error }}
          </p>
        </div>

        <!-- Panel Email -->
        <div v-else class="w-full max-w-xs space-y-4">
          <div>
            <label class="mb-1.5 ml-1 block text-xs font-medium text-white/70">Email</label>
            <input
              v-model="email"
              type="email"
              placeholder="nama@perusahaan.com"
              class="w-full rounded-xl border border-white/10 bg-white/10 px-4 py-3.5 text-white placeholder-white/40 backdrop-blur-sm transition focus:border-white/30 focus:bg-white/15 focus:outline-none"
              autocomplete="email"
            />
          </div>
          <div>
            <label class="mb-1.5 ml-1 block text-xs font-medium text-white/70">Password</label>
            <input
              v-model="password"
              type="password"
              placeholder="********"
              class="w-full rounded-xl border border-white/10 bg-white/10 px-4 py-3.5 text-white placeholder-white/40 backdrop-blur-sm transition focus:border-white/30 focus:bg-white/15 focus:outline-none"
              autocomplete="current-password"
              @keyup.enter="submitEmail"
            />
          </div>
          <p v-if="error" class="rounded-xl bg-red-500/20 px-3 py-2 text-center text-sm text-red-100">
            {{ error }}
          </p>
          <button
            type="button"
            class="mt-2 w-full rounded-xl bg-white py-3.5 font-semibold text-primary-700 shadow-lg transition hover:shadow-xl active:scale-[0.98]"
            :disabled="loading"
            @click="submitEmail"
          >
            <span v-if="loading" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary-600 border-t-transparent align-middle"></span>
            Masuk
          </button>
        </div>
      </div>

      <div class="pb-8 text-center">
        <p class="text-xs text-white/50">
          Belum punya akun?
          <NuxtLink to="/register" class="font-medium text-white underline underline-offset-2">Daftar</NuxtLink>
        </p>
        <button type="button" class="mt-2 text-xs text-white/40 underline underline-offset-2 hover:text-white/60" @click="navigateTo('/login')">
          Masuk sebagai Admin
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()

const mode = ref<'pin' | 'email'>('pin')
const email = ref('')
const pin = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

/** Setelah login sukses → arahkan sesuai kondisi akun. */
function goHome() {
  if (auth.isAdmin) return navigateTo('/admin/employees')
  if (auth.isEmployee) return navigateTo('/dashboard')
  return navigateTo('/setup') // sudah punya akun tapi belum link kode unik
}

function enterPin(n: string) {
  if (pin.value.length >= 6) return
  pin.value += n
  if (pin.value.length === 6) {
    setTimeout(() => submitPin(), 200)
  }
}

function backspacePin() {
  pin.value = pin.value.slice(0, -1)
}

function clearPin() {
  pin.value = ''
}

async function submitPin() {
  error.value = ''
  if (!email.value.trim()) {
    error.value = 'Masukkan email dulu ya.'
    pin.value = ''
    return
  }
  loading.value = true
  try {
    await auth.pinLogin(email.value.trim(), pin.value)
    await goHome()
  } catch (e: any) {
    error.value = e?.data?.message || 'Email atau PIN salah.'
    pin.value = ''
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
</script>
