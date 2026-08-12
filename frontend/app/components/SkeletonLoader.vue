<template>
  <div class="card p-6">
    <div class="space-y-4">
      <div v-for="i in rows" :key="i" class="animate-pulse space-y-2">
        <div class="h-3 w-2/5 rounded bg-gray-200"></div>
        <div class="h-3 w-4/5 rounded bg-gray-100"></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ rows?: number }>(), { rows: 3 })
</script>
