<?php

declare(strict_types=1);

// Redirect dari root aplikasi ke folder /web.
// Query string (jika ada) tetap dipertahankan.

$query = $_SERVER['QUERY_STRING'] ?? '';

$target = 'web/';
if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 302);
exit;
