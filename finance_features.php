<?php
require_once __DIR__.'/db.php';

/**
 * Fitur keuangan lanjutan tetap memakai file JSON per user.
 * Semua fungsi di file ini melakukan upgrade struktur data secara lazy,
 * sehingga file user versi lama tetap dapat dipakai tanpa migrasi manual.
 */

function financeDefaultCategories() {
    return [
        ['id'=>1,'name'=>'Makan','type'=>'expense','icon'=>'🍽️','keywords'=>['makan','nasi','sarapan','lunch','dinner','kopi','snack','jajan'],'archived'=>false],
        ['id'=>2,'name'=>'Bensin','type'=>'expense','icon'=>'⛽','keywords'=>['bensin','pertamax','pertalite','bbm','shell'],'archived'=>false],
        ['id'=>3,'name'=>'Cicilan','type'=>'expense','icon'=>'🧾','keywords'=>['cicilan','angsuran','kredit'],'archived'=>false],
        ['id'=>4,'name'=>'Belanja','type'=>'expense','icon'=>'🛒','keywords'=>['belanja','minimarket','supermarket','sembako'],'archived'=>false],
        ['id'=>5,'name'=>'Transportasi','type'=>'expense','icon'=>'🛵','keywords'=>['grab','gojek','ojek','parkir','tol','angkot','transport'],'archived'=>false],
        ['id'=>6,'name'=>'Tagihan','type'=>'expense','icon'=>'⚡','keywords'=>['listrik','pulsa','internet','wifi','air','tagihan'],'archived'=>false],
        ['id'=>7,'name'=>'Kesehatan','type'=>'expense','icon'=>'🩺','keywords'=>['obat','dokter','klinik','rumah sakit','vitamin'],'archived'=>false],
        ['id'=>8,'name'=>'Hiburan','type'=>'expense','icon'=>'🎬','keywords'=>['nonton','game','bioskop','hiburan'],'archived'=>false],
        ['id'=>9,'name'=>'Lainnya','type'=>'both','icon'=>'💳','keywords'=>[],'archived'=>false],
        ['id'=>10,'name'=>'Gaji','type'=>'income','icon'=>'💼','keywords'=>['gaji','salary'],'archived'=>false],
        ['id'=>11,'name'=>'Bonus','type'=>'income','icon'=>'🎁','keywords'=>['bonus','thr','insentif'],'archived'=>false],
        ['id'=>12,'name'=>'Transfer','type'=>'income','icon'=>'↔️','keywords'=>['transfer','dikirim','kiriman','masuk','terima'],'archived'=>false],
        ['id'=>13,'name'=>'Penjualan','type'=>'income','icon'=>'🏷️','keywords'=>['jual','penjualan'],'archived'=>false],
    ];
}

function financeEnsureFeatureData(&$d) {
    if (!isset($d['settings']) || !is_array($d['settings'])) $d['settings'] = [];
    if (!isset($d['settings']['payday_day'])) $d['settings']['payday_day'] = 1;
    if (!isset($d['settings']['notifications']) || !is_array($d['settings']['notifications'])) {
        $d['settings']['notifications'] = [
            'enabled'=>false,
            'daily_budget'=>true,
            'bills'=>true,
            'low_balance'=>false,
            'low_balance_threshold'=>100000,
            'email_enabled'=>true,
        ];
    }
    if (!array_key_exists('email_enabled', $d['settings']['notifications'])) $d['settings']['notifications']['email_enabled'] = true;

    if (!isset($d['wallets']) || !is_array($d['wallets']) || count($d['wallets']) === 0) {
        $legacyInitial = max(0, (int)($d['settings']['initial_balance'] ?? 0));
        $d['wallets'] = [[
            'id'=>1,
            'name'=>'Utama',
            'type'=>'cash',
            'initial_balance'=>$legacyInitial,
            'reserved_balance'=>0,
            'minimum_balance'=>0,
            'archived'=>false,
            'created_at'=>date('Y-m-d H:i:s'),
        ]];
    }
    if (!isset($d['categories']) || !is_array($d['categories']) || count($d['categories']) === 0) $d['categories'] = financeDefaultCategories();
    if (!isset($d['monthly_budgets']) || !is_array($d['monthly_budgets'])) $d['monthly_budgets'] = [];
    if (!isset($d['bills']) || !is_array($d['bills'])) $d['bills'] = [];
    if (!isset($d['recurring']) || !is_array($d['recurring'])) $d['recurring'] = [];
    if (!isset($d['goals']) || !is_array($d['goals'])) $d['goals'] = [];
    if (!isset($d['pending_chat_confirmation']) || !is_array($d['pending_chat_confirmation'])) $d['pending_chat_confirmation'] = [];
    auditEnsureData($d);
    if (!isset($d['meta']) || !is_array($d['meta'])) $d['meta'] = [];
    foreach ([
        'next_wallet_id'=>2,
        'next_category_id'=>14,
        'next_bill_id'=>1,
        'next_recurring_id'=>1,
        'next_goal_id'=>1,
        'next_confirmation_id'=>1,
    ] as $k=>$v) if (!isset($d['meta'][$k])) $d['meta'][$k] = $v;

    // Pastikan ID berikutnya selalu lebih besar dari ID yang sudah ada.
    foreach ([['wallets','next_wallet_id'],['categories','next_category_id'],['bills','next_bill_id'],['recurring','next_recurring_id'],['goals','next_goal_id']] as $pair) {
        $max = 0;
        foreach ((array)$d[$pair[0]] as $item) $max = max($max, (int)($item['id'] ?? 0));
        $d['meta'][$pair[1]] = max((int)$d['meta'][$pair[1]], $max + 1);
    }

    // Transaksi lama otomatis diarahkan ke dompet utama.
    foreach ($d['wallets'] as &$w) {
        if(!isset($w['reserved_balance'])) $w['reserved_balance']=0;
        if(!isset($w['minimum_balance'])) $w['minimum_balance']=0;
    }
    unset($w);
    foreach ($d['transactions'] as &$t) {
        if (!isset($t['wallet_id']) && ($t['type'] ?? '') !== 'transfer') $t['wallet_id'] = 1;
        if (($t['type'] ?? '') !== 'transfer' && !isset($t['spending_kind'])) $t['spending_kind']=transactionSpendingKind($t);
    }
    unset($t);
}

function financeReadData() {
    $d = readData();
    financeEnsureFeatureData($d);
    return $d;
}

function financeMutate($fn) {
    return mutateData(function (&$d) use ($fn) {
        financeEnsureFeatureData($d);
        return call_user_func_array($fn, [&$d]);
    });
}

