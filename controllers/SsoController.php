<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

/** Server-to-server SSO endpoints. This controller intentionally has one action without CSRF. */
final class SsoController extends Controller
{
    public $layout = false;
    public $enableCsrfValidation = false;

    public function actionAuthorize()
    {
        $clientId = trim((string)Yii::$app->request->get('client_id', ''));
        $redirectUri = trim((string)Yii::$app->request->get('redirect_uri', ''));
        $state = trim((string)Yii::$app->request->get('state', ''));
        if ($clientId === '' || strlen($clientId) > 80 || $redirectUri === '' || strlen($redirectUri) > 1000 || !preg_match('/^[A-Za-z0-9._~-]{16,256}$/', $state)) {
            throw new \yii\web\BadRequestHttpException('Permintaan SSO tidak valid.');
        }
        require_once Yii::getAlias('@app/legacy/auth.php');
        $user = authCurrentUser();
        if (!$user || empty($_SESSION['pin_verified'])) {
            $_SESSION['pending_sso_authorize'] = ['client_id' => $clientId, 'redirect_uri' => $redirectUri, 'state' => $state, 'issued_at' => time()];
            return $this->redirect(['site/index']);
        }
        try {
            $callback = \app\services\AppHubService::authorizeBrowserRequest($clientId, $redirectUri, $state, $user);
            return $this->redirect($callback);
        } catch (\Throwable $e) {
            Yii::warning('[SSO authorize] ' . $e->getMessage(), __METHOD__);
            $_SESSION['flash_error'] = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                ? $e->getMessage()
                : 'SSO sementara tidak dapat diproses. Silakan coba lagi dari galeri aplikasi.';
            $_SESSION['force_app_list'] = true;
            return $this->redirect(['site/index', 'app' => 'list']);
        }
    }

    public function actionExchange()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        Yii::$app->response->headers->set('Pragma', 'no-cache');
        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;
            Yii::$app->response->headers->set('Allow', 'POST');
            return ['ok' => false, 'error' => 'Gunakan POST untuk menukar kode SSO.'];
        }

        $input = json_decode((string)Yii::$app->request->getRawBody(), true);
        if (!is_array($input)) $input = Yii::$app->request->post();
        try {
            $result = \app\services\AppHubService::exchangeCode(
                (string)($input['client_id'] ?? ''),
                (string)($input['client_secret'] ?? ''),
                (string)($input['code'] ?? ''),
                (string)($input['state'] ?? '')
            );
            return $result;
        } catch (\InvalidArgumentException $e) {
            Yii::$app->response->statusCode = 401;
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            Yii::error('[SSO exchange] ' . $e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return ['ok' => false, 'error' => 'Login SSO sementara tidak dapat diproses. Silakan mulai lagi dari portal.'];
        }
    }
}
