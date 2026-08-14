<template>
  <div>
    <div class="mb-4 flex items-center justify-between">
      <div>
        <h3 class="text-sm font-semibold text-gray-900">Titik Lokasi Kerja</h3>
        <p class="text-xs text-gray-400">Atur titik GPS dan radius untuk validasi absen</p>
      </div>
      <button class="btn-primary !py-2 !text-xs" @click="openCreate">+ Tambah</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <EmptyState v-else-if="locations.length === 0" icon="📍" title="Belum ada lokasi kerja" description="Tambahkan minimal satu lokasi kerja agar karyawan bisa absen dengan GPS." />

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Nama</th>
            <th class="px-4 py-3 font-medium">Koordinat</th>
            <th class="px-4 py-3 font-medium">Radius</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="loc in locations" :key="loc.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ loc.name }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ loc.latitude }}, {{ loc.longitude }}</td>
            <td class="px-4 py-3 text-gray-600">{{ loc.radius_meter }} m</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="loc.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ loc.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(loc)">Edit</button>
                <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(loc)">Hapus</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal form -->
    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Lokasi' : 'Edit Lokasi'" wide @close="modal.open = false">
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label class="label">Nama <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" class="input" placeholder="mis. Kantor Pusat" required />
        </div>

        <div class="mb-2 flex items-end gap-2">
          <div class="flex-1">
            <label class="label">Latitude <span class="text-red-500">*</span></label>
            <input v-model.number="form.latitude" type="number" step="any" class="input" required />
          </div>
          <div class="flex-1">
            <label class="label">Longitude <span class="text-red-500">*</span></label>
            <input v-model.number="form.longitude" type="number" step="any" class="input" required />
          </div>
        </div>
        <button
          type="button"
          class="mb-2 inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-200"
          :disabled="locating"
          @click="useMyLocation"
        >
          <span v-if="locating" class="h-3 w-3 animate-spin rounded-full border border-gray-500 border-t-transparent"></span>
          <span v-else>📍</span>
          {{ locating ? 'Mencari…' : 'Ambil dari lokasi saya' }}
        </button>

        <div class="relative z-0 mb-4 h-56 overflow-hidden rounded-lg border border-gray-200">
          <div ref="mapEl" class="h-full w-full"></div>
          <p class="pointer-events-none absolute bottom-1 left-2 rounded bg-white/80 px-1.5 text-[10px] text-gray-600">
            Klik peta untuk pilih titik
          </p>
        </div>

        <div class="mb-4">
          <label class="label">Radius (meter)</label>
          <input v-model.number="form.radius_meter" type="number" min="10" class="input" />
          <p class="mt-1 text-xs text-gray-400">Default 100 m. Jarak lebih dari ini akan ditolak.</p>
        </div>

        <label class="mb-4 flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
          Aktif
        </label>

        <p v-if="formError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="modal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Menyimpan…' : 'Simpan' }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import 'leaflet/dist/leaflet.css'

interface WorkLocation {
  id: number
  name: string
  latitude: number
  longitude: number
  radius_meter: number
  is_active: boolean
}

const { data, refresh, pending: loading } = useApi<{ data: WorkLocation[] }>('/work-locations')
const locations = computed(() => data.value?.data || [])

const modal = reactive<{ open: boolean; mode: 'create' | 'edit'; id: number | null }>({
  open: false,
  mode: 'create',
  id: null,
})
const form = reactive({
  name: '',
  latitude: null as number | null,
  longitude: null as number | null,
  radius_meter: 100,
  is_active: true,
})
const formError = ref('')
const saving = ref(false)
const locating = ref(false)

// --- Leaflet map (klik peta → set titik) ---
const mapEl = ref<HTMLDivElement | null>(null)
let leaflet: any = null
let map: any = null
let marker: any = null

function pinIcon(L: any) {
  return L.divIcon({
    className: '',
    html: `
      <svg width="34" height="34" viewBox="0 0 24 24" fill="#0f766e" stroke="#ffffff" stroke-width="1.5" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.4))">
        <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/>
        <circle cx="12" cy="10" r="2.6" fill="#ffffff" stroke="none"/>
      </svg>`,
    iconSize: [34, 34],
    iconAnchor: [17, 32],
  })
}

