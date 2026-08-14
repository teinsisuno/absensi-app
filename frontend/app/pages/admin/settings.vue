<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-gray-900">Pengaturan</h1>
      <p class="text-sm text-gray-500">Konfigurasi tenant (mode wajah, kode unik, radius absen, WhatsApp)</p>
    </div>

    <SkeletonLoader v-if="loading" />

    <div v-else class="space-y-6">
      <!-- Umum -->
      <div class="card max-w-2xl p-6">
        <div class="mb-4 flex items-center gap-2 border-b border-gray-100 pb-3">
          <span class="text-lg">⚙️</span>
          <h2 class="text-sm font-semibold text-gray-900">Umum</h2>
        </div>
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

      <!-- WhatsApp Gateway -->
      <div class="card max-w-2xl p-6">
        <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">💬</span>
            <h2 class="text-sm font-semibold text-gray-900">WhatsApp Gateway</h2>
          </div>
          <span
            class="rounded-full px-2 py-0.5 text-xs font-medium"
            :class="waStatusBadge"
          >
            {{ waStatusLabel }}
          </span>
        </div>
        <p class="mb-4 text-xs text-gray-400">
          Kirim kode unik registrasi otomatis ke WhatsApp karyawan. Butuh bot
          <code class="rounded bg-gray-100 px-1">whatsapp-bot</code> yang sudah login (scan QR) dan API-nya berjalan.
        </p>

        <form @submit.prevent="submit">
          <div class="mb-5 flex items-center gap-2">
            <input id="whatsapp_enabled" v-model="form.whatsapp_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
            <label for="whatsapp_enabled" class="text-sm text-gray-700">Aktifkan kirim kode unik via WhatsApp</label>
          </div>

          <div class="mb-5">
            <label class="label">URL Gateway</label>
            <input v-model="form.whatsapp_gateway_url" type="url" class="input" placeholder="http://127.0.0.1:3001" />
            <p class="mt-1 text-xs text-gray-400">Alamat HTTP API whatsapp-bot (tanpa trailing slash).</p>
          </div>

          <div class="mb-5">
            <label class="label">API Token</label>
            <input v-model="form.whatsapp_api_token" type="password" class="input" placeholder="token dari .env whatsapp-bot" />
            <p class="mt-1 text-xs text-gray-400">Nilai <code class="rounded bg-gray-100 px-1">API_TOKEN</code> di file .env whatsapp-bot.</p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan Pengaturan' }}</button>
            <button type="button" class="btn-secondary" :disabled="waChecking" @click="checkStatus">
              {{ waChecking ? 'Mengecek…' : '🔄 Cek Status Gateway' }}
            </button>
            <button type="button" class="btn-secondary" :disabled="waTesting" @click="sendTest">
              {{ waTesting ? 'Mengirim…' : '📨 Kirim Test' }}
            </button>
            <input v-model="testPhone" type="tel" class="input w-44" placeholder="Nomor test, mis. 0812…" />
          </div>

          <p v-if="waError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ waError }}</p>
          <p v-if="waSuccess" class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ waSuccess }}</p>
        </form>

        <!-- Status + QR scan + restart -->
        <div v-if="form.whatsapp_enabled" class="mt-5 border-t border-gray-100 pt-4">
          <div v-if="waStatus?.error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
            {{ waStatus.error }}
          </div>
          <div v-else-if="waStatus?.connected" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">
            ✅ Terhubung sebagai <strong>{{ waStatus.name || waStatus.number }}</strong> ({{ waStatus.number }})
          </div>
          <div v-else class="rounded-lg bg-amber-50 px-3 py-4 text-center">
            <p class="mb-1 text-sm font-medium text-amber-700">Scan QR ini dengan WhatsApp kamu</p>
            <p class="mb-3 text-xs text-amber-600">
              HP → WhatsApp → ⋮ → Perangkat Tertaut → Tautkan Perangkat. QR refresh otomatis tiap 5 detik.
            </p>
            <img v-if="waQr" :src="waQr" alt="WhatsApp QR" class="mx-auto h-52 w-52 rounded-lg bg-white p-2 shadow-sm" />
            <p v-else class="text-xs text-amber-500">Menunggu QR dari gateway…</p>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="button" class="btn-secondary" :disabled="waRestarting" @click="restartGateway">
              {{ waRestarting ? 'Merestart…' : '♻️ Restart Gateway' }}
            </button>
            <span v-if="waStatus?.booting" class="text-xs text-gray-400">Gateway masih booting…</span>
          </div>
        </div>
      </div>
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
  whatsapp_enabled: false,
  whatsapp_gateway_url: '',
  whatsapp_api_token: '',
})

// Status gateway (opsional, non-blocking)
const waStatus = ref<any>(null)
const waChecking = ref(false)
const waTesting = ref(false)
const waError = ref('')
const waSuccess = ref('')
const testPhone = ref('')
const waQr = ref('')
const waRestarting = ref(false)
let waPollTimer: any = null

