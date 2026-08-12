<template>
  <div class="py-10 text-center text-sm text-gray-500">Mengalihkan…</div>
</template>

<script setup lang="ts">
definePageMeta({ layout: false })

onMounted(() => {
  navigateTo('/splash')
})
</script>
