# Absensi — Frontend Nuxt4 (Rencana Eksekusi)

> Handoff doc: backend Sprint 1-2 SELESAI (37 test pass). Sesi berikutnya kerjakan frontend Nuxt4.
> Sumber kebenaran: `docs/PRD_ABSENSI_URANOP.md` (portal-app), bagian 15 Frontend Pages.

## 1. Stack & Struktur

- Nuxt4 + Vue 3 + Tailwind CSS, PWA via `@vite-pwa/nuxt` (PRD §8 Compatibility)
- Lokasi: `H:\laragon\www\absensi-app\frontend\` (subfolder repo Laravel, biar 1 git repo — atau repo terpisah kalau mau)
- Runtime: **SSG (static)** untuk MVP — hasil build ditaruh di `public/` Laravel, sehingga satu origin: `https://{slug}-absensi.megakomsel.com` (API + frontend satu domain, tanpa CORS/token cross-origin)
  - Apache/Nginx serve `index.html` dulu (DirectoryIndex), route `/api/*` tetap jatuh ke Laravel index.php
  - Alternatif (kalau mau SSR): proxy di mini-pc — `/` → Nuxt :3000, `/api` → Laravel :8080. Catatan: tunnel Zero Trust cuma mengarah ke SATU service URL, jadi perlu reverse proxy di 10.10.10.122.

## 2. Dev Setup

```bash
cd /h/laragon/www/absensi-app
npx nuxi@latest init frontend --packageManager npm   # pilih Nuxt 4
cd frontend
npm i @nuxtjs/tailwindcss @vite-pwa/nuxt pinia @pinia/nuxt
```

- Dev server Nuxt: `npm run dev` (port 3000). Panggil API pakai `$fetch('/api/v1/...')` dengan `runtimeConfig.apiBase` (dev → proxy, prod → same origin).
- **Dev proxy** di `nuxt.config.ts`:
  ```ts
  nitro: { devProxy: { '/api': { target: 'http://tokoa-absensi.test', changeOrigin: true } } }
  ```
- **Tenancy butuh subdomain**: middleware `InitializeTenancyByDomain` hanya jalan untuk host `{slug}-absensi.*`. Untuk tes lokal:
  - Laragon vhost: `tokoa-absensi.test` → `H:\laragon\www\absensi-app\public`
  - Hosts: `127.0.0.1 tokoa-absensi.test`
  - Lalu provision tenant `tokoa` dulu: `POST http://127.0.0.1:8000/api/v1/provisioning/tenant` dengan header `X-Webhook-Secret` (cek `config/absensi.php` + `.env`) atau via test helper.
  - Kalau mau cepat tanpa provisioning manual: jalankan backend test seed (lihat `tests/Feature/ProvisioningTest.php` untuk payload webhook).
- CORS: config default Laravel (allowed_origins `*`) — request dari :3000 ke API aman. Pakai **Bearer token**, bukan cookie.

## 3. Auth Flow

### Admin/Owner (dari Central)
1. Central generate signed token (JWT short-lived, one-time) lalu redirect ke `https://{slug}-absensi.megakomsel.com/sso?token=xxx`
2. Page `/sso` → `POST /api/v1/auth/sso` `{ token }` → respon `{ token, user: {id,name,email,role}, tenant }`
3. Simpan bearer token (pinia store + localStorage), redirect ke `/admin/dashboard` (atau `/clock` kalau admin juga employee)

### Karyawan (PIN)
1. Page `/login` → input nama + PIN (4-6 digit) → `POST /api/v1/auth/employee-login` `{ name, pin }`
2. Respon `{ token, employee: {id,name,position,photo} }` → simpan token → redirect `/clock`
3. Salah PIN → 401 dengan message; 5x gagal → lock 15 menit (message "Terlalu banyak percobaan...")
4. Logout: `POST /api/v1/auth/logout` (hapus token dari store)

Semua request terproteksi pakai header `Authorization: Bearer <token>`.

## 4. API Contract (Backend Siap — jangan ubah tanpa test)

Base URL: `/api/v1` (same origin saat prod; di dev pakai proxy)

