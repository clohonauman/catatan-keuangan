<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../native_assistant.php';
require_once __DIR__.'/../learning_helper.php';
require_once __DIR__.'/../adaptive_learning_helper.php';
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../user_notification_helper.php';
$featureSnapshot = financeFeatureSnapshot();
$userId=(int)(authCurrentUser()['id']??0);
$rt=\app\repositories\FinanceRepository::realtimeState($userId);
$realtimeRevision=(int)$rt['revision'];
$realtimeChatSignature=(string)$rt['chat_signature'];
$realtimeTransactionSignature=(string)$rt['transaction_signature'];
$realtimeAccount=['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())];
$realtimeAccountSignature=sha1(json_encode($realtimeAccount,JSON_UNESCAPED_UNICODE));
$realtimeDraftSignature=transactionInboxRealtimeSignatureForUser($userId);
$realtimeNotificationSignature=userNotificationRealtimeSignature($userId);
$chatPage=\app\repositories\FinanceRepository::readChatsPage($userId,1,10);
$txPage=\app\repositories\FinanceRepository::readTransactionsPage($userId,['type'=>'all','from'=>'','to'=>'','sort'=>'date_desc','search'=>'','wallet_id'=>0,'category'=>''],1,10);
$transactions=$txPage['transactions'];$chats=$chatPage['items'];$chatTotal=(int)$chatPage['meta']['total'];
$categories=\app\repositories\FinanceRepository::monthlyExpenseCategories($userId,date('Y-m'));
echo json_encode([
    'ok'=>true,
    'revision'=>$realtimeRevision,
    'realtime'=>[
        'revision'=>$realtimeRevision,
        'chat_signature'=>$realtimeChatSignature,
        'transaction_signature'=>$realtimeTransactionSignature,
        'account_signature'=>$realtimeAccountSignature,
        'draft_signature'=>$realtimeDraftSignature,
        'notification_signature'=>$realtimeNotificationSignature
    ],
    'summary'=>summary(),
    'transactions'=>$transactions,
    'chats'=>$chats,
    'chat_meta'=>$chatPage['meta'],
    'categories'=>$categories,
    'daily_budget'=>dailyBudgetStatus(),
    'daily_budget_settings'=>dailyBudgetSettings(),
    'learning'=>['pending'=>learningPending(),'rule_count'=>count(learningListRules()),'adaptive'=>adaptiveLearningStatus()],
    'features'=>$featureSnapshot,
    'account'=>$realtimeAccount
],JSON_UNESCAPED_UNICODE);
