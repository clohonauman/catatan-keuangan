<?php
require_once __DIR__ . '/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../db.php';

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit <= 0) $limit = 10;
    $limit = min(50, max(1, $limit));

    $all = array_values((array)(readData()['chats'] ?? []));
    $total = count($all);

    // page=1 = 10 pesan TERBARU. Batch tetap dikirim urut lama -> baru
    // agar tampilan chat natural. page=2 memuat 10 pesan sebelum batch terbaru.
    $endExclusive = max(0, $total - (($page - 1) * $limit));
    $start = max(0, $endExclusive - $limit);
    $length = max(0, $endExclusive - $start);
    $items = $length > 0 ? array_slice($all, $start, $length) : [];

    echo json_encode([
        'ok'=>true,
        'chats'=>$items,
        'meta'=>[
            'page'=>$page,
            'limit'=>$limit,
            'total'=>$total,
            'loaded'=>count($items),
            'has_more'=>$start > 0,
            'next_page'=>$start > 0 ? $page + 1 : null,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Gagal memuat riwayat chat.'], JSON_UNESCAPED_UNICODE);
}
