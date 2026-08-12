<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button class="btn-secondary !px-3 !py-2" @click="navigateTo('/admin/employees')">←</button>
        <div>
          <h1 class="text-xl font-semibold text-gray-900">{{ employee?.name || 'Karyawan' }}</h1>
          <p class="text-sm text-gray-500">{{ employee?.position || '—' }} · {{ roleLabel(employee?.mobile_role) }}</p>
        </div>
      </div>
      <span class="rounded-full px-3 py-1 text-xs font-medium" :class="employee?.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
        {{ employee?.status === 'active' ? 'Aktif' : 'Nonaktif' }}
      </span>
    </div>

    <SkeletonLoader v-if="loading" />

    <div v-else-if="!employee" class="card p-10 text-center text-sm text-gray-400">Karyawan tidak ditemukan.</div>

    <template v-else>
      <!-- Tab bar -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="t in tabs"
          :key="t.value"
          type="button"
          class="rounded-full px-4 py-1.5 text-sm font-medium transition"
          :class="tab === t.value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          @click="tab = t.value"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- Biodata -->
      <div v-if="tab === 'biodata'" class="card max-w-2xl p-6">
        <div class="mb-5 flex items-center gap-4">
          <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-primary-100 text-2xl font-bold text-primary-700">
            <img v-if="employee.photo" :src="employee.photo" alt="" class="h-full w-full object-cover" />
            <span v-else>{{ employee.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div>
            <p class="text-lg font-semibold text-gray-900">{{ employee.name }}</p>
            <p class="text-sm text-gray-500">{{ employee.user?.email || 'Belum ada akun ter-link' }}</p>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <InfoRow label="Jabatan" :value="employee.position || '—'" />
          <InfoRow label="Role Mobile" :value="roleLabel(employee.mobile_role)" />
          <InfoRow label="Lokasi Kerja" :value="employee.work_location?.name || '—'" />
          <InfoRow label="Shift" :value="employee.shift?.name || '—'" />
          <InfoRow label="Supervisor" :value="employee.supervisor?.name || '—'" />
          <InfoRow label="Group" :value="(employee.groups || []).map((g: any) => g.name).join(', ') || '—'" />
        </div>
      </div>

      <!-- Dokumen -->
      <div v-if="tab === 'dokumen'" class="card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-gray-900">Dokumen</h3>
        <div v-if="!employee.documents?.length" class="text-sm text-gray-400">Belum ada dokumen.</div>
        <div v-else class="space-y-2">
          <div v-for="doc in employee.documents" :key="doc.id" class="flex items-center gap-3 rounded-lg border border-gray-100 p-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50 text-primary-600">📄</div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-gray-800">{{ doc.title }}</p>
              <p class="text-xs text-gray-400">{{ doc.document_type }} · {{ doc.document_number || '—' }}</p>
            </div>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{{ doc.verification_status }}</span>
          </div>
        </div>
      </div>

      <!-- Detail personal -->
      <div v-if="tab === 'detail'" class="card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-gray-900">Detail Personal</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <InfoRow label="NIK" :value="employee.detail?.nik || '—'" />
          <InfoRow label="Gender" :value="genderLabel(employee.detail?.gender)" />
          <InfoRow label="Tempat Lahir" :value="employee.detail?.place_of_birth || '—'" />
          <InfoRow label="Tanggal Lahir" :value="formatDate(employee.detail?.date_of_birth)" />
          <InfoRow label="Agama" :value="employee.detail?.religion || '—'" />
          <InfoRow label="Status Nikah" :value="employee.detail?.marital_status || '—'" />
          <InfoRow label="No. HP" :value="employee.detail?.phone || '—'" />
          <InfoRow label="Email" :value="employee.detail?.email || '—'" />
          <InfoRow label="Alamat" :value="employee.detail?.address || '—'" />
        </div>
      </div>

      <!-- Bank -->
      <div v-if="tab === 'bank'" class="card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-gray-900">Rekening Bank</h3>
        <div v-if="!employee.banks?.length" class="text-sm text-gray-400">Belum ada data rekening.</div>
        <div v-else class="space-y-2">
          <div v-for="bank in employee.banks" :key="bank.id" class="flex items-center justify-between rounded-lg border border-gray-100 p-3">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ bank.bank_name }}</p>
              <p class="text-xs text-gray-400">{{ bank.account_number }} · {{ bank.account_holder }}</p>
            </div>
            <span v-if="bank.is_primary" class="rounded-full bg-primary-50 px-2 py-0.5 text-xs text-primary-600">Utama</span>
          </div>
        </div>
      </div>

      <!-- Keluarga -->
      <div v-if="tab === 'keluarga'" class="card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-gray-900">Keluarga</h3>
        <div v-if="!employee.families?.length" class="text-sm text-gray-400">Belum ada data keluarga.</div>
        <div v-else class="space-y-2">
          <div v-for="fam in employee.families" :key="fam.id" class="flex items-center justify-between rounded-lg border border-gray-100 p-3">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ fam.name }}</p>
              <p class="text-xs text-gray-400">{{ fam.relation }}</p>
            </div>
            <span class="text-xs text-gray-400">{{ fam.phone || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- Kontrak -->
      <div v-if="tab === 'kontrak'" class="card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-gray-900">Kontrak Kerja</h3>
        <div v-if="!employee.contracts?.length" class="text-sm text-gray-400">Belum ada data kontrak.</div>
        <div v-else class="space-y-2">
          <div v-for="c in employee.contracts" :key="c.id" class="rounded-lg border border-gray-100 p-3">
            <div class="flex items-center justify-between">
              <p class="text-sm font-medium text-gray-800">{{ c.contract_number }}</p>
              <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs uppercase text-gray-500">{{ c.type }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-400">
              {{ formatDate(c.start_date) }} – {{ formatDate(c.end_date) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Face -->
      <div v-if="tab === 'face'" class="card max-w-2xl p-6">
        <h3 class="mb-4 font-semibold text-gray-900">Face Recognition</h3>
        <div class="flex items-center gap-3 rounded-xl border p-4" :class="employee.face_template ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
          <div class="text-2xl">{{ employee.face_template ? '✅' : '⚠️' }}</div>
          <div>
            <p class="text-sm font-medium text-gray-800">
              {{ employee.face_template ? 'Wajah terdaftar' : 'Belum scan wajah' }}
            </p>
            <p v-if="employee.face_template" class="text-xs text-gray-500">
              Mode: {{ employee.face_template.mode }} · Diperbarui {{ formatDateTime(employee.face_template.updated_at) }}
            </p>
            <p v-else class="text-xs text-gray-500">Karyawan belum melakukan enroll wajah dari perangkat.</p>
          </div>
        </div>
      </div>

      <!-- Absensi 30 hari -->
      <div v-if="tab === 'absensi'" class="card max-w-2xl p-6">
        <h3 class="mb-1 font-semibold text-gray-900">Kehadiran 30 Hari Terakhir</h3>
        <p class="mb-4 text-xs text-gray-400">{{ fromDate }} s/d {{ toDate }}</p>
        <div v-if="attendanceLoading" class="text-sm text-gray-400">Memuat…</div>
        <div v-else-if="!attendanceDays.length" class="text-sm text-gray-400">Belum ada data absensi pada rentang ini.</div>
        <div v-else class="grid grid-cols-10 gap-1.5">
          <div
            v-for="d in attendanceDays"
            :key="d.date"
            class="flex flex-col items-center rounded-lg p-1.5 text-center"
            :class="d.has ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-400'"
            :title="`${d.date}${d.clock_in ? ' · In ' + d.clock_in : ''}${d.clock_out ? ' · Out ' + d.clock_out : ''}`"
          >
            <span class="text-[10px] font-medium">{{ d.day }}</span>
            <span class="text-[9px] opacity-80">{{ d.clock_in || '—' }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

const route = useRoute()
const toast = useToast()

const tabs = [
  { value: 'biodata', label: 'Biodata' },
  { value: 'dokumen', label: 'Dokumen' },
  { value: 'detail', label: 'Detail' },
  { value: 'bank', label: 'Bank' },
  { value: 'keluarga', label: 'Keluarga' },
  { value: 'kontrak', label: 'Kontrak' },
  { value: 'face', label: 'Face' },
  { value: 'absensi', label: 'Absensi' },
]
const tab = ref('biodata')

const loading = ref(true)
const employee = ref<any>(null)

const attendanceLoading = ref(false)
const attendanceDays = ref<any[]>([])
const fromDate = ref('')
const toDate = ref('')

onMounted(async () => {
  try {
    const data = await api<{ data: any }>('GET', `/employees/${route.params.id}`)
    employee.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat detail karyawan.'))
  } finally {
    loading.value = false
  }
  loadAttendance()
})

async function loadAttendance() {
  attendanceLoading.value = true
  try {
    const to = new Date()
    const from = new Date()
    from.setDate(from.getDate() - 29)
    const fmt = (d: Date) => d.toISOString().substring(0, 10)
    fromDate.value = fmt(from)
    toDate.value = fmt(to)
    const res = await api<{ data: { employees: any[] } }>('GET', `/attendance/roster?from=${fmt(from)}&to=${fmt(to)}`)
    const row = (res.data?.employees || []).find((e: any) => e.id === Number(route.params.id))
    if (row?.days) {
      attendanceDays.value = row.days.map((d: any, i: number) => ({
        date: '',
        day: '',
        clock_in: d.clock_in,
        clock_out: d.clock_out,
        has: !!d.clock_in,
      }))
      const days = res.data?.dates || []
      attendanceDays.value = row.days.map((d: any, i: number) => {
        const date = new Date(days[i] + 'T00:00:00')
        return {
          date: days[i],
          day: date.getDate(),
          clock_in: d.clock_in,
          clock_out: d.clock_out,
          has: !!d.clock_in,
        }
      })
    }
  } catch {
    attendanceDays.value = []
  } finally {
    attendanceLoading.value = false
  }
}

function roleLabel(r: string | undefined) {
  return { karyawan: 'Karyawan', supervisor: 'Supervisor', management: 'Management' }[r || ''] || '—'
}

function genderLabel(g: string | undefined) {
  return g === 'L' ? 'Laki-laki' : g === 'P' ? 'Perempuan' : '—'
}

function formatDate(d: string | undefined) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateTime(d: string | undefined) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}
</script>
