<?php
namespace app\repositories;

use Yii;

/**
 * Application catalogue and per-user last-app preference.
 * Uses the existing app_document table so no schema migration is required.
 */
final class ApplicationHubRepository
{
    private const CATALOG_NS = 'application_hub';
    private const CATALOG_KEY = 'catalog_v1';
    private const PREF_NS = 'application_hub_preferences';

    public static function builtin(): array
    {
        return [
            'id' => 'main',
            'name' => 'Catatan Keuangan',
            'description' => 'Pencatatan transaksi, saldo, dompet, tagihan, piutang, analitik, backup, dan pengaturan akun.',
            'icon' => '💰',
            'url' => 'index.php?app=main',
            'premium_required' => false,
            'enabled' => true,
            'sso_endpoint' => '',
            'is_builtin' => true,
            'kind' => 'builtin',
        ];
    }

    /** Hide stale records created by the old built-in Planning app. A genuine
     * external app with the same display name on another host remains allowed. */
    private static function isLegacyPlanningApp(array $app): bool
    {
        $id = strtolower(trim((string)($app['id'] ?? '')));
        $builtin = strtolower(trim((string)($app['builtin'] ?? $app['kind'] ?? '')));
        if ($id === 'planning' || $builtin === 'planning') return true;
        $name = trim((string)($app['name'] ?? ''));
        if (function_exists('mb_strtolower')) $name = mb_strtolower($name, 'UTF-8');
        else $name = strtolower($name);
        if ($name !== 'perencanaan keuangan') return false;
        $url = trim((string)($app['url'] ?? ''));
        if ($url === '') return true;
        $normalizedUrl = strtolower(rawurldecode($url));
        if (str_contains($normalizedUrl, 'planning/index') || str_contains($normalizedUrl, 'app=planning') || str_contains($normalizedUrl, 'perencanaan-pengeluaran')) return true;
        $parts = parse_url($url);
        if (!is_array($parts)) return false;
        $host = strtolower((string)($parts['host'] ?? ''));
        $portalHost = '';
        try { $portalHost = strtolower((string)parse_url(Yii::$app->request->hostInfo, PHP_URL_HOST)); } catch (\Throwable $e) {}
        $path = strtolower((string)($parts['path'] ?? ''));
        $query = strtolower(rawurldecode((string)($parts['query'] ?? '')));
        $isPortalHost = $host !== '' && $portalHost !== '' && hash_equals($portalHost, $host);
        return $isPortalHost && (
            str_contains($path, 'perencanaan-pengeluaran') ||
            str_contains($query, 'planning/index') ||
            str_contains($query, 'app=planning')
        );
    }

