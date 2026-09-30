<?php
/** V68 - Filter & PDF report khusus Piutang. */

function financeReceivableReportFilters(array $input): array {
    $status=strtolower(trim((string)($input['status']??'all')));
    if(!in_array($status,['all','active','paid','overdue'],true))$status='all';
    $action=strtolower(trim((string)($input['action']??'all')));
    if(!in_array($action,['all','lend','interest','repayment'],true))$action='all';
    $from=trim((string)($input['from']??''));$to=trim((string)($input['to']??''));
    foreach(['from'=>$from,'to'=>$to] as $label=>$value){
        if($value!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))throw new InvalidArgumentException('Filter tanggal tidak valid.');
    }
    if($from!==''&&$to!==''&&$from>$to)throw new InvalidArgumentException('Tanggal awal tidak boleh melewati tanggal akhir.');
    $key=trim((string)($input['borrower_key']??''));
    if($key!==''&&!preg_match('/^[a-f0-9]{24}$/i',$key))throw new InvalidArgumentException('Filter peminjam tidak valid.');
    return ['borrower_key'=>$key,'status'=>$status,'action'=>$action,'from'=>$from,'to'=>$to,'wallet_id'=>max(0,(int)($input['wallet_id']??0))];
}

function financeReceivableReportData(array $snapshot,array $filters): array {
    $people=array_values((array)($snapshot['people']??[]));$entries=array_values((array)($snapshot['entries']??[]));
    $people=array_values(array_filter($people,static function($p)use($filters){
        if($filters['borrower_key']!=='' && (string)($p['key']??'')!==$filters['borrower_key'])return false;
        if($filters['status']==='active' && (int)($p['outstanding']??0)<=0)return false;
        if($filters['status']==='paid' && (int)($p['outstanding']??0)>0)return false;
        if($filters['status']==='overdue' && (string)($p['status']??'')!=='overdue')return false;
        return true;
    }));
    $keys=[];foreach($people as $p)$keys[(string)$p['key']]=true;
    $entries=array_values(array_filter($entries,static function($e)use($filters,$keys){
        if(!isset($keys[(string)($e['borrower_key']??'')]))return false;
        if($filters['action']!=='all' && (string)($e['action']??'')!==$filters['action'])return false;
        $date=(string)($e['transaction_date']??'');
        if($filters['from']!==''&&$date<$filters['from'])return false;
        if($filters['to']!==''&&$date>$filters['to'])return false;
        if((int)$filters['wallet_id']>0 && (int)($e['wallet_id']??0)!==(int)$filters['wallet_id'])return false;
        return true;
    }));
    $entryRestrictive=$filters['action']!=='all'||$filters['from']!==''||$filters['to']!==''||(int)$filters['wallet_id']>0;
    if($entryRestrictive){$has=[];foreach($entries as $e)$has[(string)$e['borrower_key']]=true;$people=array_values(array_filter($people,static fn($p)=>isset($has[(string)$p['key']])));}
    $lent=0;$interest=0;$repaid=0;foreach($entries as $e){$a=(string)($e['action']??'');if($a==='lend')$lent+=(int)$e['amount'];elseif($a==='interest')$interest+=(int)$e['amount'];elseif($a==='repayment')$repaid+=(int)$e['amount'];}
    $outstanding=0;$active=0;foreach($people as $p){$outstanding+=(int)($p['outstanding']??0);if((int)($p['outstanding']??0)>0)$active++;}
    return ['people'=>$people,'entries'=>$entries,'summary'=>['total_lent'=>$lent,'total_interest'=>$interest,'total_repaid'=>$repaid,'outstanding'=>$outstanding,'active_borrowers'=>$active]];
}

