# Catatan Keuangan

Versi dokumentasi: **v12 — Email Pemulihan, Token Reset Password/PIN, Wallet Aware & Premium**  
Tanggal dokumentasi: **11 September 2026**  
Teknologi utama: **PHP Native + JSON + JavaScript + PWA/IndexedDB**  
Database: **File JSON, tanpa MySQL/MariaDB**

---

## 1. Tentang Aplikasi

**Catatan Keuangan** adalah aplikasi pencatatan keuangan pribadi berbasis web yang dirancang untuk berjalan ringan pada shared hosting maupun server PHP biasa tanpa database SQL.

Aplikasi menggunakan:

- PHP Native untuk backend.
- JSON sebagai penyimpanan akun dan data keuangan.
- JavaScript native untuk antarmuka.
- IndexedDB untuk cache/sinkronisasi offline.
- Service Worker untuk PWA dan offline shell.
- Tesseract.js untuk OCR foto nota.
- WebView Android sebagai pembungkus aplikasi mobile.
- Sistem akun **Free**, **Premium**, dan **Super Admin**.

Data setiap pengguna dipisahkan ke file JSON masing-masing sehingga transaksi akun A tidak tercampur dengan akun B.

---

## 2. Fitur Utama

### 2.1 Akun dan autentikasi

Aplikasi mendukung:

- Registrasi username, email, dan password.
- Login username + password.
- PIN 4–6 digit.
- Trusted device menggunakan cookie/token perangkat.
- Email pemulihan dengan verifikasi kode 6 digit.
- Reset password dan/atau PIN menggunakan token 6 digit yang dikirim ke email terverifikasi.
- Token keamanan berlaku 15 menit, hanya dapat dipakai sekali, dan dibatasi percobaannya.
- Pengguna lama tanpa email akan mendapat pemberitahuan untuk melengkapi email pemulihan.
- Logout menghapus token perangkat aktif.
- Password dan PIN disimpan menggunakan hash PHP (`password_hash`), bukan plain text.
- Multi-user.
- Role `user` dan `super_admin`.
- Paket `free` dan `premium`.

> Catatan keamanan: file transaksi keuangan disimpan sebagai JSON server-side. Password/PIN di-hash, tetapi isi transaksi JSON **bukan enkripsi at-rest**. Folder `data/` wajib tidak dapat diakses langsung dari web.

---


## 2.2 Email pemulihan & reset password/PIN (v12)

Mulai v12, email menjadi bagian dari keamanan akun:

- Registrasi baru wajib mengisi email.
- Email harus unik antar akun.
- Email harus diverifikasi menggunakan kode 6 digit.
- Pengguna lama yang belum memiliki email akan melihat notifikasi dan diarahkan ke menu **Email & Keamanan**.
- Menu **Email & Keamanan** dapat digunakan untuk menambah/mengubah email, mengirim ulang kode, dan verifikasi.
- Pada halaman login tersedia **Lupa password / PIN?**.
- Token pemulihan dikirim ke email terverifikasi dan berlaku 15 menit.
- Pengguna dapat mereset password saja, PIN saja, atau keduanya sekaligus.
- Setelah reset berhasil, token perangkat lama dihapus sehingga sesi perangkat lama harus login kembali.

Pengiriman email dikonfigurasi melalui `mail_config.php`. Sistem mendukung `mail()` PHP dan SMTP langsung.

> Untuk production, gunakan alamat email pengirim yang valid dan SMTP/TLS bila fungsi `mail()` hosting tidak tersedia. Jangan simpan kredensial SMTP di repository publik.

---

## 3. Tipe Akun

### Free

Akun Free tetap dapat memakai fungsi dasar aplikasi, antara lain:

- Dashboard saldo.
- Saldo awal.
- Pemasukan.
- Pengeluaran.
- Pencatatan transaksi melalui chat.
- Kalkulator sederhana di chat.
- Daftar transaksi.
- Pencarian transaksi.
- Filter transaksi.
- Urut transaksi.
- Edit transaksi.
- Hapus transaksi.
- Batas pengeluaran harian.
- Sembunyikan/tampilkan saldo.
- Upload foto/nota.
- OCR foto nota.
- Riwayat chat.
- Hapus satu chat / seluruh chat.
- PWA/basic app access.
- Notifikasi browser dasar.

### Premium

Premium mendapatkan seluruh fitur Free ditambah fitur lanjutan:

