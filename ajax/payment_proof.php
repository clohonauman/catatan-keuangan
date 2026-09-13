<?php
require_once __DIR__.'/../auth.php';
$user = authRequireUnlocked();
require_once __DIR__.'/../subscription_helper.php';

try {
    $orderId = (int)($_GET['order_id'] ?? 0);
    $order = subscriptionFindOrder($orderId);
    if (!$order) throw new RuntimeException('Bukti pembayaran tidak ditemukan.');
    if (!authIsSuperAdmin($user) && (int)($order['user_id'] ?? 0) !== (int)$user['id']) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $file = basename((string)($order['proof']['file'] ?? ''));
    if ($file === '') throw new RuntimeException('Bukti pembayaran belum diunggah.');
    $path = PAYMENT_PROOF_DIR.'/'.$file;
    if (!is_file($path)) throw new RuntimeException('File bukti pembayaran tidak ditemukan.');
    $mime = (string)($order['proof']['mime'] ?? 'image/jpeg');
    if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) $mime = 'image/jpeg';
    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($path));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
}
