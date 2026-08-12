<template>
  <div class="w-full max-w-sm">
    <div class="card p-6 md:p-8">
      <div class="mb-6 text-center">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-2xl font-bold text-white">
          ⚙️
        </div>
        <h1 class="text-lg font-semibold text-gray-900">Pengaturan Awal</h1>
        <p class="mt-1 text-sm text-gray-500">Hubungkan akunmu dengan data karyawan pakai kode unik dari HR.</p>
      </div>

      <!-- Step 1: kode unik -->
      <div class="mb-4">
        <label class="label" for="code">Kode Unik</label>
        <input
          id="code"
          v-model="code"
          type="text"
          class="input uppercase tracking-widest"
          placeholder="XXXX-XXXX"
          maxlength="16"
          autocomplete="off"
          @input="onCodeInput"
        />

        <!-- Nama karyawan muncul otomatis saat kode valid -->
        <transition name="fade">
          <div v-if="checking" class="mt-3 flex items-center gap-2 text-sm text-gray-500">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-primary-600 border-t-transparent"></span>
            Memeriksa kode…
          </div>
          <div v-else-if="verifiedEmployee" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3">
            <p class="text-xs font-medium text-emerald-600">✓ Kode valid</p>
            <p class="mt-0.5 text-sm font-semibold text-gray-900">{{ verifiedEmployee.name }}</p>
            <p v-if="verifiedEmployee.position" class="text-xs text-gray-500">{{ verifiedEmployee.position }}</p>
          </div>
          <div v-else-if="codeError" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
            {{ codeError }}
          </div>
        </transition>
      </div>

      <!-- Step 2: scan wajah -->
      <div class="mb-6">
        <p class="label">Verifikasi Wajah</p>
        <button
          type="button"
          class="w-full rounded-xl border-2 border-dashed py-4 text-center transition"
          :class="auth.faceDone ? 'border-emerald-300 bg-emerald-50' : 'border-primary-300 bg-primary-50 hover:bg-primary-100'"
          @click="goScanFace"
        >
          <span class="block text-2xl">{{ auth.faceDone ? '✅' : '📷' }}</span>
          <span class="mt-1 block text-sm font-medium" :class="auth.faceDone ? 'text-emerald-700' : 'text-primary-700'">
            {{ auth.faceDone ? 'Wajah sudah discan' : 'Scan Wajah' }}
          </span>
          <span class="mt-0.5 block text-xs text-gray-500">
            {{ auth.faceDone ? 'Klik untuk scan ulang' : 'Ambil video wajah untuk verifikasi' }}
          </span>
        </button>
      </div>

        <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <button
          type="button"
          class="btn-primary w-full"
          :disabled="!canSave || loading"
          @click="save"
        >
          <span v-if="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          {{ loading ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <p v-if="!canSave" class="mt-3 text-center text-xs text-gray-400">
          Lengkapi kode unik valid dan scan wajah dulu ya.
        </p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

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
