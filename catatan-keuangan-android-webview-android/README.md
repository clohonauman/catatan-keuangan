# Catatan Keuangan Android

Aplikasi Android WebView untuk `https://charlie-finance.rf.gd/`.

## Versi
- Android app: **1.2.0** (`versionCode 3`)
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
- Offline fallback dan pemicu sinkronisasi saat jaringan kembali.
- Download laporan melalui DownloadManager.
- Link eksternal dibuka di browser/aplikasi yang sesuai.

## Buka di Android Studio
1. Buka folder project ini dengan Android Studio.
2. Tunggu Gradle Sync selesai.
3. Pastikan JDK yang digunakan adalah JDK 17.
4. Jalankan pada perangkat/emulator.

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
