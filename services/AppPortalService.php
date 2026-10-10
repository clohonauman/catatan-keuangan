<?php
namespace app\services;

use Yii;
use app\repositories\DocumentRepository;

/**
 * Persistent app catalog for the single-sign-on application launcher.
 * Catalog data is kept in app_document to avoid introducing a schema migration.
 */
final class AppPortalService
{
    private const NS = 'app_portal';
    private const CATALOG_KEY = 'applications';
    private const USER_NS = 'app_portal_user';

    private static function appBaseUrl(): string
    {
        return rtrim((string)(Yii::$app->params['appUrl'] ?? Yii::$app->request->hostInfo), '/');
    }

    private static function defaults(): array
    {
        return [
            'next_id' => 1,
            'apps' => [
                [
                    'id' => 'main', 'name' => 'Catatan Keuangan', 'icon' => '💰',
                    'description' => 'Transaksi, saldo, dompet dan pengelolaan keuangan pribadi.',
                    'url' => '', 'sso_endpoint' => '', 'sso_secret_enc' => '',
                    'requires_premium' => false, 'enabled' => true, 'builtin' => 'main', 'sort' => 10,
                    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
        ];
    }

    public static function catalog(): array
    {
        $defaults = self::defaults();
        $data = DocumentRepository::mutate(self::NS, self::CATALOG_KEY, $defaults, function (&$data) use ($defaults) {
            if (!isset($data['apps']) || !is_array($data['apps'])) $data['apps'] = [];
            // Migrate away the legacy planning builtin; only Catatan Keuangan is a builtin app.
            $data['apps'] = array_values(array_filter((array)$data['apps'], static function ($existing) {
                if (!is_array($existing)) return false;
                $id = (string)($existing['id'] ?? '');
                $builtin = (string)($existing['builtin'] ?? '');
                return $id !== 'planning' && $builtin !== 'planning' && ($builtin === '' || $id === 'main');
            }));
            foreach ($defaults['apps'] as $builtin) {
                $found = false;
                foreach ($data['apps'] as $i => $existing) {
                    if ((string)($existing['id'] ?? '') === $builtin['id']) {
                        $found = true;
                        // Preserve admin edits, but keep built-in identity and routing locked.
                        $data['apps'][$i]['builtin'] = $builtin['builtin'];
                        $data['apps'][$i]['id'] = $builtin['id'];
                        $data['apps'][$i]['url'] = '';
                        break;
                    }
                }
                if (!$found) $data['apps'][] = $builtin;
            }
            $max = 0;
            foreach ($data['apps'] as $app) $max = max($max, (int)($app['numeric_id'] ?? 0));
            $data['next_id'] = max((int)($data['next_id'] ?? 1), $max + 1);
            return $data;
        });
        $apps = array_values(array_filter((array)($data['apps'] ?? []), 'is_array'));
        usort($apps, static function ($a, $b) {
            $cmp = ((int)($a['sort'] ?? 1000)) <=> ((int)($b['sort'] ?? 1000));
            return $cmp !== 0 ? $cmp : strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $apps;
    }

    public static function find(string $id): ?array
    {
        foreach (self::catalog() as $app) if ((string)($app['id'] ?? '') === $id) return $app;
        return null;
    }

    private static function safeUrl(string $url, string $label, bool $required): string
    {
        $url = trim($url);
        if ($url === '' && !$required) return '';
        if ($url === '') throw new \InvalidArgumentException($label . ' wajib diisi.');
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || str_contains($url, '\\')) throw new \InvalidArgumentException($label . ' tidak valid.');
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) throw new \InvalidArgumentException($label . ' harus berupa URL lengkap.');
        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);
        $localHttp = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !$localHttp) throw new \InvalidArgumentException($label . ' harus menggunakan HTTPS.');
        if (isset($parts['user']) || isset($parts['pass'])) throw new \InvalidArgumentException($label . ' tidak boleh memuat kredensial.');
        return $url;
    }

