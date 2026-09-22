<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
authRequirePremiumDownload('Export CSV/Excel tersedia untuk akun Premium.');
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../transaction_filter_helper.php';
require_once __DIR__.'/../wallet_flow_helper.php';

function exportH($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function exportTypeLabel($type){if($type==='expense')return 'Pengeluaran';if($type==='income')return 'Pemasukan';if($type==='transfer')return 'Transfer antar dompet';return ucfirst((string)$type);}
function exportMoney($v){return 'Rp'.number_format((int)$v,0,',','.');}
function exportDate($v){$ts=strtotime((string)$v);return $ts?date('d/m/Y',$ts):(string)$v;}

try{
    $filters=txReadFilters($_GET);$all=allTransactions();$filtered=txFilterTransactions($all,$filters);$format=strtolower((string)($_GET['format']??'csv'));$wallets=financeWallets(true);$ledger=walletFlowBuildLedger($all,$wallets,$filters['from'],$filters['to']);
    $user=authCurrentUser();$username=(string)($user['username']??'user');$safe=preg_replace('/[^A-Za-z0-9_.-]/','-',$username);$period=($filters['from']?:'awal').'-'.($filters['to']?:'akhir');

    $columns=['Tanggal','Jenis','Dompet','Kategori','Keterangan','Debit / Keluar','Kredit / Masuk','Saldo Dompet Sebelum','Saldo Dompet Sesudah','Saldo Tujuan Sebelum','Saldo Tujuan Sesudah','Total Saldo Semua Dompet'];

    if($format==='xls'){
        $fn='laporan-rekening-koran-'.$safe.'-'.$period.'.xls';header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$fn.'"');echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#172235}h1{font-size:22px;margin:0 0 4px}h2{font-size:15px;margin:22px 0 8px}.sub{color:#667085;margin:0 0 14px}.meta,.summary,.ledger{border-collapse:collapse;width:100%;margin-bottom:16px}.meta td{border:0;padding:5px 8px}.summary th,.ledger th{background:#eaf2ff;color:#172235;font-weight:bold;text-align:left}.summary th,.summary td,.ledger th,.ledger td{border:1px solid #d5dce8;padding:7px 8px;vertical-align:top}.summary tr:nth-child(even) td,.ledger tr:nth-child(even) td{background:#f8fafc}.num{text-align:right!important;white-space:nowrap}.note{color:#667085;font-size:11px}.total{font-weight:bold;background:#f2f4f7!important}.section{background:#175cd3;color:white;font-weight:bold;padding:9px 10px}.nowrap{white-space:nowrap}</style></head><body>';
        echo '<h1>Laporan Mutasi Keuangan per Dompet</h1><p class="sub">Format rekening koran - semua nominal ditampilkan penuh tanpa singkatan.</p>';
        echo '<table class="meta"><tr><td><b>Akun</b><br>'.exportH($username).'</td><td><b>Periode</b><br>'.exportH($filters['from']?:'Awal').' s/d '.exportH($filters['to']?:'Akhir').'</td><td><b>Total awal periode</b><br>'.exportMoney($ledger['total_opening']).'</td><td><b>Total akhir periode</b><br>'.exportMoney($ledger['total_closing']).'</td></tr></table>';
        echo '<h2>Ringkasan Saldo per Dompet</h2><table class="summary"><tr><th>Dompet</th><th class="num">Saldo Awal Periode</th><th class="num">Pemasukan</th><th class="num">Pengeluaran</th><th class="num">Transfer Masuk</th><th class="num">Transfer Keluar</th><th class="num">Saldo Akhir Periode</th></tr>';
        foreach($ledger['wallet_summary'] as $w){echo '<tr><td>'.exportH($w['name'].(!empty($w['archived'])?' (Arsip)':'')).'</td><td class="num">'.exportMoney($w['opening_balance']).'</td><td class="num">'.exportMoney($w['income']).'</td><td class="num">'.exportMoney($w['expense']).'</td><td class="num">'.exportMoney($w['transfer_in']).'</td><td class="num">'.exportMoney($w['transfer_out']).'</td><td class="num"><b>'.exportMoney($w['closing_balance']).'</b></td></tr>';}
        echo '<tr class="total"><td>Total Semua Dompet</td><td class="num">'.exportMoney($ledger['total_opening']).'</td><td class="num">'.exportMoney($filtered['meta']['income']??0).'</td><td class="num">'.exportMoney($filtered['meta']['expense']??0).'</td><td colspan="2" class="note">Transfer antar dompet tidak mengubah total saldo.</td><td class="num">'.exportMoney($ledger['total_closing']).'</td></tr></table>';
        echo '<h2>Mutasi Transaksi</h2><table class="ledger"><tr>';foreach($columns as $c)echo '<th>'.exportH($c).'</th>';echo '</tr>';
        foreach($filtered['transactions'] as $t){$type=(string)($t['type']??'');$snap=walletFlowSnapshotFor($ledger,$t);$isTransfer=$type==='transfer';$amount=(int)($t['amount']??0);$debit=($type==='expense'||$isTransfer)?exportMoney($amount):'-';$credit=($type==='income'||$isTransfer)?exportMoney($amount):'-';$before=$isTransfer?exportMoney($snap['from_before']??0):exportMoney($snap['wallet_before']??0);$after=$isTransfer?exportMoney($snap['from_after']??0):exportMoney($snap['wallet_after']??0);$targetBefore=$isTransfer?exportMoney($snap['to_before']??0):'-';$targetAfter=$isTransfer?exportMoney($snap['to_after']??0):'-';echo '<tr><td class="nowrap">'.exportH(exportDate($t['transaction_date']??'')).'</td><td>'.exportH(exportTypeLabel($type)).'</td><td>'.exportH($snap['flow_label']??'-').'</td><td>'.exportH($t['category']??'').'</td><td>'.exportH($t['note']??'').'</td><td class="num">'.$debit.'</td><td class="num">'.$credit.'</td><td class="num">'.$before.'</td><td class="num">'.$after.'</td><td class="num">'.$targetBefore.'</td><td class="num">'.$targetAfter.'</td><td class="num"><b>'.exportMoney($snap['total_after']??0).'</b></td></tr>';}
        echo '</table><p class="note">Debit = uang keluar dari dompet. Kredit = uang masuk ke dompet. Untuk transfer, kolom saldo tujuan menunjukkan perubahan saldo dompet penerima.</p></body></html>';exit;
    }

    $fn='laporan-rekening-koran-'.$safe.'-'.$period.'.csv';header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$fn.'"');echo "\xEF\xBB\xBF";$fp=fopen('php://output','w');
    fputcsv($fp,['LAPORAN MUTASI KEUANGAN PER DOMPET'],';');fputcsv($fp,['Akun',$username],';');fputcsv($fp,['Periode',($filters['from']?:'Awal').' s/d '.($filters['to']?:'Akhir')],';');fputcsv($fp,['Total awal periode',exportMoney($ledger['total_opening'])],';');fputcsv($fp,['Total akhir periode',exportMoney($ledger['total_closing'])],';');fputcsv($fp,[],';');
    fputcsv($fp,['RINGKASAN SALDO PER DOMPET'],';');fputcsv($fp,['Dompet','Saldo Awal Periode','Pemasukan','Pengeluaran','Transfer Masuk','Transfer Keluar','Saldo Akhir Periode'],';');foreach($ledger['wallet_summary'] as $w)fputcsv($fp,[$w['name'].(!empty($w['archived'])?' (Arsip)':''),exportMoney($w['opening_balance']),exportMoney($w['income']),exportMoney($w['expense']),exportMoney($w['transfer_in']),exportMoney($w['transfer_out']),exportMoney($w['closing_balance'])],';');fputcsv($fp,[],';');
    fputcsv($fp,['MUTASI TRANSAKSI'],';');fputcsv($fp,$columns,';');
    foreach($filtered['transactions'] as $t){$type=(string)($t['type']??'');$snap=walletFlowSnapshotFor($ledger,$t);$isTransfer=$type==='transfer';$amount=(int)($t['amount']??0);fputcsv($fp,[exportDate($t['transaction_date']??''),exportTypeLabel($type),$snap['flow_label']??'-',$t['category']??'',$t['note']??'',($type==='expense'||$isTransfer)?exportMoney($amount):'-',($type==='income'||$isTransfer)?exportMoney($amount):'-',$isTransfer?exportMoney($snap['from_before']??0):exportMoney($snap['wallet_before']??0),$isTransfer?exportMoney($snap['from_after']??0):exportMoney($snap['wallet_after']??0),$isTransfer?exportMoney($snap['to_before']??0):'-',$isTransfer?exportMoney($snap['to_after']??0):'-',exportMoney($snap['total_after']??0)],';');}
    fclose($fp);
}catch(InvalidArgumentException $e){http_response_code(422);header('Content-Type:text/plain;charset=utf-8');echo $e->getMessage();}catch(Throwable $e){http_response_code(500);header('Content-Type:text/plain;charset=utf-8');echo 'Gagal mengekspor transaksi.';}
