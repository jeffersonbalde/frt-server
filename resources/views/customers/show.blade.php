<x-layouts.app :title="$customer->name">
    <x-slot:actions>
        <a href="{{ route('customers.index') }}" class="btn btn-secondary">All customers</a>
    </x-slot:actions>

    <section class="card mb-6 max-w-xl">
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-slate-500">Address</dt><dd class="col-span-2">{{ $customer->address ?? '—' }}</dd>
            <dt class="text-slate-500">Contact no.</dt><dd class="col-span-2">{{ $customer->contact_no ?? '—' }}</dd>
            <dt class="text-slate-500">Facebook</dt><dd class="col-span-2">{{ $customer->facebook ?? '—' }}</dd>
            <dt class="text-slate-500">Age</dt><dd class="col-span-2">{{ $customer->age ?? '—' }}</dd>
        </dl>
    </section>

    <h2 class="section-title">Transaction history</h2>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th class="col-no">#</th><th>Date</th><th>Trans. No</th><th>Recorded By</th><th>Service</th><th>Location</th><th class="num">Sacks</th><th class="num">Kg</th><th class="num">Total Amount</th><th class="num">Amount Due</th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $t)
                    <tr @class(['text-slate-400 line-through' => $t->status !== 'valid'])>
                        <td class="col-no">{{ $transactions->firstItem() + $loop->index }}</td>
                        <td>{{ $t->recorded_at->format('M j, Y h:i A') }}</td>
                        <td class="tabular-nums">{{ $t->receipt_no }}</td>
                        <td>{{ $t->recorder?->name ?? '—' }}</td>
                        <td>{{ $t->typeLabel() }}</td>
                        <td>{{ $t->location ?? '—' }}</td>
                        <td class="num">{{ $t->sack_count ?: '—' }}</td>
                        <td class="num">{{ kg($t->total_kg) }}</td>
                        <td class="num">{{ money($t->gross) }}</td>
                        <td class="num">{{ money($t->amount_due) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-6 text-center text-slate-500">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.app>
