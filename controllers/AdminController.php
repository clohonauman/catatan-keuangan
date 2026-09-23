<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\UploadedFile;
use app\services\LegacyBackupService;
use app\services\LegacyMigrationService;
use yii\db\Query;

class AdminController extends Controller
{
    public $layout = false;

    private function requireSuperAdmin(): array
    {
        require_once Yii::getAlias('@app/legacy/auth.php');
        $u = authRequireUnlocked();
        if (!authIsSuperAdmin($u)) throw new ForbiddenHttpException('Khusus Super Admin.');
        return $u;
    }

    public function actionMigration()
    {
        $u = $this->requireSuperAdmin();
        // Kompatibilitas database yang sudah menjalankan migration v1 lama.
        LegacyMigrationService::ensureInfrastructure();
        $history = (new Query())->from('{{%migration_history}}')->orderBy(['id' => SORT_DESC])->limit(20)->all();
        return $this->render('migration', ['user' => $u, 'history' => $history, 'legacyPath' => Yii::$app->params['legacyDataPath']]);
    }

    public function actionBackupLegacy()
    {
        $this->requireSuperAdmin();
        $path = trim((string)Yii::$app->params['legacyDataPath']);
        if ($path === '' || !is_dir($path)) throw new \RuntimeException('LEGACY_DATA_PATH belum diatur atau folder tidak ditemukan.');
        $file = Yii::getAlias('@runtime/legacy-full-backup-' . date('Ymd-His') . '.zip');
        LegacyBackupService::create($path, $file);
        return Yii::$app->response->sendFile($file, basename($file), ['mimeType' => 'application/zip', 'inline' => false]);
    }

    public function actionImportPath()
    {
        $u = $this->requireSuperAdmin();
        $path = trim((string)Yii::$app->params['legacyDataPath']);
        if ($path === '' || !is_dir($path)) throw new \RuntimeException('LEGACY_DATA_PATH belum valid.');
        $summary = LegacyMigrationService::importDirectory($path, (int)$u['id'], true, 'LEGACY_DATA_PATH');
        authClearLocalSession();
        return $this->render('restore-complete', ['summary' => $summary]);
    }

    public function actionRestore()
    {
        $u = $this->requireSuperAdmin();
        $file = UploadedFile::getInstanceByName('backup');
        if (!$file) throw new \RuntimeException('Pilih backup ZIP legacy.');
        if (strtolower($file->extension) !== 'zip') throw new \RuntimeException('File restore harus ZIP.');
        if ($file->size > 512 * 1024 * 1024) throw new \RuntimeException('Backup terlalu besar. Maksimal 512 MB.');
        $tmp = Yii::getAlias('@runtime/upload-legacy-' . bin2hex(random_bytes(6)) . '.zip');
        if (!$file->saveAs($tmp)) throw new \RuntimeException('Upload backup gagal.');
        try {
            $summary = LegacyMigrationService::importZip($tmp, (int)$u['id'], true);
        } finally {
            @unlink($tmp);
        }
        authClearLocalSession();
        return $this->render('restore-complete', ['summary' => $summary]);
    }
}
