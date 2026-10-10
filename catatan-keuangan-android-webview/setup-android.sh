#!/bin/bash
# Catatan Keuangan Android SDK bootstrapper for macOS.
# Detects an installed SDK; if missing, offers to install Google's official
# command-line tools, Android SDK Platform 35, Build Tools 35.0.0 and platform-tools.
set -eu

APP_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$APP_DIR"

say() { printf '%s\n' "$*"; }
fail() { printf '\nERROR: %s\n' "$*" >&2; exit 1; }
ask_yes() {
  local prompt="$1" ans
  printf '%s [Y/n]: ' "$prompt"
  read -r ans || ans=""
  case "$ans" in n|N|no|NO) return 1 ;; *) return 0 ;; esac
}

# Android Gradle Plugin 8.9.x needs JDK 17 or newer.
java_major() {
  local java_bin="$1" ver major
  [ -x "$java_bin" ] || return 1
  ver=$("$java_bin" -version 2>&1 | awk -F '\"' '/version/ { print $2; exit }')
  [ -n "$ver" ] || return 1
  major=${ver%%.*}
  if [ "$major" = "1" ]; then major=$(printf '%s' "$ver" | cut -d. -f2); fi
  case "$major" in ''|*[!0-9]*) return 1 ;; esac
  [ "$major" -ge 17 ]
}
setup_java() {
  if [ -n "${JAVA_HOME:-}" ] && java_major "$JAVA_HOME/bin/java"; then
    export PATH="$JAVA_HOME/bin:$PATH"
    say "JDK: $JAVA_HOME"
    return 0
  fi

  if [ "$(uname -s)" = "Darwin" ] && [ -x /usr/libexec/java_home ]; then
    local j17
    j17=$(/usr/libexec/java_home -v 17 2>/dev/null || true)
    if [ -n "$j17" ] && java_major "$j17/bin/java"; then
      export JAVA_HOME="$j17"
      export PATH="$JAVA_HOME/bin:$PATH"
      say "JDK: $JAVA_HOME"
      return 0
    fi
  fi

  local candidate
  for candidate in \
    "/Applications/Android Studio.app/Contents/jbr/Contents/Home" \
    "$HOME/Applications/Android Studio.app/Contents/jbr/Contents/Home" \
    "/Applications/Android Studio Preview.app/Contents/jbr/Contents/Home"; do
    if java_major "$candidate/bin/java"; then
      export JAVA_HOME="$candidate"
      export PATH="$JAVA_HOME/bin:$PATH"
      say "JDK bundled Android Studio: $JAVA_HOME"
      return 0
    fi
  done

  if command -v java >/dev/null 2>&1 && java_major "$(command -v java)"; then
    say "JDK on PATH: $(command -v java)"
    return 0
  fi

  fail "JDK 17+ tidak ditemukan. Instal/buka Android Studio (yang menyertakan JBR) atau instal JDK 17+, lalu jalankan setup-android.sh lagi."
}

