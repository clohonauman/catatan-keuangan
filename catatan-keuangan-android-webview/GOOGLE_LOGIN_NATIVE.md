# Login Google Native — Catatan Keuangan Android 2.6.3

## Apa yang berubah

Login Google di Android sekarang menggunakan Android Credential Manager. APK tidak lagi membuka browser eksternal saat pengguna memilih tombol **Masuk dengan Google**. ID token yang diterima dari Credential Manager dikembalikan ke halaman login WebView yang sama, lalu dikirim melalui form `google_login` yang sudah ada. Dengan demikian, PHP session, CSRF token, dan one-time nonce berasal dari sesi WebView, sedangkan token tetap diverifikasi di server.

## Deploy web terlebih dahulu

Deploy Web Production Patch `catatan-keuangan-google-login-native-web-production-patch-v2.6.1.zip` ke project Yii2. Pastikan login Google web v2.6.0 dan service verifier sudah terpasang, serta `GOOGLE_CLIENT_ID` sudah diisi.

## Google Cloud dan .env

Gunakan OAuth Client ID bertipe **Web application** yang sama dengan `GOOGLE_CLIENT_ID` di `.env` server. Credential Manager memakai Web Client ID sebagai `serverClientId`, dan backend memeriksa audience token terhadap ID yang sama.

```dotenv
GOOGLE_CLIENT_ID="YOUR_WEB_CLIENT_ID.apps.googleusercontent.com"
```

- Jangan masukkan Google Client Secret ke APK atau Java source.
- Jangan menaruh Server Key atau ID token permanen di aplikasi.
- Backend tetap memverifikasi signature, audience, issuer, expiry, nonce, dan `email_verified`.
- Pengguna harus memiliki akun Catatan Keuangan yang sesuai dengan aturan pengaitan akun pada backend. Login Google tidak otomatis membuat akun baru.

## Build Android

1. Pastikan komputer terhubung ke internet saat pertama kali melakukan Gradle Sync, karena Credential Manager dan Google ID dependencies perlu diunduh.
2. Buka folder project ini di Android Studio.
3. Jalankan `./setup-android.sh` lebih dahulu. Jika Android SDK belum terpasang, script akan menawarkan pemasangan SDK resmi dan membuat `local.properties`. Lihat `SDK_BOOTSTRAP.md`.
4. Tunggu Gradle Sync selesai, lalu jalankan `./gradlew assembleDebug`.
5. APK debug berada di `app/build/outputs/apk/debug/app-debug.apk`.

Versi: `2.6.3` (`versionCode 13`), package `com.charlie.finance`.

## Catatan pengujian

Jalur JS/native bridge dan aturan nonce telah diuji dengan harness lokal, dan backend Google ID-token verification smoke test lulus. APK belum dibangun atau diuji pada perangkat nyata di environment ini karena Gradle distribution tidak dapat diunduh di environment tersebut; build harus dilakukan di Android Studio/komputer yang memiliki SDK dan koneksi internet.
