<?php

class FinanceSimplePdf
{
    private $pages = [];
    private $current = -1;
    private $pageWidth = 842.0;
    private $pageHeight = 595.0;

    public function addPage() { $this->pages[]=''; $this->current=count($this->pages)-1; }
    public function pageCount() { return count($this->pages); }
    private function ensurePage() { if ($this->current < 0) $this->addPage(); }
    private function append($s) { $this->ensurePage(); $this->pages[$this->current] .= $s."\n"; }

    private function cp1252($text)
    {
        $text = str_replace(["\r","\n","\t",'–','—','•','→'], [' ',' ',' ','-','-','-','->'], (string)$text);
        $text = preg_replace('/\s+/u',' ',$text);
        if (function_exists('iconv')) {
            $converted=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
            if ($converted!==false) $text=$converted;
        } else $text=preg_replace('/[^\x20-\x7E]/','',$text);
        return $text;
    }
    private function escText($text) { return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$this->cp1252($text)); }
    public function approxWidth($text,$size) { return strlen($this->cp1252($text))*(float)$size*0.50; }
    public function truncate($text,$size,$maxWidth)
    {
        $text=trim((string)$text); if($this->approxWidth($text,$size)<=$maxWidth)return $text;
        $suffix='...';
        while($text!=='' && $this->approxWidth($text.$suffix,$size)>$maxWidth){$text=function_exists('mb_substr')?mb_substr($text,0,-1,'UTF-8'):substr($text,0,-1);} return rtrim($text).$suffix;
    }
    public function text($x,$y,$text,$size=9,$bold=false,$align='left',$maxWidth=null,$color=null)
    {
        $font=$bold?'F2':'F1'; $text=(string)$text; if($maxWidth!==null)$text=$this->truncate($text,$size,$maxWidth);
        $width=$this->approxWidth($text,$size); if($align==='right')$x-=$width; elseif($align==='center')$x-=$width/2;
        if($color===null)$color=[0.08,0.11,0.18];
        $this->append(sprintf('%.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',$color[0],$color[1],$color[2],$font,$size,$x,$y,$this->escText($text)));
    }
    public function line($x1,$y1,$x2,$y2,$r=.85,$g=.88,$b=.93,$width=.6){$this->append(sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S',$r,$g,$b,$width,$x1,$y1,$x2,$y2));}
    public function rect($x,$y,$w,$h,$fill=null,$stroke=null,$lineWidth=.6)
    {
        $cmd=''; if(is_array($fill))$cmd.=sprintf('%.3F %.3F %.3F rg ',$fill[0],$fill[1],$fill[2]); if(is_array($stroke))$cmd.=sprintf('%.3F %.3F %.3F RG %.2F w ',$stroke[0],$stroke[1],$stroke[2],$lineWidth);
        $op=is_array($fill)&&is_array($stroke)?'B':(is_array($fill)?'f':'S'); $cmd.=sprintf('%.2F %.2F %.2F %.2F re %s',$x,$y,$w,$h,$op); $this->append($cmd);
    }
    public function addFooterToAll($username='')
    {
        $total=count($this->pages); foreach($this->pages as $i=>&$content){$pageNo=$i+1;$footer='';$footer.=sprintf('0.55 0.58 0.64 rg BT /F1 7.5 Tf 1 0 0 1 32 18 Tm (%s) Tj ET' . "\n",$this->escText('Catatan Keuangan - '.$username));$footerText='Halaman '.$pageNo.' dari '.$total;$x=$this->pageWidth-32-$this->approxWidth($footerText,7.5);$footer.=sprintf('0.55 0.58 0.64 rg BT /F1 7.5 Tf 1 0 0 1 %.2F 18 Tm (%s) Tj ET' . "\n",$x,$this->escText($footerText));$content.=$footer;}unset($content);
    }
    public function output()
    {
        if(!$this->pages)$this->addPage();$objects=[];$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';$kids=[];$objNo=5;
        foreach($this->pages as $pageContent){$pageObj=$objNo++;$contentObj=$objNo++;$kids[]=$pageObj.' 0 R';$objects[$pageObj]=sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',$this->pageWidth,$this->pageHeight,$contentObj);$objects[$contentObj]='<< /Length '.strlen($pageContent).' >>'."\nstream\n".$pageContent."endstream";}$objects[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($kids).' >>';ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0=>0];$maxObj=max(array_keys($objects));for($i=1;$i<=$maxObj;$i++){if(!isset($objects[$i]))continue;$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".$objects[$i]."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($maxObj+1)."\n0000000000 65535 f \n";for($i=1;$i<=$maxObj;$i++){$off=$offsets[$i]??0;$pdf.=sprintf("%010d 00000 n \n",$off);}$pdf.="trailer\n<< /Size ".($maxObj+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
    }
}

function financeReportRupiah($amount){return 'Rp'.number_format((int)$amount,0,',','.');}
function financeReportDate($date){if(!$date)return '-';$ts=strtotime($date);return $ts?date('d/m/Y',$ts):$date;}
function financeReportTypeLabel($type){if($type==='expense')return 'Pengeluaran';if($type==='income')return 'Pemasukan';if($type==='transfer')return 'Transfer antar dompet';return 'Semua transaksi';}
function financeReportSortLabel($sort){$map=['date_desc'=>'Tanggal terbaru','date_asc'=>'Tanggal terlama','amount_desc'=>'Nominal terbesar','amount_asc'=>'Nominal terkecil'];return $map[$sort]??'Tanggal terbaru';}
function financeReportPeriodLabel($from,$to){if($from&&$to)return financeReportDate($from).' - '.financeReportDate($to);if($from)return 'Sejak '.financeReportDate($from);if($to)return 'Sampai '.financeReportDate($to);return 'Semua tanggal';}

function buildFinancialStatementPdf($username,$transactions,$meta,$ledger)
{
    $pdf=new FinanceSimplePdf();
    $blue=[0.09,0.36,0.82];$dark=[0.08,0.11,0.18];$muted=[0.39,0.45,0.54];$border=[0.84,0.87,0.92];$lightGray=[0.965,0.972,0.985];$lightBlue=[0.94,0.97,1.00];

    $drawTopHeader=function($title,$subtitle='')use($pdf,$username,$blue){$pdf->rect(0,535,842,60,$blue,null);$pdf->text(32,567,$title,16.5,true,'left',520,[1,1,1]);if($subtitle!=='')$pdf->text(32,549,$subtitle,8.2,false,'left',560,[.88,.93,1]);$pdf->text(810,568,'AKUN',7,false,'right',null,[.88,.93,1]);$pdf->text(810,551,$username,11.5,true,'right',180,[1,1,1]);};

    $drawMeta=function()use($pdf,$meta,$border,$muted){$items=[['Periode',financeReportPeriodLabel($meta['from']??'',$meta['to']??'')],['Jenis transaksi',financeReportTypeLabel($meta['type']??'all')],['Urutan',financeReportSortLabel($meta['sort']??'date_desc')],['Dibuat',date('d/m/Y H:i').' WITA']];$xs=[32,232,432,632];foreach($items as $i=>$it){$pdf->text($xs[$i],515,$it[0],7,false,'left',170,$muted);$pdf->text($xs[$i],500,$it[1],9.5,true,'left',175);} $pdf->line(32,486,810,486,$border[0],$border[1],$border[2]);};

    $drawCard=function($x,$label,$value,$fill)use($pdf,$border,$muted){$pdf->rect($x,430,184,46,$fill,$border);$pdf->text($x+12,459,$label,7,false,'left',160,$muted);$pdf->text($x+12,440,$value,11.2,true,'left',160);};

    // PAGE 1 - Ringkasan seperti rekening koran.
    $pdf->addPage();
    $drawTopHeader('LAPORAN MUTASI KEUANGAN PER DOMPET','Ringkasan saldo dan aliran dana seluruh dompet');
    $drawMeta();
    $drawCard(32,'Saldo awal periode',financeReportRupiah($ledger['total_opening']??0),$lightBlue);
    $drawCard(230,'Total pemasukan',financeReportRupiah($meta['income']??0),[.94,.99,.96]);
    $drawCard(428,'Total pengeluaran',financeReportRupiah($meta['expense']??0),[1,.95,.95]);
    $drawCard(626,'Saldo akhir periode',financeReportRupiah($ledger['total_closing']??0),$lightGray);

    $pdf->text(32,408,'RINGKASAN SALDO PER DOMPET',8.5,true);
    $pdf->text(810,408,'Semua nominal ditampilkan penuh, tanpa singkatan.',7,false,'right',null,$muted);

    $summaryHeaderY=389;
    $pdf->rect(32,$summaryHeaderY-18,778,22,[.94,.96,.99],$border);
    $pdf->text(40,$summaryHeaderY-10,'Dompet',7.2,true);
    $pdf->text(236,$summaryHeaderY-10,'Saldo Awal',7.2,true,'right');
    $pdf->text(348,$summaryHeaderY-10,'Pemasukan',7.2,true,'right');
    $pdf->text(460,$summaryHeaderY-10,'Pengeluaran',7.2,true,'right');
    $pdf->text(572,$summaryHeaderY-10,'Transfer Masuk',7.2,true,'right');
    $pdf->text(684,$summaryHeaderY-10,'Transfer Keluar',7.2,true,'right');
    $pdf->text(802,$summaryHeaderY-10,'Saldo Akhir',7.2,true,'right');

    $wallets=(array)($ledger['wallet_summary']??[]);$y=$summaryHeaderY-25;$rowH=26;$first=true;$rowNo=0;
    foreach($wallets as $w){
        if($y-$rowH<62){$pdf->addPage();$drawTopHeader('RINGKASAN SALDO PER DOMPET','Lanjutan ringkasan saldo periode '.financeReportPeriodLabel($meta['from']??'',$meta['to']??''));$summaryHeaderY=506;$pdf->rect(32,$summaryHeaderY-18,778,22,[.94,.96,.99],$border);$pdf->text(40,$summaryHeaderY-10,'Dompet',7.2,true);$pdf->text(236,$summaryHeaderY-10,'Saldo Awal',7.2,true,'right');$pdf->text(348,$summaryHeaderY-10,'Pemasukan',7.2,true,'right');$pdf->text(460,$summaryHeaderY-10,'Pengeluaran',7.2,true,'right');$pdf->text(572,$summaryHeaderY-10,'Transfer Masuk',7.2,true,'right');$pdf->text(684,$summaryHeaderY-10,'Transfer Keluar',7.2,true,'right');$pdf->text(802,$summaryHeaderY-10,'Saldo Akhir',7.2,true,'right');$y=$summaryHeaderY-25;$rowNo=0;}
        if($rowNo%2===1)$pdf->rect(32,$y-$rowH+4,778,$rowH,[.985,.988,.995],null);
        $name=(string)($w['name']??'Dompet').(!empty($w['archived'])?' (Arsip)':'');
        $pdf->text(40,$y-13,$name,7.6,true,'left',145);
        $pdf->text(236,$y-13,financeReportRupiah($w['opening_balance']??0),7.2,false,'right',100);
        $pdf->text(348,$y-13,financeReportRupiah($w['income']??0),7.2,false,'right',100);
        $pdf->text(460,$y-13,financeReportRupiah($w['expense']??0),7.2,false,'right',100);
        $pdf->text(572,$y-13,financeReportRupiah($w['transfer_in']??0),7.2,false,'right',100);
        $pdf->text(684,$y-13,financeReportRupiah($w['transfer_out']??0),7.2,false,'right',100);
        $pdf->text(802,$y-13,financeReportRupiah($w['closing_balance']??0),7.3,true,'right',106);
        $pdf->line(32,$y-$rowH+4,810,$y-$rowH+4,.91,.92,.95,.35);$y-=$rowH;$rowNo++;
    }
    $pdf->text(32,max(43,$y-2),'Catatan: transfer antar dompet memindahkan saldo antar rekening/dompet dan tidak mengubah total saldo seluruh dompet.',7,false,'left',760,$muted);

    // TRANSACTION PAGES
    $drawTxHeader=function()use($pdf,$meta,$border,$muted){$pdf->text(32,512,'MUTASI TRANSAKSI',9,true);$pdf->text(810,512,financeReportPeriodLabel($meta['from']??'',$meta['to']??''),8,false,'right');$pdf->text(32,497,'Debit = uang keluar dari dompet. Kredit = uang masuk ke dompet. Saldo dompet menunjukkan saldo sebelum -> sesudah transaksi.',7,false,'left',770,$muted);$pdf->line(32,486,810,486,$border[0],$border[1],$border[2]);};
    $drawTxTableHeader=function($y)use($pdf,$border){
        $pdf->rect(32,$y-19,778,23,[.94,.96,.99],$border);
        $pdf->text(40,$y-11,'Tanggal',7.0,true);
        $pdf->text(108,$y-11,'Dompet',7.0,true);
        $pdf->text(218,$y-11,'Keterangan',7.0,true);
        $pdf->text(508,$y-11,'Debit',7.0,true,'right');
        $pdf->text(594,$y-11,'Kredit',7.0,true,'right');
        $pdf->text(610,$y-11,'Saldo Dompet',7.0,true);
        $pdf->text(804,$y-11,'Total Saldo',7.0,true,'right');
        return $y-24;
    };

    $pdf->addPage();$drawTopHeader('LAPORAN MUTASI KEUANGAN PER DOMPET','Rincian transaksi seperti rekening koran');$drawTxHeader();$y=$drawTxTableHeader(466);$rowNo=0;
    if(!$transactions)$pdf->text(421,320,'Tidak ada transaksi yang sesuai dengan filter.',10,false,'center');

    foreach($transactions as $t){
        $type=(string)($t['type']??'');$amount=(int)($t['amount']??0);$snap=walletFlowSnapshotFor($ledger,$t);$isTransfer=$type==='transfer';$rowH=$isTransfer?46:38;
        if($y-$rowH<48){$pdf->addPage();$drawTopHeader('LAPORAN MUTASI KEUANGAN PER DOMPET','Rincian transaksi seperti rekening koran');$drawTxHeader();$y=$drawTxTableHeader(466);$rowNo=0;}
        if($rowNo%2===1)$pdf->rect(32,$y-$rowH+3,778,$rowH,[.985,.988,.995],null);
        $pdf->line(32,$y-$rowH+3,810,$y-$rowH+3,.91,.92,.95,.35);

        $flow=(string)($snap['flow_label']??'-');$category=trim((string)($t['category']??''));$note=trim((string)($t['note']??''));
        if($note==='')$note=$category?:($type==='income'?'Pemasukan':($isTransfer?'Transfer antar dompet':'Pengeluaran'));
        $walletLabel=$isTransfer
            ? (string)($snap['from_wallet_name']??'Asal').' -> '.(string)($snap['to_wallet_name']??'Tujuan')
            : (string)($snap['wallet_name']??$flow);
        $headline=$category!==''?$category:($isTransfer?'Transfer antar dompet':($type==='income'?'Pemasukan':'Pengeluaran'));
        $debit=($type==='expense'||$isTransfer)?financeReportRupiah($amount):'-';
        $credit=($type==='income'||$isTransfer)?financeReportRupiah($amount):'-';

        $pdf->text(40,$y-15,financeReportDate($t['transaction_date']??''),7.0,false,'left',58);
        if($isTransfer){
            $pdf->text(108,$y-12,(string)($snap['from_wallet_name']??'Asal'),6.7,true,'left',98);
            $pdf->text(108,$y-27,'-> '.(string)($snap['to_wallet_name']??'Tujuan'),6.5,false,'left',98,$muted);
        }else{
            $pdf->text(108,$y-18,$walletLabel,6.9,true,'left',98);
        }
        $pdf->text(218,$y-13,$headline,7.2,true,'left',204);
        $pdf->text(218,$y-27,$note,6.6,false,'left',204,$muted);
        $pdf->text(508,$y-18,$debit,7.1,$debit!=='-','right',78);
        $pdf->text(594,$y-18,$credit,7.1,$credit!=='-','right',78);

        if($isTransfer){
            $fromLine=financeReportRupiah($snap['from_before']??0).' -> '.financeReportRupiah($snap['from_after']??0);
            $toLine=financeReportRupiah($snap['to_before']??0).' -> '.financeReportRupiah($snap['to_after']??0);
            $pdf->text(610,$y-13,$fromLine,6.15,true,'left',120);
            $pdf->text(610,$y-28,$toLine,6.15,false,'left',120);
        }else{
            $balanceLine=financeReportRupiah($snap['wallet_before']??0).' -> '.financeReportRupiah($snap['wallet_after']??0);
            $pdf->text(610,$y-18,$balanceLine,6.4,true,'left',120);
        }
        $pdf->text(804,$y-18,financeReportRupiah($snap['total_after']??0),7.1,true,'right',66);
        $y-=$rowH;$rowNo++;
    }

    $pdf->addFooterToAll($username);
    return $pdf->output();
}
