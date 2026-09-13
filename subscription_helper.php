<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/admin_notification_helper.php';
require_once __DIR__.'/image_storage_helper.php';

define('SUBSCRIPTION_FILE', __DIR__.'/data/subscriptions.json');
define('PAYMENT_PROOF_DIR', __DIR__.'/data/payment_proofs');

function subscriptionDefaultPlans(): array {
    return [
        'monthly'=>[
            'key'=>'monthly',
            'label'=>'Bulanan',
            'amount'=>50000,
            'monthly_equivalent'=>50000,
            'months'=>1,
            'permanent'=>false,
            'description'=>'Rp50.000 / bulan',
        ],
        'yearly'=>[
            'key'=>'yearly',
            'label'=>'Tahunan',
            'amount'=>360000,
            'monthly_equivalent'=>30000,
            'months'=>12,
            'permanent'=>false,
            'description'=>'Rp30.000 / bulan · ditagih Rp360.000 / tahun',
        ],
        'lifetime'=>[
            'key'=>'lifetime',
            'label'=>'Permanen',
            'amount'=>600000,
            'monthly_equivalent'=>0,
            'months'=>0,
            'permanent'=>true,
            'description'=>'Rp600.000 · sekali beli',
        ],
    ];
}

function subscriptionDefaultBanks(): array {
    return [
        [
            'id'=>'seabank',
            'name'=>'SeaBank',
            'account_number'=>'',
            'account_name'=>'',
            'enabled'=>true,
            'sort'=>10,
        ],
        [
            'id'=>'bca',
            'name'=>'BCA',
            'account_number'=>'',
            'account_name'=>'',
            'enabled'=>true,
            'sort'=>20,
        ],
        [
            'id'=>'bri',
            'name'=>'BRI',
            'account_number'=>'',
            'account_name'=>'',
            'enabled'=>true,
            'sort'=>30,
        ],
    ];
}

function subscriptionDefaultData(): array {
    return [
        'plans'=>subscriptionDefaultPlans(),
        'banks'=>subscriptionDefaultBanks(),
        'coupons'=>[],
        'orders'=>[],
        'meta'=>[
            'next_order_id'=>1,
            'next_coupon_id'=>1,
            'revision'=>0,
        ],
    ];
}

