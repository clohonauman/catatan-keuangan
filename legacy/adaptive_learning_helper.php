<?php
require_once __DIR__.'/db.php';

use app\repositories\AdaptiveLearningRepository;

/**
 * Adaptive Learning V20
 * - personal per user, never cross-account
 * - only learns semantic fields, never amount/date
 * - rule parser stays primary; learning may only refine ambiguous fields
 * - best effort: if tables/migration unavailable, old parser continues normally
 */
function adaptiveAiEnvBool(string $name,bool $default=true): bool {
    $raw=$_ENV[$name]??null;
    if($raw===null || $raw==='') return $default;
    return !in_array(strtolower(trim((string)$raw)),['0','false','off','no','disabled'],true);
}
function adaptiveAiAvailable(): bool {
    return AdaptiveLearningRepository::available();
}
function adaptiveAiEnabled(): bool {
    return adaptiveAiEnvBool('ADAPTIVE_AI_ENABLED',true) && adaptiveAiAvailable();
}
function adaptiveAiCanLearn(): bool {
    // Pengumpulan feedback tetap aktif selama tabel tersedia. Feature flag
    // ADAPTIVE_AI_ENABLED hanya mematikan penerapan prediksi, bukan membuang
    // koreksi user yang berharga untuk dipelajari saat fitur diaktifkan lagi.
    return adaptiveAiAvailable();
}
function adaptiveAiMinConfidence(): float {
    $raw=(float)($_ENV['ADAPTIVE_AI_MIN_CONFIDENCE']??0.76);
    return max(0.55,min(0.95,$raw));
}
function adaptiveAiUserId(): int {
    $u=authCurrentUser();return $u?(int)($u['id']??0):0;
}
function adaptiveAiJsonArray($raw): array {
    if(is_array($raw)) return $raw;
    $x=json_decode((string)$raw,true);return is_array($x)?$x:[];
}

function adaptiveNormalizeText(string $text): string {
    $t=strtolower(trim($text));
    $t=str_replace(['–','—'],'-',$t);
    // Identitas/angka yang tidak perlu dipelajari sebagai bahasa.
    $t=preg_replace('/\b[a-z]{1,2}\s*\d{1,4}\s*[a-z]{1,3}\b/iu',' plate ',$t);
    $t=preg_replace('/\b\d{1,2}[\/-]\d{1,2}(?:[\/-]\d{2,4})?\b/u',' date ',$t);
    $t=preg_replace('/\b\d{1,2}:\d{2}(?::\d{2})?\b/u',' time ',$t);
    $t=preg_replace('/(?<![a-z0-9])(?:rp\s*)?(?:\d{1,3}(?:[\.,]\d{3})+|\d+(?:[\.,]\d+)?)(?:\s*(?:juta|jt|ribu|rb|k))?(?![a-z0-9])/iu',' amount ',$t);
    $t=preg_replace('/\b\d{6,}\b/u',' idnum ',$t);
    $t=preg_replace('/[^\p{L}\p{N}\s]+/u',' ',$t);
    $t=preg_replace('/\s+/u',' ',$t);
    return trim($t);
}
function adaptiveStopWords(): array {
    static $map=null;if($map!==null)return $map;
    $words=['yang','dan','atau','dengan','untuk','pada','dalam','dari','ke','di','ini','itu','saya','aku','sy','gue','gw','nya','dong','nih','noh','ya','iya','iyo','tolong','catat','catatkan','transaksi','amount','date','time','plate','idnum','rp'];
    $map=array_fill_keys($words,true);return $map;
}
function adaptiveTokens(string $text): array {
    $t=adaptiveNormalizeText($text);if($t==='')return [];
    $stop=adaptiveStopWords();$tokens=[];
    foreach(preg_split('/\s+/u',$t,-1,PREG_SPLIT_NO_EMPTY) as $token){
        $token=trim($token);if($token===''||isset($stop[$token]))continue;
        if(strlen($token)<2)continue;
        $tokens[$token]=true;
    }
    return array_slice(array_keys($tokens),0,24);
}

