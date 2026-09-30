<?php
/**
 * V67 - Piutang / Memberi Hutang
 *
 * Piutang dicatat sebagai transfer ke/dari dompet sistem tersembunyi agar:
 * - uang yang dipinjamkan mengurangi saldo dompet nyata;
 * - pelunasan menambah saldo dompet nyata;
 * - transaksi piutang tidak dianggap pengeluaran/pemasukan konsumtif;
 * - rekap tetap dapat dihitung per peminjam melalui metadata transaksi.
 */

function financeReceivableNormalizeName(string $name): string {
    $name=trim(preg_replace('/\s+/u',' ',$name));
    if(function_exists('mb_strtolower')) $name=mb_strtolower($name,'UTF-8');
    else $name=strtolower($name);
    return $name;
}

function financeReceivableBorrowerKey(string $name): string {
    $normalized=financeReceivableNormalizeName($name);
    return substr(hash('sha256',$normalized),0,24);
}

function financeReceivableValidDate(string $date, bool $allowBlank=false): string {
    $date=trim($date);
    if($date==='' && $allowBlank) return '';
    if($date==='') $date=date('Y-m-d');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new InvalidArgumentException('Tanggal tidak valid.');
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$d || $d->format('Y-m-d')!==$date) throw new InvalidArgumentException('Tanggal tidak valid.');
    return $date;
}

function financeReceivableIsTransaction(array $tx): bool {
    return in_array((string)($tx['source']??''),['receivable_lend','receivable_repayment'],true);
}

function financeReceivableSystemWallet(?array $data=null): ?array {
    $d=is_array($data)?$data:financeReadData();
    foreach((array)($d['wallets']??[]) as $w){
        if(strtolower((string)($w['type']??''))==='receivable' && (string)($w['system_key']??'')==='receivable') return $w;
    }
    return null;
}

function financeReceivableEnsureSystemWallet(): int {
    $existing=financeReceivableSystemWallet();
    if($existing) return (int)$existing['id'];
    $r=financeMutate(function (&$d) {
        foreach((array)$d['wallets'] as $w){
            if(strtolower((string)($w['type']??''))==='receivable' && (string)($w['system_key']??'')==='receivable') return (int)$w['id'];
        }
        $id=max(1,(int)($d['meta']['next_wallet_id']??1));
        $d['meta']['next_wallet_id']=$id+1;
        $maxSort=0;
        foreach((array)$d['wallets'] as $w) $maxSort=max($maxSort,(int)($w['sort_order']??((int)($w['id']??0)*10)));
        $wallet=[
            'id'=>$id,
            'name'=>'Piutang (Sistem)',
            'type'=>'receivable',
            'initial_balance'=>0,
            'reserved_balance'=>0,
            'minimum_balance'=>0,
            'sort_order'=>$maxSort+10,
            'archived'=>true,
            'system_key'=>'receivable',
            'created_at'=>date('Y-m-d H:i:s'),
        ];
        $d['wallets'][]=$wallet;
        auditAdd($d,'create','wallet',$id,null,$wallet,false,'Dompet sistem piutang dibuat');
        return $id;
    });
    return (int)$r['result'];
}

function financeReceivableAllowedWallets(): array {
    return array_values(array_filter(financeWalletsWithBalances(),static function($w){
        $type=strtolower((string)($w['type']??''));
        return !in_array($type,['credit_card','receivable'],true) && empty($w['archived']);
    }));
}

function financeReceivableWalletExists(int $walletId): bool {
    foreach(financeReceivableAllowedWallets() as $w) if((int)($w['id']??0)===$walletId) return true;
    return false;
}

