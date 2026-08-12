<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Kunjungan Lapangan</h1>
        <p class="text-sm text-gray-500">Rekam jejak kunjungan karyawan</p>
      </div>
    </div>

    <!-- Filter -->
    <div class="mb-4 flex flex-wrap gap-3">
      <select v-model="filters.employee_id" class="input !w-auto" @change="load">
        <option value="">Semua Karyawan</option>
        <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }}</option>
      </select>
      <input v-model="filters.date" type="date" class="input !w-auto" @change="load" />
      <button class="btn-secondary" @click="resetFilters">Reset</button>
    </div>

    <SkeletonLoader v-if="loading" />

    <EmptyState
      v-else-if="visits.length === 0"
      icon="📍"
      title="Belum ada kunjungan"
      description="Kunjungan lapangan karyawan akan muncul di sini."
    />

    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div v-for="v in visits" :key="v.id" class="card overflow-hidden">
        <button type="button" class="block w-full bg-gray-100" @click="lightbox = v">
          <img
            v-if="v.photo"
            :src="v.photo"
            :alt="`Kunjungan ${v.employee?.name}`"
            class="h-44 w-full object-cover transition hover:opacity-90"
          />
          <div v-else class="flex h-44 items-center justify-center text-3xl text-gray-300">📷</div>
        </button>
        <div class="p-4">
          <div class="mb-1 flex items-center justify-between">
            <p class="font-medium text-gray-900">{{ v.employee?.name || '—' }}</p>
            <p class="text-xs text-gray-400">{{ formatDateTime(v.visited_at) }}</p>
          </div>
          <p class="text-xs text-gray-500">{{ v.employee?.position || '' }}</p>
          <p v-if="v.notes" class="mt-2 text-sm text-gray-600">{{ v.notes }}</p>
          <p v-if="v.latitude" class="mt-2 text-xs text-gray-400">
            {{ Number(v.latitude).toFixed(5) }}, {{ Number(v.longitude).toFixed(5) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Lightbox foto -->
    <Teleport to="body">
      <div v-if="lightbox" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/80 p-4" @click="lightbox = null">
        <div class="relative max-h-[90vh] w-full max-w-lg">
          <button
            type="button"
            class="absolute -top-3 right-0 flex h-9 w-9 items-center justify-center rounded-full bg-white text-gray-700 shadow"
            @click="lightbox = null"
          >
            ✕
          </button>
          <img v-if="lightbox.photo" :src="lightbox.photo" class="max-h-[80vh] w-full rounded-xl object-contain" />
          <div class="mt-3 rounded-xl bg-white/95 p-4">
            <p class="text-sm font-medium text-gray-800">{{ lightbox.employee?.name }} — {{ formatDateTime(lightbox.visited_at) }}</p>
            <p v-if="lightbox.notes" class="mt-1 text-sm text-gray-600">{{ lightbox.notes }}</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface Visit {
  id: number
  photo?: string | null
  notes?: string | null
  latitude?: string | number | null
  longitude?: string | number | null
  visited_at: string
  employee?: { name?: string; position?: string } | null
}

const toast = useToast()
const loading = ref(true)
const visits = ref<Visit[]>([])
const employees = ref<{ id: number; name: string }[]>([])
const lightbox = ref<Visit | null>(null)

const filters = reactive({ employee_id: '' as string | number, date: '' })

async function load() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.employee_id) params.set('employee_id', String(filters.employee_id))
    if (filters.date) params.set('date', filters.date)
    const qs = params.toString() ? `?${params}` : ''
    const data = await api<{ data: Visit[] }>('GET', `/visits${qs}`)
    visits.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat kunjungan.'))
  } finally {
    loading.value = false
  }
}

async function loadEmployees() {
  try {
    const data = await api<{ data: { id: number; name: string }[] }>('GET', '/employees?status=active')
    employees.value = data.data
  } catch {
    employees.value = []
  }
}

function resetFilters() {
  filters.employee_id = ''
  filters.date = ''
  load()
}

function formatDateTime(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) +
    ' ' + new Date(d).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  load()
  loadEmployees()
})
</script>