function financeReceivableFilterLabel(array $filters,array $snapshot): string {
    $parts=[];
    if($filters['borrower_key']!=='')foreach((array)($snapshot['people']??[]) as $p)if((string)$p['key']===$filters['borrower_key']){$parts[]='Peminjam: '.(string)$p['name'];break;}
    $statusMap=['active'=>'Belum lunas','paid'=>'Sudah lunas','overdue'=>'Lewat jatuh tempo'];if(isset($statusMap[$filters['status']]))$parts[]='Status: '.$statusMap[$filters['status']];
    $actionMap=['lend'=>'Pemberian hutang','interest'=>'Bunga','repayment'=>'Pelunasan'];if(isset($actionMap[$filters['action']]))$parts[]='Jenis: '.$actionMap[$filters['action']];
    if($filters['from']!==''||$filters['to']!=='')$parts[]='Periode: '.($filters['from']!==''?financeReportDate($filters['from']):'awal').' - '.($filters['to']!==''?financeReportDate($filters['to']):'sekarang');
    if((int)$filters['wallet_id']>0)foreach((array)($snapshot['wallets']??[]) as $w)if((int)$w['id']===(int)$filters['wallet_id']){$parts[]='Dompet: '.(string)$w['name'];break;}
    return $parts?implode(' | ',$parts):'Semua data piutang';
}