function financeReceivableSnapshot(): array {
    $d=financeReadData();
    $wallets=[];
    foreach((array)($d['wallets']??[]) as $w) $wallets[(int)($w['id']??0)]=(string)($w['name']??'Dompet');

    $people=[];$entries=[];$totalLent=0;$totalRepaid=0;$totalInterest=0;
    foreach((array)($d['transactions']??[]) as $tx){
        if(!financeReceivableIsTransaction((array)$tx)) continue;
        $action=(string)($tx['source']??'')==='receivable_repayment'?'repayment':'lend';
        $name=trim((string)($tx['receivable_borrower_name']??''));
        if($name==='') $name='Tanpa Nama';
        $key=trim((string)($tx['receivable_borrower_key']??''));
        if($key==='') $key=financeReceivableBorrowerKey($name);
        $amount=max(0,(int)($tx['amount']??0));
        if(!isset($people[$key])) $people[$key]=[
            'key'=>$key,'name'=>$name,'lent'=>0,'interest'=>0,'repaid'=>0,'outstanding'=>0,'entries'=>0,'last_date'=>'','last_id'=>0,
            'next_due_date'=>'','overdue'=>false,'loans'=>[],
        ];
        if((int)($tx['id']??0)>=(int)$people[$key]['last_id']) $people[$key]['name']=$name;
        $people[$key]['entries']++;
        $date=(string)($tx['transaction_date']??'');
        if($date>$people[$key]['last_date'] || ($date===$people[$key]['last_date'] && (int)($tx['id']??0)>(int)$people[$key]['last_id'])){
            $people[$key]['last_date']=$date;$people[$key]['last_id']=(int)($tx['id']??0);
        }
        if($action==='lend'){$people[$key]['lent']+=$amount;$totalLent+=$amount;}
        else {$people[$key]['repaid']+=$amount;$totalRepaid+=$amount;}
        $walletId=$action==='lend'?(int)($tx['from_wallet_id']??0):(int)($tx['to_wallet_id']??0);
        $entries[]=[
            'id'=>(int)($tx['id']??0),
            'sort_id'=>(int)($tx['id']??0),
            'action'=>$action,
            'borrower_key'=>$key,
            'borrower_name'=>$name,
            'amount'=>$amount,
            'transaction_date'=>$date,
            'due_date'=>(string)($tx['receivable_due_date']??''),
            'note'=>(string)($tx['note']??''),
            'wallet_id'=>$walletId,
            'wallet_name'=>$wallets[$walletId]??'Dompet',
            'attachment'=>isset($tx['attachment'])&&is_array($tx['attachment'])?$tx['attachment']:null,
            'created_at'=>(string)($tx['created_at']??''),
            'receivable_lend_id'=>max(0,(int)($tx['receivable_lend_id']??0)),
            'allocations'=>[],
        ];
    }

    // V78: bunga disimpan sebagai penyesuaian non-kas di finance_meta extra_json.
    // Karena bukan finance_transaction, penambahan bunga tidak pernah mengubah saldo dompet.
    $interestRows=array_values(array_filter((array)($d['meta']['receivable_interest_entries']??[]),static fn($x)=>is_array($x)));
    foreach($interestRows as $row){
        $interestId=max(0,(int)($row['id']??0));
        $key=trim((string)($row['borrower_key']??''));
        $name=trim((string)($row['borrower_name']??''));
        $amount=max(0,(int)($row['amount']??0));
        if($interestId<=0||$key===''||$amount<=0) continue;
        if($name==='') $name='Tanpa Nama';
        if(!isset($people[$key])) $people[$key]=[
            'key'=>$key,'name'=>$name,'lent'=>0,'interest'=>0,'repaid'=>0,'outstanding'=>0,'entries'=>0,'last_date'=>'','last_id'=>0,
            'next_due_date'=>'','overdue'=>false,'loans'=>[],
        ];
        $people[$key]['interest']+=$amount;$people[$key]['entries']++;$totalInterest+=$amount;
        $date=(string)($row['transaction_date']??'');
        if($date>$people[$key]['last_date'])$people[$key]['last_date']=$date;
        $entries[]=[
            'id'=>-$interestId,
            'interest_id'=>$interestId,
            'sort_id'=>1000000000+$interestId,
            'action'=>'interest',
            'borrower_key'=>$key,
            'borrower_name'=>$name,
            'amount'=>$amount,
            'transaction_date'=>$date,
            'due_date'=>'',
            'note'=>(string)($row['note']??''),
            'wallet_id'=>0,
            'wallet_name'=>'Tidak memengaruhi saldo',
            'attachment'=>null,
            'created_at'=>(string)($row['created_at']??''),
            'receivable_lend_id'=>max(0,(int)($row['lend_id']??0)),
            'interest_rate'=>isset($row['rate_percent'])?(float)$row['rate_percent']:0,
            'interest_base'=>max(0,(int)($row['base_amount']??0)),
            'allocations'=>[],
        ];
    }

    // Alokasi pelunasan diarahkan ke satu hutang tertentu atau FIFO.
    // V78 menambahkan bunga ke nilai hutang target sebelum pelunasan dialokasikan.
    $loansByPerson=[];
    foreach($entries as $e){
        if($e['action']!=='lend') continue;
        $loansByPerson[$e['borrower_key']][(int)$e['id']]=[
            'id'=>(int)$e['id'],'borrower_key'=>(string)$e['borrower_key'],'borrower_name'=>(string)$e['borrower_name'],
            'principal_amount'=>(int)$e['amount'],'interest_amount'=>0,'amount'=>(int)$e['amount'],'repaid'=>0,'remaining'=>(int)$e['amount'],
            'transaction_date'=>(string)$e['transaction_date'],'due_date'=>(string)$e['due_date'],
            'wallet_id'=>(int)$e['wallet_id'],'wallet_name'=>(string)$e['wallet_name'],'note'=>(string)$e['note'],
            'attachment'=>$e['attachment']??null,
        ];
    }
    foreach($entries as &$e){
        if(($e['action']??'')!=='interest')continue;
        $key=(string)$e['borrower_key'];$lendId=max(0,(int)($e['receivable_lend_id']??0));
        if($lendId>0 && isset($loansByPerson[$key][$lendId])){
            $amount=max(0,(int)$e['amount']);
            $loansByPerson[$key][$lendId]['interest_amount']+=$amount;
            $loansByPerson[$key][$lendId]['amount']+=$amount;
            $loansByPerson[$key][$lendId]['remaining']+=$amount;
            $loan=$loansByPerson[$key][$lendId];
            $e['wallet_id']=(int)($loan['wallet_id']??0);
            $e['wallet_name']='Tidak memengaruhi saldo'.(!empty($loan['wallet_name'])?' · terkait '.(string)$loan['wallet_name']:'');
            $e['allocation_label']='Hutang '.financeReportDateLabel((string)$loan['transaction_date']).' · pokok '.financeRupiah((int)$loan['principal_amount']);
        }
    } unset($e);
    foreach($loansByPerson as &$loanMap){
        uasort($loanMap,static function($a,$b){$c=strcmp((string)$a['transaction_date'],(string)$b['transaction_date']);return $c!==0?$c:((int)$a['id']<=>(int)$b['id']);});
    } unset($loanMap);

    $repaymentIndexes=array_values(array_filter(array_keys($entries),static fn($i)=>(string)($entries[$i]['action']??'')==='repayment'));
    usort($repaymentIndexes,static function($ia,$ib)use($entries){$a=$entries[$ia];$b=$entries[$ib];$c=strcmp((string)$a['transaction_date'],(string)$b['transaction_date']);return $c!==0?$c:((int)($a['sort_id']??$a['id'])<=>(int)($b['sort_id']??$b['id']));});
    foreach($repaymentIndexes as $idx){
        $entry=&$entries[$idx];$key=(string)$entry['borrower_key'];$remaining=max(0,(int)$entry['amount']);
        if($remaining<=0 || empty($loansByPerson[$key])){unset($entry);continue;}
        $target=max(0,(int)($entry['receivable_lend_id']??0));
        if($target>0 && isset($loansByPerson[$key][$target])){
            $take=min($remaining,max(0,(int)$loansByPerson[$key][$target]['remaining']));
            if($take>0){
                $loansByPerson[$key][$target]['repaid']+=$take;$loansByPerson[$key][$target]['remaining']-=$take;$remaining-=$take;
                $entry['allocations'][]=['lend_id'=>$target,'amount'=>$take];
            }
        }
        if($remaining>0){
            foreach($loansByPerson[$key] as $lendId=>&$loan){
                if($remaining<=0)break;
                if($target>0 && (int)$lendId===$target)continue;
                $available=max(0,(int)$loan['remaining']);if($available<=0)continue;
                $take=min($remaining,$available);$loan['repaid']+=$take;$loan['remaining']-=$take;$remaining-=$take;
                $entry['allocations'][]=['lend_id'=>(int)$lendId,'amount'=>$take];
            } unset($loan);
        }
        if($target>0 && isset($loansByPerson[$key][$target])){
            $loan=$loansByPerson[$key][$target];
            $entry['allocation_label']='Hutang '.financeReportDateLabel((string)$loan['transaction_date']).' · total '.financeRupiah((int)$loan['amount']);
        } else $entry['allocation_label']='Akumulasi hutang (FIFO)';
        unset($entry);
    }

    $today=date('Y-m-d');
    foreach($people as $key=>&$p){
        $p['outstanding']=max(0,(int)$p['lent']+(int)($p['interest']??0)-(int)$p['repaid']);
        $loans=array_values($loansByPerson[$key]??[]);
        $nextDue='';
        foreach($loans as $loan){
            $due=(string)($loan['due_date']??'');
            if((int)($loan['remaining']??0)>0 && $due!=='' && ($nextDue==='' || $due<$nextDue))$nextDue=$due;
        }
        usort($loans,static function($a,$b){$c=strcmp((string)$b['transaction_date'],(string)$a['transaction_date']);return $c!==0?$c:((int)$b['id']<=>(int)$a['id']);});
        $p['loans']=$loans;
        $p['next_due_date']=$nextDue;
        $p['status']=$p['outstanding']<=0?'paid':'active';
        $p['overdue']=$p['outstanding']>0 && $nextDue!=='' && $nextDue<$today;
        if($p['overdue']) $p['status']='overdue';
    }
    unset($p);
    $people=array_values($people);
    usort($people,static function($a,$b){
        $activeA=(int)($a['outstanding']??0)>0?1:0;$activeB=(int)($b['outstanding']??0)>0?1:0;
        if($activeA!==$activeB)return $activeB<=>$activeA;
        $cmp=(int)($b['outstanding']??0)<=>(int)($a['outstanding']??0);
        if($cmp!==0)return $cmp;
        return strcasecmp((string)$a['name'],(string)$b['name']);
    });
    usort($entries,static function($a,$b){
        $cmp=strcmp((string)$b['transaction_date'],(string)$a['transaction_date']);
        if($cmp!==0)return $cmp;
        return (int)($b['sort_id']??$b['id']??0)<=>(int)($a['sort_id']??$a['id']??0);
    });

    return [
        'summary'=>[
            'total_lent'=>$totalLent,
            'total_interest'=>$totalInterest,
            'total_repaid'=>$totalRepaid,
            'outstanding'=>max(0,$totalLent+$totalInterest-$totalRepaid),
            'borrower_count'=>count($people),
            'active_borrowers'=>count(array_filter($people,static fn($p)=>(int)($p['outstanding']??0)>0)),
        ],
        'people'=>$people,
        'entries'=>$entries,
        'wallets'=>financeReceivableAllowedWallets(),
    ];
}

