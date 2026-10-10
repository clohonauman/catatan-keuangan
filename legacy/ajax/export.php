<?php
require_once __DIR__.'/../auth.php'; authRequireUnlocked(); authRequirePremiumDownload('Export CSV/Excel tersedia untuk akun Premium.');
require_once __DIR__.'/../db.php'; require_once __DIR__.'/../finance_features.php'; require_once __DIR__.'/../transaction_filter_helper.php';
try{
    $filters=txReadFilters($_GET);$filtered=txFilterTransactions(allTransactions(),$filters);$format=strtolower((string)($_GET['format']??'csv'));
    $walletMap=[];foreach(financeWallets(true) as $w)$walletMap[(int)$w['id']]=$w['name'];
    $user=authCurrentUser();$safe=preg_replace('/[^A-Za-z0-9_.-]/','-',($user['username']??'user'));
    $period=($filters['from']?:'awal').'-'.($filters['to']?:'akhir');
    if($format==='xls'){
        $fn='laporan-'.$safe.'-'.$period.'.xls';header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$fn.'"');echo "\xEF\xBB\xBF";
        echo '<table border="1"><tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th><th>Dompet</th><th>Pengeluaran</th><th>Pemasukan</th><th>Transfer</th></tr>';
        foreach($filtered['transactions'] as $t){$type=$t['type']??'';$wallet=$type==='transfer'?($walletMap[(int)($t['from_wallet_id']??0)]??'-').' -> '.($walletMap[(int)($t['to_wallet_id']??0)]??'-'):($walletMap[(int)($t['wallet_id']??1)]??'Utama');echo '<tr><td>'.htmlspecialchars($t['transaction_date']??'').'</td><td>'.htmlspecialchars($type).'</td><td>'.htmlspecialchars($t['category']??'').'</td><td>'.htmlspecialchars($t['note']??'').'</td><td>'.htmlspecialchars($wallet).'</td><td>'.($type==='expense'?(int)$t['amount']:'').'</td><td>'.($type==='income'?(int)$t['amount']:'').'</td><td>'.($type==='transfer'?(int)$t['amount']:'').'</td></tr>';}
        echo '</table>';exit;
    }
    $fn='laporan-'.$safe.'-'.$period.'.csv';header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$fn.'"');echo "\xEF\xBB\xBF";$fp=fopen('php://output','w');fputcsv($fp,['Tanggal','Jenis','Kategori','Keterangan','Dompet','Pengeluaran','Pemasukan','Transfer'],';');foreach($filtered['transactions'] as $t){$type=$t['type']??'';$wallet=$type==='transfer'?($walletMap[(int)($t['from_wallet_id']??0)]??'-').' -> '.($walletMap[(int)($t['to_wallet_id']??0)]??'-'):($walletMap[(int)($t['wallet_id']??1)]??'Utama');fputcsv($fp,[$t['transaction_date']??'',$type,$t['category']??'',$t['note']??'',$wallet,$type==='expense'?(int)$t['amount']:'',$type==='income'?(int)$t['amount']:'',$type==='transfer'?(int)$t['amount']:''],';');}fclose($fp);
}catch(Throwable $e){http_response_code(500);header('Content-Type:text/plain;charset=utf-8');echo 'Gagal mengekspor transaksi.';}
