<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use app\services\AppPortalService;

final class AppPortalController extends Controller
{
    public $layout = false;
    public $enableCsrfValidation = true;

    // Launcher, manager, and SSO views are intentionally stored in views/site
    // because this project shares those templates with the main SiteController.
    public function getViewPath(): string
    {
        return Yii::getAlias('@app/views/site');
    }

    private function bootstrapPortal(bool $superAdmin = false): ?array
    {
        // Portal pages contain account-specific application access and must never be cached by a shared proxy.
        Yii::$app->response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        Yii::$app->response->headers->set('Pragma', 'no-cache');
        Yii::$app->response->headers->set('X-Content-Type-Options', 'nosniff');
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        require_once Yii::getAlias('@app/legacy/maintenance_helper.php');
        $user = authCurrentUser();
        if (!$user) {
            Yii::$app->response->redirect(['/site/index'])->send();
            Yii::$app->end();
            return null;
        }
        if (empty($user['pin_hash']) || empty($_SESSION['pin_verified'])) {
            Yii::$app->response->redirect(['/site/index'])->send();
            Yii::$app->end();
            return null;
        }
        $maintenance = maintenanceSnapshot();
        if (!empty($maintenance['is_active']) && !maintenanceCanAccess($user, $maintenance)) {
            throw new \yii\web\ServiceUnavailableHttpException('Aplikasi sedang dalam pemeliharaan.');
        }
        if ($superAdmin && !authIsSuperAdmin($user)) throw new ForbiddenHttpException('Manajemen Aplikasi hanya tersedia untuk Super Admin.');
        return $user;
    }

    public function actionIndex()
    {
        $user = $this->bootstrapPortal(false);
        if (!$user) return null;
        if (Yii::$app->request->get('reset') === '1') AppPortalService::clearPreferred((int)$user['id']);
        $premium = authHasPremiumAccess($user);
        $apps = array_values(array_filter(AppPortalService::catalog(), static fn($app) => !empty($app['enabled'])));
        foreach ($apps as &$app) {
            $app['locked'] = !empty($app['requires_premium']) && !$premium;
            $app['is_image_icon'] = preg_match('#^https://|^/#i', (string)($app['icon'] ?? '')) === 1;
        }
        unset($app);
        $flashError = (string)($_SESSION['app_portal_error'] ?? '');
        $flashNotice = (string)($_SESSION['app_portal_notice'] ?? '');
        unset($_SESSION['app_portal_error'], $_SESSION['app_portal_notice']);
        $assetVersion = '260-' . max(@filemtime(Yii::getAlias('@webroot/assets/style.css')) ?: 1, @filemtime(Yii::getAlias('@webroot/assets/app.js')) ?: 1);
        return $this->render('app-launcher', [
            'user' => $user, 'premium' => $premium, 'apps' => $apps,
            'isSuperAdmin' => authIsSuperAdmin($user), 'error' => $flashError, 'notice' => $flashNotice,
            'assetVersion' => $assetVersion,
        ]);
    }

    public function actionManage()
    {
        $user = $this->bootstrapPortal(true);
        if (!$user) return null;
        $flashError = (string)($_SESSION['app_portal_error'] ?? '');
        $flashNotice = (string)($_SESSION['app_portal_notice'] ?? '');
        unset($_SESSION['app_portal_error'], $_SESSION['app_portal_notice']);
        $apps = AppPortalService::catalog();
        foreach ($apps as &$app) $app['has_sso'] = !empty($app['sso_secret_enc']);
        unset($app);
        return $this->render('app-manager', [
            'user' => $user, 'apps' => $apps, 'error' => $flashError, 'notice' => $flashNotice,
            'assetVersion' => '260-' . max(@filemtime(Yii::getAlias('@webroot/assets/style.css')) ?: 1, @filemtime(Yii::getAlias('@webroot/assets/app.js')) ?: 1),
        ]);
    }

    public function actionSave()
    {
        $user = $this->bootstrapPortal(true);
        if (!$user) return null;
        if (!Yii::$app->request->isPost) throw new MethodNotAllowedHttpException('Gunakan POST untuk menyimpan aplikasi.');
        try {
            AppPortalService::save(Yii::$app->request->post());
            $_SESSION['app_portal_notice'] = 'Aplikasi berhasil disimpan.';
        } catch (\Throwable $e) {
            Yii::warning('[AppPortal] save failed: ' . $e->getMessage(), __METHOD__);
            $_SESSION['app_portal_error'] = $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException ? $e->getMessage() : 'Aplikasi belum dapat disimpan. Periksa kembali data yang dimasukkan.';
        }
        return $this->redirect(['/app-portal/manage']);
    }

    public function actionDelete(string $id = '')
    {
        $user = $this->bootstrapPortal(true);
        if (!$user) return null;
        if (!Yii::$app->request->isPost) throw new MethodNotAllowedHttpException('Gunakan POST untuk menghapus aplikasi.');
        try {
            AppPortalService::delete(trim($id));
            $_SESSION['app_portal_notice'] = 'Aplikasi berhasil dihapus.';
        } catch (\Throwable $e) {
            $_SESSION['app_portal_error'] = $e->getMessage();
        }
        return $this->redirect(['/app-portal/manage']);
    }

    public function actionLaunch(string $id = '')
    {
        $user = $this->bootstrapPortal(false);
        if (!$user) return null;
        $app = AppPortalService::find(trim($id));
        if (!$app || empty($app['enabled'])) throw new NotFoundHttpException('Aplikasi tidak tersedia.');
        if (!empty($app['requires_premium']) && !authHasPremiumAccess($user)) {
            $_SESSION['flash_notice'] = 'Aplikasi ini memerlukan akses Premium.';
            return $this->redirect(['/site/index', 'app' => 'main']);
        }
        AppPortalService::setPreferred((int)$user['id'], (string)$app['id']);
        if (($app['builtin'] ?? '') === 'main') return $this->redirect(['/site/index', 'app' => 'main']);
        $url = trim((string)($app['url'] ?? ''));
        if ($url === '') throw new NotFoundHttpException('Link aplikasi belum diatur.');
        if (!empty($app['sso_secret_enc'])) {
            try {
                $sso = AppPortalService::createSsoToken($app, $user);
                return $this->render('sso-post', [
                    'user' => $user, 'app' => $app, 'endpoint' => $sso['endpoint'], 'token' => $sso['token'],
                    'expiresIn' => (int)$sso['expires_in'],
                ]);
            } catch (\Throwable $e) {
                Yii::error('[AppPortal] SSO launch failed: ' . $e->getMessage(), __METHOD__);
                $_SESSION['app_portal_error'] = 'Aplikasi belum dapat dibuka melalui SSO. Periksa konfigurasi integrasi aplikasi.';
                return $this->redirect(['/app-portal/index']);
            }
        }
        return $this->redirect($url);
    }
}