function financeReportDateLabel(string $date): string {
    $ts=$date!==''?strtotime($date):false;
    return $ts?date('d/m/Y',$ts):($date!==''?$date:'-');
}

function financeReceivableFindPerson(string $key): ?array {
    foreach((array)(financeReceivableSnapshot()['people']??[]) as $p) if(hash_equals((string)$p['key'],$key)) return $p;
    return null;
}

/** V70 - Resolve peminjam ke master yang sudah ada bila dipilih atau nama sama. */
function financeReceivableResolveBorrower(string $name, string $requestedKey=''): array {
    $name=trim(preg_replace('/\s+/u',' ',$name));
    $requestedKey=trim($requestedKey);
    if($requestedKey!==''){
        $person=financeReceivableFindPerson($requestedKey);
        if($person) return ['key'=>(string)$person['key'],'name'=>(string)$person['name'],'existing'=>true];
        // Draft chat untuk orang baru sudah membawa hash nama sebelum transaksi pertama disimpan.
        // Izinkan hanya jika key tersebut benar-benar merupakan hash dari nama yang ikut dikirim.
        if($name!=='' && hash_equals(financeReceivableBorrowerKey($name),$requestedKey)){
            $byName=financeReceivableFindPersonByName($name);
            if($byName) return ['key'=>(string)$byName['key'],'name'=>(string)$byName['name'],'existing'=>true];
            return ['key'=>$requestedKey,'name'=>$name,'existing'=>false];
        }
        throw new InvalidArgumentException('Peminjam yang dipilih tidak ditemukan. Muat ulang data lalu coba lagi.');
    }
    if($name==='') throw new InvalidArgumentException('Nama peminjam wajib diisi.');
    $person=financeReceivableFindPersonByName($name);
    if($person) return ['key'=>(string)$person['key'],'name'=>(string)$person['name'],'existing'=>true];
    return ['key'=>financeReceivableBorrowerKey($name),'name'=>$name,'existing'=>false];
}

