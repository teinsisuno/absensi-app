<template>
  <div class="py-10 text-center text-sm text-gray-500">Mengalihkan…</div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default', middleware: 'guard' })

onMounted(() => {
  const auth = useAuthStore()
  auth.restore()
  if (auth.isAdmin) navigateTo('/admin/employees')
  else if (auth.isEmployee) navigateTo('/clock')
  else navigateTo('/login')
})
</script>
