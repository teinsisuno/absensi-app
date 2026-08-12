<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="rounded-b-[2rem] bg-primary-800 px-6 pb-8 pt-12">
      <h1 class="text-2xl font-bold text-white">Atur PIN</h1>
      <p class="mt-1 text-sm text-primary-200/70">PIN untuk login cepat setiap hari</p>
    </div>

    <div class="px-6 py-8">
      <p class="mb-6 text-center text-sm text-gray-500">
        {{ step === 1 ? 'Buat PIN 6 digit untuk login cepat' : 'Ulangi PIN yang sama untuk konfirmasi' }}
      </p>

      <!-- Dots PIN -->
      <div class="mb-8 flex justify-center gap-3">
        <div
          v-for="i in 6"
          :key="i"
          class="h-4 w-4 rounded-full border-2 transition-all"
          :class="i <= pin.length ? 'border-primary-500 bg-primary-500' : 'border-primary-500'"
        ></div>
      </div>

      <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-center text-sm text-red-600">{{ error }}</p>

      <!-- Keypad -->
      <div class="mx-auto grid max-w-xs grid-cols-3 gap-3">
        <button
          v-for="n in [1, 2, 3, 4, 5, 6, 7, 8, 9]"
          :key="n"
          type="button"
          class="h-16 rounded-2xl border border-gray-200 bg-white text-xl font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95"
          @click="enter(String(n))"
        >
          {{ n }}
        </button>
        <button
          type="button"
          class="h-16 rounded-2xl bg-gray-100 text-sm font-medium text-gray-400 transition hover:bg-gray-200 active:scale-95"
          @click="clearPin"
        >
          ✕
        </button>
        <button
          type="button"
          class="h-16 rounded-2xl border border-gray-200 bg-white text-xl font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95"
          @click="enter('0')"
        >
          0
        </button>
        <button
          type="button"
          class="h-16 rounded-2xl bg-gray-100 text-lg text-gray-400 transition hover:bg-gray-200 active:scale-95"
          @click="backspace"
        >
          ⌫
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const step = ref<1 | 2>(1)
const pin = ref('')
const pinConfirm = ref('')
const error = ref('')

function enter(n: string) {
  error.value = ''
  if (pin.value.length >= 6) return
  pin.value += n
  if (pin.value.length === 6) {
    setTimeout(() => {
      if (step.value === 1) {
        // Lanjut konfirmasi
        pinConfirm.value = pin.value
        pin.value = ''
        step.value = 2
      } else {
        if (pin.value === pinConfirm.value) {
          submit()
        } else {
          error.value = 'PIN tidak sama. Ulangi dari awal ya.'
          pin.value = ''
          pinConfirm.value = ''
          step.value = 1
        }
      }
    }, 250)
  }
}

function backspace() {
  pin.value = pin.value.slice(0, -1)
}

function clearPin() {
  pin.value = ''
}

async function submit() {
  error.value = ''
  try {
    await auth.setPin(pin.value)
    // Lanjut ke pengaturan awal: kode unik + scan wajah
    await navigateTo('/setup')
  } catch (e: any) {
    error.value = e?.data?.message || 'Gagal menyimpan PIN, coba lagi.'
    pin.value = ''
    pinConfirm.value = ''
    step.value = 1
  }
}
</script>
