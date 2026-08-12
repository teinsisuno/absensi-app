<template>
  <div class="flex min-h-screen flex-col bg-black">
    <!-- Header overlay -->
    <div class="absolute inset-x-0 top-0 z-10 bg-gradient-to-b from-black/80 to-transparent px-6 pb-4 pt-12">
      <button
        type="button"
        class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
        @click="finish"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
          <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <h1 class="text-xl font-bold text-white">Daftarkan Wajah</h1>
      <p class="text-sm text-white/60">Posisikan wajah di dalam lingkaran</p>
    </div>

    <!-- Kamera area -->
    <div class="relative flex flex-1 items-center justify-center">
      <div class="relative h-72 w-72">
        <div class="absolute inset-0 rounded-full border-2 border-white/20"></div>
        <div class="face-ring absolute inset-2 rounded-full border-2 border-primary-500/50"></div>
        <div class="absolute inset-4 overflow-hidden rounded-full bg-gray-900">
          <video
            v-show="cameraOn"
            ref="videoEl"
            autoplay
            playsinline
            muted
            class="h-full w-full scale-x-[-1] object-cover"
          ></video>
          <div v-if="!cameraOn" class="flex h-full w-full flex-col items-center justify-center text-gray-400">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="mb-2 h-8 w-8">
              <path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2" stroke-linecap="round" />
            </svg>
            <p class="text-xs">Kamera belum aktif</p>
          </div>
          <div v-if="scanning" class="scan-line"></div>
        </div>
        <div class="absolute inset-x-0 bottom-6 text-center">
          <p class="text-sm font-medium text-white/80">{{ faceStatus }}</p>
        </div>
      </div>
    </div>

    <!-- Tombol -->
    <div class="p-6 pb-10">
      <p v-if="error" class="mb-3 rounded-xl bg-amber-500/20 px-3 py-2 text-center text-sm text-amber-100">{{ error }}</p>

      <button
        v-if="!scanning && !done"
        type="button"
        class="w-full rounded-xl bg-primary-600 py-4 font-semibold text-white shadow-lg transition active:scale-[0.98]"
        :disabled="!cameraOn"
        @click="startScan"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2 inline-block h-5 w-5 align-middle">
          <path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2" stroke-linecap="round" />
          <circle cx="12" cy="10" r="3" />
        </svg>
        {{ cameraOn ? 'Ambil & Simpan' : 'Mengaktifkan kamera…' }}
      </button>

      <button v-else-if="scanning" type="button" class="w-full rounded-xl bg-gray-700 py-4 font-semibold text-gray-300" disabled>
        Merekam… {{ scanCount }}s
      </button>

      <button v-else type="button" class="w-full rounded-xl bg-emerald-600 py-4 font-semibold text-white shadow-lg transition active:scale-[0.98]" @click="finish">
        Selesai
      </button>

      <button
        v-if="!cameraOn && error"
        type="button"
        class="mt-3 w-full rounded-xl border border-white/20 py-3 text-sm font-medium text-white/70 transition hover:bg-white/10"
        @click="finish"
      >
        Lanjut tanpa kamera (demo)
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

const auth = useAuthStore()
const videoEl = ref<HTMLVideoElement | null>(null)
const cameraOn = ref(false)
const scanning = ref(false)
const done = ref(false)
const scanCount = ref(3)
const error = ref('')
let stream: MediaStream | null = null
let timer: ReturnType<typeof setInterval> | null = null

const faceStatus = computed(() => {
  if (done.value) return 'Wajah terekam ✓'
  if (scanning.value) return 'Merekam wajah…'
  if (cameraOn.value) return 'Mencari wajah…'
  return 'Kamera belum aktif'
})

onMounted(enableCamera)
onBeforeUnmount(stopCamera)

async function enableCamera() {
  error.value = ''
  try {
    stream = await Promise.race([
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false }),
      // Device tanpa kamera / izin belum dijawab → jangan nge-hang, jatuh ke mode demo
      new Promise((_, rej) => setTimeout(() => rej(new Error('kamera timeout')), 4000)),
    ])
    if (videoEl.value) {
      videoEl.value.srcObject = stream
      await videoEl.value.play()
    }
    cameraOn.value = true
  } catch (e: any) {
    // Kamera tidak tersedia (http non-localhost / izin ditolak) → fallback demo
    error.value = 'Kamera tidak bisa diakses. Gunakan mode demo untuk lanjut.'
    cameraOn.value = false
  }
}

function stopCamera() {
  if (timer) clearInterval(timer)
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
}

function startScan() {
  if (!cameraOn.value) return
  scanning.value = true
  done.value = false
  scanCount.value = 3
  timer = setInterval(() => {
    scanCount.value -= 1
    if (scanCount.value <= 0) {
      if (timer) clearInterval(timer)
      scanning.value = false
      done.value = true
    }
  }, 1000)
}

function finish() {
  auth.markFaceDone()
  stopCamera()
  navigateTo('/setup')
}
</script>

<style scoped>
.face-ring {
  position: relative;
}
.face-ring::before {
  content: '';
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  border: 3px solid transparent;
  border-top-color: #0d9488;
  animation: spin 1.5s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.scan-line {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 2px;
  background: linear-gradient(90deg, transparent, #5eead4, transparent);
  animation: scan 2s ease-in-out infinite;
}
@keyframes scan {
  0% {
    top: 0%;
    opacity: 0;
  }
  50% {
    opacity: 1;
  }
  100% {
    top: 100%;
    opacity: 0;
  }
}
</style>
