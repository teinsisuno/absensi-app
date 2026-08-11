import type { UseFetchOptions } from 'nuxt/app'

/**
 * Base URL API tenant.
 * - Prod: same origin (`/api/v1`, dari runtimeConfig).
 * - Dev: backend di port 8000, hostname mengikuti tenant yang dibuka
 *   (tenancy by domain — biar tenant selain `tokoa` juga bisa dicoba lokal).
 */
export function apiBase() {
  const configured = useRuntimeConfig().public.apiBase as string
  if (import.meta.server) return configured
  if (process.env.NODE_ENV !== 'production') {
    return `http://${window.location.hostname}:8000/api/v1`
  }
  return configured
}

function authHeaders() {
  const auth = useAuthStore()
  return auth.token ? { Authorization: `Bearer ${auth.token}` } : {}
}

/** useFetch wrapper: otomatis attach Bearer token. */
export function useApi<T>(url: string, options: UseFetchOptions<T> = {}) {
  return useFetch<T>(url, {
    baseURL: apiBase(),
    headers: { ...authHeaders(), ...(options.headers || {}) },
    ...options,
  })
}

/** $fetch wrapper untuk POST/PUT/DELETE dengan token otomatis. */
export async function api<T = any>(method: string, url: string, body?: any) {
  return $fetch<T>(url, {
    baseURL: apiBase(),
    method: method as any,
    body,
    headers: authHeaders(),
  })
}

/** Helper parse error Laravel (422) jadi pesan ramah. */
export function errorMessage(err: any, fallback = 'Terjadi kesalahan, coba lagi.') {
  const data = err?.data
  if (typeof data?.message === 'string') return data.message
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first) && first.length) return first[0] as string
  }
  return fallback
}
