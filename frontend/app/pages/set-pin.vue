<template>
  <div class="w-full max-w-sm">
    <div class="card p-6 md:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-bold text-white">
          🔒
        </div>
        <h1 class="text-lg font-semibold text-gray-900">Atur PIN</h1>
        <p class="mt-1 text-sm text-gray-500">PIN 4-6 digit dipakai buat login cepat setiap hari.</p>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-4">
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
            autocomplete="new-password"
            required
          />
        </div>

        <div class="mb-6">
          <label class="label" for="pin-confirm">Konfirmasi PIN</label>
          <input
            id="pin-confirm"
            v-model="pinConfirm"
            type="password"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            class="input text-center text-2xl tracking-[0.5em]"
            placeholder="••••"
            autocomplete="new-password"
            required
          />
        </div>

        <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          {{ loading ? 'Menyimpan…' : 'Simpan PIN' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

const auth = useAuthStore()
const pin = ref('')
const pinConfirm = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  if (!/^\d{4,6}$/.test(pin.value)) {
    error.value = 'PIN harus 4-6 digit angka.'
    return
  }
  if (pin.value !== pinConfirm.value) {
    error.value = 'PIN tidak sama. Cek lagi ya.'
    return
  }
  loading.value = true
  try {
    await auth.setPin(pin.value)
    // Lanjut ke pengaturan awal: kode unik + scan wajah
    await navigateTo('/setup')
  } catch (e: any) {
    error.value = e?.data?.message || 'Gagal menyimpan PIN, coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>
