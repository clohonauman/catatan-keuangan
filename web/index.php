<?php
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
if (class_exists(Dotenv::class) && is_file(dirname(__DIR__) . '/.env')) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

defined('YII_DEBUG') or define('YII_DEBUG', filter_var($_ENV['YII_DEBUG'] ?? false, FILTER_VALIDATE_BOOL));
defined('YII_ENV') or define('YII_ENV', $_ENV['YII_ENV'] ?? 'prod');

/*
 * V64 — Production runtime hardening.
 * Jangan tampilkan warning/stack trace PHP ke pengguna pada production.
 * Session ID lama/asing ditolak dan session hanya boleh melalui cookie.
 */
if (YII_ENV === 'prod') {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    @ini_set('log_errors', '1');
    @ini_set('expose_php', '0');
}
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.use_trans_sid', '0');

require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
$config = require dirname(__DIR__) . '/config/web.php';
(new yii\web\Application($config))->run();
