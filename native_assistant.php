<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/learning_helper.php';
require_once __DIR__.'/finance_features.php';
require_once __DIR__.'/smart_finance_helper.php';

function rupiah($n): string { return 'Rp'.number_format((int)$n,0,',','.'); }

function norm(string $s): string {
    $s = strtolower(trim($s));
    $s = str_replace(['–','—'], '-', $s);
    return preg_replace('/\s+/u',' ',$s);
}

/**
 * Normalisasi bahasa chat sebelum diproses asisten.
 *
 * Tujuannya bukan mengubah isi bebas pengguna secara agresif, tetapi
 * memperbaiki typo/ singkatan yang umum pada konteks keuangan. Nominal,
 * tanda baca, nama bebas, dan kata yang tidak dikenal dibiarkan apa adanya.
 *
 * Contoh:
 * - "pmsukan seabank?"     -> "pemasukan seabank?"
 * - "pngluaran 20rb cash" -> "pengeluaran 20rb cash"
 * - "brp sldo skrg?"      -> "berapa saldo sekarang?"
 */
function chatNormalizationAliases(): array {
    return [
        // Pemasukan / pengeluaran
        'pmsukan'=>'pemasukan', 'pemsukan'=>'pemasukan', 'pmasukan'=>'pemasukan',
        'pemasukkan'=>'pemasukan', 'pemasukn'=>'pemasukan', 'pemasukam'=>'pemasukan',
        'pemasukna'=>'pemasukan', 'pemasukann'=>'pemasukan',
        'pngeluaran'=>'pengeluaran', 'pngluaran'=>'pengeluaran', 'pgeluaran'=>'pengeluaran',
        'pengluaran'=>'pengeluaran', 'pengeluran'=>'pengeluaran', 'pengeluarann'=>'pengeluaran',
        'pengeluarna'=>'pengeluaran', 'pngeluran'=>'pengeluaran',
        'pendpatn'=>'pendapatan', 'pendaptan'=>'pendapatan', 'pendaptn'=>'pendapatan',

        // Bahasa percakapan dan pertanyaan keuangan.
        'sampe'=>'sampai', 'smpe'=>'sampai', 'smp'=>'sampai',
        'gjian'=>'gajian', 'gajiaan'=>'gajian', 'gajihan'=>'gajian', 'amn'=>'aman', 'ckp'=>'cukup',
        'sya'=>'saya', 'sy'=>'saya', 'aq'=>'aku', 'gw'=>'saya', 'gue'=>'saya',
        'bln'=>'bulan', 'mgg'=>'minggu', 'thn'=>'tahun', 'tgl'=>'tanggal',
        'trbsr'=>'terbesar', 'trbesar'=>'terbesar', 'terbsar'=>'terbesar',
        'trkcl'=>'terkecil', 'trakhir'=>'terakhir', 'trbnyk'=>'terbanyak',
        'pnglrn'=>'pengeluaran', 'pngeluaran'=>'pengeluaran', 'pmskn'=>'pemasukan', 'penghasilan'=>'pendapatan',
        'jml'=>'jumlah', 'jmlh'=>'jumlah', 'ratarata'=>'rata-rata',
        'duit'=>'uang', 'skrng'=>'sekarang', 'skrang'=>'sekarang',
        'hr'=>'hari', 'hri'=>'hari', 'kmren'=>'kemarin', 'bnyk'=>'banyak', 'kmna'=>'kemana',
        'pgn'=>'ingin', 'utk'=>'untuk', 'sm'=>'sama', 'dgn'=>'dengan',
        // Pertanyaan / waktu
        'brp'=>'berapa', 'brpa'=>'berapa', 'brapa'=>'berapa', 'berapaa'=>'berapa',
        'sld'=>'saldo', 'sldo'=>'saldo', 'saldoo'=>'saldo',
        'skrg'=>'sekarang', 'skarang'=>'sekarang', 'sekrang'=>'sekarang',
        'kmrn'=>'kemarin', 'kemaren'=>'kemarin', 'kemrin'=>'kemarin',
        'bsok'=>'besok',

        // Kata kerja transaksi
        'msuk'=>'masuk', 'masukk'=>'masuk',
        'kluar'=>'keluar', 'kelur'=>'keluar', 'keluarr'=>'keluar',
        'dpt'=>'dapat', 'dpet'=>'dapat', 'dapet'=>'dapat',
        'trima'=>'terima', 'trimaa'=>'terima',
        'trnsfer'=>'transfer', 'tranfer'=>'transfer', 'trasnfer'=>'transfer', 'tf'=>'transfer',
        'dri'=>'dari',
        'byr'=>'bayar', 'bayarr'=>'bayar',
        'bli'=>'beli',

        // Kategori umum
        'blnja'=>'belanja', 'belnja'=>'belanja',
        'mkn'=>'makan',
        'bensn'=>'bensin', 'bensin2'=>'bensin',
        'ciciln'=>'cicilan', 'cicilann'=>'cicilan',
        'tagihn'=>'tagihan', 'tagihann'=>'tagihan',
        'gji'=>'gaji', 'gajih'=>'gaji',
        'bonuz'=>'bonus',
        'tunia'=>'tunai', 'tunaii'=>'tunai',

        // Bank yang sering dipakai pada aplikasi
        'sebank'=>'seabank', 'seabnk'=>'seabank', 'seabnkk'=>'seabank',
        'shopepay'=>'shopeepay', 'shoppepay'=>'shopeepay',
        'gopai'=>'gopay',
    ];
}

function chatNormalizationVocabulary(): array {
    // Fuzzy hanya untuk kata yang cukup khas agar nama/catatan bebas user
    // tidak dikoreksi secara berlebihan.
    return [
        'pemasukan','pengeluaran','pendapatan','saldo','transfer',
        'cicilan','tagihan','sekarang','kemarin','besok','belanja',
        'bensin','seabank','shopeepay','gopay'
    ];
}

