<?php
require_once __DIR__.'/../auth.php';
$admin = authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!authIsSuperAdmin($admin)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Khusus Super Admin.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__.'/../admin_notification_helper.php';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];
        $action = (string)($in['action'] ?? '');
        if ($action === 'mark_read') {
            $ids = isset($in['ids']) && is_array($in['ids']) ? $in['ids'] : [];
            adminNotificationMarkRead((int)$admin['id'], $ids);
        } elseif ($action === 'mark_all_read') {
            adminNotificationMarkRead((int)$admin['id']);
        } else {
            throw new InvalidArgumentException('Aksi notifikasi admin tidak dikenali.');
        }
    }
    echo json_encode([
        'ok'=>true,
        'unread_count'=>adminNotificationUnreadCount((int)$admin['id']),
        'notifications'=>adminNotificationListForUser((int)$admin['id'], 30),
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
