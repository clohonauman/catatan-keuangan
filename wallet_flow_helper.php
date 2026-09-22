<?php

/**
 * Ledger saldo per dompet untuk laporan PDF/Excel/CSV.
 * Semua saldo di sini adalah saldo aktual/gross, bukan saldo tersedia setelah
 * dana disisihkan atau saldo minimum.
 */

function walletFlowChronological($transactions)
{
    $rows = array_values((array)$transactions);
    usort($rows, function ($a, $b) {
        $dateA = (string)($a['transaction_date'] ?? '');
        $dateB = (string)($b['transaction_date'] ?? '');
        if ($dateA !== $dateB) return strcmp($dateA, $dateB);
        return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
    });
    return $rows;
}

function walletFlowEnsureWallet(&$defs, &$balances, $id, $name = '')
{
    $id = (int)$id;
    if ($id <= 0) return;
    if (!isset($defs[$id])) {
        $defs[$id] = [
            'id' => $id,
            'name' => $name !== '' ? $name : ($id === 1 ? 'Utama' : 'Dompet #'.$id),
            'initial_balance' => 0,
            'reserved_balance' => 0,
            'minimum_balance' => 0,
            'archived' => false,
        ];
    }
    if (!isset($balances[$id])) $balances[$id] = (int)($defs[$id]['initial_balance'] ?? 0);
}

function walletFlowApplyTransaction(&$balances, &$defs, $t)
{
    $type = (string)($t['type'] ?? '');
    $amount = max(0, (int)($t['amount'] ?? 0));

    if ($type === 'transfer') {
        $from = (int)($t['from_wallet_id'] ?? 0);
        $to = (int)($t['to_wallet_id'] ?? 0);
        walletFlowEnsureWallet($defs, $balances, $from);
        walletFlowEnsureWallet($defs, $balances, $to);
        if ($from > 0) $balances[$from] = (int)($balances[$from] ?? 0) - $amount;
        if ($to > 0) $balances[$to] = (int)($balances[$to] ?? 0) + $amount;
        return;
    }

    $wid = (int)($t['wallet_id'] ?? 1);
    if ($wid <= 0) $wid = 1;
    walletFlowEnsureWallet($defs, $balances, $wid, $wid === 1 ? 'Utama' : '');
    if ($type === 'income') $balances[$wid] = (int)($balances[$wid] ?? 0) + $amount;
    elseif ($type === 'expense') $balances[$wid] = (int)($balances[$wid] ?? 0) - $amount;
}

