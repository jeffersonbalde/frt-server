<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReportService;
use App\Support\ExcelExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