/**
 * Stable semantic context keys used for habit learning. These are deliberately
 * narrow: category + genuinely repetitive spending pattern, not the current
 * wallet value and not the generic expense/income type. That lets a correction
 * such as "makan -> SeaBank" carry over to "nasi goreng" while avoiding a
 * dangerous global "all expenses -> SeaBank" bias.
 */
function adaptiveContextTokens(array $tx): array {
    $out=[];
    $category=trim((string)($tx['category']??''));
    $categoryNorm=adaptiveNormalizeText($category);
    if($categoryNorm!=='' && !in_array(strtolower($categoryNorm),['lainnya','transfer antar dompet'],true)){
        $slug=preg_replace('/[^a-z0-9]+/u','_',strtolower($categoryNorm));
        $slug=trim((string)$slug,'_');
        if($slug!=='')$out[]='ctx_category_'.$slug;
    }
    $kind=strtolower(trim((string)($tx['spending_kind']??'')));
    if(in_array($kind,['daily','recurring'],true))$out[]='ctx_kind_'.$kind;
    return array_values(array_unique($out));
}
function adaptiveTokenSimilarity(array $a,array $b): float {
    $a=array_values(array_unique($a));$b=array_values(array_unique($b));
    if(!$a||!$b)return 0.0;
    $am=array_fill_keys($a,true);$bm=array_fill_keys($b,true);$inter=0;
    foreach($am as $k=>$_)if(isset($bm[$k]))$inter++;
    if($inter===0)return 0.0;
    $union=count($am)+count($bm)-$inter;
    $j=$union>0?$inter/$union:0;
    $contain=$inter/min(count($am),count($bm));
    return min(1.0,0.62*$j+0.38*$contain);
}
function adaptiveFieldSnapshot(array $tx): array {
    $out=[];
    foreach(['type','category','wallet_id','from_wallet_id','to_wallet_id','spending_kind'] as $k){
        if(array_key_exists($k,$tx) && $tx[$k]!=='' && $tx[$k]!==null)$out[$k]=$tx[$k];
    }
    return $out;
}
function adaptiveComparableValue($v): string {
    if(is_bool($v))return $v?'1':'0';
    if(is_numeric($v))return (string)(int)$v;
    return strtolower(trim((string)$v));
}
function adaptiveStoredFieldValue(string $field,$v): string {
    if(in_array($field,['wallet_id','from_wallet_id','to_wallet_id'],true))return (string)(int)$v;
    if(in_array($field,['type','spending_kind'],true))return strtolower(trim((string)$v));
    return trim((string)$v);
}
function adaptiveCorrections(array $parsed,array $confirmed): array {
    $p=adaptiveFieldSnapshot($parsed);$c=adaptiveFieldSnapshot($confirmed);$diff=[];
    foreach(array_unique(array_merge(array_keys($p),array_keys($c))) as $k){
        if(adaptiveComparableValue($p[$k]??'')!==adaptiveComparableValue($c[$k]??''))$diff[$k]=['from'=>$p[$k]??null,'to'=>$c[$k]??null];
    }
    return $diff;
}
function adaptiveSignalRows(array $tokens,array $confirmed,array $corrections,array $contextTokens=[]): array {
    $fields=adaptiveFieldSnapshot($confirmed);$rows=[];
    $allTokens=array_values(array_unique(array_merge($tokens,$contextTokens)));
    foreach($fields as $field=>$value){
        if(!in_array($field,['type','category','wallet_id','spending_kind','from_wallet_id','to_wallet_id'],true))continue;
        $weight=array_key_exists($field,$corrections)?3:1;
        foreach($allTokens as $token)$rows[]=['token'=>$token,'field_name'=>$field,'field_value'=>adaptiveStoredFieldValue($field,$value),'weight'=>$weight];
    }
    return $rows;
}

