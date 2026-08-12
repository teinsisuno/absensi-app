<template>
  <div class="relative flex min-h-screen flex-col bg-gray-900">
    <!-- Header overlay -->
    <div class="absolute inset-x-0 top-0 z-20 flex items-center justify-between bg-gradient-to-b from-black/70 to-transparent px-6 pb-4 pt-12">
      <button
        type="button"
        class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
        @click="navigateTo('/dashboard')"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
          <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <div class="text-center">
        <p class="text-xs text-white/60">{{ isForce ? 'Absensi · Tambah riwayat' : 'Absensi' }}</p>
        <p class="font-bold text-white">{{ actionType === 'out' ? 'Clock Out' : 'Clock In' }}</p>
      </div>
      <div class="w-10"></div>
    </div>

    <!-- GPS card -->
    <div class="absolute inset-x-6 top-28 z-20">
      <div class="glass flex items-center gap-3 rounded-xl p-3 shadow-lg">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
            <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="10" r="2.5" />
          </svg>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-medium text-gray-800">{{ locationName }}</p>
          <p class="text-[10px] text-gray-500">
            Radius {{ locationRadius }}m · <span class="text-emerald-600">{{ locationStatus }}</span>
          </p>
        </div>
        <div class="h-2 w-2 shrink-0 animate-pulse rounded-full bg-emerald-500"></div>
      </div>
    </div>

    <!-- Area utama: wajah + background kamera terlihat -->
    <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-black">
      <video
        v-if="cameraOn"
        ref="videoEl"
        autoplay
        playsinline
        muted
        class="absolute inset-0 h-full w-full scale-x-[-1] object-cover"
      ></video>
      <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-black/30"></div>

      <div class="relative z-10 text-center">
        <div class="relative mx-auto flex h-64 w-64 items-center justify-center rounded-full border-2 border-white/40">
          <div class="absolute inset-0 animate-spin rounded-full border-2 border-t-transparent border-primary-500/70" style="animation-duration: 3s"></div>
          <div class="flex h-56 w-56 items-center justify-center rounded-full bg-white/10 backdrop-blur-[2px]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="h-24 w-24 text-white/40">
              <circle cx="12" cy="8" r="4" />
              <path d="M4 21a8 8 0 0 1 16 0" />
            </svg>
          </div>
        </div>
        <p class="mt-6 text-sm font-medium text-white/80 drop-shadow">{{ faceStatus }}</p>
      </div>
    </div>

    <!-- Aksi -->
    <div class="bg-gradient-to-t from-black to-transparent p-6 pb-10">
      <p v-if="message" class="mb-3 rounded-xl px-3 py-2 text-center text-sm" :class="messageType === 'error' ? 'bg-red-500/20 text-red-200' : 'bg-emerald-500/20 text-emerald-200'">
        {{ message }}
      </p>

      <img v-if="capturedPhoto" :src="capturedPhoto" alt="Foto absensi" class="mx-auto mb-3 h-28 rounded-xl border border-white/20 object-cover shadow-lg" />

      <button
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-lg font-bold text-white shadow-lg transition active:scale-[0.98]"
        :class="actionType === 'out' ? 'bg-red-600 shadow-red-600/30 hover:bg-red-700' : 'bg-primary-600 shadow-primary-600/30 hover:bg-primary-700'"
        :disabled="busy"
        @click="doClock"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
          <path
            d="M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4"
            stroke-linecap="round"
          />
          <path d="M12 14a2.5 2.5 0 0 0 .5 5" stroke-linecap="round" />
        </svg>
        <span v-if="busy">{{ busyLabel }}</span>
        <span v-else>{{ actionType === 'out' ? 'Clock Out Sekarang' : 'Clock In Sekarang' }}</span>
      </button>
      <p class="mt-3 text-center text-xs text-white/40">
        Waktu server: <span class="tabular-nums">{{ clock }}</span>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: 'guard' })

const route = useRoute()
const auth = useAuthStore()

// Admin tanpa akun karyawan tidak bisa clock — arahkan ke dashboard admin
if (import.meta.client && auth.isAdmin && !auth.isEmployee) {
  navigateTo('/admin/employees')
}

const videoEl = ref<HTMLVideoElement | null>(null)
const cameraOn = ref(false)
let stream: MediaStream | null = null

const dateStr = computed(() => new Date().toISOString().slice(0, 10))
const { data, refresh } = useApi<{ data: any[] }>(() => `/attendance/me?date=${dateStr.value}`)
const records = computed(() => data.value?.data || [])
const lastRecord = computed(() => records.value[0] || null)
const isWorking = computed(() => lastRecord.value?.type === 'clock_in')

/** Tipe aksi: prioritas dari query ?type=in|out (dipilih lewat modal di dashboard),
 *  fallback otomatis dari status sesi. */
const actionType = computed<'in' | 'out'>(() => {
  const q = route.query.type
  if (q === 'in' || q === 'out') return q
  return isWorking.value ? 'out' : 'in'
})

/** Mode tambah riwayat (?force=1) — lewati cek status sesi, record tetap ditambah. */
const isForce = computed(() => route.query.force === '1' || route.query.force === 'true')

