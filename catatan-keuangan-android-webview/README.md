# Catatan Keuangan Android 2.6.3 — Offline First + Native Google Login

Project ini adalah build-source Android WebView untuk Catatan Keuangan. Gunakan `OFFLINE_FIRST_README.md` sebagai dokumentasi arsitektur offline dan sinkronisasi terbaru.

# Catatan Keuangan Android

Aplikasi Android WebView untuk `https://catatan-keuangan.cloud/`.

## Versi
- Android app: **2.6.3** (`versionCode 13`)
- Minimum Android: **Android 7.0 / API 24**
- Target SDK: **35**
- Java: **17**

## Fitur Android
- Splash Screen native saat aplikasi dibuka.
- WebView untuk Catatan Keuangan.
- BiometricPrompt native (fingerprint / face unlock yang didukung perangkat / device credential).
- Fallback ke PIN aplikasi.
- Penguncian ulang biometrik setelah aplikasi berada di background sekitar 30 detik.
- Kamera dan galeri untuk upload gambar/nota.
- Dukungan session/cookie.
- Offline-first penuh untuk data operasional pengguna, outbox sinkronisasi otomatis, dan retry berkala saat internet kembali.
- Download laporan melalui DownloadManager.
- Link eksternal dibuka di browser/aplikasi yang sesuai.
- Login Google native melalui Android Credential Manager, token diverifikasi oleh backend Yii2.

## PENTING: setup SDK sebelum membuka/build project
1. Ekstrak ZIP sampai selesai.
2. Masuk ke folder `android-project` yang berisi `app/`, `gradlew`, `settings.gradle`, dan `setup-android.sh`.
3. Jalankan `setup-android.command` (double-click) atau `./setup-android.sh` dari Terminal.
4. Jika SDK belum terpasang, setup akan menawarkan instalasi Android SDK resmi dan meminta persetujuan lisensi secara interaktif. Komputer perlu internet dan JDK 17+.
5. Sesudah `local.properties` berhasil dibuat, buka **folder `android-project` ini** di Android Studio (bukan folder induk paket) lalu pilih **Sync Project with Gradle Files**.
6. Untuk build paling mudah, jalankan `build-debug.command`.

Panduan rinci: [SDK_BOOTSTRAP.md](SDK_BOOTSTRAP.md).

## Build APK debug
```bash
./gradlew assembleDebug
```

APK debug akan berada di:
`app/build/outputs/apk/debug/app-debug.apk`

## Build AAB release
Konfigurasikan signing/keystore terlebih dahulu, kemudian build melalui Android Studio atau Gradle.

## Splash Screen
Splash native berada pada:
- `app/src/main/java/com/charlie/finance/SplashActivity.java`
- `app/src/main/res/layout/activity_splash.xml`
- `app/src/main/res/drawable/bg_splash.xml`

Durasi splash saat ini sekitar **900 ms** sebelum masuk ke `MainActivity`.

## SDK location fix (macOS)

Versi 2.6.3 memiliki bootstrap SDK yang tidak hanya mencari path, tetapi juga menawarkan instalasi SDK resmi jika belum ada. Script membuat/memperbaiki `local.properties`, memasang API 35, Build Tools 35.0.0, dan Platform Tools setelah pengguna menyetujui lisensi.

Jalankan dari folder `android-project`:
```bash
./setup-android.sh
./doctor-android.sh
./gradlew clean assembleDebug
```

Atau double-click `build-debug.command`.

Jika tidak ada SDK, setup mengunduh Command Line Tools dari server resmi Google (sekitar 156 MB) dan memverifikasi checksum sebelum memasang. Selanjutnya SDK Manager mengunduh platform/build-tools, sehingga diperlukan koneksi internet dan persetujuan lisensi.

Dokumentasi lengkap: [SDK_BOOTSTRAP.md](SDK_BOOTSTRAP.md).


## Login Google Native

Baca [GOOGLE_LOGIN_NATIVE.md](GOOGLE_LOGIN_NATIVE.md) untuk menyiapkan Google Web Client ID di `.env`, memasang Web Patch v2.6.1, dan membangun APK dengan Credential Manager. Jangan memasukkan Google Client Secret ke APK.
