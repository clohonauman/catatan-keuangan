<?php
/**
 * Conversational layer for the native/rule-based finance assistant.
 *
 * This file intentionally does not call an external AI service. It adds:
 * - conservative slang normalization;
 * - richer short follow-up context;
 * - casual/small-talk responses;
 * - simple utility questions;
 * - wallet/protected-fund explanations;
 * - budget and savings-goal summaries;
 * - "what if I spend ..." simulations;
 * - natural-language edits while a transaction confirmation is pending.
 *
 * It expects native_assistant.php + finance_features.php to be loaded first.
 */

function humanoidPick(array $items, string $seed=''): string {
    if (!$items) return '';
    $key = $seed !== '' ? $seed : date('Y-m-d-H');
    $index = abs((int)crc32($key)) % count($items);
    return (string)$items[$index];
}

function humanoidNormalizeMessage(string $message): string {
    $t = normalizeChatMessage($message);
    if ($t === '') return '';

    $aliases = [
        'klo'=>'kalau','kalo'=>'kalau','klau'=>'kalau',
        'gk'=>'tidak','ga'=>'tidak','gak'=>'tidak','nggak'=>'tidak','ngga'=>'tidak','nda'=>'tidak','ndak'=>'tidak','nyanda'=>'tidak',
        'yg'=>'yang','jd'=>'jadi','jdi'=>'jadi','knp'=>'kenapa','gmn'=>'gimana','gmna'=>'gimana',
        'udh'=>'sudah','udah'=>'sudah','sdh'=>'sudah','blm'=>'belum','msh'=>'masih',
        'makasi'=>'makasih','mksh'=>'makasih','thx'=>'makasih','thanks'=>'makasih',
        'trs'=>'terus','trus'=>'terus','aja'=>'saja','aj'=>'saja',
        'pake'=>'pakai','pke'=>'pakai','dr'=>'dari',
        'duitku'=>'uang saya','uangku'=>'uang saya','saldoku'=>'saldo saya',
    ];

    $t = preg_replace_callback('/(?<![a-z0-9])([a-z]{1,12})(?![a-z0-9])/u', function($m) use ($aliases) {
        $w = strtolower((string)$m[1]);
        return $aliases[$w] ?? $w;
    }, $t);

    $phrases = [
        '/\bapa kabar\b/u' => 'apa kabar',
        '/\bberapa duit saya\b/u' => 'berapa uang saya',
        '/\bberapa uangku\b/u' => 'berapa uang saya',
        '/\bmasih aman tidak\b/u' => 'masih aman tidak',
    ];
    foreach ($phrases as $pattern=>$replacement) $t = preg_replace($pattern,$replacement,$t);
    return trim(preg_replace('/\s+/u',' ',$t));
}

function humanoidPreviousUserQuestion(string $current=''): string {
    $chats = recentChats(40);
    for ($i=count($chats)-1; $i>=0; $i--) {
        $row = $chats[$i];
        if (($row['role'] ?? '') !== 'user') continue;
        $candidate = trim((string)($row['message'] ?? ''));
        if ($candidate === '' || norm($candidate) === norm($current)) continue;
        $candidate = humanoidNormalizeMessage($candidate);
        if (smartIsQuestion($candidate) || preg_match('/\b(?:saldo|pengeluaran|pemasukan|budget|anggaran|tagihan|cicilan|dompet|rekening|gajian)\b/u',norm($candidate))) {
            return $candidate;
        }
    }
    return '';
}

function humanoidReplaceWalletMention(string $base, array $wantedWallet): string {
    $wantedName = trim((string)($wantedWallet['name'] ?? ''));
    if ($wantedName === '' || !function_exists('financeWallets')) return $base;
    $replaced = false;
    foreach (financeWallets(true) as $w) {
        $old = trim((string)($w['name'] ?? ''));
        if ($old === '' || strcasecmp($old,$wantedName)===0) continue;
        $pattern = '/(?<![a-z0-9])'.preg_quote($old,'/').'(?![a-z0-9])/iu';
        if (preg_match($pattern,$base)) {
            $base = preg_replace($pattern,$wantedName,$base,1);
            $replaced = true;
            break;
        }
    }
    if (!$replaced && !walletMentionForText($base)) $base .= ' '.$wantedName;
    return trim($base);
}

/**
 * Extends the existing follow-up resolver for short conversational replies.
 * Examples:
 *   "berapa saldo SeaBank?" -> "kalau BCA?"
 *   "pengeluaran terbesar bulan ini?" -> "yang makan?"
 */
