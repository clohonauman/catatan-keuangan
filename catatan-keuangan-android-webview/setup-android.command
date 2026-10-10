#!/bin/bash
set -u
cd "$(dirname "$0")"

pause_on_exit() {
  code=$?
  if [ "$code" -ne 0 ]; then
    printf '\nSetup SDK gagal (exit %s). Baca pesan error di atas. Tekan Enter untuk menutup jendela...' "$code"
    read -r _ || true
  fi
}
trap pause_on_exit EXIT
./setup-android.sh
printf '\nSetup selesai. Buka folder android-project ini di Android Studio, lalu Gradle Sync.\n'
printf 'Tekan Enter untuk keluar...'
read -r _ || true
