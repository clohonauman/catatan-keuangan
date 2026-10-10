<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\services\MidtransQrisService;

/** Public webhook + authenticated QR image proxy for Midtrans QRIS. */
class PaymentController extends Controller
{
    public $enableCsrfValidation = true;

    public function beforeAction($action)
    {
        // Midtrans server-to-server webhook cannot send the browser CSRF token.
        // This exemption is restricted to this single endpoint; signature checks are mandatory there.
        if ($action->id === 'midtrans-notification') $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
    }

    public function actionQrisImage(int $order_id = 0): string
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $user = \authRequireUnlocked();
        require_once Yii::getAlias('@app/legacy/subscription_helper.php');

        $order = \subscriptionFindOrder($order_id);
        if (!$order || (int)($order['user_id'] ?? 0) !== (int)($user['id'] ?? 0)
            || ($order['payment_method'] ?? '') !== 'midtrans_qris'
            || empty($order['midtrans_qr_url'])
            || ($order['status'] ?? '') !== 'waiting_payment') {
            Yii::$app->response->statusCode = 404;
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=utf-8');
            return 'QR pembayaran tidak ditemukan atau sudah tidak aktif.';
        }

        try {
            $image = MidtransQrisService::fetchQrImage((string)$order['midtrans_qr_url']);
        } catch (\Throwable $e) {
            Yii::warning('Unable to proxy QRIS image for order '.$order_id.': '.$e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 502;
            Yii::$app->response->format = Response::FORMAT_RAW;
            Yii::$app->response->headers->set('Content-Type', 'text/plain; charset=utf-8');
            return 'QRIS belum dapat dimuat. Muat ulang status pembayaran beberapa saat lagi.';
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->headers->set('Content-Type', 'image/png');
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        Yii::$app->response->headers->set('Pragma', 'no-cache');
        Yii::$app->response->headers->set('X-Content-Type-Options', 'nosniff');
        Yii::$app->response->headers->set('Content-Length', (string)strlen($image));
        return $image;
    }

    public function actionMidtransNotification(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store');
        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            return ['ok' => false, 'message' => 'POST required'];
        }

        $payload = json_decode(Yii::$app->request->getRawBody(), true);
        if (!is_array($payload) || !MidtransQrisService::verifyNotificationSignature($payload)) {
            Yii::$app->response->statusCode = 403;
            Yii::warning('Midtrans notification rejected: invalid signature or payload.', __METHOD__);
            return ['ok' => false, 'message' => 'Invalid notification signature'];
        }

        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        require_once Yii::getAlias('@app/legacy/subscription_helper.php');

        $providerOrderId = trim((string)($payload['order_id'] ?? ''));
        $order = \subscriptionFindOrderByMidtransOrderId($providerOrderId);
        if (!$order || ($order['payment_method'] ?? '') !== 'midtrans_qris') {
            Yii::$app->response->statusCode = 404;
            Yii::warning('Midtrans notification references an unknown application order.', __METHOD__);
            return ['ok' => false, 'message' => 'Order not found'];
        }

        $grossAmount = number_format((float)($payload['gross_amount'] ?? -1), 2, '.', '');
        $expectedAmount = number_format((float)($order['amount'] ?? -2), 2, '.', '');
        if ($grossAmount !== $expectedAmount) {
            Yii::$app->response->statusCode = 422;
            Yii::error('Midtrans amount mismatch for internal order '.(int)$order['id'].'.', __METHOD__);
            return ['ok' => false, 'message' => 'Amount mismatch'];
        }

        $providerStatus = strtolower(trim((string)($payload['transaction_status'] ?? '')));
        $statusCode = (string)($payload['status_code'] ?? '');
        $fraudStatus = strtolower(trim((string)($payload['fraud_status'] ?? '')));
        $isSettled = $providerStatus === 'settlement'
            && $statusCode === '200'
            && ($fraudStatus === '' || $fraudStatus === 'accept');
        $isExpired = $providerStatus === 'expire';
        $isFailed = in_array($providerStatus, ['deny', 'cancel', 'failure'], true);

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $shouldNotify = false;
        $updated = null;
        try {
            // Yii2 Query does not expose a portable forUpdate() method; issue a
            // parameterized SELECT ... FOR UPDATE on the active MySQL transaction.
            $row = $db->createCommand(
                'SELECT * FROM {{%subscription_order}} WHERE id = :id FOR UPDATE',
                [':id' => (int)$order['id']]
            )->queryOne();
            if (!$row) throw new \RuntimeException('Local subscription order no longer exists.');
            $local = json_decode((string)($row['order_json'] ?? ''), true);
            if (!is_array($local)) throw new \RuntimeException('Local subscription order payload is invalid.');
            $local['id'] = (int)$row['id'];
            $local['user_id'] = (int)$row['user_id'];
            $local['invoice_no'] = (string)$row['invoice_no'];
            $local['status'] = (string)$row['status'];
            if (($local['payment_method'] ?? '') !== 'midtrans_qris'
                || !hash_equals((string)($local['midtrans_order_id'] ?? ''), $providerOrderId)) {
                throw new \RuntimeException('Local payment order did not match the notification.');
            }
            $lockedAmount = number_format((float)($local['amount'] ?? -2), 2, '.', '');
            if ($grossAmount !== $lockedAmount
                || (isset($payload['currency']) && strtoupper((string)$payload['currency']) !== 'IDR')
                || (isset($payload['payment_type']) && strtolower((string)$payload['payment_type']) !== 'qris')) {
                throw new \RuntimeException('Currency, payment type, or amount did not match the locked local order.');
            }

            $local['midtrans_transaction_id'] = (string)($payload['transaction_id'] ?? $local['midtrans_transaction_id'] ?? '');
            $local['midtrans_transaction_status'] = $providerStatus;
            $local['midtrans_last_notification_at'] = date('Y-m-d H:i:s');
            $local['midtrans_settlement_time'] = (string)($payload['settlement_time'] ?? $local['midtrans_settlement_time'] ?? '');
            $local['updated_at'] = date('Y-m-d H:i:s');

            if ($isSettled) {
                // The order row is locked in the same DB transaction as entitlement update.
                // A repeated webhook therefore cannot activate the same invoice twice.
                if (($local['status'] ?? '') !== 'approved' || ($local['activation_status'] ?? '') !== 'complete') {
                    $activatedUser = \subscriptionActivateUserPremium($local, 'midtrans');
                    $expiry = (string)($activatedUser['plan_expires_at'] ?? '');
                    $local['status'] = 'approved';
                    $local['activation_status'] = 'complete';
                    $local['verified_at'] = (string)($payload['settlement_time'] ?? date('Y-m-d H:i:s'));
                    $local['verified_by'] = 'midtrans';
                    $local['premium_expires_at'] = $expiry;
                    $local['rejection_reason'] = '';
                }
                $claimedAt = strtotime((string)($local['payment_notification_claimed_at'] ?? '')) ?: 0;
                $sent = !empty($local['payment_notifications_sent']);
                if (!$sent && (time() - $claimedAt) > 120) {
                    $local['payment_notification_claimed_at'] = date('Y-m-d H:i:s');
                    $shouldNotify = true;
                }
            } elseif (($local['status'] ?? '') !== 'approved') {
                if ($isExpired) $local['status'] = 'expired';
                elseif ($isFailed) $local['status'] = 'failed';
                else $local['status'] = 'waiting_payment';
            }

            $local['midtrans_amount'] = $grossAmount;
            $local['midtrans_payment_type'] = (string)($payload['payment_type'] ?? 'qris');
            $db->createCommand()->update('{{%subscription_order}}', [
                'status' => (string)($local['status'] ?? $row['status']),
                'order_json' => json_encode($local, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int)$row['id']])->execute();
            $updated = $local;
            $transaction->commit();
        } catch (\Throwable $e) {
            if ($transaction->isActive) $transaction->rollBack();
            Yii::error('Midtrans webhook processing failed: '.$e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return ['ok' => false, 'message' => 'Notification processing failed'];
        }

        if ($shouldNotify && is_array($updated)) {
            $userId = (int)$updated['user_id'];
            $expires = (string)($updated['premium_expires_at'] ?? '');
            $expiryText = $expires === '' ? 'berlaku permanen' : 'aktif sampai '.$expires;
            try {
                \addChatForUser($userId, 'assistant', '✅ Pembayaran QRIS berhasil diverifikasi otomatis. Akun Anda sekarang Premium ('.($updated['plan_label'] ?? 'Premium').') dan '.$expiryText.'.');
                $invoiceChat = "🧾 INVOICE PREMIUM LUNAS\nInvoice: ".($updated['invoice_no'] ?? '-')."\nPaket: ".($updated['plan_label'] ?? 'Premium')."\nTotal: ".\subscriptionFormatRupiah((int)($updated['amount'] ?? 0))."\nMetode: QRIS Midtrans\nStatus: LUNAS";
                \addChatForUser($userId, 'assistant', $invoiceChat);
            } catch (\Throwable $e) {
                Yii::warning('Payment chat notification failed: '.$e->getMessage(), __METHOD__);
            }
            try {
                \userNotificationCreate($userId, 'premium', 'Pembayaran Premium Berhasil', 'Pembayaran untuk invoice '.($updated['invoice_no'] ?? '').' telah terverifikasi otomatis. Premium '.$expiryText.'.', [], 'premium_midtrans_paid:'.(int)$updated['id']);
            } catch (\Throwable $e) { Yii::warning('Payment app notification failed: '.$e->getMessage(), __METHOD__); }
            try {
                \emailNotifyAutomatic($userId, 'premium_midtrans_paid_'.(int)$updated['id'], 'premium', 'Pembayaran Premium Berhasil', 'Pembayaran QRIS untuk invoice '.($updated['invoice_no'] ?? '').' telah terverifikasi otomatis. Premium '.$expiryText.'.', ['action_url'=>(rtrim((string)(Yii::$app->params['appUrl'] ?? ''), '/').'/'), 'action_label'=>'Buka Catatan Keuangan']);
            } catch (\Throwable $e) { Yii::warning('Payment email notification failed: '.$e->getMessage(), __METHOD__); }

            try {
                $json = json_encode(array_merge($updated, ['payment_notifications_sent' => true]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $db->createCommand()->update('{{%subscription_order}}', ['order_json' => $json, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int)$updated['id']])->execute();
            } catch (\Throwable $e) { Yii::warning('Unable to record payment notification dispatch: '.$e->getMessage(), __METHOD__); }
        }

        return ['ok' => true];
    }
}