| Method | Endpoint | Auth | Request | Response |
|--------|----------|------|---------|----------|
| POST | `/auth/sso` | - | `{ token }` | `{ token, user:{id,name,email,role}, tenant }` |
| POST | `/auth/employee-login` | - | `{ name, pin }` | `{ token, employee:{id,name,position,photo} }` |
| POST | `/auth/logout` | Bearer | - | `{ message }` |
| GET | `/employees?status=active\|inactive` | admin | - | `{ data: [{ id,name,photo,position,work_location_id,shift_id,supervisor_id,status,work_location,shift }] }` |
| POST | `/employees` | admin | `{ name*, photo?, position?, work_location_id?, shift_id?, supervisor_id?, status? }` | 201 `{ message, data, pin }` — **PIN hanya muncul sekali** |
| PUT | `/employees/{id}` | admin | sama (name wajib) | `{ data }` |
| POST | `/employees/{id}/reset-pin` | admin | - | `{ message, pin }` — PIN baru sekali tampil |
| DELETE | `/employees/{id}` | admin | - | `{ message }` (soft → status inactive) |
| GET | `/work-locations` | admin | - | `{ data: [{ id,name,latitude,longitude,radius_meter,is_active }] }` |
| POST | `/work-locations` | admin | `{ name*, latitude*, longitude*, radius_meter?, is_active? }` | 201 `{ data }` (radius default 100) |
| PUT | `/work-locations/{id}` | admin | sama | `{ data }` |
| DELETE | `/work-locations/{id}` | admin | - | `{ message }` |
| POST | `/attendance/clock-in` | karyawan | `{ latitude*, longitude*, selfie_photo? }` | 201 `{ message, data:{ ..., work_location } }`; 422 `{ message }` kalau luar radius / dobel clock-in |
| POST | `/attendance/clock-out` | karyawan | sama | 201 / 422 (kalau belum clock-in) |
| GET | `/attendance/me?date=YYYY-MM-DD` | karyawan | - | `{ data: [...] }` riwayat sendiri |

Catatan:
- Semua field bertanda * wajib. Validasi error → 422 (Laravel default JSON errors).
- `data.pin_hash` selalu di-hidden (jangan tampilkan).
- Status absen: `valid` / `out_of_radius_approved` / `flagged` (MVP: cuma `valid` yang dipakai).
- Waktu absen diambil dari server (`recorded_at`).

## 5. Pages (Sprint 2 scope — sesuai PRD §15)

| Route | Page | Prioritas | Isi |
|-------|------|-----------|-----|
| `/sso` | SSO handler | Must | baca `?token=`, POST `/auth/sso`, simpan, redirect |
| `/login` | Login karyawan | Must | input nama + PIN (≤3 tap), error state, lock message |
| `/clock` | Halaman absen | Must | status sesi (dari `/attendance/me`), tombol Clock In/Out, `navigator.geolocation` → POST clock-in/out, tampilkan pesan sukses/gagal + jarak |
| `/admin/employees` | Kelola karyawan | Must | tabel + form tambah/edit (nama, jabatan, lokasi, shift), tombol Reset PIN (tampilkan PIN sekali dalam modal), nonaktifkan |
| `/admin/locations` | Kelola lokasi | Must | tabel + form (nama, lat/lng, radius), bisa ambil koordinat dari browser |

Layout: admin pages butuh guard role (user.role owner/admin); `/clock` guard token karyawan.

## 6. Pitfalls

- **Tenancy = subdomain wajib**: jangan panggil API tenant dari `localhost`/`127.0.0.1` (PreventAccessFromCentralDomains → 404). Selalu via host `{slug}-absensi.*`.
- Token karyawan TIDAK bisa akses `/employees*` & `/work-locations*` (403), token admin TIDAK bisa clock-in (403). Frontend harus bedain state.
- PIN hanya muncul SEKALI — desain UI harus nyuruh admin salin dulu sebelum tutup modal (kasih tombol "Salin" + warning).
- `selfie_photo` di MVP berupa string path/URL (upload file + kompres = Sprint 3). Kirim null dulu.
- PWA offline queue = Sprint 3 (FR-009) — jangan dikerjain dulu, fokus halaman inti.
- JANGAN ikutkan `public/hot` ke image produksi (sudah di .dockerignore — jangan diubah).

## 7. Optional Enhancement (kecil, backend)

Login karyawan lebih enak pakai nama dari list (shared device): tambah endpoint publik
`GET /api/v1/auth/employee-names` → `{ data: [{ id, name }] }` (hanya karyawan active, tanpa PIN).
Kalau dikerjain: 1 controller method + route + 1 test.