function subscriptionEnsureData(): void {
    $dir = dirname(SUBSCRIPTION_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir(PAYMENT_PROOF_DIR)) @mkdir(PAYMENT_PROOF_DIR, 0775, true);
    if (!file_exists(SUBSCRIPTION_FILE)) {
        @file_put_contents(
            SUBSCRIPTION_FILE,
            json_encode(subscriptionDefaultData(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}

function subscriptionNormalizeData(array $data): array {
    $defaults = subscriptionDefaultData();
    if (!isset($data['plans']) || !is_array($data['plans'])) $data['plans'] = [];
    // Harga/paket inti dipertahankan dari aplikasi agar invoice konsisten.
    $data['plans'] = array_replace($defaults['plans'], $data['plans']);
    if (!isset($data['banks']) || !is_array($data['banks'])) $data['banks'] = $defaults['banks'];
    if (!isset($data['coupons']) || !is_array($data['coupons'])) $data['coupons'] = [];
    if (!isset($data['orders']) || !is_array($data['orders'])) $data['orders'] = [];
    if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = [];
    $data['meta']['next_order_id'] = max(1, (int)($data['meta']['next_order_id'] ?? 1));
    $data['meta']['next_coupon_id'] = max(1, (int)($data['meta']['next_coupon_id'] ?? 1));
    $data['meta']['revision'] = max(0, (int)($data['meta']['revision'] ?? 0));

    $maxId = 0;
    foreach ($data['orders'] as $order) $maxId = max($maxId, (int)($order['id'] ?? 0));
    $data['meta']['next_order_id'] = max($data['meta']['next_order_id'], $maxId + 1);

    $maxCouponId = 0;
    foreach ($data['coupons'] as $coupon) $maxCouponId = max($maxCouponId, (int)($coupon['id'] ?? 0));
    $data['meta']['next_coupon_id'] = max($data['meta']['next_coupon_id'], $maxCouponId + 1);

    return $data;
}

function subscriptionReadData(): array {
    subscriptionEnsureData();
    $fp = @fopen(SUBSCRIPTION_FILE, 'r');
    if (!$fp) return subscriptionDefaultData();
    @flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode((string)$raw, true);
    return subscriptionNormalizeData(is_array($data) ? $data : subscriptionDefaultData());
}

function subscriptionMutateData(callable $fn) {
    subscriptionEnsureData();
    $fp = @fopen(SUBSCRIPTION_FILE, 'c+');
    if (!$fp) throw new RuntimeException('Tidak dapat membuka data berlangganan.');
    if (!@flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new RuntimeException('Data berlangganan sedang digunakan. Coba lagi.');
    }
    rewind($fp);
    $raw = stream_get_contents($fp);
    $decoded = json_decode((string)$raw, true);
    $data = subscriptionNormalizeData(is_array($decoded) ? $decoded : subscriptionDefaultData());
    $result = $fn($data);
    $data['meta']['revision'] = (int)($data['meta']['revision'] ?? 0) + 1;
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $result;
}

function subscriptionPlans(): array {
    return subscriptionReadData()['plans'];
}

function subscriptionBanks(bool $enabledOnly=false): array {
    $banks = array_values(subscriptionReadData()['banks']);
    usort($banks, fn($a,$b) => ((int)($a['sort']??0) <=> (int)($b['sort']??0)) ?: strcasecmp((string)($a['name']??''), (string)($b['name']??'')));
    if ($enabledOnly) $banks = array_values(array_filter($banks, fn($b)=>!empty($b['enabled'])));
    return $banks;
}

function subscriptionBankById(string $id): ?array {
    foreach (subscriptionBanks(false) as $bank) {
        if ((string)($bank['id'] ?? '') === $id) return $bank;
    }
    return null;
}

function subscriptionSafeBankId(string $name): string {
    $id = strtolower(trim($name));
    $id = preg_replace('/[^a-z0-9]+/i', '-', $id);
    $id = trim((string)$id, '-');
    if ($id === '') $id = 'bank';
    return substr($id, 0, 40);
}

function subscriptionNormalizeCouponCode(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9_-]+/', '', $code);
    return substr((string)$code, 0, 32);
}

function subscriptionCoupons(): array {
    $coupons = array_values(subscriptionReadData()['coupons']);
    usort($coupons, fn($a,$b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
    return $coupons;
}

function subscriptionCouponById(int $id): ?array {
    foreach (subscriptionCoupons() as $coupon) {
        if ((int)($coupon['id'] ?? 0) === $id) return $coupon;
    }
    return null;
}

function subscriptionCouponByCode(string $code): ?array {
    $code = subscriptionNormalizeCouponCode($code);
    if ($code === '') return null;
    foreach (subscriptionCoupons() as $coupon) {
        if (subscriptionNormalizeCouponCode((string)($coupon['code'] ?? '')) === $code) return $coupon;
    }
    return null;
}

function subscriptionCouponExpired(array $coupon): bool {
    $expiresAt = trim((string)($coupon['expires_at'] ?? ''));
    if ($expiresAt === '') return false;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) return true;
    return $expiresAt < date('Y-m-d');
}

function subscriptionCouponLabel(array $coupon): string {
    $type = (string)($coupon['discount_type'] ?? '');
    $value = (int)($coupon['discount_value'] ?? 0);
    if ($type === 'percent') return $value.'%';
    return subscriptionFormatRupiah($value);
}

function subscriptionCalculateDiscount(int $baseAmount, array $coupon): array {
    $baseAmount = max(0, $baseAmount);
    $type = (string)($coupon['discount_type'] ?? '');
    $value = max(0, (int)($coupon['discount_value'] ?? 0));

    if ($type === 'percent') {
        $value = min(100, $value);
        $discount = (int)round($baseAmount * ($value / 100));
    } elseif ($type === 'fixed') {
        $discount = min($baseAmount, $value);
    } else {
        throw new InvalidArgumentException('Jenis diskon kupon tidak valid.');
    }

    $discount = min($baseAmount, max(0, $discount));
    return [
        'base_amount'=>$baseAmount,
        'discount_amount'=>$discount,
        'final_amount'=>max(0, $baseAmount - $discount),
    ];
}

function subscriptionValidateCoupon(string $code, string $planKey): array {
    $code = subscriptionNormalizeCouponCode($code);
    if ($code === '') throw new InvalidArgumentException('Masukkan kode kupon.');

    $plans = subscriptionPlans();
    if (!isset($plans[$planKey])) throw new InvalidArgumentException('Paket berlangganan tidak valid.');
    $coupon = subscriptionCouponByCode($code);
    if (!$coupon) throw new InvalidArgumentException('Kode kupon tidak ditemukan.');
    if (empty($coupon['enabled'])) throw new InvalidArgumentException('Kupon sedang tidak aktif.');
    if (subscriptionCouponExpired($coupon)) throw new InvalidArgumentException('Kupon sudah kedaluwarsa.');

    $calc = subscriptionCalculateDiscount((int)$plans[$planKey]['amount'], $coupon);
    return [
        'id'=>(int)$coupon['id'],
        'code'=>(string)$coupon['code'],
        'discount_type'=>(string)$coupon['discount_type'],
        'discount_value'=>(int)$coupon['discount_value'],
        'discount_label'=>subscriptionCouponLabel($coupon),
        'expires_at'=>(string)($coupon['expires_at'] ?? ''),
        'base_amount'=>$calc['base_amount'],
        'discount_amount'=>$calc['discount_amount'],
        'final_amount'=>$calc['final_amount'],
    ];
}

function subscriptionFindOrder(int $orderId): ?array {
    foreach (subscriptionReadData()['orders'] as $order) {
        if ((int)($order['id'] ?? 0) === $orderId) return $order;
    }
    return null;
}

function subscriptionLatestOrderForUser(int $userId): ?array {
    $found = null;
    foreach (subscriptionReadData()['orders'] as $order) {
        if ((int)($order['user_id'] ?? 0) !== $userId) continue;
        if ($found === null || (int)$order['id'] > (int)$found['id']) $found = $order;
    }
    return $found;
}

function subscriptionOpenOrderForUser(int $userId): ?array {
    $openStatuses = ['waiting_payment','pending_verification'];
    $found = null;
    foreach (subscriptionReadData()['orders'] as $order) {
        if ((int)($order['user_id'] ?? 0) !== $userId) continue;
        if (!in_array((string)($order['status'] ?? ''), $openStatuses, true)) continue;
        if ($found === null || (int)$order['id'] > (int)$found['id']) $found = $order;
    }
    return $found;
}

function subscriptionInvoiceNo(int $id): string {
    return 'INV-'.date('Ymd').'-'.str_pad((string)$id, 6, '0', STR_PAD_LEFT);
}

function subscriptionFormatRupiah(int $amount): string {
    return 'Rp'.number_format($amount, 0, ',', '.');
}

function subscriptionTextLength(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function subscriptionTextSlice(string $value, int $start, int $length): string {
    return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
}

function subscriptionCreateInvoice(array $user, array $input): array {
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) throw new InvalidArgumentException('Akun tidak ditemukan.');

    $existing = subscriptionOpenOrderForUser($userId);
    if ($existing) {
        throw new InvalidArgumentException('Masih ada invoice yang belum selesai: '.($existing['invoice_no'] ?? '').'. Selesaikan atau tunggu verifikasi pembayaran tersebut.');
    }

    $planKey = strtolower(trim((string)($input['plan_key'] ?? '')));
    $plans = subscriptionPlans();
    if (!isset($plans[$planKey])) throw new InvalidArgumentException('Paket berlangganan tidak valid.');
    $plan = $plans[$planKey];

    $bankId = strtolower(trim((string)($input['bank_id'] ?? '')));
    $bank = subscriptionBankById($bankId);
    if (!$bank || empty($bank['enabled'])) throw new InvalidArgumentException('Bank pembayaran tidak tersedia.');
    if (trim((string)($bank['account_number'] ?? '')) === '' || trim((string)($bank['account_name'] ?? '')) === '') {
        throw new InvalidArgumentException('Rekening '.$bank['name'].' belum dikonfigurasi oleh admin. Pilih bank lain atau hubungi admin.');
    }

    $senderName = trim((string)($input['sender_name'] ?? ''));
    $senderAccount = preg_replace('/\s+/', '', trim((string)($input['sender_account_number'] ?? '')));
    if (subscriptionTextLength($senderName) < 2 || subscriptionTextLength($senderName) > 100) throw new InvalidArgumentException('Nama pengirim wajib diisi.');
    if (!preg_match('/^[0-9A-Za-z.-]{4,40}$/', (string)$senderAccount)) throw new InvalidArgumentException('Nomor rekening pengirim tidak valid.');

    $baseAmount = (int)$plan['amount'];
    $couponCode = subscriptionNormalizeCouponCode((string)($input['coupon_code'] ?? ''));
    $couponSnapshot = null;
    $discountAmount = 0;
    $finalAmount = $baseAmount;
    if ($couponCode !== '') {
        $validatedCoupon = subscriptionValidateCoupon($couponCode, $planKey);
        $couponSnapshot = [
            'id'=>(int)$validatedCoupon['id'],
            'code'=>(string)$validatedCoupon['code'],
            'discount_type'=>(string)$validatedCoupon['discount_type'],
            'discount_value'=>(int)$validatedCoupon['discount_value'],
            'discount_label'=>(string)$validatedCoupon['discount_label'],
            'expires_at'=>(string)$validatedCoupon['expires_at'],
        ];
        $discountAmount = (int)$validatedCoupon['discount_amount'];
        $finalAmount = (int)$validatedCoupon['final_amount'];
    }

    $created = subscriptionMutateData(function (&$data) use ($user, $userId, $planKey, $plan, $bank, $senderName, $senderAccount, $baseAmount, $couponSnapshot, $discountAmount, $finalAmount) {
        $id = (int)$data['meta']['next_order_id']++;
        $order = [
            'id'=>$id,
            'invoice_no'=>subscriptionInvoiceNo($id),
            'user_id'=>$userId,
            'username'=>(string)($user['username'] ?? ''),
            'plan_key'=>$planKey,
            'plan_label'=>(string)$plan['label'],
            'plan_months'=>(int)$plan['months'],
            'plan_permanent'=>!empty($plan['permanent']),
            'base_amount'=>$baseAmount,
            'coupon'=>$couponSnapshot,
            'discount_amount'=>$discountAmount,
            'amount'=>$finalAmount,
            'bank_id'=>(string)$bank['id'],
            'bank_name'=>(string)$bank['name'],
            'bank_account_number'=>(string)$bank['account_number'],
            'bank_account_name'=>(string)$bank['account_name'],
            'sender_name'=>$senderName,
            'sender_account_number'=>$senderAccount,
            'status'=>$finalAmount <= 0 ? 'pending_verification' : 'waiting_payment',
            'proof'=>null,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>date('Y-m-d H:i:s'),
            'verified_at'=>'',
            'verified_by'=>'',
            'rejection_reason'=>'',
        ];
        $data['orders'][] = $order;
        return $order;
    });

    if ($finalAmount <= 0) {
        addChatForUser($userId, 'assistant', 'Kupon berhasil digunakan dan total invoice menjadi Rp0. Invoice '.$created['invoice_no'].' sedang menunggu verifikasi admin untuk mengaktifkan Premium.');
    }

    // Beri tahu Super Admin segera setelah invoice Premium dibuat.
    // Kegagalan email tidak menggagalkan invoice karena helper menangani error secara non-fatal.
    adminNotifySubscriptionEvent($created, 'invoice_created');
    return $created;
}

function subscriptionAllowedProofMime(string $path): string {
    $mime = '';
    if (function_exists('finfo_open')) {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $mime = (string)@finfo_file($f, $path);
            @finfo_close($f);
        }
    }
    if ($mime === '' && function_exists('getimagesize')) {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info['mime'])) $mime = (string)$info['mime'];
    }
    return strtolower($mime);
}

function subscriptionUploadProof(array $user, int $orderId, array $file): array {
    $userId = (int)($user['id'] ?? 0);
    $order = subscriptionFindOrder($orderId);
    if (!$order || (int)($order['user_id'] ?? 0) !== $userId) throw new InvalidArgumentException('Invoice tidak ditemukan.');
    if (!in_array((string)($order['status'] ?? ''), ['waiting_payment','pending_verification'], true)) {
        throw new InvalidArgumentException('Invoice ini tidak dapat menerima bukti pembayaran lagi.');
    }

    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Pilih gambar bukti pembayaran.');
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 8*1024*1024) throw new InvalidArgumentException('Ukuran gambar maksimal 8 MB.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli') throw new InvalidArgumentException('Upload bukti pembayaran tidak valid.');

    $mime = subscriptionAllowedProofMime($tmp);
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($extMap[$mime])) throw new InvalidArgumentException('Bukti bayar harus berupa gambar JPG, PNG, atau WEBP.');

    subscriptionEnsureData();
    $baseName = 'proof_'.$orderId.'_'.bin2hex(random_bytes(8));
    $stored = imageStorageSaveUpload($tmp, PAYMENT_PROOF_DIR, $baseName, [
        'max_side'=>1600,
        'quality'=>78,
        'threshold_bytes'=>750 * 1024,
        'allow_cli_copy'=>true,
    ]);
    $filename = $stored['file'];
    $storedMime = $stored['mime'];
    $storedSize = (int)$stored['size'];
    $originalSize = (int)$stored['original_size'];

    $previousStatus = (string)($order['status'] ?? '');
    $updated = subscriptionMutateData(function (&$data) use ($orderId, $filename, $storedMime, $storedSize, $originalSize, $stored) {
        foreach ($data['orders'] as &$row) {
            if ((int)($row['id'] ?? 0) !== $orderId) continue;
            $oldFile = basename((string)($row['proof']['file'] ?? ''));
            if ($oldFile !== '' && $oldFile !== $filename) @unlink(PAYMENT_PROOF_DIR.'/'.$oldFile);
            $row['proof'] = [
                'file'=>$filename,
                'mime'=>$storedMime,
                'size'=>$storedSize,
                'original_size'=>$originalSize,
                'optimized'=>!empty($stored['optimized']),
                'uploaded_at'=>date('Y-m-d H:i:s'),
            ];
            $row['status'] = 'pending_verification';
            $row['updated_at'] = date('Y-m-d H:i:s');
            return $row;
        }
        unset($row);
        throw new InvalidArgumentException('Invoice tidak ditemukan.');
    });

    // Pesan ke pengguna cukup dibuat ketika invoice pertama kali masuk tahap verifikasi.
    if ($previousStatus !== 'pending_verification') {
        addChatForUser($userId, 'assistant', 'Pembayaran sedang diverifikasi silahkan mengecek secara berkala. Invoice '.$updated['invoice_no'].' sedang diperiksa oleh admin.');
    }

    // PENTING: setiap upload bukti pembayaran yang berhasil harus memicu email ke Super Admin.
    // Sebelumnya email hanya dikirim saat status berubah ke pending_verification. Akibatnya,
    // upload ulang pada invoice yang sudah pending_verification tidak pernah mengirim email.
    adminNotifySubscriptionEvent($updated, 'proof_uploaded');

    return $updated;
}

