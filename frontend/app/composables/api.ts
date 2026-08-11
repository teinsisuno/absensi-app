import type { UseFetchOptions } from 'nuxt/app'

/** Base URL API tenant — dev: /api/v1 (proxy nitro), prod: same origin. */
export function apiBase() {
  return useRuntimeConfig().public.apiBase as string
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
