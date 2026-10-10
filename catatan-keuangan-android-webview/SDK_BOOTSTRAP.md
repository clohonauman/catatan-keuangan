# SDK Android — setup otomatis di Mac

## Mengapa SDK location error muncul?

Folder source aplikasi tidak menyertakan Android SDK karena SDK adalah toolchain terpisah, ukurannya besar, dan perlu dipasang/menyetujui lisensi Google pada komputer yang akan build. Project ini sekarang memiliki bootstrapper yang bisa menemukan SDK yang sudah ada atau menawarkan instalasi resmi jika SDK belum tersedia.

## Cara paling mudah

1. Ekstrak ZIP sampai selesai.
2. Buka folder `android-project` (folder ini yang berisi `app/`, `gradlew`, `settings.gradle`, dan `setup-android.sh`).
3. Double-click `setup-android.command`, atau buka Terminal pada folder ini lalu jalankan:

   ```bash
   chmod +x setup-android.sh gradlew setup-android.command build-debug.command
   ./setup-android.sh
   ```

4. Jika SDK tidak ditemukan, script akan meminta izin untuk mengunduh Android Command Line Tools resmi Google (sekitar 156 MB), lalu memasang:
   - Android SDK Platform 35
   - Android Build Tools 35.0.0
   - Android Platform Tools
5. Ikuti prompt lisensi di Terminal. Jangan menyetujui lisensi sebelum Anda membacanya dan menyetujuinya.
6. Script akan menulis/memperbaiki `local.properties` dengan path SDK absolut yang benar.
7. Buka **folder `android-project` itu sendiri** di Android Studio (jangan buka folder induk yang hanya berisi `android-project/`), pilih **Sync Project with Gradle Files**, lalu build.

## Build APK lewat Terminal

Double-click `build-debug.command`, atau:

```bash
./build-debug.command
```

Build manual:

```bash
./setup-android.sh
./gradlew clean assembleDebug
```

Output:

```text
app/build/outputs/apk/debug/app-debug.apk
```

## Jika Anda sudah punya SDK pada path khusus

Jalankan dengan path yang sebenarnya:

```bash
ANDROID_SDK_ROOT="/path/ke/Android/sdk" ./setup-android.sh
```

Path harus menunjuk ke root SDK yang memiliki folder seperti `platforms/`, `build-tools/`, atau `platform-tools/`, bukan ke folder `platforms/android-35`.

## Diagnosis

```bash
./doctor-android.sh
```

Perintah ini menampilkan lokasi SDK yang dipakai, apakah API 35/Build Tools terpasang, status `local.properties`, dan Java yang tersedia.

## Jika build masih gagal

- `SDK location not found`: pastikan sudah menjalankan `setup-android.sh` dari folder `android-project` yang benar dan `local.properties` terbuat.
- `Failed to download Gradle`: komputer perlu akses internet untuk mengunduh Gradle Wrapper 8.11.1.
- `License ... not accepted`: jalankan `sdkmanager --licenses` di SDK root atau ulangi setup dan ikuti prompt lisensi.
- `Android SDK Platform 35 missing`: jalankan setup lagi dan izinkan pemasangan komponen.
- `JDK 17+ not found`: instal/buka Android Studio yang menyertakan JBR, atau instal JDK 17+.

Dokumentasi resmi:
- Android Studio / Command Line Tools: https://developer.android.com/studio
- SDK Manager: https://developer.android.com/studio/intro/update
- Android 15 / API 35: https://developer.android.com/about/versions/15/setup-sdk
