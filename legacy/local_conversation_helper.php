<?php
/**
 * V72 - Local Conversational Learning Engine
 *
 * Percakapan natural tanpa API/model AI eksternal. Engine ini bukan neural LLM.
 * Ia menggabungkan:
 * - konteks percakapan terakhir;
 * - retrieval dari pasangan chat non-keuangan milik user sendiri;
 * - similarity token/bigram ringan;
 * - deteksi nada/cerita dan template respons yang bervariasi;
 * - adaptasi gaya sapaan dari histori chat.
 *
 * Data belajar berasal dari tabel chat yang memang sudah dimiliki akun pengguna.
 * Tidak ada percakapan yang dikirim ke layanan pihak ketiga dan tidak ada SQL baru.
 */


function localConversationLower(string $text): string {
    return function_exists('mb_strtolower') ? mb_strtolower($text,'UTF-8') : strtolower($text);
}

function localConversationLength(string $text): int {
    return function_exists('mb_strlen') ? mb_strlen($text,'UTF-8') : strlen($text);
}

function localConversationPos(string $haystack,string $needle) {
    return function_exists('mb_strpos') ? mb_strpos($haystack,$needle,0,'UTF-8') : strpos($haystack,$needle);
}

function localConversationNormalize(string $text): string {
    if (function_exists('humanoidNormalizeMessage')) {
        $text = humanoidNormalizeMessage($text);
    } elseif (function_exists('normalizeChatMessage')) {
        $text = normalizeChatMessage($text);
    }
    $text = localConversationLower(trim($text));
    $text = str_replace(['–','—','…'], ['-','-','...'], $text);
    $text = preg_replace('/[^\p{L}\p{N}\s\'’-]+/u', ' ', $text) ?? $text;
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function localConversationStopwords(): array {
    static $map = null;
    if ($map !== null) return $map;
    $words = [
        'aku','saya','kita','kami','kamu','anda','dia','mereka','ini','itu','yang','dan','atau','tapi','tetapi','karena','jadi','juga','saja','aja',
        'di','ke','dari','untuk','dengan','pada','dalam','sebagai','kalau','kalo','jika','bila','terus','lalu','tadi','nih','deh','dong','lah','kok','sih',
        'ada','adalah','punya','bisa','mau','ingin','sudah','belum','masih','lagi','lebih','paling','banget','sekali','agak','cukup','seperti','kayak',
        'apa','apakah','siapa','kenapa','mengapa','gimana','bagaimana','mana','kapan','berapa','ya','iya','iyo','tidak','nggak','gak','ga','ndak','nyanda',
        'hari','hariini','hari ini','kemarin','besok','sekarang','barusan','baru','waktu','saat','tentang','soal','hal','begitu','begini','memang','cuma','hanya'
    ];
    $map = array_fill_keys($words, true);
    return $map;
}

function localConversationTokens(string $text): array {
    $n = localConversationNormalize($text);
    if ($n === '') return [];
    $parts = preg_split('/\s+/u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = localConversationStopwords();
    $tokens = [];
    foreach ($parts as $word) {
        $word = trim($word);
        if ($word === '' || isset($stop[$word]) || localConversationLength($word) < 2) continue;
        // Angka murni tidak dipakai sebagai sinyal percakapan agar nominal/tanggal
        // tidak membuat retrieval percakapan menjadi salah.
        if (preg_match('/^\d+$/u', $word)) continue;
        $tokens[] = $word;
    }
    return array_values(array_unique($tokens));
}

function localConversationBigrams(array $tokens): array {
    $out = [];
    for ($i=0, $n=count($tokens)-1; $i<$n; $i++) $out[] = $tokens[$i].' '.$tokens[$i+1];
    return array_values(array_unique($out));
}

function localConversationSimilarity(string $a, string $b): float {
    $na = localConversationNormalize($a); $nb = localConversationNormalize($b);
    if ($na === '' || $nb === '') return 0.0;
    if ($na === $nb) return 1.0;
    if (localConversationLength($na) >= 8 && (localConversationPos($na,$nb) !== false || localConversationPos($nb,$na) !== false)) return 0.90;

    $ta = localConversationTokens($na); $tb = localConversationTokens($nb);
    if (!$ta || !$tb) return 0.0;
    $ia = array_values(array_intersect($ta,$tb));
    $union = array_values(array_unique(array_merge($ta,$tb)));
    $tokenScore = count($union) ? count($ia)/count($union) : 0.0;

    $ba = localConversationBigrams($ta); $bb = localConversationBigrams($tb);
    $bigramScore = 0.0;
    if ($ba && $bb) {
        $bi = array_values(array_intersect($ba,$bb));
        $bu = array_values(array_unique(array_merge($ba,$bb)));
        $bigramScore = count($bu) ? count($bi)/count($bu) : 0.0;
    }
    return min(1.0, ($tokenScore * 0.72) + ($bigramScore * 0.28));
}

function localConversationLooksFinancial(string $message): bool {
    $t = localConversationNormalize($message);
    if ($t === '') return false;

    if (function_exists('looksLikeTransactionStatement') && looksLikeTransactionStatement($message)) return true;

    // Intent keuangan yang tegas selalu diserahkan ke engine finansial.
    if (preg_match('/\b(?:saldo|dompet|rekening|kartu kredit|limit kartu|tagihan|cicilan|angsuran|gajian|pemasukan|pendapatan|pengeluaran|budget|anggaran|tabungan|piutang|hutang|utang|pinjam|pinjaman|pelunasan|transfer|transaksi)\b/u',$t)) return true;

    $moneyLike=(bool)preg_match('/(?:\d[\d.,]*\s*(?:k|rb|ribu|jt|juta)\b)|(?:\b(?:rp|rupiah)\s*\d)/u',$t);
    $questionLike=(bool)preg_match('/\b(?:berapa|cek|lihat|rekap|total|sisa|cukup|aman|habis|keluar|masuk)\b/u',$t);
    $moneyTopic=(bool)preg_match('/\b(?:uang|duit|gaji|belanja|bensin|bbm|makan|bank|bca|bri|bni|mandiri|seabank|gopay|dana|ovo|shopeepay)\b/u',$t);
    $transactionVerb=(bool)preg_match('/\b(?:catat|beli|bayar|terima|dapat|kirim|isi|topup|top up)\b/u',$t);

    if ($moneyLike && ($moneyTopic || $transactionVerb)) return true;
    if ($questionLike && $moneyTopic) return true;
    if ($transactionVerb && $moneyTopic) return true;
    return false;
}

function localConversationHistory(int $limit=50): array {
    static $cache = null;
    if (!function_exists('recentChats')) return [];
    if ($cache === null) {
        try { $cache=(array)recentChats(50); }
        catch (Throwable $e) { $cache=[]; }
    }
    $limit=max(1,min(50,$limit));
    return count($cache)>$limit ? array_slice($cache,-$limit) : $cache;
}

function localConversationPreviousUserText(string $current=''): string {
    $history = localConversationHistory(40);
    $currentKey = localConversationNormalize($current);
    for ($i=count($history)-1; $i>=0; $i--) {
        $row = $history[$i] ?? [];
        if (($row['role']??'') !== 'user') continue;
        $candidate = trim((string)($row['message']??''));
        if ($candidate === '') continue;
        if ($currentKey !== '' && localConversationNormalize($candidate) === $currentKey) continue;
        if (localConversationLooksFinancial($candidate)) continue;
        return $candidate;
    }
    return '';
}

function localConversationRecentAssistantReplies(int $limit=6): array {
    $history = localConversationHistory(30); $out=[];
    for($i=count($history)-1;$i>=0 && count($out)<$limit;$i--){
        if(($history[$i]['role']??'')!=='assistant')continue;
        $m=trim((string)($history[$i]['message']??''));if($m!=='')$out[]=$m;
    }
    return $out;
}

function localConversationPickFresh(array $options,string $seed=''): string {
    $options=array_values(array_filter(array_map('trim',$options),fn($x)=>$x!==''));
    if(!$options)return '';
    $recent=array_map('localConversationNormalize',localConversationRecentAssistantReplies(5));
    $fresh=array_values(array_filter($options,fn($x)=>!in_array(localConversationNormalize($x),$recent,true)));
    if($fresh)$options=$fresh;
    $key=$seed!==''?$seed:date('Y-m-d-H-i');
    return (string)$options[abs((int)crc32($key))%count($options)];
}

function localConversationAddress(): string {
    $history=localConversationHistory(30);$aku=0;$saya=0;
    foreach($history as $row){
        if(($row['role']??'')!=='user')continue;
        $t=' '.localConversationNormalize((string)($row['message']??'')).' ';
        $aku+=preg_match_all('/\b(?:aku|gue|gw)\b/u',$t,$m);
        $saya+=preg_match_all('/\b(?:saya)\b/u',$t,$m2);
    }
    return $saya>($aku*2+2)?'Anda':'kamu';
}

function localConversationTopicWords(string $text,int $limit=2): array {
    $tokens=localConversationTokens($text);
    $skip=['cerita','ceritakan','bilang','ngomong','kata','orang','teman','temen','banget','sekarang','akhirnya','awalnya','selesai','berhasil','memang','senang','bahagia','lega','seru','asyik','bangga','capek','cape','lelah','sedih','kecewa','kesal','marah','khawatir','cemas','takut','bingung','pusing','stres','stress','bosan','jenuh'];
    $tokens=array_values(array_filter($tokens,fn($x)=>!in_array($x,$skip,true) && localConversationLength($x)>=3));
    usort($tokens,function($a,$b){$la=localConversationLength($a);$lb=localConversationLength($b);return $lb<=>$la;});
    return array_slice($tokens,0,$limit);
}

function localConversationTone(string $message): string {
    $t=localConversationNormalize($message);
    $groups=[
        'happy'=>['senang','bahagia','lega','seru','asyik','excited','bangga','suka','berhasil','sukses','mantap'],
        'tired'=>['capek','cape','lelah','ngantuk','letih','kewalahan','overwhelmed'],
        'sad'=>['sedih','kecewa','down','nangis','menangis','kehilangan','sepi','kesepian'],
        'angry'=>['kesal','sebel','jengkel','marah','emosi','dongkol','muak'],
        'worried'=>['khawatir','cemas','takut','degdegan','gelisah','bingung','pusing','stres','stress'],
        'bored'=>['bosan','gabut','jenuh','bete'],
    ];
    foreach($groups as $tone=>$words)foreach($words as $w)if(preg_match('/\b'.preg_quote($w,'/').'\b/u',$t))return $tone;
    return 'neutral';
}

function localConversationReusableAssistantReply(string $reply): bool {
    $r=trim($reply);if($r===''||localConversationLength($r)>650)return false;
    $t=localConversationNormalize($r);
    if(localConversationLooksFinancial($r))return false;
    if(preg_match('/\b(?:belum mengenali|belum memahami|mode pembelajaran|coba gunakan perintah|periksa dulu sebelum disimpan|transaksi dibatalkan)\b/u',$t))return false;
    if(preg_match('/\bRp\s*[0-9]|\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/u',$r))return false;
    return true;
}

/**
 * Nearest-neighbour sederhana dari pasangan user->assistant milik akun yang sama.
 * Ini membuat engine dapat "belajar" pola obrolan yang pernah terjadi tanpa
 * mengirim data keluar. Hanya pasangan non-keuangan yang boleh direuse.
 */
function localConversationLearnedReply(string $message): ?string {
    $history=localConversationHistory(50);if(count($history)<4)return null;
    $pairs=[];
    for($i=0;$i<count($history)-1;$i++){
        $u=$history[$i]??[];$a=$history[$i+1]??[];
        if(($u['role']??'')!=='user'||($a['role']??'')!=='assistant')continue;
        $um=trim((string)($u['message']??''));$am=trim((string)($a['message']??''));
        if($um===''||$am===''||localConversationLooksFinancial($um)||!localConversationReusableAssistantReply($am))continue;
        if(localConversationNormalize($um)===localConversationNormalize($message))continue; // current turn may already be in history
        $score=localConversationSimilarity($message,$um);
        if($score>=0.76)$pairs[]=['score'=>$score,'reply'=>$am];
    }
    if(!$pairs)return null;
    usort($pairs,fn($x,$y)=>$y['score']<=>$x['score']);
    $best=$pairs[0];
    return (float)$best['score']>=0.82?(string)$best['reply']:null;
}

function localConversationFollowupReply(string $message): ?string {
    $t=trim(localConversationNormalize($message));
    if(!preg_match('/^(?:iya|ya|iyo|betul|benar|nah itu|persis|nggak|gak|ga|tidak|bukan|terus|lalu|terus gimana|trus gimana|oh begitu|oh iya|hmm|hmmm)$/u',$t))return null;
    $previous=localConversationPreviousUserText($message);if($previous==='')return null;
    $topics=localConversationTopicWords($previous,2);$topic=$topics?implode(' dan ',$topics):'cerita tadi';
    $address=localConversationAddress();
    if(preg_match('/^(?:nggak|gak|ga|tidak|bukan)$/u',$t)){
        return localConversationPickFresh([
            'Oke, berarti bukan itu. Kalau begitu, bagian dari '.$topic.' yang sebenarnya paling ingin '.$address.' bahas apa?',
            'Sip, saya tangkap. Coba luruskan sedikit soal '.$topic.' tadi—bagian mana yang berbeda dari yang saya pahami?'
        ],$previous.$t);
    }
    if(preg_match('/^(?:terus|lalu|terus gimana|trus gimana)$/u',$t)){
        return localConversationPickFresh([
            'Saya masih mengikuti cerita soal '.$topic.' tadi. Setelah itu apa yang terjadi?',
            'Lanjut, saya dengarkan. Bagian berikutnya dari '.$topic.' tadi gimana?',
            'Teruskan saja ceritanya—saya masih ingat konteks '.$topic.' tadi.'
        ],$previous.$t);
    }
    return localConversationPickFresh([
        'Iya, saya nangkap. Dari '.$topic.' tadi, bagian mana yang paling kepikiran sekarang?',
        'Oke, nyambung. Mau lanjut cerita soal '.$topic.' tadi?',
        'Sip, saya masih mengikuti konteksnya. Terus gimana setelah itu?'
    ],$previous.$t);
}

function localConversationPersonalQuestionReply(string $message): ?string {
    $t=localConversationNormalize($message);
    if(preg_match('/\b(?:kamu pernah|pernah nggak kamu|pernahkah kamu)\b/u',$t)){
        $addr=localConversationAddress();
        return 'Saya tidak punya pengalaman pribadi seperti manusia, tapi saya bisa ikut ngobrol dan memahami konteks dari cerita yang '.$addr.' sampaikan. Cerita saja, saya ikut dari situ.';
    }
    if(preg_match('/\b(?:kamu suka|favorit kamu|kesukaan kamu|kamu benci)\b/u',$t)){
        $addr=localConversationAddress();
        return 'Saya tidak punya selera pribadi, tapi saya bisa ikut membahasnya dari sudut pandang cerita dan pilihan yang '.$addr.' ceritakan. '.$addr.' sendiri lebih condong ke mana?';
    }
    return null;
}

function localConversationStoryPromptReply(string $message): ?string {
    $t=localConversationNormalize($message);
    if(!preg_match('/^(?:cerita dong|ceritain dong|ceritakan sesuatu|temani ngobrol|ajak ngobrol|ngobrol yuk|ayo ngobrol)(?:\s+tentang\s+(.+))?$/u',$t,$m))return null;
    $topic=trim((string)($m[1]??''));
    if($topic!==''){
        $addr=localConversationAddress();
        return 'Boleh. Kita ngobrol soal '.$topic.'. Kalau mulai dari '.$addr.' dulu: apa yang bikin topik itu lagi kepikiran sekarang?';
    }
    return localConversationPickFresh([
        'Boleh 😄 Kita ngobrol santai saja. Hari ini ada kejadian yang paling berkesan atau malah paling bikin capek?',
        'Ayo. Mulai dari hal sederhana: hari ini lebih banyak hal yang menyenangkan atau yang bikin pusing?',
        'Siap. Cerita saja apa pun yang lagi kepikiran—saya akan mengikuti konteksnya dari percakapan kita.'
    ],$t.date('Y-m-d-H'));
}

function localConversationGenericReply(string $message): ?string {
    $t=localConversationNormalize($message);if($t==='')return null;
    if(localConversationLooksFinancial($message))return null;

    // Jangan mengambil alih perintah belajar eksplisit milik Super Admin.
    if(preg_match('/^(?:ajarkan|pelajari|belajar|balas|jawab)\b/u',$t))return null;

    if(($r=localConversationFollowupReply($message))!==null)return $r;
    if(($r=localConversationPersonalQuestionReply($message))!==null)return $r;
    if(($r=localConversationStoryPromptReply($message))!==null)return $r;

    // Reuse pola obrolan user sendiri bila kemiripannya sangat tinggi.
    if(($learned=localConversationLearnedReply($message))!==null)return $learned;

    $tone=localConversationTone($message);$address=localConversationAddress();
    $previous=localConversationPreviousUserText($message);
    $topics=localConversationTopicWords($message,2);$topic=$topics?implode(' dan ',$topics):'';
    $isQuestion=(bool)preg_match('/\?\s*$/u',trim($message)) || (bool)preg_match('/^(?:apa|apakah|siapa|kenapa|mengapa|kok|gimana|bagaimana|menurut|boleh|bisa)\b/u',$t);

    if($tone==='happy')return localConversationPickFresh([
        'Wah, kedengarannya menyenangkan 😄'.($topic!==''?' Soal '.$topic.' itu,':'').' bagian yang paling bikin '.$address.' senang apa?',
        'Nice 😄 Senang dengarnya.'.($topic!==''?' Dari '.$topic.' itu,':'').' momen yang paling berkesan yang mana?',
        'Itu kabar enak didengar. Terus gimana ceritanya sampai bisa begitu?'
    ],$message);
    if($tone==='tired')return localConversationPickFresh([
        'Kedengarannya cukup menguras tenaga. Mau cerita bagian yang paling bikin capek?',
        'Wah, hari yang panjang ya. Yang paling menyita energi tadi apa?',
        'Pantes terasa capek. Cerita saja pelan-pelan, bagian mana yang paling berat hari ini?'
    ],$message);
    if($tone==='sad')return localConversationPickFresh([
        'Kedengarannya berat. Kalau '.$address.' mau cerita, saya bisa mengikuti tanpa buru-buru mengalihkan topik.',
        'Saya dengarkan. Dari semua yang terjadi, bagian mana yang paling terasa sekarang?',
        'Itu pasti bukan cerita yang ringan. Mau mulai dari kejadian yang paling bikin kepikiran?'
    ],$message);
    if($tone==='angry')return localConversationPickFresh([
        'Wajar kalau situasinya terasa menjengkelkan. Yang paling bikin kesal bagian mana?',
        'Saya nangkap nada kesalnya. Kejadiannya mulai dari mana?',
        'Kelihatannya ada bagian yang benar-benar mengganggu. Mau cerita detailnya?'
    ],$message);
    if($tone==='worried')return localConversationPickFresh([
        'Kedengarannya cukup bikin kepikiran. Hal yang paling '.$address.' khawatirkan dari situ apa?',
        'Saya nangkap ada kekhawatiran di situ. Mau kita urutkan ceritanya dari awal?',
        'Oke, ceritakan konteksnya sedikit lagi. Bagian mana yang paling bikin bingung atau cemas?'
    ],$message);
    if($tone==='bored')return localConversationPickFresh([
        'Kalau lagi bosan, kita bisa ngobrol santai 😄 Akhir-akhir ini ada hal baru yang pengin '.$address.' coba?',
        'Gabut mode ya 😄 Mau cerita soal kerjaan, hubungan, hobi, atau hal random saja?',
        'Boleh, kita isi waktu dengan ngobrol. Belakangan apa yang paling sering ada di pikiran?'
    ],$message);

    if(preg_match('/\b(?:kerja|kantor|atasan|bos|rekan|project|proyek|kegiatan|deadline|meeting|rapat)\b/u',$t)){
        return localConversationPickFresh([
            'Tentang kerjaan itu, bagian yang paling menyita perhatian '.$address.' apa?',
            'Oke, saya ikut konteks kerjaannya. Kejadiannya tadi gimana?',
            'Kerjaan memang sering punya ceritanya sendiri. Yang paling berkesan hari ini bagian mana?'
        ],$message);
    }
    if(preg_match('/\b(?:pacar|pasangan|teman|temen|sahabat|keluarga|orang tua|adik|kakak|istri|suami)\b/u',$t)){
        return localConversationPickFresh([
            'Saya ikut ceritanya. Hubungan dengan orang itu sekarang lagi gimana?',
            'Terus bagaimana respons dia waktu itu?',
            'Oke, konteksnya soal orang dekat. Bagian mana yang paling bikin '.$address.' kepikiran?'
        ],$message);
    }
    if(preg_match('/\b(?:tadi|kemarin|barusan|baru saja|akhirnya|awalnya|terjadi|ketemu|bertemu|pergi|datang)\b/u',$t)){
        return localConversationPickFresh([
            'Terus setelah itu gimana?',
            'Oh begitu. Lalu apa yang terjadi setelahnya?',
            'Saya ikut ceritanya. Bagian berikutnya gimana?'
        ],$message);
    }
    if(preg_match('/\b(?:rencana|berencana|pengen|ingin|mau|nanti|besok|minggu depan|bulan depan)\b/u',$t)){
        return localConversationPickFresh([
            'Menarik. Dari rencana itu, bagian yang paling '.$address.' tunggu apa?',
            'Kalau rencananya jadi, hasil yang paling '.$address.' harapkan apa?',
            'Oke, itu bisa jadi seru. Sudah kebayang langkah pertamanya?'
        ],$message);
    }

    if($isQuestion){
        // Untuk pertanyaan umum yang tidak punya basis pengetahuan lokal, jangan
        // berpura-pura mengetahui fakta. Tetap buat percakapan berjalan natural.
        if($previous!==''){
            $p=localConversationTopicWords($previous,2);$pt=$p?implode(' dan ',$p):'cerita sebelumnya';
            return 'Kalau pertanyaan itu masih terkait '.$pt.', kasih saya sedikit detail tambahan dari sisi '.$address.' supaya saya tidak asal menebak.';
        }
        return 'Bisa kita bahas. Karena saya berjalan lokal tanpa model pengetahuan internet, kasih sedikit konteks dari sudut '.$address.' dulu supaya respons saya tetap nyambung dan tidak asal menebak.';
    }

    $length=localConversationLength($t);
    if($length>=20){
        return localConversationPickFresh([
            'Saya ikut ceritanya.'.($topic!==''?' Tentang '.$topic.' itu,':'').' terus gimana setelahnya?',
            'Oke, saya tangkap konteksnya.'.($topic!==''?' Soal '.$topic.' itu,':'').' bagian mana yang paling ingin '.$address.' bahas?',
            'Menarik. Cerita lanjut saja—saya akan menjaga konteks dari pesan sebelumnya.'
        ],$message);
    }
    return null;
}

function localConversationReply(string $message): ?string {
    return localConversationGenericReply($message);
}
