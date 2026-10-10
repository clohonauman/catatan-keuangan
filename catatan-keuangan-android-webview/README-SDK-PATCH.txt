PATCH SETUP SDK ANDROID — Catatan Keuangan

Paket ini memperbaiki proses deteksi/pemasangan Android SDK. Ini bukan APK dan tidak menyertakan Android SDK secara langsung.

CARA PASANG KE PROJECT ANDROID YANG SUDAH ADA:
1. Ekstrak file ZIP ini LANGSUNG ke folder project Android Anda — folder yang sama dengan `app/`, `gradlew`, dan `settings.gradle`.
2. Timpa file yang diminta jika ditanya.
3. Buka Terminal pada folder itu dan jalankan: ./setup-android.sh
4. Jika SDK belum ada, pilih Y untuk mengunduh Command Line Tools resmi Google. Ikuti dialog lisensi yang muncul.
5. Setelah setup berhasil, jalankan: ./doctor-android.sh
6. Buka folder project yang sama di Android Studio, klik Sync Project with Gradle Files.
7. Build dengan ./gradlew clean assembleDebug atau double-click build-debug.command.

Untuk build pertama, komputer Mac harus memiliki internet dan JDK 17+. Command Line Tools resmi sekitar 156 MB, kemudian Platform 35, Build Tools dan Gradle juga akan diunduh.

Jangan menyalin patch ini ke folder induk yang hanya berisi folder android-project/. Ekstrak tepat di project root.

Lihat SDK_BOOTSTRAP.md untuk detail dan troubleshooting.
