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
        <h1 class="text-xl font-bold text-gray-800">Tugas Luar Saya</h1>
      </div>
    </div>

    <div class="px-4 py-4">
      <!-- Tab status -->
      <div class="mb-6 flex rounded-2xl border border-gray-100 bg-white p-2 shadow-sm">
        <button
          v-for="s in statuses"
          :key="s.value"
          type="button"
          class="flex-1 rounded-xl py-3 text-sm font-medium transition"
          :class="statusTab === s.value ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50'"
          @click="statusTab = s.value; load()"
        >
          {{ s.label }}
          <span v-if="counts[s.value]" class="ml-1 text-xs opacity-70">{{ counts[s.value] }}</span>
        </button>
      </div>

      <div v-if="listLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">Memuat…</div>

      <div v-else-if="tasks.length === 0" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">
        Tidak ada tugas dengan status ini.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="t in tasks"
          :key="t.id"
          class="cursor-pointer rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition active:scale-[0.99]"
          @click="openSheet(t)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-800">{{ t.title }}</p>
              <p v-if="t.description" class="mt-1 line-clamp-2 text-xs text-gray-500">{{ t.description }}</p>
            </div>
            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-medium" :class="statusClass(t.status)">
              {{ statusLabel(t.status) }}
            </span>
          </div>
          <div class="mt-2 flex items-center gap-3 text-[11px] text-gray-400">
            <span v-if="t.due_date" :class="isOverdue(t) ? 'font-semibold text-red-500' : ''">
              📅 {{ formatDate(t.due_date) }}<span v-if="isOverdue(t)"> · Terlambat</span>
            </span>
            <span>oleh {{ t.creator?.name || '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom sheet update status -->
    <Teleport to="body">
      <Transition name="sheet">
        <div v-if="sheet.open" class="fixed inset-0 z-[80]">
          <div class="absolute inset-0 bg-black/40" @click="sheet.open = false"></div>
          <div class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-6 pb-8 shadow-2xl" style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom))">
            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-gray-200"></div>
            <h3 class="mb-1 font-bold text-gray-900">{{ sheet.task?.title }}</h3>
            <p v-if="sheet.task?.due_date" class="mb-4 text-xs text-gray-400">Deadline: {{ formatDate(sheet.task.due_date) }}</p>

            <div class="space-y-2">
              <button
                v-for="s in statuses"
                :key="s.value"
                type="button"
                class="w-full rounded-xl px-4 py-3 text-left text-sm font-medium transition"
                :class="sheet.task?.status === s.value ? 'bg-primary-600 text-white' : 'bg-gray-50 text-gray-700 hover:bg-gray-100'"
                @click="updateStatus(s.value)"
              >
                {{ s.label }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const toast = useToast()

const statuses = [
  { value: 'all', label: 'Semua' },
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'done', label: 'Done' },
]

const statusTab = ref('all')
const listLoading = ref(true)
const tasks = ref<any[]>([])
const counts = reactive<Record<string, number>>({ pending: 0, in_progress: 0, done: 0 })

const sheet = reactive({ open: false, task: null as any, saving: false })

async function load() {
  listLoading.value = true
  try {
    const qs = statusTab.value === 'all' ? '' : `?status=${statusTab.value}`
    const data = await api<{ data: any[] }>('GET', `/tasks/me${qs}`)
    tasks.value = data.data
    const all = await api<{ data: any[] }>('GET', '/tasks/me')
    counts.pending = all.data.filter((t: any) => t.status === 'pending').length
    counts.in_progress = all.data.filter((t: any) => t.status === 'in_progress').length
    counts.done = all.data.filter((t: any) => t.status === 'done').length
  } catch {
    tasks.value = []
  } finally {
    listLoading.value = false
  }
}

function openSheet(t: any) {
  sheet.task = t
  sheet.open = true
}

async function updateStatus(status: string) {
  if (!sheet.task || sheet.saving) return
  if (sheet.task.status === status) {
    sheet.open = false
    return
  }
  sheet.saving = true
  try {
    await api('PUT', `/tasks/${sheet.task.id}/status`, { status })
    toast.success('Status tugas luar diperbarui.')
    sheet.open = false
    await load()
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal update status.'))
  } finally {
    sheet.saving = false
  }
}

function isOverdue(t: any) {
  if (!t.due_date || t.status === 'done') return false
  return new Date(t.due_date + 'T00:00:00') < new Date(new Date().toDateString())
}

function formatDate(d: string) {
  if (!d) return '—'
  return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function statusLabel(s: string) {
  return { pending: 'Pending', in_progress: 'In Progress', done: 'Done' }[s] || s
}

function statusClass(s: string) {
  if (s === 'done') return 'bg-emerald-100 text-emerald-700'
  if (s === 'in_progress') return 'bg-sky-100 text-sky-700'
  return 'bg-amber-100 text-amber-700'
}

onMounted(load)
</script>

<style scoped>
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.25s ease;
}
.sheet-enter-active > div:last-child,
.sheet-leave-active > div:last-child {
  transition: transform 0.25s ease;
}
.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}
.sheet-enter-from > div:last-child,
.sheet-leave-to > div:last-child {
  transform: translateY(100%);
}
</style>
