<template>
  <div>
    <!-- Header -->
    <div class="sticky top-0 z-20 border-b border-gray-100 bg-white px-6 pb-4 pt-12">
      <div class="mb-2 flex items-center gap-4">
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200"
          @click="navigateTo('/dashboard')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="text-xl font-bold text-gray-800">Pengajuan</h1>
      </div>
    </div>

    <div class="px-4 py-4">
      <!-- Tab jenis -->
      <div class="mb-6 flex rounded-2xl border border-gray-100 bg-white p-2 shadow-sm">
        <button
          v-for="t in types"
          :key="t.value"
          type="button"
          class="flex-1 rounded-xl py-3 text-sm font-medium transition"
          :class="leaveType === t.value ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
          @click="leaveType = t.value"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- Form -->
      <div class="mb-6 space-y-4">
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <label class="mb-2 block text-sm font-medium text-gray-600">Tanggal Mulai</label>
          <input v-model="form.start_date" type="date" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10" />
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <label class="mb-2 block text-sm font-medium text-gray-600">Tanggal Selesai</label>
          <input v-model="form.end_date" type="date" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10" />
        </div>
        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
          <label class="mb-2 block text-sm font-medium text-gray-600">Alasan</label>
          <textarea
            v-model="form.reason"
            rows="4"
            placeholder="Jelaskan alasan pengajuan..."
            class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
          ></textarea>
        </div>

        <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2.5 text-sm text-red-600">{{ error }}</p>

        <button
          type="button"
          class="w-full rounded-xl bg-primary-600 py-4 font-semibold text-white shadow-lg shadow-primary-600/25 transition hover:bg-primary-700 active:scale-[0.98]"
          :disabled="saving"
          @click="submit"
        >
          <span v-if="saving" class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent align-middle"></span>
          {{ saving ? 'Mengirim…' : 'Kirim Pengajuan' }}
        </button>
      </div>

      <!-- Riwayat pengajuan -->
      <div class="mt-8">
        <h3 class="mb-4 font-bold text-gray-800">Pengajuan Terakhir</h3>

        <div v-if="listLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">Memuat…</div>

        <div v-else-if="requests.length === 0" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">
          Belum ada pengajuan.
        </div>

        <div v-else class="space-y-3">
          <div v-for="r in requests" :key="r.id" class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="statusIconClass(r.status)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                <template v-if="r.status === 'approved'">
                  <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                </template>
                <template v-else-if="r.status === 'rejected' || r.status === 'cancelled'">
                  <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                </template>
                <template v-else>
                  <circle cx="12" cy="12" r="9" />
                  <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
                </template>
              </svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-medium text-gray-800">
                {{ typeLabel(r.type) }} — {{ formatDate(r.start_date) }}
                <span v-if="r.end_date !== r.start_date"> s/d {{ formatDate(r.end_date) }}</span>
              </p>
              <p class="truncate text-xs text-gray-400">{{ r.reason }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
              <span class="rounded-lg px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass(r.status)">
                {{ statusLabel(r.status) }}
              </span>
              <button
                v-if="r.status === 'pending'"
                type="button"
                class="text-[10px] text-red-500 underline underline-offset-2"
                @click="cancel(r)"
              >
                Batalkan
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const types = [
  { value: 'izin', label: 'Izin' },
  { value: 'cuti', label: 'Cuti' },
  { value: 'sakit', label: 'Sakit' },
]

const leaveType = ref<'izin' | 'cuti' | 'sakit'>('izin')
const form = reactive({
  start_date: '',
  end_date: '',
  reason: '',
})
const error = ref('')
const saving = ref(false)

const listLoading = ref(true)
const requests = ref<any[]>([])

async function loadRequests() {
  listLoading.value = true
  try {
    const data = await api<{ data: any[] }>('GET', '/leave-requests/me')
    requests.value = data.data
  } catch {
    requests.value = []
  } finally {
    listLoading.value = false
  }
}

async function submit() {
  error.value = ''
  if (!form.start_date || !form.end_date || !form.reason.trim()) {
    error.value = 'Lengkapi tanggal dan alasan dulu ya.'
    return
  }
  saving.value = true
  try {
    await api('POST', '/leave-requests', {
      type: leaveType.value,
      start_date: form.start_date,
      end_date: form.end_date,
      reason: form.reason.trim(),
    })
    form.start_date = ''
    form.end_date = ''
    form.reason = ''
    await loadRequests()
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal mengirim pengajuan.')
  } finally {
    saving.value = false
  }
}

async function cancel(r: any) {
  if (!confirm(`Batalkan pengajuan ${typeLabel(r.type)} tanggal ${formatDate(r.start_date)}?`)) return
  try {
    await api('POST', `/leave-requests/${r.id}/cancel`)
    await loadRequests()
  } catch (e: any) {
    alert(errorMessage(e, 'Gagal membatalkan pengajuan.'))
  }
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

function typeLabel(t: string) {
  return types.find((x) => x.value === t)?.label || t
}

function statusLabel(s: string) {
  return { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }[s] || s
}

function statusIconClass(s: string) {
  if (s === 'approved') return 'bg-emerald-100 text-emerald-600'
  if (s === 'rejected' || s === 'cancelled') return 'bg-red-100 text-red-500'
  return 'bg-amber-100 text-amber-600'
}

function statusBadgeClass(s: string) {
  if (s === 'approved') return 'bg-emerald-100 text-emerald-600'
  if (s === 'rejected' || s === 'cancelled') return 'bg-red-100 text-red-500'
  return 'bg-amber-100 text-amber-600'
}

onMounted(loadRequests)
</script>
