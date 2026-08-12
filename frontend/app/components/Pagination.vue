<template>
  <div v-if="pages > 1" class="mt-4 flex items-center justify-between gap-2 text-sm">
    <button type="button" class="btn-secondary !px-3 !py-1.5 !text-xs" :disabled="page <= 1" @click="go(page - 1)">
      ← Sebelumnya
    </button>
    <span class="text-gray-500">
      Halaman <b class="text-gray-700">{{ page }}</b> / {{ pages }}
    </span>
    <button type="button" class="btn-secondary !px-3 !py-1.5 !text-xs" :disabled="page >= pages" @click="go(page + 1)">
      Berikutnya →
    </button>
  </div>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{ page: number; pageSize: number; total: number }>(), { pageSize: 20 })

const emit = defineEmits<{ (e: 'update:page', page: number): void }>()

const pages = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))

function go(p: number) {
  if (p < 1 || p > pages.value) return
  emit('update:page', p)
}
</script>
