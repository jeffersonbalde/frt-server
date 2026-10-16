<x-layouts.app title="Customers">
    <x-slot:actions>
        <form method="GET" action="{{ route('customers.index') }}" class="flex w-full gap-2 sm:w-auto">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, address, contact" class="input min-w-0 flex-1 sm:w-64 sm:flex-none">
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">Customer Information Sheets recorded on the phones.</p>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th class="col-no">#</th><th>Name</th><th>Address</th><th>Contact no.</th><th>Facebook</th><th class="num">Age</th><th class="num">Transactions</th><th class="num">Total amount</th></tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="col-no">{{ $customers->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('customers.show', $customer) }}" class="font-semibold text-brand-600 hover:underline">{{ $customer->name }}</a></td>
                        <td>{{ $customer->address ?? '—' }}</td>
                        <td>{{ $customer->contact_no ?? '—' }}</td>
                        <td>{{ $customer->facebook ?? '—' }}</td>
                        <td class="num">{{ $customer->age ?? '—' }}</td>
                        <td class="num">{{ $customer->transactions_count }}</td>
                        <td class="num">{{ money($customer->transactions_sum_gross) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.app>
