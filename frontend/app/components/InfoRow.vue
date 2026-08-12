<template>
  <div class="flex items-center justify-between border-b border-gray-100 p-4 last:border-b-0">
    <div class="flex items-center gap-3">
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600/10 text-primary-600">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
          <template v-if="icon === 'id'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <circle cx="8" cy="11" r="2" />
            <path d="M5 17c.5-2 1.5-3 3-3s2.5 1 3 3M14 9h5M14 13h5" stroke-linecap="round" />
          </template>
          <template v-else-if="icon === 'mail'">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="M3 7l9 6 9-6" stroke-linecap="round" stroke-linejoin="round" />
          </template>
          <template v-else-if="icon === 'phone'">
            <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.8.7a2 2 0 0 1 1.7 2z" stroke-linecap="round" stroke-linejoin="round" />
          </template>
          <template v-else-if="icon === 'shift'">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
          </template>
        </svg>
      </div>
      <div>
        <p class="text-sm font-medium text-gray-800">{{ label }}</p>
        <p class="text-xs text-gray-400">{{ value }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  icon: 'id' | 'mail' | 'phone' | 'shift'
  label: string
  value: string
}>()
</script>
