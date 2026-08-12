<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="rounded-b-[2rem] bg-primary-800 px-6 pb-8 pt-12">
      <h1 class="text-2xl font-bold text-white">Tautkan Akun</h1>
      <p class="mt-1 text-sm text-primary-200/70">Masukkan kode unik dari HR</p>
    </div>

    <div class="px-6 py-6">
      <!-- Step 1: kode unik -->
      <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <label class="mb-3 block text-sm font-medium text-gray-600">Kode Unik Karyawan</label>
        <div class="flex gap-2">
          <input
            id="code"
            v-model="code"
            type="text"
            placeholder="ABCD1234"
            maxlength="16"
            class="w-full flex-1 rounded-xl border-2 border-gray-200 bg-gray-50 px-4 py-3.5 text-center text-lg font-bold uppercase tracking-widest transition focus:border-primary-500 focus:bg-white focus:outline-none"
            autocomplete="off"
            @input="onCodeInput"
          />
          <button
            type="button"
            class="rounded-xl bg-primary-600 px-5 font-medium text-white transition hover:bg-primary-700 active:scale-95"
            @click="checkCode(code.trim())"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="h-5 w-5">
              <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </div>
        <p class="mt-2 text-xs text-gray-400">Tanyakan kode ini ke bagian HR Anda</p>
      </div>

      <!-- Preview karyawan saat kode valid -->
      <transition name="fade">
        <div v-if="checking" class="mb-6 rounded-2xl border border-gray-100 bg-white p-6 text-sm text-gray-500 shadow-sm">
          <span class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary-600 border-t-transparent align-middle"></span>
          Memeriksa kode…
        </div>
        <div v-else-if="verifiedEmployee" class="mb-6 rounded-2xl border border-primary-200 bg-white p-6 shadow-sm">
          <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-600/10">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6 text-primary-600">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 21a8 8 0 0 1 16 0" />
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="truncate font-bold text-gray-800">{{ verifiedEmployee.name }}</h3>
              <p class="truncate text-sm text-gray-500">{{ verifiedEmployee.position || '—' }}</p>
            </div>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6 shrink-0 text-emerald-500">
              <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
        </div>
        <div v-else-if="codeError" class="mb-6 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-600">{{ codeError }}</div>
      </transition>

      <!-- Step 2: scan wajah -->
      <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <p class="mb-3 text-sm font-medium text-gray-600">Verifikasi Wajah</p>
        <button
          type="button"
          class="w-full rounded-2xl border-2 border-dashed py-5 text-center transition"
          :class="auth.faceDone ? 'border-emerald-300 bg-emerald-50' : 'border-primary-300 bg-primary-50 hover:bg-primary-100'"
          @click="goScanFace"
        >
          <svg v-if="auth.faceDone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mx-auto mb-1 h-8 w-8 text-emerald-500">
            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="mx-auto mb-1 h-8 w-8 text-primary-600">
            <path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2" stroke-linecap="round" />
            <circle cx="12" cy="10" r="3" />
            <path d="M7.5 17a4.5 4.5 0 0 1 9 0" stroke-linecap="round" />
          </svg>
          <span class="mt-1 block text-sm font-medium" :class="auth.faceDone ? 'text-emerald-700' : 'text-primary-700'">
            {{ auth.faceDone ? 'Wajah sudah discan' : 'Scan Wajah' }}
          </span>
          <span class="mt-0.5 block text-xs text-gray-500">
            {{ auth.faceDone ? 'Klik untuk scan ulang' : 'Posisikan wajah di dalam lingkaran' }}
          </span>
        </button>
      </div>

      <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-3 py-2.5 text-sm text-red-600">{{ error }}</p>

      <button
        type="button"
        class="w-full rounded-xl py-4 font-semibold transition"
        :class="canSave ? 'bg-primary-600 text-white shadow-lg shadow-primary-600/25 hover:bg-primary-700 active:scale-[0.98]' : 'bg-gray-200 text-gray-400'"
        :disabled="!canSave || loading"
        @click="save"
      >
        <span v-if="loading" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle"></span>
        {{ loading ? 'Menyimpan…' : 'Lanjutkan ke Dashboard' }}
      </button>
      <p v-if="!canSave" class="mt-3 text-center text-xs text-gray-400">
        Lengkapi kode unik valid dan scan wajah dulu ya.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
// State kode & hasil verifikasi disimpan di store (persist localStorage) —
// biar survive saat pindah ke /setup/face lalu kembali ke sini.
const code = computed({
  get: () => auth.setupCode,
  set: (v: string) => {
    auth.setupCode = v
    auth.persist()
  },
})
const verifiedEmployee = computed({
  get: () => auth.setupVerified,
  set: (v) => {
    auth.setupVerified = v
    auth.persist()
  },
})
const checking = ref(false)
const codeError = ref('')
const error = ref('')
const loading = ref(false)
let debounceTimer: ReturnType<typeof setTimeout> | null = null

const canSave = computed(() => !!verifiedEmployee.value && auth.faceDone)

function onCodeInput() {
  codeError.value = ''
  // Saat kode diubah setelah valid → reset employee biar nggak salah
  if (verifiedEmployee.value) verifiedEmployee.value = null
  if (debounceTimer) clearTimeout(debounceTimer)
  const trimmed = code.value.trim()
  if (trimmed.length < 6) return
  debounceTimer = setTimeout(() => checkCode(trimmed), 600)
}

async function checkCode(trimmed: string) {
  if (!trimmed || trimmed.length < 6) return
  checking.value = true
  codeError.value = ''
  try {
    const data = await auth.verifyInvite(trimmed)
    verifiedEmployee.value = data.employee
  } catch (e: any) {
    verifiedEmployee.value = null
    codeError.value = e?.data?.message || 'Kode tidak valid.'
  } finally {
    checking.value = false
  }
}

function goScanFace() {
  navigateTo('/setup/face')
}

async function save() {
  if (!verifiedEmployee.value) return
  error.value = ''
  loading.value = true
  try {
    await auth.linkEmployee(code.value.trim())
    auth.clearSetup()
    await navigateTo('/dashboard')
  } catch (e: any) {
    error.value = e?.data?.message || 'Gagal menyimpan, coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
