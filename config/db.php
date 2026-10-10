<?php
return [
    'class' => yii\db\Connection::class,
    'dsn' => $_ENV['DB_DSN'] ?? 'mysql:host=127.0.0.1;dbname=catatan_keuangan',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'attributes' => class_exists(PDO::class) ? [PDO::ATTR_EMULATE_PREPARES => false] : [],
];
