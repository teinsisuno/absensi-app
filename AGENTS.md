# absensi-app — Produk Absensi (megakomsel.com)

## Konteks Project
Aplikasi Absensi (mirip Talenta) — produk pertama ekosistem megakomsel.com. Central hub = `H:\laragon\www\portal-app` (registrasi, tenant, subscription, SSO). Absensi = app tenant terpisah: Laravel 13 API + Nuxt4 frontend (folder `frontend/`). Arsitektur: **1 tenant = 1 DB** (`tenant_absensi_{slug}`) via stancl/tenancy. Detail PRD: `docs/PRD_ABSENSI_URANOP.md`.

## Status (2026-08-12)
- **Backend Sprint 1-3 SELESAI** (58 test pass): provisioning webhook, SSO Central, auth v0.3 (register email+pw → set PIN → kode unik HR link user↔karyawan → face), admin-login (email+pw validasi ke Central), EnsureAdmin/EnsureEmployee.
- **Frontend Nuxt4 v0.3 JALAN**: splash → register → set-pin → setup (kode unik + scan wajah `/setup/face`) → dashboard. E2E flow `frontend/e2e-flow.mjs` 6/6 LULUS (CDP Chrome :9222). Fix penting: `setup/index.vue` + `setup/face.vue` (jangan setup.vue+setup/face.vue = NUXT_E4016); state setup (setupCode/setupVerified) di Pinia store biar survive navigasi face.
- **PROD mini-pc DEPLOYED**: absensi :8081 + portal-app :8080. Frontend v0.3 live di `{slug}-absensi.megakomsel.com`. Tenant prod aktif: tokoa, toko-uji, uranop (owner ti.sigitsuseno@gmail.com). Login owner/admin @ uranop-absensi → 200 (SUDAH fix: portal-app .env.production butuh ABSENSI_BASE_URL/WEBHOOK/SSO secret — kalau kosong provisioning silent no-op → tenant 404).
- **Sesi 2026-08-12 siang — ROLE MODEL & DASHBOARD ADMIN (64 test pass)**:
  - **Role (users.role)**: `superadmin` (owner, web+PWA monitor semua, TANPA relasi karyawan), `hrmanager` (web full + PWA approval cuti), `supervisor` (PWA aja: kelola group & jadwal bawahannya), `karyawan` (PWA aja: absen). Web = khusus superadmin+hrmanager.
  - **Pemisahan login web**: `/login` = login admin (admin-login ke Central); `/login-karyawan` = login karyawan (PIN/email); splash arahin karyawan ke `/login-karyawan`.
  - **Tabel baru (pola HRIS instance1, siap integrasi sync/pull — uuid+external_code+synced_at)**: `employee_groups`+`employee_group_members` (many-to-many, supervisor_id kepala group), `working_calendars`, `holidays`, `work_patterns` (work_day_hours SUDAH termasuk istirahat), `shifts` (upgrade: work_pattern_id, work_hour_start/end, check-in/out window, is_overnight; kolom start_time/end_time LAMA DI-DROP), `schedule_snapshots` (employee_id+date unique, is_holiday/leave/permit, status, source).
  - **Endpoint admin baru** (routes/tenant.php): `/groups` (+/groups/available-employees, show), `/working-calendars`, `/holidays`, `/work-patterns`, `/shifts`, `/schedule-snapshots` (bulk upsert), `/admin/stats` (format `{data:{...}}`).
  - **Frontend admin**: dashboard `/admin` (stat cards + grid menu), sidebar lengkap, halaman baru `groups.vue`, `calendars.vue`, `work-patterns.vue`, `shifts.vue`, `schedules.vue`.
  - **Pitfall**: `window.isSecureContext` — GPS web cuma jalan di HTTPS/localhost; dev `http://{slug}-absensi.test:8000` diblokir (pakai chrome://flags/#unsafely-treat-insecure-origin-as-secure).
  - ⚠️ Tenant lokal yang SUDAH ADA (sigit) butuh `php artisan tenants:migrate` setelah tambah migrasi tenant baru.
- **Sesi 2026-08-12 sore — PWA MOBILE REDESIGN (65 test pass + E2E 6/6 LULUS)**:
  - **Tema**: indigo → **teal** (#0d9488/#0f766e) di tailwind.config.ts + nuxt.config (theme_color #0f766e) + main.css.
  - **Halaman PWA baru** (layout `mobile` + komponen `MobileNav.vue` bottom nav): `/attendance` (riwayat + stat), `/leave-request` (form izin/cuti/sakit + riwayat + batalkan), `/calendar` (grid bulanan, **per role**: karyawan = jadwal sendiri; supervisor = pilih group yang dia pimpin → jadwal semua member), `/profile` (info + pengaturan + logout). Dashboard di-redesign ala referensi `docs/absensi_uranop.html` (header teal, status card clock in/out/lokasi, menu grid, riwayat, bottom nav). Clock dark mode + GPS card + tombol besar. Login karyawan = keypad PIN 6 digit + tab email. Splash fingerprint teal.
  - **Backend baru**: `LeaveRequestController` (`GET /leave-requests/me`, `POST /leave-requests`, `POST /leave-requests/{id}/cancel`), `ScheduleSnapshotController@mySchedule` (`GET /schedule-snapshots/me?from&to&group_id` — group_id hanya untuk supervisor group itu), `EmployeeGroupController@mine` (`GET /groups/mine` — group yang dipimpin/diikuti).
  - **Pitfall dev**: Vite 6+ blok host `.test` → `vite.server.allowedHosts: true` di nuxt.config. E2E CDP set value input Wajib native setter (el.value = ... TIDAK update v-model Vue). 404 API padahal route:list OK = artisan serve serve versi lama / tenancy domain tidak ada.

## Dev Setup (lokal Windows / Laragon)
```bash
# Terminal 1 — backend
cd H:\laragon\www\absensi-app
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — frontend (WAJIB --host 0.0.0.0, default ::1 bikin domain .test gagal)
cd H:\laragon\www\absensi-app\frontend
npm run dev -- --host 0.0.0.0
```
- Frontend fetch LANGSUNG `http://{slug}-absensi.test:8000/api/v1` (apiBase dinamis dari hostname; prod same-origin). CORS allow-all + Bearer.
- Hosts: `127.0.0.1 {slug}-absensi.test` (tambah manual per tenant baru).
- Tenant tes dibuat via portal-app local: `http://portal-app.test` → login admin (`admin@megakomsel.com` / `password` setelah migrate:fresh --seed) → register tenant → trial Absensi → webhook provisioning otomatis. Tenant aktif saat ini: **sigit** (owner tein.bejo1@gmail.com, DB tenant_absensi_sigit).
- ⚠️ E2E flow pakai domain `toko-uji-absensi.test` = ALIAS ke tenant **sigit** (ditambah manual: `DB::table('domains')->insert(['domain'=>'toko-uji-absensi.test','tenant_id'=>'sigit',...])`) + invite code `TEST5678` (karyawan Paijo Super, id=1) — dibuat via tinker, bukan lewat portal-app.
- CACHE_STORE=array (dev) / redis (prod). Sebelum `php artisan test` drop tenant yang slug-nya dipakai test.
- Reset DB lokal: `php artisan migrate:fresh --seed` (portal-app) + `php artisan migrate:fresh` (absensi).

## Deploy (git model — SUDAH AKTIF)
- Mini-PC `10.10.10.122` (SSH `imat@`), repo di `~/portal-app` & `~/absensi-app` = CLONE GIT dengan remote SSH (key id_ed25519 dari Windows `C:\Users\Sigit\.ssh` sudah di-copy ke mini-pc, terdaftar akun sigitsuseno).
- Alur: commit+push lokal → `ssh imat@10.10.10.122 "cd ~/absensi-app && git pull && docker compose --env-file docker/.env up -d --build"`.
- ⚠️ absensi-app WAJIB `--env-file docker/.env` (compose interpolasi MYSQL_*; tanpa itu password kosong → app 502). portal-app tanpa env-file (tapi pastikan `.env` root ada).
- `docker/.env` + `docker/.env.production` gitignored — kalau clone ulang, backup dulu 2 file itu.
- `.htaccess` public/: `/api/*` & `/up` → index.php, sisanya → index.html (SPA fallback), DirectoryIndex index.html.
- Detail: skill `laravel-docker-cloudflared-deploy` + `laravel-multi-tenant-saas`.

## Pending / Next
- **Desktop login superadmin/HR**: diskusi desain (belum kode). Rekomendasi: email+password akun Central (sama kayak admin-login web) + secure storage Tauri, opsional QR login dari web. Putuskan: app baru vs upgrade instance1-desktop; offline vs online-only.
- Fitur lanjutan v0.3: face rec beneran (upload selfie), kunjungan, tugas, pengumuman, lembur.

## Verify
```bash
php artisan test                      # backend
cd frontend && node e2e-flow.mjs      # E2E flow (butuh Chrome --remote-debugging-port=9222 + tenant dengan kode unik TEST5678)
npm run generate                      # build frontend → .output/public
```