function normalizeChatMessage(string $message): string {
    $t = norm($message);
    if ($t === '') return '';

    // Normalisasi frasa/abreviasi sebelum token per kata.
    $phrases = [
        'sea bank'=>'seabank',
        'bank sea'=>'seabank',
        'hr ini'=>'hari ini',
        'hri ini'=>'hari ini',
        'bln ini'=>'bulan ini',
        'bulanini'=>'bulan ini',
        'mgg ini'=>'minggu ini',
        'mingguini'=>'minggu ini',
    ];
    foreach ($phrases as $from=>$to) {
        $pattern = '/(?<![a-z0-9])'.preg_quote($from, '/').'(?![a-z0-9])/u';
        $t = preg_replace($pattern, $to, $t);
    }

    $aliases = chatNormalizationAliases();
    $vocabulary = chatNormalizationVocabulary();

    $t = preg_replace_callback('/(?<![a-z0-9])([a-z]{2,})(?![a-z0-9])/u', function($m) use ($aliases, $vocabulary) {
        $word = strtolower((string)$m[1]);
        if (isset($aliases[$word])) return $aliases[$word];

        // Jangan fuzzy-correct kata sangat pendek. Untuk kata khas >= 8 huruf,
        // toleransi maksimal 2 edit; selebihnya hanya 1 edit.
        $len = strlen($word);
        if ($len < 4) return $word;
        $best = $word;
        $bestDistance = 999;
        foreach ($vocabulary as $candidate) {
            if (abs(strlen($candidate) - $len) > 2) continue;
            $distance = levenshtein($word, $candidate);
            $limit = max(strlen($candidate), $len) >= 8 ? 2 : 1;
            if ($distance <= $limit && $distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }
        return $best;
    }, $t);

    // Rapikan whitespace tanpa mengubah nominal/punctuation pengguna.
    $t = preg_replace('/\s+([?!,.;:])/u', '$1', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim($t);
}

function containsText(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) !== false;
}

/**
 * Mengubah nominal bahasa Indonesia menjadi integer rupiah.
 * Contoh:
 * 1.100.000 => 1100000
 * 1,100,000 => 1100000
 * 1,1jt / 1.1jt => 1100000
 * 50rb / 50k => 50000
 * Rp 500.000 => 500000
 */
function parseAmount(string $raw): int {
    $s = norm($raw);
    $s = preg_replace('/^rp\s*/iu', '', $s);
    $s = preg_replace('/\s+/u', '', $s);

    if ($s === '') return 0;

    // Ambil unit di akhir, bila ada.
    $unit = '';
    if (preg_match('/(juta|jt|ribu|rb|k)$/iu', $s, $um)) {
        $unit = strtolower($um[1]);
        $s = substr($s, 0, -strlen($um[0]));
    }

    // Sisakan hanya angka dan separator.
    $s = preg_replace('/[^0-9\.,]/u', '', $s);
    if ($s === '') return 0;

    // Jika memakai unit jt/juta, satu separator tunggal biasanya desimal.
    if (in_array($unit, ['jt','juta'], true)) {
        if (preg_match('/^\d+[\.,]\d+$/', $s)) {
            $number = (float)str_replace(',', '.', $s);
        } else {
            // Banyak separator dianggap pemisah ribuan.
            $number = (float)str_replace(['.', ','], '', $s);
        }
        return (int)round($number * 1000000);
    }

    // Jika memakai unit rb/ribu/k, satu separator tunggal biasanya desimal.
    if (in_array($unit, ['rb','ribu','k'], true)) {
        if (preg_match('/^\d+[\.,]\d+$/', $s)) {
            $number = (float)str_replace(',', '.', $s);
        } else {
            $number = (float)str_replace(['.', ','], '', $s);
        }
        return (int)round($number * 1000);
    }

    // Tanpa unit: format pemisah ribuan Indonesia/internasional.
    // 1.100.000 / 1,100,000 / 500.000 / 500,000
    if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $s)) {
        return (int)str_replace('.', '', $s);
    }
    if (preg_match('/^\d{1,3}(?:,\d{3})+$/', $s)) {
        return (int)str_replace(',', '', $s);
    }

    // Campuran separator, mis. 1.100,000: anggap semuanya separator ribuan
    // jika bagian-bagiannya jelas kelompok 3 digit.
    if (substr_count($s, '.') + substr_count($s, ',') > 1) {
        return (int)str_replace(['.', ','], '', $s);
    }

    // Angka biasa. Untuk keamanan pencatatan rupiah, separator tanpa unit
    // juga dihapus jika diikuti tepat 3 digit, mis. 1.500 => 1500.
    if (preg_match('/^\d+[\.,]\d{3}$/', $s)) {
        return (int)str_replace(['.', ','], '', $s);
    }

    return (int)preg_replace('/\D/u', '', $s);
}


function assistantResolveFollowup(string $message): string {
    $current=normalizeChatMessage($message);
    $t=norm($current);
    if($t==='' || strlen($t)>120) return $current;
    $looksFollowup=(bool)preg_match('/^(?:kalau|kalo|terus|lalu|yang|bagaimana dengan|gimana dengan)\b/u',$t)
        || (bool)preg_match('/^(?:bulan|minggu|tahun) (?:ini|lalu|kemarin)\??$/u',$t)
        || (bool)preg_match('/^(?:hari ini|kemarin)\??$/u',$t);
    if(!$looksFollowup) return $current;
    $chats=recentChats(30);$previous='';
    for($i=count($chats)-1;$i>=0;$i--){
        $c=$chats[$i];if(($c['role']??'')!=='user')continue;
        $candidate=trim((string)($c['message']??''));
        if($candidate==='' || norm($candidate)===norm($message))continue;
        if(smartIsQuestion(normalizeChatMessage($candidate))){$previous=normalizeChatMessage($candidate);break;}
    }
    if($previous==='') return $current;
    $base=$previous;
    // Replace the most common comparative dimension instead of forcing the user to repeat it.
    if(preg_match('/\b(?:bulan|minggu|tahun) (?:ini|lalu|kemarin)\b/u',$t,$m)){
        $base=preg_replace('/\b(?:bulan|minggu|tahun) (?:ini|lalu|kemarin)\b/u',$m[0],$base,1,$count);
        if(!$count)$base.=' '.$m[0];
    } elseif(preg_match('/\b(?:hari ini|kemarin)\b/u',$t,$m)){
        $base=preg_replace('/\b(?:hari ini|kemarin)\b/u',$m[0],$base,1,$count);
        if(!$count)$base.=' '.$m[0];
    }
    if(preg_match('/\b(?:pemasukan|pendapatan)\b/u',$t))$base=preg_replace('/\b(?:pengeluaran|pemasukan|pendapatan)\b/u','pemasukan',$base,1);
    elseif(preg_match('/\bpengeluaran\b/u',$t))$base=preg_replace('/\b(?:pengeluaran|pemasukan|pendapatan)\b/u','pengeluaran',$base,1);
    $wallet=walletMentionForText($current);
    if($wallet && !walletMentionForText($base))$base.=' '.trim((string)($wallet['name']??''));
    return normalizeChatMessage($base);
}

function spendingKindForText(string $text,string $type='expense'): string {
    if($type!=='expense') return 'once';
    $t=norm($text);
    if(preg_match('/\b(?:setiap hari|harian|tiap hari|sehari-hari|rutin harian)\b/u',$t)) return 'daily';
    if(preg_match('/\b(?:berulang|rutin|setiap minggu|mingguan|setiap bulan|bulanan|langganan)\b/u',$t)) return 'recurring';
    if(preg_match('/\b(?:sekali bayar|sekali saja|satu kali|sekali|non rutin|non-rutin|cicilan|angsuran|tagihan)\b/u',$t)) return 'once';
    $cat=strtolower(categoryFor($text,$type));
    // Saran awal untuk kartu konfirmasi: hanya kebutuhan yang umumnya benar-benar
    // berulang setiap hari yang diarahkan ke pola Harian. Sisanya aman sebagai
    // Sekali bayar sampai pengguna memilih sendiri sebelum menyimpan.
    if(in_array($cat,['makan','bensin','transportasi'],true)) return 'daily';
    return 'once';
}

