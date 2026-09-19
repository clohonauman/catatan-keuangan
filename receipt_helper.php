<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/image_storage_helper.php';

function receiptUserDir() {
    $u = authCurrentUser();
    if (!$u) throw new RuntimeException('User belum login.');
    $dir = DATA_DIR.'/receipts/user_'.(int)$u['id'];
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Tidak dapat membuat folder foto nota. Periksa permission folder data.');
    }
    return $dir;
}

function receiptAllowedMime($mime) {
    return in_array($mime, ['image/jpeg','image/png','image/webp'], true);
}

function saveReceiptUpload($file) {
    if (!is_array($file) || !isset($file['tmp_name'])) return null;
    $err = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Upload foto gagal. Kode: '.$err);

    $size = isset($file['size']) ? (int)$file['size'] : 0;
    if ($size <= 0) throw new RuntimeException('File foto kosong.');
    if ($size > 8 * 1024 * 1024) throw new RuntimeException('Ukuran foto terlalu besar. Maksimal 8 MB.');

    $info = @getimagesize($file['tmp_name']);
    if (!$info || empty($info['mime']) || !receiptAllowedMime($info['mime'])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WebP.');
    }

    try { $token = bin2hex(random_bytes(16)); }
    catch (Throwable $e) { $token = sha1(uniqid('', true).mt_rand()); }
    $baseName = date('Ymd_His').'_'.$token;
    $stored = imageStorageSaveUpload((string)$file['tmp_name'], receiptUserDir(), $baseName, [
        'max_side'=>1600,
        'quality'=>78,
        'threshold_bytes'=>750 * 1024,
        'allow_cli_copy'=>true,
    ]);

    return [
        'file'=>$stored['file'],
        'mime'=>$stored['mime'],
        'size'=>$stored['size'],
        'width'=>$stored['width'],
        'height'=>$stored['height'],
        'original_size'=>$stored['original_size'],
        'optimized'=>!empty($stored['optimized']),
        'original_name'=>substr((string)($file['name'] ?? 'foto-nota'), 0, 120)
    ];
}

function receiptFilePath($fileName) {
    $fileName = basename((string)$fileName);
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $fileName)) return null;
    $path = receiptUserDir().'/'.$fileName;
    return is_file($path) ? $path : null;
}

function receiptParseMoneyToken($raw) {
    $s = strtolower(trim((string)$raw));
    $s = str_replace(['rp','idr',' '], '', $s);
    $s = preg_replace('/[^0-9\.,]/', '', $s);
    if ($s === '') return 0;
    // Format dengan sen: 125.000,00 atau 125,000.00
    if (preg_match('/^(\d{1,3}(?:\.\d{3})+),\d{2}$/', $s, $m)) return (int)str_replace('.', '', $m[1]);
    if (preg_match('/^(\d{1,3}(?:,\d{3})+)\.\d{2}$/', $s, $m)) return (int)str_replace(',', '', $m[1]);
    if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $s)) return (int)str_replace('.', '', $s);
    if (preg_match('/^\d{1,3}(?:,\d{3})+$/', $s)) return (int)str_replace(',', '', $s);
    if (substr_count($s,'.') + substr_count($s,',') > 1) return (int)str_replace(['.',','], '', $s);
    if (preg_match('/^\d+[\.,]\d{2}$/', $s)) return (int)floor((float)str_replace(',', '.', $s));
    if (preg_match('/^\d+[\.,]\d{3}$/', $s)) return (int)str_replace(['.',','], '', $s);
    return (int)preg_replace('/\D/', '', $s);
}

/**
 * Heuristik total nota. Mengutamakan baris GRAND TOTAL / TOTAL / JUMLAH / BAYAR,
 * dan menghindari subtotal, pajak, kembalian, nomor telepon, tanggal, dll.
 */
