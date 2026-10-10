#!/bin/bash
set -u
cd "$(dirname "$0")"

pause_on_exit() {
  code=$?
  if [ "$code" -ne 0 ]; then
    printf '\nBuild gagal (exit %s). Baca pesan error di atas. Tekan Enter untuk menutup jendela...' "$code"
    read -r _ || true
  fi
}
trap pause_on_exit EXIT

./setup-android.sh
printf '\nMenjalankan Gradle clean assembleDebug...\n'
./gradlew clean assembleDebug
printf '\nAPK debug berhasil dibuat:\n%s\n' "$(pwd)/app/build/outputs/apk/debug/app-debug.apk"
printf 'Ukuran: '
du -h app/build/outputs/apk/debug/app-debug.apk | awk '{print $1}'
printf '\nTekan Enter untuk keluar...'
read -r _ || true
