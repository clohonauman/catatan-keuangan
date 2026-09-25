<?php
require_once __DIR__.'/../auth.php';
$user = authRequireUnlocked();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__.'/../subscription_helper.php';

function subscriptionFail(string $message, int $code=400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['ok'=>true,'subscription'=>subscriptionUserSnapshot($user)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $multipart = stripos($contentType, 'multipart/form-data') !== false;
    if ($multipart) {
        $input = $_POST;
        $action = (string)($input['action'] ?? '');
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];
        $action = (string)($input['action'] ?? '');
    }

    if ($action === 'start_trial') {
        $confirmed = !empty($input['confirm_trial']);
        $trialUser = subscriptionStartTrial($user, $confirmed);
        echo json_encode([
            'ok'=>true,
            'message'=>'Free Trial Premium 7 hari berhasil diaktifkan.',
            'subscription'=>subscriptionUserSnapshot($trialUser),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'validate_coupon') {
        $planKey = strtolower(trim((string)($input['plan_key'] ?? '')));
        $coupon = subscriptionValidateCoupon((string)($input['coupon_code'] ?? ''), $planKey);
        echo json_encode([
            'ok'=>true,
            'message'=>'Kupon berhasil digunakan.',
            'coupon'=>$coupon,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'create_invoice') {
        $order = subscriptionCreateInvoice($user, $input);
        echo json_encode([
            'ok'=>true,
            'message'=>'Invoice berhasil dibuat.',
            'order'=>subscriptionOrderForClient($order),
            'subscription'=>subscriptionUserSnapshot(authCurrentUser()),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'upload_proof') {
        if (!$multipart) throw new InvalidArgumentException('Upload bukti bayar harus menggunakan form data.');
        if (!isset($_FILES['proof'])) throw new InvalidArgumentException('Pilih gambar bukti pembayaran.');
        $order = subscriptionUploadProof($user, (int)($input['order_id'] ?? 0), $_FILES['proof']);
        echo json_encode([
            'ok'=>true,
            'message'=>'Bukti bayar berhasil dikirim. Pembayaran sedang diverifikasi.',
            'order'=>subscriptionOrderForClient($order),
            'subscription'=>subscriptionUserSnapshot(authCurrentUser()),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new InvalidArgumentException('Aksi berlangganan tidak dikenali.');
} catch (InvalidArgumentException $e) {
    subscriptionFail($e->getMessage(), 422);
} catch (Throwable $e) {
    subscriptionFail($e->getMessage(), 500);
}
