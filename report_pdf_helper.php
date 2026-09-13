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
            $footer .= sprintf('0.55 0.58 0.64 rg BT /F1 7.5 Tf 1 0 0 1 32 18 Tm (%s) Tj ET' . "\n", $this->escText('Pencatatan Keuangan - ' . $username));
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

function buildFinancialStatementPdf($username, $transactions, $meta, $initialBalance, $openingBalance, $closingBalance, $balanceMap)
{
    $pdf = new FinanceSimplePdf();
    $blue = [0.09, 0.36, 0.82];
    $lightBlue = [0.94, 0.97, 1.00];
    $lightGray = [0.965, 0.972, 0.985];
    $border = [0.84, 0.87, 0.92];
    $red = [0.72, 0.12, 0.10];
    $green = [0.04, 0.48, 0.27];

    $drawHeader = function ($firstPage) use ($pdf, $username, $meta, $blue, $lightBlue, $lightGray, $border) {
        $pdf->rect(0, 535, 842, 60, $blue, null);
        $pdf->text(32, 567, 'LAPORAN MUTASI KEUANGAN', 18, true, 'left', null, [1, 1, 1]);
        $pdf->text(32, 549, 'Rekening koran pribadi - Catatan Keuangan', 9, false, 'left', null, [0.88, 0.93, 1]);
        $pdf->text(810, 568, 'AKUN', 7.5, false, 'right', null, [0.88, 0.93, 1]);
        $pdf->text(810, 551, $username, 12, true, 'right', 180, [1, 1, 1]);

        if ($firstPage) {
            $pdf->text(32, 515, 'Periode', 7.5, false);
            $pdf->text(32, 500, financeReportPeriodLabel($meta['from'] ?? '', $meta['to'] ?? ''), 10, true, 'left', 180);
            $pdf->text(230, 515, 'Jenis transaksi', 7.5, false);
            $pdf->text(230, 500, financeReportTypeLabel($meta['type'] ?? 'all'), 10, true, 'left', 150);
            $pdf->text(410, 515, 'Urutan', 7.5, false);
            $pdf->text(410, 500, financeReportSortLabel($meta['sort'] ?? 'date_desc'), 10, true, 'left', 150);
            $pdf->text(610, 515, 'Dibuat', 7.5, false);
            $pdf->text(610, 500, date('d/m/Y H:i') . ' WITA', 10, true, 'left', 180);
            $pdf->line(32, 486, 810, 486, $border[0], $border[1], $border[2]);
        } else {
            $pdf->text(32, 512, 'Lanjutan transaksi', 9, true);
            $pdf->text(810, 512, financeReportPeriodLabel($meta['from'] ?? '', $meta['to'] ?? ''), 8, false, 'right');
        }
    };

    $drawTableHeader = function ($y) use ($pdf, $lightGray, $border) {
        $pdf->rect(32, $y - 18, 778, 22, $lightGray, $border);
        $pdf->text(40, $y - 10, 'Tanggal', 8, true);
        $pdf->text(112, $y - 10, 'Keterangan', 8, true);
        $pdf->text(360, $y - 10, 'Kategori', 8, true);
        $pdf->text(570, $y - 10, 'Pengeluaran', 8, true, 'right');
        $pdf->text(680, $y - 10, 'Pemasukan', 8, true, 'right');
        $pdf->text(802, $y - 10, 'Saldo', 8, true, 'right');
        return $y - 23;
    };

    $drawSummaryCard = function ($x, $label, $value, $fill) use ($pdf, $border) {
        $pdf->rect($x, 432, 184, 44, $fill, $border);
        $pdf->text($x + 12, 460, $label, 7.5, false);
        $pdf->text($x + 12, 442, $value, 11.5, true, 'left', 160);
    };

    $pdf->addPage();
    $drawHeader(true);
    $drawSummaryCard(32, 'Saldo awal periode', financeReportRupiah($openingBalance), $lightBlue);
    $drawSummaryCard(230, 'Total pemasukan', financeReportRupiah($meta['income'] ?? 0), [0.94, 0.99, 0.96]);
    $drawSummaryCard(428, 'Total pengeluaran', financeReportRupiah($meta['expense'] ?? 0), [1.00, 0.95, 0.95]);
    $drawSummaryCard(626, 'Saldo akun akhir periode', financeReportRupiah($closingBalance), $lightGray);

    $pdf->text(32, 413, 'Jumlah transaksi hasil filter: ' . (int)($meta['count'] ?? count($transactions)), 8.5, true);
    if (($meta['sort'] ?? '') === 'amount_desc' || ($meta['sort'] ?? '') === 'amount_asc') {
        $pdf->text(810, 413, 'Kolom saldo mengikuti urutan kronologis akun.', 7.5, false, 'right');
    }

    $y = $drawTableHeader(394);
    $rowH = 21;
    $firstMinY = 48;
    $pageMinY = 48;

    if (!$transactions) {
        $pdf->text(421, 340, 'Tidak ada transaksi yang sesuai dengan filter.', 10, false, 'center');
    }

    foreach ($transactions as $idx => $t) {
        if ($y - $rowH < $firstMinY) {
            $pdf->addPage();
            $drawHeader(false);
            $y = $drawTableHeader(486);
            $firstMinY = $pageMinY;
        }

        if ($idx % 2 === 1) $pdf->rect(32, $y - $rowH + 3, 778, $rowH, [0.985, 0.988, 0.995], null);
        $pdf->line(32, $y - $rowH + 3, 810, $y - $rowH + 3, 0.91, 0.92, 0.95, 0.4);

        $date = financeReportDate($t['transaction_date'] ?? '');
        $note = trim((string)($t['note'] ?? ''));
        $amount = (int)($t['amount'] ?? 0);
        if (($t['type'] ?? '') === 'transfer') {
            $note = 'Transfer internal ' . financeReportRupiah($amount) . ($note !== '' ? ' - ' . $note : '');
        } elseif ($note === '') $note = (($t['type'] ?? '') === 'income' ? 'Pemasukan' : 'Pengeluaran');
        $category = trim((string)($t['category'] ?? 'Lainnya'));
        $id = (int)($t['id'] ?? 0);
        $balance = isset($balanceMap[$id]) ? (int)$balanceMap[$id] : 0;

        $pdf->text(40, $y - 12, $date, 8.2, false, 'left', 65);
        $pdf->text(112, $y - 12, $note, 8.2, false, 'left', 235);
        $pdf->text(360, $y - 12, $category, 8.2, false, 'left', 95);
        if (($t['type'] ?? '') === 'expense') {
            $pdf->text(570, $y - 12, financeReportRupiah($amount), 8.2, true, 'right');
        } else {
            $pdf->text(570, $y - 12, '-', 8.2, false, 'right');
        }
        if (($t['type'] ?? '') === 'income') {
            $pdf->text(680, $y - 12, financeReportRupiah($amount), 8.2, true, 'right');
        } else {
            $pdf->text(680, $y - 12, '-', 8.2, false, 'right');
        }
        $pdf->text(802, $y - 12, financeReportRupiah($balance), 8.2, true, 'right');
        $y -= $rowH;
    }

    $pdf->line(32, $y + 3, 810, $y + 3, 0.78, 0.81, 0.87, 0.8);
    $pdf->addFooterToAll($username);
    return $pdf->output();
}