function walletFlowBuildLedger($transactions, $wallets, $from = '', $to = '')
{
    $defs = [];
    $initial = [];

    foreach ((array)$wallets as $w) {
        $id = (int)($w['id'] ?? 0);
        if ($id <= 0) continue;
        $defs[$id] = [
            'id' => $id,
            'name' => trim((string)($w['name'] ?? '')) ?: ($id === 1 ? 'Utama' : 'Dompet #'.$id),
            'initial_balance' => (int)($w['initial_balance'] ?? 0),
            'reserved_balance' => max(0, (int)($w['reserved_balance'] ?? 0)),
            'minimum_balance' => max(0, (int)($w['minimum_balance'] ?? 0)),
            'archived' => !empty($w['archived']),
        ];
        $initial[$id] = (int)$defs[$id]['initial_balance'];
    }

    if (!$defs) {
        $defs[1] = ['id'=>1,'name'=>'Utama','initial_balance'=>0,'reserved_balance'=>0,'minimum_balance'=>0,'archived'=>false];
        $initial[1] = 0;
    }

    // Pastikan dompet lama/terarsip yang masih direferensikan transaksi tetap muncul.
    foreach ((array)$transactions as $t) {
        if (($t['type'] ?? '') === 'transfer') {
            foreach ([(int)($t['from_wallet_id'] ?? 0), (int)($t['to_wallet_id'] ?? 0)] as $wid) {
                walletFlowEnsureWallet($defs, $initial, $wid);
            }
        } else {
            $wid = (int)($t['wallet_id'] ?? 1);
            if ($wid <= 0) $wid = 1;
            walletFlowEnsureWallet($defs, $initial, $wid, $wid === 1 ? 'Utama' : '');
        }
    }

    ksort($defs);
    ksort($initial);
    $chronological = walletFlowChronological($transactions);

    // Saldo awal periode.
    $opening = $initial;
    if ($from !== '') {
        foreach ($chronological as $t) {
            $date = (string)($t['transaction_date'] ?? '');
            if ($date === '' || $date >= $from) continue;
            walletFlowApplyTransaction($opening, $defs, $t);
        }
    }

    // Saldo akhir periode.
    $closing = $initial;
    foreach ($chronological as $t) {
        $date = (string)($t['transaction_date'] ?? '');
        if ($to !== '' && $date > $to) continue;
        walletFlowApplyTransaction($closing, $defs, $t);
    }

    // Statistik arus per dompet khusus periode laporan.
    $periodStats = [];
    foreach ($defs as $id => $w) {
        $periodStats[(int)$id] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
    }
    foreach ($chronological as $t) {
        $date = (string)($t['transaction_date'] ?? '');
        if ($from !== '' && $date < $from) continue;
        if ($to !== '' && $date > $to) continue;
        $type = (string)($t['type'] ?? '');
        $amount = max(0, (int)($t['amount'] ?? 0));
        if ($type === 'transfer') {
            $fromId = (int)($t['from_wallet_id'] ?? 0);
            $toId = (int)($t['to_wallet_id'] ?? 0);
            walletFlowEnsureWallet($defs, $initial, $fromId);
            walletFlowEnsureWallet($defs, $initial, $toId);
            if (!isset($periodStats[$fromId])) $periodStats[$fromId] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            if (!isset($periodStats[$toId])) $periodStats[$toId] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            $periodStats[$fromId]['transfer_out'] += $amount;
            $periodStats[$toId]['transfer_in'] += $amount;
        } else {
            $wid = (int)($t['wallet_id'] ?? 1);
            if ($wid <= 0) $wid = 1;
            walletFlowEnsureWallet($defs, $initial, $wid, $wid === 1 ? 'Utama' : '');
            if (!isset($periodStats[$wid])) $periodStats[$wid] = ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
            if ($type === 'income') $periodStats[$wid]['income'] += $amount;
            elseif ($type === 'expense') $periodStats[$wid]['expense'] += $amount;
        }
    }

    // Snapshot setelah setiap transaksi dalam urutan kronologis riil.
    $balances = $initial;
    $snapshots = [];
    foreach ($chronological as $t) {
        $id = (int)($t['id'] ?? 0);
        $type = (string)($t['type'] ?? '');
        $amount = max(0, (int)($t['amount'] ?? 0));
        $beforeTotal = array_sum($balances);
        $snap = [
            'transaction_id' => $id,
            'type' => $type,
            'amount' => $amount,
            'total_before' => (int)$beforeTotal,
        ];

        if ($type === 'transfer') {
            $fromId = (int)($t['from_wallet_id'] ?? 0);
            $toId = (int)($t['to_wallet_id'] ?? 0);
            walletFlowEnsureWallet($defs, $balances, $fromId);
            walletFlowEnsureWallet($defs, $balances, $toId);
            $snap['from_wallet_id'] = $fromId;
            $snap['to_wallet_id'] = $toId;
            $snap['from_wallet_name'] = $defs[$fromId]['name'] ?? 'Dompet asal';
            $snap['to_wallet_name'] = $defs[$toId]['name'] ?? 'Dompet tujuan';
            $snap['from_before'] = (int)($balances[$fromId] ?? 0);
            $snap['to_before'] = (int)($balances[$toId] ?? 0);
            walletFlowApplyTransaction($balances, $defs, $t);
            $snap['from_after'] = (int)($balances[$fromId] ?? 0);
            $snap['to_after'] = (int)($balances[$toId] ?? 0);
            $snap['flow_label'] = $snap['from_wallet_name'].' -> '.$snap['to_wallet_name'];
        } else {
            $wid = (int)($t['wallet_id'] ?? 1);
            if ($wid <= 0) $wid = 1;
            walletFlowEnsureWallet($defs, $balances, $wid, $wid === 1 ? 'Utama' : '');
            $snap['wallet_id'] = $wid;
            $snap['wallet_name'] = $defs[$wid]['name'] ?? ($wid === 1 ? 'Utama' : 'Dompet #'.$wid);
            $snap['wallet_before'] = (int)($balances[$wid] ?? 0);
            walletFlowApplyTransaction($balances, $defs, $t);
            $snap['wallet_after'] = (int)($balances[$wid] ?? 0);
            $snap['flow_label'] = $snap['wallet_name'];
        }

        $snap['total_after'] = (int)array_sum($balances);
        if ($id > 0) $snapshots[$id] = $snap;
    }
    $current = $balances;

    $walletSummary = [];
    foreach ($defs as $id => $w) {
        $grossCurrent = (int)($current[$id] ?? 0);
        $protected = max(0, (int)($w['reserved_balance'] ?? 0)) + max(0, (int)($w['minimum_balance'] ?? 0));
        $stats = $periodStats[(int)$id] ?? ['income'=>0,'expense'=>0,'transfer_in'=>0,'transfer_out'=>0];
        $walletSummary[] = [
            'id' => (int)$id,
            'name' => (string)$w['name'],
            'archived' => !empty($w['archived']),
            'initial_balance' => (int)($initial[$id] ?? 0),
            'opening_balance' => (int)($opening[$id] ?? 0),
            'closing_balance' => (int)($closing[$id] ?? 0),
            'current_balance' => $grossCurrent,
            'income' => (int)$stats['income'],
            'expense' => (int)$stats['expense'],
            'transfer_in' => (int)$stats['transfer_in'],
            'transfer_out' => (int)$stats['transfer_out'],
            'reserved_balance' => max(0, (int)($w['reserved_balance'] ?? 0)),
            'minimum_balance' => max(0, (int)($w['minimum_balance'] ?? 0)),
            'available_balance' => max(0, $grossCurrent - $protected),
        ];
    }

    return [
        'wallets' => $defs,
        'initial_balances' => $initial,
        'opening_balances' => $opening,
        'closing_balances' => $closing,
        'current_balances' => $current,
        'snapshots' => $snapshots,
        'wallet_summary' => $walletSummary,
        'total_initial' => (int)array_sum($initial),
        'total_opening' => (int)array_sum($opening),
        'total_closing' => (int)array_sum($closing),
        'total_current' => (int)array_sum($current),
    ];
}

function walletFlowSnapshotFor($ledger, $transaction)
{
    $id = (int)($transaction['id'] ?? 0);
    return (array)($ledger['snapshots'][$id] ?? []);
}
