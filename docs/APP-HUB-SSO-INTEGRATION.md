# Integrasi SSO untuk aplikasi eksternal

Dokumen ini untuk pengembang aplikasi tujuan yang dibuka dari Pusat Aplikasi Catatan Keuangan. SSO memakai authorization code sekali pakai; **jangan** menyimpan token bypass permanen di URL.

## Nilai yang dikonfigurasi di aplikasi tujuan

Simpan sebagai environment variable di server aplikasi tujuan, jangan di JavaScript/browser:

```dotenv
CK_PORTAL_URL="https://catatan-keuangan.cloud"
CK_SSO_CLIENT_ID="ckapp_..."
CK_SSO_CLIENT_SECRET="secret-yang-ditampilkan-satu-kali"
CK_SSO_CALLBACK_URL="https://aplikasi.example.com/sso/callback.php"
```

`CK_SSO_CLIENT_SECRET` hanya ditampilkan satu kali ketika aplikasi dibuat atau token diregenerasi di menu Manajemen Aplikasi. Jika hilang, regenerasi token dan perbarui environment aplikasi tujuan. Secret lama langsung tidak berlaku.

Di Manajemen Aplikasi, isi **URL aplikasi** dengan endpoint start SSO, misalnya `https://aplikasi.example.com/sso/start.php`, dan **URL Callback SSO** dengan callback yang menerima `code` dan `state`. Jika callback dikosongkan, portal membuka URL sebagai tautan biasa dan login tidak dilewati.

## Alur callback yang aman

1. Pengguna memilih kartu aplikasi di portal; browser dibuka ke endpoint `app_url` aplikasi tujuan.
2. Endpoint start aplikasi tujuan membuat `state` acak dan menyimpannya pada session browser aplikasi, lalu mengarahkan browser ke portal `/index.php?r=sso/authorize` dengan `client_id`, `redirect_uri`, dan `state`.
3. Portal memeriksa sesi login/PIN, status aplikasi, URL callback yang terdaftar, dan akses Premium. Portal lalu membuat kode acak sekali pakai yang berlaku 90 detik dan mengarahkan browser ke callback dengan `code` dan `state` yang sama.
4. Callback aplikasi tujuan membandingkan `state` dengan nilai yang tersimpan pada session aplikasi, lalu menukar kode melalui permintaan server-ke-server.
5. Portal memeriksa client secret, hash kode, state, masa berlaku, app ID, dan apakah kode pernah digunakan. Jika sukses, portal mengembalikan identitas pengguna dalam JSON.

## Contoh `sso/start.php`

```php
<?php
session_start();
$portal = rtrim((string)getenv('CK_PORTAL_URL'), '/');
$clientId = (string)getenv('CK_SSO_CLIENT_ID');
$callback = (string)getenv('CK_SSO_CALLBACK_URL');
if ($portal === '' || $clientId === '' || $callback === '') {
    http_response_code(500);
    exit('Konfigurasi SSO belum lengkap.');
}
$state = bin2hex(random_bytes(32));
$_SESSION['ck_sso_state'] = $state;
$params = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $callback,
    'state' => $state,
], '', '&', PHP_QUERY_RFC3986);
header('Location: ' . $portal . '/index.php?r=sso/authorize&' . $params, true, 303);
exit;
```

## Contoh `sso/callback.php`

Sesuaikan bagian pembuatan session lokal dengan sistem autentikasi aplikasi tujuan.

```php
<?php
session_start();
$code = trim((string)($_GET['code'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));
$expectedState = (string)($_SESSION['ck_sso_state'] ?? '');
unset($_SESSION['ck_sso_state']);
if ($code === '' || $state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
    http_response_code(400);
    exit('State SSO tidak valid. Silakan mulai login dari aplikasi kembali.');
}

$portal = rtrim((string)getenv('CK_PORTAL_URL'), '/');
$payload = json_encode([
    'client_id' => (string)getenv('CK_SSO_CLIENT_ID'),
    'client_secret' => (string)getenv('CK_SSO_CLIENT_SECRET'),
    'code' => $code,
    'state' => $state,
], JSON_UNESCAPED_SLASHES);
$ch = curl_init($portal . '/index.php?r=sso/exchange');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$body = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$result = is_string($body) ? json_decode($body, true) : null;
if ($status !== 200 || !is_array($result) || empty($result['ok']) || empty($result['user']['id'])) {
    http_response_code(401);
    exit('SSO tidak berhasil. Silakan mulai lagi dari Pusat Aplikasi.');
}

session_regenerate_id(true);
$_SESSION['portal_identity'] = [
    'id' => (int)$result['user']['id'],
    'username' => (string)$result['user']['username'],
    'email' => (string)$result['user']['email'],
    'role' => (string)$result['user']['role'],
    'premium' => !empty($result['user']['premium']),
];
// Premium harus diperiksa pada server/rute berbayar aplikasi tujuan, bukan hanya di browser.
header('Location: /dashboard.php', true, 303);
exit;
```

## Pemeriksaan wajib di aplikasi tujuan

- Gunakan HTTPS untuk start, callback, dan permintaan exchange.
- Jangan mengirim client secret ke JavaScript atau menyimpannya di URL.
- Callback harus memvalidasi state yang dibuat oleh endpoint start dan disimpan dalam session browser aplikasi.
- Buat session lokal hanya setelah HTTP 200 dan `ok=true` dari endpoint portal.
- Jika aplikasi ditandai Premium, periksa `user.premium` pada session/server untuk setiap rute berbayar; jangan hanya mengandalkan kartu galeri.
- Tetap lakukan otorisasi per pengguna pada data internal aplikasi tujuan.
- Jika token diregenerasi, perbarui secret environment aplikasi tujuan.

Aplikasi yang hanya ditambahkan sebagai tautan tanpa callback SSO tetap bisa dibuka, tetapi login di aplikasi tersebut tidak dilewati. SSO tidak dapat dipaksakan pada aplikasi yang source/backend-nya tidak diintegrasikan.
