@php
    $period = $from->isSameDay($to) ? $from->format('M j, Y') : $from->format('M j').' – '.$to->format('M j, Y');
    $query = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $names = array_column($operators, 'name');
@endphp

<x-layouts.app title="Labor Report">
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

    <p class="mb-2 text-sm text-slate-600">
        <span class="font-semibold text-slate-800">{{ $period }}</span> · all phones · closed days only · Labor = 30% of sales
        after 10% diesel, split equally among the operators on duty each day. Voided transactions are not included.
    </p>
    @if (count($open_dates) > 0)
        <p class="mb-4 flex items-center gap-1.5 text-sm text-slate-500">
            <x-icon name="moon" class="size-4 shrink-0" />
            <span>Not counted yet (no End of Day): {{ collect($open_dates)->map(fn ($date) => $date->format('M j'))->implode(', ') }}</span>
        </p>
    @endif

    <h2 class="section-title mt-6">Per operator</h2>
    <div class="table-wrap mb-8 max-w-2xl">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Operator</th>
                    <th class="num">Days on duty</th>
                    <th class="num">Labor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($operators as $operator)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="font-medium">{{ $operator['name'] }}</td>
                        <td class="num">{{ $operator['days'] }}</td>
                        <td class="num font-semibold">{{ money($operator['share']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">No closed days with operators in this period.</td></tr>
                @endforelse
            </tbody>
            @if (count($operators) > 0)
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="num">{{ money($total) }}</td>
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
                            <td class="num">{{ money($operator['share']) }}</td>
                        @endforeach
                        <td class="num">{{ money($total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-500">— = not on duty that day. Click a date to see its transactions on the Daily Report.</p>
    @endif
</x-layouts.app>
