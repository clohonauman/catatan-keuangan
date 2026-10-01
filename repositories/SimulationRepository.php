<?php
namespace app\repositories;

use Yii;
use yii\db\Query;

/**
 * V82 — Data dasar untuk Simulasi Keuangan.
 *
 * Endpoint ini hanya membaca data. Tidak ada transaksi/saldo nyata yang diubah.
 * Proyeksi variabel memakai transaksi historis yang bersifat harian, sedangkan
 * transaksi berulang dan tagihan dijadwalkan terpisah agar tidak dihitung ganda.
 */
final class SimulationRepository
{
    private static function db() { return Yii::$app->db; }

    private static function decode($value, $default = []): array
    {
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private static function monthDate(string $month): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new \InvalidArgumentException('Bulan simulasi tidak valid.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0))) {
            throw new \InvalidArgumentException('Bulan simulasi tidak valid.');
        }
        return $date;
    }

    private static function monthLabel(\DateTimeImmutable $date): string
    {
        $names = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        return $names[(int)$date->format('n') - 1] . ' ' . $date->format('Y');
    }

    private static function nextDate(\DateTimeImmutable $date, string $frequency, int $interval): \DateTimeImmutable
    {
        $interval = max(1, $interval);
        if ($frequency === 'daily') return $date->modify('+' . $interval . ' day');
        if ($frequency === 'weekly') return $date->modify('+' . $interval . ' week');
        return $date->modify('+' . $interval . ' month');
    }

    private static function currentAvailable(int $userId): array
    {
        $walletRows = (new Query())->from('{{%wallet}}')
            ->select(['legacy_id','type','initial_balance','reserved_balance','minimum_balance','extra_json'])
            ->where(['user_id'=>$userId,'archived'=>false])
            ->orderBy('legacy_id')
            ->all(self::db());

        $balances = [];
        $walletMeta = [];
        foreach ($walletRows as $w) {
            $id = (int)$w['legacy_id'];
            $extra = self::decode($w['extra_json']);
            $isCard = strtolower((string)$w['type']) === 'credit_card';
            $balances[$id] = $isCard ? -max(0, (int)($extra['opening_debt'] ?? 0)) : (int)$w['initial_balance'];
            $walletMeta[$id] = [
                'type'=>(string)$w['type'],
                'reserved'=>max(0,(int)$w['reserved_balance']),
                'minimum'=>max(0,(int)$w['minimum_balance']),
                'opening_debt'=>max(0,(int)($extra['opening_debt'] ?? 0)),
                'credit_limit'=>max(0,(int)($extra['credit_limit'] ?? 0)),
            ];
        }

        if (!$walletMeta) {
            return ['available'=>0,'gross'=>0,'protected'=>0,'credit_limit'=>0,'credit_debt'=>0,'credit_available'=>0];
        }

        $rows = (new Query())->from('{{%finance_transaction}}')
            ->select([
                'wallet_id',
                'income'=>new \yii\db\Expression("COALESCE(SUM(CASE WHEN type='income' THEN amount ELSE 0 END),0)"),
                'expense'=>new \yii\db\Expression("COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END),0)"),
            ])
            ->where(['user_id'=>$userId])
            ->groupBy('wallet_id')
            ->all(self::db());
        foreach ($rows as $r) {
            $id = (int)($r['wallet_id'] ?? 0);
            if ($id <= 0 || !array_key_exists($id, $balances)) continue;
            $balances[$id] += (int)$r['income'] - (int)$r['expense'];
        }

        $rows = (new Query())->from('{{%finance_transaction}}')
            ->select(['wallet_id'=>'from_wallet_id','total'=>new \yii\db\Expression('COALESCE(SUM(amount),0)')])
            ->where(['user_id'=>$userId,'type'=>'transfer'])
            ->andWhere(['not', ['from_wallet_id'=>null]])
            ->groupBy('from_wallet_id')
            ->all(self::db());
        foreach ($rows as $r) {
            $id = (int)$r['wallet_id'];
            if ($id > 0 && array_key_exists($id, $balances)) $balances[$id] -= (int)$r['total'];
        }

        $rows = (new Query())->from('{{%finance_transaction}}')
            ->select(['wallet_id'=>'to_wallet_id','total'=>new \yii\db\Expression('COALESCE(SUM(amount),0)')])
            ->where(['user_id'=>$userId,'type'=>'transfer'])
            ->andWhere(['not', ['to_wallet_id'=>null]])
            ->groupBy('to_wallet_id')
            ->all(self::db());
        foreach ($rows as $r) {
            $id = (int)$r['wallet_id'];
            if ($id > 0 && array_key_exists($id, $balances)) $balances[$id] += (int)$r['total'];
        }

        $available = 0;
        $gross = 0;
        $protected = 0;
        $creditLimit = 0;
        $creditDebt = 0;
        $creditAvailable = 0;

        foreach ($walletMeta as $id=>$meta) {
            $grossWallet = (int)($balances[$id] ?? 0);
            if ($meta['type'] === 'credit_card') {
                $debt = max(0, -$grossWallet);
                $creditLimit += $meta['credit_limit'];
                $creditDebt += $debt;
                $creditAvailable += max(0, $meta['credit_limit'] - $debt);
                continue;
            }
            $walletProtected = $meta['reserved'] + $meta['minimum'];
            $gross += $grossWallet;
            $protected += $walletProtected;
            $available += max(0, $grossWallet - $walletProtected);
        }

        return [
            'available'=>$available,
            'gross'=>$gross,
            'protected'=>$protected,
            'credit_limit'=>$creditLimit,
            'credit_debt'=>$creditDebt,
            'credit_available'=>$creditAvailable,
        ];
    }

