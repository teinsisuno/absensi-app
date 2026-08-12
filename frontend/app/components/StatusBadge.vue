<template>
  <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium" :class="badgeClass(status)">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
const props = defineProps<{ status: string }>()

const STATUS_LABELS: Record<string, string> = {
  valid: 'Valid',
  out_of_radius_approved: 'Luar Radius',
  flagged: 'Perlu Cek',
}

const label = computed(() => STATUS_LABELS[props.status] ?? props.status)

function badgeClass(s: string) {
  if (s === 'valid') return 'bg-green-100 text-green-700'
  if (s === 'out_of_radius_approved') return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-600'
}
</script>
