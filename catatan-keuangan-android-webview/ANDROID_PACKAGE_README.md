# Catatan Keuangan Android Offline First + Google Login Native 2.6.3

Paket ini adalah **source project Android lengkap**, bukan APK hasil build.

Project menggunakan WebView sebagai UI/logic yang sama dengan web Catatan Keuangan, dengan dukungan Service Worker + IndexedDB + outbox sehingga fungsi operasional pengguna dapat berjalan tanpa jaringan.

## Wajib sebelum testing

Untuk fitur Google Login native, deploy **Web Production Patch Google Login Native v2.6.1** ke server terlebih dahulu. Aplikasi Android memuat web app dari `https://catatan-keuangan.cloud/`; alur login Google harus punya backend verifier di server.

## Perilaku offline

Saat perangkat offline:

- transaksi langsung masuk daftar transaksi lokal;
- transaksi dan chat diberi status `Belum sinkron`;
- saldo, wallet, kategori, budget, tagihan, recurring, goal, piutang, draft/inbox, notifikasi dan analitik memakai state lokal;
- chat/assistant membaca data lokal yang terakhir tersimpan dan tetap dapat memproses pencatatan transaksi;
- foto nota/lampiran tersimpan di IndexedDB sebagai Blob;
- perubahan masuk outbox dan dikirim saat internet kembali;
- ID lokal otomatis diganti ID server setelah sinkronisasi;
- retry juga dilakukan berkala untuk kondisi Wi-Fi tersambung tetapi internet sedang tidak stabil.

## Build

Buka folder ini di Android Studio dengan JDK 17, tunggu Gradle Sync, lalu:

```bash
./gradlew assembleDebug
```

Environment pembuatan paket tidak memiliki Android SDK/Gradle distribution dan tidak dapat mengunduh Gradle 8.11.1 karena akses jaringan/DNS tidak tersedia, sehingga APK tidak diklaim sudah ter-build di sini.

## Android 2.6.3 — Google Login Native dan build fix

Project menyertakan deteksi SDK otomatis dan script setup untuk macOS. File `local.properties` tidak disertakan karena path SDK bersifat khusus perangkat; `setup-android.sh` akan membuatnya dari lokasi SDK yang ditemukan.


## Native Google Login v2.6.3

Login Google Android memakai Credential Manager dan mengirim ID token kembali ke form login WebView. Deploy Web Production Patch `catatan-keuangan-google-login-native-web-production-patch-v2.6.1.zip` terlebih dahulu. Client ID yang digunakan harus sama dengan `GOOGLE_CLIENT_ID` bertipe Web application di `.env` server. APK tidak menyimpan Client Secret.


## Google Login untuk aplikasi Android

Android memakai Credential Manager native. WebView memanggil `AndroidGoogleSignIn`, lalu ID token dikembalikan ke halaman login yang sama dan diverifikasi oleh backend Yii2. Login memerlukan internet dan layanan Google yang aktif; fungsi ini tidak berjalan saat offline.

Sebelum build/install APK:
1. Deploy Web Patch `catatan-keuangan-google-login-native-web-production-patch-v2.6.1.zip`.
2. Tambahkan `GOOGLE_CLIENT_ID` (OAuth Client ID tipe Web application) pada `.env` server. Jangan masukkan Client Secret ke APK.
3. Buka project ini di Android Studio dengan JDK 17 dan tunggu Gradle Sync. Dependency Credential Manager perlu diunduh pertama kali.
4. Build dan uji pada perangkat Android yang memiliki Google Play Services.

Build output: `app/build/outputs/apk/debug/app-debug.apk`. Source sudah disiapkan, tetapi APK tidak diklaim sudah ter-build di environment paket ini karena Android SDK/Gradle distribution tidak tersedia.
