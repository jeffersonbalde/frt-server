@php
    $period = $from->isSameDay($to) ? $from->format('M j, Y') : $from->format('M j').' – '.$to->format('M j, Y');
    $query = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $names = array_column($operators, 'name');
@endphp

<x-layouts.app title="Labor Shares">
    <x-slot:actions>
        <a href="{{ route('reports.labor.export', $query) }}" class="btn btn-secondary" data-turbo="false">Export Excel</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Print / PDF</button>
    </x-slot:actions>

    {{-- Period: any From–To dates, or a quick range --}}
    <div class="card mb-6 print:hidden">
        <form method="GET" action="{{ route('reports.labor') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1 sm:flex-none">
                <label for="from" class="label">From</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="input sm:w-44" required>
                <x-field-error name="from" />
            </div>
            <div class="min-w-0 flex-1 sm:flex-none">
                <label for="to" class="label">To</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="input sm:w-44" required>
                <x-field-error name="to" />
            </div>
            <button type="submit" class="btn btn-primary w-full sm:w-auto">Show</button>
        </form>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($presets as $preset)
                @php($active = $preset['range'][0]->isSameDay($from) && $preset['range'][1]->isSameDay($to))
                <a href="{{ route('reports.labor', ['from' => $preset['range'][0]->toDateString(), 'to' => $preset['range'][1]->toDateString()]) }}"
                   @class([
                       'rounded-md border px-3 py-1.5 text-sm font-medium transition-colors',
                       'border-brand-600 bg-brand-600 text-white' => $active,
                       'border-slate-300 bg-white text-slate-700 hover:border-brand-600 hover:text-brand-700' => ! $active,
                   ])>{{ $preset['label'] }}</a>
            @endforeach
        </div>
    </div>

    <p class="mb-4 text-sm text-slate-600">
        <span class="font-semibold text-slate-800">{{ $period }}</span> · all phones · Labor = 30% of sales after 10% diesel,
        split equally among the operators on duty each day. Voided transactions are not included.
    </p>

    @if (count($open_dates) > 0)
        <div class="mb-6 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <x-icon name="alert" class="size-5 shrink-0" />
            <span>
                {{ count($open_dates) }} {{ Str::plural('day', count($open_dates)) }} not yet closed ({{ collect($open_dates)->map(fn ($date) => $date->format('M j'))->implode(', ') }}).
                Their amounts are marked <strong>Not final</strong> and may still change until End of Day.
            </span>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
        <x-stat label="Labor (final)" icon="wallet" :value="money($totals['final'])" tone="brand" :hint="count($days).' '.Str::plural('day', count($days)).' with operators on duty'" />
        <x-stat label="Not final" icon="alert" :value="money($totals['pending'])" :tone="$totals['pending'] > 0 ? 'danger' : 'default'" hint="From days not yet closed" />
        <x-stat label="Operators" icon="team" :value="(string) count($operators)" :hint="'Total '.money($totals['total'])" />
    </div>

    <h2 class="section-title">Per operator</h2>
    <div class="table-wrap mb-8 max-w-3xl">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Operator</th>
                    <th class="num">Days on duty</th>
                    <th class="num">Labor share (final)</th>
                    @if ($totals['pending'] > 0)
                        <th class="num">Not final</th>
                        <th class="num">Total</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($operators as $operator)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="font-medium">{{ $operator['name'] }}</td>
                        <td class="num">{{ $operator['days'] }}</td>
                        <td class="num font-semibold">{{ money($operator['final']) }}</td>
                        @if ($totals['pending'] > 0)
                            <td class="num text-amber-700">{{ money($operator['pending']) }}</td>
                            <td class="num">{{ money($operator['total']) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No operators on duty in this period.</td></tr>
                @endforelse
            </tbody>
            @if (count($operators) > 0)
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num">{{ money($totals['final']) }}</td>
                        @if ($totals['pending'] > 0)
                            <td class="num">{{ money($totals['pending']) }}</td>
                            <td class="num">{{ money($totals['total']) }}</td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @if (count($days) > 0)
        <h2 class="section-title">Per day</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="col-no">#</th>
                        <th>Date</th>
                        @foreach ($names as $name)
                            <th class="num">{{ $name }}</th>
                        @endforeach
                        <th class="num">Day total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $day)
                        <tr>
                            <td class="col-no">{{ $loop->iteration }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('reports.daily', ['date' => $day['date']->toDateString()]) }}" class="text-brand-600 hover:underline">{{ $day['date']->format('M j, D') }}</a>
                                @unless ($day['final'])
                                    <span class="badge ml-1 bg-amber-100 text-amber-800">Not final</span>
                                @endunless
                            </td>
                            @foreach ($names as $name)
                                <td class="num">{{ isset($day['shares'][$name]) ? money($day['shares'][$name]) : '—' }}</td>
                            @endforeach
                            <td class="num font-semibold">{{ money($day['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">Total</td>
                        @foreach ($operators as $operator)
                            <td class="num">{{ money($operator['total']) }}</td>
                        @endforeach
                        <td class="num">{{ money($totals['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-500">— = not on duty that day. Click a date to see its transactions on the Daily Report.</p>
    @endif
</x-layouts.app>