- Banyak dompet/rekening.
- Transfer antar dompet.
- Kategori custom.
- Budget kategori bulanan.
- Tagihan dan cicilan.
- Pembayaran tagihan.
- Transaksi berulang.
- Target menabung.
- Analitik keuangan.
- Prediksi kecukupan sampai gajian.
- Laporan PDF.
- Export CSV.
- Export Excel-compatible `.xls`.
- Backup JSON.
- Restore backup.
- Perpanjangan paket Premium.

### Super Admin

`super_admin` mempunyai akses penuh Premium tanpa perlu membeli paket, serta:

- Melihat seluruh akun.
- Melihat statistik user.
- Melihat status Free/Premium.
- Mengubah paket user secara manual.
- Melihat invoice Premium.
- Verifikasi pembayaran.
- Menolak pembayaran.
- Melihat bukti transfer.
- Mengelola rekening pembayaran.
- Mengelola pembelajaran balasan asisten.
- Mengakses panel administrasi.

Hak admin ditentukan oleh:

```text
role = super_admin
```

Pada instalasi baru, akun pertama yang berhasil dibuat oleh kode saat ini otomatis dibuat sebagai `super_admin`. Setelah aplikasi digunakan, jangan mengandalkan nomor ID; akses panel diperiksa dari role akun.

---

## 4. Paket Premium

Paket bawaan aplikasi:

| Paket    | Harga Invoice | Masa Aktif | Keterangan            |
| -------- | ------------: | ---------- | --------------------- |
| Bulanan  |      Rp50.000 | 1 bulan    | Rp50.000/bulan        |
| Tahunan  |     Rp360.000 | 12 bulan   | Setara Rp30.000/bulan |
| Permanen |     Rp600.000 | Selamanya  | Sekali beli           |

Harga dihitung kembali di backend. Nilai harga dari frontend tidak dipercaya sebagai sumber utama.

---

## 5. Alur Pembelian Premium

Pengguna membuka menu **Premium**, kemudian:

1. Memilih paket Bulanan, Tahunan, atau Permanen.
2. Memilih rekening bank tujuan.
3. Mengisi nama pengirim.
4. Mengisi nomor rekening pengirim.
5. Menekan **Buat Invoice**.
6. Sistem membuat invoice unik, contoh:

```text
INV-20260911-000001
```

7. Invoice menampilkan:
   - Nomor invoice.
   - Paket.
   - Total tagihan.
   - Bank tujuan.
   - Nomor rekening tujuan.
   - Nama pemilik rekening.
   - Nama pengirim.
   - Nomor rekening pengirim.
   - Status invoice.
8. Pengguna menekan **Bayar**.
9. Pengguna mengunggah bukti pembayaran.
10. File bukti harus berupa:
    - JPG/JPEG
    - PNG
    - WEBP
11. Ukuran maksimal bukti pembayaran: **8 MB**.
12. Status berubah menjadi **Menunggu Verifikasi**.
13. Chat pengguna otomatis menerima pesan bahwa pembayaran sedang diverifikasi.

Status internal invoice:

```text
waiting_payment
pending_verification
approved
rejected
```

Tampilan pengguna menerjemahkannya menjadi:

- Menunggu Pembayaran
- Menunggu Verifikasi
- Lunas / Disetujui
- Ditolak

---

## 6. Verifikasi Premium oleh Admin

Super Admin membuka:

```text
Menu > Admin > Pembayaran Premium
```

Admin dapat melihat:

- Username.
- Paket yang dibeli.
- Nomor invoice.
- Total pembayaran.
- Bank tujuan.
- Data rekening pengirim.
- Bukti bayar.
- Status.
- Tanggal pembuatan.
- Data verifikasi.

### Jika pembayaran disetujui

Sistem:

- Mengubah paket user ke `premium`.
- Menentukan masa aktif sesuai paket.
- Menyimpan admin yang melakukan verifikasi.
- Mengubah invoice menjadi `approved`.
- Mengirim pesan sukses ke chat user.
- Mengirim ringkasan invoice **LUNAS** ke chat user.

Jika user sebelumnya masih memiliki Premium berjangka yang belum habis, perpanjangan paket berjangka dihitung dari masa aktif yang masih berlaku.

Paket Permanen tidak memiliki tanggal kedaluwarsa.

### Jika pembayaran ditolak

Status invoice menjadi `rejected`.

Chat pengguna menerima pesan:

> Pembelian ditolak karena bukti bayar tidak valid. Jika ingin mengajukan pertanyaan seputar pembelian tersebut silahkan hubungi admin melalui Chat WA Only +6282259866048 (Senin - Sabtu 09.00 WITA - 17.00 WITA).