read_local_sdk() {
  local value
  [ -f "$APP_DIR/local.properties" ] || return 1
  value=$(sed -n 's/^sdk\.dir=//p' "$APP_DIR/local.properties" | tail -n 1)
  [ -n "$value" ] || return 1
  # Undo the escapes written by this script for spaces and colons.
  value=${value//\\ / }
  value=${value//\\:/\:}
  printf '%s' "$value"
}

looks_like_sdk() {
  local p="$1"
  [ -d "$p" ] || return 1
  [ -d "$p/platforms" ] || [ -d "$p/platform-tools" ] || [ -d "$p/build-tools" ] || [ -d "$p/cmdline-tools" ] || [ -d "$p/tools" ]
}

find_sdk_manager() {
  local p
  for p in "$SDK_ROOT"/cmdline-tools/*/bin/sdkmanager "$SDK_ROOT"/tools/bin/sdkmanager; do
    if [ -x "$p" ]; then printf '%s' "$p"; return 0; fi
  done
  return 1
}

has_build_tools() {
  local p
  [ -d "$SDK_ROOT/build-tools/35.0.0" ] && return 0
  for p in "$SDK_ROOT"/build-tools/35.*; do
    [ -d "$p" ] && return 0
  done
  return 1
}

write_local_properties() {
  # Spaces are escaped for compatibility with Java properties parsing.
  local escaped="$SDK_ROOT"
  escaped=${escaped//\\/\\\\}
  escaped=${escaped// /\\ }
  escaped=${escaped//:/\\:}
  printf 'sdk.dir=%s\n' "$escaped" > "$APP_DIR/local.properties"
}

download_command_line_tools() {
  local arch url expected tmp actual extracted
  [ "$(uname -s)" = "Darwin" ] || fail "Bootstrap SDK otomatis ini ditujukan untuk macOS. Pada OS lain, gunakan Android Studio > SDK Manager."
  command -v curl >/dev/null 2>&1 || fail "curl tidak ditemukan. Instal Xcode Command Line Tools atau gunakan Android Studio > SDK Manager."
  command -v unzip >/dev/null 2>&1 || fail "unzip tidak ditemukan."
  command -v shasum >/dev/null 2>&1 || fail "shasum tidak ditemukan untuk verifikasi unduhan."

  arch=$(uname -m)
  case "$arch" in
    arm64)
      url="https://dl.google.com/android/repository/commandlinetools-mac_arm64-15859902_latest.zip"
      expected="835b62a26162b229b441d1f6d4680383815a270809eb33522c0d480fa5002c4e"
      ;;
    x86_64)
      url="https://dl.google.com/android/repository/commandlinetools-mac_x86_64-15859902_latest.zip"
      expected="c5a6378ab5cf7e0d5701921405115befff13e9ff7417fb588389338f8bd050f3"
      ;;
    *) fail "Arsitektur macOS tidak dikenali: $arch. Unduh Android Command Line Tools yang cocok dari https://developer.android.com/studio#command-line-tools-only." ;;
  esac

  tmp=$(mktemp -d)
  trap 'rm -rf "${tmp:-}"' EXIT
  say "Mengunduh Android Command Line Tools resmi Google (~156 MB)..."
  say "Sumber: $url"
  curl -fL --retry 3 --connect-timeout 20 "$url" -o "$tmp/cmdline-tools.zip" || fail "Unduhan Android Command Line Tools gagal. Periksa koneksi internet dan coba lagi."
  actual=$(shasum -a 256 "$tmp/cmdline-tools.zip" | awk '{print $1}')
  [ "$actual" = "$expected" ] || fail "Checksum unduhan tidak cocok. File tidak dipasang untuk menjaga keamanan."

  mkdir -p "$SDK_ROOT/cmdline-tools"
  unzip -q "$tmp/cmdline-tools.zip" -d "$tmp/unpacked"
  [ -x "$tmp/unpacked/cmdline-tools/bin/sdkmanager" ] || fail "Isi unduhan tidak memiliki sdkmanager pada lokasi yang diharapkan."
  if [ -d "$SDK_ROOT/cmdline-tools/latest" ]; then
    mv "$SDK_ROOT/cmdline-tools/latest" "$SDK_ROOT/cmdline-tools/latest.backup.$(date +%s)"
  fi
  mv "$tmp/unpacked/cmdline-tools" "$SDK_ROOT/cmdline-tools/latest"
  trap - EXIT
  rm -rf "$tmp"
}

install_missing_packages() {
  local sdkmanager
  sdkmanager=$(find_sdk_manager || true)
  if [ -z "$sdkmanager" ]; then
    download_command_line_tools
    sdkmanager=$(find_sdk_manager || true)
  fi
  [ -n "$sdkmanager" ] && [ -x "$sdkmanager" ] || fail "sdkmanager tidak ditemukan setelah pemasangan Command Line Tools."

  say ""; say "SDK yang akan digunakan: $SDK_ROOT"
  say "Komponen yang diperlukan: Android SDK Platform 35, Build Tools 35.0.0, dan Platform Tools."
  say "Berikutnya akan muncul lisensi resmi Android. Bacalah dan setujui hanya jika Anda menerima ketentuannya."
  "$sdkmanager" --sdk_root="$SDK_ROOT" --licenses
  "$sdkmanager" --sdk_root="$SDK_ROOT" "platform-tools" "platforms;android-35" "build-tools;35.0.0"
}

setup_java

SDK_ROOT=""
LOCAL_SDK=$(read_local_sdk || true)
if [ -n "$LOCAL_SDK" ] && looks_like_sdk "$LOCAL_SDK"; then
  SDK_ROOT="$LOCAL_SDK"
elif [ -n "${ANDROID_HOME:-}" ] && looks_like_sdk "$ANDROID_HOME"; then
  SDK_ROOT="$ANDROID_HOME"
elif [ -n "${ANDROID_SDK_ROOT:-}" ] && looks_like_sdk "$ANDROID_SDK_ROOT"; then
  SDK_ROOT="$ANDROID_SDK_ROOT"
elif looks_like_sdk "$HOME/Library/Android/sdk"; then
  SDK_ROOT="$HOME/Library/Android/sdk"
elif looks_like_sdk "$HOME/Android/Sdk"; then
  SDK_ROOT="$HOME/Android/Sdk"
fi

if [ -z "$SDK_ROOT" ]; then
  SDK_ROOT="$HOME/Library/Android/sdk"
  say "Android SDK belum ditemukan pada local.properties, ANDROID_HOME, ANDROID_SDK_ROOT, atau lokasi standar macOS."
  if ! ask_yes "Unduh Android Command Line Tools dan instal SDK API 35 ke '$SDK_ROOT'? (butuh internet; unduhan awal sekitar 156 MB)"; then
    fail "SDK belum terpasang. Buka Android Studio > Tools > SDK Manager, pasang Android SDK Platform 35 dan Build Tools 35.0.0, lalu jalankan setup-android.sh lagi."
  fi
  mkdir -p "$SDK_ROOT"
  download_command_line_tools
  install_missing_packages
else
  say "SDK ditemukan: $SDK_ROOT"
  missing=0
  [ -d "$SDK_ROOT/platforms/android-35" ] || missing=1
  has_build_tools || missing=1
  [ -d "$SDK_ROOT/platform-tools" ] || missing=1
  if [ "$missing" -eq 1 ]; then
    if ask_yes "SDK ada tetapi komponen API 35/build-tools/platform-tools belum lengkap. Instal komponen yang diperlukan sekarang?"; then
      install_missing_packages
    else
      fail "Komponen SDK belum lengkap. Pasang melalui Android Studio > Tools > SDK Manager, lalu jalankan setup-android.sh lagi."
    fi
  fi
fi

[ -d "$SDK_ROOT/platforms/android-35" ] || fail "Android SDK Platform 35 belum terpasang. Jalankan setup-android.sh lagi dan izinkan pemasangan komponen."
has_build_tools || fail "Android Build Tools 35.x belum terpasang. Jalankan setup-android.sh lagi."
[ -d "$SDK_ROOT/platform-tools" ] || fail "Android Platform Tools belum terpasang. Jalankan setup-android.sh lagi."

# Resolve to an absolute canonical path where possible.
SDK_ROOT=$(cd "$SDK_ROOT" && pwd -P)
write_local_properties
chmod +x "$APP_DIR/gradlew"

say ""; say "Setup SDK berhasil. local.properties telah ditulis ulang:"
cat "$APP_DIR/local.properties"
say ""; say "Sekarang build APK dengan:"
say "  ./gradlew clean assembleDebug"
say "APK debug: $APP_DIR/app/build/outputs/apk/debug/app-debug.apk"