function humanoidResolveContext(string $message): string {
    $current = humanoidNormalizeMessage($message);
    if ($current === '') return '';

    // Pesan pencatatan baru tidak boleh diwarisi konteks pertanyaan sebelumnya.
    // Sebelumnya semua pesan pendek yang menyebut dompet (mis. SeaBank) dapat
    // salah dianggap follow-up analitik.
    if (function_exists('looksLikeTransactionStatement') && looksLikeTransactionStatement($current)) return $current;

    $resolved = assistantResolveFollowup($current);
    $t = norm($current);
    $short = strlen($t) <= 100;
    if (!$short) return $resolved;

    $wallet = walletMentionForText($current);
    $category = null;
    if (preg_match('/^(?:yang|kalau|terus|untuk|kategori)?\s*([a-z][a-z ]{1,30})\??$/u',$t,$m)) {
        $guess = trim((string)$m[1]);
        foreach (financeCategories() as $cat) {
            if (strcasecmp($guess,norm((string)($cat['name']??'')))===0) {$category=(string)$cat['name']; break;}
            foreach ((array)($cat['keywords']??[]) as $kw) if ($guess===norm((string)$kw)) {$category=(string)$cat['name']; break 2;}
        }
    }

    $explicitFollowup = (bool)preg_match('/^(?:kalau|yang|terus|lalu|kalau yang|untuk|di|dari)\b/u',$t);
    $entityOnlyFollowup = false;
    if (($wallet || $category) && (!function_exists('looksLikeTransactionStatement') || !looksLikeTransactionStatement($current))) {
        $tokens = preg_split('/[^a-z0-9]+/u',$t,-1,PREG_SPLIT_NO_EMPTY);
        // "Seabank?" / "BCA" / "yang makan?" tetap bisa mengikuti pertanyaan
        // sebelumnya, tetapi kalimat transaksi lengkap tidak.
        $entityOnlyFollowup = smartIsQuestion($current) || count($tokens)<=3;
    }
    $looksShortFollowup = $explicitFollowup || $entityOnlyFollowup;
    if (!$looksShortFollowup) return $resolved;

    // If the built-in resolver already expanded it substantially, retain it.
    if (norm($resolved)!==$t && strlen($resolved) > strlen($current)+8) {
        if ($wallet) $resolved = humanoidReplaceWalletMention($resolved,$wallet);
        return humanoidNormalizeMessage($resolved);
    }

    $previous = humanoidPreviousUserQuestion($current);
    if ($previous === '') return $resolved;
    $base = $previous;
    if ($wallet) $base = humanoidReplaceWalletMention($base,$wallet);
    if ($category && stripos($base,$category)===false) $base .= ' '.$category;
    if (preg_match('/\b(?:pengeluaran|pemasukan|pendapatan)\b/u',$t,$m)) {
        $base = preg_replace('/\b(?:pengeluaran|pemasukan|pendapatan)\b/u',$m[0],$base,1,$count);
        if (!$count) $base .= ' '.$m[0];
    }
    return humanoidNormalizeMessage($base);
}

function humanoidAmountFromText(string $message,bool $allowSmallPlain=false): int {
    // Pakai parser nominal yang sama dengan pencatatan transaksi agar angka
    // plat/tanggal/kode tidak ikut masuk ke simulasi atau koreksi draft.
    return function_exists('transactionAmountFromText')
        ? transactionAmountFromText($message,$allowSmallPlain)
        : 0;
}

function humanoidIndonesianDate(DateTimeInterface $d): string {
    $days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $months=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $days[(int)$d->format('w')].', '.$d->format('j').' '.$months[(int)$d->format('n')].' '.$d->format('Y');
}

function humanoidWalletSnapshot(?array $wallet=null): array {
    if ($wallet) {
        foreach (financeWalletsWithBalances() as $w) if ((int)($w['id']??0)===(int)($wallet['id']??0)) return $w;
        return [];
    }
    $sum=summary();
    return [
        'name'=>'semua dompet',
        'balance'=>(int)($sum['gross_balance']??$sum['balance']??0),
        'available_balance'=>(int)($sum['balance']??0),
        'reserved_balance'=>(int)($sum['reserved']??0),
        'minimum_balance'=>(int)($sum['minimum_balance']??0),
        'protected_balance'=>(int)($sum['protected_balance']??0),
    ];
}

function humanoidProtectedFundsReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/\b(?:dana disisihkan|saldo minimum|saldo mengendap|uang terlindungi|dana terlindungi|tidak bisa dipakai|tidak dapat dipakai|saldo tersedia|benar-benar bisa dipakai|bisa dipakai)\b/u',$t)) return null;
    $wallet=walletMentionForText($message);
    $s=humanoidWalletSnapshot($wallet);
    if(!$s) return null;
    $name=$wallet?(string)($s['name']??'Dompet'):'semua dompet';
    $gross=(int)($s['balance']??0);$reserved=(int)($s['reserved_balance']??0);$minimum=(int)($s['minimum_balance']??0);$available=(int)($s['available_balance']??0);
    return "Rincian {$name}:\n"
        .'• Saldo aktual: '.rupiah($gross)."\n"
        .'• Dana disisihkan: '.rupiah($reserved)."\n"
        .'• Saldo minimum: '.rupiah($minimum)."\n"
        .'• Yang benar-benar bisa dipakai: '.rupiah($available).'.';
}

function humanoidWalletRankingReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/\b(?:dompet|rekening)\b/u',$t) || !preg_match('/\b(?:paling banyak|terbesar|tertinggi|paling sedikit|terkecil|terendah|saldo terbanyak|saldo paling)\b/u',$t)) return null;
    $rows=financeWalletsWithBalances();
    if(!$rows) return 'Belum ada dompet/rekening aktif.';
    $small=(bool)preg_match('/\b(?:paling sedikit|terkecil|terendah)\b/u',$t);
    usort($rows,function($a,$b)use($small){$cmp=(int)($b['available_balance']??0)<=>(int)($a['available_balance']??0);return $small?-$cmp:$cmp;});
    $w=$rows[0];
    return ($small?'Saldo tersedia paling kecil ada di ':'Saldo tersedia paling besar ada di ').(string)$w['name'].': '.rupiah((int)$w['available_balance']).'. Saldo aktual '.rupiah((int)$w['balance']).', dengan dana terlindungi '.rupiah((int)($w['protected_balance']??0)).'.';
}

function humanoidBudgetReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/\b(?:budget|anggaran)\b/u',$t) || preg_match('/\b(?:harian|per hari|hari ini)\b/u',$t)) return null;
    $rows=financeMonthlyBudgetStatus();
    if(!$rows) return 'Belum ada budget kategori aktif untuk bulan ini.';
    $wanted='';
    foreach($rows as $r){$cat=norm((string)($r['category']??''));if($cat!==''&&preg_match('/(?<!\w)'.preg_quote($cat,'/').'(?!\w)/u',$t)){$wanted=(string)$r['category'];break;}}
    if($wanted!==''){
        foreach($rows as $r)if(strcasecmp((string)$r['category'],$wanted)===0){
            $status=(int)$r['over']>0?'melewati budget '.rupiah((int)$r['over']):'tersisa '.rupiah((int)$r['remaining']);
            return 'Budget '.$r['category'].' bulan ini '.rupiah((int)$r['limit']).'. Sudah terpakai '.rupiah((int)$r['spent']).' ('.(int)$r['percent'].'%), '.$status.'.';
        }
    }
    usort($rows,function($a,$b){return (int)$b['percent']<=>(int)$a['percent'];});
    $lines=['Budget kategori bulan ini:'];
    foreach(array_slice($rows,0,5) as $r)$lines[]='• '.$r['category'].': '.rupiah((int)$r['spent']).' / '.rupiah((int)$r['limit']).' ('.(int)$r['percent'].'%)';
    return implode("\n",$lines);
}

function humanoidGoalReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/\b(?:target tabungan|target menabung|goal|tujuan tabungan|progress tabungan|progres tabungan)\b/u',$t)) return null;
    $rows=financeGoalsStatus();
    if(!$rows)return 'Belum ada target tabungan yang dibuat.';
    $lines=['Progress target tabungan:'];
    foreach(array_slice($rows,0,5) as $g){
        $lines[]='• '.(string)$g['name'].': '.rupiah((int)$g['current_amount']).' / '.rupiah((int)$g['target_amount']).' ('.(int)$g['percent'].'%), sisa '.rupiah((int)$g['remaining']).'.';
    }
    return implode("\n",$lines);
}

function humanoidPaydayWhenReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/\b(?:kapan gajian|berapa hari lagi gajian|gajian kapan|hari menuju gajian)\b/u',$t)) return null;
    if(!function_exists('financeNextPaydayDate')) return null;
    $date=financeNextPaydayDate();$today=new DateTimeImmutable('today');$days=(int)$today->diff($date)->days;
    return 'Gajian berikutnya berdasarkan pengaturan aplikasi: '.humanoidIndonesianDate($date).'. Masih '.$days.' hari lagi.';
}

function humanoidHypotheticalReply(string $message): ?string {
    $t=norm($message);
    $intent=(bool)preg_match('/\b(?:kalau|misal|misalnya|jika|andaikan)\b.*\b(?:beli|bayar|pakai|keluar|habis)\b/u',$t)
        || (bool)preg_match('/\b(?:bisa|boleh|aman)\b.*\b(?:beli|bayar|keluar)\b/u',$t);
    if(!$intent) return null;
    $amount=humanoidAmountFromText($message);
    if($amount<=0)return 'Bisa saya simulasikan. Sebutkan nominalnya, misalnya “kalau saya beli sepatu 500rb, masih aman?”.';
    $wallet=walletMentionForText($message);$s=humanoidWalletSnapshot($wallet);
    if(!$s)return null;
    $available=(int)($s['available_balance']??0);$after=$available-$amount;$label=$wallet?(string)($s['name']??'dompet'):'semua dompet';
    if($after<0){
        return 'Kalau pengeluaran '.rupiah($amount).' diambil dari '.$label.', dana tersedia belum cukup. Kekurangannya sekitar '.rupiah(-$after).'. Dana disisihkan dan saldo minimum tidak saya anggap bisa dipakai.';
    }
    $reply='Kalau mengeluarkan '.rupiah($amount).' dari '.$label.', saldo yang benar-benar tersedia akan turun dari '.rupiah($available).' menjadi sekitar '.rupiah($after).'.';
    if(function_exists('financePrediction') && (!function_exists('authHasPremiumAccess') || authHasPremiumAccess(authCurrentUser()))){
        $p=financePrediction();$pred=(int)($p['predicted_balance']??0)-$amount;
        if($pred<0)$reply.=' Dengan pola dan tagihan yang tercatat sekarang, proyeksi sampai gajian menjadi kurang sekitar '.rupiah(-$pred).'.';
        elseif($pred<100000)$reply.=' Proyeksi sisa sampai gajian menjadi sekitar '.rupiah($pred).', jadi ruangnya cukup tipis.';
        else $reply.=' Proyeksi sisa sampai gajian menjadi sekitar '.rupiah($pred).'.';
    }
    $reply.=' Ini simulasi berdasarkan data yang sudah tercatat, bukan transaksi nyata.';
    return $reply;
}



