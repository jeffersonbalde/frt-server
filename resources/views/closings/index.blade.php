<x-layouts.app title="End of Day">
    <x-slot:actions>
        @foreach ([null => 'All', 'open' => 'Open', 'closed' => 'Closed', 'variance' => 'With variance'] as $value => $label)
            <a href="{{ route('closings.index', $value ? ['status' => $value] : []) }}"
               @class(['btn', 'btn-primary' => $filter === ($value ?: null), 'btn-secondary' => $filter !== ($value ?: null)])>{{ $label }}</a>
        @endforeach
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th class="col-no">#</th><th>Date</th><th>Device</th><th>Recorder</th><th>Operators</th><th class="num">Transactions</th>
                    <th class="num">Expected Cash</th><th class="num">Cash Counted</th><th class="num">Total Sales</th><th class="num">Variance</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td class="col-no">{{ $sessions->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('closings.show', $session) }}" class="font-semibold text-brand-600 hover:underline">{{ $session->business_date->format('M j, Y') }}</a></td>
                        <td>{{ $session->device->code }}</td>
                        <td>{{ $session->recorder?->name ?? '—' }}</td>
                        <td>{{ implode(', ', $session->operatorNames()) }}</td>
                        <td class="num">{{ $session->transactions_count }}</td>
                        <td class="num">{{ $session->status === 'closed' ? money($session->opening_fund + $session->total_collected) : '—' }}</td>
                        <td class="num">{{ $session->status === 'closed' ? money($session->cash_counted) : '—' }}</td>
                        <td class="num">{{ $session->status === 'closed' ? money($session->total_sales) : '—' }}</td>
                        <td @class(['num font-semibold', 'text-red-600' => $session->variance])>{{ $session->status === 'closed' ? money($session->variance) : '—' }}</td>
                        <td>
                            @if ($session->status === 'closed')
                                <span class="badge bg-green-100 text-green-800">Closed</span>
                            @else
                                <span class="badge bg-amber-100 text-amber-800">Open</span>
                            @endif
                            @if ($session->reopen_count > 0)
                                <span class="badge bg-slate-200 text-slate-700" title="Reopened with the owner approval code">Reopened ×{{ $session->reopen_count }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-8 text-center text-slate-500">No days found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
</x-layouts.app>
