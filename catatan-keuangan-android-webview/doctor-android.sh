#!/bin/bash
set -u
APP_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$APP_DIR"

echo "=== Catatan Keuangan Android SDK Doctor ==="
echo "Project root: $APP_DIR"
echo ""

SDK=""
if [ -f local.properties ]; then
  value=$(sed -n 's/^sdk\.dir=//p' local.properties | tail -n 1)
  value=${value//\\ / }
  value=${value//\\:/\:}
  if [ -n "$value" ] && [ -d "$value" ]; then SDK="$value"; fi
fi
if [ -z "$SDK" ] && [ -n "${ANDROID_HOME:-}" ] && [ -d "$ANDROID_HOME" ]; then SDK="$ANDROID_HOME"; fi
if [ -z "$SDK" ] && [ -n "${ANDROID_SDK_ROOT:-}" ] && [ -d "$ANDROID_SDK_ROOT" ]; then SDK="$ANDROID_SDK_ROOT"; fi
if [ -z "$SDK" ] && [ -d "$HOME/Library/Android/sdk" ]; then SDK="$HOME/Library/Android/sdk"; fi
if [ -z "$SDK" ] && [ -d "$HOME/Android/Sdk" ]; then SDK="$HOME/Android/Sdk"; fi

echo "SDK location: ${SDK:-NOT FOUND}"
if [ -n "$SDK" ]; then
  [ -d "$SDK/platforms/android-35" ] && echo "PASS: Android SDK Platform 35" || echo "MISSING: platforms/android-35"
  [ -d "$SDK/build-tools/35.0.0" ] && echo "PASS: Build Tools 35.0.0" || {
    found=false
    for bt in "$SDK"/build-tools/35.*; do [ -d "$bt" ] && found=true; done
    [ "$found" = true ] && echo "PASS: Build Tools 35.x" || echo "MISSING: build-tools/35.x"
  }
  [ -d "$SDK/platform-tools" ] && echo "PASS: platform-tools" || echo "MISSING: platform-tools"
  found_manager=false
  for sm in "$SDK"/cmdline-tools/*/bin/sdkmanager "$SDK"/tools/bin/sdkmanager; do [ -x "$sm" ] && found_manager=true; done
  [ "$found_manager" = true ] && echo "PASS: sdkmanager found" || echo "NOTE: sdkmanager not installed (not needed if required packages exist)"
fi

echo ""
if command -v java >/dev/null 2>&1; then
  java -version 2>&1 | head -n 3
else
  echo "MISSING: Java/JDK. Android Gradle Plugin requires JDK 17+. Android Studio includes JBR."
fi

echo ""
if [ -f local.properties ]; then
  echo "local.properties: present"
  cat local.properties
else
  echo "local.properties: MISSING"
fi

echo ""
if [ -n "$SDK" ] && [ -d "$SDK/platforms/android-35" ] && [ -d "$SDK/platform-tools" ]; then
  echo "SDK path and platform checks look good. Build with ./gradlew clean assembleDebug."
else
  echo "SDK is not ready. Run ./setup-android.sh and allow the SDK setup/install prompts."
  exit 1
fi
