<x-layouts.app title="Voided Transactions">
    <x-slot:actions>
        <form method="GET" action="{{ route('voids.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="from" class="label">From</label>
                <input id="from" name="from" type="date" value="{{ $from }}" class="input w-auto">
            </div>
            <div>
                <label for="to" class="label">To</label>
                <input id="to" name="to" type="date" value="{{ $to }}" class="input w-auto">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            @if ($from || $to)
                <a href="{{ route('voids.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </form>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">
        Operators can only void a transaction after entering the owner approval code (set in Settings). Voided transactions do not count in sales.
    </p>
    <x-field-error name="to" />

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th>
                    <th>Trans. No</th><th>Business date</th><th>Device</th><th>Customer</th><th>Service</th>
                    <th class="num">Amount Due</th><th>Recorded by</th><th>Voided at</th><th>Voided by</th><th>Approval</th><th>Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($voids as $t)
                    <tr>
                        <td class="col-no">{{ $voids->firstItem() + $loop->index }}</td>
                        <td class="tabular-nums font-semibold">{{ $t->receipt_no }}</td>
                        <td>{{ $t->business_date->format('M j, Y') }}</td>
                        <td>{{ $t->device->code }}</td>
                        <td>{{ $t->customer_name ?? '—' }}</td>
                        <td>{{ $t->typeLabel() }}</td>
                        <td class="num">{{ money($t->amount_due) }}</td>
                        <td>{{ $t->recorder?->name ?? '—' }}</td>
                        <td>{{ $t->voided_at?->format('M j, Y h:i A') ?? '—' }}</td>
                        <td>{{ $t->voider?->name ?? '—' }}</td>
                        <td>
                            @if ($t->voided_by)
                                <span class="badge bg-green-100 text-green-800">Owner code</span>
                            @else
                                <span class="badge bg-slate-200 text-slate-600">Before approval codes</span>
                            @endif
                        </td>
                        <td class="max-w-xs whitespace-normal">{{ $t->void_reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="py-8 text-center text-slate-500">No voided transactions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $voids->links() }}</div>
</x-layouts.app>