function financeReceivableLend(array $input, ?array $attachment=null): array {
    $name=trim(preg_replace('/\s+/u',' ',(string)($input['borrower_name']??'')));
    $resolved=financeReceivableResolveBorrower($name,(string)($input['borrower_key']??''));
    $name=(string)$resolved['name'];
    $key=(string)$resolved['key'];
    if(function_exists('mb_strlen') ? mb_strlen($name,'UTF-8')>80 : strlen($name)>80) throw new InvalidArgumentException('Nama peminjam terlalu panjang.');
    $amount=max(0,(int)($input['amount']??0));
    if($amount<=0) throw new InvalidArgumentException('Nominal hutang harus lebih dari nol.');
    $walletId=(int)($input['wallet_id']??0);
    if($walletId<=0 || !financeReceivableWalletExists($walletId)) throw new InvalidArgumentException('Pilih dompet sumber yang valid.');
    $date=financeReceivableValidDate((string)($input['transaction_date']??date('Y-m-d')));
    $due=financeReceivableValidDate((string)($input['due_date']??''),true);
    if($due!=='' && $due<$date) throw new InvalidArgumentException('Jatuh tempo tidak boleh lebih awal dari tanggal pemberian hutang.');
    $note=substr(trim((string)($input['note']??'')),0,500);
    $systemWalletId=financeReceivableEnsureSystemWallet();

    return addTransaction([
        'type'=>'transfer',
        'category'=>'Piutang · '.$name,
        'amount'=>$amount,
        'note'=>$note,
        'transaction_date'=>$date,
        'from_wallet_id'=>$walletId,
        'to_wallet_id'=>$systemWalletId,
        'source'=>'receivable_lend',
        'attachment'=>$attachment,
        'receivable_borrower_key'=>$key,
        'receivable_borrower_name'=>$name,
        'receivable_action'=>'lend',
        'receivable_due_date'=>$due,
    ]);
}

