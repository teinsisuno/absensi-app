import { reactive } from 'vue'

interface ConfirmOptions {
  title: string
  message?: string
  confirmText?: string
  cancelText?: string
  danger?: boolean
}

interface ConfirmState {
  open: boolean
  title: string
  message: string
  confirmText: string
  cancelText: string
  danger: boolean
  resolve: ((value: boolean) => void) | null
}

const state = reactive<ConfirmState>({
  open: false,
  title: '',
  message: '',
  confirmText: 'Ya',
  cancelText: 'Batal',
  danger: false,
  resolve: null,
})

/** Konfirmasi dialog promise-based — ganti confirm() native. */
export function useConfirm() {
  function confirm(options: ConfirmOptions | string): Promise<boolean> {
    const opts = typeof options === 'string' ? { title: options } : options
    state.title = opts.title
    state.message = opts.message || ''
    state.confirmText = opts.confirmText || 'Ya'
    state.cancelText = opts.cancelText || 'Batal'
    state.danger = opts.danger ?? false
    state.open = true
    return new Promise((resolve) => {
      state.resolve = resolve
    })
  }

  function settle(value: boolean) {
    state.open = false
    state.resolve?.(value)
    state.resolve = null
  }

  return { confirm, resolve: () => settle(true), reject: () => settle(false), state }
}
