<x-layouts.app title="Personnel">
    <x-slot:actions>
        <a href="{{ route('personnel.create') }}" class="btn btn-primary">Add personnel</a>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">
        Active personnel appear on the phones' Start of Day screen, where the operators on duty are chosen.
        The labor share is split equally among the operators chosen for that day.
    </p>

    <div class="table-wrap max-w-3xl">
        <table class="table">
            <thead><tr><th class="col-no">#</th><th>Name</th><th>Position</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($personnel as $person)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="font-semibold">{{ $person->name }}</td>
                        <td>{{ $person->position }}</td>
                        <td>
                            @if ($person->is_active)
                                <span class="badge bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="badge bg-slate-200 text-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="text-right"><a href="{{ route('personnel.edit', $person) }}" class="text-brand-600 hover:underline">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">No personnel yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