function financeReceivableRepay(array $input, ?array $attachment=null): array {
    $key=trim((string)($input['borrower_key']??''));
    if($key==='') throw new InvalidArgumentException('Pilih peminjam yang melakukan pelunasan.');
    $person=financeReceivableFindPerson($key);
    if(!$person) throw new InvalidArgumentException('Data peminjam tidak ditemukan.');
    $outstanding=max(0,(int)($person['outstanding']??0));
    if($outstanding<=0) throw new InvalidArgumentException('Piutang peminjam ini sudah lunas.');
    $amount=max(0,(int)($input['amount']??0));
    if($amount<=0) throw new InvalidArgumentException('Nominal pelunasan harus lebih dari nol.');
    if($amount>$outstanding) throw new InvalidArgumentException('Pelunasan melebihi sisa hutang '.financeRupiah($outstanding).'.');

    $lendId=max(0,(int)($input['lend_id']??$input['receivable_lend_id']??0));
    if($lendId>0){
        $target=null;
        foreach((array)($person['loans']??[]) as $loan)if((int)($loan['id']??0)===$lendId){$target=$loan;break;}
        if(!$target) throw new InvalidArgumentException('Hutang yang dipilih tidak ditemukan untuk peminjam ini.');
        $remaining=max(0,(int)($target['remaining']??0));
        if($remaining<=0) throw new InvalidArgumentException('Hutang yang dipilih sudah lunas.');
        if($amount>$remaining) throw new InvalidArgumentException('Pelunasan untuk hutang tersebut maksimal '.financeRupiah($remaining).'. Pilih Akumulasi semua hutang jika ingin membayar lebih besar.');
    }

    $walletId=(int)($input['wallet_id']??0);
    if($walletId<=0 || !financeReceivableWalletExists($walletId)) throw new InvalidArgumentException('Pilih dompet penerima yang valid.');
    $date=financeReceivableValidDate((string)($input['transaction_date']??date('Y-m-d')));
    $note=substr(trim((string)($input['note']??'')),0,500);
    $systemWalletId=financeReceivableEnsureSystemWallet();

    $tx=[
        'type'=>'transfer',
        'category'=>'Pelunasan Piutang · '.(string)$person['name'],
        'amount'=>$amount,
        'note'=>$note,
        'transaction_date'=>$date,
        'from_wallet_id'=>$systemWalletId,
        'to_wallet_id'=>$walletId,
        'source'=>'receivable_repayment',
        'attachment'=>$attachment,
        'receivable_borrower_key'=>$key,
        'receivable_borrower_name'=>(string)$person['name'],
        'receivable_action'=>'repayment',
    ];
    if($lendId>0)$tx['receivable_lend_id']=$lendId;
    return addTransaction($tx);
}


