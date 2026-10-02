<?php
/**
 * V79 - Perencanaan Pengeluaran
 *
 * Mesin penyusunan rencana belanja yang membaca data Catatan Keuangan
 * milik user yang sama. Tidak membuat saldo/transaksi baru; rencana disimpan
 * sebagai pengaturan per-user agar tetap ikut backup/restore yang sudah ada.
 */

if (!function_exists('planningMonthValid')) {
    function planningMonthValid($month): bool {
        $month = trim((string)$month);
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) return false;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        return $d !== false && $d->format('Y-m') === $month;
    }
}

function planningNormalizeMonth($month = ''): string {
    $month = trim((string)$month);
    $current = new DateTimeImmutable('first day of this month');
    if (!planningMonthValid($month)) {
        return $current->modify('+1 month')->format('Y-m');
    }
    $candidate = new DateTimeImmutable($month . '-01');
    if ($candidate <= $current) return $current->modify('+1 month')->format('Y-m');
    return $month;
}

function planningMonthBounds(string $month): array {
    $start = new DateTimeImmutable($month . '-01');
    return [$start, $start->modify('last day of this month')];
}

function planningMoneyRound($amount, $step = 5000): int {
    $amount = max(0, (int)$amount);
    $step = max(1, (int)$step);
    return (int)(round($amount / $step) * $step);
}

function planningMonthLabel(string $month): string {
    $months = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $d = new DateTimeImmutable($month . '-01');
    return $months[(int)$d->format('n')] . ' ' . $d->format('Y');
}

function planningStoredPlans(array $data): array {
    $plans = $data['settings']['planning_plans'] ?? [];
    return is_array($plans) ? $plans : [];
}