function buildReceivableReportPdf(string $username,array $snapshot,array $filters): string {
    $data=financeReceivableReportData($snapshot,$filters);$pdf=new FinanceSimplePdf();
    $blue=[0.04,0.20,0.45];$gold=[1.00,0.80,0.02];$text=[0.08,0.11,0.18];$muted=[0.39,0.45,0.54];$border=[0.84,0.87,0.92];$head=[0.94,0.96,0.99];$zebra=[0.985,0.988,0.995];
    $drawHeader=function(string $title,string $subtitle)use($pdf,$blue,$username){$pdf->rect(0,535,842,60,$blue,null);$pdf->text(32,567,$title,16,true,'left',520,[1,1,1]);$pdf->text(32,549,$subtitle,8,false,'left',610,[0.88,0.93,1]);$pdf->text(810,568,'AKUN',7,false,'right',null,[0.88,0.93,1]);$pdf->text(810,551,$username,11,true,'right',180,[1,1,1]);};
    $filterLabel=financeReceivableFilterLabel($filters,$snapshot);

    // Halaman rekap per peminjam.
    $page=0;$idx=0;$people=$data['people'];
    do{
        $pdf->addPage();$page++;$drawHeader('REKAP PIUTANG','Pemberian hutang, bunga, pelunasan, dan sisa piutang per peminjam');
        if($page===1){
            $pdf->text(32,515,'FILTER',7,false,'left',null,$muted);$pdf->text(32,500,$filterLabel,9,true,'left',778,$text);
            $cards=[[32,'Total diberikan',financeReportRupiah($data['summary']['total_lent']),[1,.96,.96]],[190,'Bunga',financeReportRupiah($data['summary']['total_interest']??0),[1,.98,.91]],[348,'Total dibayar',financeReportRupiah($data['summary']['total_repaid']),[.94,.99,.96]],[506,'Sisa piutang',financeReportRupiah($data['summary']['outstanding']),[.94,.97,1]],[664,'Peminjam aktif',number_format((int)$data['summary']['active_borrowers']).' orang',[.97,.97,.98]]];
            foreach($cards as [$x,$label,$value,$fill]){$pdf->rect($x,430,146,46,$fill,$border);$pdf->text($x+10,459,$label,6.4,false,'left',126,$muted);$pdf->text($x+10,440,$value,9.4,true,'left',126,$text);}
            $y=401;
        }else{$pdf->text(32,512,'REKAP PER PEMINJAM - LANJUTAN',9,true,'left',null,$text);$pdf->text(810,512,$filterLabel,7,false,'right',350,$muted);$y=486;}
        $pdf->rect(32,$y-24,778,24,$head,$border);$pdf->text(40,$y-15,'Peminjam',7,true,'left',120,$text);$pdf->text(260,$y-15,'Pokok',7,true,'right',70,$text);$pdf->text(350,$y-15,'Bunga',7,true,'right',65,$text);$pdf->text(450,$y-15,'Dibayar',7,true,'right',70,$text);$pdf->text(555,$y-15,'Sisa',7,true,'right',75,$text);$pdf->text(650,$y-15,'Status',7,true,'left',75,$text);$pdf->text(802,$y-15,'JT terdekat',7,true,'right',85,$text);$y-=24;
        if(!$people&&$page===1){$pdf->text(421,330,'Tidak ada data piutang yang sesuai dengan filter.',10,false,'center',null,$muted);}
        while($idx<count($people)&&$y-28>52){$p=$people[$idx];$bottom=$y-28;if($idx%2)$pdf->rect(32,$bottom,778,28,$zebra,null);$pdf->text(40,$bottom+10,(string)$p['name'],7.5,true,'left',135,$text);$pdf->text(260,$bottom+10,financeReportRupiah((int)$p['lent']),7,false,'right',78,$text);$pdf->text(350,$bottom+10,financeReportRupiah((int)($p['interest']??0)),7,false,'right',72,$text);$pdf->text(450,$bottom+10,financeReportRupiah((int)$p['repaid']),7,false,'right',78,$text);$pdf->text(555,$bottom+10,financeReportRupiah((int)$p['outstanding']),7.2,true,'right',82,$text);$status=(string)($p['status']??'active');$status=$status==='paid'?'Lunas':($status==='overdue'?'Lewat JT':'Belum lunas');$pdf->text(650,$bottom+10,$status,7,false,'left',85,$text);$pdf->text(802,$bottom+10,!empty($p['next_due_date'])?financeReportDate($p['next_due_date']):'-',7,false,'right',90,$text);$pdf->line(32,$bottom,810,$bottom,.91,.92,.95,.35);$y=$bottom;$idx++;}
    }while($idx<count($people));

    // Halaman riwayat transaksi sesuai filter.
    $entries=$data['entries'];$idx=0;$historyPage=0;
    do{
        $pdf->addPage();$historyPage++;$drawHeader('RIWAYAT PIUTANG','Riwayat transaksi sesuai filter PDF');
        $pdf->text(32,512,'FILTER',7,false,'left',null,$muted);$pdf->text(80,512,$filterLabel,8,true,'left',590,$text);$pdf->text(810,512,count($entries).' transaksi',8,true,'right',120,$text);
        $y=486;$pdf->rect(32,$y-24,778,24,$head,$border);$pdf->text(40,$y-15,'Tanggal',7,true,'left',65,$text);$pdf->text(105,$y-15,'Peminjam',7,true,'left',100,$text);$pdf->text(220,$y-15,'Jenis / Alokasi',7,true,'left',180,$text);$pdf->text(480,$y-15,'Dompet',7,true,'left',95,$text);$pdf->text(802,$y-15,'Nominal',7,true,'right',100,$text);$y-=24;
        if(!$entries&&$historyPage===1)$pdf->text(421,330,'Tidak ada transaksi yang sesuai dengan filter.',10,false,'center',null,$muted);
        while($idx<count($entries)&&$y-38>52){$e=$entries[$idx];$bottom=$y-38;if($idx%2)$pdf->rect(32,$bottom,778,38,$zebra,null);$action=(string)($e['action']??'lend');$repay=$action==='repayment';$interest=$action==='interest';$pdf->text(40,$bottom+16,financeReportDate((string)$e['transaction_date']),7,false,'left',60,$text);$pdf->text(105,$bottom+22,(string)$e['borrower_name'],7.2,true,'left',105,$text);$pdf->text(105,$bottom+9,!empty($e['attachment']['file'])?'Ada bukti foto':'',6,false,'left',105,$muted);$pdf->text(220,$bottom+22,$repay?'Pelunasan':($interest?'Bunga':'Memberi Hutang'),7.2,true,'left',175,$text);if($repay)$detail=(string)($e['allocation_label']??'Akumulasi hutang (FIFO)');elseif($interest){$rate=(float)($e['interest_rate']??0);$detail=($rate>0?number_format($rate,2,',','.').'% dari '.financeReportRupiah((int)($e['interest_base']??0)):'Nominal manual').' · '.(string)($e['allocation_label']??'');}else $detail=!empty($e['due_date'])?'JT '.financeReportDate($e['due_date']):'Tanpa jatuh tempo';$pdf->text(220,$bottom+9,$detail,6.3,false,'left',245,$muted);$pdf->text(480,$bottom+16,(string)$e['wallet_name'],7,false,'left',120,$text);$pdf->text(802,$bottom+16,(($repay||$interest)?'+ ':'- ').financeReportRupiah((int)$e['amount']),7.4,true,'right',110,$text);$pdf->line(32,$bottom,810,$bottom,.91,.92,.95,.35);$y=$bottom;$idx++;}
    }while($idx<count($entries));
    $pdf->addFooterToAll($username);return $pdf->output();
}
