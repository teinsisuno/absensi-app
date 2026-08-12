<template>
  <button
    type="button"
    class="rounded-2xl border border-gray-100 bg-white p-4 text-left shadow-sm transition active:scale-[0.98]"
    :class="disabled ? 'opacity-60' : 'group hover:shadow-md'"
    :disabled="disabled"
    @click="go"
  >
    <div
      class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl transition"
      :class="iconClass"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
        <template v-if="icon === 'clock'">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 7v5l3 3" stroke-linecap="round" stroke-linejoin="round" />
        </template>
        <template v-else-if="icon === 'file'">
          <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M14 3v5h5M9 13h6M9 17h4" stroke-linecap="round" stroke-linejoin="round" />
        </template>
        <template v-else-if="icon === 'map'">
          <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z" stroke-linecap="round" stroke-linejoin="round" />
          <circle cx="12" cy="10" r="2.5" />
        </template>
        <template v-else-if="icon === 'tasks'">
          <rect x="4" y="4" width="16" height="16" rx="2" />
          <path d="M9 9l2 2 4-4M9 15l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
        </template>
      </svg>
    </div>
    <p class="text-sm font-semibold text-gray-800">{{ label }}</p>
    <p class="mt-0.5 text-xs text-gray-400">{{ sub }}</p>
  </button>
</template>

<script setup lang="ts">
const props = defineProps<{
  icon: 'clock' | 'file' | 'map' | 'tasks'
  color: 'primary' | 'warning' | 'success' | 'purple'
  label: string
  sub: string
  to?: string
  disabled?: boolean
}>()

const emit = defineEmits<{ click: [] }>()

const colorClasses: Record<string, string> = {
  primary: 'bg-primary-600/10 text-primary-600 group-hover:bg-primary-600 group-hover:text-white',
  warning: 'bg-amber-500/10 text-amber-500 group-hover:bg-amber-500 group-hover:text-white',
  success: 'bg-emerald-500/10 text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white',
  purple: 'bg-purple-500/10 text-purple-500 group-hover:bg-purple-500 group-hover:text-white',
}

const iconClass = computed(() => colorClasses[props.color])

function go() {
  if (props.to) navigateTo(props.to)
  else emit('click')
}
</script>