/** V78 - Tambah bunga manual tanpa memengaruhi saldo dompet. */
function financeReceivableAddInterest(array $input): array {
    $key=trim((string)($input['borrower_key']??''));
    if($key==='') throw new InvalidArgumentException('Pilih peminjam yang akan diberi bunga.');
    $person=financeReceivableFindPerson($key);
    if(!$person) throw new InvalidArgumentException('Data peminjam tidak ditemukan.');
    if((int)($person['outstanding']??0)<=0) throw new InvalidArgumentException('Piutang peminjam ini sudah lunas.');

    $lendId=max(0,(int)($input['lend_id']??0));
    if($lendId<=0) throw new InvalidArgumentException('Pilih hutang yang akan dikenakan bunga.');
    $loan=null;
    foreach((array)($person['loans']??[]) as $row) if((int)($row['id']??0)===$lendId){$loan=$row;break;}
    if(!$loan) throw new InvalidArgumentException('Hutang yang dipilih tidak ditemukan.');
    $base=max(0,(int)($loan['remaining']??0));
    if($base<=0) throw new InvalidArgumentException('Hutang yang dipilih sudah lunas.');

    $mode=strtolower(trim((string)($input['mode']??'percent')));
    if(!in_array($mode,['percent','amount'],true))$mode='percent';
    $rate=0.0;$amount=0;
    if($mode==='percent'){
        $raw=str_replace(',','.',trim((string)($input['rate_percent']??'')));
        $rate=(float)$raw;
        if($rate<=0 || $rate>100) throw new InvalidArgumentException('Persentase bunga harus lebih dari 0% dan maksimal 100%.');
        $amount=(int)round($base*$rate/100);
        if($amount<=0)$amount=1;
    }else{
        $amount=max(0,(int)($input['amount']??0));
        if($amount<=0) throw new InvalidArgumentException('Nominal bunga harus lebih dari nol.');
    }
    $date=financeReceivableValidDate((string)($input['transaction_date']??date('Y-m-d')));
    $note=substr(trim((string)($input['note']??'')),0,500);
    $name=(string)$person['name'];

    $r=financeMutate(function (&$d) use($key,$name,$lendId,$mode,$rate,$amount,$base,$date,$note) {
        if(!isset($d['meta']['receivable_interest_entries'])||!is_array($d['meta']['receivable_interest_entries']))$d['meta']['receivable_interest_entries']=[];
        $next=max(1,(int)($d['meta']['next_receivable_interest_id']??1));
        foreach($d['meta']['receivable_interest_entries'] as $row)$next=max($next,(int)($row['id']??0)+1);
        $entry=[
            'id'=>$next,'borrower_key'=>$key,'borrower_name'=>$name,'lend_id'=>$lendId,
            'amount'=>$amount,'mode'=>$mode,'rate_percent'=>$mode==='percent'?$rate:0,'base_amount'=>$base,
            'transaction_date'=>$date,'note'=>$note,'created_at'=>date('Y-m-d H:i:s'),
        ];
        $d['meta']['receivable_interest_entries'][]=$entry;
        $d['meta']['next_receivable_interest_id']=$next+1;
        if(function_exists('auditAdd'))auditAdd($d,'create','receivable_interest',$next,null,$entry,false,'Bunga piutang ditambahkan');
        return $entry;
    });
    return (array)$r['result'];
}

/** V78 - Hapus bunga manual. Saldo dompet tetap tidak berubah. */
function financeReceivableDeleteInterest(int $id): array {
    if($id<=0) throw new InvalidArgumentException('ID bunga tidak valid.');
    $d=financeReadData();$found=null;
    foreach((array)($d['meta']['receivable_interest_entries']??[]) as $row)if((int)($row['id']??0)===$id){$found=$row;break;}
    if(!$found) throw new InvalidArgumentException('Catatan bunga tidak ditemukan.');
    $person=financeReceivableFindPerson((string)($found['borrower_key']??''));
    $amount=max(0,(int)($found['amount']??0));
    if($person && $amount>(int)($person['outstanding']??0)) throw new InvalidArgumentException('Bunga ini belum dapat dihapus karena sebagian nilainya sudah tertutup oleh pelunasan. Koreksi pelunasan terlebih dahulu.');

    $r=financeMutate(function (&$data) use($id,$found) {
        $rows=[];$deleted=false;
        foreach((array)($data['meta']['receivable_interest_entries']??[]) as $row){
            if((int)($row['id']??0)===$id){$deleted=true;continue;}
            $rows[]=$row;
        }
        if(!$deleted) throw new InvalidArgumentException('Catatan bunga tidak ditemukan.');
        $data['meta']['receivable_interest_entries']=$rows;
        if(function_exists('auditAdd'))auditAdd($data,'delete','receivable_interest',$id,$found,null,false,'Bunga piutang dihapus');
        return $found;
    });
    return (array)$r['result'];
}