function subscriptionPlanExpiryForApproval(array $order, array $user): string {
    if (!empty($order['plan_permanent'])) return '';
    $months = max(1, (int)($order['plan_months'] ?? 1));

    // Akun permanen tidak diturunkan menjadi paket berjangka.
    $existingPlan = authPlan($user);
    if (!empty($existingPlan['active']) && ($existingPlan['expires_at'] ?? '') === '') return '';

    $base = new DateTimeImmutable('today');
    $currentExpiry = trim((string)($user['plan_expires_at'] ?? ''));
    if ($currentExpiry !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $currentExpiry)) {
        $existing = new DateTimeImmutable($currentExpiry);
        if ($existing > $base) $base = $existing;
    }
    return $base->modify('+'.$months.' months')->format('Y-m-d');
}

function subscriptionActivateUserPremium(array $order, string $adminUsername): array {
    $userId = (int)$order['user_id'];
    $user = authFindUserById($userId);
    if (!$user) throw new InvalidArgumentException('User invoice tidak ditemukan.');
    $expires = subscriptionPlanExpiryForApproval($order, $user);

    return authMutateData(function (&$data) use ($userId, $order, $expires, $adminUsername) {
        foreach ($data['users'] as &$u) {
            if ((int)($u['id'] ?? 0) !== $userId) continue;
            $u['plan'] = 'premium';
            $u['plan_expires_at'] = $expires;
            $u['premium_type'] = (string)($order['plan_key'] ?? '');
            $u['premium_last_invoice'] = (string)($order['invoice_no'] ?? '');
            $u['premium_updated_at'] = date('Y-m-d H:i:s');
            $u['premium_updated_by'] = $adminUsername;
            return $u;
        }
        unset($u);
        throw new InvalidArgumentException('User tidak ditemukan.');
    });
}

