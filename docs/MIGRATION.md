# Migrasi JSON Legacy → MySQL

## Tujuan

Migrasi dirancang untuk mempertahankan ID dan mekanisme data lama sebisa mungkin. `user_id`, ID transaksi, wallet, kategori, tagihan, recurring, goal, audit, device token hash, password/PIN hash, role, paket Premium, invoice, kupon, metadata offline sync, pembelajaran assistant, notifikasi, dan lampiran dipindahkan tanpa meminta pengguna membuat ulang data.

## Isi backup penuh

Backup Super Admin menghasilkan ZIP dengan `manifest.json` + SHA-256 per file. Paket mengambil semua JSON root `data/*.json` serta:

- `data/user_finance/**`
- `data/receipts/**`
- `data/payment_proofs/**`

Saat restore, path ZIP divalidasi untuk mencegah Zip Slip dan checksum setiap item diverifikasi sebelum ekstraksi.

## Metode A — Super Admin (disarankan)

### 1. Siapkan MySQL baru

Buat database kosong, isi `.env`, lalu:

```bash
composer install --no-dev --optimize-autoloader
php yii migrate --interactive=0
```

### 2. Buat akses bootstrap

Jika MySQL benar-benar kosong, registrasikan akun pertama sementara. Mekanisme lama dipertahankan: ID pertama menjadi `super_admin`.

### 3. Buka panel migrasi

Masuk ke aplikasi → Super Admin → **Migrasi & Backup MySQL**.

Dari panel ini tersedia:

- **Download Backup JSON Penuh** — jika `LEGACY_DATA_PATH` menunjuk folder `data/` aplikasi lama.
- **Restore ZIP ke MySQL** — upload backup penuh.
- **Import dari LEGACY_DATA_PATH** — bila lama dan baru berada pada server yang sama.
- Riwayat migrasi dan ringkasan verifikasi.

Restore penuh menggunakan mode `replace_all`: data MySQL aplikasi yang ada diganti dengan backup. Database utama diproses dalam transaksi MySQL. Setelah sukses, sesi bootstrap ditutup karena akun dan trusted-device list sudah berasal dari backup.

## Metode B — CLI

Backup:

```bash
php yii legacy-migration/backup /home/user/app-lama/data /home/user/backups/catatan-legacy.zip
```

Restore ZIP:

```bash
php yii legacy-migration/import-zip /home/user/backups/catatan-legacy.zip 1
```

Import langsung folder:

```bash
php yii legacy-migration/import-path /home/user/app-lama/data 1
```

Argumen `1` berarti `replace_all`.

## Data orphan

Pada aplikasi file-based, ada kemungkinan `user_finance/user_X.json` tertinggal setelah akun sudah tidak ada di `users.json`. Restore **tidak membuang file tersebut dan tidak membuat akun palsu**. Payload disimpan di tabel `app_document` namespace `legacy_archive`, key `orphan_finance`.

Order langganan yang tidak lagi memiliki akun aktif juga diarsipkan sebagai `legacy_archive/orphan_subscription_orders` agar foreign key MySQL tetap valid tanpa kehilangan data sumber.

JSON root tambahan yang tidak dikenal versi migrator juga disimpan sebagai dokumen `legacy_archive/root_<nama-file>`.

## Verifikasi pasca-restore

Migrator membandingkan minimal:

- jumlah user aktif,
- transaksi,
- wallet,
- chat,
- order subscription,
- jumlah lampiran yang disalin,
- data orphan yang diarsipkan.

Hasil disimpan ke tabel `migration_history` dan ditampilkan setelah restore.

## Rollback/cutover aman

Sebelum cutover:

1. Download backup ZIP penuh.
2. Simpan salinan aplikasi lama + folder `data/` di lokasi non-public/read-only.
3. Lakukan restore ke database baru.
4. Login dan uji dashboard, transaksi, saldo tiap wallet, laporan, Premium, admin, dan offline sync.
5. Baru arahkan domain produksi ke folder `web/` aplikasi Yii.

Jangan menghapus backup legacy sampai verifikasi produksi selesai.

## Cutover dari aplikasi lama menggunakan backup terbaru

1. Pada aplikasi lama, login sebagai **Super Admin**.
2. Buka **Admin → Unduh Semua Data**.
3. Simpan file `catatan-keuangan-semua-data-YYYYMMDD-HHMMSS.zip` secara privat.
4. Disarankan hentikan input transaksi pada aplikasi lama setelah backup terakhir dibuat agar tidak ada perubahan baru yang tertinggal.
5. Pada aplikasi Yii Basic, buka **Super Admin → Migrasi & Backup MySQL**.
6. Pilih ZIP tadi pada **Import Backup Terbaru dari Aplikasi Lama** lalu jalankan restore.
7. Sistem memverifikasi manifest, ukuran, path aman, dan checksum SHA-256 sebelum memodifikasi MySQL.
8. Setelah restore berhasil, login kembali menggunakan akun dari backup lama.

ZIP backup memuat seluruh JSON aplikasi, `user_finance`, foto nota, dan bukti pembayaran. JSON lama adalah **format migrasi/arsip**, bukan database aktif pada versi Yii.
