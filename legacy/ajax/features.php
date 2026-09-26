<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../offline_sync_helper.php';
require_once __DIR__.'/../receipt_helper.php';

function featureFail($msg,$code=400){http_response_code($code);echo json_encode(['ok'=>false,'error'=>$msg],JSON_UNESCAPED_UNICODE);exit;}

try {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        echo json_encode(['ok'=>true,'features'=>financeFeatureSnapshot(),'summary'=>summary(),'account'=>['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())]],JSON_UNESCAPED_UNICODE); exit;
    }
    $contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
    $multipart=strpos($contentType,'multipart/form-data')!==false;
    $input=$multipart?$_POST:json_decode(file_get_contents('php://input'),true); if(!is_array($input))$input=[];
    $cached=offlineOpCachedResponse($input); if($cached){echo json_encode($cached,JSON_UNESCAPED_UNICODE);exit;}
    $action=(string)($input['action']??''); $result=null;
    $premiumActions = [
        'wallet_save','wallet_archive','wallet_transfer',
        'category_save','category_archive','monthly_budget_set',
        'bill_save','bill_delete','bill_pay',
        'recurring_save','recurring_delete','recurring_run',
        'goal_save','goal_delete','goal_contribute','payday_set'
    ];
    if (in_array($action, $premiumActions, true) && !authHasPremiumAccess(authCurrentUser())) {
        featureFail('Fitur ini khusus akun Premium. Silakan pilih paket Premium terlebih dahulu.', 403);
    }
    switch($action){
        case 'transaction_create': $result=financeCreateManualTransaction($input); break;
        case 'quick_capture':
            $attachment=null;$ocr=[];
            try{
                if($multipart&&isset($_FILES['photo']))$attachment=saveReceiptUpload($_FILES['photo']);
                if($attachment){
                    $ocrText=trim((string)($input['ocr_text']??''));if(strlen($ocrText)>12000)$ocrText=substr($ocrText,0,12000);
                    $ocrAmount=max(0,(int)($input['ocr_amount']??0));$ocrScore=max(0,(int)($input['ocr_score']??0));$ocrConfidence=max(0,min(100,(float)($input['ocr_confidence']??0)));
                    $serverDetection=detectReceiptAmountFromText($ocrText);
                    if($ocrAmount<=0&&$serverDetection['amount']>0){$ocrAmount=(int)$serverDetection['amount'];$ocrScore=(int)$serverDetection['score'];}
                    if((int)($input['amount']??0)<=0&&$ocrAmount>0)$input['amount']=$ocrAmount;
                    if(trim((string)($input['note']??''))===''){
                        $merchant=detectReceiptMerchant($ocrText);
                        $input['note']=$merchant!==''?'Nota '.$merchant:'Nota foto';
                    }
                    $ocr=['amount'=>$ocrAmount,'score'=>$ocrScore,'line'=>substr((string)($input['ocr_line']??($serverDetection['line']??'')),0,250),'confidence'=>$ocrConfidence,'text'=>substr($ocrText,0,4000)];
                }
                $result=transactionInboxAddQuick($input,$attachment,$ocr);
            }catch(Throwable $e){
                if($attachment&&!empty($attachment['file']))deleteReceiptFileIfUnused((string)$attachment['file']);
                throw $e;
            }
            break;
        case 'inbox_confirm': $result=transactionInboxConfirm((int)($input['id']??0),$input); break;
        case 'inbox_confirm_all': $result=transactionInboxConfirmAll((array)($input['ids']??[])); break;
        case 'inbox_dismiss': $result=transactionInboxDismiss((int)($input['id']??0)); break;
        case 'reconciliation_complete': $result=transactionInboxMarkReconciled((string)($input['date']??'')); break;
        case 'wallet_save': $result=financeSaveWallet($input); break;
        case 'wallet_archive': $result=financeArchiveWallet((int)($input['id']??0)); break;
        case 'wallet_transfer': $result=financeTransfer((int)($input['from_wallet_id']??0),(int)($input['to_wallet_id']??0),(int)($input['amount']??0),(string)($input['note']??''),(string)($input['transaction_date']??'')); break;
        case 'category_save': $result=financeSaveCategory($input); break;
        case 'category_archive': $result=financeArchiveCategory((int)($input['id']??0)); break;
        case 'monthly_budget_set': financeSetMonthlyBudget((int)($input['category_id']??0),(int)($input['limit']??0),(string)($input['month']??'')); $result=true; break;
        case 'bill_save': $result=financeSaveBill($input); break;
        case 'bill_delete': financeDeleteBill((int)($input['id']??0)); $result=true; break;
        case 'bill_pay': $result=financePayBill((int)($input['id']??0),(string)($input['date']??'')); break;
        case 'recurring_save': $result=financeSaveRecurring($input); break;
        case 'recurring_delete': financeDeleteRecurring((int)($input['id']??0)); $result=true; break;
        case 'recurring_run': $result=financeProcessRecurring(); break;
        case 'goal_save': $result=financeSaveGoal($input); break;
        case 'goal_delete': financeDeleteGoal((int)($input['id']??0)); $result=true; break;
        case 'goal_contribute': $result=financeContributeGoal((int)($input['id']??0),(int)($input['amount']??0)); break;
        case 'payday_set': $result=financeSetPaydayDay((int)($input['day']??1)); break;
        case 'notifications_set': $result=financeSetNotificationSettings((array)($input['settings']??[])); break;
        case 'history_undo': $result=financeUndoAudit((int)($input['id']??0)); break;
        default: featureFail('Aksi fitur tidak dikenali.');
    }
    $response=['ok'=>true,'result'=>$result,'features'=>financeFeatureSnapshot(),'summary'=>summary(),'account'=>['role'=>authUserRole(authCurrentUser()),'plan'=>authPlan(authCurrentUser())]];
    offlineOpRemember($input,$response);
    echo json_encode($response,JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException $e){featureFail($e->getMessage(),422);} catch(Throwable $e){featureFail($e->getMessage(),500);}