    private static function encryptedSecret(string $secret): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if (strlen($key) < 32) throw new \RuntimeException('APP_KEY minimal 32 karakter diperlukan untuk menyimpan token SSO aplikasi secara aman.');
        return base64_encode(Yii::$app->security->encryptByKey($secret, $key));
    }

    public static function decryptSecret(array $app): string
    {
        $cipher = (string)($app['sso_secret_enc'] ?? '');
        if ($cipher === '') return '';
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if (strlen($key) < 32) return '';
        $raw = base64_decode($cipher, true);
        if ($raw === false) return '';
        $plain = Yii::$app->security->decryptByKey($raw, $key);
        return is_string($plain) ? $plain : '';
    }

    public static function save(array $input): array
    {
        $id = trim((string)($input['id'] ?? ''));
        $isNew = $id === '';
        if (!$isNew && !preg_match('/^(?:main|app_[a-f0-9]{12})$/', $id)) throw new \InvalidArgumentException('ID aplikasi tidak valid.');
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) > 70) throw new \InvalidArgumentException('Nama aplikasi wajib diisi (maksimal 70 karakter).');
        $description = trim((string)($input['description'] ?? ''));
        if ((function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description)) > 180) throw new \InvalidArgumentException('Deskripsi maksimal 180 karakter.');
        $icon = trim((string)($input['icon'] ?? ''));
        if ($icon === '') $icon = '📱';
        if ((function_exists('mb_strlen') ? mb_strlen($icon, 'UTF-8') : strlen($icon)) > 240 || preg_match('/[<>"\']/', $icon)) throw new \InvalidArgumentException('Icon harus berupa emoji atau URL gambar yang aman.');
        if (preg_match('#^https?://#i', $icon)) $icon = self::safeUrl($icon, 'URL icon', true);
        elseif (str_starts_with($icon, '//')) throw new \InvalidArgumentException('URL icon tidak boleh menggunakan protocol-relative URL.');
        elseif (str_starts_with($icon, '/') && str_starts_with($icon, '//')) throw new \InvalidArgumentException('URL icon tidak valid.');

        $existing = $isNew ? null : self::find($id);
        if (!$isNew && !$existing) throw new \RuntimeException('Aplikasi yang akan diedit tidak ditemukan.');
        $builtin = (string)($existing['builtin'] ?? '');
        $url = $builtin !== '' ? '' : self::safeUrl((string)($input['url'] ?? ''), 'Link aplikasi', true);
        $ssoEndpoint = trim((string)($input['sso_endpoint'] ?? ''));
        if ($ssoEndpoint !== '') $ssoEndpoint = self::safeUrl($ssoEndpoint, 'Endpoint SSO', true);
        elseif ($url !== '') $ssoEndpoint = $url;
        $secretInput = trim((string)($input['sso_secret'] ?? ''));
        $removeSecret = !empty($input['remove_sso_secret']);
        $secretEnc = (string)($existing['sso_secret_enc'] ?? '');
        if ($removeSecret) $secretEnc = '';
        elseif ($secretInput !== '') {
            if (strlen($secretInput) < 32 || strlen($secretInput) > 512) throw new \InvalidArgumentException('Secret SSO harus terdiri dari 32–512 karakter.');
            $secretEnc = self::encryptedSecret($secretInput);
        }
        if ($secretEnc !== '' && $ssoEndpoint === '') throw new \InvalidArgumentException('Endpoint SSO diperlukan jika secret SSO diaktifkan.');

        $now = date('Y-m-d H:i:s');
        if ($isNew) $id = 'app_' . bin2hex(random_bytes(6));
        $app = [
            'id' => $id,
            'name' => $name,
            'icon' => $icon,
            'description' => $description,
            'url' => $url,
            'sso_endpoint' => $ssoEndpoint,
            'sso_secret_enc' => $secretEnc,
            'requires_premium' => !empty($input['requires_premium']),
            'enabled' => !empty($input['enabled']),
            'builtin' => $builtin,
            'sort' => max(1, min(9999, (int)($input['sort'] ?? 100))),
            'created_at' => (string)($existing['created_at'] ?? $now),
            'updated_at' => $now,
        ];
        DocumentRepository::mutate(self::NS, self::CATALOG_KEY, self::defaults(), function (&$data) use ($app, $isNew) {
            if (!isset($data['apps']) || !is_array($data['apps'])) $data['apps'] = [];
            $found = false;
            foreach ($data['apps'] as $i => $current) {
                if ((string)($current['id'] ?? '') === $app['id']) {
                    $data['apps'][$i] = $app;
                    $found = true;
                    break;
                }
            }
            if (!$found && $isNew) $data['apps'][] = $app;
            elseif (!$found) throw new \RuntimeException('Aplikasi tidak ditemukan. Muat ulang daftar aplikasi.');
            return true;
        });
        return $app;
    }

    public static function delete(string $id): void
    {
        if ($id === 'main') throw new \InvalidArgumentException('Aplikasi utama tidak dapat dihapus. Anda dapat menonaktifkannya.');
        DocumentRepository::mutate(self::NS, self::CATALOG_KEY, self::defaults(), function (&$data) use ($id) {
            $before = count((array)($data['apps'] ?? []));
            $data['apps'] = array_values(array_filter((array)($data['apps'] ?? []), static fn($app) => (string)($app['id'] ?? '') !== $id));
            if (count($data['apps']) === $before) throw new \RuntimeException('Aplikasi tidak ditemukan.');
            return true;
        });
    }

    public static function preferredId(int $userId): string
    {
        $data = DocumentRepository::get(self::USER_NS, (string)$userId, []);
        $id = trim((string)($data['preferred_app_id'] ?? ''));
        if ($id === 'planning') { self::clearPreferred($userId); return ''; }
        if ($id !== '') return $id;
        // Compatibility with the existing planning app's preferred_app setting.
        if (function_exists('setting')) {
            try {
                $legacy = (string)setting('preferred_app', '');
                if ($legacy === 'planning') { self::clearPreferred($userId); return ''; }
                if ($legacy === 'main') return $legacy;
            } catch (\Throwable $e) { /* user context may not be bootstrapped */ }
        }
        return '';
    }

    public static function setPreferred(int $userId, string $id): void
    {
        DocumentRepository::put(self::USER_NS, (string)$userId, ['preferred_app_id' => $id, 'updated_at' => date('Y-m-d H:i:s')]);
        if (function_exists('setSetting') && $id === 'main') {
            try { setSetting('preferred_app', $id); } catch (\Throwable $e) { /* separate portal preference is authoritative */ }
        }
    }

    public static function clearPreferred(int $userId): void
    {
        DocumentRepository::put(self::USER_NS, (string)$userId, ['preferred_app_id' => '', 'updated_at' => date('Y-m-d H:i:s')]);
        if (function_exists('setSetting')) {
            try { setSetting('preferred_app', ''); } catch (\Throwable $e) { /* user context may not be bootstrapped */ }
        }
    }

    public static function createSsoToken(array $app, array $user): array
    {
        $secret = self::decryptSecret($app);
        if ($secret === '') throw new \RuntimeException('Konfigurasi SSO aplikasi belum lengkap.');
        $endpoint = (string)($app['sso_endpoint'] ?? $app['url'] ?? '');
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) throw new \RuntimeException('Endpoint SSO aplikasi tidak valid.');
        $aud = strtolower((string)$parts['scheme']) . '://' . strtolower((string)$parts['host']) . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
        $now = time();
        $header = self::b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $payload = self::b64url(json_encode([
            'iss' => self::appBaseUrl(), 'aud' => $aud, 'sub' => (string)(int)($user['id'] ?? 0),
            'email' => (string)($user['email'] ?? ''), 'name' => (string)($user['username'] ?? ''),
            'role' => (string)($user['role'] ?? 'user'), 'premium' => function_exists('authHasPremiumAccess') ? (bool)authHasPremiumAccess($user) : false,
            'iat' => $now, 'exp' => $now + 90, 'jti' => bin2hex(random_bytes(16)),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signingInput = $header . '.' . $payload;
        $signature = self::b64url(hash_hmac('sha256', $signingInput, $secret, true));
        return ['endpoint' => $endpoint, 'token' => $signingInput . '.' . $signature, 'expires_in' => 90];
    }

    private static function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
