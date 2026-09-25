<?php

class FinanceSimplePdf
{
    private $pages = [];
    private $current = -1;
    private $pageWidth = 842.0;
    private $pageHeight = 595.0;

    public function addPage()
    {
        $this->pages[] = '';
        $this->current = count($this->pages) - 1;
    }

    public function pageCount()
    {
        return count($this->pages);
    }

    private function ensurePage()
    {
        if ($this->current < 0) $this->addPage();
    }

    private function append($s)
    {
        $this->ensurePage();
        $this->pages[$this->current] .= $s . "\n";
    }

    private function cp1252($text)
    {
        $text = str_replace(["\r", "\n", "\t", '–', '—', '•'], [' ', ' ', ' ', '-', '-', '-'], (string)$text);
        $text = preg_replace('/\s+/u', ' ', $text);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) $text = $converted;
        } else {
            $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        }
        return $text;
    }

    private function escText($text)
    {
        $text = $this->cp1252($text);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    public function approxWidth($text, $size)
    {
        return strlen($this->cp1252($text)) * (float)$size * 0.50;
    }

    public function text($x, $y, $text, $size = 9, $bold = false, $align = 'left', $maxWidth = null, $color = null)
    {
        $font = $bold ? 'F2' : 'F1';
        $text = (string)$text;
        if ($maxWidth !== null) $text = $this->truncate($text, $size, $maxWidth);
        $width = $this->approxWidth($text, $size);
        if ($align === 'right') $x -= $width;
        elseif ($align === 'center') $x -= $width / 2;
        if ($color === null) $color = [0.08, 0.11, 0.18];
        $this->append(sprintf('%.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET', $color[0], $color[1], $color[2], $font, $size, $x, $y, $this->escText($text)));
    }

    public function truncate($text, $size, $maxWidth)
    {
        $text = trim((string)$text);
        if ($this->approxWidth($text, $size) <= $maxWidth) return $text;
        $suffix = '...';
        while ($text !== '' && $this->approxWidth($text . $suffix, $size) > $maxWidth) {
            $text = function_exists('mb_substr') ? mb_substr($text, 0, -1, 'UTF-8') : substr($text, 0, -1);
        }
        return rtrim($text) . $suffix;
    }

    public function line($x1, $y1, $x2, $y2, $r = 0.85, $g = 0.88, $b = 0.93, $width = 0.6)
    {
        $this->append(sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S', $r, $g, $b, $width, $x1, $y1, $x2, $y2));
    }

    public function rect($x, $y, $w, $h, $fill = null, $stroke = null, $lineWidth = 0.6)
    {
        $cmd = '';
        if (is_array($fill)) $cmd .= sprintf('%.3F %.3F %.3F rg ', $fill[0], $fill[1], $fill[2]);
        if (is_array($stroke)) $cmd .= sprintf('%.3F %.3F %.3F RG %.2F w ', $stroke[0], $stroke[1], $stroke[2], $lineWidth);
        $op = is_array($fill) && is_array($stroke) ? 'B' : (is_array($fill) ? 'f' : 'S');
        $cmd .= sprintf('%.2F %.2F %.2F %.2F re %s', $x, $y, $w, $h, $op);
        $this->append($cmd);
    }

    public function addFooterToAll($username = '')
    {
        $total = count($this->pages);
        foreach ($this->pages as $i => &$content) {
            $pageNo = $i + 1;
            $footer = '';
            $footer .= sprintf('0.55 0.58 0.64 rg BT /F1 7.5 Tf 1 0 0 1 32 18 Tm (%s) Tj ET' . "\n", $this->escText('Catatan Keuangan - ' . $username));
            $footerText = 'Halaman ' . $pageNo . ' dari ' . $total;
            $x = $this->pageWidth - 32 - $this->approxWidth($footerText, 7.5);
            $footer .= sprintf('0.55 0.58 0.64 rg BT /F1 7.5 Tf 1 0 0 1 %.2F 18 Tm (%s) Tj ET' . "\n", $x, $this->escText($footerText));
            $content .= $footer;
        }
        unset($content);
    }

    public function output()
    {
        if (!$this->pages) $this->addPage();

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $objNo = 5;
        foreach ($this->pages as $pageContent) {
            $pageObj = $objNo++;
            $contentObj = $objNo++;
            $kids[] = $pageObj . ' 0 R';
            $objects[$pageObj] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $this->pageWidth, $this->pageHeight, $contentObj);
            $objects[$contentObj] = '<< /Length ' . strlen($pageContent) . ' >>' . "\nstream\n" . $pageContent . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        $maxObj = max(array_keys($objects));
        for ($i = 1; $i <= $maxObj; $i++) {
            if (!isset($objects[$i])) continue;
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObj; $i++) {
            $off = isset($offsets[$i]) ? $offsets[$i] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
}

function financeReportRupiah($amount)
{
    return 'Rp' . number_format((int)$amount, 0, ',', '.');
}

function financeReportDate($date)
{
    if (!$date) return '-';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : $date;
}

function financeReportDateTime($transactionDate, $createdAt = '')
{
    $transactionDate = trim((string)$transactionDate);
    $createdAt = trim((string)$createdAt);

    // Tanggal mengikuti tanggal transaksi agar transaksi backdate tetap benar.
    $dateTs = $transactionDate !== '' ? strtotime($transactionDate) : false;
    if (!$dateTs && $createdAt !== '') $dateTs = strtotime($createdAt);
    if (!$dateTs) return '-';

    $time = '00:00:00';

    // Jika transaction_date sendiri sudah mengandung jam, gunakan jam tersebut.
    if (preg_match('/\b(\d{2}:\d{2}(?::\d{2})?)\b/', $transactionDate, $m)) {
        $timeTs = strtotime($m[1]);
        if ($timeTs) $time = date('H:i:s', $timeTs);
    } elseif ($createdAt !== '') {
        // Data lama menyimpan transaction_date sebagai DATE, sehingga waktu diambil
        // dari created_at tanpa mengubah tanggal transaksi yang dipilih pengguna.
        $createdTs = strtotime($createdAt);
        if ($createdTs) $time = date('H:i:s', $createdTs);
    }

    return date('d/m/Y', $dateTs) . ' ' . $time;
}

function financeReportTypeLabel($type)
{
    if ($type === 'expense') return 'Pengeluaran saja';
    if ($type === 'income') return 'Pemasukan saja';
    if ($type === 'transfer') return 'Transfer antar dompet';
    return 'Semua transaksi';
}

function financeReportSortLabel($sort)
{
    $map = [
        'date_desc' => 'Tanggal terbaru',
        'date_asc' => 'Tanggal terlama',
        'amount_desc' => 'Nominal terbesar',
        'amount_asc' => 'Nominal terkecil',
    ];
    return isset($map[$sort]) ? $map[$sort] : 'Tanggal terbaru';
}

function financeReportPeriodLabel($from, $to)
{
    if ($from && $to) return financeReportDate($from) . ' - ' . financeReportDate($to);
    if ($from) return 'Sejak ' . financeReportDate($from);
    if ($to) return 'Sampai ' . financeReportDate($to);
    return 'Semua tanggal';
}

function financeReportWalletLabel($walletId, $walletMap)
{
    $walletId = (int)$walletId;
    if (isset($walletMap[$walletId])) return (string)($walletMap[$walletId]['name'] ?? ('Dompet #' . $walletId));
    return $walletId > 0 ? ('Dompet #' . $walletId) : 'Dompet';
}

function financeReportChronological($transactions)
{
    $rows = array_values((array)$transactions);
    usort($rows, function ($a, $b) {
        $dateA = (string)($a['transaction_date'] ?? '');
        $dateB = (string)($b['transaction_date'] ?? '');
        if ($dateA !== $dateB) return strcmp($dateA, $dateB);
        return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
    });
    return $rows;
}

function financeReportWalletContext($wallets, $allTransactions, $filteredTransactions, $fromDate = '', $toDate = '')
{
    $walletRows = array_values((array)$wallets);
    $walletMap = [];
    $balances = [];
    foreach ($walletRows as $w) {
        $id = (int)($w['id'] ?? 0);
        if ($id <= 0) continue;
        $walletMap[$id] = $w;
        $balances[$id] = (int)($w['initial_balance'] ?? 0);
    }

    // Pastikan transaksi legacy yang merujuk ID dompet lama tetap dapat dilaporkan.
    foreach ((array)$allTransactions as $t) {
        $ids = [];
        if (($t['type'] ?? '') === 'transfer') {
            $ids[] = (int)($t['from_wallet_id'] ?? 0);
            $ids[] = (int)($t['to_wallet_id'] ?? 0);
        } else {
            $ids[] = (int)($t['wallet_id'] ?? 1);
        }
        foreach ($ids as $id) {
            if ($id <= 0 || isset($walletMap[$id])) continue;
            $walletMap[$id] = ['id'=>$id, 'name'=>'Dompet #' . $id, 'initial_balance'=>0, 'archived'=>true];
            $balances[$id] = 0;
            $walletRows[] = $walletMap[$id];
        }
    }

    $initialBalances = $balances;
    $openingBalances = $balances;
    $closingBalances = $balances;
    $snapshots = [];
    $chronological = financeReportChronological($allTransactions);

    // Saldo awal periode: seluruh mutasi sebelum tanggal awal.
    if ($fromDate !== '') {
        foreach ($chronological as $t) {
            $date = (string)($t['transaction_date'] ?? '');
            if ($date === '' || $date >= $fromDate) continue;
            financeReportApplyWalletTransaction($openingBalances, $t);
        }
    }

    // Saldo sesudah tiap transaksi + total saldo seluruh dompet.
    $running = $initialBalances;
    foreach ($chronological as $t) {
        $id = (int)($t['id'] ?? 0);
        $type = (string)($t['type'] ?? '');
        $amount = (int)($t['amount'] ?? 0);
        $snap = ['type'=>$type, 'total_before'=>array_sum($running), 'total_after'=>0];
        if ($type === 'transfer') {
            $from = (int)($t['from_wallet_id'] ?? 0);
            $to = (int)($t['to_wallet_id'] ?? 0);
            if (!isset($running[$from])) $running[$from] = 0;
            if (!isset($running[$to])) $running[$to] = 0;
            $snap['from_wallet_id'] = $from;
            $snap['to_wallet_id'] = $to;
            $snap['from_before'] = (int)$running[$from];
            $snap['to_before'] = (int)$running[$to];
            $running[$from] -= $amount;
            $running[$to] += $amount;
            $snap['from_after'] = (int)$running[$from];
            $snap['to_after'] = (int)$running[$to];
        } else {
            $wid = (int)($t['wallet_id'] ?? 1);
            if (!isset($running[$wid])) $running[$wid] = 0;
            $snap['wallet_id'] = $wid;
            $snap['before'] = (int)$running[$wid];
            if ($type === 'income') $running[$wid] += $amount;
            elseif ($type === 'expense') $running[$wid] -= $amount;
            $snap['after'] = (int)$running[$wid];
        }
        $snap['total_after'] = array_sum($running);
        if ($id > 0) $snapshots[$id] = $snap;
    }

    // Saldo akhir periode: seluruh mutasi sampai tanggal akhir.
    foreach ($chronological as $t) {
        $date = (string)($t['transaction_date'] ?? '');
        if ($toDate !== '' && $date > $toDate) continue;
        financeReportApplyWalletTransaction($closingBalances, $t);
    }

    $flows = [];
    foreach ($walletMap as $id => $w) {
        $flows[$id] = ['income'=>0, 'expense'=>0, 'transfer_in'=>0, 'transfer_out'=>0];
    }
    foreach ((array)$filteredTransactions as $t) {
        $amount = (int)($t['amount'] ?? 0);
        $type = (string)($t['type'] ?? '');
        if ($type === 'income') {
            $wid = (int)($t['wallet_id'] ?? 1);
            if (!isset($flows[$wid])) $flows[$wid] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            $flows[$wid]['income'] += $amount;
        } elseif ($type === 'expense') {
            $wid = (int)($t['wallet_id'] ?? 1);
            if (!isset($flows[$wid])) $flows[$wid] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            $flows[$wid]['expense'] += $amount;
        } elseif ($type === 'transfer') {
            $from = (int)($t['from_wallet_id'] ?? 0);
            $to = (int)($t['to_wallet_id'] ?? 0);
            if (!isset($flows[$from])) $flows[$from] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            if (!isset($flows[$to])) $flows[$to] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            $flows[$from]['transfer_out'] += $amount;
            $flows[$to]['transfer_in'] += $amount;
        }
    }

    $summaryRows = [];
    foreach ($walletRows as $w) {
        $id = (int)($w['id'] ?? 0);
        if ($id <= 0) continue;
        $flow = $flows[$id] ?? ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
        $opening = (int)($openingBalances[$id] ?? 0);
        $closing = (int)($closingBalances[$id] ?? 0);
        $hasActivity = $opening !== 0 || $closing !== 0 || array_sum($flow) !== 0;
        if (!empty($w['archived']) && !$hasActivity) continue;
        $summaryRows[] = [
            'id'=>$id,
            'name'=>(string)($w['name'] ?? ('Dompet #' . $id)),
            'opening'=>$opening,
            'income'=>(int)$flow['income'],
            'expense'=>(int)$flow['expense'],
            'transfer_in'=>(int)$flow['transfer_in'],
            'transfer_out'=>(int)$flow['transfer_out'],
            'closing'=>$closing,
        ];
    }

    return [
        'wallet_map'=>$walletMap,
        'opening'=>$openingBalances,
        'closing'=>$closingBalances,
        'opening_total'=>array_sum($openingBalances),
        'closing_total'=>array_sum($closingBalances),
        'flows'=>$flows,
        'summary_rows'=>$summaryRows,
        'snapshots'=>$snapshots,
    ];
}

function financeReportApplyWalletTransaction(&$balances, $t)
{
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

function buildFinancialStatementPdf($username, $transactions, $meta, $wallets, $allTransactions)
{
    $pdf = new FinanceSimplePdf();
    $blue = [0.09, 0.36, 0.82];
    $lightBlue = [0.94, 0.97, 1.00];
    $lightGreen = [0.94, 0.99, 0.96];
    $lightRed = [1.00, 0.95, 0.95];
    $lightGray = [0.965, 0.972, 0.985];
    $tableHead = [0.94, 0.96, 0.99];
    $zebra = [0.985, 0.988, 0.995];
    $border = [0.84, 0.87, 0.92];
    $muted = [0.39, 0.45, 0.54];
    $text = [0.08, 0.11, 0.18];

    $ctx = financeReportWalletContext(
        $wallets,
        $allTransactions,
        $transactions,
        (string)($meta['from'] ?? ''),
        (string)($meta['to'] ?? '')
    );

    $drawTopHeader = function ($subtitle) use ($pdf, $username, $blue) {
        $pdf->rect(0, 535, 842, 60, $blue, null);
        $pdf->text(32, 567, 'LAPORAN MUTASI KEUANGAN PER DOMPET', 16.5, true, 'left', null, [1,1,1]);
        $pdf->text(32, 549, $subtitle, 8.2, false, 'left', 560, [0.88,0.93,1]);
        $pdf->text(810, 568, 'AKUN', 7.0, false, 'right', null, [0.88,0.93,1]);
        $pdf->text(810, 551, $username, 11.5, true, 'right', 190, [1,1,1]);
    };

    $drawSummaryCards = function () use ($pdf, $ctx, $meta, $lightBlue, $lightGreen, $lightRed, $lightGray, $border, $muted, $text) {
        $cards = [
            [32, 'Saldo awal periode', financeReportRupiah($ctx['opening_total']), $lightBlue],
            [230, 'Total pemasukan', financeReportRupiah((int)($meta['income'] ?? 0)), $lightGreen],
            [428, 'Total pengeluaran', financeReportRupiah((int)($meta['expense'] ?? 0)), $lightRed],
            [626, 'Saldo akhir periode', financeReportRupiah($ctx['closing_total']), $lightGray],
        ];
        foreach ($cards as $card) {
            [$x,$label,$value,$fill] = $card;
            $pdf->rect($x, 430, 184, 46, $fill, $border);
            $pdf->text($x+12, 459, $label, 7.0, false, 'left', 160, $muted);
            $pdf->text($x+12, 440, $value, 11.2, true, 'left', 160, $text);
        }
    };

    $drawWalletTableHeader = function ($y) use ($pdf, $tableHead, $border, $text) {
        $pdf->rect(32, $y-22, 778, 22, $tableHead, $border);
        $pdf->text(40, $y-14, 'Dompet', 7.2, true, 'left', 105, $text);
        $pdf->text(232, $y-14, 'Saldo Awal', 7.2, true, 'right', null, $text);
        $pdf->text(344, $y-14, 'Pemasukan', 7.2, true, 'right', null, $text);
        $pdf->text(456, $y-14, 'Pengeluaran', 7.2, true, 'right', null, $text);
        $pdf->text(568, $y-14, 'Transfer Masuk', 7.2, true, 'right', null, $text);
        $pdf->text(680, $y-14, 'Transfer Keluar', 7.2, true, 'right', null, $text);
        $pdf->text(802, $y-14, 'Saldo Akhir', 7.2, true, 'right', null, $text);
        return $y - 22;
    };

    $drawWalletRow = function ($row, $yTop, $index) use ($pdf, $zebra, $text) {
        $rowH = 26;
        $bottom = $yTop - $rowH;
        if ($index % 2 === 1) $pdf->rect(32, $bottom, 778, $rowH, $zebra, null);
        $yText = $bottom + 9;
        $pdf->text(40, $yText, $row['name'], 7.6, true, 'left', 118, $text);
        $pdf->text(232, $yText, financeReportRupiah($row['opening']), 7.2, false, 'right', 80, $text);
        $pdf->text(344, $yText, financeReportRupiah($row['income']), 7.2, false, 'right', 90, $text);
        $pdf->text(456, $yText, financeReportRupiah($row['expense']), 7.2, false, 'right', 90, $text);
        $pdf->text(568, $yText, financeReportRupiah($row['transfer_in']), 7.2, false, 'right', 90, $text);
        $pdf->text(680, $yText, financeReportRupiah($row['transfer_out']), 7.2, false, 'right', 90, $text);
        $pdf->text(802, $yText, financeReportRupiah($row['closing']), 7.3, true, 'right', 100, $text);
        $pdf->line(32, $bottom, 810, $bottom, 0.91,0.92,0.95,0.35);
        return $bottom;
    };

    // ===== HALAMAN RINGKASAN =====
    $summaryRows = array_values($ctx['summary_rows']);
    $summaryIndex = 0;
    $firstSummaryPage = true;
    do {
        $pdf->addPage();
        $drawTopHeader($firstSummaryPage ? 'Ringkasan saldo dan aliran dana seluruh dompet' : 'Ringkasan saldo per dompet - lanjutan');

        if ($firstSummaryPage) {
            $pdf->text(32, 515, 'Periode', 7.0, false, 'left', null, $muted);
            $pdf->text(32, 500, financeReportPeriodLabel($meta['from'] ?? '', $meta['to'] ?? ''), 9.5, true, 'left', 180, $text);
            $pdf->text(232, 515, 'Jenis transaksi', 7.0, false, 'left', null, $muted);
            $pdf->text(232, 500, financeReportTypeLabel($meta['type'] ?? 'all'), 9.5, true, 'left', 170, $text);
            $pdf->text(432, 515, 'Urutan', 7.0, false, 'left', null, $muted);
            $pdf->text(432, 500, financeReportSortLabel($meta['sort'] ?? 'date_desc'), 9.5, true, 'left', 170, $text);
            $pdf->text(632, 515, 'Dibuat', 7.0, false, 'left', null, $muted);
            $pdf->text(632, 500, date('d/m/Y H:i:s') . ' WITA', 9.5, true, 'left', 175, $text);
            $pdf->line(32, 486, 810, 486, $border[0],$border[1],$border[2],0.6);
            $drawSummaryCards();
            $pdf->text(32, 408, 'RINGKASAN SALDO PER DOMPET', 8.5, true, 'left', null, $text);
            $pdf->text(810, 408, 'Semua nominal ditampilkan penuh, tanpa singkatan.', 7.0, false, 'right', 250, $muted);
            $y = $drawWalletTableHeader(393);
            $minY = 78;
        } else {
            $pdf->text(32, 512, 'RINGKASAN SALDO PER DOMPET (LANJUTAN)', 9.0, true, 'left', null, $text);
            $pdf->text(810, 512, financeReportPeriodLabel($meta['from'] ?? '', $meta['to'] ?? ''), 8.0, false, 'right', null, $text);
            $pdf->line(32, 486, 810, 486, $border[0],$border[1],$border[2],0.6);
            $y = $drawWalletTableHeader(470);
            $minY = 60;
        }

        $rowNo = 0;
        while ($summaryIndex < count($summaryRows) && $y - 26 >= $minY) {
            $y = $drawWalletRow($summaryRows[$summaryIndex], $y, $summaryIndex);
            $summaryIndex++;
            $rowNo++;
        }

        if ($firstSummaryPage) {
            $noteY = max(54, $y - 6);
            $pdf->text(32, $noteY, 'Catatan: transfer antar dompet memindahkan saldo antar rekening/dompet dan tidak mengubah total saldo seluruh dompet.', 7.0, false, 'left', 778, $muted);
        }
        $firstSummaryPage = false;
    } while ($summaryIndex < count($summaryRows));

    // ===== HALAMAN MUTASI / REKENING KORAN =====
    $period = financeReportPeriodLabel($meta['from'] ?? '', $meta['to'] ?? '');
    $drawStatementHeader = function () use ($pdf, $drawTopHeader, $period, $border, $tableHead, $text, $muted) {
        $pdf->addPage();
        $drawTopHeader('Rincian transaksi seperti rekening koran');
        $pdf->text(32, 512, 'MUTASI TRANSAKSI', 9.0, true, 'left', null, $text);
        $pdf->text(810, 512, $period, 8.0, false, 'right', 200, $text);
        $pdf->text(32, 497, 'Debit = uang keluar dari dompet. Kredit = uang masuk ke dompet. Saldo dompet menunjukkan saldo sebelum -> sesudah transaksi.', 7.0, false, 'left', 740, $muted);
        $pdf->line(32, 486, 810, 486, $border[0],$border[1],$border[2],0.6);
        $pdf->rect(32, 447, 778, 23, $tableHead, $border);
        $pdf->text(40, 455, 'Tanggal', 7.0, true, 'left', null, $text);
        $pdf->text(108, 455, 'Dompet', 7.0, true, 'left', null, $text);
        $pdf->text(218, 455, 'Keterangan', 7.0, true, 'left', null, $text);
        $pdf->text(518, 455, 'Debit', 7.0, true, 'right', null, $text);
        $pdf->text(600, 455, 'Kredit', 7.0, true, 'right', null, $text);
        $pdf->text(610, 455, 'Saldo Dompet', 7.0, true, 'left', null, $text);
        $pdf->text(802, 455, 'Total Saldo', 7.0, true, 'right', null, $text);
        return 445.0;
    };

    $y = $drawStatementHeader();
    if (!$transactions) {
        $pdf->text(421, 360, 'Tidak ada transaksi yang sesuai dengan filter.', 10, false, 'center', null, $muted);
    }

    foreach (array_values((array)$transactions) as $idx => $t) {
        $type = (string)($t['type'] ?? '');
        $rowH = $type === 'transfer' ? 46.0 : 38.0;
        if ($y - $rowH < 52) $y = $drawStatementHeader();

        $bottom = $y - $rowH;
        if ($idx % 2 === 1) $pdf->rect(32, $bottom, 778, $rowH, $zebra, null);
        $pdf->line(32, $bottom, 810, $bottom, 0.91,0.92,0.95,0.35);

        $id = (int)($t['id'] ?? 0);
        $snap = $ctx['snapshots'][$id] ?? [];
        $amount = (int)($t['amount'] ?? 0);
        $date = financeReportDateTime($t['transaction_date'] ?? '', $t['created_at'] ?? '');
        $category = trim((string)($t['category'] ?? '')) ?: ($type === 'transfer' ? 'Transfer Antar Dompet' : 'Lainnya');
        $note = trim((string)($t['note'] ?? ''));
        if ($note === '') $note = $type === 'transfer' ? 'Transfer antar dompet' : $category;

        $pdf->text(40, $y-18, $date, 6.0, false, 'left', 64, $text);

        if ($type === 'transfer') {
            $from = (int)($t['from_wallet_id'] ?? 0);
            $to = (int)($t['to_wallet_id'] ?? 0);
            $fromName = financeReportWalletLabel($from, $ctx['wallet_map']);
            $toName = financeReportWalletLabel($to, $ctx['wallet_map']);
            $pdf->text(108, $y-15, $fromName, 6.7, true, 'left', 104, $text);
            $pdf->text(108, $y-30, '-> ' . $toName, 6.5, false, 'left', 104, $muted);
            $pdf->text(218, $y-16, 'Transfer Antar Dompet', 7.2, true, 'left', 250, $text);
            $pdf->text(218, $y-30, $note, 6.6, false, 'left', 245, $muted);
            $pdf->text(518, $y-21, financeReportRupiah($amount), 7.1, true, 'right', 65, $text);
            $pdf->text(600, $y-21, financeReportRupiah($amount), 7.1, true, 'right', 65, $text);
            $fromBefore = (int)($snap['from_before'] ?? 0);
            $fromAfter = (int)($snap['from_after'] ?? ($fromBefore-$amount));
            $toBefore = (int)($snap['to_before'] ?? 0);
            $toAfter = (int)($snap['to_after'] ?? ($toBefore+$amount));
            $pdf->text(610, $y-16, financeReportRupiah($fromBefore) . ' -> ' . financeReportRupiah($fromAfter), 6.15, true, 'left', 135, $text);
            $pdf->text(610, $y-31, financeReportRupiah($toBefore) . ' -> ' . financeReportRupiah($toAfter), 6.15, false, 'left', 135, $text);
        } else {
            $wid = (int)($t['wallet_id'] ?? 1);
            $walletName = financeReportWalletLabel($wid, $ctx['wallet_map']);
            $pdf->text(108, $y-21, $walletName, 6.9, true, 'left', 104, $text);
            $pdf->text(218, $y-16, $category, 7.2, true, 'left', 250, $text);
            $pdf->text(218, $y-30, $note, 6.6, false, 'left', 245, $muted);
            if ($type === 'expense') {
                $pdf->text(518, $y-21, financeReportRupiah($amount), 7.1, true, 'right', 65, $text);
                $pdf->text(600, $y-21, '-', 7.1, false, 'right', null, $text);
            } elseif ($type === 'income') {
                $pdf->text(518, $y-21, '-', 7.1, false, 'right', null, $text);
                $pdf->text(600, $y-21, financeReportRupiah($amount), 7.1, true, 'right', 65, $text);
            } else {
                $pdf->text(518, $y-21, '-', 7.1, false, 'right', null, $text);
                $pdf->text(600, $y-21, '-', 7.1, false, 'right', null, $text);
            }
            $before = (int)($snap['before'] ?? 0);
            $after = (int)($snap['after'] ?? $before);
            $pdf->text(610, $y-21, financeReportRupiah($before) . ' -> ' . financeReportRupiah($after), 6.4, true, 'left', 135, $text);
        }

        $pdf->text(802, $y-21, financeReportRupiah((int)($snap['total_after'] ?? $ctx['closing_total'])), 7.1, true, 'right', 88, $text);
        $y = $bottom;
    }

    $pdf->addFooterToAll($username);
    return $pdf->output();
}

