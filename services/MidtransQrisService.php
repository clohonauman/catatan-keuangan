<?php
namespace app\services;

use RuntimeException;

/**
 * Minimal Midtrans QRIS integration using the official Core API over HTTPS.
 * This keeps the integration usable on hosts where Composer install is not available.
 */
final class MidtransQrisService
{
    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false || $value === null ? $default : trim((string)$value);
    }

    public static function serverKey(): string
    {
        return self::env('MIDTRANS_SERVER_KEY');
    }

    public static function isProduction(): bool
    {
        return filter_var(self::env('MIDTRANS_IS_PRODUCTION', 'false'), FILTER_VALIDATE_BOOL);
    }

    public static function isEnabled(): bool
    {
        $key = self::serverKey();
        $enabled = filter_var(self::env('MIDTRANS_ENABLED', 'true'), FILTER_VALIDATE_BOOL);
        if (!$enabled || strlen($key) < 20) return false;
        foreach (['GANTI', 'CHANGE_ME', 'YOUR_', 'ISI_SERVER_KEY', 'PLACEHOLDER'] as $marker) {
            if (stripos($key, $marker) !== false) return false;
        }
        return true;
    }

    public static function acquirer(): string
    {
        $acquirer = strtolower(self::env('MIDTRANS_QRIS_ACQUIRER', 'gopay'));
        return in_array($acquirer, ['gopay', 'airpay_shopee'], true) ? $acquirer : 'gopay';
    }

    private static function apiBase(): string
    {
        return self::isProduction() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    private static function assertConfigured(): void
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('Pembayaran QRIS belum aktif. Isi MIDTRANS_SERVER_KEY di file .env dan aktifkan MIDTRANS_ENABLED.');
        }
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
            throw new RuntimeException('Server membutuhkan ekstensi cURL atau allow_url_fopen untuk terhubung ke Midtrans.');
        }
    }

    /** @return array{body:string,status:int,content_type:string} */
    private static function request(string $method, string $urlOrPath, ?array $payload = null, bool $expectImage = false): array
    {
        self::assertConfigured();
        $url = preg_match('#^https://#i', $urlOrPath) ? $urlOrPath : self::apiBase() . '/' . ltrim($urlOrPath, '/');
        $parts = parse_url($url);
        $expectedHost = parse_url(self::apiBase(), PHP_URL_HOST);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== strtolower((string)$expectedHost)
            || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URL layanan pembayaran tidak valid.');
        }

        $headers = [
            'Authorization: Basic ' . base64_encode(self::serverKey() . ':'),
            'Accept: ' . ($expectImage ? 'image/png,image/*;q=0.8' : 'application/json'),
        ];
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body !== null) $headers[] = 'Content-Type: application/json';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $responseBody = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($responseBody === false) {
                throw new RuntimeException('Tidak dapat terhubung ke Midtrans: ' . ($curlError ?: 'koneksi gagal'));
            }
            return ['body' => (string)$responseBody, 'status' => $status, 'content_type' => $contentType];
        }

        $headerString = implode("\r\n", $headers);
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headerString,
                'content' => $body ?? '',
                'timeout' => 25,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ];
        $ctx = stream_context_create($options);
        $responseBody = @file_get_contents($url, false, $ctx);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        $contentType = '';
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) $status = (int)$m[1];
            if (stripos($line, 'Content-Type:') === 0) $contentType = trim(substr($line, 13));
        }
        if ($responseBody === false) throw new RuntimeException('Tidak dapat terhubung ke Midtrans. Periksa koneksi HTTPS hosting.');
        return ['body' => (string)$responseBody, 'status' => $status, 'content_type' => $contentType];
    }

    /** Create one dynamic QRIS transaction; amount must be calculated on the server. */
    public static function createQrisCharge(string $orderId, int $amount, array $user, array $plan): array
    {
        self::assertConfigured();
        if ($orderId === '' || strlen($orderId) > 50 || !preg_match('/^[A-Za-z0-9_-]+$/', $orderId)) {
            throw new RuntimeException('ID pesanan pembayaran tidak valid.');
        }
        if ($amount < 1) throw new RuntimeException('Nominal QRIS harus lebih besar dari Rp0.');

        $username = trim((string)($user['username'] ?? 'Pengguna'));
        $username = preg_replace('/[^\pL\pN ._-]/u', '', $username) ?: 'Pengguna';
        $username = function_exists('mb_substr') ? mb_substr($username, 0, 50, 'UTF-8') : substr($username, 0, 50);
        $planLabel = trim((string)($plan['label'] ?? 'Premium'));
        $itemName = 'Catatan Keuangan Premium - ' . $planLabel;
        $itemName = function_exists('mb_substr') ? mb_substr($itemName, 0, 50, 'UTF-8') : substr($itemName, 0, 50);
        $planId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($plan['key'] ?? 'premium')) ?: 'premium';
        $customer = ['first_name' => $username];
        $email = trim((string)($user['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) $customer['email'] = $email;

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'item_details' => [[
                'id' => substr($planId, 0, 50),
                'price' => $amount,
                'quantity' => 1,
                'name' => $itemName,
            ]],
            'customer_details' => $customer,
            'qris' => ['acquirer' => self::acquirer()],
        ];

        $response = self::request('POST', '/v2/charge', $payload, false);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) throw new RuntimeException('Respons QRIS dari Midtrans tidak dapat dibaca.');
        if ($response['status'] < 200 || $response['status'] >= 300 || empty($decoded['transaction_id'])) {
            $message = trim((string)($decoded['status_message'] ?? $decoded['error_messages'][0] ?? ''));
            throw new RuntimeException('Midtrans menolak pembuatan QRIS' . ($message !== '' ? ': ' . substr($message, 0, 180) : '.') );
        }
        if ((string)($decoded['order_id'] ?? '') !== $orderId) throw new RuntimeException('ID pesanan QRIS dari Midtrans tidak cocok.');
        if (number_format((float)($decoded['gross_amount'] ?? -1), 2, '.', '') !== number_format((float)$amount, 2, '.', '')) {
            throw new RuntimeException('Nominal QRIS dari Midtrans tidak cocok dengan invoice.');
        }

        $qrUrl = '';
        foreach ((array)($decoded['actions'] ?? []) as $action) {
            if (!is_array($action)) continue;
            $name = (string)($action['name'] ?? '');
            if (in_array($name, ['generate-qr-code-v2', 'generate-qr-code'], true)) {
                $candidate = (string)($action['url'] ?? '');
                if (self::isAllowedQrUrl($candidate)) {
                    // Normalize to the configured Sandbox/Production API host. Midtrans examples
                    // can contain api.midtrans.com QR URLs even on Sandbox examples; use the
                    // trusted path but never let a provider URL choose the environment/host.
                    $candidate = self::normalizeQrImageUrl($candidate) ?? '';
                    if ($candidate === '') continue;
                    // Prefer QRIS ASPI v2 image if the provider includes it.
                    if ($name === 'generate-qr-code-v2') { $qrUrl = $candidate; break; }
                    if ($qrUrl === '') $qrUrl = $candidate;
                }
            }
        }
        if ($qrUrl === '') throw new RuntimeException('Midtrans tidak mengembalikan URL QRIS yang valid.');

        return [
            'order_id' => $orderId,
            'transaction_id' => (string)$decoded['transaction_id'],
            'transaction_status' => (string)($decoded['transaction_status'] ?? 'pending'),
            'status_code' => (string)($decoded['status_code'] ?? $response['status']),
            'gross_amount' => (string)($decoded['gross_amount'] ?? ''),
            'transaction_time' => (string)($decoded['transaction_time'] ?? ''),
            'qr_url' => $qrUrl,
            'acquirer' => (string)($decoded['acquirer'] ?? self::acquirer()),
        ];
    }

    public static function verifyNotificationSignature(array $payload): bool
    {
        $key = self::serverKey();
        $orderId = (string)($payload['order_id'] ?? '');
        $statusCode = (string)($payload['status_code'] ?? '');
        $grossAmount = (string)($payload['gross_amount'] ?? '');
        $signature = strtolower(trim((string)($payload['signature_key'] ?? '')));
        if ($key === '' || $orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '') return false;
        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $key);
        return hash_equals($expected, $signature);
    }

    public static function isAllowedQrUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || !in_array($host, ['api.midtrans.com', 'api.sandbox.midtrans.com'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) return false;
        $path = preg_replace('#/+#', '/', (string)($parts['path'] ?? ''));
        // V2 QRIS uses /v2/qris/{transaction-id}/qr-code; V4 ASPI responses may
        // use /v4/qris/{reference} or /v4/qris/gopay/{reference}.
        return (bool)preg_match('#^/v(?:2|4)/qris/(?:gopay/)?[A-Za-z0-9_-]+/qr-code$#D', (string)$path);
    }

    /**
     * Keep the provider-returned QR resource path, but force the configured host.
     * This prevents environment crossover while supporting provider examples that
     * return a production-shaped URL for QR resources on Sandbox.
     */
    public static function normalizeQrImageUrl(string $url): ?string
    {
        if (!self::isAllowedQrUrl($url)) return null;
        $parts = parse_url($url);
        $path = preg_replace('#/+#', '/', (string)($parts['path'] ?? ''));
        if (!preg_match('#^/v(?:2|4)/qris/(?:gopay/)?[A-Za-z0-9_-]+/qr-code$#D', (string)$path)) return null;
        return self::apiBase() . $path;
    }

    /** Fetch QR image with the secret key on server, never expose the key in the browser. */
    public static function fetchQrImage(string $url): string
    {
        $normalizedUrl = self::normalizeQrImageUrl($url);
        if ($normalizedUrl === null) throw new RuntimeException('URL QRIS tidak valid.');
        $response = self::request('GET', $normalizedUrl, null, true);
        if ($response['status'] !== 200 || !str_starts_with($response['body'], "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('Gambar QRIS belum dapat dimuat. Coba muat ulang halaman.');
        }
        return $response['body'];
    }
}
