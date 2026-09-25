<?php
use Dotenv\Dotenv;
require dirname(__DIR__) . '/vendor/autoload.php';
if (class_exists(Dotenv::class) && is_file(dirname(__DIR__) . '/.env')) Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
defined('YII_DEBUG') or define('YII_DEBUG', filter_var($_ENV['YII_DEBUG'] ?? false, FILTER_VALIDATE_BOOL));
defined('YII_ENV') or define('YII_ENV', $_ENV['YII_ENV'] ?? 'prod');
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$config = require dirname(__DIR__) . '/config/web.php';
(new yii\web\Application($config))->run();
