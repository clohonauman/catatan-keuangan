<?php
require_once __DIR__.'/../auth.php';
$user=authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');

if (!authIsSuperAdmin($user)) {
    http_response_code(403);
    echo json_encode([
        'ok'=>false,
        'error'=>'Fitur Pembelajaran hanya tersedia untuk Super Admin.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once __DIR__.'/../native_assistant.php';
require_once __DIR__.'/../learning_helper.php';
require_once __DIR__.'/../offline_sync_helper.php';

try {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        echo json_encode(['ok'=>true,'rules'=>learningListRules(),'pending'=>learningPending()],JSON_UNESCAPED_UNICODE); exit;
    }
    $in=json_decode(file_get_contents('php://input'),true); if(!is_array($in))$in=[];
    $cached=offlineOpCachedResponse($in);if($cached){echo json_encode($cached,JSON_UNESCAPED_UNICODE);exit;}
    $action=(string)($in['action']??'save');
    if ($action==='delete') {
        learningDeleteRule((int)($in['id']??0));
    } elseif ($action==='cancel_pending') {
        learningClearPending();
    } else {
        learningSaveRule((string)($in['phrase']??''),(string)($in['response']??''),(int)$user['id']);
    }
    $response=['ok'=>true,'rules'=>learningListRules(),'pending'=>learningPending()];
    offlineOpRemember($in,$response);
    echo json_encode($response,JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
