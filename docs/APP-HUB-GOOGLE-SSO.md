# Pusat Aplikasi, Login Google, dan SSO lintas aplikasi

## Perilaku Pusat Aplikasi

- Satu-satunya aplikasi bawaan adalah **Catatan Keuangan** (`app_id=main`).
- Aplikasi tambahan dikelola lewat **Manajemen Aplikasi**, yang muncul sebagai kartu khusus Super Admin dalam galeri. Kartu ini merupakan alat sistem, bukan aplikasi katalog yang bisa diubah/dihapus; tidak dihitung sebagai aplikasi bawaan.
- Hitungan aplikasi hanya menghitung **Catatan Keuangan** dan aplikasi eksternal yang terdaftar. Aplikasi bawaan tetap hanya satu: Catatan Keuangan.
- Katalog tambahan dan preferensi aplikasi terakhir disimpan pada tabel `app_document` yang sudah tersedia. Tidak ada migration baru.
- Jika pengguna telah memilih aplikasi dan kembali membuka root setelah autentikasi/PIN, aplikasi terakhir akan dibuka. `index.php?apps=1` memaksa tampilan Pusat Aplikasi. Tautan **Pusat Aplikasi** tersedia di sidebar Catatan Keuangan.
- Kartu eksternal yang diberi syarat Premium hanya dapat diluncurkan oleh akun dengan Premium/trial aktif atau Super Admin.

## Aktifkan Login dengan Google

1. Buka Google Cloud Console dan buat OAuth Client ID berjenis **Web application**.
2. Masukkan origin situs ke **Authorized JavaScript origins**, misalnya `https://catatan-keuangan.cloud` (tambahkan origin staging jika digunakan).
3. Tidak perlu memasukkan Google Client Secret untuk tombol Google Identity Services ini. Jangan memakai Client ID dari aplikasi lain yang tidak diotorisasi untuk origin tersebut.
4. Tambahkan ke `.env` production:

   ```dotenv
   GOOGLE_CLIENT_ID="1234567890-xxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com"
   ```

5. Pastikan PHP memiliki ekstensi `curl` dan `openssl`, serta server dapat mengakses `https://www.googleapis.com/oauth2/v3/certs` melalui HTTPS. Public certificate Google di-cache melalui cache Yii.
6. Buka halaman login dalam kondisi online. Tombol Google hanya tampil bila `GOOGLE_CLIENT_ID` terisi.

Backend tidak mempercayai nama atau email yang dikirim secara langsung oleh browser. Backend memverifikasi signature RS256, audience, issuer, masa berlaku, nonce, dan `email_verified=true` sebelum membuat sesi. Google subject (`sub`) disimpan dalam `extra_json` pengguna, sehingga tidak perlu kolom atau migration baru.

Akun Google baru dibuat sebagai akun Catatan Keuangan bila email belum terdaftar. Jika email telah ada, akun ditautkan ke `sub` terverifikasi. Demi menghindari auto-link yang berisiko untuk alamat email pihak ketiga, auto-link akun lama hanya diizinkan untuk Gmail atau Google Workspace (`hd`). Akun lama dengan email non-Gmail perlu masuk menggunakan username/password dan menghubungi Super Admin untuk penautan yang aman.

## SSO untuk aplikasi eksternal

Setiap aplikasi eksternal memiliki:

- **URL aplikasi**: lokasi aplikasi yang dibuka.
- **Endpoint SSO** (opsional): callback HTTPS yang menerima POST dengan field `sso_token`.
- **Kunci rahasia SSO bersama**: minimal 24 karakter acak, harus sama di portal dan aplikasi tujuan. Portal menyimpan nilai ini terenkripsi dengan `APP_KEY`.
- **Wajib Premium** dan **Aktif**.

Jika Endpoint SSO tidak diisi, kartu membuka URL sebagai tautan biasa dan aplikasi tujuan mungkin meminta login sendiri. Portal tidak bisa melewati login aplikasi sembarang hanya dengan menyimpan URL atau token; aplikasi tujuan harus menerapkan verifier di bawah.