---

## 7. Rekening Pembayaran

Bank bawaan:

- SeaBank
- BCA
- BRI

Nomor rekening dan nama pemilik sengaja dapat dikelola dari panel admin.

Super Admin dapat:

- Menambah bank.
- Mengubah nama bank.
- Mengubah nomor rekening.
- Mengubah nama pemilik rekening.
- Mengaktifkan/nonaktifkan bank.
- Mengatur urutan tampilan.
- Menghapus rekening yang tidak digunakan.

Bank tanpa nomor rekening/nama pemilik yang lengkap tidak dapat dipilih user untuk membuat invoice.

---

## 8. Pencatatan Transaksi

Transaksi utama:

- `income` — pemasukan.
- `expense` — pengeluaran.
- `transfer` — transfer antar dompet.

Contoh input chat:

```text
makan 20rb
bensin 50k
gaji 3.800.000
bayar cicilan 500rb
beli kopi 25 ribu hari ini
gaji 3,8jt
```

Parser nominal mendukung pola seperti:

```text
50000
50k
50rb
500 ribu
1.100.000
1,100,000
1,1jt
1.1jt
1,5 juta
Rp 500.000
```

Tanggal transaksi dapat berasal dari parser chat atau dipilih saat edit.

---

## 9. Edit Transaksi — v8

Pada v8, **edit transaksi adalah fitur basic**, sehingga akun Free maupun Premium dapat menggunakannya.

Edit transaksi mendukung:

- Jenis transaksi.
- Nominal.
- Tanggal transaksi.
- Kategori.
- Keterangan.
- Dompet.
- Dompet asal/tujuan untuk transfer.

Endpoint:

```text
ajax/edit_transaction.php
```

Validasi backend mencakup:

- ID transaksi harus valid.
- Nominal harus > 0.
- Format tanggal harus `YYYY-MM-DD`.
- Dompet harus benar-benar tersedia.
- Transfer tidak boleh menggunakan dompet asal dan tujuan yang sama.
- Keterangan dibatasi panjangnya.
- Transaksi milik user lain tidak dapat diedit karena data user dipisahkan berdasarkan sesi aktif.

Tombol simpan pada UI menampilkan status proses agar tidak mudah diklik berulang kali.

---

## 10. Pencarian dan Filter Transaksi — v8

Kolom pencarian sekarang **selalu tampil** dan tidak lagi disembunyikan di dalam panel Filter.

Pencarian dapat mencocokkan:

- Keterangan.
- Kategori.
- Nominal.

Filter tambahan:

- Jenis transaksi.
- Dompet/rekening.
- Kategori.
- Tanggal awal.
- Tanggal akhir.
- Urutan.

Urutan tersedia:

- Tanggal terbaru.
- Tanggal terlama.
- Nominal terbesar.
- Nominal terkecil.

Ringkasan hasil filter menampilkan:

- Jumlah transaksi.
- Total keluar.
- Total masuk.

---

## 11. Download Laporan — v8

Tombol PDF/CSV/Excel tidak lagi selalu memenuhi area transaksi.

Sekarang tersedia satu tombol:

```text
Unduh
```

Setelah diklik baru muncul pilihan:

- PDF
- CSV
- Excel

Export merupakan fitur Premium.

Endpoint terkait:

```text
ajax/report.php
ajax/export.php
```

PDF menggunakan generator native PHP di:

```text
report_pdf_helper.php
```

CSV menggunakan separator `;` dan UTF-8 BOM agar lebih nyaman dibuka di Excel.

Format Excel menggunakan HTML table dengan output `.xls` yang kompatibel untuk dibuka di Microsoft Excel/LibreOffice.

---

## 12. Saldo dan Batas Harian

Dashboard menampilkan:

- Saldo tersedia.
- Pemasukan.
- Pengeluaran.
- Saldo awal.

Saldo dapat disembunyikan dari layar menggunakan tombol mata.

Batas pengeluaran harian dapat diatur per hari:

- Senin
- Selasa
- Rabu
- Kamis
- Jumat
- Sabtu
- Minggu

Pengaturan mencakup:

- Aktif/nonaktif.
- Batas setiap hari.
- Persentase warning.
- Set massal hari kerja/semua hari.
- Status aman/mendekati/tercapai/terlewati.

---

## 13. Foto Nota dan OCR

Pengguna dapat:

