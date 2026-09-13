<?php
require_once __DIR__.'/db.php';

define('LEARNING_FILE', DATA_DIR.'/assistant_learning.json');

/**
 * Hanya Super Admin yang boleh membuat, mengubah, menghapus,
 * atau menjalankan mode pembelajaran asisten.
 *
 * Rule yang sudah tersimpan tetap dapat DIPAKAI oleh semua user.
 */
function learningCanManage(): bool {
    return function_exists('authIsSuperAdmin') && authIsSuperAdmin();
}

function learningRequireSuperAdmin(): void {
    if (!learningCanManage()) {
        throw new RuntimeException('Fitur Pembelajaran hanya tersedia untuk Super Admin.');
    }
}


function learningDefaultData() {
    return ['rules'=>[], 'meta'=>['next_id'=>1]];
}

function learningEnsureData() {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    if (!file_exists(LEARNING_FILE)) {
        file_put_contents(LEARNING_FILE, json_encode(learningDefaultData(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

function learningReadData() {
    learningEnsureData();
    $raw = @file_get_contents(LEARNING_FILE);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? array_replace_recursive(learningDefaultData(), $data) : learningDefaultData();
}

function learningMutateData($fn) {
    learningEnsureData();
    $fp = fopen(LEARNING_FILE, 'c+');
    if (!$fp) throw new RuntimeException('Tidak dapat membuka data pembelajaran. Periksa permission folder data.');
    flock($fp, LOCK_EX);
    rewind($fp);
    $data = json_decode((string)stream_get_contents($fp), true);
    if (!is_array($data)) $data = learningDefaultData();
    else $data = array_replace_recursive(learningDefaultData(), $data);
    $result = call_user_func_array($fn, [&$data]);
    rewind($fp); ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    return $result;
}

function learningNormalizePhrase($text) {
    $s = strtolower(trim((string)$text));
    $s = str_replace(['–','—'], '-', $s);
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function learningPending() {
    $p = setting('assistant_learning_pending', null);
    return is_array($p) && !empty($p['phrase']) ? $p : null;
}

function learningSetPending($phrase) {
    $phrase = trim((string)$phrase);
    if (strlen($phrase) > 160) $phrase = substr($phrase, 0, 160);
    setSetting('assistant_learning_pending', [
        'phrase'=>$phrase,
        'key'=>learningNormalizePhrase($phrase),
        'asked_at'=>date('Y-m-d H:i:s')
    ]);
}

function learningClearPending() { setSetting('assistant_learning_pending', null); }

function learningListRules() {
    $rules = learningReadData()['rules'];
    usort($rules, function($a,$b){ return strcmp((string)($b['updated_at']??$b['created_at']??''),(string)($a['updated_at']??$a['created_at']??'')); });
    return $rules;
}

function learningFindRule($message) {
    $key = learningNormalizePhrase($message);
    if ($key === '') return null;
    foreach (learningReadData()['rules'] as $r) {
        if (!empty($r['enabled']) && (string)($r['key']??'') === $key) return $r;
    }
    return null;
}

function learningSaveRule($phrase, $response, $userId=0) {
    learningRequireSuperAdmin();
    $phrase = trim((string)$phrase); $response = trim((string)$response);
    $key = learningNormalizePhrase($phrase);
    if ($key === '') throw new RuntimeException('Kalimat pemicu tidak boleh kosong.');
    if ($response === '') throw new RuntimeException('Balasan tidak boleh kosong.');
    if (strlen($phrase) > 160) $phrase = substr($phrase,0,160);
    if (strlen($response) > 1000) $response = substr($response,0,1000);
    return learningMutateData(function(&$d) use($phrase,$response,$key,$userId) {
        foreach ($d['rules'] as &$r) {
            if ((string)($r['key']??'') === $key) {
                $r['phrase']=$phrase; $r['response']=$response; $r['enabled']=true;
                $r['updated_at']=date('Y-m-d H:i:s'); $r['updated_by_user_id']=(int)$userId;
                return $r;
            }
        }
        $r=['id'=>(int)$d['meta']['next_id']++, 'key'=>$key, 'phrase'=>$phrase, 'response'=>$response, 'enabled'=>true,
            'created_by_user_id'=>(int)$userId, 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s')];
        $d['rules'][]=$r; return $r;
    });
}

function learningDeleteRule($id) {
    learningRequireSuperAdmin();
    $id=(int)$id;
    return learningMutateData(function(&$d) use($id){
        $before=count($d['rules']);
        $d['rules']=array_values(array_filter($d['rules'], function($r)use($id){return (int)($r['id']??0)!==$id;}));
        return count($d['rules']) < $before;
    });
}

function learningRenderResponse($response) {
    $u = authCurrentUser(); $s = summary();
    $days=['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
    return strtr((string)$response, [
        '{nama}'=>$u ? (string)$u['username'] : 'pengguna',
        '{saldo}'=>rupiah($s['balance']),
        '{tanggal}'=>date('d/m/Y'),
        '{hari}'=>$days[date('l')] ?? date('l')
    ]);
}

function learningLooksFinancial($message) {
    if (function_exists('smartIsQuestion') && smartIsQuestion($message)) return true;
    $t = learningNormalizePhrase($message);
    if (preg_match('/\d/u', $message) && preg_match(amountRegex(), $message)) return true;
    foreach (['saldo','pemasukan','pendapatan','pengeluaran','bensin','makan','belanja','cicilan','angsuran','bayar','gaji','batas','budget','anggaran','nota'] as $w) {
        if (strpos($t,$w)!==false) return true;
    }
    return false;
}

/** Tangani jawaban pengajaran sebelum parser transaksi dijalankan. */
function learningHandleTeachingInput($message) {
    if (!learningCanManage()) return ['handled'=>false];
    $message=trim((string)$message);
    $pending=learningPending();
    $user=authCurrentUser(); $uid=$user?(int)$user['id']:0;

    // Perintah mandiri: ajarkan "oke" => Siap, lanjut saja
    if (preg_match('/^(?:ajarkan|pelajari|belajar)\s+["“]?(.+?)["”]?\s*(?:=>|=|menjadi)\s*(.+)$/iu', $message, $m)) {
        $rule=learningSaveRule(trim($m[1]), trim($m[2]), $uid); learningClearPending();
        return ['handled'=>true,'reply'=>'✓ Dipelajari untuk semua pengguna. Jika ada pesan “'.$rule['phrase'].'”, saya akan membalas: “'.learningRenderResponse($rule['response']).'”.'];
    }

    if (!$pending) return ['handled'=>false];
    $n=learningNormalizePhrase($message);
    if (in_array($n,['batal','batalkan','cancel','skip','lewati','tidak jadi','ga jadi','gak jadi','ndak jadi'],true)) {
        learningClearPending();
        return ['handled'=>true,'reply'=>'Baik, pembelajaran untuk “'.$pending['phrase'].'” dibatalkan.'];
    }

    // Jika user langsung pindah ke transaksi/perintah keuangan, batalkan mode belajar dan proses normal.
    if (learningLooksFinancial($message) && !preg_match('/^(?:ajarkan|balas|jawab)\s*:/iu',$message)) {
        learningClearPending();
        return ['handled'=>false];
    }

    $response=$message;
    if (preg_match('/^(?:ajarkan|balas|jawab)\s*:\s*(.+)$/isu',$message,$m)) $response=trim($m[1]);
    if ($response==='') return ['handled'=>true,'reply'=>'Balas dengan jawaban yang ingin saya pelajari, atau ketik “batal”.'];

    $rule=learningSaveRule($pending['phrase'],$response,$uid); learningClearPending();
    return ['handled'=>true,'reply'=>'✓ Sudah saya pelajari. Mulai sekarang “'.$rule['phrase'].'” akan saya balas: “'.learningRenderResponse($rule['response']).'”. Aturan ini berlaku untuk semua pengguna.'];
}

function learningStartForUnknown($message) {
    $phrase=trim((string)$message);
    if ($phrase==='') return null;

    // User biasa hanya memakai rule yang sudah diajarkan Super Admin.
    // Mereka tidak dapat membuat sesi pembelajaran baru.
    if (!learningCanManage()) {
        return 'Maaf, saya belum mengenali maksud “'.$phrase.'”. Coba gunakan perintah keuangan yang lebih spesifik, misalnya “makan 20rb”, “berapa saldo?”, atau “1jt - 250rb”.';
    }

    learningSetPending($phrase);
    return 'Saya belum mengenali maksud “'.$phrase.'”. Mode Pembelajaran Super Admin aktif. Jika pengguna mengatakan “'.$phrase.'”, saya sebaiknya membalas apa? Tulis balasannya sekarang, atau ketik “batal”.';
}
