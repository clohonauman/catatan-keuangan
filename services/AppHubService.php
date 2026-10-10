<?php
namespace app\services;

use Yii;
use yii\db\Query;
use yii\helpers\Url;

final class AppHubService
{
    private static function db() { return Yii::$app->db; }
    private static function now(): string { return date('Y-m-d H:i:s'); }
    private static function baseUrl(): string { return rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/'); }

    public static function ensureBuiltins(): void
    {
        $base = self::baseUrl();
        $now = self::now();
        $builtins = [
            [
                'slug' => 'main', 'name' => 'Catatan Keuangan',
                'description' => 'Pencatatan transaksi, saldo, dompet, tagihan, piutang, analitik, backup, dan pengaturan akun.',
                'icon' => '💰', 'app_url' => $base . '/index.php?app=main', 'callback_url' => null,
                'premium_required' => 0, 'is_active' => 1, 'sort_order' => 10,
                'is_system' => 1, 'internal_key' => 'main', 'client_id' => null, 'client_secret_hash' => null,
            ],
            [
                'slug' => 'planning', 'name' => 'Perencanaan Keuangan',
                'description' => 'Rencana belanja berdasarkan riwayat pengeluaran, pemasukan, tagihan, cicilan, dan transaksi berulang.',
                'icon' => '📊', 'app_url' => $base . '/index.php?r=planning/index', 'callback_url' => null,
                'premium_required' => 1, 'is_active' => 1, 'sort_order' => 20,
                'is_system' => 1, 'internal_key' => 'planning', 'client_id' => null, 'client_secret_hash' => null,
            ],
        ];
        foreach ($builtins as $app) {
            $existing = (new Query())->from('{{%app_catalog}}')->where(['slug' => $app['slug']])->one();
            if ($existing) continue;
            try {
                self::db()->createCommand()->insert('{{%app_catalog}}', $app + ['created_at' => $now, 'updated_at' => $now])->execute();
            } catch (\yii\db\IntegrityException $e) {
                // Request lain mungkin memasukkan aplikasi bawaan yang sama secara bersamaan.
            }
        }
    }

    public static function all(bool $includeInactive = false): array
    {
        self::ensureBuiltins();
        $q = (new Query())->from('{{%app_catalog}}')->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
        if (!$includeInactive) $q->andWhere(['is_active' => 1]);
        return array_map([self::class, 'publicApp'], $q->all());
    }

    public static function findBySlug(string $slug): ?array
    {
        self::ensureBuiltins();
        $row = (new Query())->from('{{%app_catalog}}')->where(['slug' => trim($slug)])->one();
        return $row ? self::publicApp($row) : null;
    }

    private static function publicApp(array $row): array
    {
        unset($row['client_secret_hash']);
        foreach (['id','premium_required','is_active','sort_order','is_system'] as $k) if (isset($row[$k])) $row[$k] = (int)$row[$k];
        $row['sso_enabled'] = !empty($row['callback_url']) && !empty($row['client_id']);
        return $row;
    }

    public static function saveExternalApp(?int $id, array $input): array
    {
        self::ensureBuiltins();
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $icon = trim((string)($input['icon'] ?? '📱'));
        $appUrl = trim((string)($input['app_url'] ?? ''));
        $callbackUrl = trim((string)($input['callback_url'] ?? ''));
        $premium = !empty($input['premium_required']) ? 1 : 0;
        $active = !empty($input['is_active']) ? 1 : 0;
        $sortOrder = max(1, min(9999, (int)($input['sort_order'] ?? 100)));

        if ($name === '' || (function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 120) throw new \InvalidArgumentException('Nama aplikasi wajib diisi (maksimal 120 karakter).');
        if ((function_exists('mb_strlen') ? mb_strlen($description) : strlen($description)) > 4000) throw new \InvalidArgumentException('Deskripsi aplikasi terlalu panjang.');
        if ($icon === '') $icon = '📱';
        if ((function_exists('mb_strlen') ? mb_strlen($icon) : strlen($icon)) > 500) throw new \InvalidArgumentException('Icon terlalu panjang. Gunakan emoji atau URL gambar HTTPS.');
        if (preg_match('~^https://~i', $icon)) self::validateUrl($icon, 'URL icon');
        elseif (preg_match('~^[a-z][a-z0-9+.-]*:~i', $icon)) throw new \InvalidArgumentException('Icon hanya boleh berupa emoji/teks atau URL gambar HTTPS.');
        self::validateUrl($appUrl, 'URL aplikasi');
        if ($callbackUrl !== '') self::validateUrl($callbackUrl, 'URL callback SSO');
        if ($premium && $callbackUrl === '') throw new \InvalidArgumentException('Aplikasi yang wajib Premium harus menggunakan Callback SSO agar hak akses dapat diverifikasi di server aplikasi tujuan.');

        $now = self::now();
        if ($id) {
            $existing = (new Query())->from('{{%app_catalog}}')->where(['id' => $id])->one();
            if (!$existing) throw new \RuntimeException('Aplikasi tidak ditemukan.');
            if ((int)$existing['is_system'] === 1) throw new \RuntimeException('Aplikasi bawaan tidak dapat diubah dari CRUD eksternal.');
            self::db()->createCommand()->update('{{%app_catalog}}', [
                'name' => $name, 'description' => $description, 'icon' => $icon,
                'app_url' => $appUrl, 'callback_url' => ($callbackUrl !== '' ? $callbackUrl : null),
                'premium_required' => $premium, 'is_active' => $active, 'sort_order' => $sortOrder,
                'updated_at' => $now,
            ], ['id' => $id])->execute();
            return ['id' => $id, 'client_id' => $existing['client_id'] ?? '', 'client_secret' => null, 'secret_created' => false];
        }

        $slugBase = self::slugify($name);
        $slug = $slugBase;
        for ($i = 2; (new Query())->from('{{%app_catalog}}')->where(['slug' => $slug])->exists(); $i++) $slug = substr($slugBase, 0, 72) . '-' . $i;
        $clientId = 'ckapp_' . bin2hex(random_bytes(12));
        $clientSecret = self::base64UrlEncode(random_bytes(36));
        self::db()->createCommand()->insert('{{%app_catalog}}', [
            'slug' => $slug, 'name' => $name, 'description' => $description, 'icon' => $icon,
            'app_url' => $appUrl, 'callback_url' => ($callbackUrl !== '' ? $callbackUrl : null),
            'premium_required' => $premium, 'is_active' => $active, 'sort_order' => $sortOrder,
            'is_system' => 0, 'internal_key' => null, 'client_id' => $clientId,
            'client_secret_hash' => password_hash($clientSecret, PASSWORD_DEFAULT),
            'created_at' => $now, 'updated_at' => $now,
        ])->execute();
        return ['id' => (int)self::db()->getLastInsertID(), 'slug' => $slug, 'client_id' => $clientId, 'client_secret' => $clientSecret, 'secret_created' => true];
    }

    public static function regenerateSecret(int $id): array
    {
        $row = (new Query())->from('{{%app_catalog}}')->where(['id' => $id])->one();
        if (!$row || (int)$row['is_system'] === 1) throw new \RuntimeException('Aplikasi eksternal tidak ditemukan.');
        $clientId = (string)($row['client_id'] ?: ('ckapp_' . bin2hex(random_bytes(12))));
        $secret = self::base64UrlEncode(random_bytes(36));
        self::db()->createCommand()->update('{{%app_catalog}}', [
            'client_id' => $clientId,
            'client_secret_hash' => password_hash($secret, PASSWORD_DEFAULT),
            'updated_at' => self::now(),
        ], ['id' => $id])->execute();
        return ['id' => $id, 'app_name' => $row['name'], 'client_id' => $clientId, 'client_secret' => $secret, 'secret_created' => true];
    }

    public static function deleteExternalApp(int $id): void
    {
        $row = (new Query())->from('{{%app_catalog}}')->where(['id' => $id])->one();
        if (!$row) throw new \RuntimeException('Aplikasi tidak ditemukan.');
        if ((int)$row['is_system'] === 1) throw new \RuntimeException('Aplikasi bawaan tidak dapat dihapus.');
        self::db()->createCommand()->delete('{{%app_catalog}}', ['id' => $id])->execute();
    }

    public static function launch(string $slug, array $user): string
    {
        self::ensureBuiltins();
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        $app = (new Query())->from('{{%app_catalog}}')->where(['slug' => trim($slug), 'is_active' => 1])->one();
        if (!$app) throw new \RuntimeException('Aplikasi tidak tersedia atau sudah dinonaktifkan.');
        if (!empty($app['premium_required']) && !authIsSuperAdmin($user) && !authHasPremiumAccess($user)) {
            throw new \RuntimeException('Aplikasi ini memerlukan akses Premium. Silakan aktifkan Premium terlebih dahulu.');
        }

        setSetting('preferred_app', (string)$app['slug']);
        unset($_SESSION['force_app_list']);

        if (($app['internal_key'] ?? '') === 'main') return Url::to(['site/index', 'app' => 'main']);
        if (($app['internal_key'] ?? '') === 'planning') return Url::to(['planning/index']);

        if (empty($app['callback_url'])) return (string)$app['app_url'];
        if (empty($app['client_id']) || empty($app['client_secret_hash'])) throw new \RuntimeException('SSO aplikasi ini belum dikonfigurasi. Buka Manajemen Aplikasi untuk menyiapkan kredensial.');
        // Aplikasi tujuan memulai authorization flow dari endpoint start miliknya agar state
        // dibuat dan disimpan pada sesi browser aplikasi tujuan (mencegah login-CSRF).
        return (string)$app['app_url'];
    }

    public static function authorizeBrowserRequest(string $clientId, string $redirectUri, string $state, array $user): string
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $clientId = trim($clientId); $redirectUri = trim($redirectUri); $state = trim($state);
        if ($clientId === '' || $redirectUri === '' || !preg_match('/^[A-Za-z0-9._~-]{16,256}$/', $state)) {
            throw new \InvalidArgumentException('Permintaan SSO tidak valid. Silakan mulai dari aplikasi tujuan.');
        }
        $app = (new Query())->from('{{%app_catalog}}')->where(['client_id' => $clientId, 'is_active' => 1])->one();
        if (!$app || empty($app['callback_url']) || empty($app['client_secret_hash']) || !hash_equals((string)$app['callback_url'], $redirectUri)) {
            throw new \InvalidArgumentException('Aplikasi atau URL callback SSO tidak terdaftar.');
        }
        if (!empty($app['premium_required']) && !authIsSuperAdmin($user) && !authHasPremiumAccess($user)) {
            throw new \InvalidArgumentException('Aplikasi ini memerlukan akses Premium yang aktif.');
        }
        require_once Yii::getAlias('@app/legacy/maintenance_helper.php');
        $maintenance = maintenanceSnapshot();
        if (!empty($maintenance['is_active']) && !maintenanceCanAccess($user, $maintenance)) {
            throw new \InvalidArgumentException('Akses aplikasi sementara dibatasi oleh mode maintenance.');
        }

        $code = self::base64UrlEncode(random_bytes(32));
        $now = self::now();
        self::db()->createCommand()->insert('{{%app_sso_code}}', [
            'app_id' => (int)$app['id'], 'user_id' => (int)$user['id'],
            'code_hash' => hash('sha256', $code), 'state_hash' => hash('sha256', $state),
            'expires_at' => date('Y-m-d H:i:s', time() + 90), 'used_at' => null, 'created_at' => $now,
        ])->execute();
        self::db()->createCommand()->delete('{{%app_sso_code}}', ['<', 'expires_at', date('Y-m-d H:i:s', time() - 86400)]);
        $separator = strpos($redirectUri, '?') === false ? '?' : '&';
        return $redirectUri . $separator . http_build_query(['code' => $code, 'state' => $state], '', '&', PHP_QUERY_RFC3986);
    }

    public static function exchangeCode(string $clientId, string $clientSecret, string $code, string $state): array
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $clientId = trim($clientId); $clientSecret = trim($clientSecret); $code = trim($code); $state = trim($state);
        if ($clientId === '' || $clientSecret === '' || $code === '' || $state === '') throw new \InvalidArgumentException('Kredensial aplikasi atau kode SSO tidak lengkap.');
        $app = (new Query())->from('{{%app_catalog}}')->where(['client_id' => $clientId, 'is_active' => 1])->one();
        if (!$app || empty($app['callback_url']) || empty($app['client_secret_hash'])) {
            throw new \InvalidArgumentException('Kredensial aplikasi tidak valid.');
        }

        $db = self::db(); $tx = $db->beginTransaction();
        try {
            // Kunci baris aplikasi dan verifikasi secret terbaru di dalam transaksi agar regenerasi secret tidak berlomba dengan exchange.
            $app = $db->createCommand('SELECT * FROM {{%app_catalog}} WHERE [[id]] = :id FOR UPDATE', [':id' => (int)$app['id']])->queryOne();
            if (!$app || empty($app['is_active']) || (string)$app['client_id'] !== $clientId || empty($app['callback_url']) || empty($app['client_secret_hash']) || !password_verify($clientSecret, (string)$app['client_secret_hash'])) {
                throw new \InvalidArgumentException('Kredensial aplikasi tidak valid.');
            }
            $row = $db->createCommand('SELECT * FROM {{%app_sso_code}} WHERE [[code_hash]] = :hash FOR UPDATE', [':hash' => hash('sha256', $code)])->queryOne();
            if (!$row || (int)$row['app_id'] !== (int)$app['id'] || !empty($row['used_at']) || strtotime((string)$row['expires_at']) <= time() || !hash_equals((string)$row['state_hash'], hash('sha256', $state))) {
                throw new \InvalidArgumentException('Kode SSO tidak valid, telah digunakan, atau kedaluwarsa. Silakan buka aplikasi dari portal kembali.');
            }
            $userRow = (new Query())->from('{{%user}}')->where(['id' => (int)$row['user_id']])->one();
            if (!$userRow) throw new \InvalidArgumentException('Akun portal tidak ditemukan.');
            $user = AuthRepository::findById((int)$row['user_id']);
            if (!$user) throw new \InvalidArgumentException('Akun portal tidak ditemukan.');
            require_once Yii::getAlias('@app/legacy/maintenance_helper.php');
            $maintenance = maintenanceSnapshot();
            if (!empty($maintenance['is_active']) && !maintenanceCanAccess($user, $maintenance)) {
                throw new \InvalidArgumentException('Akses aplikasi sementara dibatasi oleh mode maintenance.');
            }
            if (!empty($app['premium_required']) && !authIsSuperAdmin($user) && !authHasPremiumAccess($user)) {
                throw new \InvalidArgumentException('Akses Premium pengguna tidak aktif.');
            }
            $db->createCommand()->update('{{%app_sso_code}}', ['used_at' => self::now()], ['id' => (int)$row['id']])->execute();
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) $tx->rollBack();
            throw $e;
        }