    private static function history(int $userId, \DateTimeImmutable $targetStart, int $historyMonths): array
    {
        $historyMonths = max(3, min(12, $historyMonths));
        $currentMonthStart = new \DateTimeImmutable('first day of this month');
        $historyEnd = $targetStart->modify('-1 day');
        if ($historyEnd >= $currentMonthStart) $historyEnd = $currentMonthStart->modify('-1 day');
        $historyStart = $historyEnd->modify('-' . ($historyMonths - 1) . ' months')->modify('first day of this month');

        $months=[];
        $cursor=$historyStart;
        while ($cursor <= $historyEnd) {
            $months[$cursor->format('Y-m')] = ['month'=>$cursor->format('Y-m'),'income'=>0,'expense'=>0];
            $cursor=$cursor->modify('+1 month');
        }

        $q=(new Query())->from('{{%finance_transaction}} t')
            ->leftJoin('{{%wallet}} w','w.user_id=t.user_id AND w.legacy_id=t.wallet_id')
            ->select([
                'month'=>new \yii\db\Expression("DATE_FORMAT(t.transaction_date, '%Y-%m')"),
                'type'=>'t.type',
                'category'=>'t.category',
                'total'=>new \yii\db\Expression('SUM(t.amount)'),
                'count'=>new \yii\db\Expression('COUNT(*)'),
            ])
            ->where(['t.user_id'=>$userId])
            ->andWhere(['between','t.transaction_date',$historyStart->format('Y-m-d'),$historyEnd->format('Y-m-d')])
            ->andWhere(['in','t.type',['income','expense']])
            // Transaksi berulang dan transaksi yang sudah mewakili tagihan
            // dimasukkan kembali melalui jadwal target, bukan rata-rata historis.
            ->andWhere(['or',['is','t.source',null],['not in','t.source',['recurring','bill']]])
            ->andWhere(['or',['is','t.bill_id',null],['t.bill_id'=>0]])
            // Proyeksi pengeluaran variabel memakai transaksi harian.
            ->andWhere(['or',['t.type'=>'income'],['and',['t.type'=>'expense'],['or',['t.spending_kind'=>'daily'],['is','t.spending_kind',null]]]])
            // Belanja kartu kredit bukan arus keluar kas tunai saat transaksi terjadi;
            // kewajibannya akan muncul sebagai tagihan kartu jika memang jatuh tempo.
            ->andWhere(['or',['t.type'=>'income'],['not',['w.type'=>'credit_card']],['is','w.type',null]])
            ->groupBy([
                new \yii\db\Expression("DATE_FORMAT(t.transaction_date, '%Y-%m')"),
                't.type','t.category'
            ])
            ->orderBy(['month'=>SORT_ASC,'type'=>SORT_ASC,'total'=>SORT_DESC]);
        $rows=$q->all(self::db());

        $incomeCategories=[];
        $expenseCategories=[];
        $firstDataMonth=null;
        foreach($rows as $row){
            $month=(string)$row['month'];
            if (!isset($months[$month])) continue;
            $type=(string)$row['type'];
            $amount=(int)$row['total'];
            $category=trim((string)($row['category'] ?? ''));
            if($category==='') $category=$type==='income'?'Pemasukan lain':'Lainnya';
            if($firstDataMonth===null || $month<$firstDataMonth)$firstDataMonth=$month;
            $months[$month][$type]+=$amount;
            if($type==='income'){
                $incomeCategories[$category]['total']=($incomeCategories[$category]['total']??0)+$amount;
                $incomeCategories[$category]['months'][$month]=($incomeCategories[$category]['months'][$month]??0)+$amount;
                $incomeCategories[$category]['count']=($incomeCategories[$category]['count']??0)+(int)$row['count'];
            }else{
                $expenseCategories[$category]['total']=($expenseCategories[$category]['total']??0)+$amount;
                $expenseCategories[$category]['months'][$month]=($expenseCategories[$category]['months'][$month]??0)+$amount;
                $expenseCategories[$category]['count']=($expenseCategories[$category]['count']??0)+(int)$row['count'];
            }
        }

        if($firstDataMonth!==null){
            $usedStart=$historyStart;
            $candidate=\DateTimeImmutable::createFromFormat('!Y-m-d',$firstDataMonth.'-01');
            if($candidate!==false && $candidate>$usedStart)$usedStart=$candidate;
            $usedMonths=[];$c=$usedStart;
            while($c<=$historyEnd){$usedMonths[$c->format('Y-m')]=true;$c=$c->modify('+1 month');}
            $usedCount=max(1,count($usedMonths));
        }else{
            $usedCount=0;
        }

        $incomeSuggestions=[];
        foreach($incomeCategories as $name=>$data){
            $avg=$usedCount>0?(int)round($data['total']/$usedCount):0;
            if($avg<=0)continue;
            $incomeSuggestions[]=[
                'id'=>'history-income-'.sha1($name),
                'type'=>'income','name'=>$name,'category'=>$name,'amount'=>$avg,
                'source'=>'history','source_label'=>'Rata-rata riwayat','occurrences'=>(int)$data['count'],
            ];
        }
        $expenseSuggestions=[];
        foreach($expenseCategories as $name=>$data){
            $avg=$usedCount>0?(int)round($data['total']/$usedCount):0;
            if($avg<=0)continue;
            $expenseSuggestions[]=[
                'id'=>'history-expense-'.sha1($name),
                'type'=>'expense','name'=>$name,'category'=>$name,'amount'=>$avg,
                'source'=>'history','source_label'=>'Rata-rata riwayat','occurrences'=>(int)$data['count'],
            ];
        }
        usort($incomeSuggestions,static fn($a,$b)=>$b['amount']<=>$a['amount']);
        usort($expenseSuggestions,static fn($a,$b)=>$b['amount']<=>$a['amount']);
        $incomeSuggestions=array_slice($incomeSuggestions,0,15);
        $expenseSuggestions=array_slice($expenseSuggestions,0,20);

        $historyRows=array_values($months);
        $incomeTotal=array_sum(array_column($historyRows,'income'));
        $expenseTotal=array_sum(array_column($historyRows,'expense'));

        return [
            'months'=>$historyRows,
            'from'=>$historyStart->format('Y-m'),
            'to'=>$historyEnd->format('Y-m'),
            'used_months'=>$usedCount,
            'income_total'=>(int)$incomeTotal,
            'expense_total'=>(int)$expenseTotal,
            'income_average'=>$usedCount>0?(int)round($incomeTotal/$usedCount):0,
            'expense_average'=>$usedCount>0?(int)round($expenseTotal/$usedCount):0,
            'income_suggestions'=>$incomeSuggestions,
            'expense_suggestions'=>$expenseSuggestions,
        ];
    }

