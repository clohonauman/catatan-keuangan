<?php
require_once __DIR__.'/../auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $user = authRequireUnlocked();
    $userId = (int)($user['id'] ?? 0);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = $method === 'POST' ? trim((string)($_POST['action'] ?? 'list')) : trim((string)($_GET['action'] ?? 'list'));

    if ($action === 'heartbeat') {
        // Paksa pembaruan last_used_at agar status perangkat lain dapat terlihat hampir realtime.
        authTouchCurrentDevice($userId, true);
        echo json_encode([
            'ok'=>true,
            'heartbeat'=>true,
            'server_time'=>time(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'revoke') {
        if ($method !== 'POST') throw new RuntimeException('Metode request tidak valid.');
        $result = authRevokeDevice($userId, $_POST['device_id'] ?? '');
        if (!empty($result['current'])) {
            authClearLocalSession();
            http_response_code(401);
            echo json_encode([
                'ok'=>false,
                'logged_out'=>true,
                'error'=>'Perangkat ini telah dikeluarkan dari akun.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif ($action === 'revoke_others') {
        if ($method !== 'POST') throw new RuntimeException('Metode request tidak valid.');
        $result = authRevokeOtherDevices($userId);
    } elseif ($action !== 'list') {
        throw new RuntimeException('Aksi perangkat tidak dikenali.');
    }

    // Touch juga pada request daftar supaya perangkat yang sedang melihat halaman keamanan langsung dianggap aktif.
    authTouchCurrentDevice($userId, true);
    $devices = authListDevices($userId);
    $now = time();
    foreach ($devices as &$device) {
        $last = (int)($device['last_used_ts'] ?? 0);
        $device['online'] = !empty($device['current']) || ($last > 0 && ($now - $last) <= 70);
    }
    unset($device);

    echo json_encode([
        'ok'=>true,
        'devices'=>$devices,
        'count'=>count($devices),
        'server_time'=>$now,
        'result'=>$result ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