const clock = ref('')
const busy = ref(false)
const action = ref<'in' | 'out'>('in')
const message = ref('')
const messageType = ref<'success' | 'error'>('success')
const capturedPhoto = ref<string | null>(null)
let successTimer: ReturnType<typeof setTimeout> | null = null

const locationName = computed(() => lastRecord.value?.work_location?.name || 'Mencari lokasi…')
const locationRadius = computed(() => lastRecord.value?.work_location?.radius_meter ?? 100)
const locationStatus = computed(() => (lastRecord.value ? 'Dalam Area' : 'Menunggu GPS'))
const faceStatus = computed(() => (cameraOn.value ? 'Posisikan wajah di tengah' : 'Kamera tidak aktif'))
const busyLabel = computed(() => (action.value === 'in' ? 'Mengambil foto…' : 'Memproses…'))

onMounted(() => {
  const update = () => {
    clock.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  }
  update()
  const t = setInterval(update, 1000)
  onBeforeUnmount(() => clearInterval(t))
  enableCamera()
})

onBeforeUnmount(() => {
  if (successTimer) clearTimeout(successTimer)
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
})

async function enableCamera() {
  try {
    stream = await Promise.race([
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }),
      new Promise((_, rej) => setTimeout(() => rej(new Error('kamera timeout')), 4000)),
    ])
    if (videoEl.value) {
      videoEl.value.srcObject = stream
      await videoEl.value.play()
    }
    cameraOn.value = true
  } catch {
    // Kamera tidak tersedia → tetap bisa absen (GPS saja, tanpa foto)
    cameraOn.value = false
  }
}

function getPosition(): Promise<GeolocationPosition> {
  return new Promise((resolve, reject) => {
    if (!('geolocation' in navigator)) {
      reject(new Error('Browser tidak mendukung GPS.'))
      return
    }
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 15_000,
      maximumAge: 0,
    })
  })
}

/**
 * Ambil frame video → gambar ke canvas (mirror, sama kayak preview),
 * stamp overlay geolokasi di kiri bawah, lalu kompres JPEG 70% ukuran max 800px.
 */
function capturePhoto(pos: GeolocationPosition): string | null {
  const video = videoEl.value
  if (!video || video.readyState < 2 || !video.videoWidth) return null

  const maxW = 800
  const scale = Math.min(1, maxW / video.videoWidth)
  const w = Math.round(video.videoWidth * scale)
  const h = Math.round(video.videoHeight * scale)

  const canvas = document.createElement('canvas')
  canvas.width = w
  canvas.height = h
  const ctx = canvas.getContext('2d')
  if (!ctx) return null

  // Gambar frame secara mirror (sesuai preview yang dilihat user)
  ctx.translate(w, 0)
  ctx.scale(-1, 1)
  ctx.drawImage(video, 0, 0, w, h)
  ctx.setTransform(1, 0, 0, 1, 0, 0)

  // Overlay info geolokasi di kiri bawah
  const barH = 64
  ctx.fillStyle = 'rgba(0, 0, 0, 0.55)'
  ctx.fillRect(0, h - barH, w, barH)

  const label = actionType.value === 'out' ? 'CLOCK OUT' : 'CLOCK IN'
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  const dateTime = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`

  ctx.textAlign = 'left'
  ctx.textBaseline = 'middle'
  ctx.fillStyle = '#ffffff'
  ctx.font = 'bold 17px system-ui, sans-serif'
  ctx.fillText(`ABSENSI • ${label}`, 12, h - barH + 19)
  ctx.font = '12px system-ui, sans-serif'
  ctx.fillStyle = 'rgba(255, 255, 255, 0.85)'
  ctx.fillText(`📍 ${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)}`, 12, h - barH + 43)

  return canvas.toDataURL('image/jpeg', 0.7)
}

async function doClock() {
  const type = actionType.value
  busy.value = true
  action.value = type
  message.value = ''
  messageType.value = 'success'
  capturedPhoto.value = null
  try {
    const pos = await getPosition()
    const photo = capturePhoto(pos)
    if (photo) capturedPhoto.value = photo

    const res = await api<{ message: string; data: any }>('POST', `/attendance/clock-${type}`, {
      latitude: pos.coords.latitude,
      longitude: pos.coords.longitude,
      selfie_photo: photo,
      force: isForce.value,
    })
    message.value = res.message
    const d = res.data
    if (d && d.distance_meter != null) {
      message.value += ` (jarak ${Math.round(d.distance_meter)} m)`
    }
    await refresh()
    // Sukses → tampilkan pesan sebentar, lalu otomatis kembali ke beranda
    successTimer = setTimeout(() => navigateTo('/dashboard'), 1500)
  } catch (e: any) {
    // Geolocation error (permission / timeout) vs API error
    if (e?.code === 1 || e?.code === 2 || e?.code === 3) {
      message.value = e.message || 'Gagal mendapatkan lokasi GPS.'
    } else {
      message.value = e?.data?.message || 'Gagal memproses absen.'
    }
    messageType.value = 'error'
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.glass {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
}
</style>
