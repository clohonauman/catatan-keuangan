<?php
/** Read-only financial questions. All rows come from the authenticated user's store. */
function smartIsQuestion(string $message): bool {
    $t = norm($message);
    if (preg_match('/^(?:ajarkan|pelajari|belajar|balas|jawab)\b/u', $t)) return false;
    if (smartPaydayIntent($t)) return true;
    if (strpos($t, '?') !== false) return true;
    if (preg_match('/\b(?:berapa|brp|brpa|brapa|apakah|kenapa|mengapa|gimana|bagaimana|kapan|mana|tampilkan|lihat|cek|ringkasan|rekap|laporan|bandingkan|dibanding|terbesar|trbsr|terkecil|terakhir|terbanyak|rata-rata|rerata|paling|total|jumlah|kemana|selisih)\b/u', $t)) return true;
    if (preg_match('/\b(?:uang|duit)\s+(?:saya|sya|aku|sy)|\bsisa\s+(?:uang|duit|saldo)/u', $t)) return true;
    // A period without an explicit record command is a report, even with a numeric date.
    return !preg_match('/\b(?:catat|catatkan|beli|bayar|dapat|terima)\b/u', $t)
        && preg_match('/\b(?:pengeluaran|pemasukan|pendapatan|transaksi|saldo)\b/u', $t)
        && preg_match('/\b(?:bulan|minggu|tahun|tanggal|hari ini|kemarin|terakhir)\b/u', $t);
}

