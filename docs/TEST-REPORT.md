# Test Report

## Pemeriksaan yang dijalankan pada source hasil refactor

- PHP syntax lint: **87 file PHP**, seluruhnya lolos.
- JavaScript syntax check (`node --check`):
  - `web/assets/app.js` — lolos
  - `web/assets/offline-store.js` — lolos
  - `web/sw.js` — lolos
- Audit JSON sumber: seluruh JSON yang ditemukan dapat diparse.
- Audit referensi data sumber: tidak ditemukan duplicate ID bucket utama atau referensi wallet/category rusak pada data aktif yang diperiksa.
- Backup sumber yang dibuat diverifikasi ulang dengan SHA-256 per file.
- Pemeriksaan static compatibility: endpoint `ajax/*.php`, `index.php`, `privacy-policy.php`, `delete-account.php`, dan `android-download.php` tetap tersedia pada web root baru.

## Batas pengujian environment saat build

Environment build ini tidak memiliki extension `pdo_mysql`, `mbstring`, dan `zip`, serta tidak memiliki server MySQL lokal. Karena itu **integration test nyata terhadap MySQL/Yii runtime tidak dapat dijalankan di sandbox ini**. Source sudah disertai `bin/preflight.php`; jalankan pada server tujuan sebelum migration/cutover.

Minimal sebelum production:

```bash
php bin/preflight.php
composer install --no-dev --optimize-autoloader
php yii migrate --interactive=0
```

Lalu restore backup di staging dan cocokkan dashboard, wallet balance, transaksi, report/export, Premium/order, Super Admin, receipt, dan offline sync sebelum mengalihkan domain production.
