# Catatan Keuangan — Yii2 Basic + MySQL

Refactor dari aplikasi PHP Native + penyimpanan JSON menjadi **Yii2 Basic** dengan **MySQL/InnoDB**, tanpa mengubah mekanisme utama yang sudah dipakai pengguna: login + PIN, trusted device, transaksi, dompet, kategori, tagihan, recurring, goal, audit/undo, chat/assistant, receipt OCR, Premium, pembayaran, Super Admin, broadcast email, PWA/offline sync, WebView Android, export, laporan, serta backup/restore per-user.

## Arsitektur

- `controllers/` — entry point Yii untuk halaman utama, API compatibility, dan Super Admin migration.
- `repositories/` — akses MySQL terpusat; tidak ada JSON yang dipakai sebagai database runtime.
- `services/` — backup legacy ZIP + migrasi/restore penuh JSON → MySQL.
- `migrations/` — skema MySQL/InnoDB/utf8mb4 beserta index.
- `legacy/` — business rules aplikasi lama yang dipertahankan agar mekanisme tidak berubah; storage layer-nya sudah diarahkan ke repository MySQL.
- `web/` — satu-satunya document root publik. URL lama `ajax/*.php` tetap tersedia sebagai compatibility bridge ke Yii.
- `storage/private/` — foto nota dan bukti pembayaran, berada di luar web root.
- `commands/` — fallback CLI untuk backup/migrasi.
- `catatan-keuangan-android-webview-android/` — source Android WebView lama tetap disertakan.

## Requirement server

- PHP **8.1+**
- MySQL 8.0+ atau MariaDB yang kompatibel dengan InnoDB/utf8mb4
- PHP extension: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip`
- `gd` direkomendasikan untuk pemrosesan gambar
- Composer 2
- HTTPS untuk production

Cek server:

```bash
php bin/preflight.php
```

## Instalasi singkat

```bash
cp .env.example .env
composer install --no-dev --optimize-autoloader
php yii migrate --interactive=0
```

Atur `.env` minimal:

```dotenv
YII_ENV=prod
YII_DEBUG=0
APP_KEY="ISI_RANDOM_SECRET_MINIMAL_32_KARAKTER"
APP_URL="https://domain-anda.tld"
DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=catatan_keuangan"
DB_USERNAME="user_database"
DB_PASSWORD="password_database"
```

**Document root domain wajib diarahkan ke folder `web/`**, bukan ke root project. `config/`, `.env`, `storage/`, source PHP, dan Composer vendor tidak boleh menjadi file publik.

Beri hak tulis kepada user PHP/web server untuk:

```text
runtime/
storage/private/
```

## Migrasi data JSON lama

Lihat panduan lengkap di [`docs/MIGRATION.md`](docs/MIGRATION.md).

Alur web yang direkomendasikan:

1. Buat backup JSON lama terlebih dahulu.
2. Install aplikasi Yii + jalankan migration database.
3. Jika database masih kosong, registrasikan **akun bootstrap sementara pertama**. Sesuai mekanisme lama, user ID pertama otomatis Super Admin.
4. Login, buat/buka PIN, lalu buka **Super Admin → Migrasi & Backup MySQL**.
5. Upload backup ZIP penuh atau import langsung dari `LEGACY_DATA_PATH`.
6. Restore `replace_all` akan mengganti data bootstrap dengan data asli, memverifikasi jumlah data inti, lalu menutup sesi bootstrap.
7. Login kembali memakai akun asli dari backup.

Fallback CLI:

```bash
php yii legacy-migration/backup /absolute/path/aplikasi-lama/data /tmp/backup.zip
php yii legacy-migration/import-zip /tmp/backup.zip 1
# atau
php yii legacy-migration/import-path /absolute/path/aplikasi-lama/data 1
```

## Mekanisme yang sengaja dipertahankan

URL frontend lama seperti `ajax/finance.php`, `ajax/transactions.php`, `ajax/features.php`, `ajax/admin.php`, `ajax/subscription.php`, dan endpoint lain tetap dapat dipanggil. Bedanya, request masuk ke controller Yii dan data akhirnya dibaca/ditulis ke MySQL.

Backup JSON milik user Premium juga tetap ada sebagai **format portable ekspor/impor**, tetapi JSON tersebut tidak dipakai sebagai database runtime.

## Catatan keamanan deployment

- Jangan upload `.env` ke Git.
- Jangan jadikan root project sebagai public web root.
- Jangan menyimpan ulang folder `data/` legacy di bawah `web/`.
- Gunakan HTTPS pada production; cookie session/CSRF production dibuat `Secure`, `HttpOnly`, dan `SameSite=Lax`.
- Backup ZIP penuh berisi hash password, token, data keuangan, dan lampiran. Simpan seperti backup database: terenkripsi/akses terbatas dan hapus dari folder publik.

Detail perubahan keamanan ada di [`docs/SECURITY.md`](docs/SECURITY.md), dan pemetaan JSON → tabel ada di [`docs/DATA-MAP.md`](docs/DATA-MAP.md).
