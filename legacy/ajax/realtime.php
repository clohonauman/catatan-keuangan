<?php
require_once __DIR__.'/../auth.php';
$user = authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__.'/../db.php';
require_once __DIR__.'/../user_notification_helper.php';
require_once __DIR__.'/../finance_features.php';

try {
    $data = readData();
    $revision = (int)($data['meta']['revision'] ?? 0);
    $chatSignature = sha1(json_encode($data['chats'] ?? [], JSON_UNESCAPED_UNICODE));
    $transactionSignature = sha1(json_encode($data['transactions'] ?? [], JSON_UNESCAPED_UNICODE));
    $account = ['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())];
    $accountSignature = sha1(json_encode($account, JSON_UNESCAPED_UNICODE));
    $userId = (int)($user['id'] ?? 0);
    $notificationData = userNotificationReadData($userId);
    $notificationSignature = userNotificationRealtimeSignatureFromData($notificationData);
    $draftData = transactionInboxReadForUser($userId);
    $draftSignature = transactionInboxRealtimeSignatureFromData($draftData);

    $sinceRevision = isset($_GET['since']) ? (int)$_GET['since'] : -1;
    $clientChatSignature = (string)($_GET['chat'] ?? '');
    $clientTransactionSignature = (string)($_GET['tx'] ?? '');
    $clientAccountSignature = (string)($_GET['acc'] ?? '');
    $clientNotificationSignature = (string)($_GET['notif'] ?? '');
    $clientDraftSignature = (string)($_GET['draft'] ?? '');

    $chatChanged = $clientChatSignature === '' || !hash_equals($chatSignature, $clientChatSignature);
    $transactionChanged = $clientTransactionSignature === '' || !hash_equals($transactionSignature, $clientTransactionSignature);
    $accountChanged = $clientAccountSignature === '' || !hash_equals($accountSignature, $clientAccountSignature);
    $notificationChanged = $clientNotificationSignature === '' || !hash_equals($notificationSignature, $clientNotificationSignature);
    $draftChanged = $clientDraftSignature === '' || !hash_equals($draftSignature, $clientDraftSignature);

    $changed = $sinceRevision !== $revision
        || $chatChanged
        || $transactionChanged
        || $accountChanged
        || $notificationChanged
        || $draftChanged;

    if (!$changed) {
        echo json_encode([
            'ok'=>true,
            'changed'=>false,
            'revision'=>$revision,
            'chat_signature'=>$chatSignature,
            'transaction_signature'=>$transactionSignature,
            'account_signature'=>$accountSignature,
            'notification_signature'=>$notificationSignature,
            'notification_changed'=>false,
            'draft_signature'=>$draftSignature,
            'draft_changed'=>false,
            'server_time'=>time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $recentChats = recentChats(10);
    $chatTotal = count((array)($data['chats'] ?? []));

    echo json_encode([
        'ok'=>true,
        'changed'=>true,
        'revision'=>$revision,
        'chat_signature'=>$chatSignature,
        'transaction_signature'=>$transactionSignature,
        'account_signature'=>$accountSignature,
        'notification_signature'=>$notificationSignature,
        'notification_changed'=>$notificationChanged,
        'notification_center'=>$notificationChanged ? userNotificationSnapshotFromData($notificationData, 100) : null,
        'draft_signature'=>$draftSignature,
        'draft_changed'=>$draftChanged,
        // Rekonsiliasi juga bergantung pada transaksi hari ini, jadi snapshot
        // draf dikirim ulang bila transaksi utama berubah.
        'transaction_inbox'=>($draftChanged || $transactionChanged) ? transactionInboxSnapshot() : null,
        'account'=>$account,
        'summary'=>summary(),
        'chats'=>$recentChats,
        'chat_meta'=>[
            'page'=>1,'limit'=>10,'total'=>$chatTotal,'loaded'=>count($recentChats),
            'has_more'=>$chatTotal>count($recentChats),'next_page'=>$chatTotal>count($recentChats)?2:null
        ],
        'daily_budget'=>dailyBudgetStatus(),
        'server_time'=>time()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
