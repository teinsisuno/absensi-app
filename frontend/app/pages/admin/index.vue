<template>
  <div class="py-10 text-center text-sm text-gray-500">Mengalihkan ke halaman karyawan…</div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'guard' })

onMounted(() => navigateTo('/admin/employees'))
</script>