        $plan = authPlan($user);
        return [
            'ok' => true,
            'issuer' => self::baseUrl(),
            'app' => ['slug' => (string)$app['slug'], 'name' => (string)$app['name'], 'launch_url' => (string)$app['app_url']],
            'user' => [
                'id' => (int)$user['id'], 'username' => (string)$user['username'],
                'email' => (string)($user['email'] ?? ''), 'role' => (string)($user['role'] ?? 'user'),
                'premium' => authIsSuperAdmin($user) || !empty($plan['active']),
                'premium_expires_at' => (string)($plan['expires_at'] ?? ''),
            ],
            'authenticated_at' => gmdate('c'),
        ];
    }

    public static function isPremium(array $user): bool
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        return authIsSuperAdmin($user) || authHasPremiumAccess($user);
    }

    private static function validateUrl(string $url, string $label): void
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host']) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException($label . ' harus berupa URL lengkap.');
        }
        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);
        $localDev = YII_ENV_DEV && in_array($host, ['localhost', '127.0.0.1', '::1'], true) && $scheme === 'http';
        if ($scheme !== 'https' && !$localDev) throw new \InvalidArgumentException($label . ' wajib menggunakan HTTPS.');
        if (isset($parts['user']) || isset($parts['pass'])) throw new \InvalidArgumentException($label . ' tidak boleh berisi username/password URL.');
        if (isset($parts['fragment'])) throw new \InvalidArgumentException($label . ' tidak boleh berisi fragment (#).');
        if (preg_match('/[\r\n]/', $url)) throw new \InvalidArgumentException($label . ' tidak valid.');
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'app';
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'app';
        return substr($slug, 0, 80);
    }

    private static function base64UrlEncode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
}
