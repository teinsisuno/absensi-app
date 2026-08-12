<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Pengumuman</h1>
        <p class="text-sm text-gray-500">Buat dan kelola pengumuman untuk karyawan</p>
      </div>
      <button class="btn-primary" @click="openCreate">+ Buat Pengumuman</button>
    </div>

    <SkeletonLoader v-if="loading" />

    <EmptyState v-else-if="announcements.length === 0" icon="📢" title="Belum ada pengumuman" description="Buat pengumuman pertama untuk tim kamu." />

    <div v-else class="space-y-3">
      <div v-for="a in announcements" :key="a.id" class="card p-5">
        <div class="mb-1 flex items-start justify-between gap-3">
          <div>
            <h3 class="font-semibold text-gray-900">{{ a.title }}</h3>
            <p class="mt-0.5 text-xs text-gray-400">
              oleh {{ a.creator?.name || '—' }} · {{ a.published_at ? formatDateTime(a.published_at) : 'Draft' }}
            </p>
          </div>
          <span
            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
            :class="a.published_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
          >
            {{ a.published_at ? 'Published' : 'Draft' }}
          </span>
        </div>
        <p class="mt-2 line-clamp-3 whitespace-pre-line text-sm text-gray-600">{{ a.body }}</p>
        <div class="mt-3 flex gap-1">
          <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(a)">Edit</button>
          <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(a)">Hapus</button>
        </div>
      </div>
    </div>

    <!-- Modal buat/edit -->
    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Buat Pengumuman' : 'Edit Pengumuman'" @close="modal.open = false">
      <form @submit.prevent="submitForm">
        <div class="mb-4">
          <label class="label">Judul <span class="text-red-500">*</span></label>
          <input v-model="form.title" type="text" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Isi <span class="text-red-500">*</span></label>
          <textarea v-model="form.body" rows="6" class="input" required></textarea>
        </div>
        <div class="mb-4 flex items-center gap-2">
          <input id="publish" v-model="form.publish" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary-600" />
          <label for="publish" class="text-sm text-gray-600">Publikasikan sekarang</label>
        </div>
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
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface Announcement {
  id: number
  title: string
  body: string
  published_at?: string | null
  creator?: { name?: string } | null
}

const toast = useToast()
const confirmDialog = useConfirm()

const loading = ref(true)
const announcements = ref<Announcement[]>([])

const modal = reactive<{ open: boolean; mode: 'create' | 'edit'; id: number | null }>({ open: false, mode: 'create', id: null })
const form = reactive({ title: '', body: '', publish: true })
const formError = ref('')
const saving = ref(false)

async function load() {
  loading.value = true
  try {
    const data = await api<{ data: Announcement[] }>('GET', '/announcements')
    announcements.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat pengumuman.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  modal.mode = 'create'
  modal.id = null
  form.title = ''
  form.body = ''
  form.publish = true
  formError.value = ''
  modal.open = true
}

function openEdit(a: Announcement) {
  modal.mode = 'edit'
  modal.id = a.id
  form.title = a.title
  form.body = a.body
  form.publish = !a.published_at
  formError.value = ''
  modal.open = true
}

async function submitForm() {
  formError.value = ''
  saving.value = true
  try {
    const body = { title: form.title, body: form.body, publish: form.publish }
    if (modal.mode === 'create') {
      await api('POST', '/announcements', body)
      toast.success(form.publish ? 'Pengumuman dipublikasikan.' : 'Pengumuman disimpan sebagai draft.')
    } else {
      await api('PUT', `/announcements/${modal.id}`, body)
      toast.success('Pengumuman diperbarui.')
    }
    modal.open = false
    await load()
  } catch (e: any) {
    formError.value = errorMessage(e, 'Gagal menyimpan pengumuman.')
  } finally {
    saving.value = false
  }
}

async function remove(a: Announcement) {
  const ok = await confirmDialog.confirm({
    title: 'Hapus pengumuman?',
    message: `"${a.title}" akan dihapus permanen.`,
    confirmText: 'Hapus',
    danger: true,
  })
  if (!ok) return
  try {
    await api('DELETE', `/announcements/${a.id}`)
    toast.success('Pengumuman dihapus.')
    await load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menghapus pengumuman.'))
  }
}

function formatDateTime(d: string) {
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(load)
</script>