function adaptiveCorrectionPenaltyRows(array $tokens,array $corrections,array $contextTokens=[]): array {
    $rows=[];
    $allTokens=array_values(array_unique(array_merge($tokens,$contextTokens)));
    foreach($corrections as $field=>$change){
        if(!in_array($field,['type','category','wallet_id','spending_kind','from_wallet_id','to_wallet_id'],true))continue;
        $from=$change['from']??null;
        if($from===null||$from==='')continue;
        $value=adaptiveStoredFieldValue($field,$from);
        if($value==='')continue;
        foreach($allTokens as $token)$rows[]=[
            'token'=>$token,'field_name'=>$field,'field_value'=>$value,'weight'=>2
        ];
    }
    return $rows;
}
function adaptiveLearnFromConfirmation(array $pending,array $finalDrafts): array {
    $uid=adaptiveAiUserId();
    if($uid<=0||!adaptiveAiCanLearn()){
        $result=['learned'=>0,'corrected'=>0,'fields'=>[],'available'=>adaptiveAiAvailable()];
        $GLOBALS['adaptive_learning_last_result']=$result;
        return $result;
    }
    // Gunakan baseline parser yang disimpan sebelum koreksi user.
    // Sebelumnya kita membaca pending['drafts']; padahal dialog koreksi memang
    // mengubah array tersebut langsung sehingga perubahan awal -> hasil koreksi
    // hilang sebelum fungsi learning dipanggil saat Simpan.
    $drafts=(array)(
        $pending['original_drafts']
        ?? $pending['adaptive_original_drafts']
        ?? $pending['drafts']
        ?? []
    );
    $learned=0;$correctedCount=0;$correctedFields=[];
    foreach($finalDrafts as $i=>$confirmed){
        $parsed=(array)($drafts[$i]??[]);
        // Draft biaya admin otomatis adalah transaksi turunan, bukan contoh
        // perilaku semantik user untuk belajar jenis/kategori/dompet.
        if(!empty($parsed['admin_fee_auto_wallet'])||!empty($confirmed['admin_fee_auto_wallet']))continue;
        $source=trim((string)($parsed['note']??''));
        if($source==='')$source=trim((string)($pending['message']??''));
        if($source==='')continue;
        $normalized=adaptiveNormalizeText($source);$tokens=adaptiveTokens($source);if(!$tokens)continue;
        $corrections=adaptiveCorrections($parsed,$confirmed);$wasCorrected=!empty($corrections);
        $parsedContextTokens=adaptiveContextTokens($parsed);
        $confirmedContextTokens=adaptiveContextTokens($confirmed);
        AdaptiveLearningRepository::recordExample($uid,[
            'message_hash'=>hash('sha256',$normalized),
            'source_message'=>substr($source,0,1000),
            'normalized_text'=>substr($normalized,0,500),
            'tokens'=>$tokens,
            'parsed'=>adaptiveFieldSnapshot($parsed),
            'confirmed'=>adaptiveFieldSnapshot($confirmed),
            'correction'=>$corrections,
            'was_corrected'=>$wasCorrected,
        ],adaptiveSignalRows($tokens,$confirmed,$corrections,$confirmedContextTokens));

        // Koreksi manual berarti prediksi lama terbukti salah. Kurangi asosiasi
        // token terhadap nilai lama agar model benar-benar bisa "unlearn".
        if($wasCorrected){
            AdaptiveLearningRepository::penalizeTokenSignals(
                $uid,
                adaptiveCorrectionPenaltyRows($tokens,$corrections,$parsedContextTokens)
            );
            foreach(array_keys($corrections) as $field)$correctedFields[$field]=true;
        }

        $learned++;if($wasCorrected)$correctedCount++;
    }
    $result=[
        'learned'=>$learned,
        'corrected'=>$correctedCount,
        'fields'=>array_keys($correctedFields),
        'available'=>true,
        'enabled'=>adaptiveAiEnabled()
    ];
    $GLOBALS['adaptive_learning_last_result']=$result;
    return $result;
}
function adaptiveLearningLastResult(): array {
    return is_array($GLOBALS['adaptive_learning_last_result']??null)
        ? $GLOBALS['adaptive_learning_last_result']
        : ['learned'=>0,'corrected'=>0,'fields'=>[]];
}