function smartDateRange(string $message): array {
    $t = norm($message); $now = new DateTimeImmutable('today');
    $day = $now->format('Y-m-d');
    $months = ['januari'=>1,'februari'=>2,'maret'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'agustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'desember'=>12];
    // A named date/month is evaluated before relative periods. Reject compound ranges explicitly.
    if (preg_match('/\b(?:sampai|hingga|s\.d\.|s\/d)\b/u', $t) || preg_match('/\d\s*[-–]\s*\d/u', $t)) {
        return [null,null,'', 'Untuk rentang tanggal, tanyakan satu hari atau satu bulan dulu, misalnya “pengeluaran tanggal 10 September 2026”.'];
    }
    foreach ($months as $name=>$month) {
        if (!preg_match('/\b(?:(\d{1,2})\s+)?'.$name.'(?:\s+(\d{4}))?\b/u', $t, $m)) continue;
        $year = !empty($m[2]) ? (int)$m[2] : (int)$now->format('Y');
        $date = !empty($m[1]) ? (int)$m[1] : 1;
        if (!checkdate($month, $date, $year)) return [null,null,'','Tanggal itu tidak valid. Coba periksa lagi tanggal, bulan, dan tahunnya.'];
        $start = new DateTimeImmutable(sprintf('%04d-%02d-%02d',$year,$month,$date));
        if (!empty($m[1])) return [$start->format('Y-m-d'),$start->format('Y-m-d'),$date.' '.$name.' '.$year,null];
        return [$start->format('Y-m-d'),$start->modify('last day of this month')->format('Y-m-d'),$name.' '.$year,null];
    }
    if (preg_match('/\btanggal\s+(\d{1,2})(?!\d)/u',$t,$m)) {
        if (preg_match('/\btanggal\s+\d+[\/-]/u',$t)) return [null,null,'','Tulis nama bulannya, misalnya “tanggal 10 September 2026”.'];
        $month = containsText($t,'bulan lalu') ? $now->modify('first day of last month') : $now;
        if (!checkdate((int)$month->format('n'),(int)$m[1],(int)$month->format('Y'))) return [null,null,'','Tanggal itu tidak valid untuk bulan tersebut.'];
        $date = $month->format('Y-m-').sprintf('%02d',(int)$m[1]);
        return [$date,$date,'tanggal '.date('d/m/Y',strtotime($date)),null];
    }
    if (preg_match('/\b(\d{1,4})\s+hari\s+terakhir\b/u',$t,$m)) {
        $days=(int)$m[1];
        if ($days<1 || $days>366) return [null,null,'','Gunakan periode 1 sampai 366 hari terakhir.'];
        return [$now->modify('-'.($days-1).' days')->format('Y-m-d'),$day,$days.' hari terakhir',null];
    }
    if (containsText($t,'kemarin') && !preg_match('/\b(?:bulan|minggu) kemarin\b/u',$t)) { $d=$now->modify('-1 day')->format('Y-m-d');return [$d,$d,'kemarin',null]; }
    if (containsText($t,'hari ini')) return [$day,$day,'hari ini',null];
    if (preg_match('/\bminggu (ini|lalu|kemarin)\b/u',$t,$m)) {
        $start=$now->modify('monday this week');if($m[1]!=='ini')$start=$start->modify('-7 days');
        return [$start->format('Y-m-d'),$start->modify('+6 days')->format('Y-m-d'),'minggu '.$m[1],null];
    }
    if (preg_match('/\bbulan (ini|lalu|kemarin)\b/u',$t,$m)) {
        $start=$now->modify($m[1]==='ini'?'first day of this month':'first day of last month');
        return [$start->format('Y-m-d'),$start->modify('last day of this month')->format('Y-m-d'),'bulan '.$m[1],null];
    }
    if (preg_match('/\btahun (ini|lalu|\d{4})\b/u',$t,$m)) {
        $year=$m[1]==='ini'?(int)$now->format('Y'):($m[1]==='lalu'?(int)$now->format('Y')-1:(int)$m[1]);
        if($year<1)return [null,null,'','Tahun tidak valid.'];
        return [sprintf('%04d-01-01',$year),sprintf('%04d-12-31',$year),'tahun '.$year,null];
    }
    if (preg_match('/\b(?:bulan|minggu|tahun|tanggal|besok|kemarin|hari terakhir)\b|\d{4}[-\/]\d{1,2}|\d{1,2}\/\d{1,2}/u',$t)) return [null,null,'','Periode itu belum saya pahami. Coba “hari ini”, “kemarin”, “minggu lalu”, “bulan lalu”, “7 hari terakhir”, atau “September 2026”.'];
    return [null,null,'seluruh waktu',null];
}

function smartQueryCategory(string $message, string $type): ?string {
    $t=norm($message);
    if (function_exists('financeCategories')) foreach(financeCategories() as $cat) {
        if (!in_array($cat['type']??'both',[$type,'both'],true)) continue;
        foreach(array_merge([(string)($cat['name']??'')],(array)($cat['keywords']??[])) as $word) {
            if ($word!=='' && preg_match('/(?<!\w)'.preg_quote(norm($word),'/').'(?!\w)/u',$t)) return (string)$cat['name'];
        }
    }
    $rules=$type==='income' ? ['Gaji'=>['gaji'],'Bonus'=>['bonus','thr','insentif'],'Penjualan'=>['penjualan','hasil jual']] : [
        'Makan'=>['makan','makanan','kopi','jajan'],'Bensin'=>['bensin','bbm','pertamax','pertalite'],
        'Cicilan'=>['cicilan','angsuran'],'Belanja'=>['belanja','sembako'],
        'Transportasi'=>['transportasi','parkir','gojek','grab'],'Tagihan'=>['listrik','pulsa','internet','wifi','tagihan'],
        'Kesehatan'=>['kesehatan','obat','dokter'],'Hiburan'=>['hiburan','nonton','bioskop']];
    foreach($rules as $cat=>$words)foreach($words as $word)if(preg_match('/\b'.preg_quote($word,'/').'\b/u',$t))return $cat;
    return null;
}

function smartQueryRows(?string $type, ?string $category, ?string $from, ?string $to, ?int $walletId): array {
    return array_values(array_filter(allTransactions(),function($r)use($type,$category,$from,$to,$walletId){
        // Internal transfers are not income or expenditure.
        if (!in_array($r['type']??'',['income','expense'],true)) return false;
        if ($type && ($r['type']??'')!==$type) return false;
        if ($category && strcasecmp((string)($r['category']??''),$category)!==0)return false;
        if ($walletId!==null && (int)($r['wallet_id']??0)!==$walletId)return false;
        $d=substr((string)($r['transaction_date']??''),0,10);
        return (!$from||$d>=$from)&&(!$to||$d<=$to);
    }));
}

function smartPaydayIntent(string $message): bool {
    $t=norm($message);
    if (preg_match('/^(?:ajarkan|pelajari|belajar|balas|jawab)\b/u',$t)) return false;
    return (bool)(preg_match('/\b(?:aman|cukup|bertahan|kurang|habis|solusi|hemat|prediksi|perkiraan)\b/u',$t)
        && preg_match('/\b(?:gajian|gaji berikutnya|terima gaji|tanggal gaji)\b/u',$t));
}

/** Format the existing prediction without writing data or advancing recurring transactions. */
function smartPaydayAnalysisText(array $p, int $expenseCount): string {
    $balance=(int)$p['current_balance'];
    $gross=(int)($p['gross_balance']??$balance);
    $reserved=max(0,(int)($p['reserved_balance']??0));
    $days=max(1,(int)$p['days_left']);
    $average=max(0,(int)$p['average_daily_expense']);
    $dailyTotal=max(0,(int)$p['estimated_daily_spend']);
    $bills=max(0,(int)$p['upcoming_bills_total']);
    $recExpense=max(0,(int)$p['recurring_expense']);
    $recIncome=max(0,(int)$p['recurring_income']);
    $predicted=(int)$p['predicted_balance'];
    $reserve=max(100000,$average*3);
    $withoutIncome=$predicted-$recIncome;
    $expenseCount=(int)($p['daily_expense_count']??$expenseCount);
    $thinHistory=$expenseCount===0 || (int)$p['history_days']<7;
    $date=date('d/m/Y',strtotime($p['payday_date']));
    $lines=[];
    if($predicted<0){
        $lines[]='🔴 Diperkirakan belum aman sampai gajian '.$date.'.';
        $lines[]='Dengan pola saat ini, dana diperkirakan kurang '.rupiah(-$predicted).'.';
    } elseif($thinHistory){
        $lines[]='⚪ Belum cukup data untuk memastikan aman sampai gajian '.$date.'.';
        $lines[]=$expenseCount===0?'Belum ada pengeluaran harian yang dapat dijadikan dasar dalam 30 hari terakhir; pembayaran cicilan/tagihan tidak menjadi pola belanja harian.':'Riwayat pengeluaran kurang dari 7 hari, sehingga pola harian belum cukup mewakili.';
    } elseif($withoutIncome<0){
        $lines[]='🟠 Aman bersyarat sampai gajian '.$date.'.';
        $lines[]='Proyeksi cukup hanya jika pemasukan berulang masuk tepat waktu. Tanpanya, dana diperkirakan kurang '.rupiah(-$withoutIncome).'.';
    } elseif($predicted<$reserve){
        $lines[]='🟡 Diperkirakan cukup, tetapi mepet sampai gajian '.$date.'.';
        $lines[]='Sisa dana '.rupiah($predicted).' masih di bawah cadangan analitik '.rupiah($reserve).' (nilai terbesar antara Rp100.000 dan 3 kali rata-rata harian).';
    } else {
        $lines[]='🟢 Diperkirakan aman sampai gajian '.$date.'.';
        $lines[]='Sisa dana setelah kebutuhan yang diproyeksikan masih '.rupiah($predicted).'.';
    }
    $lines[]="\nDasar perhitungan (total semua dompet):";
    $lines[]='• Saldo tersedia untuk dibelanjakan: '.rupiah($balance);
    if($reserved>0){
        $lines[]='• Saldo total sebelum dana disisihkan: '.rupiah($gross);
        $lines[]='• Dana disisihkan (tidak dipakai untuk proyeksi belanja): '.rupiah($reserved);
    }
    $lines[]='• Waktu menuju gajian: '.$days.' hari';
    $lines[]='• Rata-rata transaksi berjenis Harian: '.rupiah($average).'/hari dari '.(int)$p['history_days'].' hari pengamatan';
    $lines[]='• Estimasi pengeluaran harian sampai sebelum gajian: '.rupiah($dailyTotal);
    if (isset($p['excluded_history_total'])) $lines[]='• Pengeluaran historis yang tidak diproyeksikan sebagai harian: '.rupiah($p['excluded_history_total']).' (tetap masuk laporan dan saldo aktual)';
    if (isset($p['daily_spent_today'])) $lines[]='• Belanja harian hari ini sudah tercatat: '.rupiah($p['daily_spent_today']).'; perkiraan tambahan hari ini '.rupiah($p['remaining_daily_spend_today']).'.';
    $lines[]='• Tagihan belum lunas sebelum gajian, termasuk yang terlambat: '.rupiah($bills);
    $lines[]='• Pengeluaran berulang terjadwal: '.rupiah($recExpense);
    if($recIncome>0)$lines[]='• Pemasukan berulang yang DIHARAPKAN sebelum gajian: '.rupiah($recIncome);
    $lines[]='• Perkiraan sisa dana: '.rupiah($predicted);
    // Label the top bills rather than assuming discretionary spending is the cause.
    $billRows=(array)($p['upcoming_bills']??[]);
    usort($billRows,function($a,$b){return (int)($b['amount']??0)<=>(int)($a['amount']??0);});
    if($billRows){
        $parts=[];foreach(array_slice($billRows,0,3) as $b){
            $name=trim(preg_replace('/\s+/u',' ',(string)($b['name']??'Tagihan')));
            $parts[]=substr($name,0,80).' '.rupiah($b['amount']??0);
        }
        $lines[]='Tagihan terbesar: '.implode('; ',$parts).'.';
    }
    $lines[]="\nYang bisa kamu lakukan:";
    // Use money already on hand for recommendations; future receipts are uncertain.
    $afterFixed=$balance-$bills-$recExpense;
    $breakEven=(int)floor(max(0,$afterFixed)/$days);
    $withReserve=(int)floor(max(0,$afterFixed-$reserve)/$days);
    if($afterFixed<0){
        $lines[]='• Dana saat ini belum menutup tagihan dan pengeluaran berulang: kurang '.rupiah(-$afterFixed).' bahkan sebelum kebutuhan harian. Mengurangi belanja harian saja belum cukup.';
        $lines[]='• Prioritaskan kebutuhan pokok dan kewajiban yang jatuh tempo. Cari pemasukan tambahan yang bisa dipastikan atau bicarakan penjadwalan ulang pembayaran dengan penyedia tagihan.';
    } else {
        $lines[]='• Berdasarkan saldo yang sudah ada, batas hitungan pengeluaran harian adalah '.rupiah($breakEven).'/hari setelah menyisihkan kewajiban, tanpa cadangan.';
        if($withReserve>0){
            $lines[]='• Untuk menyisakan cadangan '.rupiah($reserve).', targetkan maksimal '.rupiah($withReserve).'/hari.';
        } else {
            $lines[]='• Dana belum cukup untuk cadangan '.rupiah($reserve).' sekaligus kebutuhan harian. Tambahan pemasukan atau penyesuaian kewajiban masih diperlukan untuk menyediakan cadangan itu.';
        }
        if($average>$breakEven){
            $lines[]='• Dibanding pola sekarang, pengeluaran perlu berkurang sekitar '.rupiah($average-$breakEven).'/hari untuk mengejar batas tanpa cadangan. Kurangi belanja yang bisa ditunda terlebih dahulu; jika batas ini tidak menutup kebutuhan pokok, cari tambahan dana atau sesuaikan kewajiban.';
        } else {
            $lines[]='• Pertahankan pengeluaran sesuai kemampuan dan sisihkan dana tagihan terlebih dahulu.';
        }
    }
    if($recIncome>0)$lines[]='• Jangan gunakan pemasukan terjadwal sebagai saldo yang sudah tersedia. Pastikan tanggal masuknya lebih awal daripada kewajiban yang harus dibayar.';
    if($thinHistory)$lines[]='• Lengkapi catatan pengeluaran harian dan tagihan sebelum menjadikan perkiraan ini sebagai patokan.';
    $lines[]="\nTanggal gajian mengikuti pengaturan di menu Analitik; sesuaikan jika berbeda. Gaji pada hari gajian belum dihitung untuk membiayai hari-hari sebelumnya.";
    $lines[]='Ini estimasi, bukan jaminan. Cicilan/tagihan yang sudah lunas tidak diproyeksikan lagi; kewajiban belum lunas dihitung sesuai jadwal. Dana disisihkan tidak dianggap sebagai uang belanja. Hubungkan pembayaran ke Tagihan agar kewajiban tidak tercatat ganda.';
    return implode("\n",$lines);
}

function smartPaydayReply(string $message): ?string {
    if(!smartPaydayIntent($message))return null;
    if(function_exists('authHasPremiumAccess')&&!authHasPremiumAccess(authCurrentUser()))return '🔒 Analitik prediksi sampai gajian tersedia untuk akun Premium. Buka menu Premium untuk melihat paket dan status akun.';
    if(!function_exists('financePrediction'))return 'Analitik prediksi belum tersedia. Pastikan file finance_features.php sudah terpasang.';
    if(preg_match('/\btanggal\s+\d|\bgajian\s+(?:tgl\s*)?\d/u',$message))return 'Analisis menggunakan tanggal gajian dari menu Analitik. Sesuaikan tanggalnya di sana, lalu tanyakan “aman sampai gajian?”.';
    $p=financePrediction();
    $cutoff=(new DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');
    $count=0;foreach(allTransactions() as $r){
        $date=substr((string)($r['transaction_date']??''),0,10);
        if(($r['type']??'')==='expense' && (int)($r['amount']??0)>0 && $date>=$cutoff && $date<=date('Y-m-d'))$count++;
    }
    return smartPaydayAnalysisText($p,$count);
}

function smartFinanceReply(string $message): ?string {
    $t=norm($message);
    if (!smartIsQuestion($t)) return null;
    $paydayReply=smartPaydayReply($t);
    if($paydayReply!==null)return $paydayReply;
    if (preg_match('/\b(?:batas|limit|budget|anggaran harian)\b/u',$t)) return null;
    $wallet=walletMentionForText($message);
    $financial=preg_match('/\b(?:pengeluaran|pemasukan|pendapatan|transaksi|saldo|uang|duit|keuangan|makan|bensin|belanja|gaji|boros|hemat|habis|keluar|kategori|ringkasan|rekap|laporan)\b/u',$t);
    if (!$financial && !$wallet) return null;
    $expense=preg_match('/\b(?:pengeluaran|habis|keluar|boros|makan|bensin|belanja)\b/u',$t);
    $income=preg_match('/\b(?:pemasukan|pendapatan|gaji|uang masuk|uang diterima)\b/u',$t);
    $overview=($expense&&$income)||preg_match('/\b(?:ringkasan|rekap|laporan|keuangan|selisih)\b/u',$t);
    $wid=$wallet?(int)$wallet['id']:null;
    $walletLabel=$wallet?' di '.(string)$wallet['name']:'';
    $compare=preg_match('/\b(?:bandingkan|dibanding|dibandingkan|lebih hemat|naik|turun)\b/u',$t);
    if (!$compare && preg_match_all('/\b(?:bulan|minggu|tahun) (?:ini|lalu|kemarin)\b/u',$t)>1) return 'Tanyakan satu periode dulu, atau tulis “bandingkan pengeluaran bulan ini dengan bulan lalu”.';
    if (preg_match('/\b(?:cukup|aman|prediksi|perkiraan)\b/u',$t)) return 'Saya belum menghitung kecukupan uang dari pertanyaan ini. Coba “berapa uang saya?” atau “rata-rata pengeluaran per hari bulan ini” untuk melihat kondisi yang tercatat.';
    [$from,$to,$period,$error]=smartDateRange($t);
    if ($error) return $error;
    if (!$expense&&!$income&&!$overview&&(preg_match('/\b(?:saldo|uang|duit)\b/u',$t)||$wallet)&&!preg_match('/\b(?:terbesar|terkecil|terakhir|kategori|rata-rata|transaksi)\b/u',$t)) {
        if($from || preg_match('/\b(?:awal|dulu|waktu itu)\b/u',$t))return 'Saya bisa menampilkan saldo saat ini. Untuk riwayat periode, coba “ringkasan pengeluaran dan pemasukan bulan ini”.';
        if($wallet&&function_exists('financeWalletsWithBalances')){
            $available=0;$gross=0;$reserved=0;
            foreach(financeWalletsWithBalances() as $w) if((int)($w['id']??0)===$wid){$available=(int)($w['available_balance']??$w['balance']??0);$gross=(int)($w['balance']??0);$reserved=(int)($w['reserved_balance']??0);break;}
            return 'Uangmu'.$walletLabel.' yang tersedia saat ini '.rupiah($available).($reserved>0?' (total '.rupiah($gross).', dana disisihkan '.rupiah($reserved).').':'.');
        }
        $s=summary();return 'Uangmu saat ini '.rupiah($s['balance']).' untuk total semua dompet.';
    }
    if (preg_match('/\b(?:bandingkan|dibanding|dibandingkan|lebih hemat|naik|turun)\b/u',$t)) {
        // Compare explicitly labelled month-to-date windows of equal calendar days.
        if(!containsText($t,'bulan ini')||!preg_match('/\bbulan (?:lalu|kemarin)\b/u',$t)||preg_match('/\b(?:tahun|minggu|tanggal|januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b/u',$t))return 'Untuk perbandingan, coba “bandingkan pengeluaran bulan ini dengan bulan lalu”.';
        $now=new DateTimeImmutable('today');$prev=$now->modify('first day of last month');
        $end=$prev->setDate((int)$prev->format('Y'),(int)$prev->format('n'),min((int)$now->format('j'),(int)$prev->format('t')));
        $type=$income&&!$expense?'income':'expense';$category=smartQueryCategory($t,$type);
        $current=array_sum(array_column(smartQueryRows($type,$category,$now->format('Y-m-01'),$now->format('Y-m-d'),$wid),'amount'));
        $previous=array_sum(array_column(smartQueryRows($type,$category,$prev->format('Y-m-d'),$end->format('Y-m-d'),$wid),'amount'));
        $diff=$current-$previous;$label=$type==='income'?'Pemasukan':'Pengeluaran';
        $change=$diff===0?'Tidak berubah.':($diff>0?'Naik ':'Turun ').rupiah(abs($diff)).($previous>0?' ('.number_format(abs($diff)/$previous*100,1,',','.').'%)':'').'.';
        return $label.($category?' '.$category:'').$walletLabel.":\nBulan ini (1–".$now->format('j/m/Y').'): '.rupiah($current)."\nBulan lalu (1–".$end->format('j/m/Y').'): '.rupiah($previous)."\n".$change.($previous===0?" Persentase perubahan tidak dihitung karena nilai bulan lalu Rp0.":'');
    }
    $type=$income&&!$expense?'income':'expense';
    if(!$expense&&!$income&&!$overview&&!preg_match('/\b(?:transaksi|kategori)\b/u',$t))return 'Saya bisa menjawab dari catatanmu. Coba “berapa uang saya?”, “pengeluaran terbesar bulan ini”, atau “ringkasan keuangan bulan ini”.';
    $category=$overview?null:smartQueryCategory($t,$type);
    $allTypes=$overview||(!$expense&&!$income&&containsText($t,'transaksi'));
    $rows=smartQueryRows($allTypes?null:$type,$category,$from,$to,$wid);
    $label=$allTypes?'transaksi':($type==='income'?'pemasukan':'pengeluaran');
    $context=$label.($category?' '.$category:'').$walletLabel.' '.$period;
    if (!$rows) return 'Belum ada catatan '.$context.'.';
    // Avoid silently ignoring unsupported numeric filters or multiple independent periods.
    if(preg_match('/\b(?:di atas|di bawah|lebih dari|kurang dari|minimal|maksimal)\b/u',$t))return 'Filter nominal belum didukung lewat chat. Gunakan filter di menu Transaksi, atau tanyakan “pengeluaran terbesar bulan ini”.';
    if ($overview) {
        $inc=0;$exp=0;foreach($rows as $r){if($r['type']==='income')$inc+=(int)$r['amount'];else $exp+=(int)$r['amount'];}
        return 'Ringkasan keuangan'.$walletLabel.' '.$period.":\nPemasukan: ".rupiah($inc)."\nPengeluaran: ".rupiah($exp)."\nSelisih pemasukan dan pengeluaran: ".rupiah($inc-$exp)."\nJumlah transaksi: ".count($rows).'. Selisih ini belum termasuk saldo awal; transfer antar-dompet tidak dihitung.';
    }
    $total=(int)array_sum(array_column($rows,'amount'));
    if(!$category && preg_match('/\b(?:kategori|boros|kemana|ke mana|untuk apa)\b/u',$t)){
        $groups=[];foreach($rows as $r){$key=(string)($r['category']??'Lainnya');$groups[$key]=($groups[$key]??0)+(int)$r['amount'];}arsort($groups);
        $lines=[];$n=0;foreach($groups as $cat=>$amount){if(++$n>5)break;$lines[]=$n.'. '.$cat.': '.rupiah($amount).' ('.number_format($total>0?$amount/$total*100:0,1,',','.').'%)';}
        return 'Kategori '.$context.' berdasarkan total nominal (maksimal 5):' ."\n".implode("\n",$lines)."\nTotal seluruh kategori: ".rupiah($total).'.';
    }
    if(preg_match('/\b(?:rata-rata|rerata)\b/u',$t)){
        if(containsText($t,'harian')||containsText($t,'per hari')){
            if(!$from)return 'Sebutkan periodenya dulu, misalnya “rata-rata pengeluaran per hari bulan ini”.';
            $end=min($to,date('Y-m-d'));
            if($from>$end)return 'Periode tersebut belum dimulai.';
            $days=(new DateTimeImmutable($from))->diff(new DateTimeImmutable($end))->days+1;
            $elapsed=smartQueryRows($type,$category,$from,$end,$wid);$amount=(int)array_sum(array_column($elapsed,'amount'));
            return 'Rata-rata '.$context.': '.rupiah(round($amount/$days)).' per hari. Total '.rupiah($amount).' dibagi '.$days.' hari kalender (termasuk hari tanpa transaksi), sampai '.date('d/m/Y',strtotime($end)).'.';
        }
        return 'Rata-rata '.$context.': '.rupiah(round($total/count($rows))).' per transaksi, dari '.count($rows).' transaksi dengan total '.rupiah($total).'.';
    }
    $rankingText=preg_replace('/\b\d+\s+hari\s+terakhir\b/u','',$t);
    if(preg_match('/\b(?:terbesar|tertinggi|terbanyak|termahal|terkecil|terendah|termurah|terakhir|terbaru|paling besar|paling kecil)\b/u',$rankingText)){
        $recent=preg_match('/\b(?:terakhir|terbaru)\b/u',$rankingText);
        $small=preg_match('/\b(?:terkecil|terendah|termurah|paling kecil)\b/u',$t);
        usort($rows,function($a,$b)use($recent,$small){
            if($recent)return strcmp((string)$b['transaction_date'],(string)$a['transaction_date'])?:((int)($b['id']??0)<=>(int)($a['id']??0));
            $cmp=(int)$b['amount']<=>(int)$a['amount'];return ($small?-$cmp:$cmp)?:strcmp((string)$b['transaction_date'],(string)$a['transaction_date']);
        });
        $limit=1;if(preg_match('/\b(?:top\s+)?(\d{1,2})\s+(?:(?:pengeluaran|pemasukan|transaksi)\s+)?(?:terbesar|terkecil|terakhir|terbaru|tertinggi|terendah)\b/u',$t,$m))$limit=max(1,min(10,(int)$m[1]));
        $lines=[];foreach(array_slice($rows,0,$limit) as $i=>$r){
            $note=trim(preg_replace('/\s+/u',' ',(string)($r['note']??'')));
            $lines[]=($i+1).'. '.rupiah($r['amount']).' — '.($r['category']??'Lainnya').' — '.date('d/m/Y',strtotime($r['transaction_date'])).($note!==''?' ('.substr($note,0,120).')':'');
        }
        return ucfirst($context).' yang '.($recent?'terakhir':($small?'terkecil':'terbesar'))." (maksimal $limit):\n".implode("\n",$lines)."\nTotal ".$context.': '.rupiah($total).' dari '.count($rows).' transaksi.';
    }
    if(preg_match('/\b(?:jumlah|berapa banyak)\s+transaksi\b|\bberapa kali\b/u',$t))return 'Ada '.count($rows).' '.$context.', dengan total '.rupiah($total).'.';
    return 'Total '.$context.' sebesar '.rupiah($total).' dari '.count($rows).' transaksi.';
}
