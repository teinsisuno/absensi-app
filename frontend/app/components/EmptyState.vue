<template>
  <div class="card flex flex-col items-center justify-center p-10 text-center">
    <div class="text-4xl">{{ icon }}</div>
    <p class="mt-3 text-sm font-medium text-gray-700">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-xs text-gray-400">{{ description }}</p>
    <slot />
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ icon?: string; title: string; description?: string }>(), {
  icon: '🗂️',
})
</script>
