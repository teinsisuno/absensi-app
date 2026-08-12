<template>
  <div>
    <!-- Header -->
    <div class="sticky top-0 z-20 border-b border-gray-100 bg-white px-6 pb-4 pt-12">
      <div class="mb-2 flex items-center gap-4">
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-600 transition hover:bg-gray-200"
          @click="navigateTo('/announcements')"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="text-xl font-bold text-gray-800">Detail Pengumuman</h1>
      </div>
    </div>

    <div class="px-4 py-4">
      <div v-if="loading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">Memuat…</div>

      <div v-else-if="!announcement" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-400 shadow-sm">
        Pengumuman tidak ditemukan.
      </div>

      <div v-else class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900">{{ announcement.title }}</h2>
        <p class="mt-1 text-xs text-gray-400">
          {{ formatDateTime(announcement.published_at) }} · oleh {{ announcement.creator?.name || '—' }}
        </p>
        <div class="mt-4 border-t border-gray-100 pt-4">
          <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ announcement.body }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'mobile', middleware: 'guard' })

const route = useRoute()
const toast = useToast()

const loading = ref(true)
const announcement = ref<any>(null)

onMounted(async () => {
  try {
    const data = await api<{ data: any }>('GET', `/announcements/${route.params.id}`)
    announcement.value = data.data
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat pengumuman.'))
  } finally {
    loading.value = false
  }
})

function formatDateTime(d: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}
</script>
