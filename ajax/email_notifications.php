<?php
require_once __DIR__ . '/../auth.php';
$user = authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../finance_features.php';
require_once __DIR__ . '/../user_email_notification_helper.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new InvalidArgumentException('Metode tidak diizinkan.');
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) $in = [];
    $settings = financeNotificationSettings();
    if (empty($settings['email_enabled'])) {
        echo json_encode(['ok' => true, 'skipped' => true, 'reason' => 'Notifikasi email dinonaktifkan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $key = trim((string)($in['key'] ?? ''));
    $kind = strtolower(trim((string)($in['kind'] ?? 'info')));
    $title = trim((string)($in['title'] ?? ''));
    $message = trim((string)($in['message'] ?? ''));
    $allowed = ['budget', 'bill', 'low_balance', 'info'];
    if (!in_array($kind, $allowed, true)) throw new InvalidArgumentException('Jenis notifikasi tidak valid.');
    if ($key === '' || strlen($key) > 120) throw new InvalidArgumentException('Kunci notifikasi tidak valid.');
    if ($title === '' || emailNotifyTextLen($title) > 120) throw new InvalidArgumentException('Judul notifikasi tidak valid.');
    if ($message === '' || emailNotifyTextLen($message) > 800) throw new InvalidArgumentException('Isi notifikasi tidak valid.');

    $result = emailNotifyAutomatic((int)$user['id'], $key, $kind, $title, $message, [
        'action_url' => 'https://catatan-keuangan.cloud/',
        'action_label' => 'Buka Catatan Keuangan',
        'verified_only' => true,
    ]);
    echo json_encode(array_merge(['ok' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}