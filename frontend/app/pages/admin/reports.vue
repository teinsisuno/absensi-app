<template>
  <div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Laporan Kehadiran</h1>
        <p class="text-sm text-gray-500">Ringkasan kehadiran per periode</p>
      </div>
      <div class="flex gap-2">
        <input v-model="period" type="month" class="input !w-auto" @change="load" />
        <button type="button" class="btn-primary" @click="downloadExport">⬇ Export CSV</button>
      </div>
    </div>

    <SkeletonLoader v-if="loading" />

    <template v-else>
      <!-- Ringkasan stat -->
      <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Hadir</p>
          <p class="mt-1 text-2xl font-bold text-primary-600">{{ summary.hadir }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Terlambat</p>
          <p class="mt-1 text-2xl font-bold text-amber-600">{{ summary.terlambat }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Tanpa Absen</p>
          <p class="mt-1 text-2xl font-bold text-red-500">{{ summary.alpha }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Izin/Cuti</p>
          <p class="mt-1 text-2xl font-bold text-sky-600">{{ summary.izin }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Karyawan</p>
          <p class="mt-1 text-2xl font-bold text-gray-900">{{ summary.employees }}</p>
        </div>
      </div>

      <!-- Grafik bar harian -->
      <div class="card mb-6 p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Clock-in per Hari</h3>
        <div v-if="!chartDays.length" class="py-8 text-center text-sm text-gray-400">Tidak ada data pada periode ini.</div>
        <div v-else class="flex h-48 items-end gap-1">
          <div
            v-for="d in chartDays"
            :key="d.date"
            class="group relative flex flex-1 flex-col items-center justify-end self-stretch"
          >
            <span v-if="d.count > 0" class="mb-1 text-[9px] font-medium text-primary-600">{{ d.count }}</span>
            <div
              class="w-full rounded-t transition hover:opacity-80"
              :class="d.count > 0 ? 'bg-primary-500' : 'bg-gray-100'"
              :style="{ height: d.height }"
              :title="`${d.date}: ${d.count} clock-in`"
            ></div>
            <span class="mt-1 text-[9px] text-gray-400">{{ d.label }}</span>
          </div>
        </div>
      </div>

      <!-- Tabel rekap per karyawan -->
      <div class="card overflow-x-auto">
        <h3 class="border-b border-gray-100 px-5 py-4 text-sm font-semibold uppercase tracking-wide text-gray-500">
          Rekap per Karyawan
        </h3>
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">Nama</th>
              <th class="px-4 py-3 font-medium">Jabatan</th>
              <th class="px-4 py-3 font-medium">Hari Hadir</th>
              <th class="px-4 py-3 font-medium">Total Clock-in</th>
              <th class="px-4 py-3 font-medium">Total Clock-out</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="e in perEmployee" :key="e.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ e.name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ e.position || '—' }}</td>
              <td class="px-4 py-3 text-primary-600">{{ e.hadir }} hari</td>
              <td class="px-4 py-3 text-gray-600">{{ e.clockIn }}</td>
              <td class="px-4 py-3 text-gray-600">{{ e.clockOut }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

const toast = useToast()

const period = ref(new Date().toISOString().substring(0, 7))
const loading = ref(true)

const summary = reactive({ hadir: 0, terlambat: 0, alpha: 0, izin: 0, employees: 0 })
const chartDays = ref<any[]>([])
const perEmployee = ref<any[]>([])

const monthFrom = computed(() => `${period.value}-01`)
const monthTo = computed(() => {
  const d = new Date(period.value + '-01T00:00:00')
  const last = new Date(d.getFullYear(), d.getMonth() + 1, 0)
  return last.toISOString().substring(0, 10)
})

function downloadExport() {
  const url = `/attendance/export?from=${monthFrom.value}&to=${monthTo.value}`
  $fetch(url, {
    baseURL: apiBase(),
    method: 'GET',
    responseType: 'blob',
    headers: { Authorization: `Bearer ${useAuthStore().token}` },
  })
    .then((blob) => {
      const objectUrl = URL.createObjectURL(blob as Blob)
      const a = document.createElement('a')
      a.href = objectUrl
      a.download = `absensi-${monthFrom.value}_${monthTo.value}.csv`
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(objectUrl)
    })
    .catch((e: any) => toast.error(errorMessage(e, 'Gagal export CSV.')))
}

onMounted(load)

async function load() {
  loading.value = true
  try {
    const data = await api<{
      data: { employees: any[]; dates: string[] }
    }>('GET', `/attendance/roster?from=${monthFrom.value}&to=${monthTo.value}`)

    const rows = data.data?.employees || []
    const dates = data.data?.dates || []

    summary.employees = rows.length

    let clockInTotal = 0
    let clockOutTotal = 0
    let hadirTotal = 0
    const perDay = new Map<string, number>()

    perEmployee.value = rows.map((e) => {
      const days = e.days || []
      const clockIn = days.reduce((sum: number, d: any) => sum + (d.count_in || 0), 0)
      const clockOut = days.reduce((sum: number, d: any) => sum + (d.count_out || 0), 0)
      const hadir = days.filter((d: any) => d.clock_in).length
      clockInTotal += clockIn
      clockOutTotal += clockOut
      hadirTotal += hadir
      days.forEach((d: any, i: number) => {
        if (d.count_in) {
          const date = dates[i]
          perDay.set(date, (perDay.get(date) || 0) + d.count_in)
        }
      })
      return {
        id: e.id,
        name: e.name,
        position: e.position,
        hadir,
        clockIn,
        clockOut,
      }
    })

    summary.hadir = hadirTotal
    summary.terlambat = 0
    summary.alpha = Math.max(0, rows.length * dates.length - hadirTotal)
    summary.izin = 0

    // Bangun data grafik
    const maxCount = Math.max(1, ...Array.from(perDay.values()))
    chartDays.value = dates.map((date: string) => {
      const count = perDay.get(date) || 0
      const d = new Date(date + 'T00:00:00')
      return {
        date,
        count,
        label: d.getDate(),
        height: count > 0 ? `${Math.max(8, Math.round((count / maxCount) * 100))}%` : '4%',
      }
    })
  } catch (e: any) {
    toast.error(errorMessage(e, 'Gagal memuat laporan.'))
  } finally {
    loading.value = false
  }
}
</script>