function detectDate(string $text): string {
    $t=norm($text); $today=new DateTimeImmutable('today');
    if (containsText($t,'kemarin')) return $today->modify('-1 day')->format('Y-m-d');
    if (containsText($t,'lusa')) return $today->modify('+2 day')->format('Y-m-d');
    if (containsText($t,'besok')) return $today->modify('+1 day')->format('Y-m-d');
    if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/',$t,$m)) {
        $y = isset($m[3]) ? (int)$m[3] : (int)$today->format('Y'); if($y<100)$y+=2000;
        if(checkdate((int)$m[2],(int)$m[1],$y)) return sprintf('%04d-%02d-%02d',$y,$m[2],$m[1]);
    }
    return $today->format('Y-m-d');
}

function categoryFor(string $chunk, string $type): string {
    $c=norm($chunk);
    // Prioritaskan kategori custom yang dibuat user beserta keyword-nya.
    if (function_exists('financeCategories')) {
        foreach (financeCategories() as $custom) {
            $ct=(string)($custom['type']??'both');
            if($ct!==$type && $ct!=='both') continue;
            $customName=norm((string)($custom['name']??''));
            if($customName!=='' && containsText($c,$customName)) return (string)$custom['name'];
            foreach((array)($custom['keywords']??[]) as $kw) if($kw!=='' && containsText($c,norm((string)$kw))) return (string)$custom['name'];
        }
    }
    $rules = $type==='income' ? [
        'Gaji'=>['gaji','salary'],
        'Bonus'=>['bonus','thr','insentif'],
        'Transfer'=>['transfer','dikirim','kiriman','masuk','pemasukan','pendapatan','terima','dapat','dapet'],
        'Penjualan'=>['jual','penjualan'],
        'Lainnya'=>[]
    ] : [
        'Makan'=>['makan','nasi','sarapan','lunch','dinner','kopi','snack','jajan','pisgor','pisang goreng','gorengan'],
        'Bensin'=>['bensin','pertamax','pertalite','bbm','shell'],
        'Cicilan'=>['cicilan','angsuran','kredit'],
        'Belanja'=>['belanja','minimarket','supermarket','sembako'],
        'Transportasi'=>['grab','gojek','ojek','parkir','tol','angkot','transport'],
        'Tagihan'=>['listrik','pulsa','internet','wifi','air','tagihan'],
        'Kesehatan'=>['obat','dokter','klinik','rumah sakit','vitamin'],
        'Hiburan'=>['nonton','game','bioskop','hiburan'],
        'Lainnya'=>[]
    ];
    foreach($rules as $cat=>$words) foreach($words as $w) if(containsText($c,$w)) return $cat;
    return 'Lainnya';
}

function typeFor(string $chunk): string {
    $c=norm($chunk);
    foreach([
        'pemasukan','pendapatan','masuk','dapat','dapet','terima','diterima',
        'gaji','bonus','thr','insentif','kiriman','dikirim','transfer masuk','hasil jual','penjualan'
    ] as $w) {
        if(containsText($c,$w)) return 'income';
    }
    return 'expense';
}

/**
 * Memilih dompet/rekening dari kalimat chat.
 *
 * Contoh:
 * - "Pengeluaran 1jt dari SeaBank" -> dompet/rekening SeaBank
 * - "Beli pisgor 10rb cash"       -> dompet bertipe cash
 * - "Bayar 50rb tunai"            -> dompet bertipe cash
 *
 * Pencarian memprioritaskan nama dompet yang disebut user. Jika tidak ada
 * nama yang cocok, kata cash/tunai diarahkan ke dompet cash aktif. Jika
 * tidak ada petunjuk dompet sama sekali, transaksi tetap memakai dompet
 * default agar perilaku lama tetap kompatibel.
 */
function walletMentionForText(string $text): ?array {
    $t = normalizeChatMessage($text);
    if (!function_exists('financeWallets')) return null;

    $wallets = financeWallets();
    if (!$wallets) return null;

    // 1) Cocokkan nama dompet/rekening secara langsung.
    foreach ($wallets as $w) {
        $rawName = trim((string)($w['name'] ?? ''));
        $name = norm($rawName);
        if ($name === '' || $name === 'utama') continue;

        $parts = preg_split('/[^a-z0-9]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts) {
            $pattern = implode('[\s._-]*', array_map(function($part){
                return preg_quote($part, '/');
            }, $parts));
            if ($pattern !== '' && preg_match('/(^|[^a-z0-9])'.$pattern.'([^a-z0-9]|$)/iu', $t)) {
                return $w;
            }
        }

        if (containsText($t, $name)) return $w;
    }

    // 2) Alias bank/e-wallet umum. Hanya dianggap cocok jika user memang
    // memiliki dompet/rekening dengan nama yang terkait.
    $bankAliases = [
        'seabank'   => ['seabank', 'sea bank', 'bank seabank', 'bank sea bank'],
        'bca'       => ['bca', 'bank bca'],
        'bri'       => ['bri', 'bank bri'],
        'bni'       => ['bni', 'bank bni'],
        'mandiri'   => ['mandiri', 'bank mandiri'],
        'bsi'       => ['bsi', 'bank bsi'],
        'jago'      => ['jago', 'bank jago'],
        'dana'      => ['dana'],
        'ovo'       => ['ovo'],
        'gopay'     => ['gopay', 'go pay'],
        'shopeepay' => ['shopeepay', 'shopee pay'],
    ];
    foreach ($wallets as $w) {
        $walletName = preg_replace('/[^a-z0-9]/u', '', norm((string)($w['name'] ?? '')));
        if ($walletName === '') continue;
        foreach ($bankAliases as $key => $aliases) {
            if (strpos($walletName, $key) === false) continue;
            foreach ($aliases as $alias) {
                $alias = normalizeChatMessage($alias);
                $aliasPattern = preg_quote($alias, '/');
                if (preg_match('/(^|[^a-z0-9])'.$aliasPattern.'([^a-z0-9]|$)/iu', $t)) {
                    return $w;
                }
            }
        }
    }

    // 3) Fuzzy ringan untuk typo nama dompet satu kata/compact, misalnya
    // "sebank" atau "seabnk". Ambang sengaja ketat agar tidak salah pilih.
    $tokens = preg_split('/[^a-z0-9]+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($wallets as $w) {
        $name = preg_replace('/[^a-z0-9]/u', '', norm((string)($w['name'] ?? '')));
        if ($name === '' || $name === 'utama' || strlen($name) < 4) continue;
        foreach ($tokens as $token) {
            if (!preg_match('/^[a-z]+$/', $token)) continue;
            if (abs(strlen($token)-strlen($name)) > 2) continue;
            $distance = levenshtein($token, $name);
            $limit = strlen($name) >= 8 ? 2 : 1;
            if ($distance <= $limit) return $w;
        }
    }

    // 4) Kata cash/tunai diarahkan ke dompet cash.
    if (preg_match('/(^|[^a-z0-9])(cash|tunai|uang tunai)([^a-z0-9]|$)/iu', $t)) {
        foreach ($wallets as $w) {
            $name = norm((string)($w['name'] ?? ''));
            if (preg_match('/(^|[^a-z0-9])(cash|tunai)([^a-z0-9]|$)/iu', $name)) return $w;
        }
        foreach ($wallets as $w) {
            if (strtolower((string)($w['type'] ?? '')) === 'cash') return $w;
        }
    }

    // 5) "utama" hanya dianggap mention jika ditulis eksplisit.
    if (preg_match('/(^|[^a-z0-9])(utama|dompet utama)([^a-z0-9]|$)/iu', $t)) {
        $defaultId = financeDefaultWalletId();
        foreach ($wallets as $w) if ((int)($w['id'] ?? 0) === (int)$defaultId) return $w;
    }

    return null;
}

