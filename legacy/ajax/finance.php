<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../native_assistant.php';
require_once __DIR__.'/../receipt_helper.php';
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../offline_sync_helper.php';
require_once __DIR__.'/../humanoid_assistant_helper.php';

function financeFail($msg,$code=400){http_response_code($code);echo json_encode(['ok'=>false,'error'=>$msg],JSON_UNESCAPED_UNICODE);exit;}
function financeConfirmationReply(array $pending): string {
    $lines=['Periksa dulu sebelum disimpan:'];
    foreach((array)($pending['drafts']??[]) as $i=>$t){
        $type=(string)($t['type']??'expense');
        $label=['expense'=>'Pengeluaran','income'=>'Pemasukan','transfer'=>'Transfer Antar Dompet'][$type]??'Transaksi';
        $prefix=count($pending['drafts'])>1?($i+1).'. ':'';
        $date=date('d/m/Y',strtotime((string)($t['transaction_date']??date('Y-m-d'))));
        if($type==='transfer'){
            $from=financeWalletById((int)($t['from_wallet_id']??$t['wallet_id']??0));
            $to=financeWalletById((int)($t['to_wallet_id']??0));
            $lines[]=$prefix.$label.' · Nominal '.rupiah((int)($t['amount']??0)).' · '.$date.' · '.($from['name']??'Pilih asal').' → '.($to['name']??'Pilih tujuan').'.';
        } else {
            $wallet=financeWalletById((int)($t['wallet_id']??0));
            $kind=$type==='expense'?(['daily'=>'Harian','once'=>'Sekali bayar','recurring'=>'Berulang'][transactionSpendingKind($t)]??'Sekali bayar'):'';
            $lines[]=$prefix.$label.' · Nominal '.rupiah((int)($t['amount']??0)).' · '.(string)($t['category']??'Lainnya').' · '.$date.' · '.($wallet['name']??'Utama').($kind!==''?' · '.$kind:'').'.';
            if($type==='expense'&&!empty($t['bill_candidates'][0])) $lines[]='   Kemungkinan terkait tagihan: '.(string)$t['bill_candidates'][0]['name'].' ('.rupiah((int)$t['bill_candidates'][0]['amount']).').';
        }
        $adaptive=(array)($t['adaptive_meta']??[]);
        if(!empty($adaptive['applied'])){
            $pct=(int)round(max(0,min(1,(float)($adaptive['confidence']??0)))*100);
            $lines[]='   🧠 Disesuaikan dari pola transaksi pribadi'.($pct>0?' · keyakinan '.$pct.'%':'').'.';
        }
    }
    if(!empty($pending['warning']))$lines[]='⚠️ '.$pending['warning'];
    $lines[]='Jenis transaksi, nominal, dan dompet masih bisa diubah pada kartu konfirmasi sebelum disimpan.';
    return implode("\n",$lines);
}
function financeAdaptiveLearningAck(): string {
    if(!function_exists('adaptiveLearningLastResult')) return '';
    $r=adaptiveLearningLastResult();
    $corrected=(int)($r['corrected']??0);
    if($corrected<=0) return '';
    $fields=array_values(array_filter(array_map('strval',(array)($r['fields']??[]))));
    $labels=['type'=>'jenis transaksi','category'=>'kategori','wallet_id'=>'dompet','from_wallet_id'=>'dompet asal','to_wallet_id'=>'dompet tujuan','spending_kind'=>'pola pengeluaran'];
    $names=[];foreach($fields as $f)if(isset($labels[$f]))$names[]=$labels[$f];
    $detail=$names?' ('.implode(', ',array_values(array_unique($names))).')':'';
    return "

🧠 Koreksi ini sudah saya pelajari".$detail.'. Pola serupa berikutnya akan diprioritaskan mengikuti koreksi ini.';
}

function financeResponse($reply,$saved=[],$receipt=[],$extra=[]){
    return array_merge([
        'ok'=>true,'reply'=>$reply,'transactions'=>$saved,'summary'=>summary(),
        'receipt'=>array_merge(['used'=>false,'amount'=>0,'score'=>0,'line'=>''],$receipt),
        'daily_budget'=>dailyBudgetStatus(),
        'pending_confirmation'=>financePendingChatConfirmation(),
        'learning'=>[
            'pending'=>authIsSuperAdmin(authCurrentUser()) ? learningPending() : null,
            'can_manage'=>authIsSuperAdmin(authCurrentUser()),
            'adaptive'=>adaptiveLearningStatus()
        ]
    ],$extra);
}

