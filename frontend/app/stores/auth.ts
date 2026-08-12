import { defineStore } from 'pinia'

interface AdminUser {
  id: number
  name: string
  email: string
  role: string
}

interface EmployeeUser {
  id: number
  name: string
  position?: string | null
  photo?: string | null
  mobile_role?: string | null
}

interface TenantInfo {
  id: string
  name?: string
}

const TOKEN_KEY = 'absensi_token'
const USER_KEY = 'absensi_user'
const EMPLOYEE_KEY = 'absensi_employee'
const TENANT_KEY = 'absensi_tenant'
const HAS_ACCOUNT_KEY = 'absensi_has_account'
const FACE_DONE_KEY = 'absensi_face_done'
const SETUP_CODE_KEY = 'absensi_setup_code'
const SETUP_VERIFIED_KEY = 'absensi_setup_verified'
const LAST_EMAIL_KEY = 'absensi_last_email'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(null)
  const user = ref<AdminUser | null>(null)
  const employee = ref<EmployeeUser | null>(null)
  const tenant = ref<TenantInfo | null>(null)
  /** Status scan wajah pada alur pengaturan awal (per perangkat). */
  const faceDone = ref(false)
  /** State pengaturan awal: kode unik yang sedang diisi + hasil verifikasi. Survive navigasi ke /setup/face. */
  const setupCode = ref('')
  const setupVerified = ref<EmployeeUser | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => !!user.value && ['owner', 'admin', 'superadmin', 'hr'].includes(user.value.role))
  const isEmployee = computed(() => !!employee.value)

  /** User baru pertama kali membuka app (belum pernah daftar di device ini). */
  const hasAccount = computed(() => import.meta.client ? localStorage.getItem(HAS_ACCOUNT_KEY) === '1' : false)

  /** Email terakhir yang dipakai login — dipakai prefill form PIN biar tinggal ketik PIN. */
  const lastEmail = ref<string>(import.meta.client ? localStorage.getItem(LAST_EMAIL_KEY) || '' : '')

  function rememberEmail(email: string) {
    lastEmail.value = email
    if (import.meta.client) localStorage.setItem(LAST_EMAIL_KEY, email)
  }

  function persist() {
    if (import.meta.client) {
      if (token.value) localStorage.setItem(TOKEN_KEY, token.value)
      else localStorage.removeItem(TOKEN_KEY)
      localStorage.setItem(USER_KEY, JSON.stringify(user.value))
      localStorage.setItem(EMPLOYEE_KEY, JSON.stringify(employee.value))
      localStorage.setItem(TENANT_KEY, JSON.stringify(tenant.value))
      localStorage.setItem(SETUP_CODE_KEY, setupCode.value)
      localStorage.setItem(SETUP_VERIFIED_KEY, JSON.stringify(setupVerified.value))
    }
  }

  function restore() {
    if (import.meta.client) {
      token.value = localStorage.getItem(TOKEN_KEY)
      user.value = JSON.parse(localStorage.getItem(USER_KEY) || 'null')
      employee.value = JSON.parse(localStorage.getItem(EMPLOYEE_KEY) || 'null')
      tenant.value = JSON.parse(localStorage.getItem(TENANT_KEY) || 'null')
      faceDone.value = localStorage.getItem(FACE_DONE_KEY) === '1'
      setupCode.value = localStorage.getItem(SETUP_CODE_KEY) || ''
      setupVerified.value = JSON.parse(localStorage.getItem(SETUP_VERIFIED_KEY) || 'null')
    }
  }

  /** Bersihkan state pengaturan awal (setelah berhasil link karyawan). */
  function clearSetup() {
    setupCode.value = ''
    setupVerified.value = null
    if (import.meta.client) {
      localStorage.removeItem(SETUP_CODE_KEY)
      localStorage.removeItem(SETUP_VERIFIED_KEY)
    }
  }

  /** Registrasi mandiri (email + nama + password) → langsung dapat token. */
  async function register(name: string, email: string, password: string) {
    const data = await $fetch<{
      token: string
      user: AdminUser
    }>('/auth/register', {
      baseURL: apiBase(),
      method: 'POST',
      body: { name, email, password },
    })
    token.value = data.token
    user.value = data.user
    employee.value = null
    persist()
    if (import.meta.client) localStorage.setItem(HAS_ACCOUNT_KEY, '1')
    return data
  }

  /** Set PIN 4-6 digit setelah register (dipakai login cepat). */
  async function setPin(pin: string) {
    const data = await $fetch<{ message: string }>('/auth/set-pin', {
      baseURL: apiBase(),
      method: 'POST',
      body: { pin },
      headers: { Authorization: `Bearer ${token.value}` },
    })
    return data
  }

  /** Cek kode unik dari HR → balikin data karyawan (nama muncul otomatis di UI). */
  async function verifyInvite(code: string) {
    return await $fetch<{
      employee: EmployeeUser
    }>('/auth/verify-invite', {
      baseURL: apiBase(),
      method: 'POST',
      body: { code },
      headers: { Authorization: `Bearer ${token.value}` },
    })
  }

  /** Pakai kode unik: link user ↔ karyawan. */
  async function linkEmployee(code: string) {
    const data = await $fetch<{
      message: string
      employee: EmployeeUser
    }>('/auth/link-employee', {
      baseURL: apiBase(),
      method: 'POST',
      body: { code },
      headers: { Authorization: `Bearer ${token.value}` },
    })
    employee.value = data.employee
    persist()
    return data
  }

  /** Login email + password. */
  async function login(email: string, password: string) {
    const data = await $fetch<{
      token: string
      user: AdminUser
      employee: EmployeeUser | null
    }>('/auth/login', {
      baseURL: apiBase(),
      method: 'POST',
      body: { email, password },
    })
    token.value = data.token
    user.value = data.user
    employee.value = data.employee
    persist()
    return data
  }

  /** Login cepat pakai PIN (email sebagai identitas). */
  async function pinLogin(email: string, pin: string) {
    const data = await $fetch<{
      token: string
      user: AdminUser
      employee: EmployeeUser | null
    }>('/auth/pin-login', {
      baseURL: apiBase(),
      method: 'POST',
      body: { email, pin },
    })
    token.value = data.token
    user.value = data.user
    employee.value = data.employee
    rememberEmail(email)
    persist()
    return data
  }

  /** Login biometrik (WebAuthn) — server cari user dari credential. */
  async function webauthnLogin(credential: any) {
    const data = await $fetch<{
      token: string
      user: AdminUser
      employee: EmployeeUser | null
    }>('/auth/webauthn/login', {
      baseURL: apiBase(),
      method: 'POST',
      body: { credential },
    })
    token.value = data.token
    user.value = data.user
    employee.value = data.employee
    if (data.user?.email) rememberEmail(data.user.email)
    persist()
    return data
  }

  /** Simpan kunci biometrik (WebAuthn) — wajib auth (login PIN dulu). */
  async function webauthnRegister(credential: any, name?: string) {
    return await $fetch<{
      message: string
      key: { id: number; name: string; created_at: string }
    }>('/auth/webauthn/register', {
      baseURL: apiBase(),
      method: 'POST',
      body: { credential, name },
      headers: { Authorization: `Bearer ${token.value}` },
    })
  }

  /** Daftar kunci biometrik user yang aktif. */
  async function webauthnKeys() {
    return await $fetch<{ data: { id: number; name: string; created_at: string }[] }>(
      '/auth/webauthn/keys',
      {
        baseURL: apiBase(),
        method: 'GET',
        headers: { Authorization: `Bearer ${token.value}` },
      }
    )
  }

  /** Hapus kunci biometrik. */
  async function webauthnDelete(id: number) {
    return await $fetch<{ message: string }>(`/auth/webauthn/keys/${id}`, {
      baseURL: apiBase(),
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` },
    })
  }

  /** Login admin/owner via token SSO dari Central */
  async function loginSso(ssoToken: string) {
    const data = await $fetch<{
      token: string
      user: AdminUser
      tenant: TenantInfo
    }>('/auth/sso', {
      baseURL: apiBase(),
      method: 'POST',
      body: { token: ssoToken },
    })
    token.value = data.token
    user.value = data.user
    employee.value = null
    tenant.value = data.tenant
    persist()
    return data
  }

  /** Login owner/admin langsung dari subdomain (email + password akun Central) */
  async function loginAdmin(email: string, password: string) {
    const data = await $fetch<{
      token: string
      user: AdminUser
      tenant: TenantInfo
    }>('/auth/admin-login', {
      baseURL: apiBase(),
      method: 'POST',
      body: { email, password },
    })
    token.value = data.token
    user.value = data.user
    employee.value = null
    tenant.value = data.tenant
    persist()
    return data
  }

  /** Tandai scan wajah selesai (alur pengaturan awal). */
  function markFaceDone() {
    faceDone.value = true
    if (import.meta.client) localStorage.setItem(FACE_DONE_KEY, '1')
  }

  async function logout() {
    if (token.value) {
      try {
        await $fetch('/auth/logout', {
          baseURL: apiBase(),
          method: 'POST',
          headers: { Authorization: `Bearer ${token.value}` },
        })
      } catch {
        // token sudah invalid — tetap lanjut logout lokal
      }
    }
    token.value = null
    user.value = null
    employee.value = null
    tenant.value = null
    faceDone.value = false
    clearSetup()
    if (import.meta.client) localStorage.removeItem(FACE_DONE_KEY)
    persist()
  }

  return {
    token,
    user,
    employee,
    tenant,
    faceDone,
    setupCode,
    setupVerified,
    isLoggedIn,
    isAdmin,
    isEmployee,
    hasAccount,
    lastEmail,
    rememberEmail,
    persist,
    restore,
    clearSetup,
    register,
    setPin,
    verifyInvite,
    linkEmployee,
    login,
    pinLogin,
    webauthnLogin,
    webauthnRegister,
    webauthnKeys,
    webauthnDelete,
    loginSso,
    loginAdmin,
    markFaceDone,
    logout,
  }
})
