<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Compatibility launch endpoint for the application hub.
 *
 * The current production link uses:
 *   /web/index.php?r=app-hub/launch&id=1
 *
 * App ID 1 is the built-in Catatan Keuangan application; ID 2 is the
 * built-in Perencanaan Keuangan application. This controller deliberately
 * does not accept arbitrary redirect URLs from query parameters.
 */
class AppHubController extends Controller
{
    public $layout = false;

    public function actionLaunch($id)
    {
        $appId = filter_var($id, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($appId === false) {
            throw new NotFoundHttpException('Aplikasi tidak ditemukan.');
        }

        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');

        $user = authCurrentUser();
        if (!$user) {
            // Never launch an app without the shared Catatan Keuangan session.
            return $this->redirect(['/site/index']);
        }

        Yii::$app->response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );

        // This route fixes the missing app-hub/launch endpoint for built-in
        // application IDs without trusting a user-supplied URL.
        if ((int)$appId === 1) {
            $preferredApp = 'main';
            $targetRoute = ['/site/index'];
        } elseif ((int)$appId === 2) {
            $preferredApp = 'planning';
            $targetRoute = ['/planning/index'];
        } else {
            throw new NotFoundHttpException('Aplikasi yang dipilih tidak tersedia.');
        }

        // Preference is non-critical: a setting-write failure should not keep
        // a signed-in user from opening the selected built-in app.
        try {
            setSetting('preferred_app', $preferredApp);
        } catch (\Throwable $e) {
            Yii::warning(
                '[AppHub] Gagal menyimpan aplikasi pilihan untuk user ID '
                . (int)($user['id'] ?? 0) . ': ' . $e->getMessage(),
                __METHOD__
            );
        }

        return $this->redirect($targetRoute);
    }
}
