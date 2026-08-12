<template>
  <div>
    <!-- Header teal -->
    <div class="rounded-b-[2rem] bg-primary-800 px-6 pb-8 pt-12">
      <div class="mb-6 flex items-center gap-4">
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
          @click="navigateTo('/dashboard')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="text-xl font-bold text-white">Profil</h1>
      </div>
      <div class="flex items-center gap-4">
        <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-2 border-white/20 bg-white/10">
          <img v-if="auth.employee?.photo" :src="auth.employee.photo" alt="foto" class="h-full w-full object-cover" />
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8" class="h-8 w-8">
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
          </svg>
        </div>
        <div>
          <h2 class="text-lg font-bold text-white">{{ auth.employee?.name || auth.user?.name || 'Karyawan' }}</h2>
          <p class="text-sm text-primary-200/70">{{ auth.employee?.position || '—' }}</p>
        </div>
      </div>
    </div>

    <div class="-mt-4 px-4 py-6">
      <!-- Info -->
      <div class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <InfoRow icon="id" label="ID Karyawan" :value="auth.employee?.id ? '#' + auth.employee.id : '—'" />
        <InfoRow icon="mail" label="Email" :value="auth.user?.email || '—'" />
        <InfoRow icon="phone" label="Telepon" :value="auth.employee?.phone || '—'" />
        <InfoRow icon="shift" label="Role" :value="roleLabel" />
      </div>

      <!-- Pengaturan -->
      <h3 class="mb-3 px-1 font-bold text-gray-800">Pengaturan</h3>
      <div class="mb-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
        <button
          type="button"
          class="flex w-full items-center justify-between border-b border-gray-100 p-4 text-left transition hover:bg-gray-50"
          @click="navigateTo('/set-pin')"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                <rect x="4" y="10" width="16" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" />
              </svg>
            </div>
            <span class="text-sm font-medium text-gray-800">Ubah PIN</span>
          </div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-gray-300">
            <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <button
          type="button"
          class="flex w-full items-center justify-between border-b border-gray-100 p-4 text-left transition hover:bg-gray-50"
          @click="toggleBiometric"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4">
                <path
                  d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
                  stroke-linecap="round"
                />
                <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
              </svg>
            </div>
            <div>
              <span class="block text-sm font-medium text-gray-800">Login Sidik Jari</span>
              <span class="block text-xs text-gray-400">{{ bioStatusText }}</span>
            </div>
          </div>
          <svg v-if="!bioBusy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-gray-300">
            <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span v-else class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-teal-500 border-t-transparent"></span>
        </button>
        <button
          type="button"
          class="flex w-full items-center justify-between border-b border-gray-100 p-4 text-left transition hover:bg-gray-50"
          @click="navigateTo('/setup/face')"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4">
                <path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2" stroke-linecap="round" />
                <circle cx="12" cy="10" r="3" />
              </svg>
            </div>
            <span class="text-sm font-medium text-gray-800">Daftar Ulang Wajah</span>
          </div>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-gray-300">
            <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <div class="flex w-full items-center justify-between p-4">
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" />
              </svg>
            </div>
            <span class="text-sm font-medium text-gray-800">Notifikasi</span>
          </div>
          <div class="h-6 w-11 rounded-full bg-primary-600 relative">
            <div class="absolute right-1 top-1 h-4 w-4 rounded-full bg-white shadow-sm"></div>
          </div>
        </div>
      </div>

      <!-- Logout -->
      <button
        type="button"
        class="w-full rounded-xl bg-red-50 py-4 font-semibold text-red-600 transition hover:bg-red-100 active:scale-[0.98]"
        @click="logout"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2 inline-block h-4 w-4 align-middle">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Keluar
      </button>
    </div>
  </div>
  <!-- Modal aktivasi biometrik -->
  <BiometricSetupModal
    v-if="showBioSetup"
    @close="showBioSetup = false"
    @done="onBioActivated"
  />
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const auth = useAuthStore()
const showBioSetup = ref(false)
const bioBusy = ref(false)
const bioKeys = ref<{ id: number; name: string; created_at: string }[]>([])

const roleLabel = computed(() => {
  if (auth.employee?.mobile_role) return auth.employee.mobile_role
  if (auth.isAdmin) return 'Admin'
  return 'Karyawan'
})

const bioStatusText = computed(() => {
  if (bioKeys.value.length > 0) return 'Aktif — ketuk untuk nonaktifkan'
  return 'Belum aktif — ketuk untuk mengaktifkan'
})

/** Muat daftar kunci biometrik saat halaman dibuka. */
onMounted(async () => {
  if (!(await isBiometricAvailable())) return
  try {
    const { data } = await auth.webauthnKeys()
    bioKeys.value = data
  } catch {
    // abaikan — perangkat tanpa dukungan
  }
})

/** Aktifkan kalau belum ada key; nonaktifkan kalau sudah ada. */
async function toggleBiometric() {
  if (bioBusy.value) return
  if (bioKeys.value.length === 0) {
    showBioSetup.value = true
    return
  }
  if (!confirm('Nonaktifkan login sidik jari di perangkat ini?')) return

  bioBusy.value = true
  try {
    for (const key of bioKeys.value) {
      await auth.webauthnDelete(key.id)
    }
    bioKeys.value = []
  } catch (e: any) {
    alert(e?.data?.message || 'Gagal menonaktifkan biometrik.')
  } finally {
    bioBusy.value = false
  }
}

function onBioActivated() {
  showBioSetup.value = false
  bioKeys.value = [{ id: 1, name: 'Fingerprint', created_at: new Date().toISOString() }]
}

async function logout() {
  await auth.logout()
  await navigateTo('/login-karyawan')
}
</script>
