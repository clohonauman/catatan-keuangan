<?php
require_once __DIR__ . '/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../transaction_filter_helper.php';

try {
    $filters = txReadFilters($_GET);
    $pagination = txReadPagination($_GET, 10, 100);
    $result = txFilterTransactions(allTransactions(), $filters);
    $result = txPaginateResult($result, (int)$pagination['page'], (int)$pagination['limit']);
    echo json_encode([
        'ok' => true,
        'transactions' => $result['transactions'],
        'meta' => $result['meta'],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Gagal memuat transaksi.'], JSON_UNESCAPED_UNICODE);
}
