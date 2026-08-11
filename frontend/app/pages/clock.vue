<template>
  <div class="space-y-4">
    <!-- Kartu identitas -->
    <div class="card p-5">
      <div class="flex items-center gap-4">
        <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-primary-100 text-xl font-bold text-primary-700">
          <img v-if="auth.employee?.photo" :src="auth.employee.photo" alt="foto" class="h-full w-full object-cover" />
          <span v-else>{{ initial }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-base font-semibold text-gray-900">{{ auth.employee?.name || 'Karyawan' }}</p>
          <p class="truncate text-sm text-gray-500">{{ auth.employee?.position || '—' }}</p>
        </div>
        <span
          class="rounded-full px-2.5 py-1 text-xs font-medium"
          :class="isWorking ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
        >
          {{ isWorking ? 'Sedang Bekerja' : 'Belum Masuk' }}
        </span>
      </div>
    </div>

    <!-- Kartu aksi clock -->
    <div class="card p-6 text-center">
      <p class="mb-1 text-sm text-gray-500">{{ todayLabel }}</p>
      <p class="mb-6 text-4xl font-bold tabular-nums text-gray-900">{{ clock }}</p>

      <p v-if="lastRecord?.work_location?.name" class="mb-4 text-xs text-gray-500">
        Lokasi: {{ lastRecord.work_location.name }}
      </p>

      <p v-if="message" class="mb-4 rounded-lg px-3 py-2 text-sm" :class="messageType === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700'">
        {{ message }}
      </p>

      <button
        v-if="!isWorking"
        type="button"
        class="w-full rounded-2xl bg-green-600 py-5 text-lg font-semibold text-white shadow-lg shadow-green-600/30 transition hover:bg-green-700 disabled:opacity-50"
        :disabled="busy"
        @click="clockIn"
      >
        <span v-if="busy && action === 'in'">Mencari lokasi…</span>
        <span v-else>🟢 Clock In</span>
      </button>

      <button
        v-else
        type="button"
        class="w-full rounded-2xl bg-red-600 py-5 text-lg font-semibold text-white shadow-lg shadow-red-600/30 transition hover:bg-red-700 disabled:opacity-50"
        :disabled="busy"
        @click="clockOut"
      >
        <span v-if="busy && action === 'out'">Memproses…</span>
        <span v-else>🔴 Clock Out</span>
      </button>
    </div>

    <!-- Riwayat hari ini -->
    <div class="card p-5">
      <h2 class="mb-3 text-sm font-semibold text-gray-700">Riwayat Hari Ini</h2>
      <p v-if="records.length === 0" class="py-4 text-center text-sm text-gray-400">Belum ada catatan absen hari ini.</p>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="r in records" :key="r.id" class="flex items-center justify-between py-2.5 text-sm">
          <span class="flex items-center gap-2">
            <span class="text-base">{{ r.type === 'clock_in' ? '🟢' : '🔴' }}</span>
            <span class="text-gray-700">{{ r.type === 'clock_in' ? 'Masuk' : 'Keluar' }}</span>
          </span>
          <span class="text-gray-500">
            {{ timeOf(r.recorded_at) }}
            <span v-if="r.distance_meter != null" class="ml-2 text-xs text-gray-400">({{ Math.round(r.distance_meter) }} m)</span>
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'clock', middleware: 'guard' })

const auth = useAuthStore()

// Admin tanpa akun karyawan tidak bisa clock — arahkan ke dashboard admin
if (import.meta.client && auth.isAdmin && !auth.isEmployee) {
  navigateTo('/admin/employees')
}

const dateStr = computed(() => new Date().toISOString().slice(0, 10))
const { data, refresh } = useApi<any[]>(() => `/attendance/me?date=${dateStr.value}`)
const records = computed(() => data.value || [])
const lastRecord = computed(() => records.value[0] || null)
const isWorking = computed(() => lastRecord.value?.type === 'clock_in')

const clock = ref('')
const todayLabel = computed(() =>
  new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
)
const busy = ref(false)
const action = ref<'in' | 'out'>('in')
const message = ref('')
const messageType = ref<'success' | 'error'>('success')

const initial = computed(() => (auth.employee?.name || '?').charAt(0).toUpperCase())

onMounted(() => {
  const update = () => {
    clock.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  }
  update()
  const t = setInterval(update, 1000)
  onBeforeUnmount(() => clearInterval(t))
})

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

async function doClock(type: 'in' | 'out') {
  busy.value = true
  action.value = type
  message.value = ''
  messageType.value = 'success'
  try {
    const pos = await getPosition()
    action.value = type === 'in' ? 'in' : 'out'
    const res = await api<{ message: string; data: any }>(type === 'in' ? 'POST' : 'POST', `/attendance/clock-${type}`, {
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

function clockIn() {
  return doClock('in')
}
function clockOut() {
  return doClock('out')
}

function timeOf(iso: string) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}
</script>
