<template>
  <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200/60 bg-white/85 backdrop-blur-lg" style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="mx-auto flex max-w-md items-center justify-around px-2 py-2">
      <NavBtn :to="'/dashboard'" :active="isActive('/dashboard')" label="Beranda">
        <HomeIcon :active="isActive('/dashboard')" />
      </NavBtn>

      <NavBtn :to="'/attendance'" :active="isActive('/attendance')" label="Absensi">
        <CalendarIcon :active="isActive('/attendance')" />
      </NavBtn>

      <!-- Tombol tengah: Absen (FAB) -->
      <button
        type="button"
        class="relative -mt-8 flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg shadow-primary-600/30 transition active:scale-95"
        @click="absenModal.open = true"
      >
        <FingerprintIcon class="h-6 w-6" />
      </button>

      <NavBtn :to="'/calendar'" :active="isActive('/calendar')" label="Jadwal">
        <ScheduleIcon :active="isActive('/calendar')" />
      </NavBtn>

      <NavBtn :to="'/profile'" :active="isActive('/profile')" label="Profil">
        <UserIcon :active="isActive('/profile')" />
      </NavBtn>
    </div>

    <!-- Modal pilih aksi absensi (dari tombol fingerprint tengah) -->
    <AbsenModal v-if="absenModal.open" @close="absenModal.open = false" />
  </nav>
</template>

<script setup lang="ts">
const route = useRoute()

const absenModal = reactive({ open: false })

function isActive(path: string) {
  return route.path === path
}
</script>

<script lang="ts">
// Ikon SVG sederhana (tanpa dependency font-awesome)
import { defineComponent, h } from 'vue'

const NavBtn = defineComponent({
  props: { to: { type: String, required: true }, active: Boolean, label: { type: String, required: true } },
  setup(props, { slots }) {
    return () =>
      h(
        'button',
        {
          type: 'button',
          class: ['flex flex-col items-center gap-1 px-4 py-1 transition', props.active ? 'text-primary-600' : 'text-gray-400'],
          onClick: () => navigateTo(props.to),
        },
        [slots.default?.(), h('span', { class: 'text-[10px] font-medium' }, props.label)],
      )
  },
})

const HomeIcon = defineComponent({
  props: { active: Boolean },
  setup(props) {
    return () =>
      h(
        'svg',
        {
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': 2,
          class: 'h-6 w-6',
        },
        [
          h('path', {
            d: 'M3 10.5L12 3l9 7.5',
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
          }),
          h('path', {
            d: 'M5 9.5V21h5v-6h4v6h5V9.5',
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round',
          }),
        ],
      )
  },
})

const CalendarIcon = defineComponent({
  props: { active: Boolean },
  setup() {
    return () =>
      h(
        'svg',
        {
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': 2,
          class: 'h-6 w-6',
        },
        [
          h('rect', { x: 3, y: 5, width: 18, height: 16, rx: 2 }),
          h('path', { d: 'M8 3v4M16 3v4M3 10h18' }),
        ],
      )
  },
})

const FingerprintIcon = defineComponent({
  setup() {
    return () =>
      h(
        'svg',
        {
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': 2,
          class: 'h-6 w-6',
        },
        [
          h('path', {
            d: 'M12 11a3 3 0 0 1 3 3c0 2.5-.8 5-2 7M9.3 6.6A6 6 0 0 1 18 14M6.5 14a5.5 5.5 0 0 0 .5 2M4.6 10.3A8 8 0 0 1 12 4',
            'stroke-linecap': 'round',
          }),
          h('path', { d: 'M12 14a2.5 2.5 0 0 0 .5 5', 'stroke-linecap': 'round' }),
        ],
      )
  },
})

const ScheduleIcon = defineComponent({
  props: { active: Boolean },
  setup() {
    return () =>
      h(
        'svg',
        {
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': 2,
          class: 'h-6 w-6',
        },
        [
          h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2 }),
          h('path', { d: 'M16 2v4M8 2v4M3 10h18' }),
          h('path', { d: 'M9 15l2 2 4-4', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }),
        ],
      )
  },
})

const UserIcon = defineComponent({
  props: { active: Boolean },
  setup() {
    return () =>
      h(
        'svg',
        {
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: 'currentColor',
          'stroke-width': 2,
          class: 'h-6 w-6',
        },
        [
          h('circle', { cx: 12, cy: 8, r: 4 }),
          h('path', { d: 'M4 21a8 8 0 0 1 16 0' }),
        ],
      )
  },
})
</script>
