<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-gray-900/50" @click="$emit('close')" />
      <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-gray-900">{{ title }}</h2>
          <button
            type="button"
            class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
            aria-label="Tutup"
            @click="$emit('close')"
          >
            ✕
          </button>
        </div>
        <slot />
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
defineProps<{ title: string }>()
defineEmits<{ close: [] }>()
</script>
