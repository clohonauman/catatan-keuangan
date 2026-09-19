# Charlie Finance — Checklist Google Play

## Build
- Application ID: `com.charlie.finance` — jangan diubah setelah rilis pertama di Play.
- Target SDK: Android 16 / API 36.
- Minimum SDK: API 24.
- Version code rilis pertama: `1`; naikkan untuk setiap update.
- Format upload: Android App Bundle (`.aab`).

## Sebelum membuat AAB
1. Android Studio > SDK Manager: pastikan Android SDK Platform 36 terpasang.
2. Sync Gradle.
3. Buat upload key melalui **Build > Generate Signed App Bundle or APK > Android App Bundle**.
4. Simpan `.jks` dan password di tempat aman. Jangan dimasukkan ke repository.
5. Aktifkan **Play App Signing** saat membuat release pertama.

## URL kebijakan
Setelah patch web Play Store diupload ke domain:
- Privacy Policy: `https://charlie-finance.rf.gd/privacy-policy.php`
- Account deletion: `https://charlie-finance.rf.gd/delete-account.php`

## App access / reviewer
Karena Charlie Finance memakai login, di Play Console > App content > App access pilih bahwa sebagian/seluruh fungsi dibatasi login, lalu berikan akun reviewer yang aktif dan petunjuk PIN jika diperlukan.

## Financial features declaration
Charlie Finance adalah pencatatan/budget keuangan pribadi, bukan bank, pinjaman, transfer uang nyata, investasi, atau wallet pembayaran. Lengkapi Financial features declaration secara akurat. Bila Play Console menganggap fitur pengelolaan uang sebagai fitur keuangan, pilih kategori yang paling sesuai (mis. `Other`) dan jelaskan bahwa aplikasi hanya melakukan pencatatan keuangan pribadi.

## Data safety — cek berdasarkan implementasi aktual
Data yang dapat diproses aplikasi:
- username / user ID;
- data transaksi, saldo, budget, tagihan, target dan catatan keuangan;
- foto nota/lampiran (opsional);
- chat/asisten dan data dukungan;
- token sesi/perangkat yang dibuat aplikasi.

Data dikirim melalui HTTPS ke domain Charlie Finance dan sebagian dapat disimpan offline di perangkat. Tidak ada SDK iklan/analytics pada project Android ini. Pastikan jawaban Data Safety sesuai konfigurasi server yang benar-benar dipakai.

## Store listing
Wajib disiapkan di Play Console:
- Nama: `Charlie Finance`
- Kategori: Finance
- App icon: `play-store-assets/app-icon-512.png`
- Feature graphic: `play-store-assets/feature-graphic-1024x500.png`
- Minimum 2 screenshot aplikasi aktual dari perangkat Android.
- Short description dan full description: lihat `STORE_LISTING_ID.md`.

## Catatan WebView
Aplikasi memuat layanan Charlie Finance milik sendiri dan menambahkan integrasi Android seperti kamera/galeri, download laporan, session, cache/offline, IndexedDB, dan sinkronisasi. Pastikan domain selalu dapat diakses reviewer Google Play tanpa anti-bot/403.