function detectReceiptAmountFromText($text) {
    $text = trim((string)$text);
    if ($text === '') return ['amount'=>0,'score'=>0,'line'=>''];
    $lines = preg_split('/\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $count = max(1, count($lines));
    $best = ['amount'=>0,'score'=>0,'line'=>''];

    foreach ($lines as $i=>$lineRaw) {
        $line = trim(preg_replace('/\s+/u',' ', $lineRaw));
        if ($line === '') continue;
        $low = strtolower($line);

        if (preg_match('/\b(telp|telepon|phone|wa|whatsapp|npwp|invoice\s*no|no\.?\s*struk|order\s*id)\b/i', $low)) continue;
        if (preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $line) && !preg_match('/\b(total|jumlah|bayar|amount)\b/i', $low)) continue;

        preg_match_all('/(?:rp\.?\s*)?\d{1,3}(?:[\.,]\d{3})+(?:[\.,]\d{2})?|(?:rp\.?\s*)?\d{4,9}(?:[\.,]\d{2})?/iu', $line, $mm);
        if (empty($mm[0])) continue;

        foreach ($mm[0] as $token) {
            $amount = receiptParseMoneyToken($token);
            if ($amount < 500 || $amount > 500000000) continue;

            $score = 0;
            if (preg_match('/\bgrand\s*total\b/i', $low)) $score += 125;
            elseif (preg_match('/\b(total\s*(bayar|payment|amount)|amount\s*due|total)\b/i', $low)) $score += 105;
            elseif (preg_match('/\b(jumlah|bayar|payment|tagihan)\b/i', $low)) $score += 75;
            if (preg_match('/\bsub\s*total|subtotal\b/i', $low)) $score -= 55;
            if (preg_match('/\b(kembali|kembalian|change|diskon|discount|hemat|ppn|pajak|tax|service|dpp)\b/i', $low)) $score -= 70;
            if (preg_match('/rp\.?/i', $line)) $score += 16;
            if (preg_match('/[\.,]\d{3}/', $token)) $score += 8;
            $score += (int)round(($i / $count) * 18); // total biasanya dekat bagian bawah
            if ($amount >= 1000) $score += 4;

            if ($score > $best['score'] || ($score === $best['score'] && $amount > $best['amount'])) {
                $best = ['amount'=>$amount,'score'=>$score,'line'=>$line];
            }
        }
    }
    return $best;
}

function detectReceiptDateFromText($text) {
    if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/', (string)$text, $m)) {
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3]; if ($y < 100) $y += 2000;
        if (checkdate($mo,$d,$y)) return sprintf('%04d-%02d-%02d',$y,$mo,$d);
    }
    return date('Y-m-d');
}

function detectReceiptMerchant($text) {
    $lines = preg_split('/\R+/u', trim((string)$text), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($lines,0,5) as $line) {
        $line = trim(preg_replace('/\s+/u',' ', $line));
        if (strlen($line) < 3 || strlen($line) > 70) continue;
        if (preg_match('/^(struk|receipt|nota|invoice|tanggal|date|telp|phone)\b/i',$line)) continue;
        if (preg_match('/[A-Za-z]{3}/',$line)) return $line;
    }
    return '';
}


/** Cek apakah file foto masih dipakai oleh chat atau transaksi user aktif. */
function receiptFileIsReferenced($fileName) {
    $fileName = basename((string)$fileName);
    if ($fileName === '') return false;
    $data = readData();
    foreach (($data['chats'] ?? []) as $chat) {
        if (($chat['attachment']['file'] ?? '') === $fileName) return true;
    }
    foreach (($data['transactions'] ?? []) as $tx) {
        if (($tx['attachment']['file'] ?? '') === $fileName) return true;
    }
    return false;
}

/** Hapus file foto hanya bila sudah tidak direferensikan data apa pun. */
function deleteReceiptFileIfUnused($fileName) {
    $fileName = basename((string)$fileName);
    if ($fileName === '' || receiptFileIsReferenced($fileName)) return false;
    $path = receiptFilePath($fileName);
    return $path ? @unlink($path) : false;
}