function financeRupiah($n) { return 'Rp'.number_format((int)$n,0,',','.'); }

function financeWallets($includeArchived=false) {
    $d = financeReadData();
    return array_values(array_filter($d['wallets'], function($w) use ($includeArchived) {
        return $includeArchived || empty($w['archived']);
    }));
}

function financeWalletById($id) {
    foreach (financeWallets(true) as $w) if ((int)$w['id'] === (int)$id) return $w;
    return null;
}

function financeDefaultWalletId() {
    $wallets = financeWallets();
    return $wallets ? (int)$wallets[0]['id'] : 1;
}

function financeWalletBalances($data=null) {
    $d = is_array($data) ? $data : financeReadData();
    financeEnsureFeatureData($d);
    $balances = [];
    foreach ($d['wallets'] as $w) $balances[(int)$w['id']] = (int)($w['initial_balance'] ?? 0);
    foreach ($d['transactions'] as $t) {
        $type = (string)($t['type'] ?? '');
        $amount = (int)($t['amount'] ?? 0);
        if ($type === 'income') {
            $wid = (int)($t['wallet_id'] ?? 1);
            if (!isset($balances[$wid])) $balances[$wid] = 0;
            $balances[$wid] += $amount;
        } elseif ($type === 'expense') {
            $wid = (int)($t['wallet_id'] ?? 1);
            if (!isset($balances[$wid])) $balances[$wid] = 0;
            $balances[$wid] -= $amount;
        } elseif ($type === 'transfer') {
            $from = (int)($t['from_wallet_id'] ?? 0);
            $to = (int)($t['to_wallet_id'] ?? 0);
            if (!isset($balances[$from])) $balances[$from] = 0;
            if (!isset($balances[$to])) $balances[$to] = 0;
            $balances[$from] -= $amount;
            $balances[$to] += $amount;
        }
    }
    return $balances;
}

function financeWalletsWithBalances() {
    $d = financeReadData();
    $balances = financeWalletBalances($d);
    $out = [];
    foreach ($d['wallets'] as $w) {
        if (!empty($w['archived'])) continue;
        $gross=(int)($balances[(int)$w['id']] ?? 0);
        $reserved=max(0,(int)($w['reserved_balance'] ?? 0));
        $minimum=max(0,(int)($w['minimum_balance'] ?? 0));
        $protected=$reserved+$minimum;
        $w['balance']=$gross;
        $w['reserved_balance']=$reserved;
        $w['minimum_balance']=$minimum;
        $w['protected_balance']=$protected;
        $w['available_balance']=max(0,$gross-$protected);
        $out[] = $w;
    }
    return $out;
}

