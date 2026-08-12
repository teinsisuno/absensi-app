<template>
  <div class="mt-3">
    <img
      v-if="photo"
      :src="photo"
      alt="Foto selfie absen"
      class="h-36 w-28 rounded-lg border border-gray-200 bg-white object-cover shadow-sm"
    />
    <div
      v-else
      class="flex h-24 w-28 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white text-gray-400"
    >
      <span class="text-xl">📷</span>
      <span class="mt-1 text-[10px]">Tanpa foto</span>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ photo?: string | null }>()
</script>
