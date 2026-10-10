<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../offline_sync_helper.php';

$input=json_decode(file_get_contents('php://input'),true) ?: [];
if(!is_array($input)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Data pengaturan tidak valid.']);exit;}
$cached=offlineOpCachedResponse($input); if($cached){echo json_encode($cached,JSON_UNESCAPED_UNICODE);exit;}

if (array_key_exists('wallet_initial_balances', $input)) {
    try {
        $items=$input['wallet_initial_balances'];
        if(!is_array($items)||!$items)throw new InvalidArgumentException('Daftar perubahan saldo awal kosong.');
        $changes=[];
        foreach($items as $item){
            if(!is_array($item))throw new InvalidArgumentException('Data dompet tidak valid.');
            $id=$item['id']??null;$value=$item['initial_balance']??null;$expected=$item['expected_initial_balance']??null;
            $reserved=$item['reserved_balance']??0;$expectedReserved=$item['expected_reserved_balance']??0;
            $minimum=$item['minimum_balance']??0;$expectedMinimum=$item['expected_minimum_balance']??0;
            if(!is_int($id)||$id<1||!is_int($value)||$value<0||$value>9007199254740991||!is_int($expected)||!is_int($reserved)||$reserved<0||$reserved>9007199254740991||!is_int($expectedReserved)||$expectedReserved<0||!is_int($minimum)||$minimum<0||$minimum>9007199254740991||!is_int($expectedMinimum)||$expectedMinimum<0||isset($changes[$id]))throw new InvalidArgumentException('ID atau nominal saldo awal/dana disisihkan/saldo minimum tidak valid.');
            $changes[$id]=['value'=>$value,'expected'=>$expected,'reserved'=>$reserved,'expected_reserved'=>$expectedReserved,'minimum'=>$minimum,'expected_minimum'=>$expectedMinimum];
        }
        $premium=authHasPremiumAccess(authCurrentUser());
        financeMutate(function(&$d)use($changes,$premium){
            $active=[];foreach($d['wallets'] as $index=>$w)if(empty($w['archived']))$active[(int)$w['id']]=$index;
            $primary=$active?(int)array_key_first($active):0;
            // Validate every wallet under the same data lock before applying any changes.
            foreach($changes as $id=>$change){
                if(!isset($active[$id]))throw new InvalidArgumentException('Dompet tidak tersedia atau sudah diarsipkan. Buka kembali Atur Saldo Awal.');
                if(!$premium&&$id!==$primary)throw new RuntimeException('Mengubah saldo awal dompet tambahan memerlukan Premium.',403);
                $current=(int)$d['wallets'][$active[$id]]['initial_balance'];
                if($current!==$change['expected']&&$current!==$change['value'])throw new RuntimeException('Saldo awal telah berubah di perangkat lain. Buka kembali menu ini sebelum menyimpan.',409);
                $currentReserved=(int)($d['wallets'][$active[$id]]['reserved_balance']??0);
                if($currentReserved!==$change['expected_reserved']&&$currentReserved!==$change['reserved'])throw new RuntimeException('Dana disisihkan telah berubah di perangkat lain. Buka kembali menu ini sebelum menyimpan.',409);
                $currentMinimum=(int)($d['wallets'][$active[$id]]['minimum_balance']??0);
                if($currentMinimum!==$change['expected_minimum']&&$currentMinimum!==$change['minimum'])throw new RuntimeException('Saldo minimum telah berubah di perangkat lain. Buka kembali menu ini sebelum menyimpan.',409);
            }
            foreach($changes as $id=>$change){
                $index=$active[$id];
                $before=$d['wallets'][$index];
                $d['wallets'][$index]['initial_balance']=$change['value'];
                $d['wallets'][$index]['reserved_balance']=$change['reserved'];
                $d['wallets'][$index]['minimum_balance']=$change['minimum'];
                auditAdd($d,'initial_balance','wallet',(int)$id,$before,$d['wallets'][$index],true,'Saldo awal / dana disisihkan / saldo minimum diubah');
            }
            if(isset($changes[$primary]))$d['settings']['initial_balance']=$changes[$primary]['value'];
        });
        $response=['ok'=>true,'summary'=>summary(),'features'=>financeFeatureSnapshot()];
        offlineOpRemember($input,$response);
        echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;
    } catch (RuntimeException $e) {
        $code=in_array($e->getCode(),[403,409],true)?$e->getCode():500;
        http_response_code($code);echo json_encode(['ok'=>false,'error'=>$code===500?'Gagal menyimpan saldo awal.':$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;
    }
}

if(isset($input['initial_balance'])) {
    $initial=max(0,(int)$input['initial_balance']);
    setSetting('initial_balance',(string)$initial);
    financeMutate(function(&$d)use($initial){
        foreach($d['wallets'] as &$w){if(empty($w['archived'])){$before=$w;$w['initial_balance']=$initial;auditAdd($d,'initial_balance','wallet',(int)$w['id'],$before,$w,true,'Saldo awal diubah');break;}} unset($w);
    });
}
if(isset($input['daily_budget']) && is_array($input['daily_budget'])) {
    setDailyBudgetSettings($input['daily_budget']);
}

$response=[
    'ok'=>true,
    'summary'=>summary(),
    'daily_budget'=>dailyBudgetStatus(),
    'daily_budget_settings'=>dailyBudgetSettings(),
    'features'=>financeFeatureSnapshot()
];
offlineOpRemember($input,$response);
echo json_encode($response,JSON_UNESCAPED_UNICODE);
