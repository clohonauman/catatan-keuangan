<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../offline_sync_helper.php';

try {
    $input=json_decode(file_get_contents('php://input'),true) ?: [];
    $cached=offlineOpCachedResponse($input);if($cached){echo json_encode($cached,JSON_UNESCAPED_UNICODE);exit;}
    $id=(int)($input['id']??0);
    if($id<=0) throw new InvalidArgumentException('ID transaksi tidak valid.');
    $expected=trim((string)($input['expected_version']??''));
    $deleted=deleteTransaction($id,$expected);
    if(!$deleted) throw new InvalidArgumentException('Transaksi tidak ditemukan.');
    $response=['ok'=>true,'summary'=>summary(),'deleted'=>$deleted];
    offlineOpRemember($input,$response);
    echo json_encode($response,JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException $e){
    http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch(RuntimeException $e){
    $code=$e->getCode()===409?409:500;http_response_code($code);echo json_encode(['ok'=>false,'error'=>$code===409?$e->getMessage():'Gagal menghapus transaksi.'],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e){
    http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Gagal menghapus transaksi.'],JSON_UNESCAPED_UNICODE);
}
