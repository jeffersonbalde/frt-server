<x-layouts.app title="Dashboard">
    <x-slot:actions>
        <a href="{{ route('reports.daily') }}" class="btn btn-primary">Today's report</a>
    </x-slot:actions>

    <section class="mb-8">
        <h2 class="section-title">Today · {{ today()->format('F j, Y') }} · closed days</h2>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-stat label="Total sales" icon="cash" :value="money($today['total_sales'])" tone="brand" :hint="$today['transactions'].' transactions'" />
            <x-stat label="Rice milled" icon="scale" :value="kg($today['kg']).' kg'" :hint="number_format($today['sacks']).' sacks'" />
            <x-stat label="Net" icon="trend" :value="money($today['net'])" tone="success" :hint="'Diesel '.money($today['fuel']).' · Labor '.money($today['labor'])" />
            <x-stat label="Variance" icon="alert" :value="money($today['variance'])" :tone="$today['variance'] === 0 ? 'default' : 'danger'" hint="From closed End of Day" />
        </div>
    </section>

    <section class="mb-8">
        <h2 class="section-title">This month · {{ today()->format('F Y') }}</h2>
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-stat label="Total sales" icon="cash" :value="money($month['total_sales'])" tone="brand" :hint="$month['transactions'].' transactions'" />
            <x-stat label="Rice milled" icon="scale" :value="kg($month['kg']).' kg'" :hint="number_format($month['sacks']).' sacks'" />
            <x-stat label="Net" icon="trend" :value="money($month['net'])" tone="success" :hint="'Diesel '.money($month['fuel']).' · Labor '.money($month['labor'])" />
            <x-stat label="Tahop on hand" icon="leaf" :value="kg(max(0, $tahop['kg_on_hand'])).' kg'" :hint="'Income this month '.money($month['tahop_income']).' · taken '.kg($tahop['kg_taken']).' kg · sold '.kg($tahop['kg_sold']).' kg'" />
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 [&>*]:min-w-0">
        <section>
            <h2 class="section-title">Phones</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="col-no">#</th><th>Device</th><th>Last user</th><th>Last sync</th></tr></thead>
                    <tbody>
                        @forelse ($devices as $device)
                            <tr>
                                <td class="col-no">{{ $loop->iteration }}</td>
                                <td class="font-semibold">{{ $device->code }} <span class="font-normal text-slate-500">{{ $device->name }}</span></td>
                                <td>{{ $device->lastUser?->name ?? '—' }}</td>
                                <td>{{ $device->last_sync_at?->diffForHumans() ?? 'Never' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-slate-500">No phone has logged in yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h2 class="section-title">Needs attention</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="col-no">#</th><th>Date</th><th>Device</th><th>Issue</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($openSessions as $session)
                            <tr>
                                <td class="col-no">{{ $loop->iteration }}</td>
                                <td>{{ $session->business_date->format('M j, Y') }}</td>
                                <td>{{ $session->device->code }}</td>
                                <td><span class="badge bg-amber-100 text-amber-800">Day not closed yet</span></td>
                                <td class="text-right"><a href="{{ route('closings.show', $session) }}" class="text-brand-600 hover:underline">View</a></td>
                            </tr>
                        @endforeach
                        @foreach ($varianceSessions as $session)
                            <tr>
                                <td class="col-no">{{ $openSessions->count() + $loop->iteration }}</td>
                                <td>{{ $session->business_date->format('M j, Y') }}</td>
                                <td>{{ $session->device->code }}</td>
                                <td><span class="badge bg-red-100 text-red-700">Variance {{ money($session->variance) }}</span></td>
                                <td class="text-right"><a href="{{ route('closings.show', $session) }}" class="text-brand-600 hover:underline">View</a></td>
                            </tr>
                        @endforeach
                        @if ($openSessions->isEmpty() && $varianceSessions->isEmpty())
                            <tr><td colspan="5" class="text-center text-slate-500">All synced days are closed and balanced.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
