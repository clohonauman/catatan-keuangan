<?php
namespace app\services;

use Yii;
use app\repositories\AuthRepository;

final class GoogleIdentityService
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    public static function verifyIdToken(string $jwt, string $clientId, string $expectedNonce): array
    {
        $jwt = trim($jwt);
        if ($jwt === '' || strlen($jwt) > 12000 || $clientId === '' || $expectedNonce === '') {
            throw new \RuntimeException('Sesi Google tidak valid. Silakan coba login kembali.');
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Respons login Google tidak valid.');
        }
        [$head64, $payload64, $sig64] = $parts;
        $header = self::decodeJsonPart($head64);
        $claims = self::decodeJsonPart($payload64);
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            throw new \RuntimeException('Tanda tangan login Google tidak didukung.');
        }

        $certs = self::googleCerts();
        $cert = null;
        foreach (($certs['keys'] ?? []) as $key) {
            if (($key['kid'] ?? '') === $header['kid'] && !empty($key['x5c'][0])) {
                $cert = (string)$key['x5c'][0];
                break;
            }
        }
        if ($cert === null) {
            Yii::$app->cache->delete('google_oidc_certs_v1');
            $certs = self::googleCerts(true);
            foreach (($certs['keys'] ?? []) as $key) {
                if (($key['kid'] ?? '') === $header['kid'] && !empty($key['x5c'][0])) {
                    $cert = (string)$key['x5c'][0];
                    break;
                }
            }
        }
        if ($cert === null) {
            throw new \RuntimeException('Kunci verifikasi Google telah berubah. Silakan coba lagi.');
        }

        $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64, "\n") . "-----END CERTIFICATE-----\n";
        $x509 = openssl_x509_read($pem);
        $publicKey = $x509 ? openssl_pkey_get_public($x509) : false;
        $signature = self::base64UrlDecode($sig64);
        if (!$publicKey || $signature === false || openssl_verify($head64 . '.' . $payload64, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Login Google tidak dapat diverifikasi. Silakan coba kembali.');
        }

        $now = time();
        $aud = $claims['aud'] ?? null;
        $audOk = is_string($aud) ? hash_equals($clientId, $aud) : (is_array($aud) && in_array($clientId, $aud, true));
        if (is_array($aud) && count($aud) > 1 && empty($claims['azp'])) $audOk = false;
        $issuer = (string)($claims['iss'] ?? '');
        $email = strtolower(trim((string)($claims['email'] ?? '')));
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $issuedAt = (int)($claims['iat'] ?? 0);
        $expiresAt = (int)($claims['exp'] ?? 0);
        $notBefore = (int)($claims['nbf'] ?? 0);
        $nonce = (string)($claims['nonce'] ?? '');

        // Native Android Credential Manager tokens can have the Web OAuth client
        // as `aud` and the Android OAuth client as `azp`. The audience is the
        // backend's primary recipient check; if an Android client ID is configured,
        // additionally restrict `azp` to that known client. Web GIS tokens continue
        // to require the configured Web client ID when `azp` is present.
        $azp = trim((string)($claims['azp'] ?? ''));
        $androidClientId = trim((string)($_ENV['GOOGLE_ANDROID_CLIENT_ID'] ?? getenv('GOOGLE_ANDROID_CLIENT_ID') ?: ''));
        $nativeAndroidRequest = preg_match('/CatatanKeuanganAndroid\/[0-9.]+/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')) === 1;
        $azpOk = true;
        if ($azp !== '') {
            if ($nativeAndroidRequest) {
                $azpOk = ($androidClientId !== '')
                    ? hash_equals($androidClientId, $azp)
                    : true; // aud, signature, nonce and verified email are still strictly checked below.
            } else {
                $azpOk = hash_equals($clientId, $azp);
            }
        }

        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)
            || !$audOk
            || !$azpOk
            || $expiresAt <= $now
            || $issuedAt <= 0
            || $issuedAt > $now + 60
            || ($notBefore > 0 && $notBefore > $now + 60)
            || $nonce === ''
            || !hash_equals($expectedNonce, $nonce)
            || empty($claims['sub'])
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !$emailVerified
        ) {
            Yii::warning('[Google login] claims rejected: ' . json_encode([
                'issuer_ok' => in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true),
                'aud_ok' => $audOk,
                'azp_present' => $azp !== '',
                'azp_ok' => $azpOk,
                'native_android' => $nativeAndroidRequest,
                'expiry_ok' => $expiresAt > $now,
                'issued_at_ok' => $issuedAt > 0 && $issuedAt <= $now + 60,
                'nonce_present' => $nonce !== '',
                'nonce_ok' => $nonce !== '' && hash_equals($expectedNonce, $nonce),
                'sub_present' => !empty($claims['sub']),
                'email_valid' => (bool)filter_var($email, FILTER_VALIDATE_EMAIL),
                'email_verified' => $emailVerified,
            ], JSON_UNESCAPED_SLASHES), __METHOD__);
            throw new \RuntimeException('Akun Google harus valid dan emailnya sudah terverifikasi. Silakan coba kembali.');
        }

        return [
            'sub' => (string)$claims['sub'],
            'email' => $email,
            'email_verified' => true,
            'name' => trim((string)($claims['name'] ?? '')),
            'picture' => (string)($claims['picture'] ?? ''),
        ];
    }

    public static function loginExistingAccount(array $claims): array
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $db = Yii::$app->db;
        $sub = trim((string)($claims['sub'] ?? ''));
        $email = strtolower(trim((string)($claims['email'] ?? '')));
        if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($claims['email_verified'])) {
            throw new \RuntimeException('Identitas Google tidak lengkap.');
        }

        $tx = $db->beginTransaction();
        try {
            $identity = (new \yii\db\Query())->from('{{%user_google_identity}}')->where(['google_sub' => $sub])->one();
            if ($identity) {
                $user = AuthRepository::findById((int)$identity['user_id']);
                if (!$user) throw new \RuntimeException('Akun yang terhubung dengan Google tidak ditemukan.');
                $db->createCommand()->update('{{%user_google_identity}}', ['email' => $email, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int)$identity['id']])->execute();
            } else {
                $user = AuthRepository::findByEmail($email);
                if (!$user) {
                    throw new \RuntimeException('Email Google belum terdaftar di Catatan Keuangan. Buat akun terlebih dahulu dengan email tersebut, lalu login menggunakan Google.');
                }
                $now = date('Y-m-d H:i:s');
                // Token Google telah diverifikasi dengan tanda tangan, audience, nonce, dan email_verified.
                // Karena Google membuktikan kepemilikan alamat email ini, alamat yang sama dapat ditautkan aman.
                if (empty($user['email_verified_at'])) {
                    $db->createCommand()->update('{{%user}}', ['email_verified_at' => $now, 'updated_at' => $now], ['id' => (int)$user['id']])->execute();
                    $user['email_verified_at'] = $now;
                }
                $db->createCommand()->insert('{{%user_google_identity}}', [
                    'user_id' => (int)$user['id'],
                    'google_sub' => $sub,
                    'email' => $email,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) $tx->rollBack();
            if ($e instanceof \RuntimeException) throw $e;
            Yii::warning('[Google login] identity mapping failed: ' . $e->getMessage(), __METHOD__);
            throw new \RuntimeException('Akun Google tidak dapat ditautkan. Jika email ini sudah terhubung ke akun Google lain, hubungi bantuan.');
        }

        $user = AuthRepository::findById((int)$user['id']);
        if (!$user) throw new \RuntimeException('Akun tidak ditemukan setelah login Google.');
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['pin_verified'] = empty($user['pin_hash']);
        unset($_SESSION['force_app_list'], $_SESSION['google_login_nonce']);
        authIssueDeviceToken((int)$user['id']);
        if (function_exists('authSendLoginNotification')) authSendLoginNotification($user);
        return $user;
    }

    private static function googleCerts(bool $force = false): array
    {
        $cacheKey = 'google_oidc_certs_v1';
        if (!$force) {
            $cached = Yii::$app->cache->get($cacheKey);
            if (is_array($cached) && !empty($cached['keys'])) return $cached;
        }
        $body = false;
        $status = 0;
        $error = '';
        if (function_exists('curl_init')) {
            $ch = curl_init(self::CERTS_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
        } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 7, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents(self::CERTS_URL, false, $context);
            $headers = $http_response_header ?? [];
            if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) $status = (int)$m[1];
            if ($body === false) $error = 'HTTPS stream request failed';
        } else {
            throw new \RuntimeException('Server belum mendukung koneksi HTTPS untuk login Google. Aktifkan cURL atau allow_url_fopen.');
        }
        $data = is_string($body) ? json_decode($body, true) : null;
        // oauth2/v1/certs returns an object keyed by kid with PEM certificates;
        // normalize it to the {keys:[{kid,x5c:[...]}]} shape used by verification.
        if ($status === 200 && is_array($data) && empty($data['keys'])) {
            $normalized = [];
            foreach ($data as $kid => $pem) {
                if (!is_string($kid) || !is_string($pem) || strpos($pem, 'BEGIN CERTIFICATE') === false) continue;
                $clean = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\\s+/', '', $pem);
                if (is_string($clean) && $clean !== '') $normalized[] = ['kid' => $kid, 'x5c' => [$clean]];
            }
            $data = ['keys' => $normalized];
        }
        if ($status !== 200 || !is_array($data) || empty($data['keys'])) {
            Yii::warning('[Google login] certificate fetch failed: HTTP ' . $status . ' ' . $error, __METHOD__);
            throw new \RuntimeException('Layanan verifikasi Google sedang tidak dapat dihubungi. Silakan coba lagi.');
        }
        Yii::$app->cache->set($cacheKey, $data, 3300);
        return $data;
    }

    private static function decodeJsonPart(string $part): array
    {
        $decoded = self::base64UrlDecode($part);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($data)) throw new \RuntimeException('Token login Google tidak valid.');
        return $data;
    }

    private static function base64UrlDecode(string $value)
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        return base64_decode($value, true);
    }
}
