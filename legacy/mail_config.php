<?php
if (!defined('MAIL_TRANSPORT')) define('MAIL_TRANSPORT', $_ENV['MAIL_TRANSPORT'] ?? 'smtp');
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM'] ?? '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Catatan Keuangan');
if (!defined('MAIL_SMTP_HOST')) define('MAIL_SMTP_HOST', $_ENV['MAIL_HOST'] ?? '');
if (!defined('MAIL_SMTP_PORT')) define('MAIL_SMTP_PORT', (int)($_ENV['MAIL_PORT'] ?? 587));
if (!defined('MAIL_SMTP_ENCRYPTION')) define('MAIL_SMTP_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');
if (!defined('MAIL_SMTP_USERNAME')) define('MAIL_SMTP_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
if (!defined('MAIL_SMTP_PASSWORD')) define('MAIL_SMTP_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
if (!defined('MAIL_SMTP_TIMEOUT')) define('MAIL_SMTP_TIMEOUT', 20);