try {
    $contentType=(string)($_SERVER['CONTENT_TYPE']??'');
    $multipart=stripos($contentType,'multipart/form-data')!==false;
    $input=$multipart?$_POST:json_decode((string)file_get_contents('php://input'),true);
    if(!is_array($input))$input=[];
    $cachedOffline=offlineOpCachedResponse($input);if($cachedOffline){echo json_encode($cachedOffline,JSON_UNESCAPED_UNICODE);exit;}

    // Explicit confirmation from the interactive chat card.
    $confirmAction=strtolower(trim((string)($input['confirmation_action']??'')));
    if(in_array($confirmAction,['confirm','cancel'],true)){
        $pending=financePendingChatConfirmation();
        $id=(int)($input['confirmation_id']??0);
        if(!$pending || (int)($pending['id']??0)!==$id) financeFail('Konfirmasi transaksi sudah tidak tersedia. Muat ulang chat.',409);
        if($confirmAction==='cancel'){
            addChat('user','Batal transaksi');financeClearPendingChatConfirmation();
            $reply='Baik, transaksi dibatalkan dan tidak mengubah saldo.';addChat('assistant',$reply);
            $response=financeResponse($reply);offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
        }
        $overrides=$input['overrides']??[];if(is_string($overrides)){$decoded=json_decode($overrides,true);$overrides=is_array($decoded)?$decoded:[];}if(!is_array($overrides))$overrides=[];
        addChat('user','Simpan transaksi');
        $saved=financeConfirmPendingChat($id,$overrides);
        $reply=nativeReply((string)($pending['message']??'konfirmasi transaksi'),$saved).financeAdaptiveLearningAck();addChat('assistant',$reply);
        $response=financeResponse($reply,$saved);offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
    }

    $rawMessage=trim((string)($input['message']??''));
    $normalized=$rawMessage!==''?humanoidNormalizeMessage($rawMessage):'';
    $message=$normalized!==''?humanoidResolveContext($normalized):'';
    $mode=strtolower(trim((string)($input['image_mode']??'auto')));if(!in_array($mode,['auto','receipt','attachment'],true))$mode='auto';
    $attachment=null;if($multipart&&isset($_FILES['photo']))$attachment=saveReceiptUpload($_FILES['photo']);
    if($message===''&&!$attachment)financeFail('Pesan atau foto harus diisi.');

    $ocrText=trim((string)($input['ocr_text']??''));if(strlen($ocrText)>12000)$ocrText=substr($ocrText,0,12000);
    $ocrAmountClient=max(0,(int)($input['ocr_amount']??0));$ocrScoreClient=max(0,(int)($input['ocr_score']??0));$ocrConfidence=max(0,min(100,(float)($input['ocr_confidence']??0)));
    $displayMessage=$rawMessage!==''?$rawMessage:($attachment?'📷 Foto nota':'');
    addChat('user',$displayMessage,$attachment);

    // Natural-language shortcut for a pending confirmation.
    $pendingBefore=financePendingChatConfirmation();
    if(!$attachment && $pendingBefore){
        $draftEdit=humanoidEditPendingConfirmation($message,$pendingBefore);
        if($draftEdit){
            $reply=(string)$draftEdit['message']."\n".financeConfirmationReply((array)$draftEdit['pending']);
            addChat('assistant',$reply);
            $response=financeResponse($reply,[],[],['normalized_message'=>$message]);
            offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
        }
    }
    if(!$attachment && $pendingBefore && preg_match('/^(?:ya|iya|iyo|ok|oke|simpan|catat|lanjut|konfirmasi)(?:\s+transaksi)?[.!]?$/u',norm($message))){
        $saved=financeConfirmPendingChat((int)$pendingBefore['id']);$reply=nativeReply((string)($pendingBefore['message']??'konfirmasi transaksi'),$saved).financeAdaptiveLearningAck();addChat('assistant',$reply);
        $response=financeResponse($reply,$saved,[],['normalized_message'=>$message]);offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
    }
    if(!$attachment && $pendingBefore && preg_match('/^(?:batal|batalkan|jangan|tidak jadi|gak jadi|nda jadi|nyanda jadi)[.!]?$/u',norm($message))){
        financeClearPendingChatConfirmation();$reply='Baik, transaksi dibatalkan dan tidak mengubah saldo.';addChat('assistant',$reply);
        $response=financeResponse($reply,[],[],['normalized_message'=>$message]);offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
    }

    if(!$attachment&&$message!==''){
        $learning=learningHandleTeachingInput($message);
        if(!empty($learning['handled'])){$reply=(string)$learning['reply'];addChat('assistant',$reply);$response=financeResponse($reply,[],[],['normalized_message'=>$message]);offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;}

        // Human-like/simple intents run before transaction extraction so a
        // hypothetical question such as “kalau beli 500rb aman?” is never
        // mistaken for an actual purchase.
        $humanReply=humanoidDirectReply($message);
        if($humanReply!==null){
            $reply=(string)$humanReply;addChat('assistant',$reply);
            $response=financeResponse($reply,[],[],['normalized_message'=>$message]);
            offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
        }
    }

    $drafts=$message!==''?extractTransactions($message):[];
    $usedReceipt=false;$receiptDetection=['amount'=>0,'score'=>0,'line'=>''];$warning='';
    if($attachment&&$mode!=='attachment'){
        $serverDetection=detectReceiptAmountFromText($ocrText);
        $receiptDetection=($ocrAmountClient>0&&$ocrScoreClient>=(int)$serverDetection['score'])?['amount'=>$ocrAmountClient,'score'=>$ocrScoreClient,'line'=>trim((string)($input['ocr_line']??''))]:$serverDetection;
    }

    if($attachment&&$mode!=='receipt'&&!empty($drafts)){
        $drafts[0]['attachment']=$attachment;$drafts[0]['source']='chat_with_photo';
    } elseif($attachment&&$mode!=='attachment'&&(int)$receiptDetection['amount']>0){
        // A scan is always previewed before saving. Low confidence is highlighted rather than silently rejected.
        $context=trim($message.' '.$ocrText);$category=categoryFor($context,'expense');$merchant=detectReceiptMerchant($ocrText);
        $note=$message!==''?$message:($merchant!==''?'Nota '.$merchant:'Nota hasil scan');$date=$message!==''?detectDate($message):detectReceiptDateFromText($ocrText);
        $drafts=[[
            'type'=>'expense','category'=>$category,'amount'=>(int)$receiptDetection['amount'],'note'=>substr($note,0,255),'transaction_date'=>$date,
            'wallet_id'=>$message!==''?walletForText($message):financeDefaultWalletId(),'source'=>'receipt_scan','spending_kind'=>spendingKindForText($context,'expense'),'attachment'=>$attachment,
            'ocr'=>['amount'=>(int)$receiptDetection['amount'],'score'=>(int)$receiptDetection['score'],'line'=>substr((string)$receiptDetection['line'],0,250),'confidence'=>$ocrConfidence,'text'=>substr($ocrText,0,4000)]
        ]];
        $usedReceipt=true;
        if((int)$receiptDetection['score']<42)$warning='Hasil scan nota kurang yakin. Periksa nominal, kategori, tanggal, dan dompet sebelum menyimpan.';
    }

    if(!empty($drafts)){
        $pending=financeSetPendingChatConfirmation($drafts,$message!==''?$message:'foto nota',$warning);
        $reply=financeConfirmationReply($pending);if($usedReceipt)$reply='📷 Total nota terbaca '.rupiah((int)$receiptDetection['amount']).".\n".$reply;
        addChat('assistant',$reply);
        $response=financeResponse($reply,[],['used'=>$usedReceipt,'amount'=>(int)$receiptDetection['amount'],'score'=>(int)$receiptDetection['score'],'line'=>(string)$receiptDetection['line']],['normalized_message'=>$message]);
        offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);exit;
    }

    if($attachment&&$mode==='attachment')$reply='📎 Foto disimpan sebagai lampiran chat. Tidak ada transaksi yang dicatat.';
    elseif($attachment&&$mode!=='attachment'){
        if((int)$receiptDetection['amount']>0)$reply='📷 Saya menemukan kemungkinan total '.rupiah($receiptDetection['amount']).', tetapi belum dapat membentuk transaksi yang valid. Tulis nominal/keterangan agar bisa saya tampilkan untuk konfirmasi.';
        elseif($ocrText==='')$reply='📷 Foto diterima, tetapi teks nota belum berhasil dipindai. Foto tetap tersimpan sebagai lampiran chat. Coba foto lebih lurus/terang.';
        else $reply='📷 Teks pada foto terbaca, tetapi total nota belum ditemukan dengan jelas. Foto tetap tersimpan sebagai lampiran chat.';
    } else $reply=nativeReply($message,[]);

    addChat('assistant',$reply);$response=financeResponse($reply,[],['used'=>false,'amount'=>(int)$receiptDetection['amount'],'score'=>(int)$receiptDetection['score'],'line'=>(string)$receiptDetection['line']],['normalized_message'=>$message]);
    offlineOpRemember($input,$response);echo json_encode($response,JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException $e){financeFail($e->getMessage(),422);} catch(RuntimeException $e){financeFail($e->getMessage(),$e->getCode()===409?409:500);} catch(Throwable $e){financeFail($e->getMessage(),500);}
