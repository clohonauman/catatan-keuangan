# Integrasi QRIS Midtrans untuk Premium

Integrasi ini menambahkan **QRIS dinamis + aktivasi Premium otomatis** ke alur langganan yang sudah ada. Transfer manual dan upload bukti pembayaran tetap tersedia. Implementasi memakai Midtrans Core API langsung melalui HTTPS sehingga tidak menambah dependency Composer.

## Konfigurasi `.env`

Salin key berikut ke `.env` (sudah ditambahkan ke `.env` project):

```dotenv
MIDTRANS_ENABLED=true
MIDTRANS_SERVER_KEY="GANTI_DENGAN_SERVER_KEY_MIDTRANS"
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_QRIS_ACQUIRER=gopay
```

- `MIDTRANS_SERVER_KEY`: **Server Key**, bukan Client Key. Secret ini hanya dipakai PHP server; jangan taruh di JavaScript, source control, aplikasi Android, atau API response.
- `MIDTRANS_IS_PRODUCTION=false`: Sandbox. Gunakan Server Key Sandbox dan simulator Midtrans untuk mengetes pembayaran.
- `MIDTRANS_IS_PRODUCTION=true`: transaksi nyata di Production. Ganti dengan Server Key Production dan pastikan metode QRIS sudah diaktifkan pada akun merchant sebelum mengubah setting ini.
- `MIDTRANS_QRIS_ACQUIRER`: `gopay` atau `airpay_shopee`, sesuai metode yang diaktifkan pada akun merchant. Default `gopay`.
- Jika key kosong/placeholder, opsi QRIS tidak ditampilkan. Transfer bank manual tetap tersedia.

Setelah mengubah `.env`, pastikan PHP membaca environment terbaru. Pada hosting dengan OPcache/PHP-FPM, reload PHP worker atau bersihkan OPcache bila konfigurasi masih terlihat lama.

## Wajib konfigurasi webhook di Dashboard Midtrans

Aktivasi otomatis bergantung pada webhook server-ke-server. URL harus memakai HTTPS dan dapat diakses publik tanpa login/password/VPN.

Jika instalasi menggunakan struktur repo ini dan halaman aplikasi diakses melalui `/web/`, isi **Payment Notification URL** di Dashboard Midtrans dengan:

```text
https://DOMAIN-ANDA.TLD/web/index.php?r=payment%2Fmidtrans-notification
```

Jika document root hosting diarahkan langsung ke isi folder `web/`, gunakan:

```text
https://DOMAIN-ANDA.TLD/index.php?r=payment%2Fmidtrans-notification
```

Pilih URL yang benar-benar dapat diakses dari internet. Di Dashboard Midtrans: **Settings → Configuration → Payment Notification URL → Update**. Jangan gunakan `localhost`.

## Cara kerja

1. Pengguna membuka menu Premium, memilih paket dan (opsional) kupon, lalu memilih QRIS.
2. Yii2 menghitung harga/potongan dari data server, membuat invoice `pending`, lalu membuat transaksi `payment_type=qris` di Midtrans.
3. QR gambar dimuat lewat endpoint internal aplikasi yang membutuhkan sesi login dan memastikan invoice milik pengguna. Server Key dan URL API QR mentah tidak dikirim ke browser.
4. UI membaca status invoice secara berkala dari database aplikasi.
5. Midtrans mengirim notifikasi ke webhook. Server memvalidasi `signature_key` dengan SHA-512, ID pesanan, nominal, mata uang IDR, metode QRIS, dan status settlement.
6. Premium baru diaktifkan setelah notifikasi settlement valid. Baris invoice dikunci dalam transaksi database agar webhook duplikat tidak mengaktifkan masa Premium dua kali.
7. Invoice kadaluwarsa/gagal tidak mengaktifkan Premium. Pengguna dapat membuat invoice baru atau memakai transfer manual.

## Status pembayaran dan keamanan

- `pending`: menunggu pembayaran.
- `settlement` + kode status sukses dan (jika tersedia) `fraud_status=accept`: pembayaran diverifikasi; Premium diaktifkan.
- `expire`: invoice kedaluwarsa.
- `deny`/`cancel`/`failure`: pembayaran gagal/ditolak.
- Redirect browser atau screenshot sukses **bukan** bukti pembayaran. Hanya status server yang terverifikasi dapat mengaktifkan Premium.
- Webhook mengabaikan notifikasi dengan signature, nominal, currency, order ID, atau payment type yang tidak cocok.
- QR image endpoint memerlukan user yang sudah login/unlock dan hanya dapat memuat QR dari invoice miliknya.

## Database dan deployment

Tidak ada migration tambahan untuk patch ini: metadata transaksi Midtrans disimpan di `subscription_order.order_json`, sedangkan status dan invoice tetap memakai tabel langganan yang sudah ada. Jangan menghapus atau mengganti tabel langganan yang dipakai aplikasi.

Pastikan:
- PHP 8.1+;
- HTTPS publik di production;
- ekstensi PHP cURL direkomendasikan (fallback stream membutuhkan `allow_url_fopen`);
- koneksi HTTPS outbound dari hosting ke Midtrans diizinkan;
- `runtime/` dan penyimpanan private yang telah dipakai aplikasi tetap writable.

## Pengujian Sandbox

1. Isi Server Key Sandbox dan set `MIDTRANS_IS_PRODUCTION=false`.
2. Buka menu Premium, pilih paket harga positif, pilih QRIS, dan buat invoice. Jika QRIS aktif, gambar QR muncul.
3. Untuk sandbox, ikuti instruksi simulator QRIS pada dokumentasi/dashboard Midtrans; transaksi Sandbox tidak menarik uang nyata.
4. Periksa tabel `subscription_order`: awalnya `waiting_payment`, lalu berubah ke `approved` setelah webhook settlement valid.
5. Kirim ulang notifikasi yang sama melalui alat notifikasi Midtrans/Simulator: masa Premium untuk invoice tersebut harus tetap teraktivasi satu kali.
6. Uji signature salah, nominal berbeda, dan transaksi expire; semuanya tidak boleh mengaktifkan Premium.
7. Setelah seluruh tes berhasil dan merchant QRIS Production disetujui, pasang Server Key Production dan set `MIDTRANS_IS_PRODUCTION=true`.

## Batasan yang perlu diketahui

- Key saja tidak cukup untuk konfirmasi otomatis: merchant harus memiliki akses QRIS, dan **Payment Notification URL wajib dikonfigurasi** di Dashboard Midtrans.
- Kode diuji secara lokal (syntax, signature, URL allow-list/normalisasi, dan smoke test). Transaksi nyata/Sandbox belum dikirim dari paket ini karena Server Key merchant tidak disediakan; lakukan tes Sandbox saat memasang.
- Harga paket Premium tetap diambil dari database/backend aplikasi; browser tidak bisa mengganti nominal final.