- Memilih gambar dari galeri.
- Mengambil foto menggunakan kamera.
- Memakai mode otomatis.
- Memakai mode scan nota.
- Memakai foto sebagai lampiran saja.

Format gambar:

- JPEG/JPG
- PNG
- WEBP

OCR menggunakan:

```text
Tesseract.js v5
```

Library dimuat dari CDN jsDelivr saat diperlukan.

Service Worker mencoba menyimpan resource OCR yang pernah berhasil dipakai agar peluang OCR tetap berjalan saat koneksi buruk/offline lebih besar.

OCR mencari informasi seperti:

- Total nominal.
- Tanggal.
- Merchant.
- Teks nota.

Hasil OCR tetap perlu diperiksa user karena kualitas OCR sangat bergantung pada pencahayaan, sudut foto, font, dan kualitas nota.

---

## 14. Chat Asisten Keuangan

Asisten berjalan native/rule-based dan tidak membutuhkan OpenAI API.

Kemampuan utama:

- Mencatat transaksi dari kalimat.
- Mengidentifikasi pemasukan/pengeluaran.
- Membaca nominal Indonesia.
- Mengidentifikasi tanggal.
- Mengategorikan transaksi.
- Kalkulator.
- Menjawab ringkasan pengeluaran/pemasukan.
- Menjawab status batas harian.
- Menjawab informasi tagihan.
- Menampilkan pesan sistem Premium.
- Menyimpan pembelajaran balasan tambahan oleh Super Admin.

Contoh kalkulasi:

```text
50000 + 25000
1,5jt - 500rb
20% dari 500000
```

---

## 15. Pembelajaran Asisten

Super Admin dapat menambahkan aturan respons baru melalui menu **Pembelajaran**.

Data disimpan di:

```text
data/assistant_learning.json
```

Fitur ini digunakan untuk menambah balasan rule-based tanpa model AI berbayar.

---

## 16. Dompet dan Rekening — Premium

Premium dapat membuat beberapa dompet, contoh:

- Tunai
- BCA
- BRI
- SeaBank
- E-Wallet
- Tabungan

Setiap dompet memiliki:

- Nama.
- Jenis.
- Saldo awal.
- Status arsip.
- Saldo berjalan.

Transfer antar dompet dicatat sebagai transaksi `transfer`, sehingga saldo dompet asal berkurang dan saldo dompet tujuan bertambah.

---

## 17. Kategori dan Budget Bulanan — Premium

Premium dapat:

- Menambah kategori custom.
- Menentukan jenis kategori.
- Memberi emoji/icon.
- Menambah keyword parser.
- Mengarsip kategori.
- Mengatur batas bulanan per kategori.
- Melihat status pemakaian budget.

Kategori default antara lain:

- Makan
- Bensin
- Cicilan
- Belanja
- Transportasi
- Tagihan
- Kesehatan
- Hiburan
- dan kategori bawaan lain di aplikasi.

---

## 18. Tagihan dan Cicilan — Premium

Fitur tagihan menyediakan:

- Nama tagihan.
- Nominal.
- Tanggal jatuh tempo.
- Kategori.
- Dompet.
- Status pembayaran.

Tagihan dapat ditandai dibayar dan menghasilkan pencatatan transaksi sesuai implementasi fitur.

---

## 19. Transaksi Berulang — Premium

Transaksi berulang mendukung:

- Pemasukan.
- Pengeluaran.
- Harian.
- Mingguan.
- Bulanan.
- Interval custom.
- Tanggal proses berikutnya.
- Aktif/nonaktif.

Pemrosesan dilakukan saat aplikasi menjalankan fitur/sinkronisasi terkait, bukan menggunakan cron server permanen.

---

## 20. Target Menabung — Premium

Target menabung menyediakan:

- Nama target.
- Target nominal.
- Nominal terkumpul.
- Deadline.
- Progress persentase.
- Sisa target.
- Rekomendasi kebutuhan tabungan harian jika deadline diisi.

---

## 21. Analitik dan Prediksi — Premium

Analitik mengambil data langsung dari transaksi user.

Fitur mencakup:

- KPI pemasukan.
- KPI pengeluaran.
- Pengeluaran berdasarkan kategori.
- Status budget.
- Perhitungan menuju gajian.
- Proyeksi transaksi berulang.
- Prediksi kecukupan saldo.

Prediksi bersifat alat bantu berdasarkan data yang telah dicatat, bukan jaminan kondisi keuangan sebenarnya.

---

## 22. Backup dan Restore — Premium

