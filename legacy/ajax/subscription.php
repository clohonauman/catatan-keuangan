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

$action = '';
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

    if ($action === 'create_qris_invoice') {
        $order = subscriptionCreateQrisInvoice($user, $input);
        echo json_encode([
            'ok'=>true,
            'message'=>'QRIS berhasil dibuat. Scan QR menggunakan aplikasi bank atau e-wallet; status akan diperbarui otomatis.',
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
    // Input/business validation errors remain actionable (invalid plan, coupon, or open invoice).
    subscriptionFail($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($action === 'create_qris_invoice') {
        $details = (string)$e->getMessage();
        $safePrefix = 'Pembayaran QRIS belum dapat disiapkan saat ini.';

        // The QRIS helper already logged the original provider response with an invoice reference.
        if (strpos($details, $safePrefix) === 0) {
            subscriptionFail($details, 503);
        }

        // Protect customers from PHP/provider/configuration errors before an invoice is created.
        $reference = subscriptionQrisSupportReference();
        Yii::error('QRIS checkout request failed [reference='.$reference.']: '.$details, __METHOD__);
        subscriptionFail(subscriptionQrisPublicFailureMessage($reference), 503);
    }
    subscriptionFail($e->getMessage(), 500);
}
