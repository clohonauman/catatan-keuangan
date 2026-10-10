<?php
namespace app\services;

use Yii;
use app\repositories\AuthRepository;
use RuntimeException;

/**
 * Google Identity Services verifier and account linker.
 * The browser token is never trusted until its RS256 signature and claims are checked here.
 */
final class GoogleSignInService
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';
    private const CERT_CACHE_KEY = 'google-gsi-x509-certs-v1';
    private const CERT_CACHE_TTL = 3000;

    public static function login(string $credential, string $clientId, string $expectedNonce): array
    {
        $clientId = trim($clientId);
        if ($clientId === '') {
            throw new RuntimeException('Login Google belum diaktifkan. Silakan gunakan username dan password.');
        }
        if ($expectedNonce === '') {
            throw new RuntimeException('Permintaan login Google kedaluwarsa. Silakan coba kembali.');
        }

        $parts = self::tokenParts($credential);
        $header = $parts['header'];
        $kid = trim((string)($header['kid'] ?? ''));
        if ($kid === '' || ($header['alg'] ?? '') !== 'RS256') {
            throw new RuntimeException('Token Google tidak valid. Silakan coba kembali.');
        }

        $certs = self::getCertificates(false);
        if (!isset($certs[$kid])) {
            // Google rotates signing certificates; refresh once if this token uses a new kid.
            $certs = self::getCertificates(true);
        }
        $claims = self::verifyIdTokenWithCertificates($credential, $clientId, $expectedNonce, $certs);

        $sub = trim((string)$claims['sub']);
        $email = strtolower(trim((string)$claims['email']));
        $user = AuthRepository::findByGoogleSub($sub);

        if (!$user) {
            $user = \authFindUserByEmail($email);
            if (!$user) {
                throw new RuntimeException('Email Google ini belum terhubung ke akun Catatan Keuangan. Silakan daftar terlebih dahulu dengan email yang sama.');
            }

            $existingSub = trim((string)($user['google_sub'] ?? ''));
            if ($existingSub !== '' && !hash_equals($existingSub, $sub)) {
                throw new RuntimeException('Akun Catatan Keuangan ini sudah terhubung ke akun Google lain. Silakan login dengan metode sebelumnya.');
            }

            $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
            $hostedDomain = strtolower(trim((string)($claims['hd'] ?? '')));
            // Google is authoritative for Gmail and Google Workspace identities. For a third-party
            // address, require that the app account already verified this same email independently.
            $googleEmailAuthoritative = $domain === 'gmail.com' || $hostedDomain !== '';
            if (!$googleEmailAuthoritative && empty($user['email_verified_at'])) {
                throw new RuntimeException('Email akun ini belum terverifikasi di Catatan Keuangan. Login menggunakan username dan password, verifikasi email, lalu coba lagi.');
            }

            $userId = (int)$user['id'];
            $linkedAt = date('Y-m-d H:i:s');
            \authMutateUserData($userId, function (&$data) use ($sub, $email, $linkedAt, $googleEmailAuthoritative) {
                if (empty($data['users'][0]) || !is_array($data['users'][0])) {
                    throw new RuntimeException('Akun Catatan Keuangan tidak ditemukan.');
                }
                $linked =& $data['users'][0];
                $currentSub = trim((string)($linked['google_sub'] ?? ''));
                if ($currentSub !== '' && !hash_equals($currentSub, $sub)) {
                    throw new RuntimeException('Akun Catatan Keuangan ini sudah terhubung ke akun Google lain.');
                }
                $linked['google_sub'] = $sub;
                $linked['google_email'] = $email;
                $linked['google_linked_at'] = $linked['google_linked_at'] ?? $linkedAt;
                $linked['google_last_login_at'] = $linkedAt;
                if ($googleEmailAuthoritative && empty($linked['email_verified_at'])) {
                    $linked['email_verified_at'] = $linkedAt;
                    $linked['email_verification_token_hash'] = '';
                    $linked['email_verification_expires_at'] = '';
                    $linked['email_verification_sent_at'] = '';
                    $linked['email_verification_attempts'] = 0;
                }
            });
            $user = \authFindUserById($userId);
        } else {
            // Update only provider metadata. Never overwrite the app's primary email from a new claim.
            $userId = (int)$user['id'];
            $lastLoginAt = date('Y-m-d H:i:s');
            \authMutateUserData($userId, function (&$data) use ($sub, $email, $lastLoginAt) {
                if (empty($data['users'][0]) || !is_array($data['users'][0])) {
                    throw new RuntimeException('Akun Catatan Keuangan tidak ditemukan.');
                }
                $linked =& $data['users'][0];
                $currentSub = trim((string)($linked['google_sub'] ?? ''));
                if ($currentSub === '' || !hash_equals($currentSub, $sub)) {
                    throw new RuntimeException('Tautan akun Google tidak valid. Silakan login menggunakan username dan password.');
                }
                $linked['google_email'] = $email;
                $linked['google_last_login_at'] = $lastLoginAt;
            });
            $user = \authFindUserById($userId);
        }

        if (!$user || (int)($user['id'] ?? 0) <= 0) {
            throw new RuntimeException('Akun Catatan Keuangan tidak dapat dibuka. Silakan coba lagi.');
        }

        // Match the regular login lifecycle: rotate session, issue trusted-device token,
        // keep PIN gate enabled for users who have already configured a PIN.
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['pin_verified'] = empty($user['pin_hash']);
        \authIssueDeviceToken((int)$user['id']);
        \authSendLoginNotification($user);

        return $user;
    }

    /** Public for deterministic tests using a locally generated signing certificate. */
    public static function verifyIdTokenWithCertificates(
        string $credential,
        string $clientId,
        string $expectedNonce,
        array $certificates
    ): array {
        $parts = self::tokenParts($credential);
        $header = $parts['header'];
        $claims = $parts['claims'];
        $kid = trim((string)($header['kid'] ?? ''));

        if (($header['alg'] ?? '') !== 'RS256' || $kid === '' || !isset($certificates[$kid]) || !is_string($certificates[$kid])) {
            throw new RuntimeException('Token Google tidak valid. Silakan coba kembali.');
        }
        if (!function_exists('openssl_verify') || !function_exists('openssl_pkey_get_public')) {
            throw new RuntimeException('Layanan verifikasi Google belum tersedia di server. Hubungi admin aplikasi.');
        }

        $publicKey = @openssl_pkey_get_public($certificates[$kid]);
        $signature = self::base64UrlDecode($parts['signature']);
        if ($publicKey === false || $signature === false) {
            throw new RuntimeException('Token Google tidak valid. Silakan coba kembali.');
        }
        $verified = @openssl_verify($parts['signed'], $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new RuntimeException('Token Google tidak valid. Silakan pilih akun dan coba lagi.');
        }

        $now = time();
        $issuer = (string)($claims['iss'] ?? '');
        $audience = $claims['aud'] ?? null;
        $audienceMatches = is_string($audience)
            ? hash_equals($clientId, $audience)
            : (is_array($audience) && in_array($clientId, $audience, true));
        $multipleAudiences = is_array($audience) && count($audience) > 1;
        $authorizedParty = trim((string)($claims['azp'] ?? ''));
        $issuedAt = filter_var($claims['iat'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);
        $hasNotBefore = array_key_exists('nbf', $claims);
        $notBefore = $hasNotBefore ? filter_var($claims['nbf'], FILTER_VALIDATE_INT) : null;

        // The backend client ID must match `aud`. For Android Credential Manager, `azp`
        // may identify the Android OAuth client while `aud` is the server's Web client ID.
        // Google backend-auth guidance requires strict signature/aud/iss/exp validation;
        // don't reject a single-audience token only because `azp` is a different client.
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            self::auditTokenRejection('issuer_mismatch');
            throw new RuntimeException('Penerbit token Google tidak valid. Silakan pilih akun dan coba lagi.');
        }
        if (!$audienceMatches) {
            self::auditTokenRejection('audience_mismatch');
            throw new RuntimeException('Token Google tidak ditujukan untuk aplikasi ini. Periksa konfigurasi Client ID Web di server.');
        }
        if ($issuedAt === false || $expiresAt === false || $expiresAt <= $now) {
            self::auditTokenRejection('token_expired_or_invalid_time');
            throw new RuntimeException('Token Google sudah kedaluwarsa. Silakan pilih akun Google dan coba lagi.');
        }
        if ($issuedAt > ($now + 300)) {
            self::auditTokenRejection('issued_at_in_future');
            throw new RuntimeException('Waktu server belum sinkron. Silakan coba beberapa saat lagi atau hubungi admin aplikasi.');
        }
        if ($hasNotBefore && ($notBefore === false || $notBefore < 0 || $notBefore > ($now + 60))) {
            self::auditTokenRejection('invalid_not_before');
            throw new RuntimeException('Token Google belum berlaku. Silakan pilih akun dan coba lagi.');
        }

        // Multiple audiences are unusual for this flow. In that case require an explicit
        // authorized party, either this server's Web Client ID or an allowlisted OAuth client.
        // GOOGLE_ALLOWED_AZP_CLIENT_IDS is optional and comma-separated; use it only for
        // verified client IDs belonging to this same Google Cloud OAuth project.
        if ($multipleAudiences) {
            $allowedAzp = array_merge([$clientId], self::configuredAllowedAzpClientIds());
            if ($authorizedParty === '' || !in_array($authorizedParty, $allowedAzp, true)) {
                self::auditTokenRejection('multiple_audience_azp_mismatch');
                throw new RuntimeException('Token Google tidak valid untuk konfigurasi aplikasi ini. Hubungi admin aplikasi.');
            }
        }

        $tokenNonce = (string)($claims['nonce'] ?? '');
        if ($expectedNonce === '' || $tokenNonce === '' || !hash_equals($expectedNonce, $tokenNonce)) {
            throw new RuntimeException('Permintaan login Google tidak valid atau kedaluwarsa. Silakan coba kembali.');
        }

        $email = strtolower(trim((string)($claims['email'] ?? '')));
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $sub = trim((string)($claims['sub'] ?? ''));
        if (!$emailVerified || !filter_var($email, FILTER_VALIDATE_EMAIL) || $sub === '') {
            throw new RuntimeException('Akun Google harus memiliki email terverifikasi. Verifikasi email Google lalu coba lagi.');
        }
        $claims['email'] = $email;
        $claims['sub'] = $sub;
        return $claims;
    }

    /**
     * Optional, comma-separated OAuth Client IDs that may appear in `azp` when a token
     * legitimately contains multiple audiences. These IDs never replace the strict `aud`
     * check against GOOGLE_CLIENT_ID.
     */
    private static function configuredAllowedAzpClientIds(): array
    {
        $raw = (string)($_ENV['GOOGLE_ALLOWED_AZP_CLIENT_IDS'] ?? getenv('GOOGLE_ALLOWED_AZP_CLIENT_IDS') ?: '');
        $values = preg_split('/[,;\s]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $values = array_map('trim', $values);
        return array_values(array_unique(array_filter($values, static function ($value) {
            return $value !== '' && strlen($value) <= 255;
        })));
    }

    /** Log reason codes only; never write ID tokens, email addresses, or raw claims to logs. */
    private static function auditTokenRejection(string $reason): void
    {
        if (class_exists('Yii') && method_exists('Yii', 'warning')) {
            \Yii::warning(['event' => 'google_id_token_rejected', 'reason' => $reason], 'security.google-signin');
        }
    }

    private static function tokenParts(string $credential): array
    {
        $pieces = explode('.', trim($credential));
        if (count($pieces) !== 3 || strlen($credential) > 20000) {
            throw new RuntimeException('Token Google tidak valid. Silakan pilih akun dan coba lagi.');
        }
        $headerJson = self::base64UrlDecode($pieces[0]);
        $claimsJson = self::base64UrlDecode($pieces[1]);
        if ($headerJson === false || $claimsJson === false) {
            throw new RuntimeException('Token Google tidak valid. Silakan pilih akun dan coba lagi.');
        }
        $header = json_decode($headerJson, true);
        $claims = json_decode($claimsJson, true);
        if (!is_array($header) || !is_array($claims)) {
            throw new RuntimeException('Token Google tidak valid. Silakan pilih akun dan coba lagi.');
        }
        return [
            'header' => $header,
            'claims' => $claims,
            'signed' => $pieces[0] . '.' . $pieces[1],
            'signature' => $pieces[2],
        ];
    }

    private static function base64UrlDecode(string $value)
    {
        $value = strtr($value, '-_', '+/');
        $remainder = strlen($value) % 4;
        if ($remainder) $value .= str_repeat('=', 4 - $remainder);
        return base64_decode($value, true);
    }

    private static function getCertificates(bool $force): array
    {
        $cache = Yii::$app->cache;
        if (!$force && $cache) {
            $cached = $cache->get(self::CERT_CACHE_KEY);
            if (is_array($cached) && $cached) return $cached;
        }

        $certificates = self::fetchCertificatesFromGoogle();
        if ($cache) $cache->set(self::CERT_CACHE_KEY, $certificates, self::CERT_CACHE_TTL);
        return $certificates;
    }

    private static function fetchCertificatesFromGoogle(): array
    {
        $url = self::CERTS_URL;
        $body = false;
        $status = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'CatatanKeuangan-GoogleSignIn/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_errno($ch);
            curl_close($ch);
            if ($body === false || $curlError !== 0) {
                error_log('[Google Sign-In] Unable to fetch public certificates (network error).');
                throw new RuntimeException('Layanan login Google sedang tidak tersedia. Silakan coba lagi beberapa saat.');
            }
        } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 6, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) $status = (int)$m[1];
        } else {
            throw new RuntimeException('Layanan verifikasi Google tidak dapat dihubungi dari server. Hubungi admin aplikasi.');
        }

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if ($status !== 200 || !is_array($decoded) || !$decoded) {
            error_log('[Google Sign-In] Public certificate endpoint returned an invalid response (HTTP ' . $status . ').');
            throw new RuntimeException('Layanan login Google sedang tidak tersedia. Silakan coba lagi beberapa saat.');
        }
        foreach ($decoded as $kid => $certificate) {
            if (!is_string($kid) || !is_string($certificate) || strpos($certificate, 'BEGIN CERTIFICATE') === false) {
                unset($decoded[$kid]);
            }
        }
        if (!$decoded) {
            error_log('[Google Sign-In] Public certificate response contained no usable certificates.');
            throw new RuntimeException('Layanan login Google sedang tidak tersedia. Silakan coba lagi beberapa saat.');
        }
        return $decoded;
    }
}
