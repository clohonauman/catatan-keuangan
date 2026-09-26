<?php
/**
 * Maintenance Mode V43
 * Global maintenance setting with plan/user exceptions.
 * Safe fallback: if tables do not exist yet, maintenance is considered OFF.
 */

function maintenanceDefaultSettings(): array
{
    return [
        'is_active' => false,
        'allow_free' => false,
        'allow_premium' => false,
        'title' => 'Mode Maintenance',
        'message' => 'Aplikasi sedang dalam proses pemeliharaan. Silakan coba kembali beberapa saat lagi.',
        'updated_at' => '',
        'updated_by' => null,
        'exception_user_ids' => [],
    ];
}

function maintenanceSnapshot(): array
{
    $default = maintenanceDefaultSettings();
    try {
        $row = Yii::$app->db->createCommand(
            'SELECT id,is_active,allow_free,allow_premium,title,message,updated_by,updated_at FROM maintenance_setting WHERE id=1 LIMIT 1'
        )->queryOne();
        if (!$row) return $default;
        $ids = Yii::$app->db->createCommand('SELECT user_id FROM maintenance_user_exception ORDER BY user_id')->queryColumn();
        return [
            'is_active' => !empty($row['is_active']),
            'allow_free' => !empty($row['allow_free']),
            'allow_premium' => !empty($row['allow_premium']),
            'title' => trim((string)($row['title'] ?? '')) ?: $default['title'],
            'message' => trim((string)($row['message'] ?? '')) ?: $default['message'],
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'updated_by' => isset($row['updated_by']) ? (int)$row['updated_by'] : null,
            'exception_user_ids' => array_values(array_unique(array_map('intval', $ids ?: []))),
        ];
    } catch (Throwable $e) {
        return $default;
    }
}

function maintenanceIsActive(): bool
{
    return !empty(maintenanceSnapshot()['is_active']);
}

function maintenanceCanAccess($user = null, ?array $settings = null): bool
{
    if ($settings === null) $settings = maintenanceSnapshot();
    if (empty($settings['is_active'])) return true;
    if (!$user) return false;
    if (authIsSuperAdmin($user)) return true;

    $uid = (int)($user['id'] ?? 0);
    if ($uid > 0 && in_array($uid, array_map('intval', $settings['exception_user_ids'] ?? []), true)) return true;

    $plan = authPlan($user);
    $premium = !empty($plan['active']);
    if ($premium && !empty($settings['allow_premium'])) return true;
    if (!$premium && !empty($settings['allow_free'])) return true;
    return false;
}

function maintenanceSaveSettings(array $input, array $admin): array
{
    if (!authIsSuperAdmin($admin)) throw new RuntimeException('Khusus Super Admin.');
    $title = trim((string)($input['title'] ?? 'Mode Maintenance'));
    $message = trim((string)($input['message'] ?? ''));
    if ($title === '') $title = 'Mode Maintenance';
    if (mb_strlen($title) > 150) throw new InvalidArgumentException('Judul maintenance maksimal 150 karakter.');
    if ($message === '') $message = 'Aplikasi sedang dalam proses pemeliharaan. Silakan coba kembali beberapa saat lagi.';
    if (mb_strlen($message) > 2000) throw new InvalidArgumentException('Pesan maintenance maksimal 2000 karakter.');

    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($input['exception_user_ids'] ?? [])), fn($v) => $v > 0)));
    $valid = [];
    foreach ((authReadData()['users'] ?? []) as $u) {
        $id = (int)($u['id'] ?? 0);
        if ($id > 0 && in_array($id, $ids, true) && !authIsSuperAdmin($u)) $valid[] = $id;
    }

    $tx = Yii::$app->db->beginTransaction();
    try {
        Yii::$app->db->createCommand()->upsert('maintenance_setting', [
            'id' => 1,
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'allow_free' => !empty($input['allow_free']) ? 1 : 0,
            'allow_premium' => !empty($input['allow_premium']) ? 1 : 0,
            'title' => $title,
            'message' => $message,
            'updated_by' => (int)($admin['id'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ], [
            'is_active' => !empty($input['is_active']) ? 1 : 0,
            'allow_free' => !empty($input['allow_free']) ? 1 : 0,
            'allow_premium' => !empty($input['allow_premium']) ? 1 : 0,
            'title' => $title,
            'message' => $message,
            'updated_by' => (int)($admin['id'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
        Yii::$app->db->createCommand()->delete('maintenance_user_exception')->execute();
        foreach ($valid as $uid) {
            Yii::$app->db->createCommand()->insert('maintenance_user_exception', [
                'user_id' => $uid,
                'created_by' => (int)($admin['id'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }
        $tx->commit();
    } catch (Throwable $e) {
        if ($tx->isActive) $tx->rollBack();
        throw $e;
    }
    return maintenanceSnapshot();
}
