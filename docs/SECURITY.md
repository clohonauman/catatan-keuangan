# Security Notes

Perubahan ini menjaga business flow lama tetapi mengganti boundary keamanan dan storage.

## Yang diperbaiki

1. **JSON bukan database runtime** — user, finance, subscription, notification, dan learning persistence dialihkan ke MySQL.
2. **Prepared queries** — akses database melalui Yii DB/Query Builder, `PDO::ATTR_EMULATE_PREPARES=false`.
3. **CSRF** — Yii CSRF aktif untuk POST/mutation, termasuk request JSON/FormData. Offline queue mengganti token dengan token halaman aktif saat replay.
4. **Session cookie** — production memakai Secure + HttpOnly + SameSite=Lax.
5. **APP_KEY** — production gagal start jika key belum diisi/terlalu pendek.
6. **Private file storage** — receipt/proof keluar dari `web/`; file hanya dilayani melalui endpoint yang melakukan authorization.
7. **Document root** — hanya `web/` yang boleh public.
8. **Restore archive validation** — manifest, SHA-256, path traversal/Zip Slip protection, size limit upload 512 MB dari panel.
9. **Atomic relational restore** — perubahan tabel utama berada dalam transaksi MySQL; jika satu tahap gagal sebelum commit, data relasional rollback.
10. **FK + unique index** — integrity user/device/order dan identitas data dijaga database.
11. **Security headers** — Apache `.htaccess` menambahkan `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, dan `Cross-Origin-Opener-Policy`.
12. **Secrets** — SMTP/DB/app key menggunakan `.env`, bukan hard-code source.

## Yang tetap dipertahankan karena kompatibilitas

- Password minimal 6 karakter dan PIN 4–6 digit mengikuti mekanisme saat ini.
- Existing legacy business rules masih dipanggil melalui adapter/repository agar hasil dan UI tidak berubah.
- Tesseract OCR browser masih menggunakan CDN yang sama.
- Endpoint `ajax/*.php` tetap tersedia sebagai bridge, tetapi dieksekusi melalui Yii controller.

## Rekomendasi production

- HTTPS only + HSTS di reverse proxy/server setelah domain dipastikan full HTTPS.
- Database user hanya diberi privilege pada database aplikasi.
- Backup database/legacy dienkripsi at-rest dan jangan diletakkan di web root.
- Jalankan backup terjadwal + uji restore berkala.
- Aktifkan PHP OPcache.
- Untuk trafik tinggi, pindahkan cache/session ke Redis pada tahap berikut tanpa mengubah business API.
- Tambahkan rate-limit terpusat (Redis/Nginx/Cloudflare) jika endpoint login/email mulai menerima trafik publik besar.
