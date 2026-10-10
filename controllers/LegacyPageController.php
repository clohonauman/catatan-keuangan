<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class LegacyPageController extends Controller
{
    public $layout = false;

    public function beforeAction($action)
    {
        if (in_array($action->id, ['privacy-policy', 'delete-account'], true)) {
            // Halaman legacy menggunakan token form internalnya sendiri.
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * Render file legacy sebagai satu response HTML utuh.
     * Jangan "return require ..." karena return value include (biasanya 1)
     * dapat bercampur dengan response Yii dan membuat output parsial/tidak stabil.
     */
    private function renderLegacyHtml(string $alias): string
    {
        Yii::$app->response->format = Response::FORMAT_HTML;
        $file = Yii::getAlias($alias);
        if (!is_file($file)) {
            throw new \yii\web\NotFoundHttpException('Halaman tidak ditemukan.');
        }

        ob_start();
        try {
            require $file;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    public function actionPrivacyPolicy(): string
    {
        return $this->renderLegacyHtml('@app/legacy/privacy-policy.php');
    }

    public function actionDeleteAccount(): string
    {
        return $this->renderLegacyHtml('@app/legacy/delete-account.php');
    }

    public function actionAndroidDownload(): string
    {
        return $this->renderLegacyHtml('@app/legacy/android-download.php');
    }
}