function syncMapToForm() {
  if (!map || !leaflet) return
  const lat = form.latitude
  const lng = form.longitude
  const hasPoint = lat != null && lng != null
  map.setView(hasPoint ? [lat, lng] : [-2.5, 118], hasPoint ? 17 : 5)
  if (marker) {
    marker.remove()
    marker = null
  }
  if (hasPoint) {
    marker = leaflet.marker([lat, lng], { icon: pinIcon(leaflet) }).addTo(map)
  }
}

function setPoint(lat: number, lng: number) {
  form.latitude = Number(lat.toFixed(7))
  form.longitude = Number(lng.toFixed(7))
  syncMapToForm()
}

async function initMap() {
  if (import.meta.server) return
  leaflet = leaflet || (await import('leaflet'))
  if (!mapEl.value || !leaflet) return
  if (!map) {
    map = leaflet.map(mapEl.value)
    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map)
    map.on('click', (e: any) => {
      setPoint(e.latlng.lat, e.latlng.lng)
      map.setView(e.latlng, Math.max(map.getZoom(), 16))
    })
  }
  syncMapToForm()
  // Modal pakai Teleport — tunggu layout selesai biar tile gak miring
  setTimeout(() => map?.invalidateSize(), 100)
}

watch(
  () => modal.open,
  (open) => {
    if (open) {
      nextTick(() => initMap())
    }
  },
)

onBeforeUnmount(() => {
  map?.remove()
  map = null
  marker = null
})

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.name = ''
  form.latitude = null
  form.longitude = null
  form.radius_meter = 100
  form.is_active = true
  formError.value = ''
  modal.open = true
}

function openEdit(loc: WorkLocation) {
  modal.mode = 'edit'
  modal.id = loc.id
  form.name = loc.name
  form.latitude = loc.latitude
  form.longitude = loc.longitude
  form.radius_meter = loc.radius_meter
  form.is_active = loc.is_active
  formError.value = ''
  modal.open = true
}

async function submitForm() {
  formError.value = ''
  saving.value = true
  const payload = {
    name: form.name,
    latitude: form.latitude,
    longitude: form.longitude,
    radius_meter: form.radius_meter,
    is_active: form.is_active,
  }
  try {
    if (modal.mode === 'create') {
      await api('POST', '/work-locations', payload)
    } else {
      await api('PUT', `/work-locations/${modal.id}`, payload)
    }
    modal.open = false
    await refresh()
  } catch (e: any) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(loc: WorkLocation) {
  if (!confirm(`Hapus lokasi "${loc.name}"?`)) return
  try {
    await api('DELETE', `/work-locations/${loc.id}`)
    await refresh()
  } catch (e: any) {
    alert(errorMessage(e))
  }
}

function useMyLocation() {
  // Browser cuma kasih akses GPS di secure context (HTTPS atau localhost).
  // http://{slug}-absensi.test:8000 (dev) = bukan secure context → diblokir.
  if (!window.isSecureContext) {
    formError.value = 'Akses GPS butuh HTTPS (atau localhost). Buka lewat https:// — di dev gunakan localhost atau flag Chrome insecure-origin.'
    return
  }
  if (!('geolocation' in navigator)) {
    formError.value = 'Browser tidak mendukung GPS.'
    return
  }
  locating.value = true
  formError.value = ''
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.latitude = Number(pos.coords.latitude.toFixed(7))
      form.longitude = Number(pos.coords.longitude.toFixed(7))
      syncMapToForm()
      locating.value = false
    },
    (err) => {
      const messages: Record<number, string> = {
        1: 'Izin GPS ditolak. Klik ikon 🔒 di address bar → izinkan akses Lokasi, lalu coba lagi.',
        2: 'Posisi tidak tersedia. Nyalakan GPS/lokasi perangkat & coba di luar ruangan.',
        3: 'Waktu mencari lokasi habis. Coba lagi.',
      }
      formError.value = messages[err?.code] || 'Gagal mendapatkan lokasi. Periksa izin GPS browser.'
      locating.value = false
    },
    { enableHighAccuracy: true, timeout: 15_000 },
  )
}
</script>
