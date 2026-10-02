<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class PlanningController extends Controller
{
    public $layout = false;
    public $enableCsrfValidation = true;

    private function bootstrapPlanning(): array
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        require_once Yii::getAlias('@app/legacy/db.php');
        require_once Yii::getAlias('@app/legacy/finance_features.php');
        require_once Yii::getAlias('@app/legacy/planning_helper.php');

        $user = authCurrentUser();
        if (!$user) {
            Yii::$app->response->redirect(['/site/index']);
            Yii::$app->end();
        }
        if (empty($_SESSION['pin_verified'])) {
            Yii::$app->response->redirect(['/site/index']);
            Yii::$app->end();
        }
        if (!authHasPremiumAccess($user)) {
            return [$user, false];
        }
        return [$user, true];
    }

    public function actionIndex()
    {
        [$user, $premium] = $this->bootstrapPlanning();
        if (!$premium) {
            return $this->render('premium-required', [
                'user' => $user,
                'appUrl' => rtrim((string)(Yii::$app->params['appUrl'] ?: Yii::$app->request->hostInfo), '/'),
            ]);
        }

        if (setting('preferred_app','') !== 'planning') setSetting('preferred_app', 'planning');
        $month = planningNormalizeMonth((string)Yii::$app->request->get('month', ''));
        $snapshot = planningSnapshot($month);
        $assetVersion = max(
            @filemtime(Yii::getAlias('@webroot/perencanaan-pengeluaran/web/assets/planning.css')) ?: 1,
            @filemtime(Yii::getAlias('@webroot/perencanaan-pengeluaran/web/assets/planning.js')) ?: 1
        );
        return $this->render('index', compact('user', 'snapshot', 'assetVersion'));
    }

    public function actionApi()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        [$user, $premium] = $this->bootstrapPlanning();
        if (!$premium) {
            Yii::$app->response->statusCode = 403;
            return ['ok'=>false,'error'=>'Penyusunan Rencana Belanja tersedia untuk akun Premium.','premium_required'=>true];
        }

        $input = json_decode((string)Yii::$app->request->rawBody, true);
        if (!is_array($input)) $input = [];
        $action = (string)($input['action'] ?? 'snapshot');
        try {
            if ($action === 'snapshot') {
                $month = planningNormalizeMonth((string)($input['month'] ?? ''));
                return ['ok'=>true,'snapshot'=>planningSnapshot($month)];
            }
            if ($action === 'save') {
                $month = planningNormalizeMonth((string)($input['month'] ?? ''));
                $result = planningSavePlan($month, $input);
                return ['ok'=>true,'snapshot'=>$result,'message'=>'Rencana belanja berhasil disimpan.'];
            }
            if ($action === 'clear') {
                $month = planningNormalizeMonth((string)($input['month'] ?? ''));
                financeMutate(function (&$d) use ($month) {
                    if (isset($d['settings']['planning_plans'][$month])) unset($d['settings']['planning_plans'][$month]);
                });
                return ['ok'=>true,'snapshot'=>planningSnapshot($month),'message'=>'Rencana bulan tersebut dikembalikan ke saran awal.'];
            }
            throw new \InvalidArgumentException('Aksi perencanaan tidak dikenal.');
        } catch (\InvalidArgumentException $e) {
            Yii::$app->response->statusCode = 422;
            return ['ok'=>false,'error'=>$e->getMessage()];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 500;
            Yii::error('[Planning] '.$e->getMessage(), __METHOD__);
            return ['ok'=>false,'error'=>'Rencana belanja tidak dapat diproses.'];
        }
    }
}