function humanoidMonthName(int $month): string {
    $months=[1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    return $months[$month] ?? '';
}

function humanoidMonthLabel(DateTimeInterface $date): string {
    return humanoidMonthName((int)$date->format('n')).' '.$date->format('Y');
}

/** Resolve the month being discussed without turning a plain transaction into analysis. */
function humanoidMonthlyTarget(string $message): DateTimeImmutable {
    $t=norm($message);$now=new DateTimeImmutable('today');
    if(preg_match('/\\bbulan (?:lalu|kemarin)\\b/u',$t)) return $now->modify('first day of last month');
    $months=['januari'=>1,'februari'=>2,'maret'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'agustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'desember'=>12];
    foreach($months as $name=>$number){
        if(!preg_match('/\\b'.$name.'(?:\\s+(\\d{4}))?\\b/u',$t,$m))continue;
        $year=!empty($m[1])?(int)$m[1]:(int)$now->format('Y');
        return new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$number));
    }
    return $now->modify('first day of this month');
}

function humanoidMonthlyStats(DateTimeInterface $month, ?int $throughDay=null): array {
    $key=$month->format('Y-m');$income=0;$expense=0;$count=0;$categories=[];$kinds=['daily'=>0,'once'=>0,'recurring'=>0];$top=null;
    foreach((array)allTransactions() as $row){
        $date=substr((string)($row['transaction_date']??''),0,10);
        if(substr($date,0,7)!==$key)continue;
        if($throughDay!==null && (int)substr($date,8,2)>$throughDay)continue;
        $type=(string)($row['type']??'');$amount=max(0,(int)($row['amount']??0));
        if($type==='income'){$income+=$amount;$count++;continue;}
        if($type!=='expense')continue;
        $expense+=$amount;$count++;
        $cat=trim((string)($row['category']??'Lainnya')) ?: 'Lainnya';
        $categories[$cat]=($categories[$cat]??0)+$amount;
        $kind=function_exists('transactionSpendingKind')?transactionSpendingKind($row):(string)($row['spending_kind']??'once');
        if(!isset($kinds[$kind]))$kind='once';$kinds[$kind]+=$amount;
        if($top===null || $amount>(int)($top['amount']??0))$top=$row;
    }
    arsort($categories);
    return ['month'=>$key,'income'=>$income,'expense'=>$expense,'net'=>$income-$expense,'count'=>$count,'categories'=>$categories,'kinds'=>$kinds,'top'=>$top];
}

function humanoidPercentChange(int $current,int $previous): ?float {
    if($previous===0)return $current===0?0.0:null;
    return (($current-$previous)/$previous)*100;
}

function humanoidMonthlyComparisonSentence(string $label,int $current,int $previous): string {
    $change=humanoidPercentChange($current,$previous);
    if($change===null)return $label.' '.rupiah($current).' (periode pembanding sebelumnya belum memiliki nilai).';
    if(abs($change)<0.5)return $label.' '.rupiah($current).' — relatif sama dengan periode pembanding.';
    return $label.' '.rupiah($current).' — '.($change>0?'naik ':'turun ').number_format(abs($change),1,',','.').'% dari '.rupiah($previous).'.';
}

function humanoidMonthlyProjection(DateTimeImmutable $month,array $stats): array {
    $today=new DateTimeImmutable('today');
    // Projection is only meaningful for the current calendar month.
    if($month->format('Y-m')!==$today->format('Y-m'))return ['available'=>false];
    $end=$today->modify('last day of this month');$remaining=max(0,(int)$today->diff($end)->days);
    $dailyAverage=0;$historyDays=0;$dailyCount=0;
    if(function_exists('financeDailyForecastHistory')){
        $history=financeDailyForecastHistory(allTransactions(),$today->format('Y-m-d'));
        $dailyAverage=max(0,(int)($history['average_daily_expense']??0));
        $historyDays=max(0,(int)($history['history_days']??0));
        $dailyCount=max(0,(int)($history['daily_expense_count']??0));
    } else {
        $elapsed=max(1,(int)$today->format('j'));
        $dailyAverage=(int)round(((int)($stats['kinds']['daily']??0))/$elapsed);
        $historyDays=$elapsed;$dailyCount=$dailyAverage>0?1:0;
    }
    $dailyRemaining=$dailyAverage*$remaining;
    $billTotal=0;$billRows=[];
    if(function_exists('financeForecastBills') && function_exists('financeBills')){
        $start=$today->modify('+1 day')->format('Y-m-d');
        $billRows=financeForecastBills(financeBills(),$start,$end->format('Y-m-d'));
        foreach((array)$billRows as $b)$billTotal+=max(0,(int)($b['amount']??0));
    }
    $recIncome=0;$recExpense=0;
    if(function_exists('financeUpcomingRecurringUntil')){
        $rec=financeUpcomingRecurringUntil($end->format('Y-m-d'));
        $recIncome=max(0,(int)($rec['income']??0));$recExpense=max(0,(int)($rec['expense']??0));
    }
    $projectedExpense=(int)$stats['expense']+$dailyRemaining+$billTotal+$recExpense;
    $projectedIncome=(int)$stats['income']+$recIncome;
    $availableNow=(int)(summary()['balance']??0);
    $availableEnd=$availableNow-$dailyRemaining-$billTotal-$recExpense+$recIncome;
    return [
        'available'=>true,'remaining_days'=>$remaining,'daily_average'=>$dailyAverage,'daily_remaining'=>$dailyRemaining,
        'bills'=>$billTotal,'recurring_expense'=>$recExpense,'recurring_income'=>$recIncome,
        'projected_expense'=>$projectedExpense,'projected_income'=>$projectedIncome,'projected_net'=>$projectedIncome-$projectedExpense,
        'available_now'=>$availableNow,'available_end'=>$availableEnd,'history_days'=>$historyDays,'daily_count'=>$dailyCount
    ];
}

function humanoidMonthlyTrendReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/\\b(?:tren|trend|pola|perbandingan|bandingkan)\\b.*\\b(?:bulan|bulanan)\\b/u',$t) && !preg_match('/\\b(?:3|4|5|6|12)\\s*bulan terakhir\\b/u',$t))return null;
    $months=3;if(preg_match('/\\b(\\d{1,2})\\s*bulan\\b/u',$t,$m))$months=max(2,min(12,(int)$m[1]));
    $now=new DateTimeImmutable('first day of this month');$rows=[];
    for($i=$months-1;$i>=0;$i--){$m=$now->modify('-'.$i.' months');$rows[]=[$m,humanoidMonthlyStats($m)];}
    $lines=['Tren '.$months.' bulan terakhir:'];
    foreach($rows as [$m,$s]){
        $partial=$m->format('Y-m')===$now->format('Y-m')?' (sampai hari ini)':'';
        if((int)($s['count']??0)===0){$lines[]='• '.humanoidMonthLabel($m).$partial.': belum ada data transaksi.';continue;}
        $lines[]='• '.humanoidMonthLabel($m).$partial.': pemasukan '.rupiah($s['income']).', pengeluaran '.rupiah($s['expense']).', net '.($s['net']>=0?'+':'-').rupiah(abs($s['net'])).'.';
    }
    $full=array_filter($rows,function($pair)use($now){return $pair[0]->format('Y-m')!==$now->format('Y-m') && (int)($pair[1]['count']??0)>0;});
    if($full){$avg=(int)round(array_sum(array_map(fn($x)=>(int)$x[1]['expense'],$full))/count($full));$lines[]='Rata-rata pengeluaran bulan penuh pada periode ini: '.rupiah($avg).'.';}
    return implode("\n",$lines);
}

