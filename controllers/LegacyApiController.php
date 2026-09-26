<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class LegacyApiController extends Controller
{
    public $enableCsrfValidation = true;

    /** @var string[] Endpoint yang secara kontrak mengembalikan JSON. */
    private const JSON_ENDPOINTS = [
        'admin','admin_notifications','backup','chats','dashboard','delete_message',
        'delete_transaction','devices','edit_transaction','email_notifications',
        'features','finance','learning','notifications','realtime','settings','subscription',
        'transactions',
    ];

    /** @var string[] Endpoint file/binary yang tidak boleh dinormalisasi sebagai JSON. */
    private const RAW_ENDPOINTS = ['export','payment_proof','photo','report'];

    private static function configureJsonResponse(Response $yiiResponse): void
    {
        $yiiResponse->format = Response::FORMAT_RAW;
        $yiiResponse->headers->set('Content-Type', 'application/json; charset=UTF-8');
        $yiiResponse->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $yiiResponse->headers->set('Pragma', 'no-cache');
        $yiiResponse->headers->set('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Cari satu object/array JSON lengkap di dalam output legacy.
     *
     * Selain trailing output historis (contoh "}1"), fungsi ini juga dapat
     * memulihkan payload bila PHP warning/notice/BOM terlanjur muncul sebelum JSON.
     * Konten di luar JSON dicatat ke error_log dan tidak dikirim ke browser.
     */
    private static function normalizeJsonBuffer(string $buffer): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $buffer) ?? $buffer;
        $clean = trim($raw);
        if ($clean === '') {
            return '';
        }

        json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $clean;
        }

        $len = strlen($clean);
        for ($start = 0; $start < $len; $start++) {
            $first = $clean[$start];
            if ($first !== '{' && $first !== '[') {
                continue;
            }

            $stack = [];
            $inString = false;
            $escaped = false;

            for ($i = $start; $i < $len; $i++) {
                $ch = $clean[$i];

                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                        continue;
                    }
                    if ($ch === '\\') {
                        $escaped = true;
                        continue;
                    }
                    if ($ch === '"') {
                        $inString = false;
                    }
                    continue;
                }

                if ($ch === '"') {
                    $inString = true;
                    continue;
                }
                if ($ch === '{' || $ch === '[') {
                    $stack[] = $ch;
                    continue;
                }
                if ($ch !== '}' && $ch !== ']') {
                    continue;
                }

                if (!$stack) {
                    break;
                }
                $open = array_pop($stack);
                if (($open === '{' && $ch !== '}') || ($open === '[' && $ch !== ']')) {
                    break;
                }

                if (!$stack) {
                    $candidate = substr($clean, $start, $i - $start + 1);
                    json_decode($candidate, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        break;
                    }

                    $prefix = trim(substr($clean, 0, $start));
                    $tail = trim(substr($clean, $i + 1));
                    if ($prefix !== '' || $tail !== '') {
                        $noise = trim($prefix . ($prefix !== '' && $tail !== '' ? ' | ' : '') . $tail);
                        error_log('[CatatanKeuangan] Output non-JSON endpoint legacy dibuang: ' . substr($noise, 0, 500));
                    }
                    return $candidate;
                }
            }
        }

        return $clean;
    }

    /**
     * Menjalankan file legacy pada scope terisolasi.
     *
     * Penting: beberapa endpoint legacy menggunakan variabel generik seperti
     * $response, $file, $input, dst. Tanpa scope ini variabel tersebut dapat
     * menimpa object Response milik controller (bug yang sebelumnya terjadi pada
     * delete_message.php: setStatusCode() terpanggil pada array).
     */
    private static function includeLegacyIsolated(string $legacyFile): void
    {
        (static function (string $__legacyFile): void {
            require $__legacyFile;
        })($legacyFile);
    }

    private static function nativeHttpStatus(): int
    {
        $code = http_response_code();
        return is_int($code) && $code >= 100 && $code <= 599 ? $code : 200;
    }

    private static function endpointErrorPayload(string $name, \Throwable $e): string
    {
        $payload = [
            'ok' => false,
            'error' => 'Terjadi kesalahan internal pada endpoint ' . $name . '.',
        ];
        if (defined('YII_DEBUG') && YII_DEBUG) {
            $payload['detail'] = $e->getMessage();
        }
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ok":false,"error":"Kesalahan server."}';
    }

    /**
     * Kirim file privat sebagai body Yii Response, bukan echo/readfile langsung.
     * Ini menghindari body image tercampur output framework/legacy ketika endpoint
     * diakses melalui front controller Yii.
     */
    private static function privateFileResponse(string $path, string $mime, bool $download = false, string $downloadName = ''): Response
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \yii\web\NotFoundHttpException('File tidak ditemukan.');
        }

        $response = Yii::$app->getResponse();
        $response->format = Response::FORMAT_RAW;
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Length', (string)filesize($path));
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($download) {
            $name = $downloadName !== '' ? $downloadName : basename($path);
            $response->headers->set('Content-Disposition', 'attachment; filename="'.addcslashes($name, "\\\"").'"');
        } else {
            $response->headers->set('Content-Disposition', 'inline; filename="'.addcslashes(basename($path), "\\\"").'"');
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \yii\web\ServerErrorHttpException('File tidak dapat dibaca.');
        }
        $response->content = $content;
        return $response;
    }

    /** Endpoint foto nota/lampiran privat milik user aktif. */
    private function servePhoto(): Response
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $user = authRequireUnlocked();
        $file = basename((string)Yii::$app->request->get('f', ''));
        if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $file)) {
            throw new \yii\web\BadRequestHttpException('Nama file foto tidak valid.');
        }
        $path = rtrim((string)Yii::$app->params['privateStorage'], '/\\')
            . '/receipts/user_' . (int)$user['id'] . '/' . $file;
        if (!is_file($path)) {
            throw new \yii\web\NotFoundHttpException('Foto lampiran tidak ditemukan di private storage.');
        }
        $info = @getimagesize($path);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
            throw new \yii\web\UnsupportedMediaTypeHttpException('Format foto tidak didukung.');
        }
        return self::privateFileResponse($path, $mime, false);
    }

    /** Endpoint bukti pembayaran privat dengan pemeriksaan kepemilikan/order. */
    private function servePaymentProof(): Response
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $user = authRequireUnlocked();
        require_once Yii::getAlias('@app/legacy/subscription_helper.php');

        $orderId = (int)Yii::$app->request->get('order_id', 0);
        $order = subscriptionFindOrder($orderId);
        if (!$order) {
            throw new \yii\web\NotFoundHttpException('Bukti pembayaran tidak ditemukan.');
        }
        if (!authIsSuperAdmin($user) && (int)($order['user_id'] ?? 0) !== (int)$user['id']) {
            throw new \yii\web\ForbiddenHttpException('Akses ditolak.');
        }
        $file = basename((string)($order['proof']['file'] ?? ''));
        if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $file)) {
            throw new \yii\web\NotFoundHttpException('File bukti pembayaran tidak ditemukan.');
        }
        $path = rtrim((string)Yii::$app->params['privateStorage'], '/\\') . '/payment_proofs/' . $file;
        if (!is_file($path)) {
            throw new \yii\web\NotFoundHttpException('File bukti pembayaran tidak ditemukan di private storage.');
        }
        $info = @getimagesize($path);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
            $mime = (string)($order['proof']['mime'] ?? 'image/jpeg');
        }
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
            throw new \yii\web\UnsupportedMediaTypeHttpException('Format bukti pembayaran tidak didukung.');
        }
        return self::privateFileResponse($path, $mime, false);
    }

    private function runLegacy(string $name)
    {
        $allowed = array_merge(self::JSON_ENDPOINTS, self::RAW_ENDPOINTS);
        if (!in_array($name, $allowed, true)) {
            throw new \yii\web\NotFoundHttpException();
        }

        // Foto dan bukti pembayaran ditangani native oleh Yii agar body binary
        // tidak pernah tercampur output compatibility/HTML.
        if ($name === 'photo') return $this->servePhoto();
        if ($name === 'payment_proof') return $this->servePaymentProof();

        $legacyFile = Yii::getAlias('@app/legacy/ajax/' . $name . '.php');
        if (!is_file($legacyFile)) {
            throw new \yii\web\NotFoundHttpException('Endpoint legacy tidak ditemukan.');
        }

        // Nama sengaja spesifik dan file legacy tetap dijalankan pada closure terisolasi.
        // Dengan demikian variabel lokal endpoint tidak pernah dapat menimpa object ini.
        $yiiResponse = Yii::$app->getResponse();
        $yiiResponse->format = Response::FORMAT_RAW;

        $isJsonEndpoint = in_array($name, self::JSON_ENDPOINTS, true);
        $baseObLevel = ob_get_level();

        if ($isJsonEndpoint) {
            self::configureJsonResponse($yiiResponse);

            // Callback dibutuhkan untuk endpoint legacy yang masih memakai exit; pada
            // jalur itu controller tidak mendapat kesempatan memproses output lagi.
            ob_start(static function ($buffer): string {
                return self::normalizeJsonBuffer((string)$buffer);
            });
        }

        try {
            self::includeLegacyIsolated($legacyFile);
        } catch (\Throwable $e) {
            error_log('[CatatanKeuangan] Legacy endpoint ' . $name . ' gagal: ' . $e);

            if (!$isJsonEndpoint) {
                throw $e;
            }

            // Buang output parsial/warning dari endpoint sebelum mengirim JSON error.
            while (ob_get_level() > $baseObLevel) {
                @ob_end_clean();
            }
            self::configureJsonResponse($yiiResponse);
            $yiiResponse->setStatusCode(500);
            $yiiResponse->content = self::endpointErrorPayload($name, $e);
            return $yiiResponse;
        }

        $nativeStatus = self::nativeHttpStatus();
        $yiiResponse->setStatusCode($nativeStatus);

        if ($isJsonEndpoint) {
            // Endpoint yang mencapai titik ini tidak melakukan exit. Ambil output dari
            // buffer milik kita, normalisasi manual, lalu jadikan body resmi Yii.
            $rawOutput = '';
            if (ob_get_level() > $baseObLevel) {
                $rawOutput = (string)ob_get_clean();
            }
            // Safety jika ada buffer tak terduga dari kode legacy.
            while (ob_get_level() > $baseObLevel) {
                $rawOutput = (string)ob_get_clean() . $rawOutput;
            }

            $normalized = self::normalizeJsonBuffer($rawOutput);
            $decoded = json_decode($normalized, true);
            if ($normalized === '' || json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                error_log('[CatatanKeuangan] Endpoint ' . $name . ' tidak menghasilkan JSON valid. Output: ' . substr($rawOutput, 0, 800));
                $yiiResponse->setStatusCode(500);
                $yiiResponse->content = json_encode([
                    'ok' => false,
                    'error' => 'Endpoint ' . $name . ' tidak menghasilkan JSON valid.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return $yiiResponse;
            }

            $yiiResponse->content = $normalized;
            return $yiiResponse;
        }

        // Endpoint binary/file tetap memakai echo/readfile/header native dari kode
        // legacy. Scope terisolasi tetap mencegah collision variabel controller.
        return null;
    }

    public function actionAdmin(){ return $this->runLegacy('admin'); }
    public function actionAdminNotifications(){ return $this->runLegacy('admin_notifications'); }
    public function actionBackup(){ return $this->runLegacy('backup'); }
    public function actionDashboard(){ return $this->runLegacy('dashboard'); }
    public function actionDeleteMessage(){ return $this->runLegacy('delete_message'); }
    public function actionDeleteTransaction(){ return $this->runLegacy('delete_transaction'); }
    public function actionDevices(){ return $this->runLegacy('devices'); }
    public function actionEditTransaction(){ return $this->runLegacy('edit_transaction'); }
    public function actionEmailNotifications(){ return $this->runLegacy('email_notifications'); }
    public function actionExport(){ return $this->runLegacy('export'); }
    public function actionFeatures(){ return $this->runLegacy('features'); }
    public function actionFinance(){ return $this->runLegacy('finance'); }
    public function actionLearning(){ return $this->runLegacy('learning'); }
    public function actionNotifications(){ return $this->runLegacy('notifications'); }
    public function actionPaymentProof(){ return $this->runLegacy('payment_proof'); }
    public function actionPhoto(){ return $this->runLegacy('photo'); }
    public function actionRealtime(){ return $this->runLegacy('realtime'); }
    public function actionReport(){ return $this->runLegacy('report'); }
    public function actionSettings(){ return $this->runLegacy('settings'); }
    public function actionSubscription(){ return $this->runLegacy('subscription'); }
    public function actionTransactions(){ return $this->runLegacy('transactions'); }

    /** Central compatibility bridge untuk seluruh ajax/*.php lama. */
    public function actionBridge(string $name='')
    {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \yii\web\BadRequestHttpException('Endpoint legacy tidak valid.');
        }
        return $this->runLegacy($name);
    }
}