function financeSaveWallet($input) {
    $id = (int)($input['id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $type = strtolower(trim((string)($input['type'] ?? 'cash')));
    $initial = (int)($input['initial_balance'] ?? 0);
    $reserved = max(0,(int)($input['reserved_balance'] ?? 0));
    $minimum = max(0,(int)($input['minimum_balance'] ?? 0));
    if ($name === '') throw new InvalidArgumentException('Nama dompet/rekening wajib diisi.');
    if (!in_array($type, ['cash','bank','ewallet','savings'], true)) $type = 'cash';
    if ($initial < 0) throw new InvalidArgumentException('Saldo awal tidak boleh negatif.');

    $r = financeMutate(function (&$d) use ($id,$name,$type,$initial,$reserved,$minimum) {
        if ($id > 0) {
            foreach ($d['wallets'] as &$w) if ((int)$w['id'] === $id) {
                $before=$w;
                $w['name'] = substr($name,0,60); $w['type']=$type; $w['initial_balance']=$initial; $w['reserved_balance']=$reserved; $w['minimum_balance']=$minimum; $w['archived']=false;
                auditAdd($d,'update','wallet',$id,$before,$w,true,'Dompet diperbarui');
                return $w;
            }
            unset($w);
            throw new InvalidArgumentException('Dompet tidak ditemukan.');
        }
        $new = ['id'=>(int)$d['meta']['next_wallet_id']++,'name'=>substr($name,0,60),'type'=>$type,'initial_balance'=>$initial,'reserved_balance'=>$reserved,'minimum_balance'=>$minimum,'archived'=>false,'created_at'=>date('Y-m-d H:i:s')];
        $d['wallets'][] = $new;
        auditAdd($d,'create','wallet',(int)$new['id'],null,$new,false,'Dompet dibuat');
        return $new;
    });
    return $r['result'];
}

function financeArchiveWallet($id) {
    $id=(int)$id;
    if ($id <= 0) throw new InvalidArgumentException('Dompet tidak valid.');
    $r = financeMutate(function (&$d) use ($id) {
        $active = array_values(array_filter($d['wallets'], function($w){return empty($w['archived']);}));
        if (count($active) <= 1) throw new InvalidArgumentException('Minimal harus ada satu dompet aktif.');
        foreach ($d['wallets'] as &$w) if ((int)$w['id'] === $id) { $w['archived']=true; return true; }
        unset($w);
        throw new InvalidArgumentException('Dompet tidak ditemukan.');
    });
    return $r['result'];
}

function financeCategories($includeArchived=false) {
    $d=financeReadData();
    return array_values(array_filter($d['categories'], function($c)use($includeArchived){return $includeArchived || empty($c['archived']);}));
}

function financeCategoryById($id) { foreach(financeCategories(true) as $c) if((int)$c['id']===(int)$id)return $c; return null; }
function financeCategoryByName($name) { foreach(financeCategories(true) as $c) if(strcasecmp((string)$c['name'],trim((string)$name))===0)return $c; return null; }

function financeSaveCategory($input) {
    $id=(int)($input['id']??0); $name=trim((string)($input['name']??'')); $type=strtolower(trim((string)($input['type']??'expense'))); $icon=trim((string)($input['icon']??'💳')); $keywords=$input['keywords']??[];
    if($name==='') throw new InvalidArgumentException('Nama kategori wajib diisi.');
    if(!in_array($type,['expense','income','both'],true))$type='expense';
    if(is_string($keywords)) $keywords=preg_split('/\s*,\s*/',trim($keywords),-1,PREG_SPLIT_NO_EMPTY);
    if(!is_array($keywords))$keywords=[];
    $keywords=array_values(array_unique(array_slice(array_filter(array_map(function($x){return strtolower(trim((string)$x));},$keywords)),0,30)));
    $r=financeMutate(function(&$d)use($id,$name,$type,$icon,$keywords){
        foreach($d['categories'] as $c) if(strcasecmp((string)$c['name'],$name)===0 && (int)$c['id']!==$id) throw new InvalidArgumentException('Nama kategori sudah ada.');
        if($id>0){foreach($d['categories'] as &$c)if((int)$c['id']===$id){$c['name']=substr($name,0,50);$c['type']=$type;$c['icon']=$icon?:'💳';$c['keywords']=$keywords;$c['archived']=false;return $c;}unset($c);throw new InvalidArgumentException('Kategori tidak ditemukan.');}
        $new=['id'=>(int)$d['meta']['next_category_id']++,'name'=>substr($name,0,50),'type'=>$type,'icon'=>$icon?:'💳','keywords'=>$keywords,'archived'=>false];$d['categories'][]=$new;return $new;
    }); return $r['result'];
}

function financeArchiveCategory($id){$id=(int)$id;$r=financeMutate(function(&$d)use($id){foreach($d['categories'] as &$c)if((int)$c['id']===$id){$c['archived']=true;return true;}unset($c);throw new InvalidArgumentException('Kategori tidak ditemukan.');});return $r['result'];}

function financeMonthKey($month='') {
    $month=trim((string)$month);
    if(preg_match('/^\d{4}-\d{2}$/',$month)) return $month;
    return date('Y-m');
}

function financeMonthlyBudgets($month='') {
    $month=financeMonthKey($month); $d=financeReadData(); $out=[];
    foreach($d['monthly_budgets'] as $b) if(($b['month']??'')===$month && !empty($b['enabled'])) $out[]=$b;
    return $out;
}

function financeSetMonthlyBudget($categoryId,$limit,$month='') {
    $categoryId=(int)$categoryId; $limit=max(0,(int)$limit); $month=financeMonthKey($month);
    if(!financeCategoryById($categoryId)) throw new InvalidArgumentException('Kategori tidak ditemukan.');
    financeMutate(function(&$d)use($categoryId,$limit,$month){
        foreach($d['monthly_budgets'] as &$b) if((int)($b['category_id']??0)===$categoryId && ($b['month']??'')===$month){$b['limit']=$limit;$b['enabled']=$limit>0;return;}
        unset($b);$d['monthly_budgets'][]=['category_id'=>$categoryId,'month'=>$month,'limit'=>$limit,'enabled'=>$limit>0];
    });
}

function financeMonthlyBudgetStatus($month='') {
    $month=financeMonthKey($month); $budgets=financeMonthlyBudgets($month); $spent=[];
    foreach(allTransactions() as $t){if(($t['type']??'')!=='expense'||strpos((string)($t['transaction_date']??''),$month)!==0)continue;$name=(string)($t['category']??'Lainnya');$spent[strtolower($name)]=($spent[strtolower($name)]??0)+(int)($t['amount']??0);}
    $out=[];
    foreach($budgets as $b){$cat=financeCategoryById((int)$b['category_id']);if(!$cat)continue;$used=(int)($spent[strtolower((string)$cat['name'])]??0);$limit=(int)$b['limit'];$pct=$limit>0?(int)round($used/$limit*100):0;$out[]=['category_id'=>(int)$cat['id'],'category'=>$cat['name'],'icon'=>$cat['icon'],'limit'=>$limit,'spent'=>$used,'remaining'=>max(0,$limit-$used),'over'=>max(0,$used-$limit),'percent'=>$pct,'status'=>$used>$limit?'exceeded':($pct>=80?'warning':'safe')];}
    return $out;
}

function financeAppendTransactionData(&$d,$t) {
    $type=(string)($t['type']??'expense');
    $amount=max(0,(int)($t['amount']??0));
    if($type==='expense') assertWalletSpendAllowedData($d,(int)($t['wallet_id']??1),$amount);
    elseif($type==='transfer') assertWalletSpendAllowedData($d,(int)($t['from_wallet_id']??0),$amount);
    $id=(int)$d['meta']['next_transaction_id']++;
    $x=['id'=>$id,'type'=>$type,'category'=>(string)($t['category']??'Lainnya'),'amount'=>$amount,'note'=>substr(trim((string)($t['note']??'')),0,255),'transaction_date'=>(string)($t['transaction_date']??date('Y-m-d')),'created_at'=>date('Y-m-d H:i:s')];
    if($type==='transfer'){$x['from_wallet_id']=(int)($t['from_wallet_id']??0);$x['to_wallet_id']=(int)($t['to_wallet_id']??0);}
    else {$x['wallet_id']=(int)($t['wallet_id']??1);$x['spending_kind']=transactionSpendingKind(array_merge($t,['type'=>$type]));}
    if(!empty($t['source']))$x['source']=$t['source'];
    if(!empty($t['bill_id']))$x['bill_id']=(int)$t['bill_id'];
    if(isset($t['attachment'])&&is_array($t['attachment']))$x['attachment']=$t['attachment'];
    if(isset($t['ocr'])&&is_array($t['ocr']))$x['ocr']=$t['ocr'];
    $d['transactions'][]=$x;
    auditAdd($d,'create','transaction',$id,null,$x,false,'Transaksi dibuat');
    return $x;
}

function financeTransfer($from,$to,$amount,$note='',$date='') {
    $from=(int)$from;$to=(int)$to;$amount=(int)$amount;$date=$date?:date('Y-m-d');
    if($from<1||$to<1||$from===$to)throw new InvalidArgumentException('Pilih dua dompet yang berbeda.');
    if($amount<=0)throw new InvalidArgumentException('Nominal transfer harus lebih dari nol.');
    if(!financeWalletById($from)||!financeWalletById($to))throw new InvalidArgumentException('Dompet transfer tidak ditemukan.');
    $r=financeMutate(function(&$d)use($from,$to,$amount,$note,$date){return financeAppendTransactionData($d,['type'=>'transfer','category'=>'Transfer Antar Dompet','amount'=>$amount,'note'=>$note?:'Transfer antar dompet','transaction_date'=>$date,'from_wallet_id'=>$from,'to_wallet_id'=>$to,'source'=>'wallet_transfer']);});
    return $r['result'];
}

function financeIsValidDate($date){
    $date=trim((string)$date);
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return false;
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    $errors=DateTimeImmutable::getLastErrors();
    return $dt!==false && ($errors===false || ((int)$errors['warning_count']===0 && (int)$errors['error_count']===0)) && $dt->format('Y-m-d')===$date;
}

function financeSaveBill($input){
    $id=(int)($input['id']??0);
    $name=trim((string)($input['name']??''));
    $amount=max(0,(int)($input['amount']??0));
    $dueDate=trim((string)($input['due_date']??''));
    $category=trim((string)($input['category']??'Tagihan'));
    $wallet=(int)($input['wallet_id']??financeDefaultWalletId());
    $rem=max(0,min(30,(int)($input['reminder_days']??3)));
    $active=isset($input['active'])?(bool)$input['active']:true;

    if($name===''||$amount<=0)throw new InvalidArgumentException('Nama dan nominal tagihan wajib diisi.');
    if(!financeIsValidDate($dueDate))throw new InvalidArgumentException('Tanggal jatuh tempo wajib diisi dan harus valid.');

    $dueDay=(int)substr($dueDate,8,2); // tetap disimpan untuk kompatibilitas data versi lama
    $r=financeMutate(function(&$d)use($id,$name,$amount,$dueDate,$dueDay,$category,$wallet,$rem,$active){
        if($id>0){
            foreach($d['bills'] as &$b)if((int)$b['id']===$id){
                $b=array_merge($b,[
                    'name'=>substr($name,0,80),
                    'amount'=>$amount,
                    'due_date'=>$dueDate,
                    'due_day'=>$dueDay,
                    'schedule_type'=>'once',
                    'category'=>$category,
                    'wallet_id'=>$wallet,
                    'reminder_days'=>$rem,
                    'active'=>$active
                ]);
                return $b;
            }
            unset($b);
            throw new InvalidArgumentException('Tagihan tidak ditemukan.');
        }
        $new=[
            'id'=>(int)$d['meta']['next_bill_id']++,
            'name'=>substr($name,0,80),
            'amount'=>$amount,
            'due_date'=>$dueDate,
            'due_day'=>$dueDay,
            'schedule_type'=>'once',
            'category'=>$category,
            'wallet_id'=>$wallet,
            'reminder_days'=>$rem,
            'active'=>$active,
            'payments'=>[],
            'created_at'=>date('Y-m-d H:i:s')
        ];
        $d['bills'][]=$new;
        return $new;
    });
    return $r['result'];
}
function financeDeleteBill($id){
    $id=(int)$id;
    financeMutate(function(&$d)use($id){
        $d['bills']=array_values(array_filter($d['bills'],function($b)use($id){return(int)$b['id']!==$id;}));
        foreach($d['transactions'] as &$t) if((int)($t['bill_id']??0)===$id){unset($t['bill_id']);if(($t['source']??'')==='bill')$t['source']='manual';$t['updated_at']=date('Y-m-d H:i:s');} unset($t);
    });
}
function financeBills(){return financeReadData()['bills'];}
function financeBillPeriod($date=''){return date('Y-m',strtotime($date?:'now'));}

function financeBillPaymentKey($bill,$referenceDate=''){
    $dueDate=trim((string)($bill['due_date']??''));
    if(financeIsValidDate($dueDate))return 'due:'.$dueDate;
    return financeBillPeriod($referenceDate?:date('Y-m-d'));
}

function financePayBill($id,$date=''){
    $id=(int)$id;
    $date=$date?:date('Y-m-d');
    if(!financeIsValidDate($date))throw new InvalidArgumentException('Tanggal pembayaran tidak valid.');
    $r=financeMutate(function(&$d)use($id,$date){
        foreach($d['bills'] as &$b)if((int)$b['id']===$id){
            $key=financeBillPaymentKey($b,$date);
            if(isset($b['payments'][$key]))throw new InvalidArgumentException('Tagihan ini sudah dibayar.');
            $tx=financeAppendTransactionData($d,[
                'type'=>'expense',
                'category'=>$b['category']??'Tagihan',
                'amount'=>(int)$b['amount'],
                'note'=>'Bayar '.$b['name'],
                'transaction_date'=>$date,
                'wallet_id'=>(int)($b['wallet_id']??1),
                'source'=>'bill',
                'bill_id'=>(int)$b['id'],
                'spending_kind'=>'once'
            ]);
            $b['payments'][$key]=['date'=>$date,'transaction_id'=>$tx['id']];
            return $tx;
        }
        unset($b);
        throw new InvalidArgumentException('Tagihan tidak ditemukan.');
    });
    return $r['result'];
}

function financeBillsStatus($date=''){
    $today=new DateTimeImmutable($date?:'today');
    $period=$today->format('Y-m');
    $daysIn=(int)$today->format('t');
    $out=[];

    foreach(financeBills() as $b){
        if(empty($b['active']))continue;

        $storedDue=trim((string)($b['due_date']??''));
        $isExact=financeIsValidDate($storedDue);
        if($isExact){
            $due=new DateTimeImmutable($storedDue);
            $paymentKey='due:'.$storedDue;
        }else{
            // Kompatibilitas tagihan lama: tetap dianggap tagihan bulanan berdasarkan due_day.
            $day=min(max(1,(int)($b['due_day']??1)),$daysIn);
            $due=new DateTimeImmutable($today->format('Y-m-').sprintf('%02d',$day));
            $paymentKey=$period;
        }

        $paid=isset($b['payments'][$paymentKey]);
        $diff=(int)$today->diff($due)->format('%r%a');
        $status=$paid?'paid':($diff<0?'overdue':($diff<=(int)($b['reminder_days']??3)?'due_soon':'upcoming'));

        $b['period']=$isExact?$storedDue:$period;
        $b['due_date']=$due->format('Y-m-d');
        $b['schedule_type']=$isExact?'once':'monthly_legacy';
        $b['days_left']=$diff;
        $b['status']=$status;
        $b['paid']=$paid;
        $out[]=$b;
    }

    usort($out,function($a,$b){return strcmp($a['due_date'],$b['due_date']);});
    return $out;
}

function financeNextRecurringDate($date,$frequency,$interval){$interval=max(1,(int)$interval);$dt=new DateTimeImmutable($date);if($frequency==='daily')return $dt->modify('+'.$interval.' day')->format('Y-m-d');if($frequency==='weekly')return $dt->modify('+'.$interval.' week')->format('Y-m-d');return $dt->modify('+'.$interval.' month')->format('Y-m-d');}
function financeSaveRecurring($input){$id=(int)($input['id']??0);$name=trim((string)($input['name']??''));$type=strtolower((string)($input['type']??'expense'));$amount=max(0,(int)($input['amount']??0));$category=trim((string)($input['category']??'Lainnya'));$wallet=(int)($input['wallet_id']??financeDefaultWalletId());$freq=strtolower((string)($input['frequency']??'monthly'));$interval=max(1,min(365,(int)($input['interval']??1)));$next=(string)($input['next_run']??date('Y-m-d'));$active=isset($input['active'])?(bool)$input['active']:true;if($name===''||$amount<=0)throw new InvalidArgumentException('Nama dan nominal transaksi berulang wajib diisi.');if(!in_array($type,['expense','income'],true))$type='expense';if(!in_array($freq,['daily','weekly','monthly'],true))$freq='monthly';if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$next))throw new InvalidArgumentException('Tanggal berikutnya tidak valid.');$r=financeMutate(function(&$d)use($id,$name,$type,$amount,$category,$wallet,$freq,$interval,$next,$active){if($id>0){foreach($d['recurring'] as &$x)if((int)$x['id']===$id){$x=array_merge($x,['name'=>substr($name,0,80),'type'=>$type,'amount'=>$amount,'category'=>$category,'wallet_id'=>$wallet,'frequency'=>$freq,'interval'=>$interval,'next_run'=>$next,'active'=>$active]);return $x;}unset($x);throw new InvalidArgumentException('Transaksi berulang tidak ditemukan.');}$new=['id'=>(int)$d['meta']['next_recurring_id']++,'name'=>substr($name,0,80),'type'=>$type,'amount'=>$amount,'category'=>$category,'wallet_id'=>$wallet,'frequency'=>$freq,'interval'=>$interval,'next_run'=>$next,'active'=>$active,'created_at'=>date('Y-m-d H:i:s')];$d['recurring'][]=$new;return $new;});return $r['result'];}
function financeDeleteRecurring($id){financeMutate(function(&$d)use($id){$d['recurring']=array_values(array_filter($d['recurring'],function($x)use($id){return(int)$x['id']!==(int)$id;}));});}
function financeRecurring(){return financeReadData()['recurring'];}
function financeProcessRecurring($throughDate=''){
    $through=$throughDate?:date('Y-m-d');
    $r=financeMutate(function(&$d)use($through){
        $created=[];
        foreach($d['recurring'] as &$x){
            if(empty($x['active']))continue;
            $guard=0;
            while(($x['next_run']??'9999-99-99')<=$through && $guard++<24){
                try{
                    $created[]=financeAppendTransactionData($d,['type'=>$x['type'],'category'=>$x['category'],'amount'=>$x['amount'],'note'=>$x['name'],'transaction_date'=>$x['next_run'],'wallet_id'=>$x['wallet_id'],'source'=>'recurring']);
                    $x['last_run']=$x['next_run'];
                    $x['next_run']=financeNextRecurringDate($x['next_run'],$x['frequency'],$x['interval']);
                    unset($x['last_error'],$x['last_error_at']);
                }catch(InvalidArgumentException $e){
                    // Jangan menembus dana disisihkan / saldo minimum. Biarkan jadwal tetap menunggu.
                    $x['last_error']=$e->getMessage();
                    $x['last_error_at']=date('Y-m-d H:i:s');
                    break;
                }
            }
        }
        unset($x);
        return $created;
    });
    return $r['result'];
}