Saat launch SSO, portal POST `sso_token` ke endpoint tujuan. Token JWT HS256 berlaku 60 detik dan berisi `iss`, `aud` (ID aplikasi yang dapat dilihat pada Manajemen Aplikasi), `sub` (ID akun Catatan Keuangan), `username`, `email`, `premium`, `plan_expires_at`, `iat`, `nbf`, `exp`, serta `jti` acak. Token yang kedaluwarsa, signature salah, issuer/audience salah, atau `jti` yang pernah dipakai wajib ditolak.

### Contoh verifikasi di aplikasi tujuan (PHP)

Sesuaikan penyimpanan replay protection dengan database/cache aplikasi tujuan. Kunci bersama jangan pernah ditaruh di JavaScript atau dibagikan kepada browser.

```php
<?php
function decodeB64Url(string $value): string {
    $value = strtr($value, '-_', '+/');
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    $decoded = base64_decode($value, true);
    if (!is_string($decoded)) throw new RuntimeException('Token tidak valid');
    return $decoded;
}

function verifyCatatanKeuanganSso(string $jwt, string $sharedSecret, string $portalUrl, string $appId): array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new RuntimeException('Token tidak valid');
    [$h64, $p64, $s64] = $parts;
    $header = json_decode(decodeB64Url($h64), true);
    $claims = json_decode(decodeB64Url($p64), true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'HS256') {
        throw new RuntimeException('Format token tidak valid');
    }
    $expected = hash_hmac('sha256', $h64 . '.' . $p64, $sharedSecret, true);
    if (!hash_equals($expected, decodeB64Url($s64))) throw new RuntimeException('Signature tidak valid');
    $now = time();
    if (($claims['iss'] ?? '') !== rtrim($portalUrl, '/') || ($claims['aud'] ?? '') !== $appId) throw new RuntimeException('Penerbit/audience tidak sesuai');
    if ((int)($claims['exp'] ?? 0) <= $now || (int)($claims['nbf'] ?? PHP_INT_MAX) > $now + 5) throw new RuntimeException('Token kedaluwarsa/belum berlaku');
    if ((int)($claims['iat'] ?? 0) > $now + 30 || empty($claims['sub']) || empty($claims['jti'])) throw new RuntimeException('Klaim tidak valid');

    // WAJIB: klaimkan jti sebagai sekali pakai secara atomik, dengan TTL sampai exp.
    // Contoh: unique INSERT pada tabel consumed_sso_jti; duplicate-key harus ditolak.
    if (!consumeSsoJtiOnce((string)$claims['jti'], (int)$claims['exp'])) throw new RuntimeException('Token sudah digunakan');
    return $claims;
}

// Setelah token tervalidasi dan jti diterima sekali saja, aplikasi tujuan membuat sesi lokalnya.
// Jangan mengandalkan username/email saja sebagai signature atau otorisasi.
```

`consumeSsoJtiOnce()` merupakan fungsi yang harus dibuat pada aplikasi tujuan, idealnya menggunakan tabel dengan unique index pada `jti` atau primitive cache atomik. Dengan begitu token singkat tidak bisa dipakai ulang.

## Pemeriksaan pascadeploy

1. Login dengan username/password; pengguna tanpa aplikasi pilihan harus melihat Pusat Aplikasi dengan satu kartu bawaan Catatan Keuangan.
2. Klik Catatan Keuangan, lalu kunjungi `index.php?apps=1`; kartu Pusat Aplikasi harus muncul kembali.
3. Logout dan login ulang; aplikasi terakhir yang dipilih harus terbuka otomatis.
4. Untuk Super Admin, buka Manajemen Aplikasi, tambahkan aplikasi uji, nonaktifkan lalu aktifkan kembali, kemudian hapus aplikasi uji.
5. Konfigurasikan Google Client ID dan uji akun Google terverifikasi; token invalid, audience salah, nonce salah, atau email tidak terverifikasi harus ditolak.
6. Uji SSO hanya pada aplikasi tujuan yang telah menerapkan verifier protocol ini. Aplikasi biasa tanpa callback tetap dibuka sebagai tautan biasa.
