<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-gray-900">Pengaturan</h1>
      <p class="text-sm text-gray-500">Konfigurasi tenant (mode wajah, kode unik, radius absen)</p>
    </div>

    <SkeletonLoader v-if="loading" />

    <div v-else class="card max-w-2xl">
      <form @submit.prevent="submit">
        <div class="mb-5">
          <label class="label">Face Recognition Mode</label>
          <select v-model="form.face_mode" class="input">
            <option value="server">Server-side (matching di server)</option>
            <option value="client">Client-side (matching di device)</option>
          </select>
          <p class="mt-1 text-xs text-gray-400">Cara template wajah dicocokkan saat absen. Default: server.</p>
        </div>

        <div class="mb-5">
          <label class="label">Masa Berlaku Kode Unik (jam)</label>
          <input v-model="form.invite_expiry_hours" type="number" min="1" max="720" class="input" />
          <p class="mt-1 text-xs text-gray-400">Berapa jam kode unik link akun berlaku. Default: 48 jam.</p>
        </div>

        <div class="mb-5">
          <label class="label">Default Radius Absen (meter)</label>
          <input v-model="form.default_radius_meter" type="number" min="10" max="10000" class="input" />
          <p class="mt-1 text-xs text-gray-400">Jarak maksimal dari titik lokasi agar absen diterima. Default: 100 m.</p>
        </div>

        <div class="mb-5 flex items-center gap-2">
          <input id="notify_email_hr" v-model="form.notify_email_hr" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
          <label for="notify_email_hr" class="text-sm text-gray-700">Kirim notifikasi email ke HR saat ada pengajuan baru</label>
        </div>

        <p v-if="formError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>
        <p v-if="saved" class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">✓ Pengaturan disimpan.</p>

        <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan Pengaturan' }}</button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const formError = ref('')

const form = reactive({
  face_mode: 'server',
  invite_expiry_hours: '48',
  default_radius_meter: '100',
  notify_email_hr: false,
})

onMounted(async () => {
  try {
    const data = await api<{ data: Record<string, string> }>('GET', '/settings')
    const s = data.data || {}
    form.face_mode = s.face_mode || 'server'
    form.invite_expiry_hours = s.invite_expiry_hours || '48'
    form.default_radius_meter = s.default_radius_meter || '100'
    form.notify_email_hr = s.notify_email_hr === 'true'
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat pengaturan.'))
  } finally {
    loading.value = false
  }
})

async function submit() {
  formError.value = ''
  saved.value = false
  saving.value = true
  try {
    await api('PUT', '/settings', {
      settings: {
        face_mode: form.face_mode,
        invite_expiry_hours: String(Number(form.invite_expiry_hours) || 48),
        default_radius_meter: String(Number(form.default_radius_meter) || 100),
        notify_email_hr: form.notify_email_hr ? 'true' : 'false',
      },
    })
    saved.value = true
    toast.success('Pengaturan disimpan.')
    setTimeout(() => (saved.value = false), 3000)
  } catch (e: any) {
    formError.value = errorMessage(e, 'Gagal menyimpan pengaturan.')
  } finally {
    saving.value = false
  }
}
</script>
