<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../native_assistant.php';
require_once __DIR__.'/../learning_helper.php';
require_once __DIR__.'/../adaptive_learning_helper.php';
require_once __DIR__.'/../finance_features.php';
$featureSnapshot = financeFeatureSnapshot();
$rawRealtime = readData();
$realtimeRevision = (int)($rawRealtime['meta']['revision'] ?? 0);
$realtimeChatSignature = sha1(json_encode($rawRealtime['chats'] ?? [], JSON_UNESCAPED_UNICODE));
$realtimeTransactionSignature = sha1(json_encode($rawRealtime['transactions'] ?? [], JSON_UNESCAPED_UNICODE));
$realtimeAccount = ['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())];
$realtimeAccountSignature = sha1(json_encode($realtimeAccount, JSON_UNESCAPED_UNICODE));
$transactions=recentTransactions(50); $chats=recentChats(40);
$month=date('Y-m'); $cats=[];
foreach(allTransactions() as $t){if(($t['type']??'')==='expense' && strpos((string)($t['transaction_date']??''), $month) === 0){$c=$t['category']??'Lainnya';$cats[$c]=($cats[$c]??0)+(int)$t['amount'];}}
arsort($cats);$categories=[];foreach($cats as $c=>$total)$categories[]=['category'=>$c,'total'=>$total];
echo json_encode([
    'ok'=>true,
    'revision'=>$realtimeRevision,
    'realtime'=>[
        'revision'=>$realtimeRevision,
        'chat_signature'=>$realtimeChatSignature,
        'transaction_signature'=>$realtimeTransactionSignature,
        'account_signature'=>$realtimeAccountSignature
    ],
    'summary'=>summary(),
    'transactions'=>$transactions,
    'chats'=>$chats,
    'categories'=>$categories,
    'daily_budget'=>dailyBudgetStatus(),
    'daily_budget_settings'=>dailyBudgetSettings(),
    'learning'=>['pending'=>learningPending(),'rule_count'=>count(learningListRules()),'adaptive'=>adaptiveLearningStatus()],
    'features'=>$featureSnapshot,
    'account'=>$realtimeAccount
],JSON_UNESCAPED_UNICODE);
