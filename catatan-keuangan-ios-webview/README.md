# Catatan Keuangan iOS

Aplikasi iOS native-wrapper untuk `https://catatan-keuangan.cloud/`, dibuat dengan **SwiftUI + WKWebView** dan mengikuti konsep aplikasi Android Catatan Keuangan.

## Target

- iOS minimum: **15.0**
- iPhone + iPad
- Swift / SwiftUI
- WKWebView persistent session/cookie
- Bundle ID default: `com.charlie.finance.ios`
- Version: `1.0.0` (build 1)

## Fitur

- Native splash screen + splash SwiftUI sekitar 900 ms.
- WKWebView untuk aplikasi Catatan Keuangan.
- Session/cookie persisten melalui `WKWebsiteDataStore.default()`.
- Face ID / Touch ID melalui `LocalAuthentication`.
- Fallback kode perangkat pada dialog native, plus tombol **Gunakan PIN aplikasi** seperti Android.
- Relock biometrik setelah aplikasi berada di background >= 30 detik.
- Bridge biometrik kompatibel dengan web yang sekarang memakai `window.AndroidBiometric`, jadi tidak perlu membuat UI biometrik khusus iOS di web.
- Kamera/galeri untuk input file/foto nota melalui mekanisme native WKWebView/iOS.
- Permission Camera, Photos, dan Face ID sudah ada pada `Info.plist`.
- Offline fallback lokal jika domain tidak dapat dibuka saat tidak ada jaringan.
- Ketika internet kembali, app memicu event `online` agar queue/offline-sync pada aplikasi web dapat lanjut.
- Download PDF/CSV/XLS/backup berbasis `Content-Disposition: attachment` menggunakan `WKDownload`, lalu menampilkan Save to Files / document export sheet.
- Link di domain `catatan-keuangan.cloud` tetap di dalam aplikasi.
- Link eksternal dibuka melalui aplikasi/browser iOS.
- JavaScript `alert`, `confirm`, dan `prompt` ditampilkan sebagai dialog native iOS.
- Back/forward swipe gesture WKWebView aktif.
- HTTP tidak diizinkan oleh ATS; aplikasi ditujukan ke HTTPS.

## Buka project

Buka:

```text
CatatanKeuanganIOS.xcodeproj
```

melalui Xcode.

## Sebelum dijalankan di iPhone

1. Pilih project **CatatanKeuanganIOS**.
2. Buka target **CatatanKeuanganIOS**.
3. Masuk ke **Signing & Capabilities**.
4. Centang **Automatically manage signing**.
5. Pilih Apple Developer Team milik Anda.
6. Jika Bundle Identifier sudah digunakan, ganti `com.charlie.finance.ios` menjadi identifier milik Anda, misalnya:

```text
com.namaanda.catatankeuangan
```

7. Pilih iPhone / Simulator lalu Run.

## Mengubah domain

Edit:

```text
CatatanKeuanganIOS/App/AppConfig.swift
```

Saat ini:

```swift
static let appURL = URL(string: "https://catatan-keuangan.cloud/")!
static let appHost = "catatan-keuangan.cloud"
```

Jika domain berubah, ganti **keduanya**.

## Build Archive / TestFlight / App Store

Di Xcode:

```text
Product
→ Archive
→ Organizer
→ Distribute App
→ App Store Connect
```

Untuk TestFlight, upload build ke App Store Connect lalu aktifkan build pada bagian TestFlight.

## Struktur penting

```text
CatatanKeuanganIOS/
├── App/
│   ├── CatatanKeuanganIOSApp.swift
│   ├── ContentView.swift
│   ├── FinanceWebView.swift
│   ├── AppModel.swift
│   ├── AppConfig.swift
│   ├── NetworkMonitor.swift
│   ├── BiometricManager.swift
│   ├── SplashView.swift
│   └── BiometricLockView.swift
└── Resources/
    ├── Info.plist
    ├── Offline.html
    ├── Assets.xcassets/
    └── Base.lproj/LaunchScreen.storyboard
```

## Catatan biometrik

Web Anda saat ini memeriksa `window.AndroidBiometric`. Supaya source web tidak perlu dirombak lagi, aplikasi iOS menyuntik compatibility bridge dengan nama yang sama:

```text
window.AndroidBiometric.getStatus()
window.AndroidBiometric.requestEnable()
window.AndroidBiometric.requestDisable()
```

Di iOS, fungsi tersebut sebenarnya diteruskan ke `LocalAuthentication` melalui `WKScriptMessageHandler`.

## Catatan upload foto

`<input type="file">` pada WKWebView akan menggunakan picker native iOS. Untuk input dengan `capture="environment"`, iOS dapat menawarkan kamera sesuai kemampuan perangkat dan versi iOS. Permission Camera/Photos sudah disediakan pada Info.plist.

## Catatan download

Response server yang memiliki:

```http
Content-Disposition: attachment
```

akan diproses sebagai download dan kemudian ditawarkan melalui document export sheet agar user dapat menyimpan file ke Files/iCloud Drive/On My iPhone.

## Yang tetap dikerjakan oleh aplikasi web

- login / register / PIN aplikasi;
- transaksi;
- smart chat;
- offline IndexedDB / queue sync;
- Premium / Free Trial;
- PDF/Excel/CSV;
- Super Admin;
- seluruh data MySQL.

Aplikasi iOS tidak membuat database keuangan kedua. Data utama tetap berasal dari server Catatan Keuangan.
