<template>
  <div class="w-full max-w-sm">
    <div class="card overflow-hidden p-0">
      <div class="p-6 text-center">
        <h1 class="text-lg font-semibold text-gray-900">Scan Wajah</h1>
        <p class="mt-1 text-sm text-gray-500">
          Posisikan wajah di tengah lingkaran, lalu tekan Mulai Scan.
        </p>
      </div>

      <!-- Area kamera -->
      <div class="relative mx-6 aspect-square overflow-hidden rounded-2xl bg-gray-900">
        <video
          v-show="cameraOn"
          ref="videoEl"
          autoplay
          playsinline
          muted
          class="absolute inset-0 h-full w-full object-cover"
        ></video>
        <div v-if="!cameraOn" class="absolute inset-0 flex flex-col items-center justify-center text-gray-400">
          <span class="text-4xl">📷</span>
          <p class="mt-2 text-xs">Kamera belum aktif</p>
        </div>

        <!-- Oval wajah -->
        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
          <div
            class="rounded-full border-2"
            :class="scanning ? 'animate-pulse border-primary-400' : 'border-white/40'"
            style="width: 70%; height: 70%"
          ></div>
        </div>

        <!-- Progress scan -->
        <div v-if="scanning" class="absolute inset-x-0 bottom-0 bg-black/50 px-4 py-3 text-center text-sm text-white">
          <div class="mb-1 flex justify-between text-xs">
            <span>Merekam…</span>
            <span>{{ scanCount }}s</span>
          </div>
          <div class="h-1.5 overflow-hidden rounded-full bg-white/20">
            <div class="h-full bg-primary-400 transition-all" :style="{ width: scanProgress + '%' }"></div>
          </div>
        </div>

        <!-- Hasil -->
        <div v-if="done" class="absolute inset-0 flex flex-col items-center justify-center bg-emerald-600/80 text-white">
          <span class="text-4xl">✅</span>
          <p class="mt-2 text-sm font-medium">Wajah terekam</p>
        </div>
      </div>

      <div class="p-6">
        <p v-if="error" class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">{{ error }}</p>

        <button
          v-if="!scanning && !done"
          type="button"
          class="btn-primary w-full"
          :disabled="!cameraOn"
          @click="startScan"
        >
          {{ cameraOn ? 'Mulai Scan' : 'Mengaktifkan kamera…' }}
        </button>

        <button v-else-if="scanning" type="button" class="btn w-full bg-gray-200 text-gray-600" disabled>
          Merekam…
        </button>

        <button v-else type="button" class="btn-primary w-full" @click="finish">
          Selesai
        </button>

        <button
          v-if="!cameraOn && error"
          type="button"
          class="mt-3 w-full rounded-lg border border-gray-200 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50"
          @click="finish"
        >
          Lanjut tanpa kamera (demo)
        </button>

        <button
          v-if="!cameraOn && !error"
          type="button"
          class="mt-3 w-full text-center text-xs text-gray-400 underline"
          @click="enableCamera"
        >
          Aktifkan kamera
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default' })

const auth = useAuthStore()
const videoEl = ref<HTMLVideoElement | null>(null)
const cameraOn = ref(false)
const scanning = ref(false)
const done = ref(false)
const scanCount = ref(3)
const scanProgress = ref(0)
const error = ref('')
let stream: MediaStream | null = null
let timer: ReturnType<typeof setInterval> | null = null

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
  scanProgress.value = 0
  timer = setInterval(() => {
    scanCount.value -= 1
    scanProgress.value = ((3 - scanCount.value) / 3) * 100
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