function walletForText(string $text): int {
    $wallet = walletMentionForText($text);
    return $wallet ? (int)$wallet['id'] : financeDefaultWalletId();
}

function walletLabelForTransaction(array $tx): string {
    if (($tx['type'] ?? '') === 'transfer') return '';
    $wid = (int)($tx['wallet_id'] ?? 0);
    if ($wid <= 0 || !function_exists('financeWalletById')) return '';
    $wallet = financeWalletById($wid);
    if (!$wallet) return '';
    $name = trim((string)($wallet['name'] ?? ''));
    if ($name === '') return '';
    if (strtolower((string)($wallet['type'] ?? '')) === 'cash' && norm($name) === 'utama') {
        return 'Utama (Cash)';
    }
    return $name;
}

function isPlan(string $text): bool {
    $t=norm($text);
    foreach(['besok','nanti','rencana','akan bayar','mau bayar','mo bayar','budget','anggaran','harus bayar','perlu bayar'] as $w) {
        if(containsText($t,$w)) return true;
    }
    return false;
}

/** Regex nominal dibuat greedy terhadap seluruh angka bertitik/koma agar
 * 1.100.000 tidak berhenti di 1.100 dan 1,1jt tidak pecah menjadi dua angka.
 */
function amountRegex(): string {
    return '/(?:rp\s*)?(?:\d{1,3}(?:[\.,]\d{3})+|\d+(?:[\.,]\d+)?)(?:\s*(?:juta|jt|ribu|rb|k))?/iu';
}

/**
 * Kalkulator native untuk chat.
 * Tidak menggunakan eval(), jadi hanya operator matematika yang memang
 * didukung yang dapat dijalankan.
 *
 * Contoh:
 * 100 + 50
 * 1jt - 250rb
 * 50rb kali 3
 * 1.200.000 dibagi 4
 * (100rb + 50rb) * 2
 * 10% dari 500rb
 * 2 pangkat 8
 */
function calcParseNumber(string $raw): float {
    $s = strtolower(trim($raw));
    $hasCurrency = preg_match('/\brp\b|^rp/iu', $s) === 1;

    $unit = '';
    if (preg_match('/(juta|jt|ribu|rb|k)\s*$/iu', $s, $m)) {
        $unit = strtolower($m[1]);
        $s = substr($s, 0, -strlen($m[0]));
    }

    $s = preg_replace('/^rp\s*/iu', '', trim($s));
    $s = preg_replace('/\s+/u', '', $s);
    $s = preg_replace('/[^0-9\.,]/u', '', $s);
    if ($s === '') return 0.0;

    if (in_array($unit, ['jt','juta'], true)) {
        if (preg_match('/^\d+[\.,]\d+$/', $s)) {
            return (float)str_replace(',', '.', $s) * 1000000;
        }
        return (float)str_replace(['.', ','], '', $s) * 1000000;
    }

    if (in_array($unit, ['rb','ribu','k'], true)) {
        if (preg_match('/^\d+[\.,]\d+$/', $s)) {
            return (float)str_replace(',', '.', $s) * 1000;
        }
        return (float)str_replace(['.', ','], '', $s) * 1000;
    }

    // Format dengan titik dan koma sekaligus, mis. 1.500,50 atau 1,500.50.
    if (strpos($s, '.') !== false && strpos($s, ',') !== false) {
        $lastDot = strrpos($s, '.');
        $lastComma = strrpos($s, ',');
        $decimalPos = max($lastDot, $lastComma);
        $decimalDigits = strlen($s) - $decimalPos - 1;

        if ($decimalDigits > 0 && $decimalDigits <= 2) {
            $integerPart = preg_replace('/[\.,]/', '', substr($s, 0, $decimalPos));
            $fractionPart = substr($s, $decimalPos + 1);
            return (float)($integerPart . '.' . $fractionPart);
        }

        return (float)str_replace(['.', ','], '', $s);
    }

    // Kelompok ribuan: 1.500 / 1.500.000 / 1,500 / 1,500,000.
    if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $s)) {
        return (float)str_replace('.', '', $s);
    }
    if (preg_match('/^\d{1,3}(?:,\d{3})+$/', $s)) {
        return (float)str_replace(',', '', $s);
    }

    // Separator tunggal bukan tiga digit dianggap desimal: 1,5 / 1.5.
    if (preg_match('/^\d+[\.,]\d+$/', $s)) {
        return (float)str_replace(',', '.', $s);
    }

    return (float)preg_replace('/\D/u', '', $s);
}

function calcFormatNumber(float $value): string {
    if (!is_finite($value)) return 'tidak terdefinisi';

    if (abs($value - round($value)) < 0.000000001) {
        return number_format((float)round($value), 0, ',', '.');
    }

    $formatted = number_format($value, 8, ',', '.');
    $formatted = rtrim($formatted, '0');
    $formatted = rtrim($formatted, ',');
    return $formatted;
}

function calcLooksMonetary(string $message): bool {
    return preg_match('/(?:\brp\s*\d|\d\s*(?:jt|juta|rb|ribu|k)\b)/iu', $message) === 1;
}

