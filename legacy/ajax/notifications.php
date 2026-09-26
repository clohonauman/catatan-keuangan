<?php
require_once __DIR__.'/../auth.php';
$user = authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../user_notification_helper.php';

function userNotificationApiFail(string $message, int $status=400): void {
    http_response_code($status);
    echo json_encode(['ok'=>false,'error'=>$message], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $userId = (int)($user['id'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(array_merge(['ok'=>true], userNotificationSnapshot($userId, 100)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $action = strtolower(trim((string)($input['action'] ?? '')));

    if ($action === 'mark_all_read') {
        userNotificationMarkRead($userId);
    } elseif ($action === 'mark_read') {
        $ids = (array)($input['ids'] ?? []);
        userNotificationMarkRead($userId, $ids);
    } elseif ($action === 'create_warning') {
        $key = preg_replace('/[^a-zA-Z0-9_.:-]+/', '_', trim((string)($input['key'] ?? '')));
        $title = trim((string)($input['title'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        $kind = strtolower(trim((string)($input['kind'] ?? 'warning')));
        if ($key === '' || strlen($key) > 120) throw new InvalidArgumentException('Kunci pemberitahuan tidak valid.');
        if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title,'UTF-8') : strlen($title)) > 160) throw new InvalidArgumentException('Judul pemberitahuan tidak valid.');
        if ($message === '' || (function_exists('mb_strlen') ? mb_strlen($message,'UTF-8') : strlen($message)) > 1200) throw new InvalidArgumentException('Isi pemberitahuan tidak valid.');
        if (!in_array($kind, ['warning','info'], true)) $kind = 'warning';
        userNotificationCreate(
            $userId,
            $kind,
            $title,
            $message,
            ['warning_key'=>$key],
            'warning:'.date('Y-m-d').':'.$key
        );
    } else {
        userNotificationApiFail('Aksi pemberitahuan tidak dikenali.', 422);
    }

    echo json_encode(array_merge(['ok'=>true], userNotificationSnapshot($userId, 100)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    userNotificationApiFail($e->getMessage(), 422);
} catch (Throwable $e) {
    userNotificationApiFail($e->getMessage(), 500);
}