    public static function externalApps(bool $includeDisabled = false): array
    {
        $document = DocumentRepository::get(self::CATALOG_NS, self::CATALOG_KEY, ['apps' => []]);
        $apps = is_array($document['apps'] ?? null) ? $document['apps'] : [];
        $out = [];
        foreach ($apps as $app) {
            if (!is_array($app) || empty($app['id'])) continue;
            if (self::isLegacyPlanningApp($app)) continue;
            if (!$includeDisabled && empty($app['enabled'])) continue;
            $app['is_builtin'] = false;
            $app['kind'] = 'external';
            $app['premium_required'] = !empty($app['premium_required']);
            $app['enabled'] = !empty($app['enabled']);
            $app['has_sso_secret'] = !empty($app['sso_secret_enc']);
            unset($app['sso_secret_enc']);
            $out[] = $app;
        }
        usort($out, static function (array $a, array $b): int {
            $bySort = ((int)($a['sort_order'] ?? 100)) <=> ((int)($b['sort_order'] ?? 100));
            return $bySort !== 0 ? $bySort : strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $out;
    }

    public static function appsForUser(bool $includeDisabled = false): array
    {
        return array_merge([self::builtin()], self::externalApps($includeDisabled));
    }

    public static function findExternal(string $id, bool $includeDisabled = false): ?array
    {
        $id = trim($id);
        if ($id === '' || $id === 'main') return null;
        foreach (self::externalApps($includeDisabled) as $app) {
            if (hash_equals((string)$app['id'], $id)) return $app;
        }
        return null;
    }

    private static function findRawExternal(string $id): ?array
    {
        $document = DocumentRepository::get(self::CATALOG_NS, self::CATALOG_KEY, ['apps' => []]);
        foreach ((array)($document['apps'] ?? []) as $app) {
            if (is_array($app) && (string)($app['id'] ?? '') === $id) return $app;
        }
        return null;
    }

    public static function preferredAppId(int $userId): string
    {
        if ($userId <= 0) return '';
        $preference = DocumentRepository::get(self::PREF_NS, (string)$userId, []);
        return trim((string)($preference['last_app_id'] ?? ''));
    }

    public static function setPreferredAppId(int $userId, string $appId): void
    {
        if ($userId <= 0) throw new \RuntimeException('Akun tidak valid.');
        DocumentRepository::put(self::PREF_NS, (string)$userId, [
            'last_app_id' => $appId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function saveExternal(array $input): array
    {
        $id = trim((string)($input['id'] ?? ''));
        $oldRaw = $id !== '' ? self::findRawExternal($id) : null;
        if ($id !== '' && !$oldRaw) throw new \RuntimeException('Aplikasi yang akan diubah tidak ditemukan.');
        if ($id === '') $id = 'app_' . bin2hex(random_bytes(7));

        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $icon = trim((string)($input['icon'] ?? '🧩'));
        $url = self::normalizeHttpsUrl((string)($input['url'] ?? ''), 'URL aplikasi');
        $premiumRequired = !empty($input['premium_required']);
        $enabled = !empty($input['enabled']);
        $ssoEndpointRaw = trim((string)($input['sso_endpoint'] ?? ''));
        $ssoEndpoint = $ssoEndpointRaw === '' ? '' : self::normalizeHttpsUrl($ssoEndpointRaw, 'Endpoint SSO');
        $newSecret = trim((string)($input['sso_secret'] ?? ''));
        $oldSecretEnc = (string)($oldRaw['sso_secret_enc'] ?? '');

        if ($name === '' || strlen($name) > 80) throw new \RuntimeException('Nama aplikasi wajib diisi dan maksimal 80 karakter.');
        if (strlen($description) > 500) throw new \RuntimeException('Deskripsi maksimal 500 karakter.');
        if ($icon === '' || strlen($icon) > 180) throw new \RuntimeException('Ikon aplikasi wajib diisi dan maksimal 180 karakter.');
        if (preg_match('/^https?:\/\//i', $icon)) self::normalizeHttpsUrl($icon, 'URL ikon');

        if ($ssoEndpoint !== '' && $newSecret === '' && $oldSecretEnc === '') {
            throw new \RuntimeException('Isi kunci rahasia SSO jika endpoint SSO diaktifkan.');
        }
        if ($newSecret !== '' && $ssoEndpoint === '') {
            throw new \RuntimeException('Isi endpoint SSO HTTPS agar kunci rahasia tidak tersimpan tanpa penggunaan.');
        }
        $secretEnc = $oldSecretEnc;
        if ($newSecret !== '') {
            if (strlen($newSecret) < 24) throw new \RuntimeException('Kunci SSO minimal 24 karakter acak.');
            $secretEnc = self::encryptSecret($newSecret);
        }

        $row = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'url' => $url,
            'premium_required' => $premiumRequired,
            'enabled' => $enabled,
            'sso_endpoint' => $ssoEndpoint,
            'sso_secret_enc' => $secretEnc,
            'sort_order' => max(0, min(10000, (int)($input['sort_order'] ?? 100))),
            'created_at' => (string)($oldRaw['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        DocumentRepository::mutate(self::CATALOG_NS, self::CATALOG_KEY, ['apps' => []], static function (&$document) use ($row) {
            if (!isset($document['apps']) || !is_array($document['apps'])) $document['apps'] = [];
            $replaced = false;
            foreach ($document['apps'] as $index => $existing) {
                if (is_array($existing) && (string)($existing['id'] ?? '') === $row['id']) {
                    $document['apps'][$index] = $row;
                    $replaced = true;
                    break;
                }
            }
            if (!$replaced) $document['apps'][] = $row;
            return true;
        });

        $safe = $row;
        $safe['is_builtin'] = false;
        $safe['kind'] = 'external';
        $safe['has_sso_secret'] = $secretEnc !== '';
        unset($safe['sso_secret_enc']);
        return $safe;
    }

    public static function setEnabled(string $id, bool $enabled): void
    {
        if ($id === '' || $id === 'main') throw new \RuntimeException('Aplikasi bawaan tidak dapat diubah dari menu ini.');
        DocumentRepository::mutate(self::CATALOG_NS, self::CATALOG_KEY, ['apps' => []], static function (&$document) use ($id, $enabled) {
            foreach ((array)($document['apps'] ?? []) as $index => $app) {
                if (is_array($app) && (string)($app['id'] ?? '') === $id) {
                    $document['apps'][$index]['enabled'] = $enabled;
                    $document['apps'][$index]['updated_at'] = date('Y-m-d H:i:s');
                    return true;
                }
            }
            throw new \RuntimeException('Aplikasi tidak ditemukan.');
        });
    }

    public static function deleteExternal(string $id): void
    {
        if ($id === '' || $id === 'main') throw new \RuntimeException('Aplikasi bawaan tidak dapat dihapus.');
        DocumentRepository::mutate(self::CATALOG_NS, self::CATALOG_KEY, ['apps' => []], static function (&$document) use ($id) {
            $before = count((array)($document['apps'] ?? []));
            $document['apps'] = array_values(array_filter((array)($document['apps'] ?? []), static function ($app) use ($id) {
                return !is_array($app) || (string)($app['id'] ?? '') !== $id;
            }));
            if (count($document['apps']) === $before) throw new \RuntimeException('Aplikasi tidak ditemukan.');
            return true;
        });
    }

    public static function getSsoSecret(string $id): string
    {
        $raw = self::findRawExternal($id);
        if (!$raw || empty($raw['sso_secret_enc'])) return '';
        return self::decryptSecret((string)$raw['sso_secret_enc']);
    }

    private static function normalizeHttpsUrl(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException($label . ' tidak valid. Gunakan URL HTTPS lengkap.');
        }
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || strtolower((string)$parts['scheme']) !== 'https' || empty($parts['host'])) {
            // HTTP hanya dibolehkan di development untuk localhost.
            $allowLocalHttp = defined('YII_ENV') && YII_ENV !== 'prod' && is_array($parts)
                && strtolower((string)($parts['scheme'] ?? '')) === 'http'
                && in_array(strtolower((string)($parts['host'] ?? '')), ['localhost', '127.0.0.1'], true);
            if (!$allowLocalHttp) throw new \RuntimeException($label . ' wajib menggunakan HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) throw new \RuntimeException($label . ' tidak boleh berisi username/password di URL.');
        return $value;
    }

    private static function cryptoKey(): string
    {
        $appKey = trim((string)($_ENV['APP_KEY'] ?? ''));
        if (strlen($appKey) < 32 || $appKey === 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET') {
            throw new \RuntimeException('APP_KEY production harus dikonfigurasi sebelum menyimpan kunci SSO.');
        }
        return hash('sha256', $appKey . '|application-hub-sso-v1', true);
    }

    private static function encryptSecret(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) throw new \RuntimeException('OpenSSL PHP diperlukan untuk mengenkripsi kunci SSO.');
        $key = self::cryptoKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'application-hub-sso-v1', 16);
        if (!is_string($ciphertext) || $ciphertext === '') throw new \RuntimeException('Kunci SSO tidak dapat dienkripsi.');
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private static function decryptSecret(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:') || !function_exists('openssl_decrypt')) {
            throw new \RuntimeException('Format kunci SSO tidak didukung.');
        }
        $raw = base64_decode(substr($encrypted, 3), true);
        if (!is_string($raw) || strlen($raw) < 29) throw new \RuntimeException('Kunci SSO tersimpan dalam format tidak valid.');
        $key = self::cryptoKey();
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, 'application-hub-sso-v1');
        if (!is_string($plain)) throw new \RuntimeException('Kunci SSO tidak dapat dibuka. Pastikan APP_KEY masih sama seperti saat kunci disimpan.');
        return $plain;
    }
}
