<?php
require_once __DIR__ . '/db.php';

function offlineClientOpId($input = null) {
    $id = '';
    if (is_array($input) && isset($input['client_op_id'])) $id = (string)$input['client_op_id'];
    if ($id === '' && !empty($_SERVER['HTTP_X_CLIENT_OP_ID'])) $id = (string)$_SERVER['HTTP_X_CLIENT_OP_ID'];
    $id = trim($id);
    if ($id === '') return '';
    if (!preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $id)) return '';
    return $id;
}

function offlineOpCachedResponse($input = null) {
    $id = offlineClientOpId($input);
    if ($id === '') return null;
    $d = readData();
    $ops = isset($d['meta']['offline_applied_ops']) && is_array($d['meta']['offline_applied_ops']) ? $d['meta']['offline_applied_ops'] : [];
    if (!isset($ops[$id]) || !is_array($ops[$id])) return null;
    $response = isset($ops[$id]['response']) && is_array($ops[$id]['response']) ? $ops[$id]['response'] : ['ok'=>true];
    $response['offline_duplicate'] = true;
    $response['client_op_id'] = $id;
    return $response;
}

function offlineOpRemember($input, $response) {
    $id = offlineClientOpId($input);
    if ($id === '') return;
    $clean = ['ok'=>true];
    if (is_array($response) && isset($response['reply'])) $clean['reply'] = (string)$response['reply'];
    mutateData(function (&$d) use ($id, $clean) {
        if (!isset($d['meta']['offline_applied_ops']) || !is_array($d['meta']['offline_applied_ops'])) $d['meta']['offline_applied_ops'] = [];
        $d['meta']['offline_applied_ops'][$id] = [
            'applied_at'=>date('Y-m-d H:i:s'),
            'response'=>$clean,
        ];
        // Batasi ukuran metadata agar JSON tidak terus membesar.
        if (count($d['meta']['offline_applied_ops']) > 300) {
            $d['meta']['offline_applied_ops'] = array_slice($d['meta']['offline_applied_ops'], -250, null, true);
        }
    });
}
