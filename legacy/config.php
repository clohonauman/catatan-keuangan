<?php
if (!defined('APP_NAME')) define('APP_NAME', $_ENV['APP_NAME'] ?? 'Catatan Keuangan');
date_default_timezone_set('Asia/Makassar');
if (class_exists(Yii::class) && Yii::$app instanceof yii\web\Application) {
    Yii::$app->session->open();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
