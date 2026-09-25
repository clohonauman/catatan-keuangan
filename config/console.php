<?php
return [
    'id' => 'catatan-keuangan-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\commands',
    'timeZone' => 'Asia/Makassar',
    'components' => [
        'db' => require __DIR__ . '/db.php',
        'cache' => ['class' => yii\caching\FileCache::class],
    ],
    'controllerMap' => [
        'migrate' => ['class' => yii\console\controllers\MigrateController::class, 'migrationPath' => '@app/migrations'],
    ],
];