function subscriptionApproveOrder(int $orderId, array $admin): array {
    if (!authIsSuperAdmin($admin)) throw new RuntimeException('Akses Super Admin diperlukan.');
    $order = subscriptionFindOrder($orderId);
    if (!$order) throw new InvalidArgumentException('Invoice tidak ditemukan.');
    if ((string)($order['status'] ?? '') === 'approved') return $order;
    if ((string)($order['status'] ?? '') !== 'pending_verification') throw new InvalidArgumentException('Invoice belum menunggu verifikasi.');
    if ((int)($order['amount'] ?? 0) > 0 && empty($order['proof']['file'])) throw new InvalidArgumentException('Bukti pembayaran belum tersedia.');

    $updatedUser = subscriptionActivateUserPremium($order, (string)($admin['username'] ?? 'admin'));
    $expires = (string)($updatedUser['plan_expires_at'] ?? '');

    $updated = subscriptionMutateData(function (&$data) use ($orderId, $admin, $expires) {
        foreach ($data['orders'] as &$row) {
            if ((int)($row['id'] ?? 0) !== $orderId) continue;
            $row['status'] = 'approved';
            $row['updated_at'] = date('Y-m-d H:i:s');
            $row['verified_at'] = date('Y-m-d H:i:s');
            $row['verified_by'] = (string)($admin['username'] ?? 'admin');
            $row['premium_expires_at'] = $expires;
            $row['rejection_reason'] = '';
            return $row;
        }
        unset($row);
        throw new InvalidArgumentException('Invoice tidak ditemukan.');
    });

    $expiryText = $expires === '' ? 'berlaku permanen' : 'aktif sampai '.$expires;
    addChatForUser((int)$updated['user_id'], 'assistant', '✅ Pembayaran berhasil diverifikasi. Akun Anda sekarang Premium ('.$updated['plan_label'].') dan '.$expiryText.'.');
    $invoiceChat = "🧾 INVOICE LUNAS\nInvoice: {$updated['invoice_no']}\nPaket: {$updated['plan_label']}";
    if (!empty($updated['coupon']['code'])) {
        $invoiceChat .= "\nHarga Awal: ".subscriptionFormatRupiah((int)($updated['base_amount'] ?? $updated['amount']));
        $invoiceChat .= "\nKupon: ".$updated['coupon']['code']." (-".subscriptionFormatRupiah((int)($updated['discount_amount'] ?? 0)).")";
    }
    $invoiceChat .= "\nTotal: ".subscriptionFormatRupiah((int)$updated['amount'])."\nBank: {$updated['bank_name']}\nStatus: LUNAS\nTerima kasih telah menggunakan Catatan Keuangan Premium.";
    addChatForUser((int)$updated['user_id'], 'assistant', $invoiceChat);

    return $updated;
}

