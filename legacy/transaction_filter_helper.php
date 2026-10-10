<?php

function txValidDateValue($value) {
    if (!is_string($value) || $value === '') return '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : '';
}

function txReadFilters($source) {
    $type = isset($source['type']) ? strtolower(trim((string)$source['type'])) : 'all';
    if (!in_array($type, ['all', 'expense', 'income', 'transfer'], true)) $type = 'all';

    $from = txValidDateValue(isset($source['from']) ? $source['from'] : '');
    $to = txValidDateValue(isset($source['to']) ? $source['to'] : '');

    $sort = isset($source['sort']) ? strtolower(trim((string)$source['sort'])) : 'date_desc';
    $allowedSorts = ['date_desc', 'date_asc', 'amount_desc', 'amount_asc'];
    if (!in_array($sort, $allowedSorts, true)) $sort = 'date_desc';

    if ($from !== '' && $to !== '' && $from > $to) {
        throw new InvalidArgumentException('Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
    }
    $search = trim((string)($source['search'] ?? ''));
    if (strlen($search) > 120) $search = substr($search,0,120);
    $wallet = max(0,(int)($source['wallet_id'] ?? 0));
    $category = trim((string)($source['category'] ?? ''));
    if (strlen($category) > 80) $category = substr($category,0,80);

    return ['type'=>$type, 'from'=>$from, 'to'=>$to, 'sort'=>$sort, 'search'=>$search, 'wallet_id'=>$wallet, 'category'=>$category];
}


function txReadPagination($source, $defaultLimit=10, $maxLimit=100) {
    $page = max(1, (int)($source['page'] ?? 1));
    $limit = (int)($source['limit'] ?? $defaultLimit);
    if ($limit <= 0) $limit = $defaultLimit;
    $limit = min(max(1, $limit), max(1, (int)$maxLimit));
    return ['page'=>$page, 'limit'=>$limit];
}

function txPaginateResult(array $result, int $page, int $limit): array {
    $items = array_values((array)($result['transactions'] ?? []));
    $total = count($items);
    $page = max(1, $page);
    $limit = max(1, $limit);
    $offset = ($page - 1) * $limit;
    $paged = array_slice($items, $offset, $limit);
    $loadedThrough = min($total, $offset + count($paged));
    $result['transactions'] = $paged;
    $result['meta']['pagination'] = [
        'page'=>$page,
        'limit'=>$limit,
        'total'=>$total,
        'loaded'=>count($paged),
        'loaded_through'=>$loadedThrough,
        'has_more'=>$loadedThrough < $total,
        'next_page'=>$loadedThrough < $total ? $page + 1 : null,
    ];
    return $result;
}

function txSortTransactions(&$items, $sort) {
    usort($items, function ($a, $b) use ($sort) {
        $amountA = (int)($a['amount'] ?? 0);
        $amountB = (int)($b['amount'] ?? 0);
        $dateA = (string)($a['transaction_date'] ?? '');
        $dateB = (string)($b['transaction_date'] ?? '');
        $idA = (int)($a['id'] ?? 0);
        $idB = (int)($b['id'] ?? 0);

        if ($sort === 'amount_desc' || $sort === 'amount_asc') {
            if ($amountA !== $amountB) {
                return $sort === 'amount_desc' ? ($amountB <=> $amountA) : ($amountA <=> $amountB);
            }
            if ($dateA !== $dateB) return strcmp($dateB, $dateA);
            return $idB <=> $idA;
        }

        if ($dateA !== $dateB) {
            return $sort === 'date_asc' ? strcmp($dateA, $dateB) : strcmp($dateB, $dateA);
        }
        return $sort === 'date_asc' ? ($idA <=> $idB) : ($idB <=> $idA);
    });
}

function txFilterTransactions($transactions, $filters) {
    $items = [];
    $totalIncome = 0;
    $totalExpense = 0;

    foreach ((array)$transactions as $t) {
        $txType = isset($t['type']) ? (string)$t['type'] : '';
        $date = isset($t['transaction_date']) ? (string)$t['transaction_date'] : '';

        if ($filters['type'] !== 'all' && $txType !== $filters['type']) continue;
        if ($filters['from'] !== '' && $date < $filters['from']) continue;
        if ($filters['to'] !== '' && $date > $filters['to']) continue;
        if (!empty($filters['category']) && strcasecmp((string)($t['category'] ?? ''),(string)$filters['category']) !== 0) continue;
        if (!empty($filters['wallet_id'])) {
            $wid=(int)$filters['wallet_id'];
            if ($txType==='transfer') {
                if ((int)($t['from_wallet_id']??0)!==$wid && (int)($t['to_wallet_id']??0)!==$wid) continue;
            } elseif ((int)($t['wallet_id']??1)!==$wid) continue;
        }
        if (!empty($filters['search'])) {
            $hay=strtolower((string)($t['note']??'').' '.(string)($t['category']??'').' '.(string)($t['amount']??''));
            if (strpos($hay,strtolower((string)$filters['search']))===false) continue;
        }

        $amount = (int)($t['amount'] ?? 0);
        if ($txType === 'income') $totalIncome += $amount;
        if ($txType === 'expense') $totalExpense += $amount;
        $items[] = $t;
    }

    txSortTransactions($items, $filters['sort']);

    return [
        'transactions' => $items,
        'meta' => [
            'count' => count($items),
            'income' => $totalIncome,
            'expense' => $totalExpense,
            'type' => $filters['type'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'sort' => $filters['sort'],
            'search' => $filters['search'] ?? '',
            'wallet_id' => (int)($filters['wallet_id'] ?? 0),
            'category' => $filters['category'] ?? '',
        ],
    ];
}

/**
 * Menghitung saldo riil setelah setiap transaksi berdasarkan urutan kronologis.
 * Nilai ini tetap benar walaupun tampilan laporan disortir berdasarkan nominal.
 */
function txBalanceMap($transactions, $initialBalance) {
    $chronological = array_values((array)$transactions);
    usort($chronological, function ($a, $b) {
        $dateA = (string)($a['transaction_date'] ?? '');
        $dateB = (string)($b['transaction_date'] ?? '');
        if ($dateA !== $dateB) return strcmp($dateA, $dateB);
        return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
    });

    $balance = (int)$initialBalance;
    $map = [];
    foreach ($chronological as $t) {
        $amount = (int)($t['amount'] ?? 0);
        if (($t['type'] ?? '') === 'income') $balance += $amount;
        elseif (($t['type'] ?? '') === 'expense') $balance -= $amount;
        $map[(int)($t['id'] ?? 0)] = $balance;
    }
    return $map;
}

function txBalanceBeforeDate($transactions, $initialBalance, $fromDate) {
    $balance = (int)$initialBalance;
    if ($fromDate === '') return $balance;
    foreach ((array)$transactions as $t) {
        $date = (string)($t['transaction_date'] ?? '');
        if ($date === '' || $date >= $fromDate) continue;
        $amount = (int)($t['amount'] ?? 0);
        if (($t['type'] ?? '') === 'income') $balance += $amount;
        elseif (($t['type'] ?? '') === 'expense') $balance -= $amount;
    }
    return $balance;
}

function txBalanceThroughDate($transactions, $initialBalance, $toDate) {
    $balance = (int)$initialBalance;
    foreach ((array)$transactions as $t) {
        $date = (string)($t['transaction_date'] ?? '');
        if ($toDate !== '' && $date > $toDate) continue;
        $amount = (int)($t['amount'] ?? 0);
        if (($t['type'] ?? '') === 'income') $balance += $amount;
        elseif (($t['type'] ?? '') === 'expense') $balance -= $amount;
    }
    return $balance;
}
