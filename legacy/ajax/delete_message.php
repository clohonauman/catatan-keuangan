<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../receipt_helper.php';
require_once __DIR__.'/../offline_sync_helper.php';

function messageDeleteFail($message, $code=400) {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $cached = offlineOpCachedResponse($input);
    if ($cached) { echo json_encode($cached, JSON_UNESCAPED_UNICODE); exit; }

    $deleteAll = !empty($input['all']);
    $deleted = [];

    if ($deleteAll) {
        $deleted = clearChatHistory();
    } else {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) messageDeleteFail('ID pesan tidak valid.');
        $item = deleteChatMessage($id);
        if (!$item) messageDeleteFail('Pesan tidak ditemukan.', 404);
        $deleted[] = $item;
    }

    // Bila sebuah foto hanya dipakai oleh chat yang dihapus, bersihkan file-nya.
    // Foto yang juga terhubung ke transaksi tetap dipertahankan.
    foreach ($deleted as $item) {
        $file = (string)($item['attachment']['file'] ?? '');
        if ($file !== '') deleteReceiptFileIfUnused($file);
    }

    $response = [
        'ok'=>true,
        'deleted_count'=>count($deleted),
        'summary'=>summary()
    ];
    offlineOpRemember($input, $response);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    messageDeleteFail($e->getMessage(), 500);
}