Backup:

```text
ajax/backup.php
```

Backup menghasilkan JSON data keuangan akun aktif.

Backup tidak dimaksudkan untuk menyimpan password atau PIN.

Restore hanya boleh dilakukan pada sesi user yang valid dan memiliki akses Premium.

Sebelum restore, simpan backup terbaru jika data penting.

---

## 23. Offline Mode dan Sinkronisasi

Aplikasi mempunyai:

- Service Worker.
- IndexedDB.
- Offline shell.
- Antrean request offline.
- Auto-sync saat koneksi kembali.
- `client_op_id` untuk mengurangi risiko operasi ganda.
- Snapshot dashboard/transaksi.
- Status sinkronisasi pada antarmuka.

Nama database IndexedDB:

```text
charlie_finance_offline_v1
```

Service Worker saat dokumentasi ini:

```text
finance-shell-v10
```

### Cara kerja singkat

Saat online:

1. Data dibaca dari server.
2. Snapshot disimpan lokal.
3. Shell aplikasi tersimpan setelah sesi unlocked berhasil dibuka.

Saat offline:

1. Halaman menggunakan shell terakhir.
2. Data yang tersedia dapat dibaca dari snapshot lokal.
3. Operasi yang mendukung queue disimpan di IndexedDB.

Saat online kembali:

1. Queue diproses.
2. Request dikirim ke server.
3. `client_op_id` digunakan untuk idempotensi.
4. Data server dimuat ulang.

> Offline tidak berarti semua operasi backend dapat berjalan tanpa server. Fitur yang membutuhkan verifikasi admin, pembayaran, download server, atau resource yang belum pernah dicache tetap membutuhkan internet.

---

## 24. Realtime / Auto Refresh

Karena shared hosting gratis biasanya tidak menyediakan server WebSocket persisten, aplikasi menggunakan polling ringan.

Interval:

- Sekitar 2 detik saat tab aktif.
- Sekitar 8 detik saat tab tidak aktif.

Endpoint:

```text
ajax/realtime.php
```

Endpoint hanya mengirim data lengkap jika revision/signature berubah, sehingga lebih hemat dibanding reload penuh terus-menerus.

`BroadcastChannel` juga digunakan bila tersedia untuk memberi tahu tab lain pada browser yang sama.

---

## 25. PWA dan Android

### PWA

File utama:

```text
manifest.json
sw.js
```

Aplikasi dapat dipasang dari browser yang mendukung PWA.

### Android APK

Folder:

```text
apks/
```

Build web saat ini menyertakan:

```text
Catatan Keuangan-v1.0.0.apk
```

URL APK pada `index.php` saat ini mengarah ke domain:

```text
https://charlie-finance.rf.gd/
```

Jika domain berubah, perbarui URL hardcoded yang dijelaskan pada bagian konfigurasi domain.

---

## 26. Struktur Folder

```text
/
├── .htaccess
├── index.php
├── auth.php
├── config.php
├── db.php
├── finance_features.php
├── native_assistant.php
├── learning_helper.php
├── receipt_helper.php
├── report_pdf_helper.php
├── subscription_helper.php
├── transaction_filter_helper.php
├── offline_sync_helper.php
├── privacy-policy.php
├── delete-account.php
├── account_deletion_helper.php
├── manifest.json
├── sw.js
│
├── ajax/
│   ├── admin.php
│   ├── backup.php
│   ├── dashboard.php
│   ├── delete_message.php
│   ├── delete_transaction.php
│   ├── edit_transaction.php
│   ├── export.php
│   ├── features.php
│   ├── finance.php
│   ├── learning.php
│   ├── payment_proof.php
│   ├── photo.php
│   ├── realtime.php
│   ├── report.php
│   ├── settings.php
│   ├── subscription.php
│   └── transactions.php
│
├── assets/
│   ├── app.js
│   ├── offline-store.js
│   ├── style.css
│   └── icon.webp
│
├── data/
│   ├── .htaccess
│   ├── users.json
│   ├── subscriptions.json
│   ├── assistant_learning.json
│   ├── user_finance/
│   │   └── user_<ID>.json
│   └── payment_proofs/
│
└── apks/
    └── Catatan Keuangan-v1.0.0.apk
```

---

## 27. Penyimpanan JSON

### `data/users.json`

Menyimpan informasi akun, antara lain:

- ID.
- Username.
- Password hash.
- PIN hash.
- Device token hash.
- Role.
- Plan.
- Masa aktif Premium.
- Metadata Premium.
- Tanggal pembuatan.

