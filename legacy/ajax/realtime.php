<?php
require_once __DIR__.'/../auth.php';
$user=authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../user_notification_helper.php';
require_once __DIR__.'/../finance_features.php';

try {
    $userId=(int)($user['id']??0);
    $rt=\app\repositories\FinanceRepository::realtimeState($userId);
    $revision=(int)$rt['revision'];
    $chatSignature=(string)$rt['chat_signature'];
    $transactionSignature=(string)$rt['transaction_signature'];
    $account=['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())];
    $accountSignature=sha1(json_encode($account,JSON_UNESCAPED_UNICODE));
    $notificationSignature=userNotificationRealtimeSignature($userId);
    $draftSignature=transactionInboxRealtimeSignatureForUser($userId);

    $sinceRevision=(int)($_GET['since']??-1);
    $clientChat=(string)($_GET['chat']??'');
    $clientTx=(string)($_GET['tx']??'');
    $clientAcc=(string)($_GET['acc']??'');
    $clientNotif=(string)($_GET['notif']??'');
    $clientDraft=(string)($_GET['draft']??'');

    $chatChanged=$clientChat===''||!hash_equals($chatSignature,$clientChat);
    $txChanged=$clientTx===''||!hash_equals($transactionSignature,$clientTx);
    $accountChanged=$clientAcc===''||!hash_equals($accountSignature,$clientAcc);
    $notificationChanged=$clientNotif===''||!hash_equals($notificationSignature,$clientNotif);
    $draftChanged=$clientDraft===''||!hash_equals($draftSignature,$clientDraft);
    $changed=$sinceRevision!==$revision||$chatChanged||$txChanged||$accountChanged||$notificationChanged||$draftChanged;

    $base=[
        'ok'=>true,'changed'=>$changed,'revision'=>$revision,
        'chat_signature'=>$chatSignature,'transaction_signature'=>$transactionSignature,'account_signature'=>$accountSignature,
        'notification_signature'=>$notificationSignature,'notification_changed'=>$notificationChanged,
        'draft_signature'=>$draftSignature,'draft_changed'=>$draftChanged,'server_time'=>time()
    ];
    if(!$changed){echo json_encode($base,JSON_UNESCAPED_UNICODE);exit;}

    if($chatChanged){
        $page=\app\repositories\FinanceRepository::readChatsPage($userId,1,10);
        $base['chats']=$page['items'];$base['chat_meta']=$page['meta'];
    }
    if($notificationChanged)$base['notification_center']=userNotificationSnapshot($userId,100);
    if($draftChanged||$txChanged)$base['transaction_inbox']=transactionInboxSnapshot();
    if($accountChanged)$base['account']=$account;
    if($txChanged||$sinceRevision!==$revision){
        $base['summary']=summary();
        $base['daily_budget']=dailyBudgetStatus();
    }
    echo json_encode($base,JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Realtime sementara tidak tersedia.'],JSON_UNESCAPED_UNICODE);
}