function financeReceivableDelete(int $id): array {
    if($id<=0) throw new InvalidArgumentException('ID transaksi piutang tidak valid.');
    $tx=transactionById($id);
    if(!$tx || !financeReceivableIsTransaction((array)$tx)) throw new InvalidArgumentException('Transaksi piutang tidak ditemukan.');
    $key=(string)($tx['receivable_borrower_key']??'');
    if($key==='' && !empty($tx['receivable_borrower_name'])) $key=financeReceivableBorrowerKey((string)$tx['receivable_borrower_name']);

    if((string)($tx['source']??'')==='receivable_lend'){
        foreach((array)(financeReadData()['meta']['receivable_interest_entries']??[]) as $interest){
            if((int)($interest['lend_id']??0)===$id) throw new InvalidArgumentException('Pemberian hutang ini sudah memiliki bunga. Hapus catatan bunga terlebih dahulu.');
        }
        $lent=0;$repaid=0;
        foreach(allTransactions() as $row){
            if((int)($row['id']??0)===$id || !financeReceivableIsTransaction((array)$row)) continue;
            if((string)($row['source']??'')==='receivable_repayment' && (int)($row['receivable_lend_id']??0)===$id){
                throw new InvalidArgumentException('Pemberian hutang ini sudah memiliki pelunasan yang ditautkan langsung. Hapus pelunasan tersebut terlebih dahulu.');
            }
            $rowKey=(string)($row['receivable_borrower_key']??'');
            if($rowKey==='' && !empty($row['receivable_borrower_name'])) $rowKey=financeReceivableBorrowerKey((string)$row['receivable_borrower_name']);
            if($rowKey!==$key) continue;
            if((string)($row['source']??'')==='receivable_lend') $lent+=(int)($row['amount']??0);
            else $repaid+=(int)($row['amount']??0);
        }
        if($repaid>$lent) throw new InvalidArgumentException('Pemberian hutang ini belum dapat dihapus karena sudah ada pelunasan yang bergantung padanya. Hapus/koreksi pelunasan terlebih dahulu.');
    }

    $file=(string)($tx['attachment']['file']??'');
    $deleted=deleteTransaction($id);
    if(!$deleted) throw new InvalidArgumentException('Transaksi piutang tidak ditemukan.');
    if($file!=='' && function_exists('deleteReceiptFileIfUnused')) deleteReceiptFileIfUnused($file);
    return $deleted;
}

/** V68 - cari peminjam existing berdasarkan nama normalisasi. */
function financeReceivableFindPersonByName(string $name): ?array {
    $needle=financeReceivableNormalizeName($name);
    if($needle==='')return null;
    foreach((array)(financeReceivableSnapshot()['people']??[]) as $p){
        if(financeReceivableNormalizeName((string)($p['name']??''))===$needle)return $p;
    }
    return null;
}

function financeReceivableWalletFromText(string $message): int {
    $t=financeReceivableNormalizeName($message);
    $best=0;$bestLen=0;
    foreach(financeReceivableAllowedWallets() as $w){
        $name=financeReceivableNormalizeName((string)($w['name']??''));
        $nameLen=function_exists('mb_strlen')?mb_strlen($name,'UTF-8'):strlen($name);
        if($name==='' || $nameLen<$bestLen)continue;
        if(strpos($t,$name)!==false){$best=(int)$w['id'];$bestLen=$nameLen;}
    }
    if($best>0)return $best;
    if(function_exists('walletForText')){
        $candidate=(int)walletForText($message);
        if($candidate>0 && financeReceivableWalletExists($candidate))return $candidate;
    }
    $wallets=financeReceivableAllowedWallets();
    return (int)($wallets[0]['id']??0);
}