function financeSaveGoal($input){$id=(int)($input['id']??0);$name=trim((string)($input['name']??''));$target=max(0,(int)($input['target_amount']??0));$current=max(0,(int)($input['current_amount']??0));$deadline=trim((string)($input['deadline']??''));if($name===''||$target<=0)throw new InvalidArgumentException('Nama dan target tabungan wajib diisi.');if($deadline!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$deadline))throw new InvalidArgumentException('Deadline tidak valid.');$r=financeMutate(function(&$d)use($id,$name,$target,$current,$deadline){if($id>0){foreach($d['goals'] as &$g)if((int)$g['id']===$id){$g['name']=substr($name,0,80);$g['target_amount']=$target;$g['current_amount']=min($target,$current);$g['deadline']=$deadline;$g['active']=$g['current_amount']<$target;return $g;}unset($g);throw new InvalidArgumentException('Target tidak ditemukan.');}$new=['id'=>(int)$d['meta']['next_goal_id']++,'name'=>substr($name,0,80),'target_amount'=>$target,'current_amount'=>min($target,$current),'deadline'=>$deadline,'active'=>$current<$target,'created_at'=>date('Y-m-d H:i:s')];$d['goals'][]=$new;return $new;});return $r['result'];}
function financeContributeGoal($id,$amount){$id=(int)$id;$amount=(int)$amount;if($amount<=0)throw new InvalidArgumentException('Nominal progress harus lebih dari nol.');$r=financeMutate(function(&$d)use($id,$amount){foreach($d['goals'] as &$g)if((int)$g['id']===$id){$g['current_amount']=min((int)$g['target_amount'],(int)$g['current_amount']+$amount);$g['active']=$g['current_amount']<$g['target_amount'];return $g;}unset($g);throw new InvalidArgumentException('Target tidak ditemukan.');});return $r['result'];}
function financeDeleteGoal($id){financeMutate(function(&$d)use($id){$d['goals']=array_values(array_filter($d['goals'],function($g)use($id){return(int)$g['id']!==(int)$id;}));});}
function financeGoals(){return financeReadData()['goals'];}
function financeGoalsStatus(){ $out=[];foreach(financeGoals() as $g){$target=max(1,(int)$g['target_amount']);$g['percent']=min(100,(int)round((int)$g['current_amount']/$target*100));$g['remaining']=max(0,$target-(int)$g['current_amount']);if(!empty($g['deadline'])&&$g['remaining']>0){$days=max(1,(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($g['deadline']))->days);$g['recommended_daily']=(int)ceil($g['remaining']/$days);}else $g['recommended_daily']=0;$out[]=$g;}return $out; }

