<template>
  <div class="card max-w-2xl p-6">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900">Kontrak Kerja</h3>
      <button class="btn-primary !px-3 !py-1.5 text-sm" @click="openCreate">+ Tambah</button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400">Memuat…</div>
    <div v-else-if="!items.length" class="text-sm text-gray-400">Belum ada data kontrak.</div>
    <div v-else class="space-y-2">
      <div
        v-for="c in items"
        :key="c.id"
        class="rounded-lg border border-gray-100 p-3"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-800">{{ c.contract_number || 'Kontrak' }}</p>
            <p class="mt-0.5 text-xs text-gray-400">{{ formatDate(c.start_date) }} – {{ formatDate(c.end_date) }}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs uppercase text-gray-500">{{ c.contract_type }}</span>
            <span
              v-if="c.is_latest"
              class="rounded-full bg-primary-50 px-2 py-0.5 text-xs text-primary-600"
            >Terbaru</span>
            <span
              class="rounded-full px-2 py-0.5 text-xs"
              :class="statusClass(c.status)"
            >{{ c.status }}</span>
            <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(c)">Edit</button>
            <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(c)">Hapus</button>
          </div>
        </div>
        <p v-if="c.notes" class="mt-1 text-xs text-gray-400">{{ c.notes }}</p>
      </div>
    </div>

    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Kontrak' : 'Edit Kontrak'" @close="modal.open = false">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">No. Kontrak</label>
            <input v-model="form.contract_number" type="text" class="input" placeholder="mis. KTR-2026-001" />
          </div>
          <div>
            <label class="label">Tipe</label>
            <select v-model="form.contract_type" class="input">
              <option value="pkwt">PKWT</option>
              <option value="pkwtt">PKWTT</option>
              <option value="magang">Magang</option>
              <option value="kontrak">Kontrak</option>
            </select>
          </div>
          <div>
            <label class="label">Mulai <span class="text-red-500">*</span></label>
            <input v-model="form.start_date" type="date" class="input" required />
          </div>
          <div>
            <label class="label">Selesai</label>
            <input v-model="form.end_date" type="date" class="input" />
          </div>
          <div>
            <label class="label">Durasi (bulan)</label>
            <input v-model="form.duration_months" type="number" min="1" class="input" placeholder="auto dari tanggal" />
          </div>
          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input">
              <option value="active">Aktif</option>
              <option value="expired">Berakhir</option>
              <option value="terminated">Diberhentikan</option>
              <option value="draft">Draft</option>
            </select>
          </div>
        </div>
        <div class="mt-4">
          <label class="label">Catatan</label>
          <textarea v-model="form.notes" rows="2" class="input" placeholder="Catatan tambahan…"></textarea>
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
  contract_number: '',
  contract_type: 'pkwt',
  start_date: '',
  end_date: '',
  duration_months: null as number | null,
  notes: '',
  status: 'active',
})
const formError = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api<{ data: any[] }>('GET', `/employees/${props.employeeId}/contracts`)
    items.value = res.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat data kontrak.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.contract_number = ''
  form.contract_type = 'pkwt'
  form.start_date = ''
  form.end_date = ''
  form.duration_months = null
  form.notes = ''
  form.status = 'active'
  formError.value = ''
  modal.open = true
}

function openEdit(c: any) {
  modal.mode = 'edit'
  modal.id = c.id
  form.contract_number = c.contract_number || ''
  form.contract_type = c.contract_type || 'pkwt'
  form.start_date = c.start_date ? c.start_date.substring(0, 10) : ''
  form.end_date = c.end_date ? c.end_date.substring(0, 10) : ''
  form.duration_months = c.duration_months ?? null
  form.notes = c.notes || ''
  form.status = c.status || 'active'
  formError.value = ''
  modal.open = true
}

async function submit() {
  formError.value = ''
  saving.value = true
  const payload = {
    contract_number: form.contract_number || null,
    contract_type: form.contract_type,
    start_date: form.start_date,
    end_date: form.end_date || null,
    duration_months: form.duration_months,
    notes: form.notes || null,
    status: form.status,
  }
  try {
    if (modal.mode === 'create') {
      await api('POST', `/employees/${props.employeeId}/contracts`, payload)
    } else {
      await api('PUT', `/employees/${props.employeeId}/contracts/${modal.id}`, payload)
    }
    toast.success(modal.mode === 'create' ? 'Kontrak ditambahkan.' : 'Kontrak diperbarui.')
    modal.open = false
    load()
  } catch (e: any) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(c: any) {
  const ok = await confirm({
    title: 'Hapus kontrak?',
    message: `${c.contract_number || 'Kontrak'} akan dihapus.`,
    danger: true,
    confirmText: 'Hapus',
  })
  if (!ok) return
  try {
    await api('DELETE', `/employees/${props.employeeId}/contracts/${c.id}`)
    toast.success('Kontrak dihapus.')
    load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menghapus kontrak.'))
  }
}

function formatDate(d: string | undefined | null) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function statusClass(s: string) {
  return { active: 'bg-green-100 text-green-700', expired: 'bg-amber-100 text-amber-700', terminated: 'bg-red-100 text-red-700', draft: 'bg-gray-100 text-gray-500' }[s] || 'bg-gray-100 text-gray-500'
}

onMounted(load)
</script>
