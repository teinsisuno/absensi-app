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
}

interface TenantInfo {
  id: string
  name?: string
}

const TOKEN_KEY = 'absensi_token'
const USER_KEY = 'absensi_user'
const EMPLOYEE_KEY = 'absensi_employee'
const TENANT_KEY = 'absensi_tenant'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(null)
  const user = ref<AdminUser | null>(null)
  const employee = ref<EmployeeUser | null>(null)
  const tenant = ref<TenantInfo | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => !!user.value && ['owner', 'admin'].includes(user.value.role))
  const isEmployee = computed(() => !!employee.value)

  function persist() {
    if (import.meta.client) {
      if (token.value) localStorage.setItem(TOKEN_KEY, token.value)
      else localStorage.removeItem(TOKEN_KEY)
      localStorage.setItem(USER_KEY, JSON.stringify(user.value))
      localStorage.setItem(EMPLOYEE_KEY, JSON.stringify(employee.value))
      localStorage.setItem(TENANT_KEY, JSON.stringify(tenant.value))
    }
  }

  function restore() {
    if (import.meta.client) {
      token.value = localStorage.getItem(TOKEN_KEY)
      user.value = JSON.parse(localStorage.getItem(USER_KEY) || 'null')
      employee.value = JSON.parse(localStorage.getItem(EMPLOYEE_KEY) || 'null')
      tenant.value = JSON.parse(localStorage.getItem(TENANT_KEY) || 'null')
    }
  }

  /** Login admin/owner via token SSO dari Central */
  async function loginSso(ssoToken: string) {
    const data = await $fetch<{
      token: string
      user: AdminUser
      tenant: TenantInfo
    >('/auth/sso', {
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

  /** Login karyawan via nama + PIN */
  async function loginEmployee(name: string, pin: string) {
    const data = await $fetch<{
      token: string
      employee: EmployeeUser
    >('/auth/employee-login', {
      baseURL: apiBase(),
      method: 'POST',
      body: { name, pin },
    })
    token.value = data.token
    employee.value = data.employee
    user.value = null
    persist()
    return data
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
    persist()
  }

  return {
    token,
    user,
    employee,
    tenant,
    isLoggedIn,
    isAdmin,
    isEmployee,
    persist,
    restore,
    loginSso,
    loginEmployee,
    logout,
  }
})
