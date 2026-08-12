<template>
  <div class="w-full max-w-sm">
    <div class="card p-6 md:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-bold text-white">
          🕐
        </div>
        <h1 class="text-lg font-semibold text-gray-900">Daftar Akun</h1>
        <p class="mt-1 text-sm text-gray-500">Buat akun dengan email, nama, dan password.</p>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="label" for="name">Nama Lengkap</label>
          <input
            id="name"
            v-model="name"
            type="text"
            class="input"
            placeholder="Nama kamu"
            autocomplete="name"
            required
          />
        </div>

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
            placeholder="Minimal 8 karakter"
            autocomplete="new-password"
            minlength="8"
            required
          />
        </div>

        <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          {{ loading ? 'Mendaftar…' : 'Daftar' }}
        </button>
      </form>

      <p class="mt-5 text-center text-xs text-gray-400">
        Sudah punya akun?
        <NuxtLink to="/login" class="font-medium text-primary-600 hover:underline">Masuk</NuxtLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

const auth = useAuthStore()
const name = ref('')
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
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
