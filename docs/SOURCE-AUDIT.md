# Audit Sumber ZIP yang Diterima

Audit ini dibuat dari `catatan-keuangan-fix.zip` sebelum refactor.

## Data legacy terdeteksi

- User aktif pada `users.json`: **2**
- File finance per-user: **3**
- Finance yang memiliki user aktif: **2**
- Finance orphan: **1** (`user_8.json`)
- Transaksi total pada semua file finance: **57**
- Chat: **15**
- Wallet: **9**
- Kategori: **39**
- Monthly budget: **4**
- Tagihan: **17**
- Goal: **1**
- Audit log: **60**
- Recurring transaction: **0**

Seluruh JSON yang ditemukan berhasil diparse. Pemeriksaan statis juga tidak menemukan duplicate ID pada bucket utama maupun referensi wallet/category yang rusak pada data sumber saat ini.

`user_8.json` tidak mempunyai pasangan akun aktif di `users.json`. Migrator baru menyimpannya sebagai arsip di tabel `app_document` (`namespace=legacy_archive`, `document_key=orphan_finance`) sehingga data tidak dibuang tetapi juga tidak dipaksakan menjadi akun baru.

## Backup sumber yang disiapkan

Backup ZIP penuh dibuat dengan format `catatan-keuangan-legacy-full-backup`, manifest, ukuran file, dan checksum SHA-256 per item. Backup ini dapat langsung dipakai di menu **Super Admin → Migrasi & Backup MySQL → Restore ZIP ke MySQL**.