function isCalculationMessage(string $message): bool {
    $t = norm($message);

    // Persentase eksplisit.
    if (preg_match('/\d+(?:[\.,]\d+)?\s*%\s*(?:dari|x|\*)/iu', $t)) return true;
    if (preg_match('/\d+(?:[\.,]\d+)?\s*(?:persen|percent)\s+dari/iu', $t)) return true;

    // Kata-kata operasi.
    if (preg_match('/\b(?:hitung|hitungkan|berapa\s+hasil|hasil\s+dari|ditambah|tambah|plus|dikurangi|kurang|minus|dikali|kali|dibagi|bagi|pangkat)\b/iu', $t)
        && preg_match('/\d/u', $t)) {
        return true;
    }

    // Ekspresi simbolik murni / hampir murni: 100 + 50, (1jt-250rb)/3.
    $clean = preg_replace('/^(?:berapa|hitung|hitungkan|hasil(?:nya)?|berapa\s+hasil(?:nya)?|sama\s+dengan|=)\s*/iu', '', $t);
    $number = '(?:rp\s*)?(?:\d{1,3}(?:[\.,]\d{3})+|\d+(?:[\.,]\d+)?)(?:\s*(?:juta|jt|ribu|rb|k))?';
    if (preg_match('/'.$number.'\s*(?:\+|\-|\*|\/|:|\^|×|÷|x)\s*'.$number.'/iu', $clean)) {
        return true;
    }

    return false;
}

function calcTokenize(string $message): array {
    $expr = norm($message);

    // Kasus yang sangat umum: "10% dari 500rb".
    $amountPattern = '(?:rp\s*)?(?:\d{1,3}(?:[\.,]\d{3})+|\d+(?:[\.,]\d+)?)(?:\s*(?:juta|jt|ribu|rb|k))?';
    if (preg_match('/('.$amountPattern.')\s*(?:%|persen|percent)\s+dari\s+('.$amountPattern.')/iu', $expr, $m)) {
        $pct = calcParseNumber($m[1]) / 100;
        $base = calcParseNumber($m[2]);
        return [
            ['type'=>'number','value'=>$pct],
            ['type'=>'operator','value'=>'*'],
            ['type'=>'number','value'=>$base],
        ];
    }

    // Hilangkan kata pembuka yang tidak memengaruhi ekspresi.
    $expr = preg_replace('/\b(?:berapa|hitung|hitungkan|tolong|dong|hasilnya|hasil|dari|sama\s+dengan)\b/iu', ' ', $expr);

    // Ubah operator bahasa Indonesia menjadi simbol.
    $replacements = [
        '/\b(?:ditambah|tambah|plus)\b/iu' => ' + ',
        '/\b(?:dikurangi|kurang|minus)\b/iu' => ' - ',
        '/\b(?:dikali|kali)\b/iu' => ' * ',
        '/\b(?:dibagi|bagi)\b/iu' => ' / ',
        '/\bpangkat\b/iu' => ' ^ ',
        '/×/u' => ' * ',
        '/÷/u' => ' / ',
        '/:/u' => ' / ',
    ];
    foreach ($replacements as $pattern=>$replacement) {
        $expr = preg_replace($pattern, $replacement, $expr);
    }

    // x sebagai operator hanya jika berada di antara angka/kurung.
    $expr = preg_replace('/(?<=[0-9\)])\s*[xX]\s*(?=[0-9\(])/u', ' * ', $expr);

    $tokens = [];
    $len = strlen($expr);
    $i = 0;
    $expectValue = true;

    while ($i < $len) {
        $ch = $expr[$i];

        if (ctype_space($ch)) {
            $i++;
            continue;
        }

        if ($ch === '(') {
            $tokens[] = ['type'=>'left','value'=>'('];
            $i++;
            $expectValue = true;
            continue;
        }
        if ($ch === ')') {
            $tokens[] = ['type'=>'right','value'=>')'];
            $i++;
            $expectValue = false;
            continue;
        }

        if (strpos('+-*/^', $ch) !== false) {
            // Unary minus: -50 atau -(...) -> 0 - ...
            if ($ch === '-' && $expectValue) {
                $tokens[] = ['type'=>'number','value'=>0.0];
            }
            $tokens[] = ['type'=>'operator','value'=>$ch];
            $i++;
            $expectValue = true;
            continue;
        }

        // Number token termasuk Rp, rb/ribu/k, jt/juta.
        $rest = substr($expr, $i);
        if (preg_match('/^(?:rp\s*)?(?:\d{1,3}(?:[\.,]\d{3})+|\d+(?:[\.,]\d+)?)(?:\s*(?:juta|jt|ribu|rb|k))?/iu', $rest, $m)) {
            $raw = $m[0];
            $value = calcParseNumber($raw);
            $i += strlen($raw);

            // Postfix persen: "10%" menjadi 0.1.
            $tail = substr($expr, $i);
            if (preg_match('/^\s*(?:%|persen\b|percent\b)/iu', $tail, $pm)) {
                $value /= 100;
                $i += strlen($pm[0]);
            }

            $tokens[] = ['type'=>'number','value'=>$value];
            $expectValue = false;
            continue;
        }

        // Karakter/kata lain berarti ekspresi tidak aman/tidak dikenali.
        if (preg_match('/^\p{L}+/u', $rest, $wm)) {
            throw new RuntimeException('Bagian "'.$wm[0].'" belum dikenali sebagai operasi hitung.');
        }

        throw new RuntimeException('Ekspresi hitung mengandung karakter yang tidak didukung.');
    }

    return $tokens;
}

function calcEvaluateTokens(array $tokens): float {
    if (!$tokens) throw new RuntimeException('Ekspresi hitung kosong.');

    $precedence = ['+'=>1, '-'=>1, '*'=>2, '/'=>2, '^'=>3];
    $rightAssociative = ['^'=>true];

    $output = [];
    $ops = [];

    foreach ($tokens as $token) {
        if ($token['type'] === 'number') {
            $output[] = $token;
            continue;
        }

        if ($token['type'] === 'operator') {
            $op = $token['value'];
            while ($ops) {
                $top = end($ops);
                if (($top['type'] ?? '') !== 'operator') break;
                $topOp = $top['value'];
                $shouldPop = !empty($rightAssociative[$op])
                    ? ($precedence[$op] < $precedence[$topOp])
                    : ($precedence[$op] <= $precedence[$topOp]);
                if (!$shouldPop) break;
                $output[] = array_pop($ops);
            }
            $ops[] = $token;
            continue;
        }

        if ($token['type'] === 'left') {
            $ops[] = $token;
            continue;
        }

        if ($token['type'] === 'right') {
            $foundLeft = false;
            while ($ops) {
                $top = array_pop($ops);
                if (($top['type'] ?? '') === 'left') {
                    $foundLeft = true;
                    break;
                }
                $output[] = $top;
            }
            if (!$foundLeft) throw new RuntimeException('Tanda kurung tidak seimbang.');
        }
    }

    while ($ops) {
        $top = array_pop($ops);
        if (($top['type'] ?? '') === 'left') {
            throw new RuntimeException('Tanda kurung tidak seimbang.');
        }
        $output[] = $top;
    }

    $stack = [];
    foreach ($output as $token) {
        if ($token['type'] === 'number') {
            $stack[] = (float)$token['value'];
            continue;
        }

        if (count($stack) < 2) {
            throw new RuntimeException('Ekspresi hitung belum lengkap.');
        }

        $b = array_pop($stack);
        $a = array_pop($stack);
        switch ($token['value']) {
            case '+': $result = $a + $b; break;
            case '-': $result = $a - $b; break;
            case '*': $result = $a * $b; break;
            case '/':
                if (abs($b) < 0.000000000001) {
                    throw new RuntimeException('Tidak bisa membagi dengan 0.');
                }
                $result = $a / $b;
                break;
            case '^':
                $result = pow($a, $b);
                break;
            default:
                throw new RuntimeException('Operator tidak didukung.');
        }

        if (!is_finite($result)) {
            throw new RuntimeException('Hasil perhitungan terlalu besar atau tidak terdefinisi.');
        }
        $stack[] = $result;
    }

    if (count($stack) !== 1) {
        throw new RuntimeException('Ekspresi hitung belum lengkap.');
    }

    return (float)$stack[0];
}

