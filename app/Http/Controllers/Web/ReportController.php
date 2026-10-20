<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Support\ExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function daily(Request $request): View
    {
        $date = $this->date($request);

        return view('reports.daily', ['date' => $date, ...$this->reports->daily($date)]);
    }

    public function dailyExport(Request $request): BinaryFileResponse
    {
        $date = $this->date($request);
        $report = $this->reports->daily($date);
        $operators = $report['operators'];

        $columns = [
            ['Trans. No', 'text'], ['Date', 'date'], ['Time', 'time'], ['Device', 'text'], ['Recorded By', 'text'],
            ['Customer Name', 'text'], ['Location', 'text'], ['Service', 'text'], ['No. of Sack', 'int'], ['Qty. Kg', 'decimal'],
            ['Rate per Kg', 'rate'], ['Total Amount', 'money'], ['Tahop Kg Taken', 'decimal'], ['Tahop Taken', 'money'],
            ['Adjustment', 'money'], ['Amount Paid', 'money'], ['Cash', 'money'], ['Change', 'money'], ['Sales', 'money'],
            ['Diesel/Other Charges', 'money'], ...array_map(fn ($name) => ["$name (labor)", 'money'], $operators),
            ['Net Amount', 'money'], ['Status', 'text'],
        ];

        $rows = $report['rows']->map(function (array $row) use ($operators) {
            /** @var Transaction $transaction */
            $transaction = $row['transaction'];

            return [
                $transaction->receipt_no,
                $transaction->business_date,
                $transaction->recorded_at,
                $transaction->device->code,
                $transaction->recorder?->name,
                $transaction->customer_name,
                $transaction->location,
                $transaction->typeLabel(),
                $transaction->sack_count,
                $transaction->total_kg,
                $this->pesos($transaction->rate),
                $this->pesos($transaction->gross),
                $transaction->tahop_kg,
                $this->pesos($transaction->tahop_offset),
                $this->pesos($transaction->adjustment),
                $this->pesos($transaction->amount_due),
                $this->pesos($transaction->cash),
                $this->pesos($transaction->change),
                $this->pesos($row['sales']),
                $this->pesos($transaction->fuel),
                ...array_map(fn ($name) => $this->pesos($row['shares'][$name] ?? 0), $operators),
                $this->pesos($transaction->net),
                $transaction->status === 'valid'
                    ? 'Valid'
                    : trim('Void: '.$transaction->void_reason.' ('.$transaction->voided_at?->format('h:i A').($transaction->voider ? ', by '.$transaction->voider->name : '').')'),
            ];
        });

        $valid = $report['rows']->map(fn (array $row) => $row['transaction']->status === 'valid');

        return (new ExcelExport("Daily {$date->format('M j, Y')}", $columns, $rows->all(), $valid->all()))
            ->download("frt-daily-{$date->toDateString()}.xlsx");
    }

    public function monthly(Request $request): View
    {
        $month = $this->month($request);

        return view('reports.monthly', ['month' => $month, ...$this->reports->monthly($month)]);
    }

    public function monthlyExport(Request $request): BinaryFileResponse
    {
        $month = $this->month($request);
        $report = $this->reports->monthly($month);

        $columns = [
            ['Date', 'date'], ['No. of Customer', 'int'], ['No. of Location', 'int'], ['Transactions', 'int'],
            ['Total No. of Sack', 'int'], ['Total Qty. Kg', 'decimal'], ['Milling Amount', 'money'], ['Tahop Taken', 'money'],
            ['Adjustments', 'money'], ['Milling Cash', 'money'], ['Tahop Sales', 'money'], ['Tahop Income', 'money'],
            ['Total Sales', 'money'], ['Diesel/Other Charges', 'money'], ['Labor', 'money'], ['Net Amount', 'money'],
            ['Total Variance', 'money'],
        ];

        $rows = $report['rows']->map(fn (array $row) => [
            $row['date'],
            $row['customers'],
            $row['locations'],
            $row['transactions'],
            $row['sacks'],
            $row['kg'],
            $this->pesos($row['milling_gross']),
            $this->pesos($row['tahop_bought']),
            $this->pesos($row['adjustments']),
            $this->pesos($row['milling_cash']),
            $this->pesos($row['tahop_sales']),
            $this->pesos($row['tahop_income']),
            $this->pesos($row['total_sales']),
            $this->pesos($row['fuel']),
            $this->pesos($row['labor']),
            $this->pesos($row['net']),
            $this->pesos($row['variance']),
        ]);

        return (new ExcelExport($month->format('F Y'), $columns, $rows->values()->all()))
            ->download("frt-monthly-{$month->format('Y-m')}.xlsx");
    }

    public function labor(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('reports.labor', [
            'from' => $from,
            'to' => $to,
            'presets' => $this->presets(),
            ...$this->reports->laborShares($from, $to),
        ]);
    }

    public function laborExport(Request $request): BinaryFileResponse
    {
        [$from, $to] = $this->range($request);
        $report = $this->reports->laborShares($from, $to);
        $names = array_column($report['operators'], 'name');

        $columns = [
            ['Date', 'date'],
            ...array_map(fn (string $name) => [$name, 'money'], $names),
            ['Day total', 'money'],
        ];

        $rows = array_map(fn (array $day) => [
            $day['date'],
            ...array_map(fn (string $name) => isset($day['shares'][$name]) ? $this->pesos($day['shares'][$name]) : null, $names),
            $this->pesos($day['total']),
        ], $report['days']);

        return (new ExcelExport("Labor {$from->format('M j')} - {$to->format('M j, Y')}", $columns, $rows))
            ->download("frt-labor-report-{$from->toDateString()}-to-{$to->toDateString()}.xlsx");
    }

    /**
     * The From–To dates of the labor report. Defaults to this month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'to.after_or_equal' => 'The "To" date must be on or after the "From" date.',
        ]);

        [$from, $to] = $this->presets()['this_month']['range'];

        if (isset($validated['from'])) {
            $from = Carbon::parse($validated['from']);
            $to = $from->copy();
        }
        if (isset($validated['to'])) {
            $to = Carbon::parse($validated['to']);
        }
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to, true) > 366) {
            throw ValidationException::withMessages(['to' => 'Choose a period of one year or less.']);
        }

        return [$from, $to];
    }

    /**
     * Quick ranges for the labor report. Weeks run Monday–Sunday.
     *
     * @return array<string, array{label: string, range: array{0: Carbon, 1: Carbon}}>
     */
    private function presets(): array
    {
        $today = today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $lastMonth = $today->copy()->startOfMonth()->subMonthNoOverflow();

        return [
            'this_week' => ['label' => 'This week', 'range' => [$weekStart, $weekStart->copy()->addDays(6)]],
            'last_week' => ['label' => 'Last week', 'range' => [$weekStart->copy()->subWeek(), $weekStart->copy()->subDay()]],
            'this_month' => ['label' => 'This month', 'range' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()]],
            'last_month' => ['label' => 'Last month', 'range' => [$lastMonth, $lastMonth->copy()->endOfMonth()->startOfDay()]],
        ];
    }

    private function date(Request $request): Carbon
    {
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return isset($validated['date']) ? Carbon::parse($validated['date']) : today();
    }

    private function month(Request $request): Carbon
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return isset($validated['month']) ? Carbon::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfDay() : today()->startOfMonth();
    }

    /** Centavos as pesos, for Excel. */
    private function pesos(int|float|null $centavos): float
    {
        return round(((int) ($centavos ?? 0)) / 100, 2);
    }
}