/** Rich monthly analysis: actuals, fair same-date comparison, spending pattern and end-of-month projection. */
function humanoidMonthlyAnalysisReply(string $message): ?string {
    $t=norm($message);
    $isMonthly=(bool)preg_match('/\b(?:analisis|analisa|evaluasi|review|ringkasan|kondisi|pola|proyeksi|perkiraan)\b.*\b(?:bulan|bulanan|akhir bulan)\b/u',$t)
        || (bool)preg_match('/\b(?:bulan ini|bulan lalu)\b.*\b(?:boros|hemat|aman|sehat|bagus|buruk)\b/u',$t)
        || (bool)preg_match('/\b(?:aman|cukup)\b.*\b(?:sampai )?akhir bulan\b/u',$t)
        || (bool)preg_match('/\b(?:tren|trend)\b.*\b(?:bulan|bulanan)\b/u',$t)
        || (bool)preg_match('/\b\d{1,2}\s*bulan terakhir\b/u',$t);
    if(!$isMonthly)return null;
    // Explicit multi-month trend is handled by the trend renderer.
    $trend=humanoidMonthlyTrendReply($message);if($trend!==null)return $trend;

    $target=humanoidMonthlyTarget($message);$stats=humanoidMonthlyStats($target);$now=new DateTimeImmutable('today');
    if((int)($stats['count']??0)===0)return 'Belum ada data pemasukan/pengeluaran untuk '.humanoidMonthLabel($target).', jadi pola bulanan belum bisa dianalisis.';
    $isCurrent=$target->format('Y-m')===$now->format('Y-m');
    $label=humanoidMonthLabel($target);
    $elapsed=$isCurrent?(int)$now->format('j'):(int)$target->format('t');
    $prev=$target->modify('first day of last month');$sameDay=min($elapsed,(int)$prev->format('t'));
    $currentComparable=humanoidMonthlyStats($target,$sameDay);$previousComparable=humanoidMonthlyStats($prev,$sameDay);

    $lines=['📊 Analisis '.$label.($isCurrent?' (sampai hari ini)':'').':'];
    $lines[]='• Pemasukan: '.rupiah($stats['income']);
    $lines[]='• Pengeluaran: '.rupiah($stats['expense']);
    $lines[]='• Arus bersih: '.($stats['net']>=0?'+':'-').rupiah(abs($stats['net']));
    if($stats['income']>0){$saveRate=($stats['net']/$stats['income'])*100;$lines[]='• Rasio sisa dari pemasukan: '.number_format($saveRate,1,',','.').'%. ';}

    $lines[]="\nPerbandingan tanggal 1–{$sameDay} dengan ".humanoidMonthLabel($prev).':';
    $lines[]='• '.humanoidMonthlyComparisonSentence('Pengeluaran',$currentComparable['expense'],$previousComparable['expense']);
    $lines[]='• '.humanoidMonthlyComparisonSentence('Pemasukan',$currentComparable['income'],$previousComparable['income']);
    $expenseChange=humanoidPercentChange((int)$currentComparable['expense'],(int)$previousComparable['expense']);
    if($expenseChange!==null){
        if($expenseChange>10)$lines[]='↗️ Pola belanja pada periode setara sedang lebih tinggi dari bulan sebelumnya.';
        elseif($expenseChange<-10)$lines[]='↘️ Pola belanja pada periode setara sedang lebih rendah dari bulan sebelumnya.';
        else $lines[]='➡️ Pola belanja pada periode setara relatif stabil dibanding bulan sebelumnya.';
    }

    if($stats['categories']){
        $topCats=array_slice($stats['categories'],0,3,true);$parts=[];foreach($topCats as $cat=>$amount)$parts[]=$cat.' '.rupiah($amount);
        $lines[]="\nKategori pengeluaran terbesar: ".implode('; ',$parts).'.';
    }
    $k=$stats['kinds'];
    $lines[]='Pola pengeluaran: Harian '.rupiah((int)$k['daily']).' · Sekali bayar '.rupiah((int)$k['once']).' · Berulang '.rupiah((int)$k['recurring']).'.';
    if($isCurrent && function_exists('financeMonthlyBudgetStatus')){
        $budgets=financeMonthlyBudgetStatus();$risk=[];
        foreach((array)$budgets as $b)if((int)($b['percent']??0)>=80)$risk[]=(string)($b['category']??'Kategori').' '.(int)($b['percent']??0).'%';
        if($risk)$lines[]='Budget yang perlu diperhatikan: '.implode('; ',array_slice($risk,0,4)).'.';
    }

    if($isCurrent){
        $p=humanoidMonthlyProjection($target,$stats);
        if(!empty($p['available'])){
            $lines[]="\nProyeksi sampai akhir bulan:";
            if((int)$p['daily_count']===0)$lines[]='• Belum ada pola transaksi Harian yang cukup; transaksi Sekali Bayar tidak saya gandakan ke hari-hari berikutnya.';
            else $lines[]='• Rata-rata pola Harian: '.rupiah($p['daily_average']).'/hari; estimasi tambahan '.$p['remaining_days'].' hari: '.rupiah($p['daily_remaining']).'.';
            if($p['bills']>0)$lines[]='• Tagihan belum lunas sampai akhir bulan: '.rupiah($p['bills']).'.';
            if($p['recurring_expense']>0)$lines[]='• Pengeluaran berulang terjadwal: '.rupiah($p['recurring_expense']).'.';
            if($p['recurring_income']>0)$lines[]='• Pemasukan berulang yang diharapkan: '.rupiah($p['recurring_income']).'.';
            $lines[]='• Perkiraan total pengeluaran bulan: '.rupiah($p['projected_expense']).'.';
            $lines[]='• Perkiraan saldo tersedia akhir bulan: '.rupiah($p['available_end']).'.';
            if($p['available_end']<0)$lines[]='⚠️ Dengan data saat ini, saldo tersedia berpotensi tidak cukup sebelum bulan berakhir.';
            elseif($p['available_end']<100000)$lines[]='🟡 Masih positif, tetapi ruang saldo tersedia diperkirakan cukup tipis.';
            else $lines[]='🟢 Berdasarkan data yang tercatat, saldo tersedia masih diproyeksikan positif di akhir bulan.';
        }
    }
    return implode("\n",$lines);
}

function humanoidUtilityReply(string $message): ?string {
    $t=norm($message);
    if(preg_match('/\b(?:hari apa|tanggal berapa|tanggal sekarang|hari ini tanggal berapa)\b/u',$t)){
        return 'Sekarang '.humanoidIndonesianDate(new DateTimeImmutable('now')).'.';
    }
    if(preg_match('/\b(?:jam berapa|pukul berapa|waktu sekarang)\b/u',$t)){
        return 'Sekarang pukul '.date('H:i').' (waktu server aplikasi).';
    }
    return null;
}

function humanoidHelpReply(string $message): ?string {
    $t=norm($message);
    if(!preg_match('/^(?:help|bantuan|kamu bisa apa|bisa apa saja|bisa ngapain|fitur chat|contoh pertanyaan|cara pakai chat)\??$/u',$t)) return null;
    return "Saya bisa bantu dengan bahasa biasa. Contohnya:\n"
        ."• Catat: “makan 25rb tadi siang dari BCA”\n"
        ."• Koreksi sebelum simpan: “eh, ubah jadi 20rb” atau “pakai SeaBank saja”\n"
        ."• Tanya: “berapa uang saya?”, “kemarin habis berapa?”, “kalau bulan lalu?”\n"
        ."• Analisis: “aman sampai gajian?”, “analisis bulan ini”, “tren 6 bulan”\n"
        ."• Simulasi: “kalau beli sepatu 500rb masih aman?”\n"
        ."• Budget: “sisa budget makan berapa?”\n"
        ."• Dompet: “saldo BCA yang benar-benar bisa dipakai berapa?”\n"
        ."• Tagihan: “cicilan apa yang belum lunas?”\n"
        ."• Target: “progress target tabungan saya”.";
}

