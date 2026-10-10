<?php
require_once __DIR__.'/../auth.php';
$user=authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../learning_helper.php';
require_once __DIR__.'/../adaptive_learning_helper.php';
require_once __DIR__.'/../user_notification_helper.php';
require_once __DIR__.'/../transaction_inbox_helper.php';

try {
    $data=\app\repositories\FinanceRepository::read((int)$user['id']);
    $account=['role'=>authUserRole($user),'plan'=>authPlan($user)];
    $feature=financeFeatureSnapshot();
    echo json_encode([
        'ok'=>true,
        'saved_at'=>date('c'),
        'data'=>$data,
        'features'=>$feature,
        'summary'=>summary(),
        'account'=>$account,
        'daily_budget'=>dailyBudgetStatus(),
        'daily_budget_settings'=>dailyBudgetSettings(),
        'notification_center'=>userNotificationSnapshot((int)$user['id'],100),
        'transaction_inbox'=>transactionInboxSnapshot(),
        'learning'=>[
            'pending'=>learningPending(),
            'rule_count'=>count(learningListRules()),
            'rules'=>learningListRules(),
            'adaptive'=>adaptiveLearningStatus(),
        ],
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Snapshot offline belum dapat dibuat.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
