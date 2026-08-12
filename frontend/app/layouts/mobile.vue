<template>
  <div class="mx-auto min-h-screen w-full max-w-md bg-gray-50">
    <slot />
    <!-- Spacer biar konten nggak ketutupan bottom nav -->
    <div class="h-24" />
    <MobileNav />
  </div>
</template>
