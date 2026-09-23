# Build iOS — checklist cepat

1. Buka `CatatanKeuanganIOS.xcodeproj` di Xcode.
2. Target > Signing & Capabilities > pilih Team.
3. Pastikan Bundle ID unik.
4. Pilih iPhone fisik untuk tes Camera, Face ID/Touch ID, dan download.
5. Run.
6. Tes:
   - login + PIN;
   - Face ID/Touch ID enable/disable;
   - background >30 detik lalu kembali;
   - upload galeri;
   - foto kamera;
   - transaksi offline lalu online kembali;
   - PDF/CSV/XLS;
   - link WhatsApp/browser eksternal.
7. Release: Product > Archive > Distribute App.

## Jika website dipindah domain

Ubah `AppConfig.appURL` dan `AppConfig.appHost` sebelum Archive.
