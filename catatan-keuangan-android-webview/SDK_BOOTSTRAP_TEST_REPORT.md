# Android SDK Bootstrap Test Report — v2.6.3

## Automated validation

- `bash -n` on `setup-android.sh`, `doctor-android.sh`, `setup-android.command`, `build-debug.command`: PASS.
- `sh -n gradlew`: PASS.
- Existing SDK detection using `ANDROID_HOME`: PASS.
- Repair of a stale `/old/missing/sdk` entry in `local.properties`: PASS.
- SDK path containing spaces is correctly escaped in `local.properties` and decoded by doctor: PASS.
- Missing SDK branch offers official installation and exits with actionable instructions when the user declines: PASS.
- SDK exists but API 35/build-tools/platform-tools are missing: the SDK Manager branch was tested with a mock `sdkmanager`; all required package directories and `local.properties` were produced: PASS.
- Gradle wrapper detected a valid SDK and repaired `local.properties` before trying to start Gradle: PASS.
- No machine-specific `local.properties` is included in the archive: PASS.

## Full APK build limitation

A full `./gradlew clean assembleDebug` was **not** claimed as successful in the packaging environment. The Android SDK platform is not installed in this environment and Gradle 8.11.1 cannot be fetched here because `services.gradle.org` DNS lookup fails (`UnknownHostException`). The bootstrapper is intended to run on the user's Mac with internet access and JDK 17+; it downloads the official command-line tools, verifies SHA-256, asks the user to accept SDK licenses, installs API 35/build tools, then writes `local.properties`.

Official docs: https://developer.android.com/studio and https://developer.android.com/about/versions/15/setup-sdk
