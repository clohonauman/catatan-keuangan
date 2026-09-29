<?php
use app\repositories\FinanceRepository;

/**
 * Preferensi notifikasi per pengguna.
 * Disimpan bersama settings finance user sehingga tidak membutuhkan tabel/migration baru.
 */
function userNotificationPreferenceDefaults(): array
{
    return [
        'enabled' => false,
        'email_enabled' => false,
    ];
}

function userNotificationPreferenceForUser(int $userId): array
{
    if ($userId <= 0) return userNotificationPreferenceDefaults();
    try {
        $data = FinanceRepository::read($userId);
        $raw = is_array($data['settings']['notifications'] ?? null)
            ? $data['settings']['notifications']
            : [];
        return [
            'enabled' => !empty($raw['enabled']),
            'email_enabled' => !empty($raw['email_enabled']),
        ];
    } catch (Throwable $e) {
        return userNotificationPreferenceDefaults();
    }
}

function userNotificationEmailEnabledForUser(int $userId): bool
{
    $pref = userNotificationPreferenceForUser($userId);
    return !empty($pref['enabled']) && !empty($pref['email_enabled']);
}
