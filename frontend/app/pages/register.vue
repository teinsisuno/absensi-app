<template>
  <div class="mx-auto min-h-screen w-full max-w-md bg-gray-50">
    <!-- Header -->
    <div class="rounded-b-[2rem] bg-primary-800 px-6 pb-8 pt-12 shadow-lg">
      <NuxtLink to="/login-karyawan" class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white/70 transition hover:bg-white/20 hover:text-white">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
          <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </NuxtLink>
      <h1 class="text-2xl font-bold text-white">Buat Akun</h1>
      <p class="mt-1 text-sm text-primary-200/70">Daftar untuk mulai menggunakan Absensi</p>
    </div>

    <!-- Form -->
    <div class="space-y-5 px-6 py-6">
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-600">Nama Lengkap</label>
        <input
          id="name"
          v-model="name"
          type="text"
          placeholder="Budi Santoso"
          class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-gray-800 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
          autocomplete="name"
          required
        />
      </div>
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-600">Email</label>
        <input
          id="email"
          v-model="email"
          type="email"
          placeholder="budi@perusahaan.com"
          class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-gray-800 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
          autocomplete="email"
          required
        />
      </div>
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-600">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          placeholder="Minimal 8 karakter"
          class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-gray-800 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
          autocomplete="new-password"
          minlength="8"
          required
        />
      </div>
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-600">Konfirmasi Password</label>
        <input
          id="passwordConfirm"
          v-model="passwordConfirm"
          type="password"
          placeholder="Ulangi password"
          class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-gray-800 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
          autocomplete="new-password"
          minlength="8"
          required
        />
      </div>

      <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2.5 text-sm text-red-600">{{ error }}</p>

      <button
        type="button"
        class="mt-4 w-full rounded-xl bg-primary-600 py-4 font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:bg-primary-700 active:scale-[0.98]"
        :disabled="loading"
        @click="submit"
      >
        <span v-if="loading" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle"></span>
        {{ loading ? 'Mendaftar…' : 'Lanjutkan' }}
      </button>
      <p class="mt-4 text-center text-xs text-gray-400">Dengan mendaftar, Anda menyetujui Syarat & Ketentuan</p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  if (password.value !== passwordConfirm.value) {
    error.value = 'Konfirmasi password tidak sama.'
    return
  }
  loading.value = true
  try {
    await auth.register(name.value.trim(), email.value.trim(), password.value)
    // Setelah register → langsung set PIN
    await navigateTo('/set-pin')
  } catch (e: any) {
    error.value = e?.data?.message || 'Registrasi gagal, coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>