function calculatorReply(string $message): ?string {
    if (!isCalculationMessage($message)) return null;

    try {
        $tokens = calcTokenize($message);
        $result = calcEvaluateTokens($tokens);
        $formatted = calcFormatNumber($result);

        if (calcLooksMonetary($message)) {
            return 'Hasilnya Rp'.$formatted.'.';
        }
        return 'Hasilnya '.$formatted.'.';
    } catch (Throwable $e) {
        return 'Saya belum bisa menghitung ekspresi itu. '.$e->getMessage().' Contoh: “1jt - 250rb”, “50rb kali 3”, “1.200.000 dibagi 4”, atau “10% dari 500rb”.';
    }
}

function extractTransactions(string $message): array {
    $message = normalizeChatMessage($message);
    // Pertanyaan dengan angka/tanggal tidak boleh mencatat transaksi.
    if (smartIsQuestion($message)) return [];
    // Pesan kalkulator tidak boleh dianggap sebagai transaksi keuangan.
    if (isCalculationMessage($message)) return [];
    if (isPlan($message)) return [];

    $date = detectDate($message);
    $out = [];

    // Pisah hanya pada kata penghubung transaksi, bukan pada koma/titik.
    // Ini penting agar 1,1jt dan 1.100.000 tidak terpotong.
    $chunks = preg_split('/\s*(?:;|\bdan\b|\bserta\b|\blalu\b|\bterus\b|\bkemudian\b)\s*/ui', $message, -1, PREG_SPLIT_NO_EMPTY);

    foreach($chunks as $chunk) {
        if (!preg_match_all(amountRegex(), $chunk, $matches, PREG_OFFSET_CAPTURE)) continue;

        // Biasanya satu chunk satu transaksi. Jika ada beberapa nominal tanpa
        // penghubung yang jelas, catat nominal pertama saja untuk mencegah duplikasi salah.
        $rawAmount = $matches[0][0][0] ?? '';
        $amount = parseAmount($rawAmount);
        if ($amount <= 0) continue;

        $type = typeFor($chunk);
        $cat = categoryFor($chunk, $type);
        $note = trim(preg_replace('/\s+/u',' ',$chunk));

        $out[] = [
            'type'=>$type,
            'category'=>$cat,
            'amount'=>$amount,
            'note'=>substr($note,0,255),
            'transaction_date'=>$date,
            'wallet_id'=>walletForText($chunk),
            'spending_kind'=>spendingKindForText($chunk,$type)
        ];
    }

    return $out;
}

function dateRangeFor(string $message): array {
    [$smartFrom,$smartTo,$smartPeriod,$smartError] = smartDateRange($message);
    if ($smartError === null) return [$smartFrom,$smartTo,$smartPeriod];
    $t=norm($message); $now=new DateTimeImmutable('today');
    if(containsText($t,'hari ini')) return [$now->format('Y-m-d'),$now->format('Y-m-d'),'hari ini'];
    if(containsText($t,'bulan ini')) return [$now->modify('first day of this month')->format('Y-m-d'),$now->modify('last day of this month')->format('Y-m-d'),'bulan ini'];
    if(containsText($t,'minggu ini')) {
        $start=$now->modify('monday this week'); $end=$start->modify('+6 days'); return [$start->format('Y-m-d'),$end->format('Y-m-d'),'minggu ini'];
    }
    return [null,null,'seluruh waktu'];
}

function queryTotal(string $type, ?string $category, ?string $from, ?string $to, ?int $walletId=null): int {
    $total=0;
    foreach(allTransactions() as $t){
        if(($t['type']??'')!==$type) continue;
        if($category && strtolower((string)$t['category'])!==strtolower($category)) continue;
        if($walletId !== null && (int)($t['wallet_id'] ?? 0) !== $walletId) continue;
        $date=(string)($t['transaction_date']??'');
        if($from && $date<$from) continue;
        if($to && $date>$to) continue;
        $total+=(int)($t['amount']??0);
    }
    return $total;
}

function detectQueryCategory(string $message, string $type='expense'): ?string {
    foreach(['Makan','Bensin','Cicilan','Belanja','Transportasi','Tagihan','Kesehatan','Hiburan'] as $cat){
        if(categoryFor($message,$type)===$cat) return $cat;
    }
    return null;
}


function billQueryIntent(string $message): ?string {
    $t = norm($message);

    // Hanya anggap sebagai pertanyaan jadwal cicilan/tagihan jika konteksnya jelas.
    if (!preg_match('/\b(cicilan|angsuran|tagihan)\b/u', $t)) return null;

    // Jika pengguna secara eksplisit menanyakan histori pengeluaran, biarkan
    // mekanisme query transaksi biasa yang menjawab.
    if (containsText($t, 'pengeluaran') && !containsText($t, 'jatuh tempo')) return null;

    if (
        containsText($t, 'bulan depan') ||
        containsText($t, 'next month')
    ) return 'next_month';

    if (
        containsText($t, 'sudah dekat') ||
        containsText($t, 'yang dekat') ||
        containsText($t, 'terdekat') ||
        containsText($t, 'jatuh tempo dekat') ||
        containsText($t, 'segera jatuh tempo') ||
        containsText($t, 'akan jatuh tempo')
    ) return 'near';

    if (
        containsText($t, 'saat ini') ||
        containsText($t, 'sekarang') ||
        containsText($t, 'bulan ini') ||
        containsText($t, 'belum dibayar') ||
        containsText($t, 'belum lunas') ||
        containsText($t, 'yang aktif') ||
        containsText($t, 'aktif saat ini')
    ) return 'current';

    return null;
}

