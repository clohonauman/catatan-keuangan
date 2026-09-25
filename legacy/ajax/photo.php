<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();
require_once __DIR__.'/../receipt_helper.php';

$file = isset($_GET['f']) ? (string)$_GET['f'] : '';
$path = receiptFilePath($file);
if (!$path) { http_response_code(404); exit('Foto tidak ditemukan.'); }
$info = @getimagesize($path);
$mime = $info && !empty($info['mime']) ? $info['mime'] : 'application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