    private static function scheduledBills(int $userId, \DateTimeImmutable $targetStart, \DateTimeImmutable $targetEnd): array
    {
        $rows=(new Query())->from('{{%bill}}')
            ->where(['user_id'=>$userId,'active'=>true])
            ->orderBy('legacy_id')->all(self::db());
        $out=[];
        $targetMonth=$targetStart->format('Y-m');
        $daysIn=(int)$targetStart->format('t');
        foreach($rows as $r){
            $extra=self::decode($r['extra_json']);
            $bill=array_merge($extra,[
                'id'=>(int)$r['legacy_id'],'name'=>(string)$r['name'],'amount'=>(int)$r['amount'],
                'due_date'=>(string)($r['due_date']??''),'due_day'=>(int)($r['due_day']??0),
                'schedule_type'=>(string)$r['schedule_type'],'category'=>(string)($r['category']??''),
                'wallet_id'=>(int)($r['wallet_id']??0),'payments'=>self::decode($r['payments_json']),
                'active'=>(bool)$r['active'],'created_at'=>$r['created_at']??null,
            ]);
            $stored=trim((string)$bill['due_date']);
            $isAuto=!empty($bill['auto_generated']) && (($bill['bill_type']??'')==='credit_card');
            $due='';$paymentKey='';
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$stored)){
                if(substr($stored,0,7)!==$targetMonth)continue;
                $due=$stored;$paymentKey='due:'.$stored;
            }else{
                $day=min(max(1,(int)($bill['due_day']??1)),$daysIn);
                $due=$targetMonth.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT);
                $paymentKey=$targetMonth;
            }

            if($isAuto){
                $paid=0;
                foreach((array)($bill['payments']??[]) as $p)$paid+=max(0,(int)($p['amount']??0));
                $remaining=max(0,(int)$bill['amount']-$paid);
                if($remaining<=0)continue;
                $amount=$remaining;
            }else{
                if(isset($bill['payments'][$paymentKey]))continue;
                $amount=max(0,(int)$bill['amount']);
                if($amount<=0)continue;
            }
            $out[]=[
                'id'=>'bill-'.(int)$bill['id'],'type'=>'expense','name'=>(string)$bill['name'],
                'category'=>$bill['category']!==''?$bill['category']:'Tagihan','amount'=>$amount,
                'source'=>'bill','source_label'=>$isAuto?'Tagihan kartu kredit':'Tagihan / cicilan',
                'occurrences'=>1,'due_date'=>$due,'bill_id'=>(int)$bill['id'],
            ];
        }
        usort($out,static function($a,$b){$c=strcmp((string)$a['due_date'],(string)$b['due_date']);return $c!==0?$c:($b['amount']<=>$a['amount']);});
        return $out;
    }

    private static function scheduledRecurring(int $userId, \DateTimeImmutable $targetStart, \DateTimeImmutable $targetEnd): array
    {
        $rows=(new Query())->from('{{%recurring_transaction}}')
            ->where(['user_id'=>$userId,'active'=>true])
            ->orderBy('legacy_id')->all(self::db());
        $out=[];
        foreach($rows as $r){
            $next=trim((string)$r['next_run']);
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$next);
            if($date===false)continue;
            $frequency=(string)$r['frequency'];$interval=max(1,(int)$r['interval_value']);
            $guard=0;$occurrences=[];
            while($date<$targetStart && $guard++<500)$date=self::nextDate($date,$frequency,$interval);
            while($date<=$targetEnd && $guard++<500){
                $occurrences[]=$date->format('Y-m-d');
                $date=self::nextDate($date,$frequency,$interval);
            }
            if(!$occurrences)continue;
            $amount=max(0,(int)$r['amount'])*count($occurrences);
            if($amount<=0)continue;
            $type=(string)$r['type']==='income'?'income':'expense';
            $out[]=[
                'id'=>'recurring-'.(int)$r['legacy_id'],'type'=>$type,
                'name'=>(string)$r['name'],'category'=>(string)($r['category']??'') ?: ($type==='income'?'Pemasukan lain':'Lainnya'),
                'amount'=>$amount,'source'=>'recurring','source_label'=>'Transaksi berulang',
                'occurrences'=>count($occurrences),'dates'=>$occurrences,'recurring_id'=>(int)$r['legacy_id'],
            ];
        }
        usort($out,static function($a,$b){$c=strcmp((string)$a['type'],(string)$b['type']);return $c!==0?$c:($b['amount']<=>$a['amount']);});
        return $out;
    }

    public static function snapshot(int $userId, string $targetMonth, int $historyMonths=6): array
    {
        $targetStart=self::monthDate($targetMonth);
        $today=new \DateTimeImmutable('today');
        $currentMonthStart=$today->modify('first day of this month');
        if($targetStart<$currentMonthStart){
            throw new \InvalidArgumentException('Simulasi hanya dapat dibuat untuk bulan berjalan atau bulan berikutnya.');
        }
        $targetEnd=$targetStart->modify('last day of this month');
        $history=self::history($userId,$targetStart,$historyMonths);
        $bills=self::scheduledBills($userId,$targetStart,$targetEnd);
        $recurring=self::scheduledRecurring($userId,$targetStart,$targetEnd);
        $scheduledIncome=array_values(array_filter($recurring,static fn($x)=>$x['type']==='income'));
        $scheduledExpense=array_values(array_merge(
            array_filter($recurring,static fn($x)=>$x['type']==='expense'),
            $bills
        ));
        $current=self::currentAvailable($userId);
        return [
            'ok'=>true,
            'target_month'=>$targetStart->format('Y-m'),
            'target_month_label'=>self::monthLabel($targetStart),
            'history'=>$history,
            'scheduled'=>[
                'income'=>$scheduledIncome,
                'expense'=>$scheduledExpense,
            ],
            'current'=>$current,
            'rules'=>[
                'history_months'=>$historyMonths,
                'history_expense_mode'=>'daily_only',
                'scheduled_separate'=>true,
            ],
        ];
    }
}
