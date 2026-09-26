<?php
require_once __DIR__ . '/../auth.php';
$user=authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../db.php';

try {
    $page=max(1,(int)($_GET['page']??1));
    $limit=min(50,max(1,(int)($_GET['limit']??10)));
    $result=\app\repositories\FinanceRepository::readChatsPage((int)$user['id'],$page,$limit);
    echo json_encode(['ok'=>true,'chats'=>$result['items'],'meta'=>$result['meta']],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Gagal memuat riwayat chat.'],JSON_UNESCAPED_UNICODE);
}
