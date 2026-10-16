<x-layouts.app :title="$user->exists ? 'Edit account' : 'Add account'">
    <form method="POST"
          action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
          class="card max-w-lg space-y-4">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" class="input" required>
            <x-field-error name="name" />
        </div>

        <div>
            <label for="username" class="label">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username', $user->username) }}" class="input" required autocomplete="off">
            <x-field-error name="username" />
        </div>

        <div>
            <label for="role" class="label">Role</label>
            <select id="role" name="role" class="input" required>
                @foreach (\App\Enums\Role::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <x-field-error name="role" />
        </div>

        <div>
            <label for="password" class="label">{{ $user->exists ? 'New password (leave blank to keep)' : 'Password' }}</label>
            <input id="password" name="password" type="password" class="input" @required(! $user->exists) autocomplete="new-password">
            <x-field-error name="password" />
        </div>

        <div>
            <label for="password_confirmation" class="label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" class="input" @required(! $user->exists) autocomplete="new-password">
        </div>

        @if ($user->exists)
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $user->is_active))>
                Active
            </label>
        @endif

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