function planningSavedPlan(string $month, ?array $data = null): array {
    $d = is_array($data) ? $data : financeReadData();
    $plans = planningStoredPlans($d);
    $row = isset($plans[$month]) && is_array($plans[$month]) ? $plans[$month] : [];
    return [
        'month' => $month,
        'income_override' => array_key_exists('income_override', $row) && $row['income_override'] !== null ? max(0, (int)$row['income_override']) : null,
        'target_savings' => max(0, (int)($row['target_savings'] ?? 0)),
        'items' => is_array($row['items'] ?? null) ? array_values($row['items']) : [],
        'notes' => substr(trim((string)($row['notes'] ?? '')), 0, 2000),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function planningRecurringBetween(array $recurring, string $fromExclusive, string $toInclusive): array {
    $income = 0; $expense = 0; $rows = [];
    foreach ($recurring as $r) {
        if (empty($r['active'])) continue;
        $next = trim((string)($r['next_run'] ?? ''));
        if (!financeIsValidDate($next)) continue;
        $guard = 0;
        while ($next <= $toInclusive && $guard++ < 240) {
            if ($next > $fromExclusive) {
                $amount = max(0, (int)($r['amount'] ?? 0));
                $type = (string)($r['type'] ?? 'expense');
                if ($type === 'income') $income += $amount; else $expense += $amount;
                $rows[] = [
                    'id' => (int)($r['id'] ?? 0),
                    'name' => (string)($r['name'] ?? 'Transaksi berulang'),
                    'type' => $type,
                    'amount' => $amount,
                    'category' => (string)($r['category'] ?? 'Lainnya'),
                    'wallet_id' => (int)($r['wallet_id'] ?? 0),
                    'date' => $next,
                    'source' => 'recurring',
                ];
            }
            $next = financeNextRecurringDate($next, (string)($r['frequency'] ?? 'monthly'), (int)($r['interval'] ?? 1));
        }
    }
    usort($rows, static function($a, $b) { return strcmp((string)$a['date'], (string)$b['date']); });
    return ['income'=>$income, 'expense'=>$expense, 'rows'=>$rows];
}

function planningBillRowsForMonth(array $bills, string $today, string $month): array {
    [, $end] = planningMonthBounds($month);
    $rows = financeForecastBills($bills, $today, $end->format('Y-m-d'));
    $start = $month . '-01';
    return array_values(array_filter($rows, static function($row) use ($start, $month) {
        $date = (string)($row['due_date'] ?? '');
        return $date >= $start && strpos($date, $month) === 0 && empty($row['paid']);
    }));
}

function planningHistoricalVariableCategories(array $transactions, string $targetMonth, int $months = 6): array {
    $targetStart = new DateTimeImmutable('first day of this month');
    $monthKeys = [];
    for ($i = $months; $i >= 1; $i--) $monthKeys[] = $targetStart->modify('-' . $i . ' month')->format('Y-m');
    $totals = []; $activeMonths = [];
    foreach ($monthKeys as $m) $activeMonths[$m] = [];

    foreach ($transactions as $t) {
        if (($t['type'] ?? '') !== 'expense') continue;
        if (in_array((string)($t['source'] ?? ''), ['receivable_lend','receivable_repayment'], true)) continue;
        if (transactionSpendingKind((array)$t) !== 'daily') continue;
        $date = substr((string)($t['transaction_date'] ?? ''), 0, 7);
        if (!isset($activeMonths[$date])) continue;
        $cat = trim((string)($t['category'] ?? 'Lainnya')) ?: 'Lainnya';
        $amount = max(0, (int)($t['amount'] ?? 0));
        if (!isset($activeMonths[$date][$cat])) $activeMonths[$date][$cat] = 0;
        $activeMonths[$date][$cat] += $amount;
        if (!isset($totals[$cat])) $totals[$cat] = ['sum'=>0,'months'=>0,'last'=>0];
        $totals[$cat]['sum'] += $amount;
        $totals[$cat]['months']++;
        $totals[$cat]['last'] = max($totals[$cat]['last'], $amount);
    }

    $rows = [];
    foreach ($totals as $cat => $v) {
        $divisor = max(1, (int)$v['months']);
        $avg = planningMoneyRound((int)round($v['sum'] / $divisor));
        if ($avg <= 0) continue;
        $rows[] = [
            'id' => 'history:' . sha1(mb_strtolower($cat, 'UTF-8')),
            'type' => 'variable',
            'label' => $cat,
            'category' => $cat,
            'amount' => $avg,
            'suggested_amount' => $avg,
            'history_total' => (int)$v['sum'],
            'history_months' => (int)$v['months'],
            'source' => 'history',
            'enabled' => true,
            'editable' => true,
        ];
    }
    usort($rows, static function($a, $b) { return (int)$b['amount'] <=> (int)$a['amount']; });
    return $rows;
}

function planningHistoricalIncome(array $transactions, string $targetMonth, int $months = 6): int {
    $targetStart = new DateTimeImmutable('first day of this month');
    $keys = [];
    for ($i = $months; $i >= 1; $i--) $keys[] = $targetStart->modify('-' . $i . ' month')->format('Y-m');
    $monthly = array_fill_keys($keys, 0);
    foreach ($transactions as $t) {
        if (($t['type'] ?? '') !== 'income') continue;
        if ((string)($t['source'] ?? '') === 'recurring') continue;
        $m = substr((string)($t['transaction_date'] ?? ''), 0, 7);
        if (isset($monthly[$m])) $monthly[$m] += max(0, (int)($t['amount'] ?? 0));
    }
    $values = array_values($monthly);
    $nonZero = array_values(array_filter($values, static fn($v) => (int)$v > 0));
    if (!$nonZero) return 0;
    return planningMoneyRound((int)round(array_sum($nonZero) / count($nonZero)));
}

function planningTargetMonthData(string $month, array $data): array {
    // Samakan jadwal berulang dengan aplikasi utama sebelum menyusun proyeksi.
    // Pemrosesan hanya mengejar transaksi sampai hari ini; rencana bulan depan
    // tetap membaca jadwal berikutnya dari data yang sudah disinkronkan.
    financeProcessRecurring();
    $data = financeReadData();
    $today = new DateTimeImmutable('today');
    $todayDate = $today->format('Y-m-d');
    [$targetStart, $targetEnd] = planningMonthBounds($month);
    $transactions = (array)($data['transactions'] ?? []);
    $bills = (array)($data['bills'] ?? []);
    $recurring = (array)($data['recurring'] ?? []);
    $summary = summary();
    $wallets = financeWalletsWithBalances();

    // Saldo yang realistis dibawa menuju bulan target: saldo tersedia saat ini,
    // dikurangi pengeluaran harian dan kewajiban yang diperkirakan sebelum target,
    // ditambah pemasukan terjadwal.
    $bridgeTo = $targetStart->modify('-1 day')->format('Y-m-d');
    $bridgeDays = max(0, (int)$today->diff($targetStart)->days);
    $history30 = financeDailyForecastHistory($transactions, $todayDate);
    $dailyAvg = max(0, (int)$history30['average_daily_expense']);
    $bridgeDaily = $bridgeDays > 0 ? $dailyAvg * $bridgeDays : 0;
    $bridgeRecurring = planningRecurringBetween($recurring, $today->modify('-1 day')->format('Y-m-d'), $bridgeTo);
    $bridgeBills = [];
    if ($bridgeTo >= $todayDate) {
        $bridgeBills = financeForecastBills($bills, $todayDate, $bridgeTo);
        $bridgeBills = array_values(array_filter($bridgeBills, static function($row) use ($todayDate, $bridgeTo) {
            $date = (string)($row['due_date'] ?? '');
            return $date >= $todayDate && $date <= $bridgeTo && empty($row['paid']);
        }));
    }
    $bridgeBillTotal = (int)array_sum(array_map(static fn($r) => max(0, (int)($r['amount'] ?? 0)), $bridgeBills));
    $projectedOpening = max(0,
        (int)($summary['available_balance'] ?? $summary['balance'] ?? 0)
        + (int)$bridgeRecurring['income']
        - (int)$bridgeRecurring['expense']
        - $bridgeBillTotal
        - $bridgeDaily
    );

    $targetRecurring = planningRecurringBetween($recurring, $targetStart->modify('-1 day')->format('Y-m-d'), $targetEnd->format('Y-m-d'));
    $targetBills = planningBillRowsForMonth($bills, $todayDate, $month);
    $targetBillRows = [];
    foreach ($targetBills as $b) {
        $targetBillRows[] = [
            'id' => 'bill:' . (int)($b['id'] ?? 0) . ':' . (string)($b['due_date'] ?? ''),
            'type' => 'obligation',
            'label' => (string)($b['name'] ?? 'Tagihan'),
            'category' => (string)($b['category'] ?? 'Tagihan'),
            'amount' => max(0, (int)($b['amount'] ?? 0)),
            'suggested_amount' => max(0, (int)($b['amount'] ?? 0)),
            'source' => 'bill',
            'ref_id' => (int)($b['id'] ?? 0),
            'due_date' => (string)($b['due_date'] ?? ''),
            'schedule_type' => (string)($b['schedule_type'] ?? ''),
            'enabled' => true,
            'editable' => true,
        ];
    }
    foreach ((array)$targetRecurring['rows'] as $r) {
        if (($r['type'] ?? 'expense') !== 'expense') continue;
        $targetBillRows[] = [
            'id' => 'recurring:' . (int)$r['id'] . ':' . (string)$r['date'],
            'type' => 'obligation',
            'label' => (string)$r['name'],
            'category' => (string)$r['category'],
            'amount' => max(0, (int)$r['amount']),
            'suggested_amount' => max(0, (int)$r['amount']),
            'source' => 'recurring',
            'ref_id' => (int)$r['id'],
            'due_date' => (string)$r['date'],
            'enabled' => true,
            'editable' => true,
        ];
    }
    usort($targetBillRows, static function($a, $b) { return strcmp((string)($a['due_date'] ?? ''), (string)($b['due_date'] ?? '')); });

    $variableSuggestions = planningHistoricalVariableCategories($transactions, $month, 6);
    $historicalIncome = planningHistoricalIncome($transactions, $month, 6);
    $scheduledIncome = (int)$targetRecurring['income'];
    $expectedIncome = $scheduledIncome + $historicalIncome;
    $credit = [];
    foreach ($wallets as $w) {
        if (strtolower((string)($w['type'] ?? '')) !== 'credit_card') continue;
        $credit[] = [
            'name' => (string)($w['name'] ?? 'Kartu Kredit'),
            'limit' => max(0, (int)($w['credit_limit'] ?? 0)),
            'used' => max(0, (int)($w['credit_used'] ?? $w['outstanding_balance'] ?? 0)),
            'available' => max(0, (int)($w['available_limit'] ?? 0)),
        ];
    }

    return [
        'month' => $month,
        'month_label' => planningMonthLabel($month),
        'today' => $todayDate,
        'current_available_balance' => max(0, (int)($summary['available_balance'] ?? $summary['balance'] ?? 0)),
        'projected_opening_balance' => $projectedOpening,
        'bridge_days' => $bridgeDays,
        'bridge_daily_estimate' => $bridgeDaily,
        'bridge_bills_total' => $bridgeBillTotal,
        'bridge_recurring_income' => (int)$bridgeRecurring['income'],
        'bridge_recurring_expense' => (int)$bridgeRecurring['expense'],
        'expected_income' => $expectedIncome,
        'historical_income_estimate' => $historicalIncome,
        'scheduled_income' => $scheduledIncome,
        'variable_suggestions' => $variableSuggestions,
        'obligations' => $targetBillRows,
        'obligation_total' => (int)array_sum(array_map(static fn($r) => max(0, (int)($r['amount'] ?? 0)), $targetBillRows)),
        'credit_cards' => $credit,
        'notes' => [
            'Riwayat' => 'Saran belanja variabel memakai rata-rata pengeluaran harian dari maksimal 6 bulan yang sudah selesai.',
            'Kewajiban' => 'Tagihan, cicilan, dan transaksi berulang yang sudah terjadwal dipisahkan agar tidak terhitung dua kali sebagai belanja variabel.',
            'Estimasi' => 'Pemasukan dan saldo awal bulan target adalah estimasi berdasarkan data yang tercatat; bukan jaminan.',
        ],
    ];
}

function planningNormalizeItems(array $items): array {
    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ''));
        if ($label === '') continue;
        $amount = max(0, (int)($item['amount'] ?? 0));
        if ($amount <= 0) continue;
        $id = trim((string)($item['id'] ?? ''));
        if ($id === '') $id = 'custom:' . bin2hex(random_bytes(5));
        $source = in_array((string)($item['source'] ?? ''), ['history','bill','recurring','custom'], true) ? (string)$item['source'] : 'custom';
        $requestedType = (string)($item['type'] ?? 'variable');
        $type = $source === 'bill' || $source === 'recurring' ? 'obligation' : ($requestedType === 'obligation' ? 'obligation' : 'variable');
        $out[] = [
            'id' => substr($id, 0, 120),
            'type' => $type,
            'label' => substr($label, 0, 100),
            'category' => substr(trim((string)($item['category'] ?? $label)), 0, 80),
            'amount' => $amount,
            'source' => $source,
            'ref_id' => max(0, (int)($item['ref_id'] ?? 0)),
            'due_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($item['due_date'] ?? '')) ? (string)$item['due_date'] : '',
            'enabled' => array_key_exists('enabled', $item) ? !empty($item['enabled']) : true,
        ];
    }
    return array_slice($out, 0, 100);
}

