# Membuat AAB Release

## Cara paling mudah — Android Studio
1. Buka folder project yang berisi `settings.gradle`.
2. **File > Sync Project with Gradle Files**.
3. **Build > Generate Signed App Bundle or APK**.
4. Pilih **Android App Bundle**.
5. Klik **Create new...** untuk membuat upload keystore jika belum ada.
6. Pilih variant **release**.
7. Build.

AAB biasanya berada di:
`app/build/outputs/bundle/release/app-release.aab`

## Opsional — build dari command line
Bila Anda menambahkan Gradle Wrapper dan membuat `keystore.properties` dari contoh:
`./gradlew bundleRelease`

## Update berikutnya
Sebelum membuat AAB baru, naikkan `versionCode` di `app/build.gradle`:
- 1 -> 2 -> 3 -> dst.
`versionName` dapat diubah misalnya `1.0.0` -> `1.0.1`.

Jangan pernah kehilangan upload keystore. Simpan backup aman di luar project.