function adaptivePredictionVotes(string $message,array $context=[]): array {
    $uid=adaptiveAiUserId();
    $tokens=array_values(array_unique(adaptiveTokens($message)));
    $currentContext=adaptiveContextTokens($context);
    $predictionTokens=array_values(array_unique(array_merge($tokens,$currentContext)));
    if($uid<=0||!adaptiveAiEnabled()||!$predictionTokens)return ['fields'=>[],'matched_examples'=>0,'example_ids'=>[],'example_count'=>0,'manual_overrides'=>[]];

    $examples=AdaptiveLearningRepository::recentExamples($uid,140);
    $votes=[];$matched=[];$manualAnchors=[];

    foreach($examples as $position=>$ex){
        $exampleTokens=(array)($ex['tokens']??[]);
        $confirmed=(array)($ex['confirmed']??[]);
        $exampleContext=adaptiveContextTokens($confirmed);
        $rawSim=adaptiveTokenSimilarity($tokens,$exampleTokens);
        $contextSim=adaptiveTokenSimilarity($currentContext,$exampleContext);
        $sim=max($rawSim,$contextSim>=0.999?0.86:0.0);

        // Explicit user corrections are hard personal memories. A correction
        // must not be outvoted by dozens of older, uncorrected confirmations
        // that happen to use the same category. Exact/near phrase matches win;
        // same-category matches also win so "makan siang" can teach "nasi goreng".
        if(!empty($ex['was_corrected']) && $sim>=0.80){
            $correction=(array)($ex['correction']??[]);
            foreach(array_keys($correction) as $field){
                $value=adaptiveStoredFieldValue($field,$confirmed[$field]??'');
                if($value==='')continue;
                $score=$sim + ($rawSim>=0.99?0.10:0.0) - ($position*0.0001);
                $currentAnchorScore = isset($manualAnchors[$field])
                    ? (float)($manualAnchors[$field]['score'] ?? -INF)
                    : -INF;
                if($score>$currentAnchorScore){
                    $manualAnchors[$field]=[
                        'value'=>$value,
                        'score'=>$score,
                        'confidence'=>min(0.995,max(0.92,$sim + ($rawSim>=0.99?0.05:0.0))),
                        'evidence'=>$rawSim>=0.99?8.0:6.0,
                        'example_id'=>(int)($ex['id']??0),
                        'similarity'=>$sim,
                        'match_type'=>$rawSim>=0.99?'exact_correction':'category_correction',
                    ];
                }
            }
        }

        if($rawSim<0.34 && $contextSim<0.999)continue;
        if(max($rawSim,$contextSim)<0.34)continue;
        $bonus=!empty($ex['was_corrected'])?2.35:0.90;
        $weight=max($rawSim,$contextSim)*$bonus;
        foreach(adaptiveFieldSnapshot($confirmed) as $field=>$value){
            $value=adaptiveStoredFieldValue($field,$value);if($value==='')continue;
            $votes[$field][$value]=($votes[$field][$value]??0)+$weight;
        }
        if(($rawSim>=0.34)||($contextSim>=0.999)){
            $matched[]=['id'=>(int)($ex['id']??0),'similarity'=>max($rawSim,$contextSim)];
        }
    }

    // Personal vocabulary / context classifier. Context keys are deliberate
    // habits such as category Makan -> wallet, but explicit correction anchors
    // below always override these aggregate counts.
    foreach(AdaptiveLearningRepository::tokenStats($uid,$predictionTokens) as $stat){
        $token=(string)($stat['token']??'');$field=(string)($stat['field_name']??'');$value=(string)($stat['field_value']??'');$count=max(0,(int)($stat['positive_count']??0));
        if($token===''||$field===''||$value===''||$count<=0)continue;
        $base=str_starts_with($token,'ctx_')?0.90:0.28;
        $weight=$base*log(1+$count);
        $votes[$field][$value]=($votes[$field][$value]??0)+$weight;
    }

    $fields=[];
    foreach($votes as $field=>$values){
        arsort($values,SORT_NUMERIC);$bestValue=(string)array_key_first($values);$best=(float)$values[$bestValue];$total=max(0.0001,array_sum($values));
        $ratio=$best/$total;$evidence=min(1.0,$best/1.35);$confidence=max(0.0,min(0.99,$ratio*(0.55+0.45*$evidence)));
        $fields[$field]=['value'=>$bestValue,'confidence'=>$confidence,'evidence'=>$best];
    }

    // Manual correction anchors are intentionally applied last. This is the
    // critical fix: a correction such as Makan -> Uang Tunai must not be
    // overridden by older Makan -> Honest examples.
    foreach($manualAnchors as $field=>$anchor){
        $fields[$field]=[
            'value'=>$anchor['value'],
            'confidence'=>$anchor['confidence'],
            'evidence'=>$anchor['evidence']
        ];
        $matched[]=['id'=>$anchor['example_id'],'similarity'=>$anchor['similarity']];
    }

    usort($matched,fn($a,$b)=>$b['similarity']<=>$a['similarity']);
    $usedIds=array_values(array_filter(array_map(fn($x)=>(int)$x['id'],array_slice($matched,0,8))));
    $manualMeta=[];
    foreach($manualAnchors as $field=>$anchor){
        $manualMeta[$field]=[
            'example_id'=>(int)$anchor['example_id'],
            'similarity'=>(float)$anchor['similarity'],
            'match_type'=>(string)$anchor['match_type']
        ];
    }
    return [
        'fields'=>$fields,
        'matched_examples'=>count($matched),
        'example_ids'=>$usedIds,
        'example_count'=>AdaptiveLearningRepository::countExamples($uid),
        'manual_overrides'=>$manualMeta,
    ];
}
function adaptiveMentionedWalletIds(string $message): array {
    if(!function_exists('financeWallets'))return [];
    $t=function_exists('normalizeChatMessage')?normalizeChatMessage($message):strtolower(trim($message));
    $ids=[];
    foreach((array)financeWallets(true) as $w){
        $id=(int)($w['id']??0);$name=trim((string)($w['name']??''));
        if($id<=0||$name==='')continue;
        $parts=preg_split('/[^a-z0-9]+/u',strtolower($name),-1,PREG_SPLIT_NO_EMPTY);
        if(!$parts)continue;
        $pattern=implode('[\s._-]*',array_map(fn($x)=>preg_quote($x,'/'),$parts));
        if($pattern!==''&&preg_match('/(^|[^a-z0-9])'.$pattern.'([^a-z0-9]|$)/iu',$t))$ids[$id]=true;
    }
    return array_keys($ids);
}

