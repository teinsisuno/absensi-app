/**
 * Guard auth — hanya jalan di client (SPA mode).
 * Redirect berdasarkan tipe login: admin vs karyawan.
 */
export default defineNuxtRouteMiddleware((to) => {
  if (import.meta.server) return

  const auth = useAuthStore()
  auth.restore()

  const isAdminRoute = to.path.startsWith('/admin')

  if (!auth.isLoggedIn) {
    // Kalau ada token SSO di query, biarkan /sso menanganinya
    if (to.path !== '/sso' && !(to.path === '/login' && to.query.token)) {
      return navigateTo(isAdminRoute ? '/sso' : '/login')
    }
    return
  }

  if (isAdminRoute && !auth.isAdmin) {
    // Karyawan tidak boleh buka halaman admin
    return navigateTo('/clock')
  }

  if (to.path === '/login' && auth.isLoggedIn) {
    return navigateTo(auth.isAdmin ? '/admin/employees' : '/clock')
  }

  if (to.path === '/clock' && auth.isAdmin && !auth.isEmployee) {
    // Admin bisa buka /clock juga (kalau admin = karyawan) — biarkan
  }
})
