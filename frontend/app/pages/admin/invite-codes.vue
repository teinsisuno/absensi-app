<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-gray-900">Kode Unik</h1>
        <p class="text-sm text-gray-500">Kode sekali pakai untuk menautkan akun karyawan</p>
      </div>
      <button class="btn-primary" @click="openGenerate">+ Generate Kode</button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-sm text-gray-400">Memuat…</div>

    <div v-else-if="codes.length === 0" class="card p-10 text-center text-sm text-gray-400">
      Belum ada kode unik. Klik “Generate Kode” untuk membuat kode pertama.
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="w-full min-w-[720px] text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 font-medium">Karyawan</th>
            <th class="px-4 py-3 font-medium">Kode</th>
            <th class="px-4 py-3 font-medium">Status</th>
            <th class="px-4 py-3 font-medium">Dipakai Oleh</th>
            <th class="px-4 py-3 font-medium">Kadaluwarsa</th>
            <th class="px-4 py-3 text-right font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="item in codes" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ item.employee?.name || '—' }}</td>
            <td class="px-4 py-3">
              <code class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs tracking-wider text-gray-800">
                {{ item.code }}
              </code>
            </td>
            <td class="px-4 py-3">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusClass(item)"
              >
                {{ statusLabel(item) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ item.used_by_user?.name || '—' }}</td>
            <td class="px-4 py-3 text-gray-600">{{ formatDate(item.expires_at) }}</td>
            <td class="px-4 py-3">
              <div class="flex justify-end gap-1">
                <button class="rounded-lg px-2 py-1 text-xs text-primary-600 hover:bg-primary-50" @click="copyCode(item.code)">
                  📋 Salin
                </button>
                <button
                  v-if="canResend(item)"
                  class="rounded-lg px-2 py-1 text-xs text-indigo-600 hover:bg-indigo-50"
                  :title="'Generate kode baru untuk ' + item.employee?.name"
                  @click="generateFor(item.employee_id)"
                >
                  Buat Baru
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal generate: pilih karyawan belum link -->
    <AppModal v-if="genModal.open" :title="genModal.result ? 'Kode Unik Dibuat' : 'Generate Kode Unik'" @close="closeGen">
      <div v-if="genModal.result">
        <div class="text-center">
          <p class="mb-1 text-sm text-gray-500">Kode unik untuk <b>{{ genModal.employeeName }}</b></p>
          <p class="mb-4 text-3xl font-bold tracking-widest text-indigo-700">{{ genModal.result.code }}</p>
          <div class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">
            ⚠️ Kode hanya ditampilkan <b>sekali</b> dan berlaku sampai
            <b>{{ formatDate(genModal.result.expires_at) }}</b>. Bagikan ke karyawan lewat chat/SMS.
          </div>
          <div v-if="genModal.result.whatsapp_sent" class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
            ✅ Kode terkirim otomatis ke WhatsApp karyawan.
          </div>
          <div v-else-if="genModal.result.whatsapp_note" class="mb-4 rounded-lg bg-yellow-50 px-3 py-2 text-sm text-yellow-700">
            ⚠️ {{ genModal.result.whatsapp_note }}
          </div>
          <div class="flex justify-center gap-2">
            <button class="btn-primary" @click="copyResult">📋 Salin Kode</button>
            <button class="btn-secondary" @click="closeGen">Selesai</button>
          </div>
          <p v-if="copied" class="mt-3 text-sm text-green-600">Kode tersalin! ✓</p>
        </div>
      </div>
      <div v-else>
        <div class="mb-4">
          <label class="label">Karyawan (belum ter-link) <span class="text-red-500">*</span></label>
          <select v-model="genModal.employeeId" class="input">
            <option :value="null" disabled>— Pilih karyawan —</option>
            <option v-for="emp in unlinkedEmployees" :key="emp.id" :value="emp.id">
              {{ emp.name }}{{ emp.position ? ` — ${emp.position}` : '' }}
            </option>
          </select>
          <p v-if="unlinkedEmployees.length === 0" class="mt-2 text-xs text-gray-400">
            Semua karyawan sudah ter-link ke akun.
          </p>
        </div>
        <p v-if="genModal.error" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{{ genModal.error }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeGen">Batal</button>
          <button class="btn-primary" :disabled="!genModal.employeeId || saving" @click="submitGenerate">
            {{ saving ? 'Membuat…' : 'Generate' }}
          </button>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

interface InviteCodeItem {
  id: number
  employee_id: number
  code: string
  expires_at: string
  used_at: string | null
  created_at?: string
  employee?: { id: number; name: string } | null
  creator?: { id: number; name: string } | null
  used_by_user?: { id: number; name: string } | null
}

interface Employee {
  id: number
  name: string
  position?: string | null
  user?: { id: number } | null
}

const { data, refresh, pending: loading } = useApi<{ data: InviteCodeItem[] }>('/invite-codes')
const { data: empData } = useApi<{ data: Employee[] }>('/employees?status=active')

const codes = computed(() => data.value?.data || [])
const employees = computed(() => empData.value?.data || [])
const unlinkedEmployees = computed(() => employees.value.filter((e) => !e.user))

const genModal = reactive<{
  open: boolean
  employeeId: number | null
  employeeName: string
  result: { code: string; expires_at: string; whatsapp_sent?: boolean; whatsapp_note?: string | null } | null
  error: string
}>({ open: false, employeeId: null, employeeName: '', result: null, error: '' })
const saving = ref(false)
const copied = ref(false)

function openGenerate() {
  genModal.employeeId = null
  genModal.employeeName = ''
  genModal.result = null
  genModal.error = ''
  genModal.open = true
}

function closeGen() {
  genModal.open = false
  genModal.result = null
  genModal.error = ''
}

function statusLabel(item: InviteCodeItem) {
  if (item.used_at) return 'Terpakai'
  if (new Date(item.expires_at) < new Date()) return 'Kedaluwarsa'
  return 'Aktif'
}

function statusClass(item: InviteCodeItem) {
  if (item.used_at) return 'bg-blue-100 text-blue-700'
  if (new Date(item.expires_at) < new Date()) return 'bg-gray-100 text-gray-500'
  return 'bg-green-100 text-green-700'
}

function canResend(item: InviteCodeItem) {
  // Bisa bikin kode baru kalau kode lama sudah terpakai / kedaluwarsa & karyawan masih ada
  return !!item.employee_id && (!!item.used_at || new Date(item.expires_at) < new Date())
}

function formatDate(value?: string | null) {
  if (!value) return '—'
  return value.substring(0, 10)
}

async function copyCode(code: string) {
  try {
    await navigator.clipboard.writeText(code)
    alert('Kode disalin: ' + code)
  } catch {
    // clipboard tidak tersedia
  }
}

async function copyResult() {
  if (!genModal.result) return
  try {
    await navigator.clipboard.writeText(genModal.result.code)
    copied.value = true
    setTimeout(() => (copied.value = false), 2500)
  } catch {
    // clipboard tidak tersedia — biarkan user menyalin manual
  }
}

async function generateFor(employeeId: number) {
  // Buka modal dengan karyawan terpilih — HR mengonfirmasi dulu, baru POST saat klik Generate
  const emp = employees.value.find((e) => e.id === employeeId)
  genModal.employeeId = employeeId
  genModal.employeeName = emp?.name || ''
  genModal.result = null
  genModal.error = ''
  genModal.open = true
}

async function submitGenerate() {
  if (!genModal.employeeId) return
  genModal.error = ''
  saving.value = true
  try {
    const res = await api<{ data: { code: string; expires_at: string } }>('POST', '/invite-codes', {
      employee_id: genModal.employeeId,
    })
    const emp = employees.value.find((e) => e.id === genModal.employeeId)
    genModal.employeeName = emp?.name || ''
    genModal.result = res.data
    await refresh()
  } catch (e: any) {
    genModal.error = errorMessage(e)
  } finally {
    saving.value = false
  }
}
</script>