function planningSnapshot(string $month = ''): array {
    $month = planningNormalizeMonth($month);
    $data = financeReadData();
    $base = planningTargetMonthData($month, $data);
    $saved = planningSavedPlan($month, $data);
    $items = $saved['items'];
    if (!$items) {
        $items = array_merge($base['variable_suggestions'], $base['obligations']);
    }
    $items = planningNormalizeItems($items);
    $income = $saved['income_override'] !== null ? $saved['income_override'] : $base['expected_income'];
    $targetSavings = max(0, (int)$saved['target_savings']);
    $variableTotal = 0; $obligationTotal = 0;
    foreach ($items as $item) {
        if (empty($item['enabled'])) continue;
        if (($item['type'] ?? '') === 'variable') $variableTotal += (int)$item['amount'];
        else $obligationTotal += (int)$item['amount'];
    }
    $plannedSpend = $variableTotal + $obligationTotal;
    $availableFunds = (int)$base['projected_opening_balance'] + (int)$income;
    $required = $plannedSpend + $targetSavings;
    $gap = max(0, $required - $availableFunds);
    $remaining = $availableFunds - $required;
    $status = $gap > 0 ? 'warning' : ($remaining < max(100000, (int)$base['bridge_daily_estimate'] * 3) ? 'tight' : 'safe');
    return array_merge($base, [
        'saved_plan' => $saved,
        'items' => $items,
        'income_for_plan' => $income,
        'target_savings' => $targetSavings,
        'variable_total' => $variableTotal,
        'planned_obligations_total' => $obligationTotal,
        'planned_spending_total' => $plannedSpend,
        'available_funds' => $availableFunds,
        'additional_funds_needed' => $gap,
        'projected_remaining' => $remaining,
        'status' => $status,
        'estimated' => true,
    ]);
}

function planningSavePlan(string $month, array $payload): array {
    if (!planningMonthValid($month)) throw new InvalidArgumentException('Bulan rencana tidak valid.');
    $incomeOverride = null;
    if (array_key_exists('income_override', $payload) && $payload['income_override'] !== null && $payload['income_override'] !== '') {
        $incomeOverride = max(0, (int)$payload['income_override']);
    }
    $items = planningNormalizeItems((array)($payload['items'] ?? []));
    $targetSavings = max(0, (int)($payload['target_savings'] ?? 0));
    $notes = substr(trim((string)($payload['notes'] ?? '')), 0, 2000);
    $result = financeMutate(function (&$d) use ($month, $incomeOverride, $items, $targetSavings, $notes) {
        if (!isset($d['settings']['planning_plans']) || !is_array($d['settings']['planning_plans'])) $d['settings']['planning_plans'] = [];
        $d['settings']['planning_plans'][$month] = [
            'income_override' => $incomeOverride,
            'target_savings' => $targetSavings,
            'items' => $items,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        return $d['settings']['planning_plans'][$month];
    });
    return planningSnapshot($month);
}
