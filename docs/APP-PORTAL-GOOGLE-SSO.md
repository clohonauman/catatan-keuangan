# Konfigurasi Google Login dan SSO Portal Aplikasi

## Google Sign-In

1. Buka Google Cloud Console dan pilih/buat project.
2. Konfigurasikan OAuth consent screen sesuai aplikasi.
3. Buka **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
4. Pilih jenis **Web application**.
5. Tambahkan Authorized JavaScript origins, minimal:
   - `https://catatan-keuangan.cloud`
   - Tambahkan `https://www.catatan-keuangan.cloud` hanya jika domain `www` memang dipakai untuk akses login.
6. Salin **Client ID** (akhiran `apps.googleusercontent.com`) ke environment server:

   ```dotenv
   GOOGLE_CLIENT_ID="YOUR_WEB_CLIENT_ID.apps.googleusercontent.com"
   ```

7. Jangan menaruh Google Client Secret atau Server Key lain di frontend. Implementasi ini menggunakan GIS ID token dan melakukan verifikasi server-to-server melalui endpoint tokeninfo Google, lalu memeriksa `aud`, `iss`, expiry, dan `email_verified`.
8. Jika PHP-FPM/OPcache tidak membaca environment baru, reload PHP worker atau lakukan langkah sesuai penyedia hosting.

Email harus dinyatakan terverifikasi oleh Google. Bila ada akun Catatan Keuangan dengan email yang sama, akun tersebut dihubungkan ke identitas Google yang terverifikasi. Jika `google_sub` sudah tertaut ke akun Google lain, login ditolak dan pengguna perlu meminta bantuan. Akun baru dibuat dengan data identitas terverifikasi dan diarahkan membuat PIN bila belum mempunyai PIN.

## Pengelolaan aplikasi

- Galeri aplikasi: `index.php?r=app-portal%2Findex`.
- Manajemen aplikasi: `index.php?r=app-portal%2Fmanage` (khusus Super Admin).
- Built-in `Catatan Keuangan` dan `Perencanaan Keuangan` tidak bisa dihapus; aplikasi eksternal bisa ditambah/edit/dinonaktifkan/dihapus.
- Tautan aplikasi dan endpoint SSO eksternal harus HTTPS. `APP_KEY` harus stabil dan minimal 32 karakter untuk mengenkripsi secret bersama di database.
- Catatan katalog aplikasi dan pilihan aplikasi terakhir disimpan di `app_document`.

## Receiver SSO di aplikasi lain

Portal mengirim field POST `sso_token` berisi JWT dengan algoritma HS256. Payload memiliki `iss` (domain portal), `aud` (origin aplikasi tujuan), `sub` (ID user portal), `email`, `name`, `role`, `premium`, `iat`, `exp` (90 detik), dan `jti` acak.

Aplikasi tujuan harus:

1. Mengambil token POST dan memvalidasi HS256 menggunakan secret bersama yang sama.
2. Memeriksa `iss` dan `aud` dengan perbandingan tepat, memeriksa `exp`/`iat`, serta menolak token kedaluwarsa.
3. Menyimpan/menolak `jti` secara atomik agar token tidak dapat dipakai dua kali.
4. Melakukan pemetaan identitas user dan membuat sesi login setelah seluruh pemeriksaan lolos.
5. Menggunakan HTTPS. Jangan menaruh secret di JavaScript atau menyimpan token lengkap dalam log.

Klaim `premium` adalah status yang ditandatangani portal. Jika aplikasi tujuan membutuhkan otorisasi Premium yang dapat dicabut/berubah secara real-time, implementasikan sinkronisasi/status server-to-server yang sesuai; jangan mengandalkan klaim lama melewati masa hidup token.
