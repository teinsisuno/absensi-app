<template>
  <div>
    <!-- Header -->
    <div class="sticky top-0 z-20 border-b border-gray-100 bg-white px-6 pb-4 pt-12">
      <div class="mb-2 flex items-center gap-4">
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200"
          @click="navigateTo('/dashboard')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="text-xl font-bold text-gray-800">Kunjungan</h1>
      </div>
    </div>

    <div class="px-4 py-4">
      <!-- Tab -->
      <div class="mb-6 flex rounded-2xl border border-gray-100 bg-white p-2 shadow-sm">
        <button
          type="button"
          class="flex-1 rounded-xl py-3 text-sm font-medium transition"
          :class="tab === 'new' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
          @click="tab = 'new'"
        >
          Catat Kunjungan
        </button>
        <button
          type="button"
          class="flex-1 rounded-xl py-3 text-sm font-medium transition"
          :class="tab === 'history' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
          @click="tab = 'history'"
        >
          Riwayat
        </button>
      </div>

      <!-- Tab baru -->
      <template v-if="tab === 'new'">
        <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm font-medium text-gray-600">Foto Kunjungan</p>
            <span v-if="gpsState" class="text-xs text-gray-400">{{ gpsState }}</span>
          </div>

          <!-- Pratinjau foto -->
          <div v-if="photo" class="relative mb-3 overflow-hidden rounded-xl bg-gray-100">
            <img :src="photo" class="h-56 w-full object-cover" />
            <button
              type="button"
              class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow"
              @click="photo = ''"
            >
              ✕
            </button>
          </div>

          <!-- Kamera / upload -->
          <div v-if="!photo">
            <div v-if="cameraActive" class="relative mb-3 overflow-hidden rounded-xl bg-black">
              <video ref="videoEl" autoplay playsinline class="h-56 w-full object-cover"></video>
              <button type="button" class="absolute inset-x-0 bottom-2 mx-auto block rounded-full bg-white/90 px-4 py-2 text-sm font-medium text-gray-800 shadow" @click="capture">
                📷 Ambil Foto
              </button>
            </div>
            <div class="flex gap-2">
              <button type="button" class="flex-1 rounded-xl border border-primary-200 bg-primary-50 py-3 text-sm font-medium text-primary-700" @click="toggleCamera">
                {{ cameraActive ? 'Tutup Kamera' : 'Buka Kamera' }}
              </button>
              <label class="flex-1 cursor-pointer rounded-xl border border-gray-200 bg-gray-50 py-3 text-center text-sm font-medium text-gray-600">
                📂 Pilih Foto
                <input type="file" accept="image/*" class="hidden" @change="onFilePick" />
              </label>
            </div>
            <p v-if="!isSecure" class="mt-2 text-xs text-amber-600">
              ⚠️ Kamera butuh HTTPS. Pakai "Pilih Foto" kalau kamera tidak terbuka.
            </p>
          </div>
        </div>

        <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <label class="mb-2 block text-sm font-medium text-gray-600">Koordinat</label>
          <p v-if="coords" class="rounded-xl bg-gray-50 px-3 py-2.5 text-sm text-gray-700">
            {{ Number(coords.latitude).toFixed(6) }}, {{ Number(coords.longitude).toFixed(6) }}
          </p>
          <p v-else class="text-xs text-gray-400">Ambil lokasi otomatis saat submit…</p>
        </div>

        <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <label class="mb-2 block text-sm font-medium text-gray-600">Keterangan</label>
          <textarea
            v-model="notes"
            rows="3"
            placeholder="Catat hasil kunjungan..."
            class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
          ></textarea>
        </div>

        <p v-if="error" class="mb-4 rounded-xl bg-red-50 px-3 py-2.5 text-sm text-red-600">{{ error }}</p>

        <button
          type="button"
          class="w-full rounded-xl bg-primary-600 py-4 font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:bg-primary-700 active:scale-[0.98]"
          :disabled="saving"
          @click="submit"
        >
          <span v-if="saving" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle"></span>
          {{ saving ? 'Menyimpan…' : 'Simpan Kunjungan' }}
        </button>
      </template>

      <!-- Tab riwayat -->
      <template v-else>
        <div v-if="listLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">Memuat…</div>

        <div v-else-if="visits.length === 0" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">
          Belum ada kunjungan.
        </div>

        <div v-else class="space-y-3">
          <div v-for="v in visits" :key="v.id" class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
            <img v-if="v.photo" :src="v.photo" class="h-40 w-full object-cover" />
            <div class="p-4">
              <p class="text-xs text-gray-400">{{ formatDateTime(v.visited_at) }}</p>
              <p v-if="v.notes" class="mt-1 text-sm text-gray-700">{{ v.notes }}</p>
              <p v-if="v.latitude" class="mt-1 text-xs text-gray-400">{{ Number(v.latitude).toFixed(6) }}, {{ Number(v.longitude).toFixed(6) }}</p>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const toast = useToast()

