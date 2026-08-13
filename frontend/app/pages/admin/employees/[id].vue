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
        <div class="mb-5 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-primary-100 text-2xl font-bold text-primary-700">
              <img v-if="employee.photo" :src="employee.photo" alt="" class="h-full w-full object-cover" />
              <span v-else>{{ employee.name.charAt(0).toUpperCase() }}</span>
            </div>
            <div>
              <p class="text-lg font-semibold text-gray-900">{{ employee.name }}</p>
              <p class="text-sm text-gray-500">{{ employee.user?.email || 'Belum ada akun ter-link' }}</p>
            </div>
          </div>
          <button class="btn-secondary !px-3 !py-1.5 text-sm" @click="openEmpEdit">✏️ Edit</button>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <InfoRow icon="id" label="Jabatan" :value="employee.position || '—'" />
          <InfoRow icon="shift" label="Role Mobile" :value="roleLabel(employee.mobile_role)" />
          <InfoRow label="Lokasi Kerja" :value="employee.work_location?.name || '—'" />
          <InfoRow label="Shift" :value="employee.shift?.name || '—'" />
          <InfoRow label="Supervisor" :value="employee.supervisor?.name || '—'" />
          <InfoRow label="Group" :value="(employee.groups || []).map((g: any) => g.name).join(', ') || '—'" />
        </div>
      </div>

      <!-- Dokumen -->
      <EmployeeDocumentEditor v-if="tab === 'dokumen'" :employee-id="route.params.id" />

      <!-- Detail personal -->
      <div v-if="tab === 'detail'" class="card max-w-2xl p-6">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="font-semibold text-gray-900">Detail Personal</h3>
          <button class="btn-secondary !px-3 !py-1.5 text-sm" @click="openDetailEdit">✏️ Edit</button>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <InfoRow label="NIK" :value="employee.detail?.nik || '—'" />
          <InfoRow label="Gender" :value="genderLabel(employee.detail?.gender)" />
          <InfoRow label="Tempat Lahir" :value="employee.detail?.place_of_birth || '—'" />
          <InfoRow label="Tanggal Lahir" :value="formatDate(employee.detail?.date_of_birth)" />
          <InfoRow label="Agama" :value="employee.detail?.religion || '—'" />
          <InfoRow label="Status Nikah" :value="employee.detail?.marital_status || '—'" />
          <InfoRow label="Gol. Darah" :value="employee.detail?.blood_type || '—'" />
          <InfoRow label="No. HP" :value="employee.detail?.phone || '—'" />
          <InfoRow label="Email" :value="employee.detail?.email || '—'" />
          <InfoRow label="NPWP" :value="employee.detail?.npwp || '—'" />
          <InfoRow label="Kontak Darurat" :value="employee.detail?.emergency_contact_name || '—'" />
          <InfoRow label="HP Darurat" :value="employee.detail?.emergency_contact_phone || '—'" />
          <InfoRow label="Alamat" :value="employee.detail?.address || '—'" />
        </div>
      </div>

      <!-- Bank -->
      <EmployeeBankEditor v-if="tab === 'bank'" :employee-id="route.params.id" />

      <!-- Keluarga -->
      <EmployeeFamilyEditor v-if="tab === 'keluarga'" :employee-id="route.params.id" />

      <!-- Kontrak -->
      <EmployeeContractEditor v-if="tab === 'kontrak'" :employee-id="route.params.id" />

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

    <!-- Modal edit biodata kepegawaian -->
    <AppModal v-if="empModal.open" title="Edit Karyawan" @close="empModal.open = false">
      <form @submit.prevent="saveEmp">
        <div class="mb-4">
          <label class="label">Nama <span class="text-red-500">*</span></label>
          <input v-model="empForm.name" type="text" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Jabatan</label>
          <input v-model="empForm.position" type="text" class="input" placeholder="mis. Kasir" />
        </div>
        <div class="mb-4">
          <label class="label">Role Mobile</label>
          <select v-model="empForm.mobile_role" class="input">
            <option value="karyawan">Karyawan</option>
            <option value="supervisor">Supervisor</option>
            <option value="management">Management</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="label">Lokasi Kerja</label>
          <select v-model="empForm.work_location_id" class="input">
            <option :value="null">— Tanpa lokasi —</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="label">Shift</label>
          <select v-model="empForm.shift_id" class="input">
            <option :value="null">— Tanpa shift —</option>
            <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="label">Status</label>
          <select v-model="empForm.status" class="input">
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>

        <p v-if="empFormError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ empFormError }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="empModal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="empSaving">{{ empSaving ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
      </form>
    </AppModal>

    <!-- Modal edit detail personal -->
    <AppModal v-if="detailModal.open" title="Edit Detail Personal" wide @close="detailModal.open = false">
      <form @submit.prevent="saveDetail">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">NIK</label>
            <input v-model="detailForm.nik" type="text" class="input" />
          </div>
          <div>
            <label class="label">Jenis Kelamin</label>
            <select v-model="detailForm.gender" class="input">
              <option value="">—</option>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div>
            <label class="label">Tempat Lahir</label>
            <input v-model="detailForm.place_of_birth" type="text" class="input" />
          </div>
          <div>
            <label class="label">Tanggal Lahir</label>
            <input v-model="detailForm.date_of_birth" type="date" class="input" />
          </div>
          <div>
            <label class="label">Agama</label>
            <input v-model="detailForm.religion" type="text" class="input" placeholder="mis. Islam" />
          </div>
          <div>
            <label class="label">Status Nikah</label>
            <select v-model="detailForm.marital_status" class="input">
              <option value="">—</option>
              <option value="Belum Menikah">Belum Menikah</option>
              <option value="Menikah">Menikah</option>
              <option value="Cerai">Cerai</option>
              <option value="Cerai Mati">Cerai Mati</option>
            </select>
          </div>
          <div>
            <label class="label">Gol. Darah</label>
            <input v-model="detailForm.blood_type" type="text" class="input" placeholder="mis. O" />
          </div>
          <div>
            <label class="label">No. HP</label>
            <input v-model="detailForm.phone" type="text" class="input" />
          </div>
          <div>
            <label class="label">Email</label>
            <input v-model="detailForm.email" type="email" class="input" />
          </div>
          <div>
            <label class="label">NPWP</label>
            <input v-model="detailForm.npwp" type="text" class="input" placeholder="00.000.000.0-000.000" />
          </div>
          <div>
            <label class="label">Kontak Darurat</label>
            <input v-model="detailForm.emergency_contact_name" type="text" class="input" />
          </div>
          <div>
            <label class="label">HP Darurat</label>
            <input v-model="detailForm.emergency_contact_phone" type="text" class="input" />
          </div>
          <div class="sm:col-span-2">
            <label class="label">Alamat</label>
            <textarea v-model="detailForm.address" rows="2" class="input"></textarea>
          </div>
        </div>

        <p v-if="detailFormError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ detailFormError }}</p>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="detailModal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="detailSaving">{{ detailSaving ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
      </form>
    </AppModal>
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

const locations = ref<any[]>([])
const shifts = ref<any[]>([])

const attendanceLoading = ref(false)
const attendanceDays = ref<any[]>([])
const fromDate = ref('')
const toDate = ref('')

onMounted(async () => {
  await Promise.all([
    loadEmployee(),
    loadOptions(),
  ])
  loadAttendance()
})

async function loadEmployee() {
  try {
    const data = await api<{ data: any }>('GET', `/employees/${route.params.id}`)
    employee.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat detail karyawan.'))
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const [locRes, shiftRes] = await Promise.all([
      api<{ data: any[] }>('GET', '/work-locations'),
      api<{ data: any[] }>('GET', '/shifts'),
    ])
    locations.value = locRes.data
    shifts.value = shiftRes.data
  } catch {
    // options opsional — biarkan kosong
  }
}

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

/* ---------- Modal edit biodata kepegawaian ---------- */

const empModal = reactive<{ open: boolean }>({ open: false })
const empForm = reactive({
  name: '',
  position: '',
  mobile_role: 'karyawan',
  work_location_id: null as number | null,
  shift_id: null as number | null,
  status: 'active',
})
const empFormError = ref('')
const empSaving = ref(false)

function openEmpEdit() {
  empForm.name = employee.value.name
  empForm.position = employee.value.position || ''
  empForm.mobile_role = employee.value.mobile_role || 'karyawan'
  empForm.work_location_id = employee.value.work_location_id ?? null
  empForm.shift_id = employee.value.shift_id ?? null
  empForm.status = employee.value.status
  empFormError.value = ''
  empModal.open = true
}

async function saveEmp() {
  empFormError.value = ''
  empSaving.value = true
  try {
    await api('PUT', `/employees/${route.params.id}`, {
      name: empForm.name,
      position: empForm.position || null,
      mobile_role: empForm.mobile_role,
      work_location_id: empForm.work_location_id,
      shift_id: empForm.shift_id,
      status: empForm.status,
    })
    toast.success('Karyawan diperbarui.')
    empModal.open = false
    await loadEmployee()
  } catch (e: any) {
    empFormError.value = errorMessage(e)
  } finally {
    empSaving.value = false
  }
}

/* ---------- Modal edit detail personal ---------- */

const detailModal = reactive<{ open: boolean }>({ open: false })
const detailForm = reactive({
  nik: '',
  gender: '',
  religion: '',
  blood_type: '',
  marital_status: '',
  place_of_birth: '',
  date_of_birth: '',
  address: '',
  phone: '',
  email: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
  npwp: '',
})
const detailFormError = ref('')
const detailSaving = ref(false)

function openDetailEdit() {
  const d = employee.value.detail || {}
  detailForm.nik = d.nik || ''
  detailForm.gender = d.gender || ''
  detailForm.religion = d.religion || ''
  detailForm.blood_type = d.blood_type || ''
  detailForm.marital_status = d.marital_status || ''
  detailForm.place_of_birth = d.place_of_birth || ''
  detailForm.date_of_birth = d.date_of_birth ? d.date_of_birth.substring(0, 10) : ''
  detailForm.address = d.address || ''
  detailForm.phone = d.phone || ''
  detailForm.email = d.email || ''
  detailForm.emergency_contact_name = d.emergency_contact_name || ''
  detailForm.emergency_contact_phone = d.emergency_contact_phone || ''
  detailForm.npwp = d.npwp || ''
  detailFormError.value = ''
  detailModal.open = true
}

async function saveDetail() {
  detailFormError.value = ''
  detailSaving.value = true
  try {
    await api('PUT', `/employees/${route.params.id}/detail`, {
      nik: detailForm.nik || null,
      gender: detailForm.gender || null,
      religion: detailForm.religion || null,
      blood_type: detailForm.blood_type || null,
      marital_status: detailForm.marital_status || null,
      place_of_birth: detailForm.place_of_birth || null,
      date_of_birth: detailForm.date_of_birth || null,
      address: detailForm.address || null,
      phone: detailForm.phone || null,
      email: detailForm.email || null,
      emergency_contact_name: detailForm.emergency_contact_name || null,
      emergency_contact_phone: detailForm.emergency_contact_phone || null,
      npwp: detailForm.npwp || null,
    })
    toast.success('Detail personal diperbarui.')
    detailModal.open = false
    await loadEmployee()
  } catch (e: any) {
    detailFormError.value = errorMessage(e)
  } finally {
    detailSaving.value = false
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