function humanoidSmallTalkReply(string $message): ?string {
    $t=trim(norm($message)," \t\n\r\0\x0B.!?");
    if($t==='')return null;
    if(preg_match('/^(?:hai|halo|hello|hei|hey|pagi|selamat pagi|siang|selamat siang|sore|selamat sore|malam|selamat malam)$/u',$t)){
        return humanoidPick([
            'Halo 👋 Mau cek kondisi keuangan atau langsung catat transaksi?',
            'Hai 👋 Saya siap. Bisa tanya saldo, pengeluaran, tagihan, atau langsung bilang transaksi yang mau dicatat.',
            'Halo! Ada yang mau dicek dari keuanganmu hari ini?'
        ],$t.date('Y-m-d'));
    }
    if(preg_match('/^(?:makasih|terima kasih|sip makasih|oke makasih|thanks)$/u',$t))return humanoidPick(['Sama-sama 👌','Siap, sama-sama.','Dengan senang hati.'],date('Y-m-d-H'));
    if(preg_match('/^(?:sip|mantap|nice|oke|ok|good|bagus)$/u',$t))return humanoidPick(['Siap 👌','Mantap. Kalau ada transaksi lain, tinggal tulis saja.','Oke, lanjut kalau ada yang mau dicek.'],$t.date('H'));
    if(preg_match('/^(?:siapa kamu|kamu siapa|nama kamu siapa|kamu ai|kamu manusia)$/u',$t))return 'Saya asisten keuangan bawaan Catatan Keuangan. Saya bekerja rule-based dari data akunmu dan tidak perlu mengirim percakapan ke layanan AI eksternal.';
    if(preg_match('/^(?:apa kabar|gimana kabarmu|bagaimana kabarmu)$/u',$t))return 'Baik dan siap bantu 😄 Mau cek saldo, pengeluaran, atau ada transaksi yang mau dicatat?';
    if(preg_match('/^(?:bye|dadah|sampai nanti|sampai jumpa)$/u',$t))return 'Siap, sampai nanti 👋';
    if(preg_match('/\b(?:capek|pusing)\b/u',$t) && strlen($t)<80)return 'Kalau mau yang praktis, tinggal tulis singkat saja—misalnya “saldo”, “aman sampai gajian?”, atau “makan 20rb”. Saya yang rapikan.';
    return null;
}

/**
 * Read-only/simple conversational intents that should run before the normal
 * transaction extractor, especially hypothetical purchase questions.
 */
function humanoidDirectReply(string $message): ?string {
    foreach([
        'humanoidHelpReply','humanoidSmallTalkReply','humanoidUtilityReply',
        'humanoidPaydayWhenReply','humanoidProtectedFundsReply','humanoidWalletRankingReply',
        'humanoidMonthlyAnalysisReply','humanoidBudgetReply','humanoidGoalReply','humanoidHypotheticalReply'
    ] as $fn){$reply=$fn($message);if($reply!==null)return $reply;}
    return null;
}

function humanoidPendingTargetIndexes(array $pending,string $message): array {
    $count=count((array)($pending['drafts']??[]));if($count<1)return [];
    $t=norm($message);
    if(preg_match('/\b(?:semua|keduanya)\b/u',$t))return range(0,$count-1);
    $map=['pertama'=>1,'kesatu'=>1,'kedua'=>2,'ketiga'=>3,'keempat'=>4,'kelima'=>5];
    foreach($map as $word=>$number)if(preg_match('/\b'.$word.'\b/u',$t))return $number<=$count?[$number-1]:[];
    if(preg_match('/\b(?:transaksi|nomor|no\.?)\s*(\d{1,2})\b/u',$t,$m)){$n=(int)$m[1];return $n>=1&&$n<=$count?[$n-1]:[];}
    return [0];
}

/**
 * Lets the user correct a pending transaction card in natural language.
 * Nothing is committed to the ledger here; it only edits the draft.
 */
