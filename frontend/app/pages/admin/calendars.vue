<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Kalender Kerja</h1>
        <p class="text-sm text-gray-500">Kalender tahunan & daftar hari libur</p>
      </div>
      <button class="btn-primary" @click="openCalendarModal">+ Tambah Kalender</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <template v-else>
      <!-- Pilih kalender -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="cal in calendars"
          :key="cal.id"
          type="button"
          class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
          :class="selectedCalendarId === cal.id ? 'bg-primary-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50'"
          @click="selectCalendar(cal.id)"
        >
          {{ cal.name }} · {{ cal.year }}
          <span class="text-xs opacity-70">({{ cal.holidays_count }})</span>
        </button>
        <p v-if="calendars.length === 0" class="text-sm text-gray-400">Belum ada kalender. Tambahkan dulu.</p>
      </div>

      <!-- Daftar libur kalender terpilih -->
      <div v-if="selectedCalendarId" class="card overflow-x-auto">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Hari Libur</h2>
          <button class="btn-primary !py-1.5 !text-xs" @click="openHolidayModal">+ Tambah Libur</button>
        </div>

        <div v-if="holidays.length === 0" class="flex flex-col items-center justify-center p-10 text-center">
          <div class="text-3xl">🎉</div>
          <p class="mt-2 text-sm font-medium text-gray-700">Belum ada hari libur</p>
          <p class="mt-1 text-xs text-gray-400">Tambahkan hari libur di kalender ini.</p>
        </div>
        <table v-else class="w-full min-w-[560px] text-left text-sm">
          <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-3 font-medium">Tanggal</th>
              <th class="px-4 py-3 font-medium">Nama</th>
              <th class="px-4 py-3 font-medium">Tipe</th>
              <th class="px-4 py-3 text-right font-medium">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="h in holidays" :key="h.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-medium text-gray-900">{{ formatDate(h.date) }}</td>
              <td class="px-4 py-3 text-gray-700">{{ h.name || '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="h.type === 'nasional' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'"
                >
                  {{ h.type === 'nasional' ? 'Nasional' : 'Perusahaan' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-1">
                  <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="removeHoliday(h)">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Modal kalender -->
    <AppModal v-if="calendarModal.open" :title="calendarModal.mode === 'create' ? 'Tambah Kalender' : 'Edit Kalender'" @close="calendarModal.open = false">
      <form @submit.prevent="submitCalendar">
        <div class="mb-4">
          <label class="label">Nama <span class="text-red-500">*</span></label>
          <input v-model="calendarForm.name" type="text" class="input" placeholder="mis. Kalender 2026" required />
        </div>
        <div class="mb-4">
          <label class="label">Tahun <span class="text-red-500">*</span></label>
          <input v-model.number="calendarForm.year" type="number" min="2020" max="2100" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Deskripsi</label>
          <textarea v-model="calendarForm.description" rows="2" class="input" placeholder="Opsional"></textarea>
        </div>
        <p v-if="calendarError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ calendarError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="calendarModal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
      </form>
    </AppModal>

    <!-- Modal libur -->
    <AppModal v-if="holidayModal.open" title="Tambah Hari Libur" @close="holidayModal.open = false">
      <form @submit.prevent="submitHoliday">
        <div class="mb-4">
          <label class="label">Tanggal <span class="text-red-500">*</span></label>
          <input v-model="holidayForm.date" type="date" class="input" required />
        </div>
        <div class="mb-4">
          <label class="label">Nama Libur</label>
          <input v-model="holidayForm.name" type="text" class="input" placeholder="mis. HUT RI" />
        </div>
        <div class="mb-4">
          <label class="label">Tipe <span class="text-red-500">*</span></label>
          <select v-model="holidayForm.type" class="input">
            <option value="nasional">Nasional</option>
            <option value="company">Perusahaan</option>
          </select>
        </div>
        <p v-if="holidayError" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ holidayError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="holidayModal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface CalendarItem {
  id: number
  name: string
  year: number
  description?: string | null
  holidays_count: number
}

interface HolidayItem {
  id: number
  date: string
  name?: string | null
  type: string
}

const loading = ref(true)
const saving = ref(false)
const calendars = ref<CalendarItem[]>([])
const holidays = ref<HolidayItem[]>([])
const selectedCalendarId = ref<number | null>(null)

const calendarModal = reactive({ open: false, mode: 'create' as 'create' | 'edit' })
const calendarForm = reactive({ name: '', year: new Date().getFullYear(), description: '' })
const calendarError = ref('')

const holidayModal = reactive({ open: false })
const holidayForm = reactive({ date: '', name: '', type: 'nasional' })
const holidayError = ref('')

function formatDate(d: string) {
  const [y, m, day] = d.split('-')
  return `${day}-${m}-${y}`
}

async function loadCalendars() {
  loading.value = true
  try {
    const data = await api<{ data: CalendarItem[] }>('GET', '/working-calendars')
    calendars.value = data.data
    if (calendars.value.length && selectedCalendarId.value === null) {
      selectedCalendarId.value = calendars.value[0].id
    }
    await loadHolidays()
  } catch (e: any) {
    calendarError.value = errorMessage(e, 'Gagal memuat kalender.')
  } finally {
    loading.value = false
  }
}

async function loadHolidays() {
  if (!selectedCalendarId.value) {
    holidays.value = []
    return
  }
  try {
    const data = await api<{ data: HolidayItem[] }>('GET', `/holidays?working_calendar_id=${selectedCalendarId.value}`)
    holidays.value = data.data
  } catch {
    holidays.value = []
  }
}

function selectCalendar(id: number) {
  selectedCalendarId.value = id
  loadHolidays()
}

function openCalendarModal() {
  calendarForm.name = ''
  calendarForm.year = new Date().getFullYear()
  calendarForm.description = ''
  calendarError.value = ''
  calendarModal.mode = 'create'
  calendarModal.open = true
}

async function submitCalendar() {
  saving.value = true
  calendarError.value = ''
  try {
    const res = await api<{ data: CalendarItem }>('POST', '/working-calendars', {
      name: calendarForm.name,
      year: calendarForm.year,
      description: calendarForm.description || null,
    })
    calendarModal.open = false
    selectedCalendarId.value = res.data.id
    await loadCalendars()
  } catch (e: any) {
    calendarError.value = errorMessage(e, 'Gagal menyimpan kalender.')
  } finally {
    saving.value = false
  }
}

function openHolidayModal() {
  holidayForm.date = new Date().toISOString().slice(0, 10)
  holidayForm.name = ''
  holidayForm.type = 'nasional'
  holidayError.value = ''
  holidayModal.open = true
}

async function submitHoliday() {
  saving.value = true
  holidayError.value = ''
  try {
    await api('POST', '/holidays', {
      working_calendar_id: selectedCalendarId.value,
      date: holidayForm.date,
      name: holidayForm.name || null,
      type: holidayForm.type,
    })
    holidayModal.open = false
    await loadHolidays()
    await loadCalendars() // refresh count
  } catch (e: any) {
    holidayError.value = errorMessage(e, 'Gagal menyimpan libur.')
  } finally {
    saving.value = false
  }
}

async function removeHoliday(h: HolidayItem) {
  if (!confirm(`Hapus libur ${h.name || h.date}?`)) return
  try {
    await api('DELETE', `/holidays/${h.id}`)
    await loadHolidays()
    await loadCalendars()
  } catch (e: any) {
    alert(errorMessage(e, 'Gagal menghapus libur.'))
  }
}

onMounted(loadCalendars)
</script>