function financeSetPaydayDay($day){$day=max(1,min(31,(int)$day));setSetting('payday_day',$day);return $day;}
function financeNextPaydayDate($day=null,$from=''){$day=$day?:max(1,min(31,(int)setting('payday_day',1)));$now=new DateTimeImmutable($from?:'today');$make=function($base)use($day){$d=min($day,(int)$base->format('t'));return new DateTimeImmutable($base->format('Y-m-').sprintf('%02d',$d));};$candidate=$make($now);if($candidate<=$now)$candidate=$make($now->modify('first day of next month'));return $candidate;}

function financeAnalytics($month=''){
    $month=financeMonthKey($month);$start=new DateTimeImmutable($month.'-01');$end=$start->modify('last day of this month');$prev=$start->modify('-1 month')->format('Y-m');$income=0;$expense=0;$prevExpense=0;$byCat=[];$daily=[];
    foreach(allTransactions() as $t){$date=(string)($t['transaction_date']??'');$type=(string)($t['type']??'');$amount=(int)($t['amount']??0);if(strpos($date,$month)===0){if($type==='income')$income+=$amount;elseif($type==='expense'){$expense+=$amount;$cat=(string)($t['category']??'Lainnya');$byCat[$cat]=($byCat[$cat]??0)+$amount;$daily[$date]=($daily[$date]??0)+$amount;}}elseif(strpos($date,$prev)===0&&$type==='expense')$prevExpense+=$amount;}
    arsort($byCat);ksort($daily);$elapsed=$month===date('Y-m')?(int)date('j'):(int)$end->format('j');$avg=$elapsed>0?(int)round($expense/$elapsed):0;$change=$prevExpense>0?round((($expense-$prevExpense)/$prevExpense)*100,1):null;
    $catRows=[];foreach($byCat as $k=>$v)$catRows[]=['category'=>$k,'total'=>$v,'percent'=>$expense>0?round($v/$expense*100,1):0];$dailyRows=[];foreach($daily as $k=>$v)$dailyRows[]=['date'=>$k,'total'=>$v];
    return ['month'=>$month,'income'=>$income,'expense'=>$expense,'net'=>$income-$expense,'previous_expense'=>$prevExpense,'expense_change_percent'=>$change,'average_daily_expense'=>$avg,'top_category'=>$catRows?$catRows[0]:null,'categories'=>$catRows,'daily'=>$dailyRows,'monthly_budgets'=>financeMonthlyBudgetStatus($month)];
}