function billMonthNameId(int $month): string {
    $names = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    return $names[$month] ?? '';
}

function billDateId(string $date): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;
    $dt = new DateTimeImmutable($date);
    return $dt->format('d/m/Y');
}

function billDueStatusText(array $bill): string {
    if (!empty($bill['paid'])) return 'Lunas';

    $dueDate = (string)($bill['due_date'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $today = new DateTimeImmutable('today');
        $due = new DateTimeImmutable($dueDate);
        $days = (int)$today->diff($due)->format('%r%a');
    } else {
        $days = (int)($bill['days_left'] ?? 0);
    }

    if ($days < 0) return 'Terlambat '.abs($days).' hari';
    if ($days === 0) return 'Jatuh tempo hari ini';
    if ($days === 1) return 'Jatuh tempo besok';
    return $days.' hari lagi';
}

function billRowsForChat(string $intent): array {
    $today = new DateTimeImmutable('today');
    $rows = [];
    $title = '';

    if ($intent === 'next_month') {
        $start = $today->modify('first day of next month');
        $end = $start->modify('last day of this month');
        foreach (financeBillsStatus($start->format('Y-m-d')) as $bill) {
            $due = (string)($bill['due_date'] ?? '');
            if (!empty($bill['paid'])) continue;
            if ($due < $start->format('Y-m-d') || $due > $end->format('Y-m-d')) continue;
            $rows[] = $bill;
        }
        $title = 'Cicilan bulan depan — '.billMonthNameId((int)$start->format('n')).' '.$start->format('Y');
    } elseif ($intent === 'near') {
        // "Sudah dekat" = kewajiban yang terlambat atau jatuh tempo maksimal 7 hari lagi.
        foreach (financeBillsStatus($today->format('Y-m-d')) as $bill) {
            if (!empty($bill['paid'])) continue;
            $days = (int)($bill['days_left'] ?? 99999);
            if ($days <= 7) $rows[] = $bill;
        }
        $title = 'Cicilan yang sudah dekat';
    } else {
        // Saat ini: semua kewajiban belum lunas yang sudah jatuh tempo atau
        // jatuh tempo sampai akhir bulan berjalan. Tagihan masa depan tidak dicampur.
        $end = $today->modify('last day of this month');
        foreach (financeBillsStatus($today->format('Y-m-d')) as $bill) {
            if (!empty($bill['paid'])) continue;
            $due = (string)($bill['due_date'] ?? '');
            if ($due !== '' && $due <= $end->format('Y-m-d')) $rows[] = $bill;
        }
        $title = 'Cicilan saat ini — '.billMonthNameId((int)$today->format('n')).' '.$today->format('Y');
    }

    usort($rows, function($a, $b) {
        return strcmp((string)($a['due_date'] ?? ''), (string)($b['due_date'] ?? ''));
    });

    return ['title'=>$title, 'rows'=>$rows];
}

function billChatReply(string $message): ?string {
    $intent = billQueryIntent($message);
    if ($intent === null) return null;

    // Tagihan & Cicilan adalah fitur Premium. Super Admin tetap memiliki akses.
    if (function_exists('authHasPremiumAccess') && !authHasPremiumAccess()) {
        return '🔒 Rincian Tagihan & Cicilan tersedia untuk akun Premium. Upgrade Premium untuk melihat cicilan saat ini, yang sudah dekat, dan cicilan bulan depan langsung dari chat.';
    }

    $result = billRowsForChat($intent);
    $rows = $result['rows'];
    $title = $result['title'];

    if (!$rows) {
        if ($intent === 'next_month') {
            $next = (new DateTimeImmutable('today'))->modify('first day of next month');
            return '🧾 '.$title."\nTidak ada cicilan/tagihan yang belum lunas pada ".billMonthNameId((int)$next->format('n')).' '.$next->format('Y').".\n\nTotal: Rp0.";
        }
        if ($intent === 'near') {
            return '🧾 '.$title."\nTidak ada cicilan/tagihan yang terlambat atau jatuh tempo dalam 7 hari ke depan.\n\nTotal: Rp0.";
        }
        return '🧾 '.$title."\nTidak ada cicilan/tagihan belum lunas yang jatuh tempo sampai akhir bulan ini.\n\nTotal: Rp0.";
    }

    $lines = ['🧾 '.$title];
    $total = 0;
    $no = 1;
    foreach ($rows as $bill) {
        $amount = (int)($bill['amount'] ?? 0);
        $total += $amount;
        $name = trim((string)($bill['name'] ?? 'Tagihan')) ?: 'Tagihan';
        $due = billDateId((string)($bill['due_date'] ?? ''));
        $status = billDueStatusText($bill);
        $lines[] = $no.'. '.$name.' — '.rupiah($amount);
        $lines[] = '   Jatuh tempo: '.$due.' · '.$status;
        $no++;
    }
    $lines[] = '';
    $lines[] = 'Total: '.rupiah($total).'.';

    return implode("\n", $lines);
}


function dailyBudgetReplyText(string $date): string {
    $b = dailyBudgetStatus($date);
    if (empty($b['active'])) return '';

    if ($b['status'] === 'warning') {
        return ' ⚠️ Pengeluaran '.$b['day_name'].' sudah '.rupiah($b['spent']).' dari batas '.rupiah($b['limit']).' ('.$b['percent'].'%). Tersisa '.rupiah($b['remaining']).'.';
    }
    if ($b['status'] === 'reached') {
        return ' 🚨 Batas pengeluaran '.$b['day_name'].' sudah tercapai: '.rupiah($b['spent']).' dari '.rupiah($b['limit']).'.';
    }
    if ($b['status'] === 'exceeded') {
        return ' 🚨 Batas pengeluaran '.$b['day_name'].' terlewati '.rupiah($b['over']).'. Total '.rupiah($b['spent']).' dari batas '.rupiah($b['limit']).'.';
    }
    return '';
}

function nativeReply(string $message, array $saved=[]): string {
    $message = normalizeChatMessage($message);
    $t=norm($message); $sum=summary();

    // Kalkulator chat berjalan native dan tidak mencatat hasil sebagai transaksi.
    if (!$saved) {
        $smartReply = smartFinanceReply($message);
        if ($smartReply !== null) return $smartReply;
        $calculator = calculatorReply($message);
        if ($calculator !== null) return $calculator;
    }

    if($saved){
        $parts=[];
        $walletIds=[];
        foreach($saved as $x) {
            $walletLabel = walletLabelForTransaction($x);
            $direction = ($x['type'] ?? '') === 'income' ? ' ke ' : ' dari ';
            $parts[] = ($x['type']==='expense'?'Pengeluaran':'Pemasukan').' '.rupiah($x['amount']).' ('.$x['category'].')'.($walletLabel!=='' ? $direction.$walletLabel : '');
            if (($x['type'] ?? '') !== 'transfer' && !empty($x['wallet_id'])) $walletIds[(int)$x['wallet_id']] = true;
        }
        $reply = 'Tercatat: '.implode(', ',$parts).'. Saldo total sekarang '.rupiah($sum['balance']).'.';

        // Jika transaksi hanya mengenai satu dompet, sebutkan saldo dompet
        // tersebut setelah transaksi agar user langsung tahu sumber saldo
        // yang dipotong/ditambah sudah benar.
        if (count($walletIds) === 1 && function_exists('financeWalletBalances')) {
            $wid = (int)array_key_first($walletIds);
            $wallet = function_exists('financeWalletById') ? financeWalletById($wid) : null;
            $balances = financeWalletBalances();
            if ($wallet && array_key_exists($wid, $balances)) {
                $walletName = walletLabelForTransaction(['type'=>'expense','wallet_id'=>$wid]);
                if ($walletName !== '') $reply .= ' Saldo '.$walletName.' sekarang '.rupiah($balances[$wid]).'.';
            }
        }
        $expenseDates = [];
        foreach ($saved as $x) {
            if (($x['type'] ?? '') === 'expense') $expenseDates[(string)($x['transaction_date'] ?? date('Y-m-d'))] = true;
        }
        foreach (array_keys($expenseDates) as $date) $reply .= dailyBudgetReplyText($date);
        return $reply;
    }

    if(!smartIsQuestion($message) && isPlan($message) && preg_match('/[0-9]/',$message)) {
        return 'Itu terdengar seperti rencana/anggaran, jadi tidak saya catat sebagai transaksi aktual.';
    }

    // Pertanyaan jadwal cicilan/tagihan dijawab dari data Tagihan & Cicilan,
    // bukan dari histori transaksi yang sudah terjadi.
    $billReply = billChatReply($message);
    if ($billReply !== null) return $billReply;

    [$from,$to,$period]=dateRangeFor($message);

    if(containsText($t,'batas') || containsText($t,'limit') || containsText($t,'budget harian') || containsText($t,'anggaran harian')) {
        $b = dailyBudgetStatus($from && $from === $to ? $from : date('Y-m-d'));
        if (empty($b['active'])) {
            return 'Belum ada batas pengeluaran aktif untuk '.$b['day_name'].'. Atur batas harian dari menu Batas.';
        }
        if ($b['status'] === 'exceeded') {
            return 'Batas '.$b['day_name'].' '.rupiah($b['limit']).'. Pengeluaran sudah '.rupiah($b['spent']).', melewati batas sebesar '.rupiah($b['over']).'.';
        }
        return 'Batas '.$b['day_name'].' '.rupiah($b['limit']).'. Pengeluaran saat ini '.rupiah($b['spent']).' ('.$b['percent'].'%). Sisa batas '.rupiah($b['remaining']).'. Status: '.$b['label'].'.';
    }

    if(containsText($t,'saldo')) {
        $mentionedWallet = walletMentionForText($message);
        if ($mentionedWallet && function_exists('financeWalletBalances')) {
            $wid = (int)($mentionedWallet['id'] ?? 0);
            $balances = financeWalletBalances();
            if ($wid > 0 && array_key_exists($wid, $balances)) {
                $name = trim((string)($mentionedWallet['name'] ?? 'Dompet')) ?: 'Dompet';
                return 'Saldo '.$name.' saat ini '.rupiah((int)$balances[$wid]).'.';
            }
        }

        $walletText = '';
        if (function_exists('financeWalletsWithBalances')) {
            $wallets = financeWalletsWithBalances();
            $parts = [];
            foreach ($wallets as $wallet) {
                $name = trim((string)($wallet['name'] ?? 'Dompet'));
                $parts[] = $name.' '.rupiah((int)($wallet['balance'] ?? 0));
            }
            if ($parts) $walletText = ' Rincian saldo: '.implode(' • ', $parts).'.';
        }
        return 'Saldo total saat ini '.rupiah($sum['balance']).'.'.$walletText.' Total pemasukan '.rupiah($sum['income']).' dan pengeluaran '.rupiah($sum['expense']).'.';
    }

    if(containsText($t,'pengeluaran') || containsText($t,'habis') || containsText($t,'keluar')){
        $cat=detectQueryCategory($message,'expense');
        $mentionedWallet = walletMentionForText($message);
        $walletId = $mentionedWallet ? (int)($mentionedWallet['id'] ?? 0) : null;
        $val=queryTotal('expense',$cat,$from,$to,$walletId ?: null);
        $walletText = $mentionedWallet ? ' di '.(trim((string)($mentionedWallet['name'] ?? 'Dompet')) ?: 'Dompet') : '';
        return 'Total pengeluaran'.($cat?' '.$cat:'').$walletText.' '.$period.' adalah '.rupiah($val).'.';
    }

    if(containsText($t,'pemasukan') || containsText($t,'pendapatan')){
        $cat=detectQueryCategory($message,'income');
        $mentionedWallet = walletMentionForText($message);
        $walletId = $mentionedWallet ? (int)($mentionedWallet['id'] ?? 0) : null;
        $val=queryTotal('income',$cat,$from,$to,$walletId ?: null);
        $walletText = $mentionedWallet ? ' di '.(trim((string)($mentionedWallet['name'] ?? 'Dompet')) ?: 'Dompet') : '';
        return 'Total pemasukan'.($cat?' '.$cat:'').$walletText.' '.$period.' adalah '.rupiah($val).'.';
    }

    if(containsText($t,'bantu') || containsText($t,'contoh') || containsText($t,'cara')) {
        return 'Coba tulis: “makan 20rb”, “isi Pertamax 50rb”, “dapat gaji 3,8jt”, “berapa saldo?”, “cicilan saat ini”, “cicilan yang sudah dekat”, “cicilan bulan depan”, atau hitung langsung seperti “1jt - 250rb”.';
    }

    if(preg_match('/^(hai|halo|hello|pagi|siang|malam)\b/u',$t)) {
        return 'Halo! Saya siap mencatat keuangan secara lokal. Tidak ada data yang dikirim ke layanan AI atau internet.';
    }

    if (smartIsQuestion($message)) return 'Saya belum memahami pertanyaan itu. Coba “berapa uang saya?”, “pengeluaran terbesar bulan ini”, “kategori paling boros bulan lalu”, atau “ringkasan keuangan bulan ini”.';

    // Pengetahuan yang pernah diajarkan pengguna berlaku lintas akun.
    $learned = learningFindRule($message);
    if ($learned) return learningRenderResponse($learned['response']);

    // Bila belum dikenal, masuk ke mode belajar. Pesan berikutnya dapat menjadi balasan yang diajarkan.
    return learningStartForUnknown($message);
}
