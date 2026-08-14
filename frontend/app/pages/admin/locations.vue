<template>
  <div />
</template>

<script setup lang="ts">
// Halaman lokasi kerja dipindah ke tab "Lokasi" di Pengaturan (v0.3+)
// Redirect biar link lama (/admin/locations) tidak 404.
definePageMeta({ layout: 'admin', middleware: 'guard' })

onMounted(() => {
  navigateTo('/admin/settings?tab=lokasi', { replace: true })
})
</script>
