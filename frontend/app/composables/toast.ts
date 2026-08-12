import { reactive } from 'vue'

export interface Toast {
  id: number
  type: 'success' | 'error' | 'info'
  message: string
}

export const toasts = reactive<Toast[]>([])

let nextId = 1

function push(type: Toast['type'], message: string, duration = 3500) {
  const id = nextId++
  toasts.push({ id, type, message })
  setTimeout(() => remove(id), duration)
}

function remove(id: number) {
  const index = toasts.findIndex((t) => t.id === id)
  if (index !== -1) toasts.splice(index, 1)
}

/** Toast notification sederhana — ganti alert() untuk feedback singkat. */
export function useToast() {
  return {
    success: (message: string) => push('success', message),
    error: (message: string) => push('error', message),
    info: (message: string) => push('info', message),
    remove,
  }
}