### `data/user_finance/user_<ID>.json`

Menyimpan data keuangan per user:

- Settings.
- Saldo awal.
- Batas harian.
- Transaksi.
- Chat.
- Wallet.
- Kategori.
- Budget.
- Tagihan.
- Transaksi berulang.
- Target.
- Notifikasi.
- Metadata revision.
- Metadata offline operation.

### `data/subscriptions.json`

Menyimpan:

- Paket Premium.
- Bank pembayaran.
- Invoice/order.
- Status pembayaran.
- Bukti pembayaran.
- Data verifikasi.
- Revenue yang dihitung dari order approved.

### `data/payment_proofs/`

Menyimpan file bukti pembayaran.

File tidak diakses langsung oleh user. Tampilan bukti menggunakan endpoint:

```text
ajax/payment_proof.php
```

yang memeriksa hak user/admin.

---

## 28. Endpoint Utama

| Endpoint                      | Fungsi                      | Akses             |
| ----------------------------- | --------------------------- | ----------------- |
| `ajax/dashboard.php`          | Dashboard, chat, transaksi  | Login + PIN       |
| `ajax/finance.php`            | Chat & pencatatan transaksi | Login + PIN       |
| `ajax/transactions.php`       | List/filter transaksi       | Login + PIN       |
| `ajax/edit_transaction.php`   | Ambil/edit transaksi        | Free/Premium      |
| `ajax/delete_transaction.php` | Hapus transaksi             | Free/Premium      |
| `ajax/delete_message.php`     | Hapus chat                  | Free/Premium      |
| `ajax/settings.php`           | Pengaturan dasar            | Free/Premium      |
| `ajax/photo.php`              | Menampilkan foto milik akun | Login + PIN       |
| `ajax/features.php`           | Fitur keuangan lanjutan     | Mayoritas Premium |
| `ajax/report.php`             | PDF                         | Premium           |
| `ajax/export.php`             | CSV/Excel                   | Premium           |
| `ajax/backup.php`             | Backup/restore              | Premium           |
| `ajax/subscription.php`       | Pembelian Premium           | Login + PIN       |
| `ajax/payment_proof.php`      | Bukti bayar                 | Pemilik/Admin     |
| `ajax/admin.php`              | Admin & verifikasi          | Super Admin       |
| `ajax/learning.php`           | Pembelajaran assistant      | Super Admin       |
| `ajax/realtime.php`           | Polling perubahan           | Login + PIN       |

---

## 29. Konfigurasi Domain

Domain aplikasi saat ini:

```text
https://charlie-finance.rf.gd
```

Jika aplikasi dipindahkan ke domain lain, cari dan ubah referensi berikut:

### `index.php`

Nilai:

```text
syncBase
```

serta URL download APK.

### `assets/app.js`

Terdapat teks status sinkronisasi yang menyebut domain saat ini.

### Android WebView

Project Android mempunyai `APP_URL` dan `APP_HOST` sendiri. Ubah keduanya jika domain berubah.

Setelah mengubah domain, naikkan versi cache Service Worker agar browser tidak mempertahankan asset lama.

---

## 30. Requirement Server

Minimum yang direkomendasikan:

```text
PHP 7.4+
```

Rekomendasi:

```text
PHP 8.1 / 8.2
Apache + mod_rewrite
HTTPS
```

PHP membutuhkan kemampuan:

- Session.
- JSON.
- File read/write.
- `password_hash`.
- `random_bytes`.
- `DateTime`.
- File upload.
- `fileinfo` direkomendasikan untuk validasi MIME bukti bayar.
- `getimagesize` sebagai fallback validasi gambar.

Tidak membutuhkan:

- MySQL.
- MariaDB.
- PostgreSQL.
- Composer.
- Node.js.
- npm.
- Cron untuk fungsi dasar.

---

## 31. Permission Folder

Rekomendasi umum shared hosting:

```text
Folder: 755
File:   644
```

Folder yang harus dapat ditulis oleh PHP:

```text
data/
data/user_finance/
data/payment_proofs/
```

Jika hosting membutuhkan group write, gunakan:

```text
775
```

Hindari `777` kecuali benar-benar diperlukan untuk diagnosis sementara dan segera kembalikan setelah selesai.

---

## 32. Keamanan

Proteksi yang tersedia:

