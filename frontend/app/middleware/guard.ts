/**
 * Guard auth — hanya jalan di client (SPA mode).
 * Alur: splash → register/set-pin/setup (akun baru) → dashboard/clock.
 */
export default defineNuxtRouteMiddleware((to) => {
  if (import.meta.server) return

  const auth = useAuthStore()
  auth.restore()

  const isAdminRoute = to.path.startsWith('/admin')

  if (!auth.isLoggedIn) {
    // Halaman publik tanpa login
    const isPublicPage = ['/sso', '/login', '/register', '/splash'].includes(to.path)
    if (!isPublicPage) {
      return navigateTo(isAdminRoute ? '/sso' : '/login')
    }
    return
  }

  // Sudah login
  const home = () => (auth.isAdmin ? '/admin/employees' : auth.isEmployee ? '/dashboard' : '/setup')

  if (isAdminRoute && !auth.isAdmin) {
    // Karyawan tidak boleh buka halaman admin
    return navigateTo('/dashboard')
  }

  if (['/login', '/register', '/splash'].includes(to.path)) {
    return navigateTo(home())
  }

  if (to.path === '/sso' && auth.isLoggedIn) {
    return navigateTo(home())
  }
})
