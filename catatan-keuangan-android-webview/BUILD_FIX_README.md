# Catatan Keuangan Android 2.6.3 — SDK bootstrap & build fix

Perubahan pada v2.6.3:

1. `setup-android.sh` kini memperbaiki `local.properties` yang menunjuk ke lokasi usang.
2. Jika SDK belum ada, script menawarkan untuk mengunduh Android Command Line Tools resmi Google, memverifikasi SHA-256, lalu memasang Android SDK Platform 35, Build Tools 35.0.0, dan Platform Tools.
3. Instalasi menampilkan persetujuan lisensi Android secara interaktif; lisensi tidak disetujui diam-diam.
4. `gradlew` mengecek ulang SDK dan memanggil setup jika lokasi atau komponen wajib belum tersedia.
5. `doctor-android.sh` menampilkan lokasi SDK yang terdeteksi dan komponen yang hilang.
6. `build-debug.command` menahan jendela Terminal saat build gagal agar pesan error tidak langsung hilang.
7. Versi aplikasi menjadi `2.6.3` / `versionCode 13`.

## Cara build

Buka folder `android-project` di paket ini. Jalankan `setup-android.command` dahulu (atau `./setup-android.sh` dari Terminal), lalu buka folder yang sama di Android Studio dan lakukan Gradle Sync. Setelah setup berhasil, jalankan `build-debug.command` atau `./gradlew clean assembleDebug`.

APK: `app/build/outputs/apk/debug/app-debug.apk`.

## Catatan validasi

Source Android/Gradle tidak dapat membundel SDK komputer pengguna. Setup dapat mengunduh SDK resmi jika komputer mempunyai internet, JDK 17+, ruang disk, dan pengguna menyetujui lisensinya. APK penuh belum diklaim berhasil dibuild dalam environment paket ini karena Android SDK platform tidak tersedia dan Gradle Wrapper perlu diunduh dari internet.