function adaptiveSemanticTypeHint(string $message): ?string {
    $t=adaptiveNormalizeText($message);
    if($t==='')return null;

    if(preg_match('/\b(?:transfer antar|pindah(?:kan)?|mutasi)\b/u',$t))return 'transfer';

    // Jika dua dompet milik user disebut dalam arah "dari ... ke ...",
    // perlakukan sebagai transfer antar dompet, bukan pemasukan.
    $walletIds=adaptiveMentionedWalletIds($message);
    if(count($walletIds)>=2 && preg_match('/\bdari\b.*\bke\b/u',$t))return 'transfer';

    // Arah yang menyebut user sendiri bersifat tegas.
    if(preg_match('/\b(?:saya|aku|kita)\s+(?:kasih|beri|memberi|kirim|transfer)\b/u',$t))return 'expense';
    if(preg_match('/\b(?:kasih|beri|memberi|kirim|transfer)\s+(?:ke\s+)?(?:saya|aku|kita)\b/u',$t))return 'income';

    // "<nama orang> transfer ... ke <dompet saya>" adalah pemasukan.
    // Contoh: "Utari transfer 150k ke Seabank".
    $wallet=function_exists('walletMentionForText')?walletMentionForText($message):null;
    if($wallet && preg_match('/^(?!\s*(?:saya|aku|kita)\b)[\p{L}][\p{L}\s.\'’-]{0,45}\s+(?:transfer|kirim)\b.*\bke\b/u',$t)){
        return 'income';
    }
    if($wallet && preg_match('/\b(?:dari)\s+[\p{L}][\p{L}\s.\'’-]{0,45}\s+(?:ke|masuk ke)\b/u',$t)){
        return 'income';
    }

    if(preg_match('/\b(?:kasih|beri|memberi)\s+uang\b/u',$t)
        && !preg_match('/\b(?:saya|aku|kita)\s+(?:kasih|beri|memberi)\b/u',$t))return 'income';
    if(preg_match('/\b(?:pemasukan|pendapatan|dapat|terima|diterima|dikasih|diberi|bunga|cashback|refund|bonus|gaji|masuk)\b/u',$t))return 'income';
    if(preg_match('/\b(?:pengeluaran|beli|bayar|belanja|keluar|bbm|bensin|makan|cicilan|tagihan)\b/u',$t))return 'expense';
    return null;
}
function adaptiveHardExplicitType(string $message): ?string {
    $t=adaptiveNormalizeText($message);
    if($t==='')return null;
    if(preg_match('/\b(?:transfer antar|pindah(?:kan)?|mutasi)\b/u',$t))return 'transfer';
    if(preg_match('/\b(?:saya|aku|kita)\s+(?:kasih|beri|memberi|kirim|transfer)\b/u',$t))return 'expense';
    if(preg_match('/\b(?:kasih|beri|memberi|kirim|transfer)\s+(?:ke\s+)?(?:saya|aku|kita)\b/u',$t))return 'income';
    if(preg_match('/\b(?:pemasukan|pendapatan|dapat|terima|diterima|dikasih|diberi|bunga|cashback|refund|bonus|gaji|masuk)\b/u',$t))return 'income';
    if(preg_match('/\b(?:pengeluaran|beli|bayar|belanja|keluar|bbm|bensin|makan|cicilan|tagihan)\b/u',$t))return 'expense';
    return null;
}
function adaptiveExplicitType(string $message): ?string {
    return adaptiveHardExplicitType($message);
}
function adaptivePredictionValue(array $prediction,string $field,float $threshold): ?array {
    $x=$prediction['fields'][$field]??null;
    if(!is_array($x)||(float)($x['confidence']??0)<$threshold)return null;
    return $x;
}
function adaptiveApplyToDrafts(string $message,array $drafts): array {
    if(!$drafts)return $drafts;
    $min=adaptiveAiMinConfidence();
    $enabled=adaptiveAiEnabled();

    foreach($drafts as $i=>&$draft){
        $source=trim((string)($draft['note']??$message));
        $applied=[];$conf=[];$prediction=['fields'=>[],'matched_examples'=>0,'example_ids'=>[],'example_count'=>0];

        // Pemahaman arah transaksi berbasis bahasa tetap bekerja walaupun
        // inference adaptive dimatikan. Ini bukan "hafalan", melainkan grammar.
        $hardType=adaptiveHardExplicitType($source);
        $semanticType=adaptiveSemanticTypeHint($source);
        if($semanticType!==null && $semanticType!==(string)($draft['type']??'')){
            $draft['type']=$semanticType;
            $applied[]='type_semantic';
            $conf[]=0.98;
        }

        if($enabled){
            // First pass uses the parser's current semantic context. After type/
            // category refinement we run a second pass so learned category ->
            // wallet habits can affect the same draft immediately.
            $prediction=adaptivePredictionVotes($source,$draft);
            $typePred=adaptivePredictionValue($prediction,'type',$min);

            // Koreksi personal boleh mengalahkan inferensi grammar yang lemah,
            // tetapi tidak mengalahkan kata yang benar-benar eksplisit seperti
            // "pemasukan", "pengeluaran", atau "transfer antar dompet".
            if($typePred && $hardType===null){
                $v=(string)$typePred['value'];
                if(in_array($v,['expense','income','transfer'],true) && $v!==(string)($draft['type']??'')){
                    $draft['type']=$v;$applied[]='type';$conf[]=(float)$typePred['confidence'];
                }
            }

            $currentType=(string)($draft['type']??'expense');
            $catPred=adaptivePredictionValue($prediction,'category',max(0.70,$min-0.05));
            if($catPred){
                $current=(string)($draft['category']??'Lainnya');$c=(float)$catPred['confidence'];
                if($current===''||strtolower($current)==='lainnya'||$c>=0.90){
                    $value=trim((string)$catPred['value']);
                    if($value!==''&&strcasecmp($value,$current)!==0){
                        $draft['category']=$value;$applied[]='category';$conf[]=$c;
                    }
                }
            }

            // Recompute after the category/type pass. This is what makes a
            // corrected wallet follow a learned semantic category on later text.
            $prediction=adaptivePredictionVotes($source,$draft);

            $walletExplicit=function_exists('walletMentionForText') ? walletMentionForText($source) : null;
            if($currentType!=='transfer' && !$walletExplicit){
                $walletPred=adaptivePredictionValue($prediction,'wallet_id',max(0.78,$min));
                if($walletPred){
                    $wid=(int)$walletPred['value'];
                    $valid=!function_exists('financeWalletById')||financeWalletById($wid);
                    if($wid>0&&$valid&&$wid!==(int)($draft['wallet_id']??0)){
                        $draft['wallet_id']=$wid;$applied[]='wallet_id';$conf[]=(float)$walletPred['confidence'];
                    }
                }
            }

            if($currentType==='expense'){
                $kindPred=adaptivePredictionValue($prediction,'spending_kind',max(0.80,$min));
                if($kindPred){
                    $v=(string)$kindPred['value'];
                    if(in_array($v,['daily','once','recurring'],true)&&$v!==(string)($draft['spending_kind']??'')){
                        $draft['spending_kind']=$v;$applied[]='spending_kind';$conf[]=(float)$kindPred['confidence'];
                    }
                }
            }else{
                $draft['spending_kind']='once';
            }

            if($applied && !empty($prediction['example_ids'])){
                AdaptiveLearningRepository::markExamplesUsed((array)$prediction['example_ids']);
            }
        }else{
            $currentType=(string)($draft['type']??'expense');
            if($currentType!=='expense')$draft['spending_kind']='once';
        }

        $draft['adaptive_meta']=[
            'enabled'=>$enabled,
            'available'=>adaptiveAiAvailable(),
            'applied'=>$applied,
            'confidence'=>$conf?array_sum($conf)/count($conf):0,
            'matched_examples'=>(int)($prediction['matched_examples']??0),
            'example_count'=>(int)($prediction['example_count']??0),
            'type_prediction'=>$prediction['fields']['type']??null,
            'manual_overrides'=>(array)($prediction['manual_overrides']??[]),
        ];
    }
    unset($draft);
    return $drafts;
}
function adaptiveLearningStatus(): array {
    $uid=adaptiveAiUserId();$available=adaptiveAiAvailable();$enabled=$uid>0&&adaptiveAiEnabled();$count=($uid>0&&$available)?AdaptiveLearningRepository::countExamples($uid):0;
    return [
        'enabled'=>$enabled,
        'collecting'=>$uid>0&&$available,
        'available'=>$available,
        'examples'=>$count,
        'ready'=>$count>=1,
        'mode'=>'personal',
        'storage'=>method_exists('\app\repositories\AdaptiveLearningRepository','storageMode') ? AdaptiveLearningRepository::storageMode() : 'unknown',
        'min_confidence'=>adaptiveAiMinConfidence()
    ];
}
function adaptiveExportCurrentUser(): array { $uid=adaptiveAiUserId();return $uid>0?AdaptiveLearningRepository::exportUser($uid):['version'=>1,'examples'=>[],'token_stats'=>[]]; }
function adaptiveImportCurrentUser(array $payload,bool $replace=true): void { $uid=adaptiveAiUserId();if($uid>0)AdaptiveLearningRepository::importUser($uid,$payload,$replace); }
