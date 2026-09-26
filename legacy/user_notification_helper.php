<?php
use app\repositories\DocumentRepository;

/**
 * Pusat Pemberitahuan milik pengguna.
 *
 * Disimpan di app_document agar tidak membutuhkan migration baru. Setiap akun
 * memiliki dokumen sendiri sehingga daftar pemberitahuan tidak tercampur antar-user.
 */
function userNotificationDefaultData(): array
{
    return ['items' => [], 'meta' => ['next_id' => 1]];
}

function userNotificationDocumentKey(int $userId): string
{
    return 'user_' . max(0, $userId);
}

function userNotificationNormalizeData($data): array
{
    if (!is_array($data)) $data = [];
    if (!isset($data['items']) || !is_array($data['items'])) $data['items'] = [];
    if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = [];
    $maxId = 0;
    foreach ($data['items'] as $row) $maxId = max($maxId, (int)($row['id'] ?? 0));
    $data['meta']['next_id'] = max($maxId + 1, (int)($data['meta']['next_id'] ?? 1), 1);
    return $data;
}

function userNotificationReadData(int $userId): array
{
    if ($userId <= 0) return userNotificationDefaultData();
    return userNotificationNormalizeData(
        DocumentRepository::get('user_notifications', userNotificationDocumentKey($userId), userNotificationDefaultData())
    );
}

function userNotificationMutateData(int $userId, callable $fn)
{
    if ($userId <= 0) throw new InvalidArgumentException('Pengguna pemberitahuan tidak valid.');
    return DocumentRepository::mutate(
        'user_notifications',
        userNotificationDocumentKey($userId),
        userNotificationDefaultData(),
        function (&$data) use ($fn) {
            $data = userNotificationNormalizeData($data);
            return $fn($data);
        }
    );
}

function userNotificationSanitizePayload(array $payload): array
{
    // Payload hanya metadata tampilan. Jangan pernah menyimpan password, PIN,
    // cookie, token perangkat, session ID, atau credential lainnya di sini.
    $allowed = [
        'device','via','ip','login_at','channel','app_version',
        'action_url','action_label','campaign_id','kind','warning_key',
    ];
    $clean = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $payload)) continue;
        $value = $payload[$key];
        if (is_bool($value) || is_int($value) || is_float($value)) $clean[$key] = $value;
        elseif (is_string($value)) { $text=trim($value); $clean[$key]=function_exists('mb_substr')?mb_substr($text,0,800,'UTF-8'):substr($text,0,800); }
    }
    return $clean;
}

function userNotificationCreate(
    int $userId,
    string $type,
    string $title,
    string $message,
    array $payload = [],
    string $dedupKey = ''
): array {
    $type = strtolower(trim($type));
    $allowedTypes = ['login','broadcast','warning','info','premium'];
    if (!in_array($type, $allowedTypes, true)) $type = 'info';
    $title = trim($title);
    $message = trim($message);
    if ($title === '') throw new InvalidArgumentException('Judul pemberitahuan wajib diisi.');
    if ((function_exists('mb_strlen')?mb_strlen($title,'UTF-8'):strlen($title)) > 160) $title = function_exists('mb_substr')?mb_substr($title,0,160,'UTF-8'):substr($title,0,160);
    if ((function_exists('mb_strlen')?mb_strlen($message,'UTF-8'):strlen($message)) > 5000) $message = function_exists('mb_substr')?mb_substr($message,0,5000,'UTF-8'):substr($message,0,5000);
    $payload = userNotificationSanitizePayload($payload);
    $dedupKey = preg_replace('/[^a-zA-Z0-9_.:|\-]+/', '_', trim($dedupKey));

    return userNotificationMutateData($userId, function (&$data) use ($type,$title,$message,$payload,$dedupKey) {
        if ($dedupKey !== '') {
            foreach (array_reverse($data['items']) as $existing) {
                if ((string)($existing['dedup_key'] ?? '') === $dedupKey) return $existing;
            }
        }
        $row = [
            'id' => (int)$data['meta']['next_id']++,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
            'dedup_key' => $dedupKey,
            'read_at' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $data['items'][] = $row;
        // Cukup untuk histori beberapa bulan tanpa membuat app_document membengkak.
        if (count($data['items']) > 400) $data['items'] = array_slice($data['items'], -400);
        return $row;
    });
}

function userNotificationList(int $userId, int $limit = 80): array
{
    $limit = max(1, min(200, $limit));
    $rows = userNotificationReadData($userId)['items'];
    usort($rows, fn($a,$b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
    return array_slice($rows, 0, $limit);
}

function userNotificationUnreadCount(int $userId): int
{
    $count = 0;
    foreach (userNotificationReadData($userId)['items'] as $row) {
        if (trim((string)($row['read_at'] ?? '')) === '') $count++;
    }
    return $count;
}

function userNotificationMarkRead(int $userId, array $ids = []): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
    return userNotificationMutateData($userId, function (&$data) use ($ids) {
        $changed = 0;
        $all = count($ids) === 0;
        foreach ($data['items'] as &$row) {
            if (!$all && !in_array((int)($row['id'] ?? 0), $ids, true)) continue;
            if (trim((string)($row['read_at'] ?? '')) !== '') continue;
            $row['read_at'] = date('Y-m-d H:i:s');
            $changed++;
        }
        unset($row);
        return $changed;
    });
}

function userNotificationSnapshot(int $userId, int $limit = 80): array
{
    return [
        'unread_count' => userNotificationUnreadCount($userId),
        'notifications' => userNotificationList($userId, $limit),
    ];
}

function userNotificationSnapshotFromData(array $data, int $limit = 80): array
{
    $limit = max(1, min(200, $limit));
    $data = userNotificationNormalizeData($data);
    $rows = $data['items'];
    usort($rows, fn($a,$b) => (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
    $unread = 0;
    foreach ($data['items'] as $row) {
        if (trim((string)($row['read_at'] ?? '')) === '') $unread++;
    }
    return [
        'unread_count' => $unread,
        'notifications' => array_slice($rows, 0, $limit),
    ];
}

function userNotificationRealtimeSignatureFromData(array $data): string
{
    $data = userNotificationNormalizeData($data);
    $fingerprint = [];
    foreach ($data['items'] as $row) {
        $fingerprint[] = [
            (int)($row['id'] ?? 0),
            (string)($row['read_at'] ?? ''),
        ];
    }
    return sha1(json_encode($fingerprint, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}

function userNotificationRealtimeSignature(int $userId): string
{
    return userNotificationRealtimeSignatureFromData(userNotificationReadData($userId));
}

