<template>
  <AppModal title="Absensi" @close="$emit('close')">
    <p class="mb-4 rounded-xl bg-gray-50 px-3 py-2 text-center text-sm text-gray-600">
      Status: <span class="font-semibold text-gray-800">{{ statusLabel }}</span>
    </p>

    <!-- Tombol utama -->
    <div class="space-y-3">
      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-2xl p-4 text-left shadow-lg transition active:scale-[0.98]"
        :class="hasIn ? 'cursor-not-allowed bg-gray-100 text-gray-400 shadow-none' : 'bg-primary-600 text-white shadow-primary-600/30'"
        :disabled="hasIn"
        @click="choose('in', false)"
      >
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full" :class="hasIn ? 'bg-gray-200' : 'bg-white/20'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="10" cy="7" r="4" />
          </svg>
        </div>
        <div class="flex-1">
          <p class="font-bold">Clock In</p>
          <p class="text-xs" :class="hasIn ? 'text-gray-400' : 'text-primary-100'">
            {{ hasIn ? 'Sudah clock in hari ini' : 'Absen masuk + foto wajah' }}
          </p>
        </div>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" :class="hasIn ? 'text-gray-300' : 'text-primary-200'">
          <path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-2xl p-4 text-left shadow-lg transition active:scale-[0.98]"
        :class="canClockOut ? 'bg-red-600 text-white shadow-red-600/30' : 'cursor-not-allowed bg-gray-100 text-gray-400 shadow-none'"
        :disabled="!canClockOut"
        @click="choose('out', false)"
      >
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full" :class="canClockOut ? 'bg-white/20' : 'bg-gray-200'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="10" cy="7" r="4" />
            <path d="M21 11l-3-3m0 0l-3 3m3-3v8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="flex-1">
          <p class="font-bold">Clock Out</p>
          <p class="text-xs" :class="canClockOut ? 'text-red-100' : 'text-gray-400'">
            {{ clockOutHint }}
          </p>
        </div>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" :class="canClockOut ? 'text-red-200' : 'text-gray-300'">
          <path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </div>

    <!-- Tombol ulang: menambah riwayat -->
    <div class="mt-4 flex items-center gap-2">
      <div class="h-px flex-1 bg-gray-200"></div>
      <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Tambah riwayat</p>
      <div class="h-px flex-1 bg-gray-200"></div>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-2">
      <button
        type="button"
        class="rounded-xl border py-2.5 text-sm font-semibold transition active:scale-[0.98]"
        :class="canClockInUlang ? 'border-primary-200 bg-primary-50 text-primary-700 hover:bg-primary-100' : 'cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300'"
        :disabled="!canClockInUlang"
        @click="choose('in', true)"
      >
        Clock In Ulang
      </button>
      <button
        type="button"
        class="rounded-xl border py-2.5 text-sm font-semibold transition active:scale-[0.98]"
        :class="canClockOutUlang ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100' : 'cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300'"
        :disabled="!canClockOutUlang"
        @click="choose('out', true)"
      >
        Clock Out Ulang
      </button>
    </div>
    <p class="mt-2 text-center text-[11px] text-gray-400">
      Tombol "Ulang" menambah catatan riwayat tanpa mengubah status utama.
    </p>
  </AppModal>
</template>

<script setup lang="ts">
defineEmits<{ close: [] }>()

const dateStr = computed(() => new Date().toISOString().slice(0, 10))
const { data } = useApi<{ data: any[] }>(() => `/attendance/me?date=${dateStr.value}`)
const records = computed(() => data.value?.data || [])

const hasIn = computed(() => records.value.some((r) => r.type === 'clock_in'))
const hasOut = computed(() => records.value.some((r) => r.type === 'clock_out'))
const isWorking = computed(() => records.value[0]?.type === 'clock_in')

// Tombol utama:
// - Clock In  → hanya bisa kalau BELUM clock in hari ini
// - Clock Out → hanya bisa kalau sudah clock in DAN belum clock out
const canClockOut = computed(() => hasIn.value && !hasOut.value)
// Tombol ulang (force, menambah riwayat):
// - Clock In Ulang  → setelah ada siklus selesai (sudah out) & sesi tertutup
// - Clock Out Ulang → asal sudah pernah clock in (termasuk saat sesi kebuka ganda)
const canClockInUlang = computed(() => hasOut.value && !isWorking.value)
const canClockOutUlang = computed(() => hasIn.value)

const statusLabel = computed(() => {
  if (isWorking.value) return 'Sedang Bekerja'
  if (records.value.length > 0) return 'Selesai Hari Ini'
  return 'Belum Absen'
})
const clockOutHint = computed(() => {
  if (!hasIn.value) return 'Belum clock in hari ini'
  if (hasOut.value) return 'Sudah clock out — pakai Clock Out Ulang'
  return 'Absen pulang + foto wajah'
})

function choose(type: 'in' | 'out', force: boolean) {
  navigateTo(`/clock?type=${type}${force ? '&force=1' : ''}`)
}
</script>