function subscriptionRejectOrder(int $orderId, array $admin, string $reason=''): array {
    if (!authIsSuperAdmin($admin)) throw new RuntimeException('Akses Super Admin diperlukan.');
    $order = subscriptionFindOrder($orderId);
    if (!$order) throw new InvalidArgumentException('Invoice tidak ditemukan.');
    if ((string)($order['status'] ?? '') !== 'pending_verification') throw new InvalidArgumentException('Invoice tidak sedang menunggu verifikasi.');
    $reason = trim($reason);
    if ($reason === '') $reason = 'Bukti bayar tidak valid.';
    if (subscriptionTextLength($reason) > 300) $reason = subscriptionTextSlice($reason, 0, 300);

    $updated = subscriptionMutateData(function (&$data) use ($orderId, $admin, $reason) {
        foreach ($data['orders'] as &$row) {
            if ((int)($row['id'] ?? 0) !== $orderId) continue;
            $row['status'] = 'rejected';
            $row['updated_at'] = date('Y-m-d H:i:s');
            $row['verified_at'] = date('Y-m-d H:i:s');
            $row['verified_by'] = (string)($admin['username'] ?? 'admin');
            $row['rejection_reason'] = $reason;
            return $row;
        }
        unset($row);
        throw new InvalidArgumentException('Invoice tidak ditemukan.');
    });

    addChatForUser((int)$updated['user_id'], 'assistant', 'Pembelian ditolak karena bukti bayar tidak valid. Jika ingin mengajukan pertanyaan seputar pembelian tersebut silahkan hubungi admin melalui Chat WA Only +6282259866048 (Senin - Sabtu 09.00 WITA - 17.00 WITA).');
    return $updated;
}

