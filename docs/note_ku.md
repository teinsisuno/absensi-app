flow mobile :

1. saat buka aplikasi akan muncul loading state memunculkan logo di center dengan loading circle mengelilingi logo selama 3 detik kemudian loading state scale-in dan hilang.
2. jika pertama kali membuka maka akan muncul halaman register, dengan memasukkan email, nama, password atau login dengan google, setelah selesai di arahkan ke halaman pengaturan awal 1
3. di pengaturan awal 1 ini fungsinya untuk memasukkan kode unik (untuk merelasikan tabel user dengan tabel karyawan) dan mengambil data untuk face_recognition.
   3.a. saat dimasukkan kode unik itu otomatis di bawah field muncul nama sesuai dengan tabel karyawan.
   3.b. di bawah-nya ada tombol scan wajah yang akan mengarahkan ke halaman pengambilan foto/video wajah untuk face_recognition/face_detection, jika sudah selesai kembali ke halaman pengaturan awal 1 dan di bagian paling bawah tombol simpan sudah aktif.
4. setelah klik tombol simpan adan di arahkan ke halaman dashboard.
5. fasilitas karyawan :
    - biodata dan dokumen
    - check_in dan check_out
    - riwayat absensi
    - pengajuan lembur
    - jadwal kerja
    - notifikasi jadwal
    - pengajuan cuti
    - kunjungan (foto selfi yang memiliki koordinat lokasi dan keterangan)
    - pengumuman
    - tugas

6. fasilitas supervisor/mandor :
    - biodata dan dokumen
    - check_in dan check_out
    - riwayat absensi
    - jadwal kerja
    - management group
    - pengajuan lembur
    - notifikasi jadwal
    - pengajuan cuti
    - kunjungan (foto selfi yang memiliki koordinat lokasi dan keterangan)
    - pengumuman
    - tugas

7. fasilitas management/direktur/owner :
    - monitoring absensi,
    - task giving
    - memberikan pengumuman

web full management khusus superadmin dan HR,

- tidak semua user adalah karyawan,

1. untuk registrasi harus punya email. setelah register menggunakan password, muncul untuk setting pin gitu aja.
2. ok 1 - 1.5 detik saja, ya untuk oauth google tidak usah tidak apa-apa. ko unik nanti yang generate dan membagikan adalah HR,

- verifikasi wajah di simpan di server saja,
- ya saat check in.
- ok ikut
- coba kita buat di client side dan server side, nanti di pengaturan ada pilihan.
- fasilitas karyawan, setuju di tambahin, apalagi kalau bisa saat di pencet menu pengajuan ada dropdown
- approval semua ada di hr, supervisor pun cuma bisa pengajuan
- management nggak butuh absen :D

3. setuju

idcq yqqe knqs nylq

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=unrancreative2020@gmail.com
MAIL_PASSWORD=idcq yqqe knqs nylq
MAIL_ENCRYPTION=ssl

kita naming dulu deh roles-nya = 1. supervisor (owner pakai ini aja) = full akses di pwa hanya monitor tidak butuh relasi ke karyawan, 2.hrmanager (admin) full akses web, di pwa bisa approval cuti, 3.supervisor (manager atau mandor) tidak bisa akses web di p bisa buat group dan buat jadwal karyawan. 4. karyawan

$env:ANTHROPIC_BASE_URL="https://api.deepseek.com/anthropic"
$env:ANTHROPIC_AUTH_TOKEN=
$env:ANTHROPIC_MODEL="deepseek-v4-pro[1m]"
$env:ANTHROPIC_DEFAULT_OPUS_MODEL="deepseek-v4-pro[1m]"
$env:ANTHROPIC_DEFAULT_SONNET_MODEL="deepseek-v4-pro[1m]"
$env:ANTHROPIC_DEFAULT_HAIKU_MODEL="deepseek-v4-flash"
$env:CLAUDE_CODE_SUBAGENT_MODEL="deepseek-v4-flash"
$env:CLAUDE_CODE_EFFORT_LEVEL="max"
$env:CLAUDE_CODE_AUTO_COMPACT_WINDOW="786432"

berdasarkan alur "Proses Daftar, Login hingga Beranda" berikut ini. Tolong cek apakah aplikasi saat ini sudah mengimplementasikan alur dan logika serta penanganan _error_ (_edge cases_) dengan baik.

## 1. Cek database lokal untuk Tenant/Link

- **Jika kosong:** Tampilkan halaman isi tenant/link.
- **Jika sudah ada:** Lanjut ke tahap 2.

## 2. Cek database lokal untuk PIN, Email, dan Password

- **Kondisi Kosong (Alur Register):**
  Tampilkan halaman register. User memasukkan nama, email, dan password.
    - _Validasi:_ Cek API apakah email sudah terdaftar. Jika ada, tampilkan pesan error. Jika tidak, simpan data ke server dan lokal, lalu arahkan user untuk set PIN. Setelah set PIN selesai, lanjut ke tahap 3.

- **Kondisi Terisi (Alur Login):**
  Tampilkan halaman login dengan input PIN saja.
    - _Validasi:_ Jika PIN yang dimasukkan salah, tampilkan pesan error dan minta input ulang. Jika benar, lanjut ke tahap 3.

## 3. Cek database karyawan untuk ID Karyawan

- **Kondisi Kosong:**
  Tampilkan halaman input Kode Unik.
    - _Validasi:_ Jika kode unik salah/tidak ditemukan, tampilkan pesan error. Jika benar, tampilkan nama karyawan dan tombol "Simpan" di bagian bawah.
    - _Lanjutan:_ Setelah klik "Simpan", arahkan ke halaman pengambilan data wajah. Ambil data wajah dan simpan (jika gagal/dibatalkan, berikan pesan error atau opsi coba lagi). Jika berhasil, masuk ke Beranda.
- **Kondisi Terisi:**
  Cek apakah gambar wajah sudah ada di database.
    - **Jika sudah ada:** Langsung masuk ke Beranda.
    - **Jika belum ada:** Arahkan ke halaman pengambilan data wajah terlebih dahulu sebelum masuk ke Beranda.
