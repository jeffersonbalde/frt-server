<x-layouts.app title="Devices">
    <p class="mb-4 text-sm text-slate-600">
        Each phone gets a letter code the first time someone logs in on it. Receipt numbers start with that code
        (for example A-000001), so phones never produce the same number.
    </p>

    <div class="table-wrap max-w-4xl">
        <table class="table">
            <thead><tr><th class="col-no">#</th><th>Code</th><th>Phone</th><th>Last user</th><th class="num">Transactions</th><th>Last sync</th><th>Registered</th></tr></thead>
            <tbody>
                @forelse ($devices as $device)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="tabular-nums font-semibold">{{ $device->code }}</td>
                        <td>{{ $device->name ?? '—' }}</td>
                        <td>{{ $device->lastUser?->name ?? '—' }}</td>
                        <td class="num">{{ $device->transactions_count }}</td>
                        <td>{{ $device->last_sync_at?->format('M j, Y h:i A') ?? 'Never' }}</td>
                        <td>{{ $device->created_at->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-500">No phone has logged in yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
