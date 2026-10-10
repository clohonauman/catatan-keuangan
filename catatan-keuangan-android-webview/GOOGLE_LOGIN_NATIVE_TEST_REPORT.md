# Android Native Google Login — Build Regression Report v2.6.2

## Fixed compile error

Error reported on macOS:

```text
error: exception GoogleIdTokenParsingException is never thrown in body of corresponding try statement
```

The selected `googleid:1.2.1` API path used by this project does not declare `GoogleIdTokenParsingException` from the calls inside `handleNativeGoogleCredential()`. The specific checked-exception import and catch clause were removed. The existing general `catch (Exception error)` remains as the single error path; it logs only the exception class, resets `googleSignInInProgress`, and reports a safe user-facing error.

## Checks

- `GoogleIdTokenParsingException` import/reference removed from `MainActivity.java`: PASS.
- `GoogleIdTokenCredential.createFrom(custom.getData())` retained: PASS.
- Fallback `catch (Exception error)` retained and resets sign-in progress: PASS.
- Java regression harness matching the compiler's checked-exception rule: PASS (old pattern fails; corrected pattern compiles).
- Version updated to `2.6.2` / `versionCode 12`: PASS.
- Android source copy and bundled `android-project/` match for `MainActivity.java`: PASS.
- ZIP integrity and secret/local.properties scan: PASS.

## Build limitation

A full Android Gradle compile was attempted with `./gradlew :app:compileDebugJavaWithJavac --stacktrace`, but the wrapper could not download Gradle 8.11.1 because `services.gradle.org` could not be resolved (`UnknownHostException`). Android SDK Platform 35 is also not installed in this build environment. Therefore a full APK compile is **not claimed**; run Gradle Sync and `./gradlew clean assembleDebug` from Android Studio on the Mac after network access is available.