- Password/PIN hash.
- Session.
- Regenerasi session ID saat login/PIN.
- Device token disimpan sebagai hash.
- File locking (`flock`) saat mutasi JSON.
- Folder `data/` diblokir lewat `.htaccess`.
- Endpoint admin memeriksa role `super_admin`.
- Endpoint Premium memeriksa status paket di backend.
- Harga Premium dihitung backend.
- Bukti pembayaran diverifikasi MIME.
- Nama file bukti bayar dibuat acak.
- Bukti bayar hanya dapat dibuka oleh pemilik invoice atau admin.
- Cache download menggunakan endpoint yang membutuhkan sesi.
- Restore/backup dibatasi Premium.

### Sangat penting untuk Nginx

`.htaccess` hanya berlaku pada Apache/LiteSpeed yang kompatibel.

Jika memakai Nginx, tambahkan rule untuk memblokir:

```text
/data/
```

Contoh konsep:

```nginx
location ^~ /data/ {
    deny all;
    return 403;
}
```

Jangan biarkan `users.json`, `subscriptions.json`, atau `user_finance/*.json` bisa diunduh publik.

---

## 33. Backup yang Harus Disimpan

Sebelum update aplikasi, minimal backup:

```text
data/users.json
data/subscriptions.json
data/assistant_learning.json
data/user_finance/
data/payment_proofs/
```

Jika ada bukti bayar penting, backup folder `payment_proofs` bersamaan dengan `subscriptions.json`.

---

## 34. Upgrade Aplikasi

Untuk website yang sudah digunakan:

1. Backup folder `data/`.
2. Gunakan **patch**, bukan full package.
3. Upload file patch sesuai struktur.
4. Overwrite file program.
5. Jangan overwrite `data/users.json`.
6. Jangan overwrite `data/user_finance/`.
7. Jangan overwrite `data/subscriptions.json`.
8. Jangan hapus `payment_proofs/`.
9. Pastikan Service Worker versi baru ter-load.
10. Tutup PWA/WebView lalu buka kembali.

Jika frontend masih terlihat versi lama:

- Refresh paksa.
- Hapus cache situs bila perlu.
- Tutup aplikasi Android dari recent apps.
- Buka ulang.
- Pastikan `sw.js` yang baru benar-benar ter-upload.

---

## 35. Troubleshooting Singkat

### Tidak bisa edit transaksi

Pastikan file berikut versi terbaru:

```text
ajax/edit_transaction.php
assets/app.js
index.php
auth.php
```

Edit transaksi tidak memerlukan Premium pada v8.

Periksa Network/response jika masih gagal dan pastikan folder data user dapat ditulis PHP.

### Search tidak terlihat

Pastikan `index.php`, `style.css`, dan `app.js` versi v8 sudah ter-upload.

Search pada v8 berada di luar panel Filter.

### PDF/CSV/Excel tidak bisa

Export adalah fitur Premium.

Pastikan:

```text
auth.php
ajax/report.php
ajax/export.php
```

versi terbaru.

### Admin tidak muncul

Periksa `data/users.json` dan pastikan akun mempunyai:

```json
"role": "super_admin"
```

### Invoice tidak bisa dibuat

Pastikan bank tujuan di panel Admin sudah mempunyai:

- Nomor rekening.
- Nama pemilik.
- Status aktif.

### Bukti bayar gagal di-upload

Periksa:

- File JPG/PNG/WEBP.
- Ukuran <= 8 MB.
- Folder `data/payment_proofs/` writable.
- `upload_max_filesize` PHP cukup.
- `post_max_size` PHP cukup.
- Extension `fileinfo` tersedia atau `getimagesize` dapat membaca file.

### Data tidak tersimpan

Periksa permission:

```text
data/
data/user_finance/
```

dan error log PHP.

### CSS/JS lama terus muncul

Periksa:

```text
sw.js
```

Pastikan cache saat ini adalah:

```text
finance-shell-v10
```

Jika perlu unregister Service Worker lama dari browser lalu reload.

---

## 36. Catatan Hosting InfinityFree

Aplikasi dapat berjalan pada shared hosting seperti InfinityFree selama:

- PHP aktif.
- File JSON dapat ditulis.
- Session berfungsi.
- HTTPS aktif.
- Nama endpoint PHP tidak diblokir provider.
- Permission sesuai.
- Folder `data/` terlindungi.

Jika provider menampilkan 403 pada nama file tertentu, hindari mengganti permission ke `777` secara permanen. Periksa aturan hosting, `.htaccess`, nama endpoint, dan security filter provider.

---

## 37. Privasi dan Penghapusan Akun