function financeReceivableExtractChatBorrower(string $message,string $action): string {
    $raw=trim(preg_replace('/\s+/u',' ',$message));
    $name='';
    if($action==='repayment'){
        if(preg_match('/^(.+?)\s+(?:melunasi|melunaskan|melunaskan|lunasi|membayar|bayar(?:kan)?)\b/iu',$raw,$m))$name=trim($m[1]);
        elseif(preg_match('/^(?:pelunasan|bayar(?:kan)?\s+(?:hutang|utang)|lunasi)\s+(?:dari\s+)?(.+?)\s+(?:rp\s*)?\d/iu',$raw,$m))$name=trim($m[1]);
    } else {
        // Bentuk paling natural: "Utari utang 50rb dari Tunai" / "Utari pinjam 50rb".
        if(preg_match('/^(.+?)\s+(?:hutang|utang|pinjam|meminjam|pinjem)\b/iu',$raw,$m))$name=trim($m[1]);
        elseif(preg_match('/^(?:beri(?:kan)?|memberi|kasih|pinjamkan)\s+(?:hutang\s+|utang\s+)?(?:ke\s+|kepada\s+)?(.+?)\s+(?:rp\s*)?\d/iu',$raw,$m))$name=trim($m[1]);
    }
    $name=preg_replace('/^(?:si|ke|kepada)\s+/iu','',$name);
    $name=trim($name," \t\n\r\0\x0B,.-");
    if(in_array(financeReceivableNormalizeName($name),['saya','aku','kita','kami'],true))return '';
    if(function_exists('mb_substr'))$name=mb_substr($name,0,80,'UTF-8');else $name=substr($name,0,80);
    return $name;
}

/**
 * Deteksi perintah piutang dari Asisten Keuangan.
 * Menghasilkan draft khusus yang tetap wajib dikonfirmasi user sebelum saldo berubah.
 */
function financeReceivableParseChatDraft(string $message): ?array {
    $message=trim($message);if($message==='')return null;
    if(function_exists('smartIsQuestion') && smartIsQuestion($message))return null;
    if(function_exists('isCalculationMessage') && isCalculationMessage($message))return null;
    $t=function_exists('normalizeChatMessage')?normalizeChatMessage($message):financeReceivableNormalizeName($message);

    $action='';
    if(preg_match('/\b(?:melunasi|melunaskan|lunasi|pelunasan|bayar(?:kan)?\s+(?:hutang|utang)|membayar\s+(?:hutang|utang))\b/u',$t))$action='repayment';
    elseif(preg_match('/\b(?:memberi\s+(?:hutang|utang)|beri(?:kan)?\s+(?:hutang|utang)|kasih\s+(?:hutang|utang)|pinjamkan|hutang|utang|pinjam|meminjam|pinjem)\b/u',$t))$action='lend';
    if($action==='')return null;

    $amount=function_exists('transactionAmountFromText')?(int)transactionAmountFromText($t,true):(function_exists('parseAmount')?(int)parseAmount($t):0);
    if($amount<=0)return ['error'=>'Nominal hutang/pelunasan belum terbaca. Contoh: “Nama peminjam utang 50rb dari Tunai”.'];
    $borrower=financeReceivableExtractChatBorrower($message,$action);
    if($borrower==='')return ['error'=>'Nama peminjam belum terbaca. Contoh: “Nama peminjam utang 50rb dari Tunai”.'];

    $walletId=financeReceivableWalletFromText($t);
    if($walletId<=0)return ['error'=>'Belum ada dompet yang dapat dipakai untuk mencatat piutang.'];
    $date=function_exists('detectDate')?(string)detectDate($t):date('Y-m-d');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))$date=date('Y-m-d');

    if($action==='repayment'){
        $person=financeReceivableFindPersonByName($borrower);
        if(!$person)return ['error'=>'Piutang atas nama '.$borrower.' belum ditemukan. Periksa nama peminjam atau catat pemberian hutangnya lebih dulu.'];
        if((int)($person['outstanding']??0)<=0)return ['error'=>'Piutang '.$person['name'].' sudah lunas.'];
        if($amount>(int)$person['outstanding'])return ['error'=>'Pelunasan '.financeRupiah($amount).' melebihi sisa hutang '.$person['name'].' sebesar '.financeRupiah((int)$person['outstanding']).'.'];
        return [
            'type'=>'transfer','amount'=>$amount,'transaction_date'=>$date,'wallet_id'=>$walletId,'note'=>substr($message,0,255),
            'receivable_action'=>'repayment','receivable_borrower_key'=>(string)$person['key'],'receivable_borrower_name'=>(string)$person['name'],
            'receivable_lend_id'=>0,'source'=>'receivable_chat','receivable_loans'=>(array)($person['loans']??[]),
        ];
    }

    $resolved=financeReceivableResolveBorrower($borrower,'');
    return [
        'type'=>'transfer','amount'=>$amount,'transaction_date'=>$date,'wallet_id'=>$walletId,'note'=>substr($message,0,255),
        'receivable_action'=>'lend','receivable_borrower_key'=>(string)$resolved['key'],'receivable_borrower_name'=>(string)$resolved['name'],
        'receivable_lend_id'=>0,'source'=>'receivable_chat',
    ];
}