const tab = ref<'new' | 'history'>('new')
const photo = ref('')
const notes = ref('')
const error = ref('')
const saving = ref(false)

const coords = ref<{ latitude: number; longitude: number } | null>(null)
const gpsState = ref('')
const isSecure = ref(true)

const cameraActive = ref(false)
const videoEl = ref<HTMLVideoElement | null>(null)
let stream: MediaStream | null = null

const listLoading = ref(true)
const visits = ref<any[]>([])

onMounted(() => {
  isSecure.value = typeof window !== 'undefined' && window.isSecureContext !== false
  loadHistory()
})

onBeforeUnmount(() => stopCamera())

async function loadHistory() {
  listLoading.value = true
  try {
    const data = await api<{ data: any[] }>('GET', '/visits/me')
    visits.value = data.data
  } catch {
    visits.value = []
  } finally {
    listLoading.value = false
  }
}

async function getCoords(): Promise<{ latitude: number; longitude: number } | null> {
  if (!('geolocation' in navigator)) return null
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const c = { latitude: pos.coords.latitude, longitude: pos.coords.longitude }
        coords.value = c
        gpsState.value = `📍 ${Number(c.latitude).toFixed(6)}, ${Number(c.longitude).toFixed(6)}`
        resolve(c)
      },
      () => resolve(null),
      { timeout: 8000, enableHighAccuracy: true },
    )
  })
}

async function toggleCamera() {
  if (cameraActive.value) {
    stopCamera()
    return
  }
  if (!navigator.mediaDevices?.getUserMedia) {
    toast.error('Kamera tidak didukung di perangkat ini.')
    return
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    if (videoEl.value) videoEl.value.srcObject = stream
    cameraActive.value = true
  } catch {
    toast.error('Gagal membuka kamera. Coba pakai Pilih Foto.')
  }
}

function stopCamera() {
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
  cameraActive.value = false
}

function capture() {
  const video = videoEl.value
  if (!video) return
  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth || 640
  canvas.height = video.videoHeight || 480
  canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
  photo.value = canvas.toDataURL('image/jpeg', 0.7)
  stopCamera()
}

function onFilePick(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => (photo.value = String(reader.result))
  reader.readAsDataURL(file)
  input.value = ''
}

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const location = await getCoords()
    await api('POST', '/visits', {
      latitude: location?.latitude ?? null,
      longitude: location?.longitude ?? null,
      photo: photo.value || null,
      notes: notes.value || null,
    })
    toast.success('Kunjungan tercatat.')
    photo.value = ''
    notes.value = ''
    coords.value = null
    gpsState.value = ''
    tab.value = 'history'
    await loadHistory()
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal menyimpan kunjungan.')
  } finally {
    saving.value = false
  }
}

function formatDateTime(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) +
    ' ' + new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>
