<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/mail_helper.php';

const USER_EMAIL_NOTIFICATION_FILE = __DIR__.'/data/user_email_notifications.json';

function emailNotifyDefaultData(): array {
    return [
        'campaigns' => [],
        'automatic' => [],
        'meta' => ['next_campaign_id'=>1, 'next_auto_id'=>1],
    ];
}

function emailNotifyNormalizeData($data): array {
    if (!is_array($data)) $data = [];
    $base = emailNotifyDefaultData();
    if (!isset($data['campaigns']) || !is_array($data['campaigns'])) $data['campaigns'] = [];
    if (!isset($data['automatic']) || !is_array($data['automatic'])) $data['automatic'] = [];
    if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = [];
    $data['meta']['next_campaign_id'] = max(1, (int)($data['meta']['next_campaign_id'] ?? 1));
    $data['meta']['next_auto_id'] = max(1, (int)($data['meta']['next_auto_id'] ?? 1));
    return array_replace($base, $data);
}

function emailNotifyRead(): array {
    if (!file_exists(USER_EMAIL_NOTIFICATION_FILE)) return emailNotifyDefaultData();
    $raw = @file_get_contents(USER_EMAIL_NOTIFICATION_FILE);
    return emailNotifyNormalizeData(json_decode((string)$raw, true));
}

