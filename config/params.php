<?php
return [
    'appName' => $_ENV['APP_NAME'] ?? 'Catatan Keuangan',
    'appUrl' => rtrim($_ENV['APP_URL'] ?? '', '/'),
    'legacyDataPath' => $_ENV['LEGACY_DATA_PATH'] ?? '',
    'privateStorage' => dirname(__DIR__) . '/storage/private',
];