function financeUpcomingRecurringUntil($toDate){$income=0;$expense=0;foreach(financeRecurring() as $r){if(empty($r['active']))continue;$next=$r['next_run'];$guard=0;while($next<=$toDate&&$guard++<24){if(($r['type']??'')==='income')$income+=(int)$r['amount'];else$expense+=(int)$r['amount'];$next=financeNextRecurringDate($next,$r['frequency'],$r['interval']);}}return ['income'=>$income,'expense'=>$expense];}
/** Payments already represented by a schedule must not inflate daily spending. */
function financeDailyForecastEligible(array $t): bool {
    if (($t['type']??'')!=='expense' || (int)($t['amount']??0)<=0) return false;
    return transactionSpendingKind($t)==='daily';
}

function financeDailyForecastHistory(array $transactions, string $today): array {
    $now=new DateTimeImmutable($today);$cutoff=$now->modify('-29 days')->format('Y-m-d');
    $first='';$total=0;$excluded=0;$count=0;$todaySpent=0;
    foreach($transactions as $t){
        $date=substr((string)($t['transaction_date']??''),0,10);
        if(($t['type']??'')!=='expense'||(int)($t['amount']??0)<=0||!financeIsValidDate($date)||$date<$cutoff||$date>$today)continue;
        if(!financeDailyForecastEligible($t)){$excluded+=(int)$t['amount'];continue;}
        if($first===''||$date<$first)$first=$date;
        $count++;$total+=(int)$t['amount'];
        if($date===$today)$todaySpent+=(int)$t['amount'];
    }
    $historyDays=$first===''?0:(int)(new DateTimeImmutable($first))->diff($now)->days+1;
    return ['history_days'=>$historyDays,'daily_expense_count'=>$count,'daily_history_total'=>$total,
        'excluded_history_total'=>$excluded,'daily_spent_today'=>$todaySpent,
        'average_daily_expense'=>$historyDays>0?(int)round($total/$historyDays):0];
}

function financeForecastBills(array $bills, string $today, string $through): array {
    $rows=[];
    foreach($bills as $b){
        if(empty($b['active']))continue;
        $stored=trim((string)($b['due_date']??''));
        if(financeIsValidDate($stored)){
            // Exact-date bills are one-off: never roll them into another month.
            if($stored<=$through&&!isset($b['payments']['due:'.$stored])){
                $b['schedule_type']='once';$b['paid']=false;$rows[]=$b;
            }
            continue;
        }
        // Compatibility for legacy monthly bills; include next month only if due before payday.
        $month=(new DateTimeImmutable($today))->modify('first day of this month');
        while($month->format('Y-m-d')<=$through){
            $period=$month->format('Y-m');
            $due=$period.'-'.sprintf('%02d',min(max(1,(int)($b['due_day']??1)),(int)$month->format('t')));
            if($due<=$through&&!isset($b['payments'][$period])){
                $row=$b;$row['due_date']=$due;$row['period']=$period;$row['schedule_type']='monthly_legacy';$row['paid']=false;$rows[]=$row;
            }
            $month=$month->modify('first day of next month');
        }
    }
    usort($rows,function($a,$b){return strcmp($a['due_date'],$b['due_date']);});
    return $rows;
}

function financePrediction(){
    $sum=summary();$payday=financeNextPaydayDate();$today=new DateTimeImmutable('today');
    $days=max(0,(int)$today->diff($payday)->days);
    $history=financeDailyForecastHistory(allTransactions(),$today->format('Y-m-d'));
    $avg=$history['average_daily_expense'];
    // Today's actual purchases have already reduced the current balance.
    $remainingToday=$days>0?max(0,$avg-$history['daily_spent_today']):0;
    $dailyEstimate=$avg*max(0,$days-1)+$remainingToday;
    $beforePayday=$payday->modify('-1 day')->format('Y-m-d');
    $billRows=financeForecastBills(financeBills(),$today->format('Y-m-d'),$beforePayday);
    $billTotal=(int)array_sum(array_column($billRows,'amount'));
    $rec=financeUpcomingRecurringUntil($beforePayday);
    $pred=(int)$sum['balance']-$dailyEstimate-$billTotal+(int)$rec['income']-(int)$rec['expense'];
    $sufficient=$history['daily_expense_count']>0&&$history['history_days']>=7;
    $withoutIncome=$pred-(int)$rec['income'];
    $status=$pred<0?'risk':(!$sufficient?'unknown':($withoutIncome<0?'conditional':($pred<max(100000,$avg*3)?'tight':'safe')));
    return array_merge($history,[
        'payday_date'=>$payday->format('Y-m-d'),'days_left'=>$days,'current_balance'=>(int)$sum['balance'],'gross_balance'=>(int)($sum['gross_balance']??$sum['balance']),'reserved_balance'=>(int)($sum['reserved']??0),'minimum_balance'=>(int)($sum['minimum_balance']??0),'protected_balance'=>(int)($sum['protected_balance']??0),
        'estimated_daily_spend'=>$dailyEstimate,'remaining_daily_spend_today'=>$remainingToday,
        'upcoming_bills'=>$billRows,'upcoming_bills_total'=>$billTotal,
        'recurring_income'=>(int)$rec['income'],'recurring_expense'=>(int)$rec['expense'],
        'predicted_balance'=>$pred,'status'=>$status,'history_sufficient'=>$sufficient
    ]);
}


