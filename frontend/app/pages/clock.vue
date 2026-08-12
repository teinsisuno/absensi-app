<template>
  <div class="relative flex min-h-screen flex-col bg-gray-900">
    <!-- Header overlay -->
    <div class="absolute inset-x-0 top-0 z-20 flex items-center justify-between bg-gradient-to-b from-black/80 to-transparent px-6 pb-4 pt-12">
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
        <p class="text-xs text-white/60">Absensi</p>
        <p class="font-bold text-white">{{ isWorking ? 'Clock Out' : 'Clock In' }}</p>
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

    <!-- Area utama: ring wajah -->
    <div class="relative flex flex-1 items-center justify-center bg-black">
      <video
        v-if="cameraOn"
        ref="videoEl"
        autoplay
        playsinline
        muted
        class="absolute inset-0 h-full w-full scale-x-[-1] object-cover opacity-40"
      ></video>
      <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-black/50"></div>

      <div class="relative z-10 text-center">
        <div class="relative mx-auto flex h-64 w-64 items-center justify-center rounded-full border-2 border-white/30">
          <div class="absolute inset-0 animate-spin rounded-full border-2 border-t-transparent border-primary-500/60" style="animation-duration: 3s"></div>
          <div class="flex h-56 w-56 items-center justify-center rounded-full bg-white/5 backdrop-blur-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="h-24 w-24 text-white/20">
              <circle cx="12" cy="8" r="4" />
              <path d="M4 21a8 8 0 0 1 16 0" />
            </svg>
          </div>
        </div>
        <p class="mt-6 text-sm font-medium text-white/70">{{ faceStatus }}</p>
      </div>
    </div>

    <!-- Aksi -->
    <div class="bg-gradient-to-t from-black to-transparent p-6 pb-10">
      <p v-if="message" class="mb-3 rounded-xl px-3 py-2 text-center text-sm" :class="messageType === 'error' ? 'bg-red-500/20 text-red-200' : 'bg-emerald-500/20 text-emerald-200'">
        {{ message }}
      </p>

      <button
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded-2xl py-4 text-lg font-bold text-white shadow-lg transition active:scale-[0.98]"
        :class="isWorking ? 'bg-red-600 shadow-red-600/30 hover:bg-red-700' : 'bg-primary-600 shadow-primary-600/30 hover:bg-primary-700'"
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
        <span v-else>{{ isWorking ? 'Clock Out Sekarang' : 'Clock In Sekarang' }}</span>
      </button>
      <p class="mt-3 text-center text-xs text-white/40">
        Waktu server: <span class="tabular-nums">{{ clock }}</span>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false, middleware: 'guard' })

const auth = useAuthStore()

// Admin tanpa akun karyawan tidak bisa clock — arahkan ke dashboard admin
if (import.meta.client && auth.isAdmin && !auth.isEmployee) {
  navigateTo('/admin/employees')
}

const videoEl = ref<HTMLVideoElement | null>(null)
const cameraOn = ref(false)
let stream: MediaStream | null = null

const dateStr = computed(() => new Date().toISOString().slice(0, 10))
const { data, refresh } = useApi<any[]>(() => `/attendance/me?date=${dateStr.value}`)
const records = computed(() => data.value || [])
const lastRecord = computed(() => records.value[0] || null)
const isWorking = computed(() => lastRecord.value?.type === 'clock_in')

const clock = ref('')
const busy = ref(false)
const action = ref<'in' | 'out'>('in')
const message = ref('')
const messageType = ref<'success' | 'error'>('success')

const locationName = computed(() => lastRecord.value?.work_location?.name || 'Mencari lokasi…')
const locationRadius = computed(() => lastRecord.value?.work_location?.radius_meter ?? 100)
const locationStatus = computed(() => (lastRecord.value ? 'Dalam Area' : 'Menunggu GPS'))
const faceStatus = computed(() => (cameraOn.value ? 'Posisikan wajah di tengah' : 'Kamera tidak aktif'))
const busyLabel = computed(() => (action.value === 'in' ? 'Mencari lokasi…' : 'Memproses…'))

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
    // Kamera tidak tersedia → tetap bisa absen (GPS saja)
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

async function doClock() {
  const type = isWorking.value ? 'out' : 'in'
  busy.value = true
  action.value = type
  message.value = ''
  messageType.value = 'success'
  try {
    const pos = await getPosition()
    const res = await api<{ message: string; data: any }>('POST', `/attendance/clock-${type}`, {
      latitude: pos.coords.latitude,
      longitude: pos.coords.longitude,
      selfie_photo: null,
    })
    message.value = res.message
    const d = res.data
    if (d && d.distance_meter != null) {
      message.value += ` (jarak ${Math.round(d.distance_meter)} m)`
    }
    await refresh()
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
