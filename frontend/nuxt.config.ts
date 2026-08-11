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
      // Base URL API tenant. Prod: same origin → /api/v1 (di-serve Laravel).
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '/api/v1',
    },
  },

  nitro: {
    devProxy: {
      // Saat `npm run dev` (port 3000), request /api/v1 diteruskan ke backend tenant.
      // Ganti API_PROXY_TARGET sesuai vhost tenant yang mau dites.
      '/api/v1': {
        target: process.env.API_PROXY_TARGET || 'http://tokoa-absensi.test',
        changeOrigin: true,
      },
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