function subscriptionStatusLabel(string $status): string {
    return [
        'waiting_payment'=>'Menunggu Pembayaran',
        'pending_verification'=>'Menunggu Verifikasi',
        'approved'=>'Lunas / Disetujui',
        'rejected'=>'Ditolak',
    ][$status] ?? ucfirst(str_replace('_',' ', $status));
}

function subscriptionOrderForClient(?array $order): ?array {
    if (!$order) return null;
    $out = $order;
    $out['status_label'] = subscriptionStatusLabel((string)($order['status'] ?? ''));
    $out['proof_url'] = !empty($order['proof']['file']) ? 'ajax/payment_proof.php?order_id='.(int)$order['id'] : '';
    return $out;
}

function subscriptionUserSnapshot(array $user): array {
    $plans = array_values(subscriptionPlans());
    $banks = subscriptionBanks(true);
    foreach ($banks as &$bank) {
        $bank['configured'] = trim((string)($bank['account_number'] ?? '')) !== '' && trim((string)($bank['account_name'] ?? '')) !== '';
    }
    unset($bank);
    return [
        'plans'=>$plans,
        'banks'=>$banks,
        'latest_order'=>subscriptionOrderForClient(subscriptionLatestOrderForUser((int)$user['id'])),
        'open_order'=>subscriptionOrderForClient(subscriptionOpenOrderForUser((int)$user['id'])),
        'account'=>[
            'role'=>authUserRole($user),
            'plan'=>authPlan($user),
            'premium_type'=>(string)($user['premium_type'] ?? ''),
            'premium_last_invoice'=>(string)($user['premium_last_invoice'] ?? ''),
        ],
    ];
}