function emailNotifyMutate(callable $fn) {
    $dir = dirname(USER_EMAIL_NOTIFICATION_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fp = @fopen(USER_EMAIL_NOTIFICATION_FILE, 'c+');
    if (!$fp) throw new RuntimeException('Penyimpanan notifikasi email tidak dapat dibuka.');
    try {
        if (!flock($fp, LOCK_EX)) throw new RuntimeException('Penyimpanan notifikasi email sedang digunakan.');
        rewind($fp);
        $raw = stream_get_contents($fp);
        $data = emailNotifyNormalizeData(json_decode((string)$raw, true));
        $result = $fn($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        if ($json === false) throw new RuntimeException('Data notifikasi email tidak dapat disimpan.');
        ftruncate($fp, 0); rewind($fp);
        if (fwrite($fp, $json) === false) throw new RuntimeException('Gagal menulis data notifikasi email.');
        fflush($fp);
        flock($fp, LOCK_UN);
        return $result;
    } finally {
        @fclose($fp);
    }
}

function emailNotifyTextLen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function emailNotifyTextSlice(string $s, int $start, int $len): string { return function_exists('mb_substr') ? mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len); }
function emailNotifyEsc($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function emailNotifySafeUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';
    if (!filter_var($url, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Link/tombol email tidak valid.');
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['https','http'], true)) throw new InvalidArgumentException('Link email harus menggunakan http atau https.');
    return $url;
}

function emailNotifyMaskEmail(string $email): string {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    [$local,$domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') return '';
    $visible = strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);
    return $visible.'***@'.$domain;
}

function emailNotifyAndroidDownloadPage(): string {
    return 'https://charlie-finance.rf.gd/android-download.php';
}

function emailNotifyNormalizeBroadcastActionUrl(string $kind, string $url): array {
    $url = trim($url);
    if ($url === '') {
        return [$kind === 'android_update' ? emailNotifyAndroidDownloadPage() : '', $kind === 'android_update'];
    }
    $safe = emailNotifySafeUrl($url);
    $path = strtolower((string)parse_url($safe, PHP_URL_PATH));
    if ($kind === 'android_update' && preg_match('/\.apk$/i', $path)) {
        // Link APK langsung lebih sering dianggap berisiko oleh filter email.
        // Email diarahkan ke landing page resmi; file APK tetap diunduh setelah pengguna membuka halaman tersebut.
        return [emailNotifyAndroidDownloadPage(), true];
    }
    return [$safe, false];
}

function emailNotifyTemplate(string $title, string $message, array $options=[]): string {
    $app = emailNotifyEsc(APP_NAME);
    $titleEsc = emailNotifyEsc($title);
    $messageEsc = nl2br(emailNotifyEsc($message));
    $label = trim((string)($options['action_label'] ?? ''));
    $url = trim((string)($options['action_url'] ?? ''));
    $includeLink = !empty($options['include_link']);
    $kind = strtolower(trim((string)($options['kind'] ?? 'info')));
    $eyebrow = [
        'android_update'=>'PEMBARUAN APLIKASI', 'announcement'=>'PENGUMUMAN', 'maintenance'=>'INFORMASI LAYANAN',
        'security'=>'KEAMANAN AKUN', 'bill'=>'PENGINGAT TAGIHAN', 'budget'=>'PENGINGAT KEUANGAN',
        'low_balance'=>'PERINGATAN SALDO', 'premium'=>'PREMIUM',
    ][$kind] ?? 'NOTIFIKASI';

    // Mode aman adalah default. Link eksternal sengaja tidak disisipkan kecuali admin
    // mengaktifkannya secara eksplisit, karena domain download/hosting dapat membuat
    // Gmail memfilter email meskipun SMTP mengembalikan 250 OK.
    $button = '';
    if ($includeLink && $url !== '') {
        $safeUrl = emailNotifyEsc($url);
        $safeLabel = emailNotifyEsc($label !== '' ? $label : 'Buka Informasi');
        $button = '<div style="margin-top:20px"><a href="'.$safeUrl.'" style="display:inline-block;background:#175cd3;color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 18px;border-radius:10px">'.$safeLabel.'</a></div>';
    }

    $safeHint = '';
    if ($kind === 'android_update' && !$includeLink) {
        $safeHint = '<p style="margin:18px 0 0;color:#667085;font-size:12px;line-height:1.6">Buka aplikasi Catatan Keuangan untuk melihat informasi pembaruan dan sumber unduhan terbaru.</p>';
    }

    // Dibuat sedekat mungkin dengan format email keamanan / invoice yang sudah terbukti
    // masuk ke Gmail: satu card sederhana, tanpa tracking pixel, tanpa URL tersembunyi,
    // dan tanpa elemen promosi berlebihan.
    return '<!doctype html><html><body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#101828">'
        .'<div style="max-width:540px;margin:32px auto;padding:0 16px"><div style="background:#fff;border:1px solid #e4e7ec;border-radius:20px;padding:28px;box-shadow:0 8px 30px rgba(16,24,40,.08)">'
        .'<div style="font-size:12px;font-weight:700;color:#175cd3;margin-bottom:8px">'.$app.' · '.emailNotifyEsc($eyebrow).'</div>'
        .'<h2 style="margin:0 0 12px;font-size:22px;line-height:1.3">'.$titleEsc.'</h2>'
        .'<div style="margin:0;color:#667085;font-size:14px;line-height:1.7">'.$messageEsc.'</div>'
        .$button.$safeHint
        .'<p style="margin:22px 0 0;padding-top:16px;border-top:1px solid #edf0f4;color:#98a2b3;font-size:11px;line-height:1.5">Notifikasi resmi dari '.$app.'. Jika informasi ini tidak relevan, Anda dapat mengabaikan email ini.</p>'
        .'</div></div></body></html>';
}

function emailNotifyUserRecord(int $userId): ?array {
    $u = authFindUserById($userId);
    return is_array($u) ? $u : null;
}

function emailNotifyUserEmail(array $user, bool $verifiedOnly=true): string {
    $email = strtolower(trim((string)($user['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    if ($verifiedOnly && trim((string)($user['email_verified_at'] ?? '')) === '') return '';
    return $email;
}

function emailNotifySendToUser(int $userId, string $kind, string $title, string $message, array $options=[]): array {
    $user = emailNotifyUserRecord($userId);
    if (!$user) return ['ok'=>false,'skipped'=>true,'reason'=>'Pengguna tidak ditemukan.'];
    $email = emailNotifyUserEmail($user, !array_key_exists('verified_only', $options) || !empty($options['verified_only']));
    if ($email === '') return ['ok'=>false,'skipped'=>true,'reason'=>'Email belum tersedia atau belum terverifikasi.'];

    $subject = trim((string)($options['subject'] ?? $title));
    $actionUrl = trim((string)($options['action_url'] ?? ''));
    if ($actionUrl !== '') $actionUrl = emailNotifySafeUrl($actionUrl);
    $html = emailNotifyTemplate($title, $message, [
        'kind'=>$kind,
        'action_url'=>$actionUrl,
        'action_label'=>(string)($options['action_label'] ?? ''),
        'include_link'=>!empty($options['include_link']),
    ]);
    financeSendMail($email, '['.APP_NAME.'] '.$subject, $html, $message);
    return ['ok'=>true,'email'=>$email,'username'=>(string)($user['username'] ?? '')];
}

function emailNotifyAutomatic(int $userId, string $dedupKey, string $kind, string $title, string $message, array $options=[]): array {
    $dedupKey = preg_replace('/[^a-zA-Z0-9_.:-]+/', '_', trim($dedupKey));
    if ($dedupKey === '') throw new InvalidArgumentException('Kunci notifikasi tidak valid.');
    $day = date('Y-m-d');
    $fullKey = $userId.'|'.$day.'|'.$dedupKey;
    $existing = emailNotifyRead();
    foreach (array_reverse($existing['automatic']) as $row) {
        if (($row['dedup_key'] ?? '') === $fullKey && ($row['status'] ?? '') === 'sent') {
            return ['ok'=>true,'skipped'=>true,'reason'=>'Notifikasi email hari ini sudah dikirim.'];
        }
    }

    $status='sent'; $error=''; $result=[];
    try { $result = emailNotifySendToUser($userId, $kind, $title, $message, $options); if (!empty($result['skipped'])) $status='skipped'; }
    catch (Throwable $e) { $status='failed'; $error=$e->getMessage(); }

    emailNotifyMutate(function (&$data) use ($userId,$fullKey,$kind,$title,$message,$status,$error,$result) {
        $data['automatic'][] = [
            'id'=>(int)$data['meta']['next_auto_id']++, 'user_id'=>$userId, 'dedup_key'=>$fullKey,
            'kind'=>$kind, 'title'=>$title, 'message'=>$message, 'email'=>(string)($result['email'] ?? ''),
            'status'=>$status, 'error'=>$error ?: (string)($result['reason'] ?? ''), 'created_at'=>date('Y-m-d H:i:s'),
        ];
        if (count($data['automatic']) > 1500) $data['automatic'] = array_slice($data['automatic'], -1500);
    });

    if ($status === 'failed') return ['ok'=>false,'error'=>$error];
    if ($status === 'skipped') return ['ok'=>true,'status'=>'skipped','skipped'=>true,'reason'=>(string)($result['reason'] ?? '')];
    return array_merge($result, ['ok'=>true,'status'=>$status]);
}

function emailBroadcastTargetUsers(string $target, array $userIds=[]): array {
    $target = strtolower(trim($target));
    if (!in_array($target, ['all','free','premium','selected'], true)) throw new InvalidArgumentException('Target broadcast tidak valid.');
    $wanted = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($x)=>$x>0)));
    $auth = authReadData();
    $rows=[]; $seen=[];
    foreach ((array)($auth['users'] ?? []) as $u) {
        $id=(int)($u['id'] ?? 0);
        if ($target === 'selected' && !in_array($id, $wanted, true)) continue;
        $plan = authPlan($u);
        if ($target === 'free' && !empty($plan['active'])) continue;
        if ($target === 'premium' && empty($plan['active'])) continue;
        $email = emailNotifyUserEmail($u, true);
        if ($email === '' || isset($seen[$email])) continue;
        $seen[$email]=true;
        $rows[]=['user_id'=>$id,'username'=>(string)($u['username'] ?? ''),'email'=>$email,'status'=>'pending','error'=>'','sent_at'=>''];
    }
    return $rows;
}

function emailBroadcastCreate(array $admin, array $input): array {
    if (!authIsSuperAdmin($admin)) throw new RuntimeException('Akses Super Admin diperlukan.');
    $kind = strtolower(trim((string)($input['kind'] ?? 'announcement')));
    if (!in_array($kind, ['android_update','announcement','maintenance','security'], true)) throw new InvalidArgumentException('Jenis broadcast tidak valid.');
    $target = strtolower(trim((string)($input['target'] ?? 'all')));
    $title = trim((string)($input['title'] ?? ''));
    $subject = trim((string)($input['subject'] ?? $title));
    $message = trim((string)($input['message'] ?? ''));
    $actionUrl = trim((string)($input['action_url'] ?? ''));
    $actionLabel = trim((string)($input['action_label'] ?? ''));
    $includeLink = !empty($input['include_link']);
    $actionUrlRewritten = false;
    if ($title === '' || emailNotifyTextLen($title) > 120) throw new InvalidArgumentException('Judul broadcast wajib diisi (maks. 120 karakter).');
    if ($subject === '' || emailNotifyTextLen($subject) > 140) throw new InvalidArgumentException('Subjek email wajib diisi (maks. 140 karakter).');
    if ($message === '' || emailNotifyTextLen($message) > 4000) throw new InvalidArgumentException('Isi broadcast wajib diisi (maks. 4.000 karakter).');
    [$actionUrl, $actionUrlRewritten] = emailNotifyNormalizeBroadcastActionUrl($kind, $actionUrl);
    if (emailNotifyTextLen($actionLabel) > 50) throw new InvalidArgumentException('Label tombol terlalu panjang.');
    $recipients = emailBroadcastTargetUsers($target, (array)($input['user_ids'] ?? []));
    if (!$recipients) throw new InvalidArgumentException('Tidak ada pengguna dengan email terverifikasi pada target ini.');

    return emailNotifyMutate(function (&$data) use ($admin,$kind,$target,$title,$subject,$message,$actionUrl,$actionLabel,$includeLink,$actionUrlRewritten,$recipients) {
        $campaign=[
            'id'=>(int)$data['meta']['next_campaign_id']++, 'kind'=>$kind, 'target'=>$target,
            'title'=>$title, 'subject'=>$subject, 'message'=>$message, 'action_url'=>$actionUrl, 'action_label'=>$actionLabel,
            'include_link'=>$includeLink, 'action_url_rewritten'=>$actionUrlRewritten,
            'created_by'=>(string)($admin['username'] ?? 'admin'), 'created_at'=>date('Y-m-d H:i:s'), 'updated_at'=>date('Y-m-d H:i:s'),
            'status'=>'queued', 'recipients'=>$recipients,
        ];
        $data['campaigns'][]=$campaign;
        if (count($data['campaigns']) > 100) $data['campaigns'] = array_slice($data['campaigns'], -100);
        return $campaign;
    });
}

function emailBroadcastFind(int $id): ?array {
    foreach (emailNotifyRead()['campaigns'] as $c) if ((int)($c['id'] ?? 0) === $id) return $c;
    return null;
}

function emailBroadcastSummary(array $c): array {
    $counts=['pending'=>0,'queued'=>0,'sending'=>0,'sent'=>0,'failed'=>0];
    $recipientSummary=[];
    foreach ((array)($c['recipients'] ?? []) as $r) {
        $s=(string)($r['status'] ?? 'pending');
        if (!isset($counts[$s])) $counts[$s]=0;
        $counts[$s]++;
        $recipientSummary[]=[
            'email'=>emailNotifyMaskEmail((string)($r['email'] ?? '')),
            'status'=>$s,
            'error'=>(string)($r['error'] ?? ''),
            'sent_at'=>(string)($r['sent_at'] ?? ''),
            'transport'=>(string)($r['transport'] ?? ''),
            'message_id'=>(string)($r['message_id'] ?? ''),
            'smtp_response'=>emailNotifyTextSlice((string)($r['smtp_response'] ?? ''), 0, 220),
        ];
    }
    $total=array_sum($counts);
    return [
        'id'=>(int)($c['id'] ?? 0),'kind'=>(string)($c['kind'] ?? ''),'target'=>(string)($c['target'] ?? ''),
        'title'=>(string)($c['title'] ?? ''),'subject'=>(string)($c['subject'] ?? ''),'message'=>(string)($c['message'] ?? ''),
        'action_url'=>(string)($c['action_url'] ?? ''),'action_label'=>(string)($c['action_label'] ?? ''),
        'include_link'=>!empty($c['include_link']), 'action_url_rewritten'=>!empty($c['action_url_rewritten']),
        'created_by'=>(string)($c['created_by'] ?? ''),'created_at'=>(string)($c['created_at'] ?? ''),'updated_at'=>(string)($c['updated_at'] ?? ''),
        'status'=>(string)($c['status'] ?? 'queued'),'counts'=>$counts,'total'=>$total,'recipients'=>$recipientSummary,
        'pause_reason'=>(string)($c['pause_reason'] ?? ''), 'pause_message'=>(string)($c['pause_message'] ?? ''),
        'paused_at'=>(string)($c['paused_at'] ?? ''),
    ];
}

function emailBroadcastSnapshot(): array {
    $rows=array_map('emailBroadcastSummary', emailNotifyRead()['campaigns']);
    usort($rows, fn($a,$b)=>(int)$b['id'] <=> (int)$a['id']);
    return array_slice($rows, 0, 30);
}

function emailBroadcastProcess(int $campaignId, int $batchSize=5): array {
    $batchSize=max(1,min(10,$batchSize));
    $indexes=[]; $campaign=null;
    emailNotifyMutate(function (&$data) use ($campaignId,$batchSize,&$indexes,&$campaign) {
        foreach ($data['campaigns'] as &$c) {
            if ((int)($c['id'] ?? 0) !== $campaignId) continue;

            // Jika Gmail sudah memberi sinyal limit harian, jangan terus menembak SMTP.
            // Antrean hanya dilanjutkan setelah admin menekan Proses Antrean / Coba Lagi.
            if (($c['status'] ?? '') === 'paused_quota') {
                $campaign=$c;
                break;
            }

            foreach ($c['recipients'] as $i=>&$r) {
                if (($r['status'] ?? '') === 'sending') {
                    $processingAt = strtotime((string)($r['processing_at'] ?? '')) ?: 0;
                    if ($processingAt === 0 || $processingAt < time()-300) { $r['status']='pending'; $r['processing_at']=''; }
                }
                if (count($indexes) >= $batchSize) continue;
                if (($r['status'] ?? 'pending') !== 'pending') continue;
                $r['status']='sending'; $r['processing_at']=date('Y-m-d H:i:s'); $indexes[]=$i;
            }
            unset($r);
            if ($indexes) {
                $c['status']='sending';
                $c['pause_reason']=''; $c['pause_message']=''; $c['paused_at']='';
                $c['updated_at']=date('Y-m-d H:i:s');
            }
            $campaign=$c;
            break;
        }
        unset($c);
    });
    if (!$campaign) throw new InvalidArgumentException('Broadcast tidak ditemukan.');

    // Campaign sedang dijeda karena kuota provider email habis.
    if (($campaign['status'] ?? '') === 'paused_quota') {
        return emailBroadcastSummary($campaign);
    }

    foreach ($indexes as $idx) {
        $recipient=$campaign['recipients'][$idx] ?? null;
        if (!$recipient) continue;
        $status='sent'; $error=''; $delivery=[]; $quotaHit=false;
        try {
            $html=emailNotifyTemplate((string)$campaign['title'], (string)$campaign['message'], [
                'kind'=>(string)$campaign['kind'],'action_url'=>(string)$campaign['action_url'],'action_label'=>(string)$campaign['action_label'],
                'include_link'=>!empty($campaign['include_link']),
            ]);
            $delivery = (array)financeSendMail((string)$recipient['email'], '['.APP_NAME.'] '.(string)$campaign['subject'], $html, (string)$campaign['message']);
            if (empty($delivery['accepted'])) throw new RuntimeException('Server email tidak mengonfirmasi penerimaan pesan.');
        } catch (Throwable $e) {
            if (function_exists('financeMailIsDailySendingLimitError') && financeMailIsDailySendingLimitError($e)) {
                $quotaHit=true;
                $status='queued';
                $error='Batas pengiriman harian Gmail tercapai. Email tetap disimpan di antrean dan belum dianggap gagal.';
            } else {
                $status='failed';
                $error=$e->getMessage();
            }
        }

        emailNotifyMutate(function (&$data) use ($campaignId,$idx,$status,$error,$delivery,$quotaHit) {
            foreach ($data['campaigns'] as &$c) {
                if ((int)($c['id'] ?? 0) !== $campaignId) continue;
                if (!isset($c['recipients'][$idx])) break;

                $c['recipients'][$idx]['status']=$status;
                $c['recipients'][$idx]['error']=$error;
                $c['recipients'][$idx]['processing_at']='';
                $c['recipients'][$idx]['sent_at']=$status==='sent'?date('Y-m-d H:i:s'):'';
                $c['recipients'][$idx]['transport']=(string)($delivery['transport'] ?? '');
                $c['recipients'][$idx]['message_id']=(string)($delivery['message_id'] ?? '');
                $c['recipients'][$idx]['smtp_response']=emailNotifyTextSlice((string)($delivery['smtp_response'] ?? ''), 0, 500);

                if ($quotaHit) {
                    // Jangan lanjutkan recipient lain dalam batch yang sudah ditandai sending.
                    // Semuanya dikembalikan ke antrean agar tidak hilang / tidak menjadi gagal palsu.
                    foreach ($c['recipients'] as &$r) {
                        if (in_array(($r['status'] ?? 'pending'), ['pending','sending'], true)) {
                            $r['status']='queued';
                            $r['processing_at']='';
                            if (trim((string)($r['error'] ?? '')) === '') {
                                $r['error']='Menunggu kuota pengiriman email tersedia kembali.';
                            }
                        }
                    }
                    unset($r);
                    $c['status']='paused_quota';
                    $c['pause_reason']='gmail_daily_limit';
                    $c['pause_message']='Kuota pengiriman harian Gmail tercapai. Penerima yang belum terkirim tetap aman di antrean.';
                    $c['paused_at']=date('Y-m-d H:i:s');
                    $c['updated_at']=date('Y-m-d H:i:s');
                    break;
                }

                $hasPending=false; $hasSending=false; $hasQueued=false; $hasFailed=false;
                foreach ($c['recipients'] as $r) {
                    $s=$r['status'] ?? 'pending';
                    if ($s==='pending') $hasPending=true;
                    elseif ($s==='queued') $hasQueued=true;
                    elseif ($s==='sending') $hasSending=true;
                    elseif ($s==='failed') $hasFailed=true;
                }
                $c['status']=($hasPending||$hasSending||$hasQueued)?'sending':($hasFailed?'completed_with_errors':'completed');
                $c['updated_at']=date('Y-m-d H:i:s');
                break;
            }
            unset($c);
        });

        if ($quotaHit) break;
    }
    $fresh=emailBroadcastFind($campaignId);
    return emailBroadcastSummary($fresh ?: $campaign);
}

function emailBroadcastSendTest(array $admin, array $input): array {
    if (!authIsSuperAdmin($admin)) throw new RuntimeException('Akses Super Admin diperlukan.');
    $email = emailNotifyUserEmail($admin, true);
    if ($email === '') throw new RuntimeException('Email Super Admin belum tersedia atau belum terverifikasi.');
    $kind = strtolower(trim((string)($input['kind'] ?? 'announcement')));
    if (!in_array($kind, ['android_update','announcement','maintenance','security'], true)) throw new InvalidArgumentException('Jenis broadcast tidak valid.');
    $title = trim((string)($input['title'] ?? 'Tes Notifikasi Email'));
    $subject = trim((string)($input['subject'] ?? $title));
    $message = trim((string)($input['message'] ?? 'Ini adalah email tes dari Catatan Keuangan.'));
    [$actionUrl,$rewritten] = emailNotifyNormalizeBroadcastActionUrl($kind, trim((string)($input['action_url'] ?? '')));
    $actionLabel = trim((string)($input['action_label'] ?? ''));
    $includeLink = !empty($input['include_link']);
    if ($title === '' || $subject === '' || $message === '') throw new InvalidArgumentException('Judul, subjek, dan isi pesan wajib diisi.');
    $html = emailNotifyTemplate($title, $message, ['kind'=>$kind,'action_url'=>$actionUrl,'action_label'=>$actionLabel,'include_link'=>$includeLink]);
    try {
        $delivery = (array)financeSendMail($email, '['.APP_NAME.'] [TES] '.$subject, $html, $message);
    } catch (Throwable $e) {
        if (function_exists('financeMailIsDailySendingLimitError') && financeMailIsDailySendingLimitError($e)) {
            throw new RuntimeException('Kuota pengiriman harian Gmail sedang habis. Email tes tidak dikirim. Broadcast biasa akan tetap disimpan di antrean sampai Anda memprosesnya kembali setelah kuota pulih.');
        }
        throw $e;
    }
    if (empty($delivery['accepted'])) throw new RuntimeException('Server email tidak mengonfirmasi penerimaan pesan tes.');
    return [
        'ok'=>true,
        'email'=>emailNotifyMaskEmail($email),
        'accepted'=>true,
        'transport'=>(string)($delivery['transport'] ?? ''),
        'message_id'=>(string)($delivery['message_id'] ?? ''),
        'smtp_response'=>emailNotifyTextSlice((string)($delivery['smtp_response'] ?? ''),0,220),
        'action_url'=>$actionUrl,
        'action_url_rewritten'=>$rewritten,
        'include_link'=>$includeLink,
    ];
}

function emailBroadcastRetryFailed(int $campaignId): array {
    return emailNotifyMutate(function (&$data) use ($campaignId) {
        foreach ($data['campaigns'] as &$c) {
            if ((int)($c['id'] ?? 0) !== $campaignId) continue;
            foreach ($c['recipients'] as &$r) {
                if (in_array(($r['status'] ?? ''), ['failed','sending','queued'], true)) {
                    $r['status']='pending';
                    $r['error']='';
                    $r['processing_at']='';
                }
            }
            unset($r);
            $c['status']='queued';
            $c['pause_reason']=''; $c['pause_message']=''; $c['paused_at']='';
            $c['updated_at']=date('Y-m-d H:i:s');
            return emailBroadcastSummary($c);
        }
        unset($c);
        throw new InvalidArgumentException('Broadcast tidak ditemukan.');
    });
}
