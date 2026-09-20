<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_helper.php';

define('ADMIN_NOTIFICATION_FILE', __DIR__ . '/data/admin_notifications.json');

function adminNotificationDefaultData(): array
{
    return ['notifications' => [], 'meta' => ['next_id' => 1]];
}

function adminNotificationEnsureData(): void
{
    $dir = dirname(ADMIN_NOTIFICATION_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!file_exists(ADMIN_NOTIFICATION_FILE)) {
        @file_put_contents(ADMIN_NOTIFICATION_FILE, json_encode(adminNotificationDefaultData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

function adminNotificationReadData(): array
{
    adminNotificationEnsureData();
    $raw = @file_get_contents(ADMIN_NOTIFICATION_FILE);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = adminNotificationDefaultData();
    if (!isset($data['notifications']) || !is_array($data['notifications'])) $data['notifications'] = [];
    if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = ['next_id' => 1];
    $max = 0;
    foreach ($data['notifications'] as $n) $max = max($max, (int)($n['id'] ?? 0));
    $data['meta']['next_id'] = max((int)($data['meta']['next_id'] ?? 1), $max + 1);
    return $data;
}

function adminNotificationMutateData(callable $fn)
{
    adminNotificationEnsureData();
    $fp = @fopen(ADMIN_NOTIFICATION_FILE, 'c+');
    if (!$fp) throw new RuntimeException('Tidak dapat membuka data notifikasi admin.');
    @flock($fp, LOCK_EX);
    rewind($fp);
    $raw = stream_get_contents($fp);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = adminNotificationDefaultData();
    if (!isset($data['notifications']) || !is_array($data['notifications'])) $data['notifications'] = [];
    if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = ['next_id' => 1];
    $max = 0;
    foreach ($data['notifications'] as $n) $max = max($max, (int)($n['id'] ?? 0));
    $data['meta']['next_id'] = max((int)($data['meta']['next_id'] ?? 1), $max + 1);
    $result = $fn($data);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function adminNotificationSuperAdmins(): array
{
    $users = authReadData()['users'] ?? [];
    return array_values(array_filter($users, fn($u) => authUserRole($u) === 'super_admin'));
}

function adminNotificationCreate(int $adminUserId, string $type, string $title, string $message, array $payload = []): array
{
    if ($adminUserId <= 0) throw new InvalidArgumentException('Admin tidak valid.');
    return adminNotificationMutateData(function (&$data) use ($adminUserId, $type, $title, $message, $payload) {
        $id = (int)$data['meta']['next_id']++;
        $row = [
            'id' => $id,
            'admin_user_id' => $adminUserId,
            'type' => trim($type),
            'title' => trim($title),
            'message' => trim($message),
            'payload' => $payload,
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => '',
        ];
        $data['notifications'][] = $row;
        if (count($data['notifications']) > 500) $data['notifications'] = array_slice($data['notifications'], -500);
        return $row;
    });
}

function adminNotificationListForUser(int $adminUserId, int $limit = 30): array
{
    $rows = array_values(array_filter(adminNotificationReadData()['notifications'], fn($n) => (int)($n['admin_user_id'] ?? 0) === $adminUserId));
    usort($rows, fn($a, $b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
    return array_slice($rows, 0, max(1, min(100, $limit)));
}

function adminNotificationUnreadCount(int $adminUserId): int
{
    $count = 0;
    foreach (adminNotificationReadData()['notifications'] as $n) {
        if ((int)($n['admin_user_id'] ?? 0) === $adminUserId && trim((string)($n['read_at'] ?? '')) === '') $count++;
    }
    return $count;
}

function adminNotificationMarkRead(int $adminUserId, array $ids = []): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($x) => $x > 0)));
    return adminNotificationMutateData(function (&$data) use ($adminUserId, $ids) {
        $changed = 0;
        foreach ($data['notifications'] as &$n) {
            if ((int)($n['admin_user_id'] ?? 0) !== $adminUserId) continue;
            if ($ids && !in_array((int)($n['id'] ?? 0), $ids, true)) continue;
            if (trim((string)($n['read_at'] ?? '')) !== '') continue;
            $n['read_at'] = date('Y-m-d H:i:s');
            $changed++;
        }
        unset($n);
        return $changed;
    });
}



function adminNotificationEmailLogPath(): string
{
    return __DIR__ . '/data/email_delivery_log.json';
}

function adminNotificationLogEmail(array $row): void
{
    $path = adminNotificationEmailLogPath();
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fp = @fopen($path, 'c+');
    if (!$fp) return;
    @flock($fp, LOCK_EX);
    rewind($fp);
    $raw = stream_get_contents($fp);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = ['emails' => []];
    if (!isset($data['emails']) || !is_array($data['emails'])) $data['emails'] = [];
    $row['created_at'] = date('Y-m-d H:i:s');
    $data['emails'][] = $row;
    if (count($data['emails']) > 200) $data['emails'] = array_slice($data['emails'], -200);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
}

function adminNotificationAppUrl(): string
{
    if (!empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        return ($https ? 'https://' : 'http://') . trim((string)$_SERVER['HTTP_HOST']) . '/';
    }
    return 'https://catatan-keuangan.cloud/';
}

function adminNotificationEmailHtml(string $title, string $intro, array $order): string
{
    // Sengaja tanpa link eksternal. Email OTP dari aplikasi terbukti sampai,
    // sedangkan link ke domain hosting dapat meningkatkan risiko filter spam/phishing Gmail.
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $invoice = $e($order['invoice_no'] ?? '-');
    $username = $e($order['username'] ?? '-');
    $plan = $e($order['plan_label'] ?? '-');
    $totalRaw = function_exists('subscriptionFormatRupiah') ? subscriptionFormatRupiah((int)($order['amount'] ?? 0)) : ('Rp' . number_format((int)($order['amount'] ?? 0), 0, ',', '.'));
    $total = $e($totalRaw);
    $bank = $e(trim((string)($order['bank_name'] ?? '-') . ' ' . (string)($order['bank_account_number'] ?? '')));
    $sender = $e((string)($order['sender_name'] ?? '-') . ' · ' . (string)($order['sender_account_number'] ?? '-'));
    $statusMap = [
        'waiting_payment' => 'Menunggu Pembayaran',
        'pending_verification' => 'Menunggu Verifikasi',
        'approved' => 'Lunas / Disetujui',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ];
    $statusRaw = (string)($order['status'] ?? '');
    $status = $e($statusMap[$statusRaw] ?? ($statusRaw !== '' ? $statusRaw : '-'));
    $paymentDate = $e((string)($order['proof']['uploaded_at'] ?? $order['updated_at'] ?? $order['created_at'] ?? '-'));
    $coupon = '';
    if (!empty($order['coupon']['code'])) {
        $discountRaw = function_exists('subscriptionFormatRupiah') ? subscriptionFormatRupiah((int)($order['discount_amount'] ?? 0)) : (string)($order['discount_amount'] ?? 0);
        $coupon = '<tr><td style="padding:8px 0;color:#667085">Kupon</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $e($order['coupon']['code']) . ' (-' . $e($discountRaw) . ')</td></tr>';
    }

    return '<!doctype html><html><body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#101828">'
        . '<div style="max-width:560px;margin:32px auto;padding:0 16px"><div style="background:#fff;border:1px solid #e4e7ec;border-radius:20px;padding:28px">'
        . '<div style="font-size:12px;font-weight:700;color:#175cd3;margin-bottom:8px">CATATAN KEUANGAN</div>'
        . '<h2 style="margin:0 0 10px;font-size:22px">' . $e($title) . '</h2>'
        . '<p style="margin:0 0 20px;color:#667085;font-size:14px;line-height:1.6">' . $e($intro) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
        . '<tr><td style="padding:8px 0;color:#667085">Invoice</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $invoice . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#667085">Pengguna</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $username . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#667085">Paket</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $plan . '</td></tr>'
        . $coupon
        . '<tr><td style="padding:8px 0;color:#667085">Total</td><td style="padding:8px 0;text-align:right;font-size:18px;font-weight:800;color:#175cd3">' . $total . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#667085">Bank tujuan</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $bank . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#667085">Pengirim</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $sender . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#667085">Status</td><td style="padding:8px 0;text-align:right;font-weight:800">' . $status . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#667085">Waktu pembayaran</td><td style="padding:8px 0;text-align:right;font-weight:700">' . $paymentDate . '</td></tr>'
        . '</table>'
        . '<p style="margin:20px 0 0;color:#667085;font-size:12px;line-height:1.6">Silakan buka aplikasi Catatan Keuangan secara langsung untuk memeriksa bukti pembayaran dan melakukan verifikasi.</p>'
        . '<p style="margin:10px 0 0;color:#98a2b3;font-size:11px;line-height:1.5">Notifikasi otomatis untuk akun Super Admin.</p>'
        . '</div></div></body></html>';
}

function adminNotifySubscriptionEvent(array $order, string $event): void
{
    $admins = adminNotificationSuperAdmins();
    if (!$admins) return;

    $invoice = (string)($order['invoice_no'] ?? '-');
    $username = (string)($order['username'] ?? '-');
    $plan = (string)($order['plan_label'] ?? '-');
    $amount = function_exists('subscriptionFormatRupiah') ? subscriptionFormatRupiah((int)($order['amount'] ?? 0)) : 'Rp' . number_format((int)($order['amount'] ?? 0), 0, ',', '.');

    if ($event === 'proof_uploaded') {
        $title = 'Bukti Bayar Premium Masuk';
        $message = $username . ' mengunggah bukti pembayaran ' . $invoice . ' · ' . $plan . ' · ' . $amount . '. Silakan verifikasi di menu Admin.';
        $emailSubject = '[Catatan Keuangan] Pembayaran masuk - ' . $invoice;
        $emailIntro = 'Pembayaran baru telah masuk. Detail invoice dan bukti pembayaran tersedia di bawah ini untuk segera diverifikasi.';
    } else {
        $title = 'Invoice Premium Baru';
        $message = $username . ' membuat ' . $invoice . ' · ' . $plan . ' · ' . $amount . '.';
        $message .= (int)($order['amount'] ?? 0) <= 0 ? ' Invoice Rp0 menunggu persetujuan admin.' : ' Menunggu pembayaran pengguna.';
        $emailSubject = '[Catatan Keuangan] Invoice Premium baru - ' . $invoice;
        $emailIntro = 'Ada pengguna yang membuat invoice berlangganan Premium baru.';
    }

    $payload = [
        'order_id' => (int)($order['id'] ?? 0),
        'invoice_no' => $invoice,
        'username' => $username,
        'plan_label' => $plan,
        'amount' => (int)($order['amount'] ?? 0),
        'status' => (string)($order['status'] ?? ''),
        'event' => $event,
    ];

    foreach ($admins as $admin) {
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) continue;
        try {
            adminNotificationCreate($adminId, 'premium_' . $event, $title, $message, $payload);
        } catch (Throwable $e) {
            error_log('Gagal membuat notifikasi aplikasi admin: ' . $e->getMessage());
        }
        try {
            addChatForUser($adminId, 'assistant', '🔔 ' . $title . "\n" . $message);
        } catch (Throwable $e) {
            error_log('Gagal menambahkan chat notifikasi admin: ' . $e->getMessage());
        }

        // Jalur notifikasi eksternal untuk pembelian Premium: EMAIL SAJA.
        // Dikirim ke alamat email yang tersimpan pada akun Super Admin.
        $email = authNormalizeEmail($admin['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $html = adminNotificationEmailHtml($title, $emailIntro, $order);
                financeSendMail($email, $emailSubject, $html, $title . "\n" . $message);
                adminNotificationLogEmail([
                    'event' => $event,
                    'order_id' => (int)($order['id'] ?? 0),
                    'invoice_no' => $invoice,
                    'admin_user_id' => $adminId,
                    'to' => $email,
                    'subject' => $emailSubject,
                    'status' => 'sent',
                    'error' => '',
                ]);
            } catch (Throwable $e) {
                // Kegagalan email tidak boleh membatalkan invoice/pembayaran pengguna.
                adminNotificationLogEmail([
                    'event' => $event,
                    'order_id' => (int)($order['id'] ?? 0),
                    'invoice_no' => $invoice,
                    'admin_user_id' => $adminId,
                    'to' => $email,
                    'subject' => $emailSubject,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                error_log('Gagal mengirim email notifikasi admin ke ' . $email . ': ' . $e->getMessage());
            }
        } else {
            error_log('Notifikasi Premium tidak dikirim: akun Super Admin ID ' . $adminId . ' belum memiliki email valid.');
        }
    }
}