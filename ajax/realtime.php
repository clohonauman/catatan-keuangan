<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__.'/../db.php';

try {
    $data = readData();
    $revision = (int)($data['meta']['revision'] ?? 0);
    $chatSignature = sha1(json_encode($data['chats'] ?? [], JSON_UNESCAPED_UNICODE));
    $transactionSignature = sha1(json_encode($data['transactions'] ?? [], JSON_UNESCAPED_UNICODE));
    $account = ['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())];
    $accountSignature = sha1(json_encode($account, JSON_UNESCAPED_UNICODE));

    $sinceRevision = isset($_GET['since']) ? (int)$_GET['since'] : -1;
    $clientChatSignature = (string)($_GET['chat'] ?? '');
    $clientTransactionSignature = (string)($_GET['tx'] ?? '');
    $clientAccountSignature = (string)($_GET['acc'] ?? '');

    $changed = $sinceRevision !== $revision
        || $clientChatSignature === ''
        || $clientTransactionSignature === ''
        || !hash_equals($chatSignature, $clientChatSignature)
        || !hash_equals($transactionSignature, $clientTransactionSignature)
        || $clientAccountSignature === ''
        || !hash_equals($accountSignature, $clientAccountSignature);

    if (!$changed) {
        echo json_encode([
            'ok'=>true,
            'changed'=>false,
            'revision'=>$revision,
            'chat_signature'=>$chatSignature,
            'transaction_signature'=>$transactionSignature,
            'account_signature'=>$accountSignature,
            'server_time'=>time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok'=>true,
        'changed'=>true,
        'revision'=>$revision,
        'chat_signature'=>$chatSignature,
        'transaction_signature'=>$transactionSignature,
        'account_signature'=>$accountSignature,
        'account'=>$account,
        'summary'=>summary(),
        'chats'=>recentChats(40),
        'daily_budget'=>dailyBudgetStatus(),
        'server_time'=>time()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
