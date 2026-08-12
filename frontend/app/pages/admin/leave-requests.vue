<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Pengajuan Izin / Cuti / Sakit</h1>
        <p class="text-sm text-gray-500">Setujui atau tolak pengajuan karyawan</p>
      </div>
    </div>

    <!-- Filter tab status -->
    <div class="mb-4 flex flex-wrap gap-2">
      <button
        v-for="f in filters"
        :key="f.value"
        type="button"
        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
        :class="filter === f.value ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        @click="filter = f.value; load()"
      >
        {{ f.label }}
        <span v-if="f.count !== undefined" class="ml-1 text-xs opacity-70">{{ f.count }}</span>
      </button>
    </div>

    <SkeletonLoader v-if="loading" />

    <EmptyState
      v-else-if="requests.length === 0"
      icon="📝"
      title="Belum ada pengajuan"
      description="Pengajuan izin/cuti/sakit karyawan akan muncul di sini."
    />

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[720px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Karyawan</th>
            <th class="px-4 py-3 font-medium">Tipe</th>
            <th class="px-4 py-3 font-medium">Tanggal</th>
            <th class="px-4 py-3 font-medium">Alasan</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="r in requests" :key="r.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <p class="font-medium text-gray-900">{{ r.employee?.name || '—' }}</p>
              <p class="text-xs text-gray-400">{{ r.employee?.position || '' }}</p>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-lg bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700">{{ typeLabel(r.type) }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">
              {{ formatDate(r.start_date) }}
              <span v-if="r.end_date !== r.start_date"> s/d {{ formatDate(r.end_date) }}</span>
            </td>
            <td class="max-w-[200px] px-4 py-3 text-gray-600">
              <span class="line-clamp-2">{{ r.reason }}</span>
              <p v-if="r.approval_notes" class="mt-1 text-xs text-gray-400">Catatan: {{ r.approval_notes }}</p>
            </td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(r.status)">
                {{ statusLabel(r.status) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div v-if="r.status === 'pending'" class="flex justify-end gap-1">
                <button class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100" @click="approve(r)">
                  Setujui
                </button>
                <button class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-100" @click="openReject(r)">
                  Tolak
                </button>
              </div>
              <span v-else class="text-xs text-gray-400">{{ r.approver?.name || '' }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal tolak dengan catatan wajib -->
    <AppModal v-if="rejectModal.open" title="Tolak Pengajuan" @close="rejectModal.open = false">
      <form @submit.prevent="submitReject">
        <div class="mb-4">
          <label class="label">Catatan <span class="text-red-500">*</span></label>
          <textarea
            v-model="rejectModal.notes"
            rows="3"
            class="input"
            placeholder="Alasan penolakan (wajib diisi)"
            required
          ></textarea>
        </div>
        <p v-if="rejectError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ rejectError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="rejectModal.open = false">Batal</button>
          <button type="submit" class="btn-primary !bg-red-600 hover:!bg-red-700" :disabled="rejectModal.saving">
            {{ rejectModal.saving ? 'Menyimpan…' : 'Tolak Pengajuan' }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface LeaveRequest {
  id: number
  type: string
  start_date: string
  end_date: string
  reason: string
  status: string
  approval_notes?: string | null
  employee?: { name?: string; position?: string } | null
  approver?: { name?: string } | null
}

const toast = useToast()
const confirmDialog = useConfirm()

const filters = [
  { value: '', label: 'Semua' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Disetujui' },
  { value: 'rejected', label: 'Ditolak' },
]
const filter = ref('')
const loading = ref(true)
const requests = ref<LeaveRequest[]>([])

const rejectModal = reactive({ open: false, id: null as number | null, notes: '', saving: false })
const rejectError = ref('')

async function load() {
  loading.value = true
  try {
    const data = await api<{ data: LeaveRequest[] }>('GET', `/leave-requests${filter.value ? `?status=${filter.value}` : ''}`)
    requests.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat pengajuan.'))
  } finally {
    loading.value = false
  }
}

async function approve(r: LeaveRequest) {
  const ok = await confirmDialog.confirm({
    title: 'Setujui pengajuan?',
    message: `${r.employee?.name || 'Karyawan'} — ${typeLabel(r.type)} ${formatDate(r.start_date)}`,
    confirmText: 'Setujui',
  })
  if (!ok) return
  try {
    await api('POST', `/leave-requests/${r.id}/approve`)
    toast.success('Pengajuan disetujui.')
    await load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal menyetujui pengajuan.'))
  }
}

function openReject(r: LeaveRequest) {
  rejectModal.id = r.id
  rejectModal.notes = ''
  rejectError.value = ''
  rejectModal.open = true
}

async function submitReject() {
  rejectError.value = ''
  rejectModal.saving = true
  try {
    await api('POST', `/leave-requests/${rejectModal.id}/reject`, { notes: rejectModal.notes })
    rejectModal.open = false
    toast.success('Pengajuan ditolak.')
    await load()
  } catch (e: any) {
    rejectError.value = errorMessage(e, 'Gagal menolak pengajuan.')
  } finally {
    rejectModal.saving = false
  }
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function typeLabel(t: string) {
  return { izin: 'Izin', cuti: 'Cuti', sakit: 'Sakit' }[t] || t
}

function statusLabel(s: string) {
  return { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }[s] || s
}

function statusClass(s: string) {
  if (s === 'approved') return 'bg-emerald-100 text-emerald-700'
  if (s === 'rejected') return 'bg-red-100 text-red-600'
  if (s === 'cancelled') return 'bg-gray-100 text-gray-500'
  return 'bg-amber-100 text-amber-700'
}

onMounted(load)
</script>
