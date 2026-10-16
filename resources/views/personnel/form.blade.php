<x-layouts.app :title="$personnel->exists ? 'Edit personnel' : 'Add personnel'">
    <form method="POST"
          action="{{ $personnel->exists ? route('personnel.update', $personnel) : route('personnel.store') }}"
          class="card max-w-lg space-y-4">
        @csrf
        @if ($personnel->exists)
            @method('PUT')
        @endif

        <div>
            <label for="name" class="label">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $personnel->name) }}" class="input" required>
            <x-field-error name="name" />
        </div>

        <div>
            <label for="position" class="label">Position</label>
            <select id="position" name="position" class="input" required>
                @foreach (\App\Models\Personnel::POSITIONS as $position)
                    <option value="{{ $position }}" @selected(old('position', $personnel->position) === $position)>{{ $position }}</option>
                @endforeach
            </select>
            <x-field-error name="position" />
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $personnel->is_active))>
            Active (shown on Start of Day)
        </label>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="{{ route('personnel.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-layouts.app>
