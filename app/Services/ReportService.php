<?php

namespace App\Services;

use App\Models\DaySession;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the Daily (per transaction) and Monthly (per day) sales reports from the FRT System PPT, slide 18,
 * consolidated across all devices. All money values are in centavos.
 *
 * Client rules (meeting notes):
 *  - Tahop taken from a customer is not income; it only lowers the cash the customer pays.
 *  - Tahop income = kg sold × (sell price − buy price).
 *  - Total Sales = cash from milling + tahop income. Each transaction's share is diesel + labor + net.
 *  - Only days that went through End of Day are counted in reports.
 */
class ReportService
{
    /**
     * Split an amount into equal shares; leftover centavos go to the first shares.
     *
     * @return list<int>
     */
    public static function splitShares(int $amount, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $base = intdiv($amount, $count);
        $remainder = $amount - ($base * $count);

        return array_map(fn (int $index) => $base + ($index < $remainder ? 1 : 0), range(0, $count - 1));
    }

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, operators: list<string>, totals: array<string, mixed>, sessions: Collection<int, DaySession>, open_sessions: Collection<int, DaySession>}
     */
    public function daily(Carbon $date): array
    {
        $allSessions = DaySession::query()
            ->with(['device', 'recorder'])
            ->whereDate('business_date', $date)
            ->orderBy('device_id')
            ->get();

        $sessions = $allSessions->where('status', 'closed')->values();

        $transactions = $this->closedDayTransactions()
            ->with(['device', 'daySession', 'voider', 'recorder'])
            ->whereDate('business_date', $date)
            ->orderBy('recorded_at')
            ->get();

        $operators = $sessions->flatMap(fn (DaySession $session) => $session->operatorNames())->unique()->values()->all();

        $rows = $transactions->map(function (Transaction $transaction) {
            $shares = [];

            if ($transaction->status === 'valid') {
                $names = $transaction->daySession->operatorNames();
                $shares = array_combine($names, self::splitShares($transaction->labor, count($names))) ?: [];
            }

            return [
                'transaction' => $transaction,
                'sales' => $transaction->labor + $transaction->fuel + $transaction->net,
                'shares' => $shares,
            ];
        });

        $totals = [
            ...$this->sumTransactions($transactions->where('status', 'valid')),
            'variance' => (int) $sessions->sum('variance'),
            'operator_shares' => $this->operatorShares($rows),
        ];

        return [
            'rows' => $rows,
            'operators' => $operators,
            'totals' => $totals,
            'sessions' => $sessions,
            'open_sessions' => $allSessions->where('status', 'open')->values(),
        ];
    }

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, mixed>, operators: Collection<string, array{days: int, share: int}>, open_days: int}
     */
    public function monthly(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $transactions = $this->closedDayTransactions()
            ->with('daySession')
            ->valid()
            ->whereBetween('business_date', [$start, $end])
            ->get();

        $sessions = DaySession::query()
            ->where('status', 'closed')
            ->whereBetween('business_date', [$start, $end])
            ->get();

        $rows = $transactions
            ->groupBy(fn (Transaction $transaction) => $transaction->business_date->toDateString())
            ->sortKeys()
            ->map(function (Collection $dayTransactions, string $date) use ($sessions) {
                $daySessions = $sessions->filter(fn (DaySession $session) => $session->business_date->toDateString() === $date);

                return [
                    'date' => Carbon::parse($date),
                    'customers' => $dayTransactions->pluck('customer_name')->filter()->unique()->count(),
                    'locations' => $dayTransactions->pluck('location')->filter()->unique()->count(),
                    'variance' => (int) $daySessions->sum('variance'),
                    ...$this->sumTransactions($dayTransactions),
                ];
            })
            ->values();

        $operatorTotals = collect();

        foreach ($transactions as $transaction) {
            $names = $transaction->daySession->operatorNames();

            foreach (self::splitShares($transaction->labor, count($names)) as $index => $share) {
                $name = $names[$index];
                $current = $operatorTotals->get($name, ['days' => [], 'share' => 0]);
                $current['share'] += $share;
                $current['days'][$transaction->business_date->toDateString()] = true;
                $operatorTotals->put($name, $current);
            }
        }

        $operators = $operatorTotals
            ->map(fn (array $value) => ['days' => count($value['days']), 'share' => $value['share']])
            ->sortKeys();

        return [
            'rows' => $rows,
            'totals' => [
                ...$this->sumTransactions($transactions),
                'variance' => (int) $sessions->sum('variance'),
                'days' => $rows->count(),
            ],
            'operators' => $operators,
            'open_days' => DaySession::query()
                ->where('status', 'open')
                ->whereBetween('business_date', [$start, $end])
                ->count(),
        ];
    }

    /**
     * Labor share per operator between two dates (inclusive), e.g. a kinsenas or a week.
     *
     * Each valid transaction's labor is split equally among the operators on duty that day, exactly as on the
     * Daily Report. Days that went through End of Day are final; days still open are included but kept apart
     * as "not final", because their amounts can still change.
     *
     * @return array{operators: list<array{name: string, days: int, final: int, pending: int, total: int}>, days: list<array{date: Carbon, final: bool, shares: array<string, int>, total: int}>, totals: array{final: int, pending: int, total: int}, open_dates: list<Carbon>}
     */
    public function laborShares(Carbon $from, Carbon $to): array
    {
        // whereDate on both ends so the last day counts on every database (SQLite stores the date with a time).
        $inRange = fn (Builder $query) => $query->whereDate('business_date', '>=', $from)->whereDate('business_date', '<=', $to);

        $sessions = DaySession::query()->where($inRange)->get();
        $transactions = Transaction::query()
            ->with('daySession')
            ->valid()
            ->where($inRange)
            ->get();

        // A day is final once every phone that worked that day has closed it.
        $openDates = $sessions->where('status', 'open')
            ->map(fn (DaySession $session) => $session->business_date->toDateString())
            ->unique()
            ->sort()
            ->values();

        /** @var array<string, array<string, int>> $byDate date => operator => share */
        $byDate = [];
        /** @var array<string, array<string, true>> $dutyDates operator => dates on duty */
        $dutyDates = [];

        foreach ($sessions as $session) {
            $date = $session->business_date->toDateString();
            $byDate[$date] ??= [];
            foreach ($session->operatorNames() as $name) {
                $dutyDates[$name][$date] = true;
                $byDate[$date][$name] ??= 0;
            }
        }

        foreach ($transactions as $transaction) {
            $date = $transaction->business_date->toDateString();
            $names = $transaction->daySession->operatorNames();

            foreach (self::splitShares($transaction->labor, count($names)) as $index => $share) {
                $byDate[$date][$names[$index]] = ($byDate[$date][$names[$index]] ?? 0) + $share;
            }
        }

        ksort($byDate);
        $open = array_flip($openDates->all());

        $days = [];
        $operators = [];

        foreach ($byDate as $date => $shares) {
            ksort($shares);
            $final = ! isset($open[$date]);
            $days[] = ['date' => Carbon::parse($date), 'final' => $final, 'shares' => $shares, 'total' => array_sum($shares)];

            foreach ($shares as $name => $share) {
                $operators[$name] ??= ['name' => $name, 'days' => count($dutyDates[$name] ?? []), 'final' => 0, 'pending' => 0, 'total' => 0];
                $operators[$name][$final ? 'final' : 'pending'] += $share;
                $operators[$name]['total'] += $share;
            }
        }

        ksort($operators);
        $operators = array_values($operators);

        return [
            'operators' => $operators,
            'days' => $days,
            'totals' => [
                'final' => array_sum(array_column($operators, 'final')),
                'pending' => array_sum(array_column($operators, 'pending')),
                'total' => array_sum(array_column($operators, 'total')),
            ],
            'open_dates' => $openDates->map(fn (string $date) => Carbon::parse($date))->all(),
        ];
    }

    /**
     * Totals for closed days in a date range (the dashboard).
     *
     * @return array<string, int|float>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $transactions = $this->closedDayTransactions()
            ->valid()
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return [
            ...$this->sumTransactions($transactions),
            'variance' => (int) DaySession::query()
                ->where('status', 'closed')
                ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()])
                ->sum('variance'),
        ];
    }

    /**
     * Tahop still on hand across all phones: everything taken from customers minus everything sold.
     *
     * @return array{kg_taken: float, kg_sold: float, kg_on_hand: float}
     */
    public function tahopStock(): array
    {
        $kgTaken = (float) Transaction::query()->valid()->where('type', Transaction::TYPE_MILLING)->sum('tahop_kg');
        $kgSold = (float) Transaction::query()->valid()->where('type', Transaction::TYPE_TAHOP_SALE)->sum('total_kg');

        return [
            'kg_taken' => round($kgTaken, 2),
            'kg_sold' => round($kgSold, 2),
            'kg_on_hand' => round($kgTaken - $kgSold, 2),
        ];
    }

    /**
     * @return Builder<Transaction>
     */
    private function closedDayTransactions(): Builder
    {
        return Transaction::query()->whereHas('daySession', fn (Builder $query) => $query->where('status', 'closed'));
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, int|float>
     */
    private function sumTransactions(Collection $transactions): array
    {
        $milling = $transactions->where('type', Transaction::TYPE_MILLING);
        $tahopSales = $transactions->where('type', Transaction::TYPE_TAHOP_SALE);

        $tahopSalesAmount = (int) $tahopSales->sum('gross');
        $tahopIncome = (int) $tahopSales->sum(fn (Transaction $transaction) => $transaction->labor + $transaction->fuel + $transaction->net);

        return [
            'transactions' => $transactions->count(),
            'milling_count' => $milling->count(),
            'tahop_sale_count' => $tahopSales->count(),
            'sacks' => (int) $milling->sum('sack_count'),
            'kg' => round((float) $milling->sum('total_kg'), 2),
            'milling_gross' => (int) $milling->sum('gross'),
            'tahop_bought' => (int) $milling->sum('tahop_offset'),
            'tahop_kg_bought' => round((float) $milling->sum('tahop_kg'), 2),
            'adjustments' => (int) $transactions->sum('adjustment'),
            'milling_cash' => (int) $milling->sum('amount_due'),
            'tahop_sales' => $tahopSalesAmount,
            'tahop_kg_sold' => round((float) $tahopSales->sum('total_kg'), 2),
            'tahop_cost' => $tahopSalesAmount - $tahopIncome,
            'tahop_income' => $tahopIncome,
            'total_sales' => (int) $transactions->sum(fn (Transaction $transaction) => $transaction->labor + $transaction->fuel + $transaction->net),
            'amount_due' => (int) $transactions->sum('amount_due'),
            'labor' => (int) $transactions->sum('labor'),
            'fuel' => (int) $transactions->sum('fuel'),
            'net' => (int) $transactions->sum('net'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function operatorShares(Collection $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            foreach ($row['shares'] as $name => $share) {
                $totals[$name] = ($totals[$name] ?? 0) + $share;
            }
        }

        ksort($totals);

        return $totals;
    }
}
