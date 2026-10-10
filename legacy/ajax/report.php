<?php
require_once __DIR__ . '/../auth.php';
authRequireUnlocked();
authRequirePremiumDownload('Laporan PDF tersedia untuk akun Premium.');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../transaction_filter_helper.php';
require_once __DIR__ . '/../finance_features.php';
require_once __DIR__ . '/../report_pdf_helper.php';

try {
    $filters = txReadFilters($_GET);
    $all = allTransactions();
    $filtered = txFilterTransactions($all, $filters);
    $user = authCurrentUser();
    $username = $user ? (string)$user['username'] : 'user';
    $wallets = financeWallets(true);

    $pdf = buildFinancialStatementPdf(
        $username,
        $filtered['transactions'],
        $filtered['meta'],
        $wallets,
        $all
    );

    $period = 'semua-tanggal';
    if ($filters['from'] && $filters['to']) $period = $filters['from'].'_sd_'.$filters['to'];
    elseif ($filters['from']) $period = 'sejak_'.$filters['from'];
    elseif ($filters['to']) $period = 'sampai_'.$filters['to'];
    $safeUser = preg_replace('/[^A-Za-z0-9_.-]/', '-', $username);
    $filename = 'laporan-flow-dompet-'.$safeUser.'-'.$period.'.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($pdf));
    header('Cache-Control: private, no-store, max-age=0');
    echo $pdf;
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Gagal membuat laporan keuangan.';
}
