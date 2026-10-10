CATATAN KEUANGAN ANDROID v2.6.3 — OFFLINE FIRST + GOOGLE LOGIN NATIVE

PENTING: sebelum membuka Gradle project atau build APK, jalankan setup SDK.

1. Pastikan komputer Mac tersambung ke internet dan memiliki JDK 17+ (Android Studio menyertakan JBR).
2. Buka Terminal pada folder android-project ini, atau double-click setup-android.command.
3. Jalankan ./setup-android.sh. Jika SDK belum ditemukan, pilih Y untuk mengunduh Android Command Line Tools resmi, lalu setujui lisensi SDK setelah membacanya.
4. Setup memasang Android SDK Platform 35, Build Tools 35.0.0, Platform Tools, dan membuat local.properties dengan path yang benar.
5. Buka FOLDER android-project INI di Android Studio (bukan folder induk yang berisi android-project/), lalu klik Sync Project with Gradle Files.
6. Build dengan double-click build-debug.command atau jalankan ./gradlew clean assembleDebug.

Output APK:
app/build/outputs/apk/debug/app-debug.apk

Jika gagal, jalankan ./doctor-android.sh dan baca SDK_BOOTSTRAP.md.
Android SDK tidak disertakan dalam ZIP; pemasangan awal perlu internet dan persetujuan lisensi Google.
