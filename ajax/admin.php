<?php
require_once __DIR__ . '/../auth.php';
$admin = authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!authIsSuperAdmin($admin)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Khusus Super Admin.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../subscription_helper.php';
require_once __DIR__ . '/../user_email_notification_helper.php';

function adminUsersSnapshot(): array
{
    $auth = authReadData();
    $rows = [];
    $totalTx = 0;
    $active = 0;
    $premium = 0;

    foreach ($auth['users'] as $u) {
        $id = (int)$u['id'];
        $file = USER_DATA_DIR . '/user_' . $id . '.json';
        $tx = 0;
        $last = '';
        $balance = null;
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $d = json_decode((string)$raw, true);
            if (is_array($d)) {
                $tx = count($d['transactions'] ?? []);
                $last = @date('Y-m-d H:i:s', filemtime($file));
                $income = 0;
                $expense = 0;
                foreach ($d['transactions'] ?? [] as $t) {
                    if (($t['type'] ?? '') === 'income') $income += (int)$t['amount'];
                    elseif (($t['type'] ?? '') === 'expense') $expense += (int)$t['amount'];
                }
                $initial = (int)($d['settings']['initial_balance'] ?? 0);
                if (isset($d['wallets']) && is_array($d['wallets'])) {
                    $initial = 0;
                    foreach ($d['wallets'] as $w) if (empty($w['archived'])) $initial += (int)($w['initial_balance'] ?? 0);
                }
                $balance = $initial + $income - $expense;
            }
        }
        $totalTx += $tx;
        if ($last && strtotime($last) >= time() - 30 * 86400) $active++;
        $plan = authPlan($u);
        if ($plan['active']) $premium++;
        $rows[] = [
            'id' => $id,
            'username' => $u['username'],
            'email' => (string)($u['email'] ?? ''),
            'email_verified' => trim((string)($u['email_verified_at'] ?? '')) !== '',
            'role' => authUserRole($u),
            'plan' => $plan,
            'premium_type' => (string)($u['premium_type'] ?? ''),
            'premium_last_invoice' => (string)($u['premium_last_invoice'] ?? ''),
            'created_at' => $u['created_at'] ?? '',
            'last_activity' => $last,
            'transactions' => $tx,
            'balance' => $balance,
        ];
    }

    return [
        'users' => $rows,
        'stats' => [
            'users' => count($rows),
            'active_30d' => $active,
            'premium' => $premium,
            'transactions' => $totalTx,
        ],
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];
        $action = (string)($in['action'] ?? '');
        $actionResult = null;

        switch ($action) {
            case 'set_plan':
                $changedUser = authAdminSetUserPlan((int)($in['user_id'] ?? 0), (string)($in['plan'] ?? 'free'), (string)($in['expires_at'] ?? ''));
                try {
                    $planLabel = ((string)($changedUser['plan'] ?? 'free') === 'premium') ? 'Premium' : 'Free';
                    $expiry = trim((string)($changedUser['plan_expires_at'] ?? ''));
                    $msg = 'Status paket akun Anda telah diperbarui oleh admin menjadi ' . $planLabel . '.' . ($expiry !== '' ? ' Masa aktif sampai ' . $expiry . '.' : '');
                    emailNotifyAutomatic((int)$changedUser['id'], 'admin_plan_' . date('YmdHis'), 'premium', 'Status Paket Akun Diperbarui', $msg, ['action_url' => 'https://catatan-keuangan.cloud/', 'action_label' => 'Buka Catatan Keuangan']);
                } catch (Throwable $mailError) { /* perubahan paket tetap berhasil walau email gagal */
                }
                break;
            case 'subscription_approve':
                subscriptionApproveOrder((int)($in['order_id'] ?? 0), $admin);
                break;
            case 'subscription_reject':
                subscriptionRejectOrder((int)($in['order_id'] ?? 0), $admin, (string)($in['reason'] ?? ''));
                break;
            case 'coupon_save':
                subscriptionAdminSaveCoupon((array)($in['coupon'] ?? []));
                break;
            case 'coupon_delete':
                subscriptionAdminDeleteCoupon((int)($in['coupon_id'] ?? 0));
                break;
            case 'bank_save':
                subscriptionAdminSaveBank((array)($in['bank'] ?? []));
                break;
            case 'bank_delete':
                subscriptionAdminDeleteBank((string)($in['bank_id'] ?? ''));
                break;
            case 'email_broadcast_create':
                $actionResult = emailBroadcastCreate($admin, (array)($in['broadcast'] ?? []));
                break;
            case 'email_broadcast_process':
                $actionResult = emailBroadcastProcess((int)($in['campaign_id'] ?? 0), (int)($in['batch_size'] ?? 5));
                break;
            case 'email_broadcast_retry':
                $actionResult = emailBroadcastRetryFailed((int)($in['campaign_id'] ?? 0));
                break;
            case 'email_broadcast_test':
                $actionResult = emailBroadcastSendTest($admin, (array)($in['broadcast'] ?? []));
                break;
            default:
                throw new InvalidArgumentException('Aksi admin tidak dikenal.');
        }
    }

    $users = adminUsersSnapshot();
    echo json_encode([
        'ok' => true,
        'users' => $users['users'],
        'stats' => $users['stats'],
        'subscriptions' => subscriptionAdminSnapshot(),
        'email_broadcasts' => emailBroadcastSnapshot(),
        'action_result' => $actionResult ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}