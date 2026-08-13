<template>
  <div class="card max-w-2xl p-6">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900">Dokumen</h3>
      <button class="btn-primary !px-3 !py-1.5 text-sm" @click="openCreate">+ Tambah</button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400">Memuat…</div>
    <div v-else-if="!items.length" class="text-sm text-gray-400">Belum ada dokumen.</div>
    <div v-else class="space-y-2">
      <div v-for="doc in items" :key="doc.id" class="flex items-center gap-3 rounded-lg border border-gray-100 p-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50 text-primary-600">📄</div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-gray-800">{{ doc.title }}</p>
          <p class="text-xs text-gray-400">{{ doc.document_type }}<span v-if="doc.document_number"> · {{ doc.document_number }}</span></p>
        </div>
        <span
          class="rounded-full px-2 py-0.5 text-xs"
          :class="verifClass(doc.verification_status)"
        >{{ verifLabel(doc.verification_status) }}</span>
        <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(doc)">Edit</button>
        <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(doc)">Hapus</button>
      </div>
    </div>

    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Dokumen' : 'Edit Dokumen'" @close="modal.open = false">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Jenis <span class="text-red-500">*</span></label>
            <select v-model="form.document_type" class="input" required>
              <option value="KTP">KTP</option>
              <option value="KK">Kartu Keluarga</option>
              <option value="Ijazah">Ijazah</option>
              <option value="Sertifikat">Sertifikat</option>
              <option value="BPJS">BPJS</option>
              <option value="SIM">SIM</option>
              <option value="Lainnya">Lainnya</option>
            </select>
          </div>
          <div>
            <label class="label">Nomor Dokumen</label>
            <input v-model="form.document_number" type="text" class="input" />
          </div>
          <div class="sm:col-span-2">
            <label class="label">Judul <span class="text-red-500">*</span></label>
            <input v-model="form.title" type="text" class="input" required placeholder="mis. KTP Budi" />
          </div>
          <div>
            <label class="label">Tanggal Terbit</label>
            <input v-model="form.issue_date" type="date" class="input" />
          </div>
          <div>
            <label class="label">Tanggal Kadaluarsa</label>
            <input v-model="form.expiry_date" type="date" class="input" />
          </div>
          <div>
            <label class="label">Diterbitkan Oleh</label>
            <input v-model="form.issued_by" type="text" class="input" placeholder="mis. Dukcapil" />
          </div>
          <div>
            <label class="label">Verifikasi</label>
            <select v-model="form.verification_status" class="input">
              <option value="pending">Pending</option>
              <option value="verified">Verified</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="label">Lokasi File (URL/path)</label>
            <input v-model="form.file_path" type="text" class="input" placeholder="opsional — upload file menyusul" />
          </div>
          <div class="sm:col-span-2">
            <label class="label">Keterangan</label>
            <textarea v-model="form.description" rows="2" class="input"></textarea>
          </div>
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
  document_type: 'KTP',
  document_number: '',
  title: '',
  description: '',
  file_path: '',
  issue_date: '',
  expiry_date: '',
  issued_by: '',
  verification_status: 'pending',
})
const formError = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api<{ data: any[] }>('GET', `/employees/${props.employeeId}/documents`)
    items.value = res.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat dokumen.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.document_type = 'KTP'
  form.document_number = ''
  form.title = ''
  form.description = ''
  form.file_path = ''
  form.issue_date = ''
  form.expiry_date = ''
  form.issued_by = ''
  form.verification_status = 'pending'
  formError.value = ''
  modal.open = true
}

function openEdit(doc: any) {
  modal.mode = 'edit'
  modal.id = doc.id
  form.document_type = doc.document_type
  form.document_number = doc.document_number || ''
  form.title = doc.title
  form.description = doc.description || ''
  form.file_path = doc.file_path || ''
  form.issue_date = doc.issue_date ? doc.issue_date.substring(0, 10) : ''
  form.expiry_date = doc.expiry_date ? doc.expiry_date.substring(0, 10) : ''
  form.issued_by = doc.issued_by || ''
  form.verification_status = doc.verification_status || 'pending'
  formError.value = ''
  modal.open = true
}

async function submit() {
  formError.value = ''
  saving.value = true
  const payload = {
    document_type: form.document_type,
    document_number: form.document_number || null,
    title: form.title,
    description: form.description || null,
    file_path: form.file_path || null,
    issue_date: form.issue_date || null,
    expiry_date: form.expiry_date || null,
    issued_by: form.issued_by || null,
    verification_status: form.verification_status,
  }
  try {
    if (modal.mode === 'create') {
      await api('POST', `/employees/${props.employeeId}/documents`, payload)
    } else {
      await api('PUT', `/employees/${props.employeeId}/documents/${modal.id}`, payload)
    }
    toast.success(modal.mode === 'create' ? 'Dokumen ditambahkan.' : 'Dokumen diperbarui.')
    modal.open = false
    load()
  } catch (e: any) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(doc: any) {
  const ok = await confirm({
    title: 'Hapus dokumen?',
    message: `${doc.title} akan dihapus.`,
    danger: true,
    confirmText: 'Hapus',
  })
  if (!ok) return
  try {
    await api('DELETE', `/employees/${props.employeeId}/documents/${doc.id}`)
    toast.success('Dokumen dihapus.')
    load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menghapus dokumen.'))
  }
}

function verifLabel(s: string) {
  return { pending: 'Pending', verified: 'Verified', rejected: 'Rejected' }[s] || s
}

function verifClass(s: string) {
  return { pending: 'bg-amber-100 text-amber-700', verified: 'bg-green-100 text-green-700', rejected: 'bg-red-100 text-red-700' }[s] || 'bg-gray-100 text-gray-500'
}

onMounted(load)
</script>
