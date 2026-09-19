<?php
require_once __DIR__.'/../auth.php';
authRequireUnlocked();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../db.php';
require_once __DIR__.'/../finance_features.php';
require_once __DIR__.'/../offline_sync_helper.php';

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) throw new InvalidArgumentException('ID transaksi tidak valid.');

        $transaction = transactionById($id);
        if (!$transaction) throw new InvalidArgumentException('Transaksi tidak ditemukan.');

        echo json_encode([
            'ok'=>true,
            'transaction'=>$transaction,
            'wallets'=>financeWalletsWithBalances(),
            'categories'=>financeCategories(),
            'bills'=>financeBillsStatus()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok'=>false,'error'=>'Metode request tidak didukung.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Data transaksi tidak valid.');

    $cached = offlineOpCachedResponse($input);
    if ($cached) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) throw new InvalidArgumentException('ID transaksi tidak valid.');

    $existing = transactionById($id);
    if (!$existing) throw new InvalidArgumentException('Transaksi tidak ditemukan.');

    $type = strtolower(trim((string)($input['type'] ?? ($existing['type'] ?? 'expense'))));
    if (!in_array($type, ['income','expense','transfer'], true)) {
        throw new InvalidArgumentException('Jenis transaksi tidak valid.');
    }

    $amount = (int)($input['amount'] ?? 0);
    if ($amount <= 0) throw new InvalidArgumentException('Nominal harus lebih dari nol.');

    $transactionDate = trim((string)($input['transaction_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
        throw new InvalidArgumentException('Tanggal transaksi tidak valid.');
    }
    $dateCheck = DateTimeImmutable::createFromFormat('!Y-m-d', $transactionDate);
    if (!$dateCheck || $dateCheck->format('Y-m-d') !== $transactionDate) {
        throw new InvalidArgumentException('Tanggal transaksi tidak valid.');
    }

    $input['type'] = $type;
    $input['amount'] = $amount;
    $input['transaction_date'] = $transactionDate;
    $input['note'] = substr(trim((string)($input['note'] ?? '')), 0, 255);
    $input['expected_version'] = trim((string)($input['expected_version'] ?? ''));
    $spendingKind = strtolower(trim((string)($input['spending_kind'] ?? ($existing['spending_kind'] ?? transactionSpendingKind($existing)))));
    if (!in_array($spendingKind, ['daily','once','recurring'], true)) $spendingKind = 'once';
    $input['spending_kind'] = $spendingKind;
    $input['bill_id'] = max(0,(int)($input['bill_id'] ?? ($existing['bill_id'] ?? 0)));

    if ($type === 'transfer') {
        $from = (int)($input['from_wallet_id'] ?? 0);
        $to = (int)($input['to_wallet_id'] ?? 0);
        if ($from <= 0 || $to <= 0) {
            throw new InvalidArgumentException('Pilih dompet asal dan tujuan.');
        }
        if ($from === $to) {
            throw new InvalidArgumentException('Dompet asal dan tujuan harus berbeda.');
        }

        $walletIds = array_map(static function ($w) {
            return (int)($w['id'] ?? 0);
        }, financeWalletsWithBalances());

        if (!in_array($from, $walletIds, true) || !in_array($to, $walletIds, true)) {
            throw new InvalidArgumentException('Dompet transaksi tidak ditemukan.');
        }

        $input['category'] = 'Transfer Antar Dompet';
        $input['from_wallet_id'] = $from;
        $input['to_wallet_id'] = $to;
        $input['bill_id'] = 0;
    } else {
        $category = trim((string)($input['category'] ?? 'Lainnya'));
        if ($category === '') $category = 'Lainnya';
        $input['category'] = substr($category, 0, 80);

        $walletId = (int)($input['wallet_id'] ?? 1);
        $walletIds = array_map(static function ($w) {
            return (int)($w['id'] ?? 0);
        }, financeWalletsWithBalances());
        if ($walletId <= 0 || !in_array($walletId, $walletIds, true)) {
            throw new InvalidArgumentException('Dompet transaksi tidak ditemukan.');
        }
        $input['wallet_id'] = $walletId;
    }

    if ($type !== 'transfer' && (int)$input['bill_id'] > 0) {
        $bill = financeBillById((int)$input['bill_id']);
        if (!$bill) throw new InvalidArgumentException('Tagihan yang dipilih tidak ditemukan.');
        $paymentKey = financeBillPaymentKey($bill, $transactionDate);
        if (isset($bill['payments'][$paymentKey]) && (int)($bill['payments'][$paymentKey]['transaction_id'] ?? 0) !== $id) {
            throw new InvalidArgumentException('Tagihan ini sudah terhubung dengan transaksi lain untuk periode tersebut.');
        }
    }

    $updated = updateTransaction($id, $input);
    if ($type !== 'transfer') $updated = financeLinkTransactionToBill($id, (int)$input['bill_id']);
    $response = [
        'ok'=>true,
        'transaction'=>$updated,
        'summary'=>summary(),
        'features'=>financeFeatureSnapshot()
    ];

    offlineOpRemember($input, $response);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    $code = $e->getCode() === 409 ? 409 : 500;
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$code===409?$e->getMessage():'Gagal memperbarui transaksi: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Gagal memperbarui transaksi: '.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
