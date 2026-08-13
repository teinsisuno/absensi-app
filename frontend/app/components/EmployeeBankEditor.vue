<template>
  <div class="card max-w-2xl p-6">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900">Rekening Bank</h3>
      <button class="btn-primary !px-3 !py-1.5 text-sm" @click="openCreate">+ Tambah</button>
    </div>

    <div v-if="loading" class="text-sm text-gray-400">Memuat…</div>
    <div v-else-if="!items.length" class="text-sm text-gray-400">Belum ada data rekening.</div>
    <div v-else class="space-y-2">
      <div
        v-for="bank in items"
        :key="bank.id"
        class="flex items-center justify-between rounded-lg border border-gray-100 p-3"
      >
        <div>
          <p class="text-sm font-medium text-gray-800">{{ bank.bank_name }}</p>
          <p class="text-xs text-gray-400">{{ bank.account_number }} · {{ bank.account_name }}</p>
        </div>
        <div class="flex items-center gap-2">
          <span v-if="bank.is_default" class="rounded-full bg-primary-50 px-2 py-0.5 text-xs text-primary-600">Utama</span>
          <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(bank)">Edit</button>
          <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(bank)">Hapus</button>
        </div>
      </div>
    </div>

    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Rekening' : 'Edit Rekening'" @close="modal.open = false">
      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="label">Bank <span class="text-red-500">*</span></label>
          <input v-model="form.bank_name" type="text" class="input" required placeholder="mis. BCA" />
        </div>
        <div class="mb-4">
          <label class="label">No. Rekening <span class="text-red-500">*</span></label>
          <input v-model="form.account_number" type="text" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Atas Nama <span class="text-red-500">*</span></label>
          <input v-model="form.account_name" type="text" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Cabang</label>
          <input v-model="form.branch" type="text" class="input" placeholder="mis. BCA KC Ungaran" />
        </div>
        <label class="mb-4 flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_default" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
          Jadikan rekening utama
        </label>

        <p v-if="formError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ formError }}</p>

        <div class="flex justify-end gap-2">
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
  bank_name: '',
  account_number: '',
  account_name: '',
  branch: '',
  is_default: false,
})
const formError = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api<{ data: any[] }>('GET', `/employees/${props.employeeId}/banks`)
    items.value = res.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat rekening.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.bank_name = ''
  form.account_number = ''
  form.account_name = ''
  form.branch = ''
  form.is_default = false
  formError.value = ''
  modal.open = true
}

function openEdit(bank: any) {
  modal.mode = 'edit'
  modal.id = bank.id
  form.bank_name = bank.bank_name
  form.account_number = bank.account_number
  form.account_name = bank.account_name
  form.branch = bank.branch || ''
  form.is_default = !!bank.is_default
  formError.value = ''
  modal.open = true
}

async function submit() {
  formError.value = ''
  saving.value = true
  const payload = {
    bank_name: form.bank_name,
    account_number: form.account_number,
    account_name: form.account_name,
    branch: form.branch || null,
    is_default: form.is_default,
  }
  try {
    if (modal.mode === 'create') {
      await api('POST', `/employees/${props.employeeId}/banks`, payload)
    } else {
      await api('PUT', `/employees/${props.employeeId}/banks/${modal.id}`, payload)
    }
    toast.success(modal.mode === 'create' ? 'Rekening ditambahkan.' : 'Rekening diperbarui.')
    modal.open = false
    load()
  } catch (e: any) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}

async function remove(bank: any) {
  const ok = await confirm({
    title: 'Hapus rekening?',
    message: `${bank.bank_name} ${bank.account_number} akan dihapus.`,
    danger: true,
    confirmText: 'Hapus',
  })
  if (!ok) return
  try {
    await api('DELETE', `/employees/${props.employeeId}/banks/${bank.id}`)
    toast.success('Rekening dihapus.')
    load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menghapus rekening.'))
  }
}

onMounted(load)
</script>
