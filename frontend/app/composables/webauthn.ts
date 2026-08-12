/**
 * WebAuthn (passkey/biometrik) helpers untuk PWA.
 *
 * Alur:
 * - Aktivasi: login PIN → server beri creation options → navigator.credentials.create()
 *   → kirim credential → server simpan kunci publik.
 * - Login: server beri request options (userless) → navigator.credentials.get()
 *   → browser minta sidik jari / Face ID → kirim assertion → dapat token.
 *
 * Catatan browser:
 * - Semua field binary (challenge, user.id, allowCredentials[].id, dll) wajib
 *   ArrayBuffer — server mengirim base64url string, jadi konversi di sini.
 * - Response credential (rawId, clientDataJSON, attestationObject, dll) berupa
 *   ArrayBuffer — wajib diubah ke base64url string sebelum dikirim ke server.
 */

/** Konversi base64url string → ArrayBuffer (untuk publicKey options). */
export function b64urlToBuffer(b64url: string): ArrayBuffer {
  const base64 = b64url.replace(/-/g, '+').replace(/_/g, '/')
  const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=')
  const binary = atob(padded)
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i)
  return bytes.buffer
}

/** Konversi ArrayBuffer → base64url string (untuk kirim credential ke server). */
export function bufferToB64url(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer)
  let binary = ''
  bytes.forEach((b) => (binary += String.fromCharCode(b)))
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

/** Ubah creation options dari server → siap dipakai navigator.credentials.create(). */
export function toCreationOptions(options: any) {
  const publicKey: any = {
    ...options,
    challenge: b64urlToBuffer(options.challenge),
  }
  if (options.user?.id) publicKey.user = { ...options.user, id: b64urlToBuffer(options.user.id) }
  if (options.excludeCredentials?.length) {
    publicKey.excludeCredentials = options.excludeCredentials.map((c: any) => ({
      ...c,
      id: b64urlToBuffer(c.id),
    }))
  }
  return publicKey
}

/** Ubah request options dari server → siap dipakai navigator.credentials.get(). */
export function toRequestOptions(options: any) {
  const publicKey: any = {
    ...options,
    challenge: b64urlToBuffer(options.challenge),
  }
  if (options.allowCredentials?.length) {
    publicKey.allowCredentials = options.allowCredentials.map((c: any) => ({
      ...c,
      id: b64urlToBuffer(c.id),
    }))
  }
  return publicKey
}

/** Ubah credential hasil browser → JSON yang bisa dikirim ke server. */
export function serializeCredential(credential: any) {
  const response: Record<string, any> = {}
  for (const [key, value] of Object.entries(credential.response || {})) {
    response[key] = value instanceof ArrayBuffer ? bufferToB64url(value) : value
  }
  return {
    id: credential.id,
    rawId: bufferToB64url(credential.rawId),
    type: credential.type,
    response,
  }
}

/** Cek dukungan biometrik platform (sidik jari / Face ID) di browser ini. */
export async function isBiometricAvailable(): Promise<boolean> {
  if (!import.meta.client) return false
  if (!window.PublicKeyCredential) return false
  try {
    return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
  } catch {
    return false
  }
}

/** Apakah browser ini bisa melakukan WebAuthn sama sekali (secure context + API ada). */
export function isWebauthnSupported(): boolean {
  return import.meta.client && !!window.PublicKeyCredential
}
