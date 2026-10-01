<?php
require_once __DIR__.'/../auth.php';
$user=authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');

if(!authHasPremiumAccess($user)){
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Simulasi Keuangan tersedia untuk akun Premium.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try{
    $month=trim((string)($_GET['month']??''));
    if($month===''){
        $month=(new DateTimeImmutable('first day of next month'))->format('Y-m');
    }
    $historyMonths=max(3,min(12,(int)($_GET['history_months']??6)));
    $snapshot=\app\repositories\SimulationRepository::snapshot((int)$user['id'],$month,$historyMonths);
    echo json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(InvalidArgumentException $e){
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    error_log('[CatatanKeuangan] Simulation endpoint gagal: '.$e);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Data simulasi belum dapat dimuat.'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