function financeBillById($id){foreach(financeBills() as $b)if((int)($b['id']??0)===(int)$id)return $b;return null;}

function financeBillCandidatesForTransaction(array $tx): array {
    if(($tx['type']??'')!=='expense') return [];
    $amount=(int)($tx['amount']??0);$text=strtolower(trim((string)($tx['category']??'').' '.(string)($tx['note']??'')));
    $rows=[];
    foreach(financeBillsStatus() as $b){
        if(!empty($b['paid']))continue;
        $score=0;
        if((int)($b['amount']??0)===$amount)$score+=60;
        elseif($amount>0 && abs((int)$b['amount']-$amount)<=max(5000,(int)round($amount*.05)))$score+=25;
        $name=strtolower(trim((string)($b['name']??'')));$cat=strtolower(trim((string)($b['category']??'')));
        if($name!=='' && strpos($text,$name)!==false)$score+=35;
        if($cat!=='' && strpos($text,$cat)!==false)$score+=15;
        if($score>=25){$b['match_score']=$score;$rows[]=$b;}
    }
    usort($rows,function($a,$b){return (int)$b['match_score']<=>(int)$a['match_score'];});
    return array_slice($rows,0,5);
}

function financeLinkTransactionToBill($transactionId,$billId=0){
    $transactionId=(int)$transactionId;$billId=(int)$billId;
    return financeMutate(function(&$d)use($transactionId,$billId){
        $txIndex=null;foreach($d['transactions'] as $i=>$tx)if((int)($tx['id']??0)===$transactionId){$txIndex=$i;break;}
        if($txIndex===null)throw new InvalidArgumentException('Transaksi tidak ditemukan.');
        foreach($d['bills'] as &$b){foreach((array)($b['payments']??[]) as $key=>$payment)if((int)($payment['transaction_id']??0)===$transactionId)unset($b['payments'][$key]);}unset($b);
        if($billId<=0){unset($d['transactions'][$txIndex]['bill_id']);if(($d['transactions'][$txIndex]['source']??'')==='bill')$d['transactions'][$txIndex]['source']='manual';return $d['transactions'][$txIndex];}
        if(($d['transactions'][$txIndex]['type']??'')!=='expense')throw new InvalidArgumentException('Hanya transaksi pengeluaran yang dapat dihubungkan ke tagihan.');
        foreach($d['bills'] as &$b)if((int)($b['id']??0)===$billId){
            $key=financeBillPaymentKey($b,(string)($d['transactions'][$txIndex]['transaction_date']??date('Y-m-d')));
            if(isset($b['payments'][$key]) && (int)($b['payments'][$key]['transaction_id']??0)!==$transactionId)throw new InvalidArgumentException('Tagihan ini sudah terhubung dengan transaksi lain.');
            $date=(string)($d['transactions'][$txIndex]['transaction_date']??date('Y-m-d'));
            $b['payments'][$key]=['date'=>$date,'transaction_id'=>$transactionId];
            $d['transactions'][$txIndex]['bill_id']=$billId;
            $d['transactions'][$txIndex]['source']='bill';
            $d['transactions'][$txIndex]['spending_kind']='once';
            return $d['transactions'][$txIndex];
        }
        unset($b);throw new InvalidArgumentException('Tagihan tidak ditemukan.');
    })['result'];
}

function financePendingChatConfirmation(){ $d=financeReadData(); return !empty($d['pending_chat_confirmation'])?$d['pending_chat_confirmation']:null; }
function financeSetPendingChatConfirmation(array $drafts,$sourceMessage='',$warning=''){
    $rows=[];
    foreach($drafts as $draft){
        $draft['spending_kind']=transactionSpendingKind($draft);
        $draft['bill_candidates']=financeBillCandidatesForTransaction($draft);
        $rows[]=$draft;
    }
    $r=financeMutate(function(&$d)use($rows,$sourceMessage,$warning){
        $id=(int)$d['meta']['next_confirmation_id']++;
        $d['pending_chat_confirmation']=['id'=>$id,'message'=>substr((string)$sourceMessage,0,500),'warning'=>substr((string)$warning,0,250),'drafts'=>$rows,'created_at'=>date('Y-m-d H:i:s')];
        return $d['pending_chat_confirmation'];
    });return $r['result'];
}
function financeClearPendingChatConfirmation(){financeMutate(function(&$d){$d['pending_chat_confirmation']=[];});}
function financeConfirmPendingChat($confirmationId,array $overrides=[]){
    $pending=financePendingChatConfirmation();
    if(!$pending || (int)($pending['id']??0)!==(int)$confirmationId)throw new InvalidArgumentException('Konfirmasi transaksi sudah tidak tersedia.');
    $prepared=[];$billKeys=[];
    foreach((array)$pending['drafts'] as $i=>$draft){
        $ov=(array)($overrides[$i]??[]);
        foreach(['amount','category','transaction_date','wallet_id','spending_kind','bill_id','note'] as $k)if(array_key_exists($k,$ov))$draft[$k]=$ov[$k];
        $draft['amount']=max(0,(int)($draft['amount']??0));if($draft['amount']<=0)throw new InvalidArgumentException('Nominal konfirmasi tidak valid.');
        if(!financeIsValidDate((string)($draft['transaction_date']??'')))throw new InvalidArgumentException('Tanggal konfirmasi tidak valid.');
        $draft['wallet_id']=(int)($draft['wallet_id']??financeDefaultWalletId());
        $draft['spending_kind']=in_array(($draft['spending_kind']??''),['daily','once','recurring'],true)?$draft['spending_kind']:transactionSpendingKind($draft);
        $billId=(int)($draft['bill_id']??0);unset($draft['bill_candidates']);
        if($billId>0){
            $bill=financeBillById($billId);if(!$bill)throw new InvalidArgumentException('Tagihan yang dipilih tidak ditemukan.');
            $key=financeBillPaymentKey($bill,(string)$draft['transaction_date']);$unique=$billId.'|'.$key;
            if(isset($billKeys[$unique]))throw new InvalidArgumentException('Dua draft tidak dapat dipasangkan ke tagihan/periode yang sama.');
            $billKeys[$unique]=true;
            if(isset($bill['payments'][$key]))throw new InvalidArgumentException('Tagihan yang dipilih sudah memiliki pembayaran untuk periode tersebut.');
        }
        $draft['bill_id']=$billId;$prepared[]=$draft;
    }
    // Validasi seluruh draft lebih dulu agar konfirmasi multi-transaksi tidak tersimpan sebagian.
    $requiredByWallet=[];
    foreach($prepared as $draft){
        if(($draft['type']??'expense')!=='expense')continue;
        $wid=(int)($draft['wallet_id']??financeDefaultWalletId());
        $requiredByWallet[$wid]=($requiredByWallet[$wid]??0)+(int)($draft['amount']??0);
    }
    if($requiredByWallet){
        $snapshot=financeReadData();
        foreach($requiredByWallet as $wid=>$needed) assertWalletSpendAllowedData($snapshot,(int)$wid,(int)$needed);
    }
    $saved=[];
    foreach($prepared as $draft){
        $billId=(int)($draft['bill_id']??0);$tx=addTransaction($draft);
        if($billId>0)$tx=financeLinkTransactionToBill((int)$tx['id'],$billId);
        $saved[]=$tx;
    }
    financeClearPendingChatConfirmation();return $saved;
}

