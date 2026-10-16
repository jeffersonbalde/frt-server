<x-layouts.app title="Accounts">
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="btn btn-primary">Add account</a>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-600">
        Operators log in to the FRT app on their phone once with internet, then set a PIN for offline use.
        Deactivating an account or changing its password signs it out of every phone on the next sync.
    </p>

    <div class="table-wrap max-w-4xl">
        <table class="table">
            <thead><tr><th class="col-no">#</th><th>Name</th><th>Username</th><th>Role</th><th class="num">Signed-in phones</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class="col-no">{{ $loop->iteration }}</td>
                        <td class="font-semibold">{{ $user->name }}</td>
                        <td class="tabular-nums">{{ $user->username }}</td>
                        <td>{{ $user->role->label() }}</td>
                        <td class="num">{{ $user->tokens_count }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge bg-green-100 text-green-800">Active</span>
                            @else
                                <span class="badge bg-slate-200 text-slate-600">Inactive</span>
                            @endif
                        </td>
                        <td class="text-right"><a href="{{ route('users.edit', $user) }}" class="text-brand-600 hover:underline">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
