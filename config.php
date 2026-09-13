<?php
define('APP_NAME', 'Catatan Keuangan');
date_default_timezone_set('Asia/Makassar');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
