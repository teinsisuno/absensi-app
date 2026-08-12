<template>
  <Teleport to="body">
    <Transition name="confirm">
      <div v-if="state.open" class="fixed inset-0 z-[110] flex items-end justify-center bg-black/40 p-4 sm:items-center" @click.self="reject">
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
          <h3 class="text-base font-semibold text-gray-900">{{ state.title }}</h3>
          <p v-if="state.message" class="mt-2 text-sm text-gray-500">{{ state.message }}</p>
          <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="btn-secondary" @click="reject">{{ state.cancelText }}</button>
            <button type="button" class="btn-primary" :class="state.danger && '!bg-red-600 hover:!bg-red-700'" @click="resolve">
              {{ state.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
const { state, resolve, reject } = useConfirm()
</script>

<style scoped>
.confirm-enter-active,
.confirm-leave-active {
  transition: opacity 0.2s ease;
}
.confirm-enter-active > div,
.confirm-leave-active > div {
  transition: transform 0.2s ease;
}
.confirm-enter-from,
.confirm-leave-to {
  opacity: 0;
}
.confirm-enter-from > div,
.confirm-leave-to > div {
  transform: translateY(16px);
}
</style>
