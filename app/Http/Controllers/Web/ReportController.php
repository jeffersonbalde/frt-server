<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function daily(Request $request): View
    {
        $date = $this->date($request);

        return view('reports.daily', ['date' => $date, ...$this->reports->daily($date)]);
    }

    public function dailyExport(Request $request): StreamedResponse
    {
        $date = $this->date($request);
        $report = $this->reports->daily($date);
        $operators = $report['operators'];

        $header = [
            'Trans. No', 'Date', 'Time', 'Device', 'Recorded By', 'Customer Name', 'Location', 'Service', 'No. of Sack', 'Qty. Kg',
            'Rate per Kg', 'Total Amount', 'Tahop Kg Taken', 'Tahop Taken', 'Adjustment', 'Amount Paid', 'Cash',
            'Change', 'Sales', 'Diesel/Other Charges', ...array_map(fn ($name) => "$name (labor)", $operators), 'Net Amount', 'Status',
        ];

        $rows = $report['rows']->map(function (array $row) use ($operators) {
            /** @var Transaction $transaction */
            $transaction = $row['transaction'];

            return [
                $transaction->receipt_no,
                $transaction->business_date->toDateString(),
                $transaction->recorded_at->format('h:i A'),
                $transaction->device->code,
                $transaction->recorder?->name,
                $transaction->customer_name,
                $transaction->location,
                $transaction->typeLabel(),
                $transaction->sack_count,
                $transaction->total_kg,
                money_plain($transaction->rate),
                money_plain($transaction->gross),
                $transaction->tahop_kg,
                money_plain($transaction->tahop_offset),
                money_plain($transaction->adjustment),
                money_plain($transaction->amount_due),
                money_plain($transaction->cash),
                money_plain($transaction->change),
                money_plain($row['sales']),
                money_plain($transaction->fuel),
                ...array_map(fn ($name) => money_plain($row['shares'][$name] ?? 0), $operators),
                money_plain($transaction->net),
                $transaction->status === 'valid'
                    ? 'Valid'
                    : trim('Void: '.$transaction->void_reason.' ('.$transaction->voided_at?->format('h:i A').($transaction->voider ? ', by '.$transaction->voider->name : '').')'),
            ];
        });

        return $this->csv("frt-daily-{$date->toDateString()}.csv", $header, $rows->all());
    }

    public function monthly(Request $request): View
    {
        $month = $this->month($request);

        return view('reports.monthly', ['month' => $month, ...$this->reports->monthly($month)]);
    }

    public function monthlyExport(Request $request): StreamedResponse
    {
        $month = $this->month($request);
        $report = $this->reports->monthly($month);

        $header = [
            'Date', 'No. of Customer', 'No. of Location', 'Transactions', 'Total No. of Sack', 'Total Qty. Kg',
            'Milling Amount', 'Tahop Taken', 'Adjustments', 'Milling Cash', 'Tahop Sales', 'Tahop Income', 'Total Sales',
            'Diesel/Other Charges', 'Labor', 'Net Amount', 'Total Variance',
        ];

        $rows = $report['rows']->map(fn (array $row) => [
            $row['date']->toDateString(),
            $row['customers'],
            $row['locations'],
            $row['transactions'],
            $row['sacks'],
            $row['kg'],
            money_plain($row['milling_gross']),
            money_plain($row['tahop_bought']),
            money_plain($row['adjustments']),
            money_plain($row['milling_cash']),
            money_plain($row['tahop_sales']),
            money_plain($row['tahop_income']),
            money_plain($row['total_sales']),
            money_plain($row['fuel']),
            money_plain($row['labor']),
            money_plain($row['net']),
            money_plain($row['variance']),
        ]);

        return $this->csv("frt-monthly-{$month->format('Y-m')}.csv", $header, $rows->all());
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

    /**
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    private function csv(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, $row, escape: '');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