function subscriptionAdminSnapshot(): array {
    $d = subscriptionReadData();
    $orders = array_values($d['orders']);
    usort($orders, fn($a,$b)=>(int)($b['id']??0) <=> (int)($a['id']??0));
    $rows = array_map('subscriptionOrderForClient', $orders);
    return [
        'plans'=>array_values($d['plans']),
        'banks'=>subscriptionBanks(false),
        'coupons'=>subscriptionCoupons(),
        'orders'=>$rows,
        'stats'=>[
            'waiting_payment'=>count(array_filter($orders, fn($x)=>($x['status']??'')==='waiting_payment')),
            'pending_verification'=>count(array_filter($orders, fn($x)=>($x['status']??'')==='pending_verification')),
            'approved'=>count(array_filter($orders, fn($x)=>($x['status']??'')==='approved')),
            'rejected'=>count(array_filter($orders, fn($x)=>($x['status']??'')==='rejected')),
            'revenue'=>array_reduce($orders, fn($sum,$x)=>$sum+(($x['status']??'')==='approved'?(int)($x['amount']??0):0), 0),
        ],
    ];
}

function subscriptionAdminSaveCoupon(array $input): array {
    if (!authIsSuperAdmin()) throw new RuntimeException('Akses Super Admin diperlukan.');

    $id = max(0, (int)($input['id'] ?? 0));
    $code = subscriptionNormalizeCouponCode((string)($input['code'] ?? ''));
    $type = strtolower(trim((string)($input['discount_type'] ?? 'percent')));
    $value = (int)($input['discount_value'] ?? 0);
    $expiresAt = trim((string)($input['expires_at'] ?? ''));
    $enabled = !empty($input['enabled']);

    if ($code === '' || strlen($code) < 3) throw new InvalidArgumentException('Kode kupon minimal 3 karakter.');
    if (!in_array($type, ['percent','fixed'], true)) throw new InvalidArgumentException('Jenis diskon kupon tidak valid.');
    if ($type === 'percent' && ($value < 1 || $value > 100)) throw new InvalidArgumentException('Diskon persen harus 1 sampai 100.');
    if ($type === 'fixed' && $value < 1) throw new InvalidArgumentException('Potongan rupiah harus lebih dari Rp0.');
    if ($expiresAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) throw new InvalidArgumentException('Tanggal kedaluwarsa kupon tidak valid.');

    return subscriptionMutateData(function (&$data) use ($id, $code, $type, $value, $expiresAt, $enabled) {
        foreach ($data['coupons'] as $row) {
            if ((int)($row['id'] ?? 0) !== $id && subscriptionNormalizeCouponCode((string)($row['code'] ?? '')) === $code) {
                throw new InvalidArgumentException('Kode kupon sudah digunakan.');
            }
        }

        if ($id > 0) {
            foreach ($data['coupons'] as &$coupon) {
                if ((int)($coupon['id'] ?? 0) !== $id) continue;
                $coupon['code'] = $code;
                $coupon['discount_type'] = $type;
                $coupon['discount_value'] = $value;
                $coupon['expires_at'] = $expiresAt;
                $coupon['enabled'] = $enabled;
                $coupon['updated_at'] = date('Y-m-d H:i:s');
                return $coupon;
            }
            unset($coupon);
            throw new InvalidArgumentException('Kupon tidak ditemukan.');
        }

        $new = [
            'id'=>(int)$data['meta']['next_coupon_id']++,
            'code'=>$code,
            'discount_type'=>$type,
            'discount_value'=>$value,
            'expires_at'=>$expiresAt,
            'enabled'=>$enabled,
            'created_at'=>date('Y-m-d H:i:s'),
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        $data['coupons'][] = $new;
        return $new;
    });
}

function subscriptionAdminDeleteCoupon(int $id): bool {
    if (!authIsSuperAdmin()) throw new RuntimeException('Akses Super Admin diperlukan.');
    if ($id <= 0) throw new InvalidArgumentException('Kupon tidak valid.');
    subscriptionMutateData(function (&$data) use ($id) {
        $before = count($data['coupons']);
        $data['coupons'] = array_values(array_filter($data['coupons'], fn($c)=>(int)($c['id'] ?? 0) !== $id));
        if (count($data['coupons']) === $before) throw new InvalidArgumentException('Kupon tidak ditemukan.');
        return true;
    });
    return true;
}

function subscriptionAdminSaveBank(array $input): array {
    if (!authIsSuperAdmin()) throw new RuntimeException('Akses Super Admin diperlukan.');
    $rawId = trim((string)($input['id'] ?? ''));
    $name = trim((string)($input['name'] ?? ''));
    $accountNumber = preg_replace('/\s+/', '', trim((string)($input['account_number'] ?? '')));
    $accountName = trim((string)($input['account_name'] ?? ''));
    $enabled = !empty($input['enabled']);
    $sort = max(0, min(9999, (int)($input['sort'] ?? 100)));
    if ($name === '' || subscriptionTextLength($name) > 50) throw new InvalidArgumentException('Nama bank wajib diisi.');
    if ($accountNumber !== '' && !preg_match('/^[0-9A-Za-z.-]{4,40}$/', (string)$accountNumber)) throw new InvalidArgumentException('Nomor rekening bank tidak valid.');
    if ($accountName !== '' && subscriptionTextLength($accountName) > 100) throw new InvalidArgumentException('Nama pemilik rekening terlalu panjang.');

    return subscriptionMutateData(function (&$data) use ($rawId, $name, $accountNumber, $accountName, $enabled, $sort) {
        $id = $rawId !== '' ? subscriptionSafeBankId($rawId) : subscriptionSafeBankId($name);
        $existingIds = array_map(fn($b)=>(string)($b['id']??''), $data['banks']);
        if ($rawId === '') {
            $base = $id; $n = 2;
            while (in_array($id, $existingIds, true)) $id = $base.'-'.$n++;
        }
        foreach ($data['banks'] as &$bank) {
            if ((string)($bank['id'] ?? '') !== $id) continue;
            $bank['name'] = $name;
            $bank['account_number'] = $accountNumber;
            $bank['account_name'] = $accountName;
            $bank['enabled'] = $enabled;
            $bank['sort'] = $sort;
            return $bank;
        }
        unset($bank);
        $new = [
            'id'=>$id,
            'name'=>$name,
            'account_number'=>$accountNumber,
            'account_name'=>$accountName,
            'enabled'=>$enabled,
            'sort'=>$sort,
        ];
        $data['banks'][] = $new;
        return $new;
    });
}

function subscriptionAdminDeleteBank(string $id): bool {
    if (!authIsSuperAdmin()) throw new RuntimeException('Akses Super Admin diperlukan.');
    $id = subscriptionSafeBankId($id);
    subscriptionMutateData(function (&$data) use ($id) {
        $data['banks'] = array_values(array_filter($data['banks'], fn($b)=>(string)($b['id']??'') !== $id));
        return true;
    });
    return true;
}
