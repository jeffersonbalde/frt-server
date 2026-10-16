<x-layouts.app title="Monthly Sales Report">
    <x-slot:actions>
        <form method="GET" action="{{ route('reports.monthly') }}" class="flex items-center gap-2">
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input w-auto" onchange="this.form.requestSubmit()">
        </form>
        <a href="{{ route('reports.monthly.export', ['month' => $month->format('Y-m')]) }}" class="btn btn-secondary" data-turbo="false">Export CSV</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Print / PDF</button>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">Per day · {{ $month->format('F Y') }} · all phones · closed days only · valid transactions only.</p>

    @if ($open_days > 0)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $open_days }} {{ Str::plural('day', $open_days) }} this month still waiting for End of Day {{ $open_days === 1 ? 'is' : 'are' }} not included.
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 2xl:grid-cols-5">
        <x-stat label="Total sales" icon="cash" :value="money($totals['total_sales'])" tone="brand" :hint="$totals['days'].' operating days'" />
        <x-stat label="Rice milled" icon="scale" :value="kg($totals['kg']).' kg'" :hint="number_format($totals['sacks']).' sacks · '.$totals['milling_count'].' transactions'" />
        <x-stat label="Avg kg / transaction" icon="chart" :value="$totals['milling_count'] ? kg($totals['kg'] / $totals['milling_count']).' kg' : '—'" />
        <x-stat label="Net amount" icon="trend" :value="money($totals['net'])" tone="success" :hint="'Avg '.($totals['milling_count'] ? money(intdiv($totals['net'], $totals['milling_count'])) : '—').' / milling'" />
        <x-stat label="Total variance" icon="alert" :value="money($totals['variance'])" :tone="$totals['variance'] === 0 ? 'default' : 'danger'" />
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Date</th>
                    <th class="num">No. of Customer</th>
                    <th class="num">No. of Location</th>
                    <th class="num">Total No. of Sack</th>
                    <th class="num">Total Qty. Kg</th>
                    <th class="num">Milling Amount</th>
                    <th class="num">Tahop Taken</th>
                    <th class="num">Adj.</th>
                    <th class="num">Milling Cash</th>
                    <th class="num">Tahop Sales</th>
                    <th class="num">Tahop Income</th>
                    <th class="num">Total Sales</th>
                    <th class="num">Diesel</th>
                    <th class="num">Labor</th>
                    <th class="num">Net Amount</th>
                    <th class="num">Variance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td><a href="{{ route('reports.daily', ['date' => $row['date']->toDateString()]) }}" class="text-brand-600 hover:underline">{{ $row['date']->format('M j, D') }}</a></td>
                        <td class="num">{{ $row['customers'] }}</td>
                        <td class="num">{{ $row['locations'] }}</td>
                        <td class="num">{{ number_format($row['sacks']) }}</td>
                        <td class="num">{{ kg($row['kg']) }}</td>
                        <td class="num">{{ money($row['milling_gross']) }}</td>
                        <td class="num">({{ money($row['tahop_bought'], false) }})</td>
                        <td class="num">({{ money($row['adjustments'], false) }})</td>
                        <td class="num">{{ money($row['milling_cash']) }}</td>
                        <td class="num">{{ money($row['tahop_sales']) }}</td>
                        <td class="num">{{ money($row['tahop_income']) }}</td>
                        <td class="num font-semibold">{{ money($row['total_sales']) }}</td>
                        <td class="num">{{ money($row['fuel']) }}</td>
                        <td class="num">{{ money($row['labor']) }}</td>
                        <td class="num font-semibold">{{ money($row['net']) }}</td>
                        <td @class(['num', 'font-semibold text-red-600' => $row['variance'] !== 0])>{{ money($row['variance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="17" class="py-8 text-center text-slate-500">No closed days with transactions this month.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        <td colspan="2"></td>
                        <td class="num">{{ number_format($totals['sacks']) }}</td>
                        <td class="num">{{ kg($totals['kg']) }}</td>
                        <td class="num">{{ money($totals['milling_gross']) }}</td>
                        <td class="num">({{ money($totals['tahop_bought'], false) }})</td>
                        <td class="num">({{ money($totals['adjustments'], false) }})</td>
                        <td class="num">{{ money($totals['milling_cash']) }}</td>
                        <td class="num">{{ money($totals['tahop_sales']) }}</td>
                        <td class="num">{{ money($totals['tahop_income']) }}</td>
                        <td class="num">{{ money($totals['total_sales']) }}</td>
                        <td class="num">{{ money($totals['fuel']) }}</td>
                        <td class="num">{{ money($totals['labor']) }}</td>
                        <td class="num">{{ money($totals['net']) }}</td>
                        <td class="num">{{ money($totals['variance']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <h2 class="section-title mt-8">Labor share per operator</h2>
    <div class="table-wrap max-w-xl">
        <table class="table">
            <thead><tr><th class="col-no">#</th><th>Operator</th><th class="num">Days on duty</th><th class="num">Labor share</th></tr></thead>
            <tbody>
                @forelse ($operators as $name => $operator)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td>{{ $name }}</td>
                        <td class="num">{{ $operator['days'] }}</td>
                        <td class="num font-semibold">{{ money($operator['share']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500">No labor recorded this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
