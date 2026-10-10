<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\services\LegacyBackupService;
use app\services\LegacyMigrationService;

final class LegacyMigrationController extends Controller
{
    public function actionBackup(string $dataPath, string $output=''): int
    {
        $dataPath=rtrim($dataPath,"/\\");
        if($output==='') $output=Yii::getAlias('@runtime/legacy-full-backup-'.date('Ymd-His').'.zip');
        $summary=LegacyBackupService::create($dataPath,$output);
        $this->stdout("Backup dibuat: {$output}\n");
        $this->stdout(json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        return ExitCode::OK;
    }

    public function actionImportPath(string $dataPath, int $replaceAll=1): int
    {
        $summary=LegacyMigrationService::importDirectory(rtrim($dataPath,"/\\"),0,(bool)$replaceAll,'CLI:'.$dataPath);
        $this->stdout(json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        return empty($summary['warnings'])?ExitCode::OK:ExitCode::UNSPECIFIED_ERROR;
    }

    public function actionImportZip(string $zipPath, int $replaceAll=1): int
    {
        $summary=LegacyMigrationService::importZip($zipPath,0,(bool)$replaceAll);
        $this->stdout(json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        return empty($summary['warnings'])?ExitCode::OK:ExitCode::UNSPECIFIED_ERROR;
    }
}
