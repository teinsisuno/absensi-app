<template>
  <AppModal title="Aktifkan Login Sidik Jari" @close="$emit('close')">
    <div class="text-center">
      <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-teal-50">
        <svg viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="1.6" class="h-9 w-9">
          <path
            d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
            stroke-linecap="round"
          />
          <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
        </svg>
      </div>
      <h3 class="mb-2 text-lg font-semibold text-gray-900">Login lebih cepat dengan sidik jari</h3>
      <p class="mb-1 text-sm text-gray-500">
        Setelah diaktifkan, kamu cukup sentuh sidik jari / Face ID untuk masuk — tanpa ketik email & PIN.
      </p>
      <p class="mb-5 text-xs text-gray-400">
        Sidik jari tidak pernah disimpan. Data biometrik hanya dicek oleh sistem HP kamu (WebAuthn).
      </p>

      <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

      <button
        type="button"
        class="w-full rounded-xl bg-teal-600 py-3 font-semibold text-white shadow-lg transition hover:bg-teal-700 active:scale-[0.98]"
        :disabled="busy"
        @click="activate"
      >
        <span v-if="busy" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle"></span>
        {{ busy ? 'Meminta sidik jari…' : 'Aktifkan Sekarang' }}
      </button>
      <button
        type="button"
        class="mt-2 w-full rounded-xl py-3 text-sm font-medium text-gray-500 transition hover:bg-gray-50"
        :disabled="busy"
        @click="$emit('close')"
      >
        Nanti saja
      </button>
    </div>
  </AppModal>
</template>

<script setup lang="ts">
const emit = defineEmits<{ close: []; done: [] }>()

const auth = useAuthStore()

const busy = ref(false)
const error = ref('')

async function activate() {
  busy.value = true
  error.value = ''
  try {
    // 1. Ambil creation options (wajib auth — token dari login PIN)
    const options = await $fetch<any>('/auth/webauthn/register/options', {
      baseURL: apiBase(),
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
    })

    // 2. Browser minta sidik jari / Face ID → buat credential
    const credential: any = await navigator.credentials.create({
      publicKey: toCreationOptions(options),
    })
    if (!credential) throw new Error('Pendaftaran dibatalkan.')

    // 3. Kirim credential ke server (simpan kunci publik)
    await auth.webauthnRegister(serializeCredential(credential), 'Fingerprint')

    emit('done')
  } catch (e: any) {
    if (e?.name === 'NotAllowedError') {
      error.value = 'Pendaftaran dibatalkan. Kamu bisa coba lagi nanti.'
    } else {
      error.value = e?.data?.message || e?.message || 'Gagal mengaktifkan biometrik. Coba lagi.'
    }
  } finally {
    busy.value = false
  }
}
</script>