function financeAuditHistory($limit=30){$d=financeReadData();$rows=array_reverse((array)$d['audit_log']);return array_slice($rows,0,max(1,min(100,(int)$limit)));}
function financeUndoAudit($auditId){
    $auditId=(int)$auditId;
    $r=financeMutate(function(&$d)use($auditId){
        auditEnsureData($d);$idx=null;foreach($d['audit_log'] as $i=>$row)if((int)($row['id']??0)===$auditId){$idx=$i;break;}
        if($idx===null)throw new InvalidArgumentException('Riwayat perubahan tidak ditemukan.');
        $a=$d['audit_log'][$idx];if(empty($a['undoable'])||!empty($a['undone_at']))throw new InvalidArgumentException('Perubahan ini tidak dapat dibatalkan lagi.');
        $type=(string)($a['entity_type']??'');$id=(int)($a['entity_id']??0);
        if($type==='transaction' && ($a['action']??'')==='delete'){
            foreach($d['transactions'] as $x)if((int)($x['id']??0)===$id)throw new InvalidArgumentException('Transaksi dengan ID ini sudah ada.');
            $row=(array)($a['before']??[]);
            if(($row['type']??'')==='expense') assertWalletSpendAllowedData($d,(int)($row['wallet_id']??financeDefaultWalletId()),(int)($row['amount']??0));
            elseif(($row['type']??'')==='transfer') assertWalletSpendAllowedData($d,(int)($row['from_wallet_id']??0),(int)($row['amount']??0));
            $d['transactions'][]=$row;$d['meta']['next_transaction_id']=max((int)$d['meta']['next_transaction_id'],$id+1);
            if(!empty($row['bill_id'])){foreach($d['bills'] as &$b)if((int)$b['id']===(int)$row['bill_id']){$key=financeBillPaymentKey($b,(string)($row['transaction_date']??date('Y-m-d')));$b['payments'][$key]=['date'=>$row['transaction_date'],'transaction_id'=>$id];}unset($b);}
        } elseif($type==='transaction' && ($a['action']??'')==='update'){
            $row=(array)$a['before'];$found=false;
            if(($row['type']??'')==='expense') assertWalletSpendAllowedData($d,(int)($row['wallet_id']??financeDefaultWalletId()),(int)($row['amount']??0),$id);
            elseif(($row['type']??'')==='transfer') assertWalletSpendAllowedData($d,(int)($row['from_wallet_id']??0),(int)($row['amount']??0),$id);
            foreach($d['bills'] as &$bill){foreach((array)($bill['payments']??[]) as $key=>$payment)if((int)($payment['transaction_id']??0)===$id)unset($bill['payments'][$key]);}unset($bill);
            foreach($d['transactions'] as &$x)if((int)($x['id']??0)===$id){$x=$row;$found=true;break;}unset($x);if(!$found)throw new InvalidArgumentException('Transaksi yang akan dipulihkan sudah tidak ada.');
            if(!empty($row['bill_id'])){foreach($d['bills'] as &$bill)if((int)($bill['id']??0)===(int)$row['bill_id']){$key=financeBillPaymentKey($bill,(string)($row['transaction_date']??date('Y-m-d')));if(isset($bill['payments'][$key])&&(int)($bill['payments'][$key]['transaction_id']??0)!==$id)throw new InvalidArgumentException('Tagihan lama sudah terhubung ke transaksi lain, sehingga perubahan tidak dapat dibatalkan.');$bill['payments'][$key]=['date'=>$row['transaction_date'],'transaction_id'=>$id];break;}unset($bill);}
        } elseif($type==='wallet' && in_array(($a['action']??''),['update','initial_balance'],true)){
            $found=false;foreach($d['wallets'] as &$w)if((int)($w['id']??0)===$id){$w=(array)$a['before'];$found=true;break;}unset($w);if(!$found)throw new InvalidArgumentException('Dompet yang akan dipulihkan tidak ditemukan.');
        } else throw new InvalidArgumentException('Jenis perubahan ini belum mendukung undo.');
        $d['audit_log'][$idx]['undone_at']=date('Y-m-d H:i:s');
        auditAdd($d,'undo',$type,$id,$a['after']??null,$a['before']??null,false,'Membatalkan perubahan #'.$auditId);
        return true;
    });return $r['result'];
}

function financeNotificationSettings(){ $d=financeReadData();return $d['settings']['notifications']; }
function financeSetNotificationSettings($input){$cfg=['enabled'=>!empty($input['enabled']),'daily_budget'=>!empty($input['daily_budget']),'bills'=>!empty($input['bills']),'low_balance'=>!empty($input['low_balance']),'low_balance_threshold'=>max(0,(int)($input['low_balance_threshold']??100000)),'email_enabled'=>!array_key_exists('email_enabled',$input)||!empty($input['email_enabled'])];setSetting('notifications',$cfg);return $cfg;}

function financeFeatureSnapshot(){
    financeProcessRecurring();
    return [
        'wallets'=>financeWalletsWithBalances(),
        'categories'=>financeCategories(),
        'monthly_budgets'=>financeMonthlyBudgetStatus(),
        'bills'=>financeBillsStatus(),
        'recurring'=>financeRecurring(),
        'goals'=>financeGoalsStatus(),
        'analytics'=>financeAnalytics(),
        'prediction'=>financePrediction(),
        'payday_day'=>(int)setting('payday_day',1),
        'notifications'=>financeNotificationSettings(),
        'pending_confirmation'=>financePendingChatConfirmation(),
        'history'=>financeAuditHistory(30),
    ];
}
