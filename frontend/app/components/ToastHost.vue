<template>
  <div class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4">
    <TransitionGroup name="toast">
      <div
        v-for="t in toasts"
        :key="t.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur"
        :class="toastClass(t.type)"
      >
        <span class="mt-0.5 text-sm">{{ icon(t.type) }}</span>
        <p class="flex-1 text-sm font-medium">{{ t.message }}</p>
        <button type="button" class="opacity-60 hover:opacity-100" @click="remove(t.id)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
const { toasts, remove } = useToast()

function toastClass(type: string) {
  if (type === 'success') return 'border-emerald-200 bg-emerald-50/95 text-emerald-800'
  if (type === 'error') return 'border-red-200 bg-red-50/95 text-red-800'
  return 'border-sky-200 bg-sky-50/95 text-sky-800'
}

function icon(type: string) {
  if (type === 'success') return '✓'
  if (type === 'error') return '✕'
  return 'ℹ'
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
