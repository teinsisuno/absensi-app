<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Pola Kerja</h1>
        <p class="text-sm text-gray-500">Durasi kerja, istirahat & aturan hari kerja (dalam jam)</p>
      </div>
      <button class="btn-primary" @click="openCreate">+ Tambah Pola</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <div v-else-if="patterns.length === 0" class="card p-10 text-center text-sm text-gray-400">
      Belum ada pola kerja.
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Kode</th>
            <th class="px-4 py-3 font-medium">Nama</th>
            <th class="px-4 py-3 font-medium">Hari Kerja</th>
            <th class="px-4 py-3 font-medium">Jam Kerja</th>
            <th class="px-4 py-3 font-medium">Istirahat</th>
            <th class="px-4 py-3 font-medium">Sabtu</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in patterns" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs font-medium text-gray-900">{{ p.code }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ p.name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ p.work_day }} hari</td>
            <td class="px-4 py-3 text-gray-600">{{ p.work_day_hours }} jam<span v-if="p.half_day_hours"> / {{ p.half_day_hours }} jam (½)</span></td>
            <td class="px-4 py-3 text-gray-600">{{ p.wd_rest_hours }} jam<span v-if="p.hd_rest_hours"> / {{ p.hd_rest_hours }} jam (½)</span></td>
            <td class="px-4 py-3 text-gray-600">{{ satLabel(p.sat_type) }}</td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ p.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="openEdit(p)">Edit</button>
                <button class="rounded-lg px-2 py-1 text-xs text-red-600 hover:bg-red-50" @click="remove(p)">Hapus</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal form -->
    <AppModal v-if="modal.open" :title="modal.mode === 'create' ? 'Tambah Pola Kerja' : 'Edit Pola Kerja'" @close="modal.open = false">
      <form @submit.prevent="submitForm">
        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Kode <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" class="input uppercase" placeholder="mis. STAF" required />
          </div>
          <div>
            <label class="label">Nama <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" class="input" placeholder="mis. Staf Toko" required />
          </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Hari Kerja / Minggu</label>
            <input v-model.number="form.work_day" type="number" min="1" max="7" class="input" />
          </div>
          <div>
            <label class="label">Jam Kerja / Hari (termasuk istirahat)</label>
            <input v-model.number="form.work_day_hours" type="number" min="1" max="24" class="input" />
          </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Jam Kerja Setengah Hari</label>
            <input v-model.number="form.half_day_hours" type="number" min="0" max="24" class="input" />
          </div>
          <div>
            <label class="label">Istirahat Hari Kerja (jam)</label>
            <input v-model.number="form.wd_rest_hours" type="number" min="0" max="24" class="input" />
          </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label class="label">Istirahat Setengah Hari (jam)</label>
            <input v-model.number="form.hd_rest_hours" type="number" min="0" max="24" class="input" />
          </div>
          <div>
            <label class="label">Sabtu</label>
            <select v-model="form.sat_type" class="input">
              <option value="off">Libur</option>
              <option value="full">Masuk penuh</option>
              <option value="half">Setengah hari</option>
            </select>
          </div>
        </div>

        <div class="mb-4 flex items-center gap-4">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.sun_overtime" type="checkbox" class="rounded border-gray-300" />
            Lembur Minggu
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
            Aktif
          </label>
        </div>

        <p v-if="error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="modal.open = false">Batal</button>
          <button type="submit" class="btn-primary" :disabled="saving">
            <span v-if="saving" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
            {{ saving ? 'Menyimpan…' : 'Simpan' }}
          </button>
        </div>
      </form>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface PatternItem {
  id: number
  code: string
  name: string
  work_day: number
  work_day_hours: number
  half_day_hours: number
  wd_rest_hours: number
  hd_rest_hours: number
  sat_type: string
  sun_overtime: boolean
  is_active: boolean
}

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const patterns = ref<PatternItem[]>([])

const modal = reactive({ open: false, mode: 'create' as 'create' | 'edit', id: null as number | null })
const form = reactive({
  code: '',
  name: '',
  work_day: 5,
  work_day_hours: 8,
  half_day_hours: 4,
  wd_rest_hours: 1,
  hd_rest_hours: 0,
  sat_type: 'off',
  sun_overtime: false,
  is_active: true,
})

function satLabel(s: string) {
  return s === 'full' ? 'Masuk penuh' : s === 'half' ? 'Setengah' : 'Libur'
}

async function load() {
  loading.value = true
  try {
    const data = await api<{ data: PatternItem[] }>('GET', '/work-patterns')
    patterns.value = data.data
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal memuat pola kerja.')
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.code = ''
  form.name = ''
  form.work_day = 5
  form.work_day_hours = 8
  form.half_day_hours = 4
  form.wd_rest_hours = 1
  form.hd_rest_hours = 0
  form.sat_type = 'off'
  form.sun_overtime = false
  form.is_active = true
  error.value = ''
}

function openCreate() {
  resetForm()
  modal.mode = 'create'
  modal.id = null
  modal.open = true
}

function openEdit(p: PatternItem) {
  resetForm()
  modal.mode = 'edit'
  modal.id = p.id
  Object.assign(form, {
    code: p.code,
    name: p.name,
    work_day: p.work_day,
    work_day_hours: p.work_day_hours,
    half_day_hours: p.half_day_hours,
    wd_rest_hours: p.wd_rest_hours,
    hd_rest_hours: p.hd_rest_hours,
    sat_type: p.sat_type,
    sun_overtime: p.sun_overtime,
    is_active: p.is_active,
  })
  modal.open = true
}

async function submitForm() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      code: form.code,
      name: form.name,
      work_day: form.work_day,
      work_day_hours: form.work_day_hours,
      half_day_hours: form.half_day_hours,
      wd_rest_hours: form.wd_rest_hours,
      hd_rest_hours: form.hd_rest_hours,
      sat_type: form.sat_type,
      sun_overtime: form.sun_overtime,
      is_active: form.is_active,
    }
    if (modal.mode === 'create') {
      await api('POST', '/work-patterns', payload)
    } else {
      await api('PUT', `/work-patterns/${modal.id}`, payload)
    }
    modal.open = false
    await load()
  } catch (e: any) {
    error.value = errorMessage(e, 'Gagal menyimpan pola kerja.')
  } finally {
    saving.value = false
  }
}

async function remove(p: PatternItem) {
  if (!confirm(`Hapus pola kerja "${p.name}"?`)) return
  try {
    await api('DELETE', `/work-patterns/${p.id}`)
    await load()
  } catch (e: any) {
    alert(errorMessage(e, 'Gagal menghapus pola kerja.'))
  }
}

onMounted(load)
</script>
