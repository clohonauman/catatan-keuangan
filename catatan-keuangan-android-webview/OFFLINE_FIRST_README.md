# Catatan Keuangan Android 2.6.1 — Offline First + Native Google Login

Project Android ini menggunakan **WebView + Service Worker + IndexedDB** agar UI dan business logic web tetap menjadi satu sumber implementasi, sementara data operasional pengguna dapat dipakai tanpa jaringan.

## Catatan versi 2.6.1

Login Google di aplikasi Android menggunakan Credential Manager native. Fitur ini online-only; ID token dikirim ke backend Yii2 di domain Catatan Keuangan dan diverifikasi server-side. Penggunaan native login mensyaratkan Web Patch Google Login Native v2.6.1 dan `GOOGLE_CLIENT_ID` bertipe Web application di `.env` server.

## Arsitektur

- **IndexedDB** menyimpan snapshot akun lengkap, transaksi, wallet, kategori, tagihan, recurring, goals, piutang, chat, notifikasi, pending confirmation, dan state fitur.
- **Outbox queue** menyimpan setiap mutasi offline sebagai operasi yang harus dikirim ketika koneksi kembali.
- **ID lokal negatif** dipakai untuk entitas baru di perangkat. Setelah server menerima operasi, ID tersebut direkonsiliasi menjadi ID server.
- **Service Worker** mencache shell aplikasi yang sudah login/unlock agar cold-start Android tetap bisa membuka origin HTTPS saat offline.
- **Android ConnectivityManager** memicu event `online`/`offline`, sehingga sinkronisasi berjalan otomatis ketika jaringan kembali.
- **Assistant offline** menjalankan parser dan kalkulasi lokal untuk saldo, prediksi sampai gajian, pengeluaran, pencatatan pemasukan/pengeluaran/transfer, dan konfirmasi transaksi.

## Alur penggunaan

1. Pertama kali, login dan unlock akun ketika online.
2. Web mengambil `ajax/offline_snapshot.php` dan menyimpan snapshot lengkap ke IndexedDB.
3. Setelah data offline siap, Android menandai perangkat sebagai `offline-ready`.
4. Saat jaringan putus, UI tetap dibuka dari Service Worker dan request operasional diarahkan ke mesin offline.
5. Setiap perubahan lokal langsung diterapkan ke state perangkat lalu dimasukkan ke outbox.
6. Saat jaringan kembali, outbox dikirim satu per satu. Ketergantungan ID otomatis direwrite menggunakan peta ID lokal → ID server.
7. Setelah sinkronisasi, snapshot server terbaru kembali disimpan sehingga server tetap menjadi sumber kebenaran akhir.

## Data lokal dan keamanan

IndexedDB dipilih karena cocok untuk data terstruktur dan queue transaksi tanpa layanan berbayar. Data tetap berada di sandbox aplikasi/WebView pada perangkat; implementasi ini **belum mengenkripsi database IndexedDB secara terpisah** dengan kunci aplikasi, sehingga keamanan penyimpanan tetap mengikuti perlindungan data perangkat/Android.

Logout atau pembersihan data offline akan menghapus snapshot/queue milik akun dari perangkat melalui mekanisme yang sudah ada di web app.

## Fitur offline utama

- transaksi manual, income/expense/transfer;
- wallet dan saldo;
- kategori;
- budget bulanan dan daily budget;
- tagihan dan pembayaran tagihan;
- recurring dan goal;
- piutang, pelunasan, dan bunga dasar;
- transaction inbox / draft;
- chat finance dan konfirmasi transaksi;
- assistant lokal untuk analitik dan prediksi sederhana;
- notifikasi lokal/state notifikasi;
- upload foto nota, bukti piutang, dan lampiran chat tetap tersimpan sebagai Blob lokal;
- OCR nota dipanaskan (warm-up) saat online sehingga resource OCR dapat dipakai kembali ketika offline;
- pencarian dan pagination data yang sudah tersimpan;
- sinkronisasi otomatis dan retry saat jaringan pulih, termasuk retry berkala ketika Wi-Fi tersambung tetapi internet sedang lag;
- seluruh perubahan lokal tampil langsung di daftar/fungsi terkait dan diberi status **Belum sinkron** sampai server mengonfirmasi;
- chat offline tetap membaca snapshot finansial terakhir dan dapat menjawab analitik/pencatatan tanpa jaringan;
- OCR nota memakai resource yang dipanaskan dan dicache saat online agar dapat dimuat kembali dari cache ketika offline.

## Fungsi server-bound

Fungsi yang secara sifat harus berkomunikasi dengan server tidak dipalsukan sebagai hasil final. Contohnya pembayaran/subscription, administrasi/super-admin, pengiriman email, dan persetujuan server. Saat offline, permintaan yang aman untuk ditunda masuk ke outbox sehingga pengguna tidak kehilangan input; hasil final baru ditandai tersimpan setelah server menerima dan merekonsiliasikannya.

OCR nota dipersiapkan saat perangkat online agar resource Tesseract dapat dipakai kembali saat offline. Foto tetap merupakan data lokal utama, sehingga kegagalan OCR tidak pernah membatalkan penyimpanan foto atau transaksi/draft yang dibuat pengguna.

## Build

Buka folder project ini di Android Studio lalu jalankan:

```bash
./gradlew assembleDebug
```

Atau gunakan **Build > Build APK(s)** di Android Studio.

Project menggunakan:

- compileSdk 35
- targetSdk 35
- minSdk 24
- Java 17
- Android Gradle Plugin 8.9.1
- Gradle 8.11.1

`local.properties` yang terikat ke mesin pengembang sengaja tidak disertakan dalam paket distribusi. Android Studio akan membuatnya otomatis.

## Catatan build lingkungan saat pembuatan paket

Source Android sudah diperbarui dan divalidasi secara statis. Build APK tidak dijalankan di environment pembuatan paket karena Android SDK/Gradle distribution tidak tersedia secara lokal dan wrapper membutuhkan resolusi dependency/distribution melalui jaringan.
