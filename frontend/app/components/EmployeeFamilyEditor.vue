<template>
  <div class="card max-w-2xl p-6">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900">Keluarga</h3>
      <button class="btn-primary !px-3 !py-1.5 text-sm" @click="openCreate">+ Tambah</button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400">Memuat…</div>
    <div v-else-if="!items.length" class="text-sm text-gray-400">Belum ada data keluarga.</div>
    <div v-else class="space-y-2">
      <div
        v-for="fam in items"
        :key="fam.id"
        class="flex items-center justify-between rounded-lg border border-gray-100 p-3"
      >
        <div>
          <p class="text-sm font-medium text-gray-800">{{ fam.name }}</p>
          <p class="text-xs text-gray-400">
            {{ fam.relation }}<span v-if="fam.is_dependent"> · Tanggungan</span><span v-if="fam.is_emergency_contact"> · Kontak darurat</span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-400">{{ fam.phone || fam.emergency_phone || '—' }}</span>
          <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(fam)">Edit</button>
          <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(fam)">Hapus</button>
        </div>
      </div>
    </div>

    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Keluarga' : 'Edit Keluarga'" @close="modal.open = false">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Hubungan <span class="text-red-500">*</span></label>
            <select v-model="form.relation" class="input" required>
              <option value="Suami">Suami</option>
              <option value="Istri">Istri</option>
              <option value="Anak">Anak</option>
              <option value="Orang Tua">Orang Tua</option>
              <option value="Saudara">Saudara</option>
            </select>
          </div>
          <div>
            <label class="label">Nama <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" class="input" required />
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
            <label class="label">NIK</label>
            <input v-model="form.nik" type="text" class="input" />
          </div>
          <div>
            <label class="label">Tanggal Lahir</label>
            <input v-model="form.date_of_birth" type="date" class="input" />
          </div>
          <div>
            <label class="label">Pendidikan</label>
            <input v-model="form.education_level" type="text" class="input" placeholder="mis. SMA" />
          </div>
          <div>
            <label class="label">Pekerjaan</label>
            <input v-model="form.occupation" type="text" class="input" />
          </div>
          <div>
            <label class="label">No. HP Darurat</label>
            <input v-model="form.emergency_phone" type="text" class="input" />
          </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-700">
          <label class="flex items-center gap-2">
            <input v-model="form.is_dependent" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
            Tanggungan (pajak/BPJS)
          </label>
          <label class="flex items-center gap-2">
            <input v-model="form.is_emergency_contact" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
            Kontak darurat
          </label>
        </div>

        <p v-if="formError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>

        <div class="mt-4 flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="modal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ employeeId: number | string }>()
const toast = useToast()
const { confirm } = useConfirm()

const loading = ref(true)
const items = ref<any[]>([])
const modal = reactive<{ open: boolean; mode: 'create' | 'edit'; id: number | null }>({
  open: false,
  mode: 'create',
  id: null,
})
const form = reactive({
  relation: 'Istri',
  name: '',
  gender: '',
  nik: '',
  date_of_birth: '',
  education_level: '',
  occupation: '',
  is_dependent: false,
  is_emergency_contact: false,
  emergency_phone: '',
})
const formError = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api<{ data: any[] }>('GET', `/employees/${props.employeeId}/families`)
    items.value = res.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat data keluarga.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.relation = 'Istri'
  form.name = ''
  form.gender = ''
  form.nik = ''
  form.date_of_birth = ''
  form.education_level = ''
  form.occupation = ''
  form.is_dependent = false
  form.is_emergency_contact = false
  form.emergency_phone = ''
  formError.value = ''
  modal.open = true
}

function openEdit(fam: any) {
  modal.mode = 'edit'
  modal.id = fam.id
  form.relation = fam.relation
  form.name = fam.name
  form.gender = fam.gender || ''
  form.nik = fam.nik || ''
  form.date_of_birth = fam.date_of_birth ? fam.date_of_birth.substring(0, 10) : ''
  form.education_level = fam.education_level || ''
  form.occupation = fam.occupation || ''
  form.is_dependent = !!fam.is_dependent
  form.is_emergency_contact = !!fam.is_emergency_contact
  form.emergency_phone = fam.emergency_phone || ''
  formError.value = ''
  modal.open = true
}

async function submit() {
  formError.value = ''
  saving.value = true
  const payload = {
    relation: form.relation,
    name: form.name,
    gender: form.gender || null,
    nik: form.nik || null,
    date_of_birth: form.date_of_birth || null,
    education_level: form.education_level || null,
    occupation: form.occupation || null,
    is_dependent: form.is_dependent,
    is_emergency_contact: form.is_emergency_contact,
    emergency_phone: form.emergency_phone || null,
  }
  try {
    if (modal.mode === 'create') {
      await api('POST', `/employees/${props.employeeId}/families`, payload)
    } else {
      await api('PUT', `/employees/${props.employeeId}/families/${modal.id}`, payload)
    }
    toast.success(modal.mode === 'create' ? 'Keluarga ditambahkan.' : 'Keluarga diperbarui.')
    modal.open = false
    load()
  } catch (e: any) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(fam: any) {
  const ok = await confirm({
    title: 'Hapus data keluarga?',
    message: `${fam.name} (${fam.relation}) akan dihapus.`,
    danger: true,
    confirmText: 'Hapus',
  })
  if (!ok) return
  try {
    await api('DELETE', `/employees/${props.employeeId}/families/${fam.id}`)
    toast.success('Data keluarga dihapus.')
    load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menghapus data keluarga.'))
  }
}

onMounted(load)
</script>
