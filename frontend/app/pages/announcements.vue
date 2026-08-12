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
        <h1 class="text-xl font-bold text-gray-800">Pengumuman</h1>
      </div>
    </div>

    <div class="px-4 py-4">
      <div v-if="listLoading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">Memuat…</div>

      <div v-else-if="announcements.length === 0" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">
        Belum ada pengumuman.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="a in announcements"
          :key="a.id"
          class="cursor-pointer rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition active:scale-[0.99]"
          @click="navigateTo(`/announcements/${a.id}`)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-gray-800">{{ a.title }}</p>
              <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ a.body }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-medium text-primary-600">BARU</span>
          </div>
          <p class="mt-2 text-[11px] text-gray-400">
            {{ formatDateTime(a.published_at) }} · oleh {{ a.creator?.name || '—' }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const listLoading = ref(true)
const announcements = ref<any[]>([])

onMounted(async () => {
  try {
    const data = await api<{ data: any[] }>('GET', '/announcements')
    announcements.value = data.data
  } catch {
    announcements.value = []
  } finally {
    listLoading.value = false
  }
})

function formatDateTime(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>