const waStatusLabel = computed(() => {
  if (!form.whatsapp_enabled) return 'Nonaktif'
  if (!waStatus.value) return 'Belum dicek'
  if (waStatus.value.error) return 'Error'
  if (waStatus.value.connected) return 'Terhubung'
  return 'Menunggu QR'
})

const waStatusBadge = computed(() => {
  if (!form.whatsapp_enabled) return 'bg-gray-100 text-gray-500'
  if (!waStatus.value) return 'bg-gray-100 text-gray-500'
  if (waStatus.value.error) return 'bg-red-100 text-red-700'
  if (waStatus.value.connected) return 'bg-green-100 text-green-700'
  return 'bg-amber-100 text-amber-700'
})

onMounted(async () => {
  try {
    const data = await api<{ data: Record<string, string> }>('GET', '/settings')
    const s = data.data || {}
    form.face_mode = s.face_mode || 'server'
    form.invite_expiry_hours = s.invite_expiry_hours || '48'
    form.default_radius_meter = s.default_radius_meter || '100'
    form.notify_email_hr = s.notify_email_hr === 'true'
    form.whatsapp_enabled = s.whatsapp_enabled === 'true'
    form.whatsapp_gateway_url = s.whatsapp_gateway_url || ''
    form.whatsapp_api_token = s.whatsapp_api_token || ''
    if (form.whatsapp_enabled) {
      checkStatus()
      startWaPolling()
    }
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat pengaturan.'))
  } finally {
    loading.value = false
  }
})

onUnmounted(stopWaPolling)

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
        whatsapp_enabled: form.whatsapp_enabled ? 'true' : 'false',
        whatsapp_gateway_url: form.whatsapp_gateway_url.trim(),
        whatsapp_api_token: form.whatsapp_api_token.trim(),
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

async function checkStatus() {
  waChecking.value = true
  waError.value = ''
  waSuccess.value = ''
  try {
    // Simpan dulu biar gateway pakai konfigurasi terbaru
    await submit()
    const res = await api<{ data: any }>('GET', '/settings/whatsapp/status')
    waStatus.value = res.data
    if (res.data?.error) waError.value = 'Status: ' + res.data.error
    else if (res.data?.connected) {
      waSuccess.value = 'Gateway terhubung sebagai ' + (res.data.name || res.data.number || 'bot')
      waQr.value = ''
    } else {
      waSuccess.value = 'Gateway aktif — menunggu scan QR / bot belum siap.'
      loadQr()
    }
  } catch (e: any) {
    waError.value = errorMessage(e, 'Gagal cek status.')
  } finally {
    waChecking.value = false
  }
}

async function loadQr() {
  try {
    const res = await api<{ data: any }>('GET', '/settings/whatsapp/qr')
    const d = res.data
    waQr.value = d?.qr ? d.qr : ''
  } catch (e) {
    // diam — polling berikutnya yang update
  }
}

async function restartGateway() {
  waRestarting.value = true
  waError.value = ''
  waSuccess.value = ''
  try {
    const res = await api<{ message: string }>('POST', '/settings/whatsapp/restart')
    waSuccess.value = res.message || 'Gateway di-restart.'
    waQr.value = ''
    waStatus.value = null
    setTimeout(() => {
      checkStatus()
      startWaPolling()
    }, 3000)
  } catch (e: any) {
    waError.value = errorMessage(e, 'Gagal restart gateway.')
  } finally {
    waRestarting.value = false
  }
}

function startWaPolling() {
  stopWaPolling()
  waPollTimer = setInterval(async () => {
    if (!form.whatsapp_enabled) return
    try {
      const res = await api<{ data: any }>('GET', '/settings/whatsapp/status')
      if (res?.data) {
        waStatus.value = res.data
        if (res.data.connected) waQr.value = ''
        else loadQr()
      }
    } catch (e) {
      // gateway mungkin lagi restart — biarkan polling lanjut
    }
  }, 5000)
}

function stopWaPolling() {
  if (waPollTimer) {
    clearInterval(waPollTimer)
    waPollTimer = null
  }
}

async function sendTest() {
  if (!testPhone.value.trim()) {
    waError.value = 'Isi nomor tujuan test dulu.'
    return
  }
  waError.value = ''
  waSuccess.value = ''
  waTesting.value = true
  try {
    // Kirim Test = otomatis aktifkan gateway + simpan dulu, biar langsung jalan
    if (!form.whatsapp_enabled) {
      form.whatsapp_enabled = true
      toast.info('WhatsApp Gateway otomatis diaktifkan.')
    }
    await submit()
    await api('POST', '/settings/whatsapp/test', { phone: testPhone.value.trim() })
    waSuccess.value = 'Pesan test terkirim! Cek WhatsApp nomor ' + testPhone.value.trim() + '.'
  } catch (e: any) {
    waError.value = errorMessage(e, 'Gagal kirim test.')
  } finally {
    waTesting.value = false
  }
}
</script>
