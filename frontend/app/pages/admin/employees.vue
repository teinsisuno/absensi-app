<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Karyawan</h1>
        <p class="text-sm text-gray-500">Kelola data karyawan dan kode unik</p>
      </div>
      <button class="btn-primary" @click="openCreate">+ Tambah</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <div v-else-if="employees.length === 0" class="card p-10 text-center text-sm text-gray-400">
      Belum ada karyawan. Klik “Tambah” untuk menambah.
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[640px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Nama</th>
            <th class="px-4 py-3 font-medium">Jabatan</th>
            <th class="px-4 py-3 font-medium">Lokasi</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Akun</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="emp in employees" :key="emp.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-primary-100 text-sm font-bold text-primary-700">
                  <img v-if="emp.photo" :src="emp.photo" alt="" class="h-full w-full object-cover" />
                  <span v-else>{{ emp.name.charAt(0).toUpperCase() }}</span>
                </div>
                <div>
                  <NuxtLink :to="`/admin/employees/${emp.id}`" class="font-medium text-gray-900 hover:text-primary-600">{{ emp.name }}</NuxtLink>
                  <p v-if="emp.detail?.nik" class="text-xs text-gray-400">NIK: {{ emp.detail.nik }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ emp.position || '—' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ emp.work_location?.name || '—' }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="emp.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ emp.status === 'active' ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span v-if="emp.user" class="text-xs text-green-700">
                ✓ {{ emp.user.name }}
              </span>
              <span v-else class="text-xs text-gray-400">Belum link</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <button
                  class="rounded-lg px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent"
                  :disabled="!!emp.user"
                  :title="emp.user ? 'Akun sudah ter-link' : 'Generate kode unik untuk link akun karyawan'"
                  @click="generateInvite(emp)"
                >
                  Kode Unik
                </button>
                <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(emp)">Edit</button>
                <button
                  class="rounded-lg px-2 py-1 text-xs hover:bg-red-50"
                  :class="emp.status === 'active' ? 'text-red-600' : 'text-gray-500'"
                  @click="toggleStatus(emp)"
                >
                  {{ emp.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal form tambah/edit -->
    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Karyawan' : 'Edit Karyawan'" wide @close="modal.open = false">
      <form @submit.prevent="submitForm">
        <div class="max-h-[70vh] overflow-y-auto pr-1">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Data Kepegawaian</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label">Nama <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" class="input" required />
          </div>
          <div>
            <label class="label">Jabatan</label>
            <input v-model="form.position" type="text" class="input" placeholder="mis. Kasir" />
          </div>
          <div>
            <label class="label">Role Mobile</label>
            <select v-model="form.mobile_role" class="input">
              <option value="karyawan">Karyawan</option>
              <option value="supervisor">Supervisor</option>
              <option value="management">Management</option>
            </select>
          </div>
          <div>
            <label class="label">Lokasi Kerja</label>
            <select v-model="form.work_location_id" class="input">
              <option :value="null">— Tanpa lokasi —</option>
              <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Shift</label>
            <select v-model="form.shift_id" class="input">
              <option :value="null">— Tanpa shift —</option>
              <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input">
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>

        <p class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Data Pribadi</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">NIK</label>
            <input v-model="form.nik" type="text" class="input" placeholder="16 digit" />
          </div>
          <div>
            <label class="label">Jenis Kelamin</label>
            <select v-model="form.gender" class="input">
              <option value="">—</option>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div>
            <label class="label">Tempat Lahir</label>
            <input v-model="form.place_of_birth" type="text" class="input" />
          </div>
          <div>
            <label class="label">Tanggal Lahir</label>
            <input v-model="form.date_of_birth" type="date" class="input" />
          </div>
          <div>
            <label class="label">Agama</label>
            <input v-model="form.religion" type="text" class="input" placeholder="mis. Islam" />
          </div>
          <div>
            <label class="label">Status Nikah</label>
            <select v-model="form.marital_status" class="input">
              <option value="">—</option>
              <option value="Belum Menikah">Belum Menikah</option>
              <option value="Menikah">Menikah</option>
              <option value="Cerai">Cerai</option>
              <option value="Cerai Mati">Cerai Mati</option>
            </select>
          </div>
          <div>
            <label class="label">No. HP</label>
            <input v-model="form.phone" type="text" class="input" />
          </div>
          <div>
            <label class="label">Email</label>
            <input v-model="form.email" type="email" class="input" />
          </div>
          <div>
            <label class="label">Kontak Darurat</label>
            <input v-model="form.emergency_contact_name" type="text" class="input" />
          </div>
          <div>
            <label class="label">HP Darurat</label>
            <input v-model="form.emergency_contact_phone" type="text" class="input" />
          </div>
          <div class="sm:col-span-2">
            <label class="label">Alamat</label>
            <textarea v-model="form.address" rows="2" class="input"></textarea>
          </div>
        </div>

        <p v-if="formError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="modal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Menyimpan…' : 'Simpan' }}
          </button>
        </div>
      </form>
    </AppModal>

    <!-- Modal kode unik sekali tampil -->
    <AppModal v-if="inviteModal.open" title="Kode Unik Karyawan" @close="inviteModal.open = false">
      <div class="text-center">
        <p class="mb-1 text-sm text-gray-500">Kode unik untuk <b>{{ inviteModal.name }}</b></p>
        <p class="mb-4 text-3xl font-bold tracking-widest text-indigo-700">{{ inviteModal.code }}</p>
        <div class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">
          ⚠️ Kode hanya ditampilkan <b>sekali</b> dan berlaku sampai
          <b>{{ formatDate(inviteModal.expiresAt) }}</b>. Bagikan ke karyawan lewat chat/SMS —
          dia memakainya saat registrasi untuk menautkan akunnya.
        </div>
        <div class="flex justify-center gap-2">
          <button class="btn-primary" @click="copyCode">📋 Salin Kode</button>
          <button class="btn-secondary" @click="inviteModal.open = false">Tutup</button>
        </div>
        <p v-if="copied" class="mt-3 text-sm text-green-600">Kode tersalin! ✓</p>
      </div>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface Employee {
  id: number
  name: string
  photo?: string | null
  position?: string | null
  mobile_role?: string | null
  work_location_id?: number | null
  shift_id?: number | null
  supervisor_id?: number | null
  status: string
  detail?: {
    nik?: string | null
    gender?: string | null
    religion?: string | null
    marital_status?: string | null
    place_of_birth?: string | null
    date_of_birth?: string | null
    phone?: string | null
    email?: string | null
    address?: string | null
    emergency_contact_name?: string | null
    emergency_contact_phone?: string | null
  } | null
  work_location?: { id: number; name: string } | null
  shift?: any
  user?: { id: number; name: string; email: string } | null
}

interface WorkLocation {
  id: number
  name: string
}

const { data, refresh, pending: loading } = useApi<{ data: Employee[] }>('/employees')
const { data: locData } = useApi<{ data: WorkLocation[] }>('/work-locations')
const { data: shiftData } = useApi<{ data: any[] }>('/shifts')

const employees = computed(() => data.value?.data || [])
const locations = computed(() => locData.value?.data || [])
const shifts = computed(() => shiftData.value?.data || [])

const modal = reactive<{ open: boolean; mode: 'create' | 'edit'; id: number | null }>({
  open: false,
  mode: 'create',
  id: null,
})
const form = reactive({
  name: '',
  position: '',
  mobile_role: 'karyawan',
  work_location_id: null as number | null,
  shift_id: null as number | null,
  status: 'active',
  nik: '',
  gender: '' as '' | 'L' | 'P',
  religion: '',
  marital_status: '',
  place_of_birth: '',
  date_of_birth: '',
  phone: '',
  email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',
})
const formError = ref('')
const saving = ref(false)

const inviteModal = reactive<{ open: boolean; code: string; name: string; expiresAt: string | null }>({
  open: false,
  code: '',
  name: '',
  expiresAt: null,
})
const copied = ref(false)

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.name = ''
  form.position = ''
  form.mobile_role = 'karyawan'
  form.work_location_id = null
  form.shift_id = null
  form.status = 'active'
  form.nik = ''
  form.gender = ''
  form.religion = ''
  form.marital_status = ''
  form.place_of_birth = ''
  form.date_of_birth = ''
  form.phone = ''
  form.email = ''
  form.address = ''
  form.emergency_contact_name = ''
  form.emergency_contact_phone = ''
  formError.value = ''
  modal.open = true
}

function openEdit(emp: Employee) {
  modal.mode = 'edit'
  modal.id = emp.id
  form.name = emp.name
  form.position = emp.position || ''
  form.mobile_role = emp.mobile_role || 'karyawan'
  form.work_location_id = emp.work_location_id ?? null
  form.shift_id = emp.shift_id ?? null
  form.status = emp.status
  const d = emp.detail || {}
  form.nik = d.nik || ''
  form.gender = (d.gender as any) || ''
  form.religion = d.religion || ''
  form.marital_status = d.marital_status || ''
  form.place_of_birth = d.place_of_birth || ''
  form.date_of_birth = d.date_of_birth ? d.date_of_birth.substring(0, 10) : ''
  form.phone = d.phone || ''
  form.email = d.email || ''
  form.address = d.address || ''
  form.emergency_contact_name = d.emergency_contact_name || ''
  form.emergency_contact_phone = d.emergency_contact_phone || ''
  formError.value = ''
  modal.open = true
}

function buildPayload() {
  return {
    name: form.name,
    position: form.position || null,
    mobile_role: form.mobile_role,
    work_location_id: form.work_location_id,
    shift_id: form.shift_id,
    status: form.status,
    detail: {
      nik: form.nik || null,
      gender: form.gender || null,
      religion: form.religion || null,
      marital_status: form.marital_status || null,
      place_of_birth: form.place_of_birth || null,
      date_of_birth: form.date_of_birth || null,
      phone: form.phone || null,
      email: form.email || null,
      address: form.address || null,
      emergency_contact_name: form.emergency_contact_name || null,
      emergency_contact_phone: form.emergency_contact_phone || null,
    },
  }
}

async function submitForm() {
  formError.value = ''
  saving.value = true
  try {
    if (modal.mode === 'create') {
      await api('POST', '/employees', buildPayload())
      modal.open = false
    } else {
      await api('PUT', `/employees/${modal.id}`, buildPayload())
      modal.open = false
    }
    await refresh()
  } catch (e: any) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function toggleStatus(emp: Employee) {
  const next = emp.status === 'active' ? 'inactive' : 'active'
  const label = next === 'inactive' ? 'nonaktifkan' : 'aktifkan'
  if (!confirm(`${emp.name} akan di-${label}. Lanjut?`)) return
  try {
    if (next === 'inactive') {
      await api('DELETE', `/employees/${emp.id}`)
    } else {
      await api('PUT', `/employees/${emp.id}`, { status: 'active', name: emp.name })
    }
    await refresh()
  } catch (e: any) {
    alert(errorMessage(e))
  }
}

async function generateInvite(emp: Employee) {
  try {
    const res = await api<{ data: { code: string; expires_at: string } }>('POST', '/invite-codes', {
      employee_id: emp.id,
    })
    inviteModal.code = res.data.code
    inviteModal.name = emp.name
    inviteModal.expiresAt = res.data.expires_at
    inviteModal.open = true
    await refresh()
  } catch (e: any) {
    alert(errorMessage(e))
  }
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(inviteModal.code)
    copied.value = true
    setTimeout(() => (copied.value = false), 2500)
  } catch {
    // clipboard tidak tersedia — biarkan user menyalin manual
  }
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return value.substring(0, 10)
}
</script>
