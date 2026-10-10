<?php
require_once __DIR__ . '/../auth.php';
$user=authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../transaction_filter_helper.php';

try {
    $filters=txReadFilters($_GET);
    $pagination=txReadPagination($_GET,10,100);
    $result=\app\repositories\FinanceRepository::readTransactionsPage((int)$user['id'],$filters,(int)$pagination['page'],(int)$pagination['limit']);
    echo json_encode(['ok'=>true,'transactions'=>$result['transactions'],'meta'=>$result['meta']],JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Gagal memuat transaksi.'],JSON_UNESCAPED_UNICODE);
}
