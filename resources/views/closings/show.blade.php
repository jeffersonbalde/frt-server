<x-layouts.app :title="'End of Day · '.$session->business_date->format('M j, Y').' · Phone '.$session->device->code">
    <x-slot:actions>
        <a href="{{ route('reports.daily', ['date' => $session->business_date->toDateString()]) }}" class="btn btn-secondary">Daily report</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Print / PDF</button>
    </x-slot:actions>

    @if ($session->reopen_count > 0)
        <div class="mb-6 flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">Reopened {{ $session->reopen_count }} {{ Str::plural('time', $session->reopen_count) }} after End of Day (approved with the owner code).</p>
                <p class="mt-1">
                    Latest: {{ $session->reopened_at?->format('M j, Y h:i A') ?? '—' }}
                    · by {{ $session->reopener?->name ?? '—' }}
                    · reason: {{ $session->reopen_reason ?? '—' }}
                    @if ($session->reopen_previous_variance !== null)
                        · variance before: {{ money($session->reopen_previous_variance) }}
                    @endif
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3 [&>*]:min-w-0">
        <section class="card">
            <h2 class="mb-3 font-semibold text-slate-900">Start of Day</h2>
            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-slate-500">Recorder</dt><dd>{{ $session->recorder?->name ?? '—' }}</dd>
                <dt class="text-slate-500">Started</dt><dd>{{ $session->started_at->format('h:i A') }}</dd>
                <dt class="text-slate-500">Rice per kg</dt><dd>{{ money($session->rice_rate) }}</dd>
                <dt class="text-slate-500">Tahop per kg (buy)</dt><dd>{{ money($session->tahop_buy_rate) }}</dd>
                <dt class="text-slate-500">Tahop per kg (sell)</dt><dd>{{ money($session->tahop_sell_rate) }}</dd>
                <dt class="text-slate-500">Oil / gas price</dt><dd>{{ money($session->fuel_price) }}</dd>
                <dt class="text-slate-500">Opening cash</dt><dd>{{ money($session->opening_fund) }}</dd>
                <dt class="text-slate-500">Labor / Fuel</dt><dd>{{ $session->labor_pct }}% / {{ $session->fuel_pct }}%</dd>
                <dt class="text-slate-500">Operators</dt><dd>{{ implode(', ', $session->operatorNames()) ?: '—' }}</dd>
            </dl>
        </section>

        <section class="card">
            <h2 class="mb-3 font-semibold text-slate-900">Cash breakdown</h2>
            @if ($session->status === 'closed')
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-slate-500"><th class="w-6 pr-2 pb-1 font-medium">#</th><th class="pr-2 pb-1 font-medium">Pcs</th><th class="pr-2 pb-1 font-medium">Bill</th><th class="pb-1 text-right font-medium">Amount</th></tr></thead>
                    <tbody>
                        @foreach (collect($session->cash_count ?? [])->filter(fn ($pieces) => $pieces > 0)->sortKeysDesc() as $denomination => $pieces)
                            <tr class="border-t border-slate-100">
                                <td class="pr-2 py-0.5 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="pr-2 tabular-nums">{{ $pieces }}</td>
                                <td class="pr-2 tabular-nums whitespace-nowrap">{{ money((int) $denomination) }}</td>
                                <td class="text-right tabular-nums">{{ money($pieces * (int) $denomination) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="border-t border-slate-300 font-semibold"><td colspan="3">Total cash</td><td class="text-right tabular-nums">{{ money($session->cash_counted) }}</td></tr></tfoot>
                </table>
                </div>
            @else
                <p class="text-sm text-slate-500">This day has not been closed on the phone yet.</p>
            @endif
        </section>

        <section class="card">
            <h2 class="mb-3 font-semibold text-slate-900">Cash check</h2>
            @if ($session->status === 'closed')
                <dl class="grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">Opening cash</dt><dd class="text-right tabular-nums">{{ money($session->opening_fund) }}</dd>
                    <dt class="text-slate-500">+ Milling cash + tahop income</dt><dd class="text-right tabular-nums">{{ money($session->total_collected) }}</dd>
                    <dt class="font-semibold">Expected cash</dt><dd class="text-right font-semibold tabular-nums">{{ money($session->opening_fund + $session->total_collected) }}</dd>
                    <dt class="font-semibold">Cash counted</dt><dd class="text-right font-semibold tabular-nums">{{ money($session->cash_counted) }}</dd>
                    <dt class="text-slate-500">Total sales</dt><dd class="text-right tabular-nums">{{ money($session->total_sales) }}</dd>
                    <dt class="text-slate-500">Tahop taken (not income)</dt><dd class="text-right tabular-nums">{{ money($session->tahop_in_kind) }}</dd>
                    <dt class="font-semibold">Variance</dt>
                    <dd @class(['text-right text-lg font-bold tabular-nums', 'text-red-600' => $session->variance, 'text-green-700' => ! $session->variance])>{{ money($session->variance) }}</dd>
                </dl>
                @if ($session->remarks)
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><span class="font-semibold">Remarks:</span> {{ $session->remarks }}</p>
                @endif
                <p class="mt-3 text-xs text-slate-500">Closed {{ $session->closed_at?->format('M j, Y h:i A') }}</p>
            @else
                <span class="badge bg-amber-100 text-amber-800">Open</span>
            @endif
        </section>
    </div>

    <h2 class="section-title mt-8">Transactions</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th><th>Trans. No</th><th>Time</th><th>Recorded By</th><th>Customer</th><th>Service</th><th class="num">Kg</th>
                    <th class="num">Total Amount</th><th class="num">Tahop Offset</th><th class="num">Adj.</th><th class="num">Amount Due</th><th class="num">Cash</th><th class="num">Change</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($session->transactions as $t)
                    <tr @class(['text-slate-400 line-through' => $t->status !== 'valid'])>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="tabular-nums">{{ $t->receipt_no }}</td>
                        <td>{{ $t->recorded_at->format('h:i A') }}</td>
                        <td>{{ $t->recorder?->name ?? '—' }}</td>
                        <td>{{ $t->customer_name ?? '—' }}</td>
                        <td>
                            {{ $t->typeLabel() }}
                            @if ($t->status !== 'valid')
                                <span class="badge bg-red-100 text-red-700">Void: {{ $t->void_reason }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $t->voided_at?->format('h:i A') }}{{ $t->voider ? ' · by '.$t->voider->name : '' }}</span>
                            @endif
                        </td>
                        <td class="num">{{ kg($t->total_kg) }}</td>
                        <td class="num">{{ money($t->gross) }}</td>
                        <td class="num">{{ money($t->tahop_offset) }}</td>
                        <td class="num">{{ money($t->adjustment) }}</td>
                        <td class="num">{{ money($t->amount_due) }}</td>
                        <td class="num">{{ money($t->cash) }}</td>
                        <td class="num">{{ money($t->change) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="py-6 text-center text-slate-500">No transactions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
