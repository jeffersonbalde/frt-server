<x-layouts.app title="Daily Sales Report">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.daily') }}" class="flex items-center gap-2">
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="input w-auto" onchange="this.form.requestSubmit()">
        </form>
        <a href="{{ route('reports.daily.export', ['date' => $date->toDateString()]) }}" class="btn btn-secondary" data-turbo="false">Export CSV</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Print / PDF</button>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">
        Per transaction · {{ $date->format('l, F j, Y') }} · all phones · closed days only.
        Total sales = cash from milling + tahop income. Diesel is taken from total sales first, then labor from what is left,
        split among the operators on duty.
    </p>

    @if ($open_sessions->isNotEmpty())
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Not included yet — End of Day not done on phone {{ $open_sessions->map(fn ($session) => $session->device->code)->implode(', ') }}.
            The report updates after the phone closes the day and syncs.
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 2xl:grid-cols-5">
        <x-stat label="Total sales" icon="cash" :value="money($totals['total_sales'])" tone="brand" :hint="'Milling cash '.money($totals['milling_cash']).' + tahop income '.money($totals['tahop_income'])" />
        <x-stat label="Rice milled" icon="scale" :value="kg($totals['kg']).' kg'" :hint="$totals['sacks'].' sacks · '.$totals['milling_count'].' customers'" />
        <x-stat label="Tahop" icon="leaf" :value="money($totals['tahop_income'])" :hint="'Income · sold '.kg($totals['tahop_kg_sold']).' kg · taken '.kg($totals['tahop_kg_bought']).' kg'" />
        <x-stat label="Diesel + Labor" icon="team" :value="money($totals['fuel'] + $totals['labor'])" :hint="'Diesel '.money($totals['fuel']).' · Labor '.money($totals['labor'])" />
        <x-stat label="Net amount" icon="trend" :value="money($totals['net'])" tone="success" />
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Trans. No</th>
                    <th>Time</th>
                    <th>Recorded By</th>
                    <th>Customer Name</th>
                    <th>Location</th>
                    <th>Service</th>
                    <th class="num">No. of Sack</th>
                    <th class="num">Qty. Kg</th>
                    <th class="num">Rate / Kg</th>
                    <th class="num">Total Amount</th>
                    <th class="num">Tahop Taken</th>
                    <th class="num">Adj.</th>
                    <th class="num">Amount Paid</th>
                    <th class="num">Cash</th>
                    <th class="num">Change</th>
                    <th class="num">Sales</th>
                    <th class="num">Diesel</th>
                    @foreach ($operators as $operator)
                        <th class="num">{{ $operator }}</th>
                    @endforeach
                    <th class="num">Net Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($t = $row['transaction'])
                    <tr @class(['text-slate-400 line-through' => $t->status !== 'valid'])>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="tabular-nums">{{ $t->receipt_no }}</td>
                        <td>{{ $t->recorded_at->format('h:i A') }}</td>
                        <td>{{ $t->recorder?->name ?? '—' }}</td>
                        <td>{{ $t->customer_name ?? '—' }}</td>
                        <td>{{ $t->location ?? '—' }}</td>
                        <td>
                            {{ $t->typeLabel() }}
                            @if ($t->status !== 'valid')
                                <span class="badge bg-red-100 text-red-700 no-underline">Void: {{ $t->void_reason }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500 no-underline">{{ $t->voided_at?->format('h:i A') }}{{ $t->voider ? ' · by '.$t->voider->name : '' }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $t->sack_count ?: '—' }}</td>
                        <td class="num">{{ kg($t->total_kg) }}</td>
                        <td class="num">{{ money($t->rate) }}</td>
                        <td class="num">{{ money($t->gross) }}</td>
                        <td class="num">{{ $t->tahop_offset ? '('.money($t->tahop_offset, false).')' : '—' }}</td>
                        <td class="num">{{ $t->adjustment ? '('.money($t->adjustment, false).')' : '—' }}</td>
                        <td class="num">{{ money($t->amount_due) }}</td>
                        <td class="num">{{ money($t->cash) }}</td>
                        <td class="num">{{ money($t->change) }}</td>
                        <td class="num" title="{{ $t->type === 'tahop_sale' ? 'Tahop income: sale minus buying price' : 'Cash paid for milling' }}">{{ money($row['sales']) }}</td>
                        <td class="num">{{ money($t->fuel) }}</td>
                        @foreach ($operators as $operator)
                            <td class="num">{{ isset($row['shares'][$operator]) ? money($row['shares'][$operator]) : '—' }}</td>
                        @endforeach
                        <td class="num font-semibold">{{ money($t->net) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 19 + count($operators) }}" class="py-8 text-center text-slate-500">No synced transactions for this date.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="7">Total (valid only)</td>
                        <td class="num">{{ $totals['sacks'] }}</td>
                        <td class="num">{{ kg($totals['kg']) }}</td>
                        <td></td>
                        <td class="num">{{ money($totals['milling_gross'] + $totals['tahop_sales']) }}</td>
                        <td class="num">({{ money($totals['tahop_bought'], false) }})</td>
                        <td class="num">({{ money($totals['adjustments'], false) }})</td>
                        <td class="num">{{ money($totals['amount_due']) }}</td>
                        <td colspan="2"></td>
                        <td class="num">{{ money($totals['total_sales']) }}</td>
                        <td class="num">{{ money($totals['fuel']) }}</td>
                        @foreach ($operators as $operator)
                            <td class="num">{{ money($totals['operator_shares'][$operator] ?? 0) }}</td>
                        @endforeach
                        <td class="num">{{ money($totals['net']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if ($sessions->isNotEmpty())
        <h2 class="section-title mt-8">End of Day per phone</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="col-no">#</th><th>Device</th><th>Recorder</th><th>Operators</th><th class="num">Opening Cash</th>
                        <th class="num">Milling Cash + Tahop Income</th><th class="num">Expected Cash</th><th class="num">Cash Counted</th>
                        <th class="num">Total Sales</th><th class="num">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                        <tr>
                            <td class="col-no">{{ $loop->iteration }}</td>
                            <td><a href="{{ route('closings.show', $session) }}" class="font-semibold text-brand-600 hover:underline">{{ $session->device->code }}</a></td>
                            <td>{{ $session->recorder?->name ?? '—' }}</td>
                            <td>{{ implode(', ', $session->operatorNames()) }}</td>
                            <td class="num">{{ money($session->opening_fund) }}</td>
                            <td class="num">{{ money($session->total_collected) }}</td>
                            <td class="num">{{ money($session->opening_fund + $session->total_collected) }}</td>
                            <td class="num">{{ money($session->cash_counted) }}</td>
                            <td class="num">{{ money($session->total_sales) }}</td>
                            <td @class(['num font-semibold', 'text-red-600' => $session->variance, 'text-green-700' => ! $session->variance])>{{ money($session->variance) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