Halaman:

```text
privacy-policy.php
delete-account.php
```

tersedia pada aplikasi.

Penghapusan akun harus diperlakukan sebagai tindakan permanen. Pastikan implementasi produksi juga membersihkan data user terkait sesuai kebijakan aplikasi.

---

## 38. File Penting yang Jangan Hilang Saat Update

Paling penting:

```text
auth.php
db.php
finance_features.php
subscription_helper.php
native_assistant.php
offline_sync_helper.php
transaction_filter_helper.php
assets/app.js
assets/offline-store.js
assets/style.css
sw.js
index.php
ajax/
```

Dan seluruh data pengguna di:

```text
data/
```

---

## 39. Checklist Sebelum Dipublikasikan

- HTTPS aktif.
- `/data/` tidak dapat dibuka publik.
- Permission JSON benar.
- Akun Super Admin benar.
- Rekening Premium sudah dikonfigurasi.
- Invoice bisa dibuat.
- Bukti bayar bisa diunggah.
- Admin bisa melihat bukti bayar.
- Approve mengaktifkan Premium.
- Reject mengirim pesan chat.
- Invoice lunas muncul di chat.
- Edit transaksi bekerja untuk Free.
- Search transaksi selalu tampil.
- Filter bekerja.
- Menu Unduh membuka PDF/CSV/Excel.
- Free ditolak saat mencoba export Premium.
- Premium dapat export.
- PWA berhasil install.
- Offline shell berhasil setelah login online minimal sekali.
- Service Worker terbaru aktif.
- Backup data sudah dibuat.

---

## 40. Versi v8 — Ringkasan Perubahan

Versi v8 fokus pada stabilisasi transaksi dan antarmuka transaksi:

- Memperbaiki proses edit transaksi.
- Edit transaksi tersedia untuk Free.
- Validasi edit transaksi diperketat.
- Search dikeluarkan dari panel Filter.
- Search selalu terlihat.
- PDF/CSV/Excel disatukan di tombol **Unduh**.
- Pilihan format baru muncul setelah tombol Unduh ditekan.
- Endpoint export tetap dilindungi Premium.
- Proteksi Premium terpusat diperbaiki.
- Service Worker dinaikkan ke `finance-shell-v10`.
- Sistem Premium, admin, invoice, OCR, offline sync, dan JSON tetap dipertahankan.

---

## 41. Lisensi / Penggunaan

Dokumentasi ini dibuat untuk project **Catatan Keuangan**.

Jika project akan didistribusikan kepada pihak lain, tambahkan file lisensi sesuai kebutuhan sebelum publikasi.

---

## 42. Dukungan Pembelian Premium

Kontak yang tampil pada sistem untuk masalah pembelian:

```text
Chat WA Only: +6282259866048
Senin - Sabtu
09.00 WITA - 17.00 WITA
```

---

## 43. Rekomendasi Produksi

Untuk penggunaan pribadi/skala kecil, penyimpanan JSON masih memadai.

Jika jumlah pengguna, transaksi, atau concurrent write menjadi sangat besar, pertimbangkan migrasi storage ke database relasional. Struktur aplikasi sekarang menggunakan file locking untuk mengurangi konflik write, tetapi JSON bukan pengganti database untuk beban transaksi berskala tinggi.

Untuk versi sekarang, jangan mengganti storage ke MySQL bila ingin mempertahankan arsitektur aplikasi yang ada.

---

**Catatan Keuangan**  
PHP Native · JSON · PWA · Android WebView · Offline Sync

## Chat berdasarkan Dompet / Rekening

Chat dapat menentukan sumber saldo berdasarkan nama dompet/rekening yang disebut dalam kalimat.

Contoh:

- `Pengeluaran 1jt dari SeaBank` → mencatat pengeluaran Rp1.000.000 dari rekening SeaBank.
- `Beli pisgor 10rb cash` → mencatat pengeluaran Rp10.000 dari dompet bertipe Cash.
- `Beli kopi 20rb tunai` → kata `tunai` diperlakukan sama dengan `cash`.
- `Pengeluaran 100rb dari BCA` → memotong saldo rekening BCA.

Nama dompet yang dibuat pengguna juga dideteksi secara otomatis. Jika tidak ada nama dompet/rekening yang disebut, transaksi menggunakan dompet default seperti sebelumnya.

Setelah transaksi dicatat, balasan chat akan menyebutkan dompet/rekening yang digunakan serta saldo dompet tersebut setelah transaksi.
