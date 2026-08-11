// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // SPA mode: hasil `nuxt generate` = index.html + assets,
  // di-serve dari public/ Laravel (1 origin, tanpa CORS), auth di localStorage.
  ssr: false,

  modules: [
    '@nuxtjs/tailwindcss',
    '@vite-pwa/nuxt',
    '@pinia/nuxt',
  ],

  tailwindcss: {
    cssPath: '~/assets/css/main.css',
    configPath: '~/tailwind.config.ts',
  },

  runtimeConfig: {
    public: {
      // Base URL API tenant.
      // Dev: langsung ke artisan serve (CORS Laravel default allow-all + Bearer token, tanpa cookie).
      // Prod (same origin): /api/v1 — di-serve Laravel, set NUXT_PUBLIC_API_BASE saat build.
      apiBase:
        process.env.NUXT_PUBLIC_API_BASE ||
        (process.env.NODE_ENV === 'production' ? '/api/v1' : 'http://tokoa-absensi.test:8000/api/v1'),
    },
  },

  pwa: {
    registerType: 'autoUpdate',
    manifest: {
      name: 'Absensi Karyawan',
      short_name: 'Absensi',
      description: 'Aplikasi absensi karyawan — clock in/out dengan GPS',
      lang: 'id',
      theme_color: '#4f46e5',
      background_color: '#f3f4f6',
      display: 'standalone',
      icons: [
        {
          src: '/icons/icon-192.png',
          sizes: '192x192',
          type: 'image/png',
          purpose: 'any maskable',
        },
        {
          src: '/icons/icon-512.png',
          sizes: '512x512',
          type: 'image/png',
          purpose: 'any maskable',
        },
      ],
    },
    workbox: {
      globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
    },
    devOptions: { enabled: false },
  },

  app: {
    head: {
      title: 'Absensi',
      htmlAttrs: { lang: 'id' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'theme-color', content: '#4f46e5' },
      ],
    },
  },
})