function humanoidEditPendingConfirmation(string $message,array $pending): ?array {
    $t=norm($message);
    if(!preg_match('/\b(?:ubah|ganti|koreksi|salah|harusnya|seharusnya|jadikan|pakai|gunakan|dari|tanggal|kategori|catatan|keterangan|nominal|harian|sekali|berulang|rutin|pemasukan|pengeluaran|transfer)\b/u',$t))return null;
    if(preg_match('/^(?:ya|iya|iyo|ok|oke|simpan|catat|lanjut|konfirmasi|batal|batalkan|jangan)$/u',$t))return null;

    $indexes=humanoidPendingTargetIndexes($pending,$message);if(!$indexes)return null;
    $changes=[];
    $amount=0;
    if(preg_match('/\b(?:ubah|ganti|koreksi|nominal|harusnya|seharusnya|jadi)\b/u',$t))$amount=humanoidAmountFromText($message,true);
    $wallet=walletMentionForText($message);
    $hasDate=(bool)preg_match('/\b(?:hari ini|kemarin|besok|lusa|tanggal)\b|\b\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?\b/u',$t);
    $date=$hasDate?detectDate($message):'';
    $type='';
    if(preg_match('/\b(?:jadi|jadikan|ubah|ganti|harusnya|seharusnya)?\s*(?:pemasukan|pendapatan|uang masuk)\b/u',$t))$type='income';
    elseif(preg_match('/\b(?:jadi|jadikan|ubah|ganti|harusnya|seharusnya)?\s*(?:pengeluaran|uang keluar)\b/u',$t))$type='expense';
    elseif(preg_match('/\b(?:jadi|jadikan|ubah|ganti|harusnya|seharusnya)?\s*(?:transfer antar dompet|transfer)\b/u',$t))$type='transfer';

    $kind='';
    if(preg_match('/\b(?:harian|setiap hari|tiap hari)\b/u',$t))$kind='daily';
    elseif(preg_match('/\b(?:berulang|rutin|bulanan|mingguan|langganan)\b/u',$t))$kind='recurring';
    elseif(preg_match('/\b(?:sekali bayar|sekali saja|satu kali|non rutin|non-rutin)\b/u',$t))$kind='once';
    $category='';
    if(preg_match('/\b(?:kategori|masuk kategori|jadikan kategori)\b/u',$t)){
        $category=categoryFor($message,(string)(($pending['drafts'][$indexes[0]]['type']??'expense')));
        if($category==='Lainnya'&&!preg_match('/\blainnya\b/u',$t))$category='';
    }
    $note=null;
    if(preg_match('/\b(?:catatan|keterangan)(?:nya)?\s*(?:jadi|:)?\s*(.+)$/u',$t,$m))$note=trim((string)$m[1]);

    if($amount<=0&&!$wallet&&$date===''&&$type===''&&$kind===''&&$category===''&&$note===null)return null;
    $id=(int)($pending['id']??0);
    $r=financeMutate(function(&$d)use($id,$indexes,$amount,$wallet,$date,$type,$kind,$category,$note,&$changes){
        if(empty($d['pending_chat_confirmation'])||(int)($d['pending_chat_confirmation']['id']??0)!==$id)throw new InvalidArgumentException('Konfirmasi transaksi sudah tidak tersedia.');
        foreach($indexes as $idx){
            if(!isset($d['pending_chat_confirmation']['drafts'][$idx]))continue;
            $draft=&$d['pending_chat_confirmation']['drafts'][$idx];
            if($amount>0){$draft['amount']=$amount;$changes['nominal']=rupiah($amount);}
            if($type!==''){
                $draft['type']=$type;
                $changes['jenis']=['income'=>'Pemasukan','expense'=>'Pengeluaran','transfer'=>'Transfer Antar Dompet'][$type]??$type;
                if($type==='transfer'){
                    $draft['category']='Transfer Antar Dompet';$draft['bill_id']=0;$draft['spending_kind']='once';
                    $fallback=(int)($draft['wallet_id']??financeDefaultWalletId());
                    $draft['from_wallet_id']=(int)($draft['from_wallet_id']??$fallback);
                    $draft['to_wallet_id']=(int)($draft['to_wallet_id']??0);
                } else {
                    if(empty($draft['wallet_id']))$draft['wallet_id']=(int)($draft['from_wallet_id']??financeDefaultWalletId());
                    unset($draft['from_wallet_id'],$draft['to_wallet_id']);
                    if($type==='income'){$draft['spending_kind']='once';$draft['bill_id']=0;}
                }
            }
            if($wallet){
                if(($draft['type']??'expense')==='transfer'){$draft['from_wallet_id']=(int)$wallet['id'];$changes['dompet asal']=(string)$wallet['name'];}
                else {$draft['wallet_id']=(int)$wallet['id'];$changes['dompet']=(string)$wallet['name'];}
            }
            if($date!==''){$draft['transaction_date']=$date;$changes['tanggal']=date('d/m/Y',strtotime($date));}
            if($kind!==''&&($draft['type']??'expense')==='expense'){$draft['spending_kind']=$kind;$changes['pola']=['daily'=>'Harian','once'=>'Sekali bayar','recurring'=>'Berulang'][$kind]??$kind;}
            if($category!==''){$draft['category']=$category;$changes['kategori']=$category;}
            if($note!==null){$draft['note']=substr($note,0,255);$changes['catatan']=$draft['note'];}
            $draft['bill_candidates']=financeBillCandidatesForTransaction($draft);
            unset($draft);
        }
        $d['pending_chat_confirmation']['updated_at']=date('Y-m-d H:i:s');
        return $d['pending_chat_confirmation'];
    });
    $updated=$r['result'];
    $parts=[];foreach($changes as $k=>$v)$parts[]=$k.' → '.$v;
    return ['pending'=>$updated,'message'=>'Sip, saya perbaiki draft: '.implode(' · ',$parts).'.'];
}
