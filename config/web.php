<?php
$params = require __DIR__ . '/params.php';
$appKey=(string)($_ENV['APP_KEY']??'');
if(YII_ENV==='prod' && (strlen($appKey)<32 || $appKey==='CHANGE_ME_TO_A_LONG_RANDOM_SECRET')) {
    throw new RuntimeException('APP_KEY production wajib diisi minimal 32 karakter acak.');
}
$config = [
    'id' => 'catatan-keuangan',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'timeZone' => 'Asia/Makassar',
    'aliases' => ['@bower' => '@vendor/bower-asset', '@npm' => '@vendor/npm-asset'],
    'components' => [
        'request' => [
            'cookieValidationKey' => $appKey !== '' ? $appKey : 'development-only-key',
            'enableCsrfValidation' => true,
            'csrfCookie' => ['httpOnly' => true, 'sameSite' => 'Lax', 'secure' => !YII_DEBUG, 'path' => '/'],
        ],
        'cache' => ['class' => yii\caching\FileCache::class],
        'user' => ['identityClass' => app\models\User::class, 'enableAutoLogin' => false],
        'session' => [
            'name' => 'ck_session',
            'cookieParams' => ['httponly' => true, 'samesite' => 'Lax', 'secure' => !YII_DEBUG, 'path' => '/'],
            'timeout' => 7200,
        ],
        'errorHandler' => ['errorAction' => 'site/error'],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [[
                'class' => yii\log\FileTarget::class,
                'levels' => ['error','warning'],
                // Jangan dump POST/COOKIE/SESSION ke log production karena dapat berisi password, PIN, token, atau cookie sesi.
                'logVars' => [],
                'maxFileSize' => 10240,
                'maxLogFiles' => 10,
            ]],
        ],
        'db' => require __DIR__ . '/db.php',
        'urlManager' => [
            'enablePrettyUrl' => false,
            'showScriptName' => true,
            'rules' => [],
        ],
    ],
    'params' => $params,
];
return $config;
